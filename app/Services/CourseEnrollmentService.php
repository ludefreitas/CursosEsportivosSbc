<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Services\AuditLogService;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class CourseEnrollmentService
{
    private const ACTIVE_STATUSES = ['aguardando_matricula', 'matriculada'];
    private const STATUS_LABELS = [
        'aguardando_matricula' => 'Aguardando matrícula',
        'matriculada' => 'Matriculada',
        'lista_espera' => 'Lista de espera',
        'cancelada' => 'Cancelada',
        'excluida' => 'Excluída',
        'excluida_por_falta' => 'Excluída por falta',
        'desistente' => 'Desistente',
        'encerrada' => 'Encerrada',
    ];

    public function listOpenClasses(): array
    {
        $pdo = Database::connection();
        $this->closeExpiredEnrollments($pdo);
        $stmt = $pdo->query("\n            SELECT t.id, t.nome, t.idade_minima, t.idade_maxima, t.vagas_totais, t.vagas_geral,\n                   t.vagas_pcd, t.vagas_plm, t.vagas_pvs,\n                   te.id AS temporada_id, te.nome AS temporada_nome, te.data_inicio, te.data_fim,\n                   te.inscricoes_inicio, te.inscricoes_fim, te.permitir_inscricao_por_cpf,\n                   te.permitir_inscricao_logada, m.nome AS modalidade_nome, l.nome_local,\n                   COALESCE(l.apelido_local, l.nome_local) AS local_nome, e.nome AS espaco_nome,\n                   nm.nome AS nivel_nome\n            FROM turmas t\n            INNER JOIN temporadas te ON te.id = t.temporada_id\n            INNER JOIN modalidades m ON m.id = t.modalidade_id\n            INNER JOIN locais_treino l ON l.id = t.local_treino_id\n            INNER JOIN espacos_treino e ON e.id = t.espaco_treino_id\n            LEFT JOIN niveis_modalidade nm ON nm.id = t.nivel_modalidade_id\n            WHERE t.ativo = 1 AND te.ativo = 1 AND l.ativo = 1 AND e.ativo = 1\n              AND CURDATE() BETWEEN te.data_inicio AND te.data_fim\n              AND (te.inscricoes_inicio IS NULL OR NOW() >= te.inscricoes_inicio)\n              AND (te.inscricoes_fim IS NULL OR NOW() <= te.inscricoes_fim)\n            ORDER BY te.data_inicio ASC, m.nome ASC, t.nome ASC\n        ");

        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($classes as &$class) {
            $class['vagas_disponiveis'] = $this->availableSeats($pdo, $class);
        }
        unset($class);
        return $classes;
    }

    public function listForAuthenticatedAccount(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("\n            SELECT i.id, i.status, i.created_at, i.updated_at, i.motivo_status,\n                   p.nome_completo, p.cpf, t.nome AS turma_nome, te.nome AS temporada_nome,\n                   m.nome AS modalidade_nome, COALESCE(l.apelido_local, l.nome_local) AS local_nome\n            FROM inscricoes_turma i\n            INNER JOIN pessoas p ON p.id = i.pessoa_id\n            INNER JOIN turmas t ON t.id = i.turma_id\n            INNER JOIN temporadas te ON te.id = t.temporada_id\n            INNER JOIN modalidades m ON m.id = t.modalidade_id\n            INNER JOIN locais_treino l ON l.id = t.local_treino_id\n            WHERE EXISTS (\n                SELECT 1 FROM contas c\n                INNER JOIN pessoas titular ON titular.cpf = c.cpf\n                LEFT JOIN vinculos_responsaveis vr ON vr.responsavel_pessoa_id = titular.id\n                WHERE c.id = :conta_id AND (p.id = titular.id OR p.id = vr.dependente_pessoa_id)\n            )\n            ORDER BY i.created_at DESC\n        ");
        $stmt->execute([':conta_id' => Auth::id()]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['status_label'] = self::STATUS_LABELS[(string) $row['status']] ?? (string) $row['status'];
        }
        return $rows;
    }

    public function listForManagement(): array
    {
        $pdo = Database::connection();
        $this->closeExpiredEnrollments($pdo);
        $stmt = $pdo->query("SELECT i.id, i.status, i.created_at, i.motivo_status, p.nome_completo, p.cpf, t.nome AS turma_nome, te.nome AS temporada_nome, m.nome AS modalidade_nome FROM inscricoes_turma i INNER JOIN pessoas p ON p.id = i.pessoa_id INNER JOIN turmas t ON t.id = i.turma_id INNER JOIN temporadas te ON te.id = t.temporada_id INNER JOIN modalidades m ON m.id = t.modalidade_id WHERE i.status IN ('aguardando_matricula', 'lista_espera', 'matriculada') ORDER BY i.status ASC, i.created_at ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['status_label'] = self::STATUS_LABELS[(string) $row['status']] ?? (string) $row['status'];
        }
        return $rows;
    }

    public function listPeopleForAuthenticatedAccount(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("\n            SELECT DISTINCT p.id, p.nome_completo, p.cpf, p.data_nascimento\n            FROM contas c\n            INNER JOIN pessoas titular ON titular.cpf = c.cpf\n            INNER JOIN pessoas p ON p.id = titular.id\n                OR EXISTS (SELECT 1 FROM vinculos_responsaveis vr WHERE vr.responsavel_pessoa_id = titular.id AND vr.dependente_pessoa_id = p.id)\n            WHERE c.id = :conta_id\n            ORDER BY p.nome_completo ASC\n        ");
        $stmt->execute([':conta_id' => Auth::id()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function enroll(array $data): array
    {
        $classId = (int) ($data['turma_id'] ?? 0);
        $personId = (int) ($data['pessoa_id'] ?? 0);
        $cpf = normalize_cpf((string) ($data['cpf'] ?? ''));
        $termsAccepted = (int) ($data['aceite_termos'] ?? 0) === 1;

        if ($classId <= 0 || !$termsAccepted) {
            throw new RuntimeException('Selecione uma turma e aceite os termos para continuar.');
        }

        $pdo = Database::connection();
        $class = $this->findClass($pdo, $classId);
        $season = $this->findSeason($pdo, (int) $class['temporada_id']);
        $now = new DateTimeImmutable();
        $window = $this->findCurrentWindow($pdo, (int) $season['id'], (int) $class['modalidade_id'], $now);

        if (!$window && !$this->withinSeasonEnrollment($season, $now)) {
            throw new RuntimeException('As inscrições para esta temporada não estão abertas no momento.');
        }

        $person = null;
        if ($personId > 0) {
            if (!Auth::check()) {
                throw new RuntimeException('Faça login para inscrever uma pessoa vinculada.');
            }
            $person = $this->findAuthorizedPerson($pdo, $personId);
        } else {
            if (!validar_cpf($cpf)) {
                throw new RuntimeException('Informe um CPF válido.');
            }
            $stmt = $pdo->prepare('SELECT * FROM pessoas WHERE cpf = :cpf LIMIT 1');
            $stmt->execute([':cpf' => $cpf]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$person) {
                throw new RuntimeException('Este CPF não está cadastrado no sistema.');
            }
            if (!Auth::check() && empty($season['permitir_inscricao_por_cpf'])) {
                throw new RuntimeException('Esta temporada exige login para realizar inscrições.');
            }
            if (!Auth::check() && calculate_age((string) $person['data_nascimento']) < 18) {
                throw new RuntimeException('A inscrição de menor de idade deve ser feita pelo responsável logado.');
            }
            if (Auth::check()) {
                $person = $this->findAuthorizedPerson($pdo, (int) $person['id']);
            }
        }

        if (!$person || (int) ($person['cadastro_completo'] ?? 0) !== 1) {
            throw new RuntimeException('A pessoa precisa ter o cadastro completo para se inscrever.');
        }

        if (!empty($season['permitir_inscricao_logada']) === false && Auth::check()) {
            throw new RuntimeException('Esta temporada não permite inscrições pelo sistema logado.');
        }

        $this->validateAge($person, $class);
        $this->validateDuplicate($pdo, $classId, (int) $person['id']);
        $this->validateSeasonLimit($pdo, (int) $season['id'], (int) $class['modalidade_id'], (int) $person['id'], $window);

        $publico = $this->resolvePublic($pdo, (int) $person['id']);
        $forceWaitlist = $window && (int) $window['forcar_lista_espera'] === 1;
        $status = $forceWaitlist || $this->availableSeats($pdo, $class, $publico) <= 0
            ? 'lista_espera'
            : 'aguardando_matricula';

        $stmt = $pdo->prepare("\n            INSERT INTO inscricoes_turma (turma_id, pessoa_id, publico_alvo, status, inscrito_por_conta_id, created_at)\n            VALUES (:turma_id, :pessoa_id, :publico, :status, :conta_id, NOW())\n        ");
        $stmt->execute([
            ':turma_id' => $classId,
            ':pessoa_id' => (int) $person['id'],
            ':publico' => $publico,
            ':status' => $status,
            ':conta_id' => Auth::check() ? Auth::id() : null,
        ]);
        $enrollmentId = (int) $pdo->lastInsertId();

        AuditLogService::record('inscricao_turma.criada', 'inscricoes_turma', $enrollmentId, [
            'turma_id' => $classId,
            'pessoa_id' => (int) $person['id'],
            'status' => $status,
            'conta_id' => Auth::check() ? Auth::id() : null,
        ]);

        return ['id' => $enrollmentId, 'status' => $status, 'status_label' => self::STATUS_LABELS[$status]];
    }

    public function cancel(int $enrollmentId): void
    {
        if (!Auth::check()) {
            throw new RuntimeException('Faça login para cancelar a inscrição.');
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare("\n            SELECT i.* FROM inscricoes_turma i\n            WHERE i.id = :id AND i.status IN ('aguardando_matricula', 'lista_espera', 'matriculada')\n              AND EXISTS (\n                SELECT 1 FROM contas c INNER JOIN pessoas titular ON titular.cpf = c.cpf\n                LEFT JOIN vinculos_responsaveis vr ON vr.responsavel_pessoa_id = titular.id\n                WHERE c.id = :conta_id AND (i.pessoa_id = titular.id OR i.pessoa_id = vr.dependente_pessoa_id)\n              )\n            LIMIT 1\n        ");
        $stmt->execute([':id' => $enrollmentId, ':conta_id' => Auth::id()]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Inscrição não encontrada ou não pertence à sua responsabilidade.');
        }
        $stmt = $pdo->prepare("UPDATE inscricoes_turma SET status = 'cancelada', cancelado_por_conta_id = :conta_id, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $enrollmentId, ':conta_id' => Auth::id()]);
        AuditLogService::record('inscricao_turma.cancelada', 'inscricoes_turma', $enrollmentId, ['conta_id' => Auth::id()]);
    }

    public function changeStatus(int $enrollmentId, string $status, int $accountId, string $reason = ''): void
    {
        $allowedStatuses = ['matriculada', 'excluida', 'excluida_por_falta', 'desistente', 'encerrada'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('Status inválido para atualização da inscrição.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Informe o motivo da alteração da inscrição.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT id, status FROM inscricoes_turma WHERE id = :id AND status IN ('aguardando_matricula', 'lista_espera', 'matriculada') LIMIT 1");
        $stmt->execute([':id' => $enrollmentId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Inscrição não encontrada ou já encerrada.');
        }

        $stmt = $pdo->prepare('UPDATE inscricoes_turma SET status = :status, motivo_status = :motivo, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':status' => $status, ':motivo' => $reason, ':id' => $enrollmentId]);
        AuditLogService::record('inscricao_turma.status_alterado', 'inscricoes_turma', $enrollmentId, [
            'status' => $status,
            'motivo' => $reason,
            'conta_id' => $accountId,
        ]);
    }

    private function findClass(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT t.*, te.nome AS temporada_nome, te.data_inicio AS temporada_inicio, te.data_fim AS temporada_fim FROM turmas t INNER JOIN temporadas te ON te.id = t.temporada_id WHERE t.id = :id AND t.ativo = 1 AND te.ativo = 1 LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new RuntimeException('Turma não encontrada ou indisponível.'); }
        return $row;
    }

    private function closeExpiredEnrollments(PDO $pdo): void
    {
        $pdo->exec("UPDATE inscricoes_turma i INNER JOIN turmas t ON t.id = i.turma_id INNER JOIN temporadas te ON te.id = t.temporada_id SET i.status = 'encerrada', i.motivo_status = 'Temporada encerrada.', i.updated_at = NOW() WHERE te.data_fim < CURDATE() AND i.status IN ('aguardando_matricula', 'matriculada', 'lista_espera')");
    }

    private function findSeason(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT * FROM temporadas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function findCurrentWindow(PDO $pdo, int $seasonId, int $modalityId, DateTimeImmutable $now): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM temporadas_janelas_inscricao WHERE temporada_id = :temporada_id AND ativo = 1 AND data_inicio <= :agora AND data_fim >= :agora AND (modalidade_id = :modalidade_id OR modalidade_id IS NULL) ORDER BY modalidade_id IS NULL ASC, numero_inscricao DESC LIMIT 1");
        $stmt->execute([':temporada_id' => $seasonId, ':modalidade_id' => $modalityId, ':agora' => $now->format('Y-m-d H:i:s')]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function withinSeasonEnrollment(array $season, DateTimeImmutable $now): bool
    {
        if (!empty($season['inscricoes_inicio']) && $now < new DateTimeImmutable((string) $season['inscricoes_inicio'])) { return false; }
        if (!empty($season['inscricoes_fim']) && $now > new DateTimeImmutable((string) $season['inscricoes_fim'])) { return false; }
        return true;
    }

    private function findAuthorizedPerson(PDO $pdo, int $personId): array
    {
        $stmt = $pdo->prepare("SELECT p.* FROM pessoas p WHERE p.id = :person_id AND EXISTS (SELECT 1 FROM contas c INNER JOIN pessoas titular ON titular.cpf = c.cpf LEFT JOIN vinculos_responsaveis vr ON vr.responsavel_pessoa_id = titular.id WHERE c.id = :conta_id AND (p.id = titular.id OR p.id = vr.dependente_pessoa_id)) LIMIT 1");
        $stmt->execute([':person_id' => $personId, ':conta_id' => Auth::id()]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$person) { throw new RuntimeException('A pessoa selecionada não pertence à sua responsabilidade.'); }
        return $person;
    }

    private function validateAge(array $person, array $class): void
    {
        $age = calculate_age((string) $person['data_nascimento']);
        if ($age === null || $age < (int) $class['idade_minima'] || $age > (int) $class['idade_maxima']) {
            throw new RuntimeException('A pessoa não está dentro da faixa etária desta turma.');
        }
    }

    private function validateDuplicate(PDO $pdo, int $classId, int $personId): void
    {
        $stmt = $pdo->prepare("SELECT id FROM inscricoes_turma WHERE turma_id = :turma_id AND pessoa_id = :pessoa_id AND status IN ('aguardando_matricula', 'matriculada', 'lista_espera') LIMIT 1");
        $stmt->execute([':turma_id' => $classId, ':pessoa_id' => $personId]);
        if ($stmt->fetchColumn()) { throw new RuntimeException('Esta pessoa já está inscrita nesta turma.'); }
    }

    private function validateSeasonLimit(PDO $pdo, int $seasonId, int $modalityId, int $personId, ?array $window): void
    {
        $limit = $window ? (int) $window['limite_inscricoes_pessoa'] : 1;
        if ($limit <= 0) { return; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma i INNER JOIN turmas t ON t.id = i.turma_id WHERE t.temporada_id = :temporada_id AND i.pessoa_id = :pessoa_id AND i.status IN ('aguardando_matricula', 'matriculada', 'lista_espera')");
        $stmt->execute([':temporada_id' => $seasonId, ':pessoa_id' => $personId]);
        if ((int) $stmt->fetchColumn() >= $limit) { throw new RuntimeException('O limite de inscrições permitido para esta pessoa neste período já foi atingido.'); }
    }

    private function resolvePublic(PDO $pdo, int $personId): string
    {
        $stmt = $pdo->prepare("SELECT CASE WHEN EXISTS (SELECT 1 FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id WHERE cp.pessoa_id = p.id AND cp.status = 'validado' AND tc.slug = 'pcd') THEN 'pcd' ELSE 'geral' END FROM pessoas p WHERE p.id = :id");
        $stmt->execute([':id' => $personId]);
        return (string) ($stmt->fetchColumn() ?: 'geral');
    }

    private function availableSeats(PDO $pdo, array $class, string $public = 'geral'): int
    {
        $seatKey = in_array($public, ['pcd', 'plm', 'pvs'], true) ? 'vagas_' . $public : 'vagas_geral';
        $capacity = (int) ($class[$seatKey] ?? 0);
        if ($capacity <= 0) { return 0; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id = :turma_id AND publico_alvo = :publico AND status IN ('aguardando_matricula', 'matriculada')");
        $stmt->execute([':turma_id' => (int) $class['id'], ':publico' => $public]);
        return max(0, $capacity - (int) $stmt->fetchColumn());
    }
}
