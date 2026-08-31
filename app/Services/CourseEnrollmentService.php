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
    private static bool $courseAgeCriterionSchemaChecked = false;
    private static bool $courseSeasonSchemaChecked = false;
    private const ACTIVE_STATUSES = ['aguardando_matricula', 'matriculada'];
    private const STATUS_LABELS = [
        'aguardando_matricula' => 'Aguardando matrícula',
        'matriculada' => 'Matriculada',
        'lista_espera' => 'Lista de espera',
        'cancelada' => 'Cancelada',
        'excluida' => 'Excluída',
        'excluida_por_falta' => 'Excluída por falta',
        'desistente' => 'Desistente',
        'suspensa' => 'Suspensa',
    ];

    public function listOpenClasses(?int $locationId = null, ?int $modalityId = null): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $this->ensureCourseAgeCriterionSchema($pdo);
        $sql = "\n            SELECT t.id, t.nome, t.idade_minima, t.idade_maxima, t.vagas_totais, t.vagas_geral,\n                   t.vagas_pcd, t.vagas_plm, t.vagas_pvs, t.modalidade_id, t.local_treino_id,\n                   te.id AS temporada_id, te.nome AS temporada_nome, cm.data_inicio, cm.data_fim,\n                   cm.inscricoes_inicio, cm.inscricoes_fim, cm.matriculas_inicio, cm.matriculas_fim,\n                   cm.permitir_inscricao_periodo_matricula, te.permitir_inscricao_por_cpf,\n                   te.permitir_inscricao_logada, m.nome AS modalidade_nome, l.nome_local,\n                   COALESCE(l.apelido_local, l.nome_local) AS local_nome, e.nome AS espaco_nome,\n                   nm.nome AS nivel_nome\n            FROM turmas t\n            INNER JOIN temporadas te ON te.id = t.temporada_id\n            INNER JOIN cronogramas_modalidade cm ON cm.id = t.cronograma_modalidade_id\n            INNER JOIN modalidades m ON m.id = t.modalidade_id\n            INNER JOIN locais_treino l ON l.id = t.local_treino_id\n            INNER JOIN espacos_treino e ON e.id = t.espaco_treino_id\n            LEFT JOIN niveis_modalidade nm ON nm.id = t.nivel_modalidade_id\n            WHERE t.ativo = 1 AND te.ativo = 1 AND te.status = 'ativa' AND m.ativo = 1 AND l.ativo = 1 AND e.ativo = 1\n              AND CURDATE() BETWEEN cm.data_inicio AND cm.data_fim\n              AND (\n                    ((cm.inscricoes_inicio IS NULL OR NOW() >= cm.inscricoes_inicio)\n                     AND (cm.inscricoes_fim IS NULL OR NOW() <= cm.inscricoes_fim))\n                    OR\n                    (cm.permitir_inscricao_periodo_matricula = 1\n                     AND (cm.matriculas_inicio IS NULL OR NOW() >= cm.matriculas_inicio)\n                     AND (cm.matriculas_fim IS NULL OR NOW() <= cm.matriculas_fim))\n              )";
        $params = [];
        if (($locationId ?? 0) > 0) {
            $sql .= ' AND t.local_treino_id = :local_id';
            $params[':local_id'] = $locationId;
        }
        if (($modalityId ?? 0) > 0) {
            $sql .= ' AND t.modalidade_id = :modalidade_id';
            $params[':modalidade_id'] = $modalityId;
        }
        $sql .= ' ORDER BY te.data_inicio ASC, m.nome ASC, t.nome ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($classes as &$class) {
            $criterionStmt = $pdo->prepare('SELECT criterio_faixa_etaria, sexo, dias_semana, hora_inicio, hora_fim FROM turmas WHERE id = :id LIMIT 1');
            $criterionStmt->execute([':id' => (int) $class['id']]);
            $eligibility = $criterionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $class['criterio_faixa_etaria'] = normalize_age_rule_mode((string) ($eligibility['criterio_faixa_etaria'] ?? 'idade_exata'));
            $class['sexo'] = (string) ($eligibility['sexo'] ?? '');
            $class['dias_semana'] = (string) ($eligibility['dias_semana'] ?? '');
            $class['dias_semana_descricao'] = $this->describeClassWeekdays((string) ($eligibility['dias_semana'] ?? ''));
            $class['hora_inicio'] = (string) ($eligibility['hora_inicio'] ?? '');
            $class['hora_fim'] = (string) ($eligibility['hora_fim'] ?? '');
            $class['periodo_dia'] = $this->describeClassDayPeriod((string) ($eligibility['hora_inicio'] ?? ''));
            $class['vagas_disponiveis'] = $this->availableSeats($pdo, $class);
            $class['faixa_etaria_descricao'] = $this->describeClassAgeRule($class);
        }
        unset($class);
        return $classes;
    }

    public function listOpenModalitiesByLocation(int $locationId): array
    {
        if ($locationId <= 0) {
            throw new RuntimeException('Selecione um local válido.');
        }
        $items = [];
        foreach ($this->listOpenClasses($locationId) as $class) $items[(int) $class['modalidade_id']] = ['id' => (int) $class['modalidade_id'], 'nome' => (string) $class['modalidade_nome']];
        $items = array_values($items);
        usort($items, fn(array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));
        return $items;
    }

    public function listOpenLocationsByModality(int $modalityId): array
    {
        if ($modalityId <= 0) {
            throw new RuntimeException('Selecione uma modalidade válida.');
        }

        $items = [];
        foreach ($this->listOpenClasses(null, $modalityId) as $class) $items[(int) $class['local_treino_id']] = ['id' => (int) $class['local_treino_id'], 'nome_local' => (string) $class['nome_local'], 'apelido_local' => (string) $class['local_nome']];
        $items = array_values($items);
        usort($items, fn(array $a, array $b): int => strcasecmp($a['apelido_local'], $b['apelido_local']));
        return $items;
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
            $seasonStmt = $pdo->prepare('SELECT te.status FROM inscricoes_turma i INNER JOIN turmas t ON t.id = i.turma_id INNER JOIN temporadas te ON te.id = t.temporada_id WHERE i.id = :id LIMIT 1');
            $seasonStmt->execute([':id' => (int) $row['id']]);
            $row['temporada_encerrada'] = $seasonStmt->fetchColumn() === 'encerrada';
        }
        return $rows;
    }

    public function listForManagement(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query("SELECT i.id, i.status, i.created_at, i.motivo_status, p.nome_completo, p.cpf, p.eh_pcd, p.eh_pvs, p.eh_plm, t.nome AS turma_nome, te.nome AS temporada_nome, m.nome AS modalidade_nome FROM inscricoes_turma i INNER JOIN pessoas p ON p.id = i.pessoa_id INNER JOIN turmas t ON t.id = i.turma_id INNER JOIN temporadas te ON te.id = t.temporada_id INNER JOIN modalidades m ON m.id = t.modalidade_id WHERE i.status IN ('aguardando_matricula', 'lista_espera', 'matriculada') ORDER BY i.status ASC, i.created_at ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['status_label'] = self::STATUS_LABELS[(string) $row['status']] ?? (string) $row['status'];
            $conditions = [];
            if ((int) ($row['eh_pcd'] ?? 0) === 1) { $conditions[] = 'PCD'; }
            if ((int) ($row['eh_pvs'] ?? 0) === 1) { $conditions[] = 'PVS'; }
            if ((int) ($row['eh_plm'] ?? 0) === 1) { $conditions[] = 'PLM'; }
            $row['condicoes'] = implode(', ', $conditions);
        }
        return $rows;
    }

    public function listSeasonsForManagement(): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $stmt = $pdo->query('SELECT * FROM temporadas ORDER BY data_inicio DESC, id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSeasonOriginsForManagement(): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $stmt = $pdo->query('SELECT id, nome, ativo FROM origens_temporada ORDER BY ativo DESC, nome ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveSeasonOrigin(int $accountId, array $data): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['nome'] ?? ''));
        $active = (!isset($data['ativo']) || (int) $data['ativo'] === 1) ? 1 : 0;

        if ($name === '') {
            throw new RuntimeException('Informe o nome da instituição de origem.');
        }
        if (mb_strlen($name) > 180) {
            throw new RuntimeException('O nome da instituição deve ter no máximo 180 caracteres.');
        }

        $duplicate = $pdo->prepare('SELECT id FROM origens_temporada WHERE nome = :nome AND id <> :id LIMIT 1');
        $duplicate->execute([':nome' => $name, ':id' => $id]);
        if ($duplicate->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Já existe uma origem da temporada com este nome.');
        }

        if ($id > 0) {
            $exists = $pdo->prepare('SELECT id FROM origens_temporada WHERE id = :id LIMIT 1');
            $exists->execute([':id' => $id]);
            if (!$exists->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('A origem da temporada informada não foi encontrada.');
            }
            $stmt = $pdo->prepare('UPDATE origens_temporada SET nome = :nome, ativo = :ativo WHERE id = :id LIMIT 1');
            $stmt->execute([':nome' => $name, ':ativo' => $active, ':id' => $id]);
            $syncSeasons = $pdo->prepare('UPDATE temporadas SET origem_temporada = :nome WHERE origem_temporada_id = :id');
            $syncSeasons->execute([':nome' => $name, ':id' => $id]);
            AuditLogService::record('origem_temporada.atualizada', 'origens_temporada', $id, ['conta_id' => $accountId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO origens_temporada (nome, ativo) VALUES (:nome, :ativo)');
            $stmt->execute([':nome' => $name, ':ativo' => $active]);
            $id = (int) $pdo->lastInsertId();
            AuditLogService::record('origem_temporada.criada', 'origens_temporada', $id, ['conta_id' => $accountId]);
        }

        return ['id' => $id, 'nome' => $name, 'ativo' => $active];
    }

    public function deleteSeasonOrigin(int $accountId, int $originId): void
    {
        if ($originId <= 0) {
            throw new RuntimeException('Não foi possível identificar a origem da temporada que será excluída.');
        }

        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $origin = $this->findSeasonOrigin($pdo, $originId);
        if (!$origin) {
            throw new RuntimeException('A origem da temporada informada não foi encontrada.');
        }

        $usage = $pdo->prepare('SELECT COUNT(*) FROM temporadas WHERE origem_temporada_id = :id');
        $usage->execute([':id' => $originId]);
        if ((int) $usage->fetchColumn() > 0) {
            throw new RuntimeException('Esta origem está vinculada a uma ou mais temporadas e não pode ser excluída. Altere seu status para inativa.');
        }

        $stmt = $pdo->prepare('DELETE FROM origens_temporada WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $originId]);
        AuditLogService::record('origem_temporada.excluida', 'origens_temporada', $originId, [
            'conta_id' => $accountId,
            'nome' => (string) ($origin['nome'] ?? ''),
        ]);
    }

    public function listClassesForManagement(): array
    {
        $pdo = Database::connection();
        $this->ensureCourseAgeCriterionSchema($pdo);
        $stmt = $pdo->query("SELECT t.*, te.nome AS temporada_nome, te.data_inicio AS temporada_inicio, te.data_fim AS temporada_fim, m.nome AS modalidade_nome, cm.nome AS cronograma_nome, COALESCE(l.apelido_local, l.nome_local) AS local_nome, e.nome AS espaco_nome, nm.nome AS nivel_nome, professor.nome_completo AS professor_nome FROM turmas t INNER JOIN temporadas te ON te.id = t.temporada_id INNER JOIN modalidades m ON m.id = t.modalidade_id INNER JOIN cronogramas_modalidade cm ON cm.id = t.cronograma_modalidade_id INNER JOIN locais_treino l ON l.id = t.local_treino_id INNER JOIN espacos_treino e ON e.id = t.espaco_treino_id LEFT JOIN niveis_modalidade nm ON nm.id = t.nivel_modalidade_id LEFT JOIN contas pc ON pc.id = t.professor_conta_id LEFT JOIN pessoas professor ON professor.cpf = pc.cpf ORDER BY te.data_inicio DESC, t.nome ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listModalitySchedulesForManagement(): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $stmt = $pdo->query('SELECT cm.*, te.nome AS temporada_nome, m.nome AS modalidade_nome, (SELECT COUNT(*) FROM turmas t WHERE t.cronograma_modalidade_id = cm.id) AS total_turmas FROM cronogramas_modalidade cm INNER JOIN temporadas te ON te.id = cm.temporada_id INNER JOIN modalidades m ON m.id = cm.modalidade_id ORDER BY te.data_inicio DESC, m.nome ASC, cm.nome ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveModalitySchedule(int $accountId, array $data): array
    {
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $id = (int) ($data['cronograma_modalidade_id'] ?? 0);
        $seasonId = (int) ($data['temporada_id'] ?? 0);
        $modalityId = (int) ($data['modalidade_id'] ?? 0);
        $name = trim((string) ($data['nome'] ?? ''));
        $startDate = trim((string) ($data['data_inicio'] ?? ''));
        $endDate = trim((string) ($data['data_fim'] ?? ''));
        if ($seasonId <= 0 || $modalityId <= 0 || $name === '' || $startDate === '' || $endDate === '') throw new RuntimeException('Informe a temporada, a modalidade, o nome e o período geral do cronograma.');
        if ($startDate > $endDate) throw new RuntimeException('A data final do cronograma deve ser posterior à data inicial.');
        $hasNotice = !empty($data['possui_edital']);
        $noticeNumber = $hasNotice ? trim((string) ($data['numero_edital'] ?? '')) : null;
        $noticeLink = $hasNotice ? trim((string) ($data['link_edital'] ?? '')) : null;
        if ($hasNotice && ($noticeNumber === '' || $noticeLink === '')) throw new RuntimeException('Informe o número e o link do edital específico.');
        if ($hasNotice && filter_var($noticeLink, FILTER_VALIDATE_URL) === false) throw new RuntimeException('Informe um link válido para o edital, incluindo http:// ou https://.');
        $fields = ['inscricoes_inicio', 'inscricoes_fim', 'matriculas_inicio', 'matriculas_fim', 'inscricoes_abertas_inicio', 'inscricoes_abertas_fim', 'aulas_inicio', 'aulas_fim'];
        $values = [];
        foreach ($fields as $field) $values[$field] = trim((string) ($data[$field] ?? '')) ?: null;
        foreach ([['inscricoes_inicio', 'inscricoes_fim'], ['matriculas_inicio', 'matriculas_fim'], ['inscricoes_abertas_inicio', 'inscricoes_abertas_fim'], ['aulas_inicio', 'aulas_fim']] as [$start, $end]) {
            if ($values[$start] && $values[$end] && $values[$start] > $values[$end]) throw new RuntimeException('A data final de cada período deve ser posterior à data inicial.');
        }
        $params = [':temporada' => $seasonId, ':modalidade' => $modalityId, ':nome' => $name, ':inscricoes_inicio' => $values['inscricoes_inicio'], ':inscricoes_fim' => $values['inscricoes_fim'], ':matriculas_inicio' => $values['matriculas_inicio'], ':matriculas_fim' => $values['matriculas_fim'], ':inscricao_matricula' => !empty($data['permitir_inscricao_periodo_matricula']) ? 1 : 0, ':abertas_inicio' => $values['inscricoes_abertas_inicio'], ':abertas_fim' => $values['inscricoes_abertas_fim'], ':aulas_inicio' => $values['aulas_inicio'], ':aulas_fim' => $values['aulas_fim'], ':possui_edital' => $hasNotice ? 1 : 0, ':numero_edital' => $noticeNumber, ':link_edital' => $noticeLink];
        $params[':data_inicio'] = $startDate;
        $params[':data_fim'] = $endDate;
        if ($id > 0) {
            $current = $pdo->prepare('SELECT temporada_id, modalidade_id FROM cronogramas_modalidade WHERE id=:id LIMIT 1');
            $current->execute([':id' => $id]);
            $currentSchedule = $current->fetch(PDO::FETCH_ASSOC);
            if (!$currentSchedule) throw new RuntimeException('Cronograma não encontrado.');
            if (((int) $currentSchedule['temporada_id'] !== $seasonId || (int) $currentSchedule['modalidade_id'] !== $modalityId)) {
                $usage = $pdo->prepare('SELECT COUNT(*) FROM turmas WHERE cronograma_modalidade_id=:id');
                $usage->execute([':id' => $id]);
                if ((int) $usage->fetchColumn() > 0) throw new RuntimeException('A temporada e a modalidade não podem ser alteradas porque existem turmas associadas a este cronograma.');
            }
            $params[':id'] = $id;
            $stmt = $pdo->prepare('UPDATE cronogramas_modalidade SET temporada_id=:temporada, modalidade_id=:modalidade, nome=:nome, data_inicio=:data_inicio, data_fim=:data_fim, inscricoes_inicio=:inscricoes_inicio, inscricoes_fim=:inscricoes_fim, matriculas_inicio=:matriculas_inicio, matriculas_fim=:matriculas_fim, permitir_inscricao_periodo_matricula=:inscricao_matricula, inscricoes_abertas_inicio=:abertas_inicio, inscricoes_abertas_fim=:abertas_fim, aulas_inicio=:aulas_inicio, aulas_fim=:aulas_fim, possui_edital=:possui_edital, numero_edital=:numero_edital, link_edital=:link_edital WHERE id=:id LIMIT 1');
            $stmt->execute($params);
            AuditLogService::record('cronograma_modalidade.atualizado', 'cronogramas_modalidade', $id, ['conta_id' => $accountId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO cronogramas_modalidade (temporada_id, modalidade_id, nome, data_inicio, data_fim, inscricoes_inicio, inscricoes_fim, matriculas_inicio, matriculas_fim, permitir_inscricao_periodo_matricula, inscricoes_abertas_inicio, inscricoes_abertas_fim, aulas_inicio, aulas_fim, possui_edital, numero_edital, link_edital) VALUES (:temporada, :modalidade, :nome, :data_inicio, :data_fim, :inscricoes_inicio, :inscricoes_fim, :matriculas_inicio, :matriculas_fim, :inscricao_matricula, :abertas_inicio, :abertas_fim, :aulas_inicio, :aulas_fim, :possui_edital, :numero_edital, :link_edital)');
            $stmt->execute($params); $id = (int) $pdo->lastInsertId();
            AuditLogService::record('cronograma_modalidade.criado', 'cronogramas_modalidade', $id, ['conta_id' => $accountId]);
        }
        return ['id' => $id];
    }

    public function deleteModalitySchedule(int $accountId, int $id): void
    {
        if ($id <= 0) throw new RuntimeException('Cronograma inválido.');
        $pdo = Database::connection();
        $usage = $pdo->prepare('SELECT COUNT(*) FROM turmas WHERE cronograma_modalidade_id = :id');
        $usage->execute([':id' => $id]);
        $count = (int) $usage->fetchColumn();
        if ($count > 0) throw new RuntimeException('Este cronograma não pode ser excluído porque está associado a ' . $count . ' turma(s). Altere o cronograma dessas turmas primeiro.');
        $stmt = $pdo->prepare('DELETE FROM cronogramas_modalidade WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Cronograma não encontrado.');
        AuditLogService::record('cronograma_modalidade.excluido', 'cronogramas_modalidade', $id, ['conta_id' => $accountId]);
    }

    public function createSeason(int $accountId, array $data): array
    {
        $name = trim((string) ($data['nome'] ?? ''));
        $type = trim((string) ($data['tipo_periodicidade'] ?? 'anual'));
        $start = trim((string) ($data['data_inicio'] ?? ''));
        $end = trim((string) ($data['data_fim'] ?? ''));
        $hasNotice = !empty($data['possui_edital']);
        $noticeNumber = $hasNotice ? trim((string) ($data['numero_edital'] ?? '')) : null;
        $noticeLink = $hasNotice ? trim((string) ($data['link_edital'] ?? '')) : null;
        $pdo = Database::connection();
        $this->ensureCourseSeasonSchema($pdo);
        $this->ensureCourseAgeCriterionSchema($pdo);
        $originId = (int) ($data['origem_temporada_id'] ?? 0);
        $origin = $this->findSeasonOrigin($pdo, $originId);
        if ($name === '' || !$origin || !in_array($type, ['anual', 'semestral', 'quadrimestral', 'bimestral', 'mensal'], true) || $start === '' || $end === '') { throw new RuntimeException('Preencha nome, instituição gestora, periodicidade e período da temporada.'); }
        if ($hasNotice && ($noticeNumber === '' || $noticeLink === '')) { throw new RuntimeException('Informe o número e o link do edital da temporada.'); }
        if ($hasNotice && filter_var($noticeLink, FILTER_VALIDATE_URL) === false) { throw new RuntimeException('Informe um link válido para o edital, incluindo http:// ou https://.'); }
        if ($start > $end) { throw new RuntimeException('A data final da temporada deve ser posterior à inicial.'); }
        $id = (int) ($data['id'] ?? 0);
        $secondRelease = trim((string) ($data['data_liberacao_segunda_inscricao'] ?? '')) ?: null;
        $additionalRelease = trim((string) ($data['data_liberacao_inscricoes_adicionais'] ?? '')) ?: null;
        if ($secondRelease && $additionalRelease && $secondRelease > $additionalRelease) { throw new RuntimeException('A liberação da terceira inscrição deve ocorrer depois da liberação da segunda.'); }
        $params = [':nome' => $name, ':origem_id' => $originId, ':origem' => (string) $origin['nome'], ':possui_edital' => $hasNotice ? 1 : 0, ':numero_edital' => $noticeNumber, ':link_edital' => $noticeLink, ':tipo' => $type, ':inicio' => $start, ':fim' => $end, ':status' => in_array(($data['status'] ?? 'planejada'), ['planejada', 'ativa', 'suspensa', 'encerrada', 'cancelada'], true) ? $data['status'] : 'planejada', ':inscricoes_inicio' => trim((string) ($data['inscricoes_inicio'] ?? '')) ?: null, ':inscricoes_fim' => trim((string) ($data['inscricoes_fim'] ?? '')) ?: null, ':matriculas_inicio' => trim((string) ($data['matriculas_inicio'] ?? '')) ?: null, ':matriculas_fim' => trim((string) ($data['matriculas_fim'] ?? '')) ?: null, ':inscricao_matricula' => !empty($data['permitir_inscricao_periodo_matricula']) ? 1 : 0, ':abertas_inicio' => trim((string) ($data['inscricoes_abertas_inicio'] ?? '')) ?: null, ':abertas_fim' => trim((string) ($data['inscricoes_abertas_fim'] ?? '')) ?: null, ':aulas_inicio' => trim((string) ($data['aulas_inicio'] ?? '')) ?: null, ':aulas_fim' => trim((string) ($data['aulas_fim'] ?? '')) ?: null, ':cpf' => !empty($data['permitir_inscricao_por_cpf']) ? 1 : 0, ':logada' => !empty($data['permitir_inscricao_logada']) ? 1 : 0, ':limite' => max(1, (int) ($data['limite_inscricoes_periodo'] ?? 1)), ':segunda_liberacao' => $secondRelease, ':adicionais_liberacao' => $additionalRelease, ':limite_adicionais' => max(3, (int) ($data['limite_inscricoes_adicionais'] ?? 3))];
        if ($id > 0) {
            $params[':id'] = $id;
            $stmt = $pdo->prepare('UPDATE temporadas SET nome=:nome, origem_temporada_id=:origem_id, origem_temporada=:origem, possui_edital=:possui_edital, numero_edital=:numero_edital, link_edital=:link_edital, tipo_periodicidade=:tipo, data_inicio=:inicio, data_fim=:fim, status=:status, inscricoes_inicio=:inscricoes_inicio, inscricoes_fim=:inscricoes_fim, matriculas_inicio=:matriculas_inicio, matriculas_fim=:matriculas_fim, permitir_inscricao_periodo_matricula=:inscricao_matricula, inscricoes_abertas_inicio=:abertas_inicio, inscricoes_abertas_fim=:abertas_fim, aulas_inicio=:aulas_inicio, aulas_fim=:aulas_fim, permitir_inscricao_por_cpf=:cpf, permitir_inscricao_logada=:logada, limite_inscricoes_periodo=:limite, data_liberacao_segunda_inscricao=:segunda_liberacao, data_liberacao_inscricoes_adicionais=:adicionais_liberacao, limite_inscricoes_adicionais=:limite_adicionais WHERE id=:id LIMIT 1');
            $stmt->execute($params);
            AuditLogService::record('temporada.atualizada', 'temporadas', $id, ['conta_id' => $accountId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO temporadas (nome, origem_temporada_id, origem_temporada, possui_edital, numero_edital, link_edital, tipo_periodicidade, data_inicio, data_fim, status, inscricoes_inicio, inscricoes_fim, matriculas_inicio, matriculas_fim, permitir_inscricao_periodo_matricula, inscricoes_abertas_inicio, inscricoes_abertas_fim, aulas_inicio, aulas_fim, permitir_inscricao_por_cpf, permitir_inscricao_logada, limite_inscricoes_periodo, data_liberacao_segunda_inscricao, data_liberacao_inscricoes_adicionais, limite_inscricoes_adicionais, ativo) VALUES (:nome, :origem_id, :origem, :possui_edital, :numero_edital, :link_edital, :tipo, :inicio, :fim, :status, :inscricoes_inicio, :inscricoes_fim, :matriculas_inicio, :matriculas_fim, :inscricao_matricula, :abertas_inicio, :abertas_fim, :aulas_inicio, :aulas_fim, :cpf, :logada, :limite, :segunda_liberacao, :adicionais_liberacao, :limite_adicionais, 1)');
            $stmt->execute($params);
            $id = (int) $pdo->lastInsertId();
            AuditLogService::record('temporada.criada', 'temporadas', $id, ['conta_id' => $accountId]);
        }
        return ['id' => $id];
    }

    public function createClass(int $accountId, array $data): array
    {
        $required = ['temporada_id', 'modalidade_id', 'cronograma_modalidade_id', 'local_treino_id', 'espaco_treino_id', 'nome'];
        foreach ($required as $field) { if (trim((string) ($data[$field] ?? '')) === '') { throw new RuntimeException('Preencha todos os campos obrigatórios da turma.'); } }
        $pdo = Database::connection();
        $this->ensureCourseAgeCriterionSchema($pdo);
        $id = (int) ($data['id'] ?? 0);
        $scheduleId = (int) $data['cronograma_modalidade_id'];
        $schedule = $pdo->prepare('SELECT id FROM cronogramas_modalidade WHERE id=:id AND temporada_id=:temporada AND modalidade_id=:modalidade LIMIT 1');
        $schedule->execute([':id' => $scheduleId, ':temporada' => (int) $data['temporada_id'], ':modalidade' => (int) $data['modalidade_id']]);
        if (!$schedule->fetchColumn()) throw new RuntimeException('Selecione um cronograma correspondente à temporada e à modalidade da turma.');
        $params = [':temporada' => (int) $data['temporada_id'], ':modalidade' => (int) $data['modalidade_id'], ':local' => (int) $data['local_treino_id'], ':espaco' => (int) $data['espaco_treino_id'], ':nivel' => (int) ($data['nivel_modalidade_id'] ?? 0) ?: null, ':nome' => trim((string) $data['nome']), ':idade_minima' => max(0, (int) ($data['idade_minima'] ?? 0)), ':idade_maxima' => max(0, (int) ($data['idade_maxima'] ?? 120)), ':criterio_faixa_etaria' => normalize_age_rule_mode((string) ($data['criterio_faixa_etaria'] ?? 'idade_exata')), ':vagas_totais' => max(0, (int) ($data['vagas_totais'] ?? 0)), ':vagas_geral' => max(0, (int) ($data['vagas_geral'] ?? 0)), ':vagas_pcd' => max(0, (int) ($data['vagas_pcd'] ?? 0)), ':vagas_plm' => max(0, (int) ($data['vagas_plm'] ?? 0)), ':vagas_pvs' => max(0, (int) ($data['vagas_pvs'] ?? 0)), ':espera_geral' => max(0, (int) ($data['vagas_espera_geral'] ?? 0)), ':espera_pcd' => max(0, (int) ($data['vagas_espera_pcd'] ?? 0)), ':espera_plm' => max(0, (int) ($data['vagas_espera_plm'] ?? 0)), ':espera_pvs' => max(0, (int) ($data['vagas_espera_pvs'] ?? 0))];
        $params[':cronograma'] = $scheduleId;
        $weekdays = $this->normalizeClassWeekdays($data['dias_semana'] ?? []);
        $params[':dias_semana'] = $weekdays ?: null;
        $params[':hora_inicio'] = trim((string) ($data['hora_inicio'] ?? '')) ?: null;
        $params[':hora_fim'] = trim((string) ($data['hora_fim'] ?? '')) ?: null;
        if (($weekdays !== '') !== ($params[':hora_inicio'] !== null && $params[':hora_fim'] !== null)) { throw new RuntimeException('Selecione os dias da semana e informe os horários de início e fim das aulas.'); }
        if ($params[':hora_inicio'] !== null && $params[':hora_inicio'] >= $params[':hora_fim']) { throw new RuntimeException('O horário final da aula deve ser posterior ao horário inicial.'); }
        $params[':sexo'] = in_array((string) ($data['sexo'] ?? ''), ['masculino', 'feminino'], true) ? (string) $data['sexo'] : null;
        $currentProfessorId = $id > 0 ? $this->classProfessorId($pdo, $id) : 0;
        $params[':professor'] = $id > 0 ? ($currentProfessorId ?: null) : ($this->accountIsProfessor($pdo, $accountId) ? $accountId : null);
        if ($id > 0) {
            $params[':id'] = $id;
            $stmt = $pdo->prepare('UPDATE turmas SET temporada_id=:temporada, modalidade_id=:modalidade, cronograma_modalidade_id=:cronograma, local_treino_id=:local, espaco_treino_id=:espaco, nivel_modalidade_id=:nivel, professor_conta_id=:professor, nome=:nome, dias_semana=:dias_semana, hora_inicio=:hora_inicio, hora_fim=:hora_fim, idade_minima=:idade_minima, idade_maxima=:idade_maxima, criterio_faixa_etaria=:criterio_faixa_etaria, sexo=:sexo, vagas_totais=:vagas_totais, vagas_geral=:vagas_geral, vagas_pcd=:vagas_pcd, vagas_plm=:vagas_plm, vagas_pvs=:vagas_pvs, vagas_espera_geral=:espera_geral, vagas_espera_pcd=:espera_pcd, vagas_espera_plm=:espera_plm, vagas_espera_pvs=:espera_pvs WHERE id=:id LIMIT 1');
            $stmt->execute($params);
            AuditLogService::record('turma.atualizada', 'turmas', $id, ['conta_id' => $accountId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO turmas (temporada_id, modalidade_id, cronograma_modalidade_id, local_treino_id, espaco_treino_id, nivel_modalidade_id, professor_conta_id, nome, dias_semana, hora_inicio, hora_fim, idade_minima, idade_maxima, criterio_faixa_etaria, sexo, vagas_totais, vagas_geral, vagas_pcd, vagas_plm, vagas_pvs, vagas_espera_geral, vagas_espera_pcd, vagas_espera_plm, vagas_espera_pvs, ativo) VALUES (:temporada, :modalidade, :cronograma, :local, :espaco, :nivel, :professor, :nome, :dias_semana, :hora_inicio, :hora_fim, :idade_minima, :idade_maxima, :criterio_faixa_etaria, :sexo, :vagas_totais, :vagas_geral, :vagas_pcd, :vagas_plm, :vagas_pvs, :espera_geral, :espera_pcd, :espera_plm, :espera_pvs, 1)');
            $stmt->execute($params);
            $id = (int) $pdo->lastInsertId();
            AuditLogService::record('turma.criada', 'turmas', $id, ['conta_id' => $accountId]);
        }
        return ['id' => $id];
    }

    public function deactivate(string $entity, int $id, int $accountId): void
    {
        $tables = [
            'temporada' => 'temporadas',
            'turma' => 'turmas',
        ];
        if (!isset($tables[$entity]) || $id <= 0) { throw new RuntimeException('Registro inválido para inativação.'); }
        $table = $tables[$entity];
        $stmt = Database::connection()->prepare("UPDATE {$table} SET ativo = 0 WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) { throw new RuntimeException('Registro não encontrado ou já inativo.'); }
        AuditLogService::record($entity . '.inativada', $table, $id, ['conta_id' => $accountId]);
    }

    public function listPeopleForAuthenticatedAccount(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("\n            SELECT DISTINCT p.id, p.nome_completo, p.cpf, p.data_nascimento, p.sexo, p.cadastro_completo\n            FROM contas c\n            INNER JOIN pessoas titular ON titular.cpf = c.cpf\n            INNER JOIN pessoas p ON p.id = titular.id\n                OR EXISTS (SELECT 1 FROM vinculos_responsaveis vr WHERE vr.responsavel_pessoa_id = titular.id AND vr.dependente_pessoa_id = p.id)\n            WHERE c.id = :conta_id\n            ORDER BY p.nome_completo ASC\n        ");
        $stmt->execute([':conta_id' => Auth::id()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProfessors(): array
    {
        $stmt = Database::connection()->query("SELECT DISTINCT c.id, p.nome_completo FROM contas c INNER JOIN pessoas p ON p.cpf = c.cpf INNER JOIN conta_papeis cp ON cp.conta_id = c.id INNER JOIN papeis papel ON papel.id = cp.papel_id WHERE c.ativo = 1 AND papel.slug = 'teacher' ORDER BY p.nome_completo ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listClassesForProfessor(int $accountId): array
    {
        $pdo = Database::connection();
        $this->ensureCourseAgeCriterionSchema($pdo);
        $stmt = $pdo->prepare("SELECT t.*, te.nome AS temporada_nome, m.nome AS modalidade_nome, COALESCE(l.apelido_local, l.nome_local) AS local_nome, e.nome AS espaco_nome FROM turmas t INNER JOIN temporadas te ON te.id = t.temporada_id INNER JOIN modalidades m ON m.id = t.modalidade_id INNER JOIN locais_treino l ON l.id = t.local_treino_id INNER JOIN espacos_treino e ON e.id = t.espaco_treino_id WHERE t.professor_conta_id = :professor_id AND t.ativo = 1 ORDER BY te.data_inicio DESC, t.nome ASC");
        $stmt->execute([':professor_id' => $accountId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($classes as &$class) { $class['dias_semana_descricao'] = $this->describeClassWeekdays((string) ($class['dias_semana'] ?? '')); }
        unset($class);
        return $classes;
    }

    public function assignProfessor(int $classId, int $professorAccountId, int $accountId): void
    {
        if ($classId <= 0 || !$this->accountIsProfessor(Database::connection(), $professorAccountId)) { throw new RuntimeException('Selecione uma turma e um professor válidos.'); }
        $stmt = Database::connection()->prepare('UPDATE turmas SET professor_conta_id = :professor_id WHERE id = :turma_id LIMIT 1');
        $stmt->execute([':professor_id' => $professorAccountId, ':turma_id' => $classId]);
        if ($stmt->rowCount() === 0 && $this->classProfessorId(Database::connection(), $classId) !== $professorAccountId) { throw new RuntimeException('Turma não encontrada.'); }
        AuditLogService::record('turma.professor_atribuido', 'turmas', $classId, ['conta_id' => $accountId, 'professor_conta_id' => $professorAccountId]);
    }

    public function getClassEnrollmentDetails(int $classId): array
    {
        $pdo = Database::connection();
        $class = $this->findClass($pdo, $classId);
        $class['dias_semana_descricao'] = $this->describeClassWeekdays((string) ($class['dias_semana'] ?? ''));
        $class['periodo_dia'] = $this->describeClassDayPeriod((string) ($class['hora_inicio'] ?? ''));
        $class['criterio_faixa_etaria'] = normalize_age_rule_mode((string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'));
        $class['faixa_etaria_descricao'] = $this->describeClassAgeRule($class);
        $class['vagas_geral_disponiveis'] = $this->availableSeats($pdo, $class, 'geral');
        $class['vagas_pcd_disponiveis'] = $this->availableSeats($pdo, $class, 'pcd');
        $class['vagas_plm_disponiveis'] = $this->availableSeats($pdo, $class, 'plm');
        $class['vagas_pvs_disponiveis'] = $this->availableSeats($pdo, $class, 'pvs');
        $class['espera_geral_disponivel'] = $this->availableWaitlistSeats($pdo, $class, 'geral');
        $class['espera_pcd_disponivel'] = $this->availableWaitlistSeats($pdo, $class, 'pcd');
        $class['espera_plm_disponivel'] = $this->availableWaitlistSeats($pdo, $class, 'plm');
        $class['espera_pvs_disponivel'] = $this->availableWaitlistSeats($pdo, $class, 'pvs');

        $people = [];
        if (Auth::check()) {
            foreach ($this->listPeopleForAuthenticatedAccount() as $person) {
                $age = calculate_age((string) ($person['data_nascimento'] ?? ''));
                $reasons = [];
                if ((int) ($person['cadastro_completo'] ?? 0) !== 1) $reasons[] = 'Cadastro incompleto';
                if (!$this->personMatchesClassAgeRule($person, $class)) $reasons[] = $this->classAgeBlockReason($person, $class);
                $requiredSex = trim((string) ($class['sexo'] ?? ''));
                if ($requiredSex !== '' && $requiredSex !== (string) ($person['sexo'] ?? '')) $reasons[] = 'Sexo não permitido para esta turma';
                $duplicate = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id = :turma AND pessoa_id = :pessoa AND status IN ('aguardando_matricula', 'matriculada', 'lista_espera')");
                $duplicate->execute([':turma' => $classId, ':pessoa' => (int) $person['id']]);
                if ((int) $duplicate->fetchColumn() > 0) $reasons[] = 'Pessoa já inscrita nesta turma';
                $person['idade'] = $age;
                $person['publico_alvo'] = $this->resolvePublic($pdo, (int) $person['id']);
                $person['elegivel'] = $reasons === [];
                $person['motivo_bloqueio'] = implode('; ', $reasons);
                $people[] = $person;
            }
        }
        return ['class' => $class, 'people' => $people];
    }

    public function enroll(array $data): array
    {
        $classId = (int) ($data['turma_id'] ?? 0);
        $personId = (int) ($data['pessoa_id'] ?? 0);
        $cpf = normalize_cpf((string) ($data['cpf'] ?? ''));
        $tokenValue = trim((string) ($data['token'] ?? ''));
        $termsAccepted = (int) ($data['aceite_termos'] ?? 0) === 1;

        if ($classId <= 0 || !$termsAccepted) {
            throw new RuntimeException('Selecione uma turma e aceite os termos para continuar.');
        }

        $pdo = Database::connection();
        $class = $this->findClass($pdo, $classId);
        $season = $this->findSeason($pdo, (int) $class['temporada_id']);
        $season['inscricoes_inicio'] = $class['cronograma_inscricoes_inicio'] ?? null;
        $season['inscricoes_fim'] = $class['cronograma_inscricoes_fim'] ?? null;
        $season['matriculas_inicio'] = $class['cronograma_matriculas_inicio'] ?? null;
        $season['matriculas_fim'] = $class['cronograma_matriculas_fim'] ?? null;
        $season['permitir_inscricao_periodo_matricula'] = (int) ($class['cronograma_permitir_inscricao_matricula'] ?? 0);
        if ((string) ($season['status'] ?? 'planejada') !== 'ativa') {
            throw new RuntimeException('Esta temporada não está ativa para receber inscrições.');
        }
        $token = $this->findEnrollmentToken($pdo, $tokenValue, $classId, $cpf);
        $now = new DateTimeImmutable();
        $today = $now->format('Y-m-d');
        if ($today < (string) ($class['cronograma_data_inicio'] ?? '') || $today > (string) ($class['cronograma_data_fim'] ?? '9999-12-31')) {
            throw new RuntimeException('Esta turma não está disponível para consulta e inscrição neste período.');
        }
        if (!$this->withinSeasonEnrollment($season, $now)) {
            throw new RuntimeException('As inscrições para o cronograma desta modalidade não estão abertas no momento.');
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
                throw new RuntimeException('Este CPF pertence a uma pessoa menor de idade. A inscrição deve ser feita pelo responsável legal, que deverá fazer login e selecionar o dependente.');
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

        $this->validateAge($person, $class, $token !== null);
        $this->validateDuplicate($pdo, $classId, (int) $person['id']);
        $this->validateSeasonLimit($pdo, $season, (int) $person['id'], $now, $token !== null);

        $publico = $this->resolvePublic($pdo, (int) $person['id']);
        if ($token !== null) { $publico = (string) $token['publico_alvo']; }
        $this->validatePublic($pdo, (int) $person['id'], $publico, $token !== null);
        $forceWaitlist = $this->isOpenEnrollmentPhase($season, $now);
        $status = $forceWaitlist || $this->availableSeats($pdo, $class, $publico) <= 0
            ? 'lista_espera'
            : 'aguardando_matricula';
        $waitPosition = $status === 'lista_espera' ? $this->nextWaitlistPosition($pdo, $classId, $publico) : null;
        if ($status === 'lista_espera' && $this->availableWaitlistSeats($pdo, $class, $publico) <= 0 && $token === null) {
            throw new RuntimeException('A lista de espera desta cota já atingiu o limite de vagas.');
        }

        $stmt = $pdo->prepare("\n            INSERT INTO inscricoes_turma (turma_id, pessoa_id, publico_alvo, status, posicao_lista_espera, inscrito_por_conta_id, created_at)\n            VALUES (:turma_id, :pessoa_id, :publico, :status, :posicao, :conta_id, NOW())\n        ");
        $stmt->execute([
            ':turma_id' => $classId,
            ':pessoa_id' => (int) $person['id'],
            ':publico' => $publico,
            ':status' => $status,
            ':posicao' => $waitPosition,
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
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$enrollment) {
            throw new RuntimeException('Inscrição não encontrada ou não pertence à sua responsabilidade.');
        }
        $stmt = $pdo->prepare("UPDATE inscricoes_turma SET status = 'cancelada', cancelado_por_conta_id = :conta_id, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $enrollmentId, ':conta_id' => Auth::id()]);
        $history = $pdo->prepare('INSERT INTO inscricoes_turma_historico (inscricao_turma_id, status_anterior, status_novo, motivo, alterado_por_conta_id) VALUES (:id, :anterior, :novo, :motivo, :conta)');
        $history->execute([':id' => $enrollmentId, ':anterior' => $enrollment['status'], ':novo' => 'cancelada', ':motivo' => 'Cancelamento solicitado pelo responsável.', ':conta' => Auth::id()]);
        AuditLogService::record('inscricao_turma.cancelada', 'inscricoes_turma', $enrollmentId, ['conta_id' => Auth::id()]);
    }

    public function createExceptionToken(int $accountId, array $data): string
    {
        $cpf = normalize_cpf((string) ($data['cpf'] ?? ''));
        $classId = (int) ($data['turma_id'] ?? 0);
        $public = strtolower(trim((string) ($data['publico_alvo'] ?? 'geral')));
        $reason = trim((string) ($data['motivo'] ?? ''));
        if (!validar_cpf($cpf) || $classId <= 0 || !in_array($public, ['geral', 'pcd', 'plm', 'pvs'], true) || $reason === '') { throw new RuntimeException('Informe CPF, turma, tipo de vaga e motivo para criar o token.'); }
        $token = bin2hex(random_bytes(32));
        $stmt = Database::connection()->prepare('INSERT INTO tokens_inscricao_turma (token, turma_id, cpf, publico_alvo, criado_por_conta_id, validade, usos_maximos, motivo) VALUES (:token, :turma, :cpf, :publico, :conta, :validade, :usos, :motivo)');
        $stmt->execute([':token' => $token, ':turma' => $classId, ':cpf' => $cpf, ':publico' => $public, ':conta' => $accountId, ':validade' => trim((string) ($data['validade'] ?? '')) ?: null, ':usos' => max(1, (int) ($data['usos_maximos'] ?? 1)), ':motivo' => $reason]);
        $id = (int) Database::connection()->lastInsertId();
        AuditLogService::record('inscricao_turma.token_criado', 'tokens_inscricao_turma', $id, ['conta_id' => $accountId, 'turma_id' => $classId, 'cpf' => $cpf, 'publico_alvo' => $public]);
        return $token;
    }

    private function findEnrollmentToken(PDO $pdo, string $token, int $classId, string $cpf): ?array
    {
        if ($token === '') { return null; }
        $stmt = $pdo->prepare('SELECT * FROM tokens_inscricao_turma WHERE token = :token AND turma_id = :turma AND cpf = :cpf AND ativo = 1 AND usos_realizados < usos_maximos AND (validade IS NULL OR validade >= NOW()) LIMIT 1');
        $stmt->execute([':token' => $token, ':turma' => $classId, ':cpf' => $cpf]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new RuntimeException('Token inválido, expirado ou não autorizado para este CPF e turma.'); }
        return $row;
    }

    private function nextWaitlistPosition(PDO $pdo, int $classId, string $public): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id = :turma AND publico_alvo = :publico AND status = 'lista_espera'");
        $stmt->execute([':turma' => $classId, ':publico' => $public]);
        return (int) $stmt->fetchColumn() + 1;
    }

    private function validatePublic(PDO $pdo, int $personId, string $public, bool $hasToken): void
    {
        if ($hasToken || $public === 'geral') { return; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id WHERE cp.pessoa_id = :pessoa AND cp.status = 'validado' AND tc.slug = :slug");
        $slug = ['pcd' => 'pcd', 'plm' => 'plm', 'pvs' => 'pvs'][$public];
        $stmt->execute([':pessoa' => $personId, ':slug' => $slug]);
        if ((int) $stmt->fetchColumn() === 0) { throw new RuntimeException('A condição escolhida não corresponde a uma condição validada para esta pessoa.'); }
    }

    private function accountHasRole(PDO $pdo, int $accountId, string $role): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM conta_papeis cp INNER JOIN papeis p ON p.id = cp.papel_id WHERE cp.conta_id = :conta AND p.slug = :role');
        $stmt->execute([':conta' => $accountId, ':role' => $role]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function changeStatus(int $enrollmentId, string $status, int $accountId, string $reason = '', ?string $suspensionEnd = null, string $exceptionToken = ''): void
    {
        $allowedStatuses = ['aguardando_matricula', 'matriculada', 'suspensa', 'desistente', 'excluida_por_falta', 'excluida'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('Status inválido para atualização da inscrição.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Informe o motivo da alteração da inscrição.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT i.id, i.status, i.turma_id, i.publico_alvo, p.cpf FROM inscricoes_turma i INNER JOIN pessoas p ON p.id = i.pessoa_id WHERE i.id = :id AND i.status IN ('aguardando_matricula', 'lista_espera', 'matriculada', 'suspensa') LIMIT 1");
        $stmt->execute([':id' => $enrollmentId]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$enrollment) {
            throw new RuntimeException('Inscrição não encontrada ou já encerrada.');
        }
        if ($status === 'excluida' && !$this->accountHasRole($pdo, $accountId, 'master_admin')) {
            throw new RuntimeException('Somente o Administrador Master pode excluir inscrições.');
        }
        if ($status === 'aguardando_matricula' && $enrollment['status'] !== 'lista_espera' && !$this->accountHasRole($pdo, $accountId, 'master_admin')) {
            throw new RuntimeException('Somente o Administrador Master pode retornar a inscrição para aguardando matrícula.');
        }
        $token = $this->findEnrollmentToken($pdo, trim($exceptionToken), (int) $enrollment['turma_id'], normalize_cpf((string) $enrollment['cpf']));
        if ($status === 'matriculada' && $enrollment['status'] !== 'matriculada' && $this->availableSeats($pdo, $this->findClass($pdo, (int) $enrollment['turma_id']), (string) $enrollment['publico_alvo']) <= 0 && $token === null && !$this->accountHasRole($pdo, $accountId, 'master_admin')) {
            throw new RuntimeException('Não há vaga normal disponível para esta cota.');
        }
        if ($status === 'suspensa' && trim($reason) === '') {
            throw new RuntimeException('Informe o motivo da suspensão.');
        }

        if ($status === 'suspensa' && trim((string) $suspensionEnd) === '') { throw new RuntimeException('Informe o prazo final da suspensão.'); }
        $stmt = $pdo->prepare('UPDATE inscricoes_turma SET status = :status, motivo_status = :motivo, suspensa_inicio = CASE WHEN :status_suspensa = "suspensa" THEN NOW() ELSE NULL END, suspensa_fim = :suspensa_fim, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':status' => $status, ':motivo' => $reason, ':status_suspensa' => $status, ':suspensa_fim' => $status === 'suspensa' ? $suspensionEnd : null, ':id' => $enrollmentId]);
        $history = $pdo->prepare('INSERT INTO inscricoes_turma_historico (inscricao_turma_id, status_anterior, status_novo, motivo, alterado_por_conta_id) VALUES (:id, :anterior, :novo, :motivo, :conta)');
        $history->execute([':id' => $enrollmentId, ':anterior' => $enrollment['status'], ':novo' => $status, ':motivo' => $reason, ':conta' => $accountId]);
        if ($token !== null && $status === 'matriculada') {
            $pdo->prepare('UPDATE tokens_inscricao_turma SET usos_realizados = usos_realizados + 1, ativo = IF(usos_realizados + 1 >= usos_maximos, 0, ativo) WHERE id = :id')->execute([':id' => (int) $token['id']]);
        }
        AuditLogService::record('inscricao_turma.status_alterado', 'inscricoes_turma', $enrollmentId, [
            'status' => $status,
            'motivo' => $reason,
            'conta_id' => $accountId,
        ]);
    }

    private function findClass(PDO $pdo, int $id): array
    {
        $this->ensureCourseAgeCriterionSchema($pdo);
        $stmt = $pdo->prepare('SELECT t.*, te.nome AS temporada_nome, te.data_inicio AS temporada_inicio, te.data_fim AS temporada_fim, cm.data_inicio AS cronograma_data_inicio, cm.data_fim AS cronograma_data_fim, cm.aulas_inicio, cm.inscricoes_inicio AS cronograma_inscricoes_inicio, cm.inscricoes_fim AS cronograma_inscricoes_fim, cm.matriculas_inicio AS cronograma_matriculas_inicio, cm.matriculas_fim AS cronograma_matriculas_fim, cm.permitir_inscricao_periodo_matricula AS cronograma_permitir_inscricao_matricula FROM turmas t INNER JOIN temporadas te ON te.id = t.temporada_id INNER JOIN cronogramas_modalidade cm ON cm.id = t.cronograma_modalidade_id WHERE t.id = :id AND t.ativo = 1 AND te.ativo = 1 LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new RuntimeException('Turma não encontrada ou indisponível.'); }
        return $row;
    }

    private function findSeason(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT * FROM temporadas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function withinSeasonEnrollment(array $season, DateTimeImmutable $now): bool
    {
        $withinInitialEnrollment = (empty($season['inscricoes_inicio']) || $now >= new DateTimeImmutable((string) $season['inscricoes_inicio']))
            && (empty($season['inscricoes_fim']) || $now <= new DateTimeImmutable((string) $season['inscricoes_fim']));
        if ($withinInitialEnrollment) { return true; }

        if (empty($season['permitir_inscricao_periodo_matricula'])) { return false; }

        return (empty($season['matriculas_inicio']) || $now >= new DateTimeImmutable((string) $season['matriculas_inicio']))
            && (empty($season['matriculas_fim']) || $now <= new DateTimeImmutable((string) $season['matriculas_fim']));
    }

    private function isOpenEnrollmentPhase(array $season, DateTimeImmutable $now): bool
    {
        if (empty($season['inscricoes_abertas_inicio']) || empty($season['inscricoes_abertas_fim'])) {
            return false;
        }
        return $now >= new DateTimeImmutable((string) $season['inscricoes_abertas_inicio'])
            && $now <= new DateTimeImmutable((string) $season['inscricoes_abertas_fim']);
    }

    private function findAuthorizedPerson(PDO $pdo, int $personId): array
    {
        $stmt = $pdo->prepare("SELECT p.* FROM pessoas p WHERE p.id = :person_id AND EXISTS (SELECT 1 FROM contas c INNER JOIN pessoas titular ON titular.cpf = c.cpf LEFT JOIN vinculos_responsaveis vr ON vr.responsavel_pessoa_id = titular.id WHERE c.id = :conta_id AND (p.id = titular.id OR p.id = vr.dependente_pessoa_id)) LIMIT 1");
        $stmt->execute([':person_id' => $personId, ':conta_id' => Auth::id()]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$person) { throw new RuntimeException('A pessoa selecionada não pertence à sua responsabilidade.'); }
        return $person;
    }

    private function validateAge(array $person, array $class, bool $hasException = false): void
    {
        if ($hasException) { return; }
        if (!$this->personMatchesClassAgeRule($person, $class)) {
            throw new RuntimeException('A pessoa não atende ao critério etário desta turma: ' . $this->describeClassAgeRule($class) . '.');
        }
    }

    private function classAgeReferenceDate(array $class): DateTimeImmutable
    {
        $value = trim((string) ($class['aulas_inicio'] ?? $class['temporada_inicio'] ?? $class['data_inicio'] ?? ''));
        try { return $value !== '' ? new DateTimeImmutable($value) : new DateTimeImmutable('today'); }
        catch (\Throwable $e) { return new DateTimeImmutable('today'); }
    }

    private function personMatchesClassAgeRule(array $person, array $class): bool
    {
        return person_matches_age_rule(
            (string) ($person['data_nascimento'] ?? ''),
            (int) ($class['idade_minima'] ?? 0),
            (int) ($class['idade_maxima'] ?? 120),
            (string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'),
            $this->classAgeReferenceDate($class)
        );
    }

    private function describeClassAgeRule(array $class): string
    {
        $description = describe_age_rule(
            (int) ($class['idade_minima'] ?? 0),
            (int) ($class['idade_maxima'] ?? 120),
            (string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'),
            $this->classAgeReferenceDate($class)
        );
        return (string) ($description['detailed'] ?? 'Faixa etária não informada');
    }

    private function classAgeBlockReason(array $person, array $class): string
    {
        $personName = trim((string) ($person['nome_completo'] ?? 'A pessoa'));
        $mode = normalize_age_rule_mode((string) ($class['criterio_faixa_etaria'] ?? 'idade_exata'));
        $referenceDate = $this->classAgeReferenceDate($class);
        if ($mode === 'ano_nascimento') {
            $range = birth_year_range_from_age_range((int) $class['idade_minima'], (int) $class['idade_maxima'], $referenceDate);
            $birthYear = birth_year_from_date((string) ($person['data_nascimento'] ?? ''));
            return 'Esta turma aceita inscrições de pessoas nascidas entre ' . (int) $range['from'] . ' e ' . (int) $range['to'] . ', ' . $personName . ' nasceu em ' . ($birthYear === null ? 'ano não informado' : (string) $birthYear) . '.';
        }

        $age = null;
        try {
            $birthDate = new DateTimeImmutable((string) ($person['data_nascimento'] ?? ''));
            if ($birthDate <= $referenceDate) { $age = $birthDate->diff($referenceDate)->y; }
        } catch (\Throwable $e) {
            $age = null;
        }
        return 'Esta turma aceita inscrições de pessoas com idade entre ' . (int) $class['idade_minima'] . ' e ' . (int) $class['idade_maxima'] . ' anos, ' . $personName . ' tem ' . ($age === null ? 'idade não informada' : $age . ' anos') . '.';
    }

    private function normalizeClassWeekdays($value): string
    {
        $values = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        $days = [];
        foreach ($values ?: [] as $day) {
            $number = (int) $day;
            if ($number >= 1 && $number <= 7) { $days[$number] = $number; }
        }
        ksort($days);
        return implode(',', $days);
    }

    private function describeClassWeekdays(string $value): string
    {
        $normalized = $this->normalizeClassWeekdays($value);
        if ($normalized === '') { return ''; }
        $names = [1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sábado', 7 => 'domingo'];
        $days = array_map('intval', explode(',', $normalized));
        $groups = [];
        $start = $previous = $days[0];
        foreach (array_slice($days, 1) as $day) {
            if ($day === $previous + 1) { $previous = $day; continue; }
            $groups[] = [$start, $previous];
            $start = $previous = $day;
        }
        $groups[] = [$start, $previous];
        $parts = [];
        foreach ($groups as [$from, $to]) {
            if ($to - $from >= 2) { $parts[] = 'de ' . $names[$from] . ' a ' . $names[$to]; }
            elseif ($to > $from) { $parts[] = $names[$from]; $parts[] = $names[$to]; }
            else { $parts[] = $names[$from]; }
        }
        if (count($parts) === 1) { return ucfirst($parts[0]); }
        $last = array_pop($parts);
        return ucfirst(implode(', ', $parts) . ' e ' . $last);
    }

    private function describeClassDayPeriod(string $startTime): string
    {
        if (!preg_match('/^(\d{1,2}):/', trim($startTime), $matches)) { return ''; }
        $hour = (int) $matches[1];
        if ($hour >= 5 && $hour < 12) { return 'Manhã'; }
        if ($hour >= 12 && $hour < 18) { return 'Tarde'; }
        return 'Noite';
    }

    private function validateDuplicate(PDO $pdo, int $classId, int $personId): void
    {
        $stmt = $pdo->prepare("SELECT id FROM inscricoes_turma WHERE turma_id = :turma_id AND pessoa_id = :pessoa_id AND status IN ('aguardando_matricula', 'matriculada', 'lista_espera') LIMIT 1");
        $stmt->execute([':turma_id' => $classId, ':pessoa_id' => $personId]);
        if ($stmt->fetchColumn()) { throw new RuntimeException('Esta pessoa já está inscrita nesta turma.'); }
    }

    private function validateSeasonLimit(PDO $pdo, array $season, int $personId, DateTimeImmutable $now, bool $hasException = false): void
    {
        if ($hasException) { return; }
        $limit = max(1, (int) ($season['limite_inscricoes_periodo'] ?? 1));
        if (!empty($season['data_liberacao_segunda_inscricao']) && $now >= new DateTimeImmutable((string) $season['data_liberacao_segunda_inscricao'])) {
            $limit = max(2, $limit);
        }
        if (!empty($season['data_liberacao_inscricoes_adicionais']) && $now >= new DateTimeImmutable((string) $season['data_liberacao_inscricoes_adicionais'])) {
            $limit = max(3, (int) ($season['limite_inscricoes_adicionais'] ?? 3));
        }
        if ($limit <= 0) { return; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma i INNER JOIN turmas t ON t.id = i.turma_id WHERE t.temporada_id = :temporada_id AND i.pessoa_id = :pessoa_id AND i.status IN ('aguardando_matricula', 'matriculada', 'lista_espera')");
        $stmt->execute([':temporada_id' => (int) $season['id'], ':pessoa_id' => $personId]);
        if ((int) $stmt->fetchColumn() >= $limit) { throw new RuntimeException('O limite de ' . $limit . ' inscrição(ões) por CPF nesta temporada já foi atingido.'); }
    }

    private function resolvePublic(PDO $pdo, int $personId): string
    {
        $stmt = $pdo->prepare("SELECT CASE
            WHEN EXISTS (SELECT 1 FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id WHERE cp.pessoa_id = p.id AND cp.status = 'validado' AND tc.slug = 'pcd') THEN 'pcd'
            WHEN EXISTS (SELECT 1 FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id WHERE cp.pessoa_id = p.id AND cp.status = 'validado' AND tc.slug = 'plm') THEN 'plm'
            WHEN EXISTS (SELECT 1 FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id = cp.tipo_certificado_id WHERE cp.pessoa_id = p.id AND cp.status = 'validado' AND tc.slug = 'pvs') THEN 'pvs'
            ELSE 'geral' END FROM pessoas p WHERE p.id = :id");
        $stmt->execute([':id' => $personId]);
        return (string) ($stmt->fetchColumn() ?: 'geral');
    }

    private function availableSeats(PDO $pdo, array $class, string $public = 'geral'): int
    {
        $seatKey = in_array($public, ['pcd', 'plm', 'pvs'], true) ? 'vagas_' . $public : 'vagas_geral';
        $capacity = (int) ($class[$seatKey] ?? 0);
        if ($capacity <= 0) { return 0; }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id = :turma_id AND publico_alvo = :publico AND status IN ('aguardando_matricula', 'matriculada', 'suspensa')");
        $stmt->execute([':turma_id' => (int) $class['id'], ':publico' => $public]);
        return max(0, $capacity - (int) $stmt->fetchColumn());
    }

    private function availableWaitlistSeats(PDO $pdo, array $class, string $public): int
    {
        $seatKey = in_array($public, ['pcd', 'plm', 'pvs'], true) ? 'vagas_espera_' . $public : 'vagas_espera_geral';
        $capacity = (int) ($class[$seatKey] ?? 0);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id = :turma_id AND publico_alvo = :publico AND status = 'lista_espera'");
        $stmt->execute([':turma_id' => (int) $class['id'], ':publico' => $public]);
        return max(0, $capacity - (int) $stmt->fetchColumn());
    }

    private function ensureCourseAgeCriterionSchema(PDO $pdo): void
    {
        if (self::$courseAgeCriterionSchemaChecked) { return; }
        $stmt = $pdo->query("SHOW COLUMNS FROM turmas LIKE 'criterio_faixa_etaria'");
        if (!$stmt || !$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE turmas ADD COLUMN criterio_faixa_etaria ENUM('idade_exata', 'ano_nascimento') NOT NULL DEFAULT 'idade_exata' AFTER idade_maxima");
        }
        $sexStmt = $pdo->query("SHOW COLUMNS FROM turmas LIKE 'sexo'");
        if (!$sexStmt || !$sexStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE turmas ADD COLUMN sexo ENUM('masculino', 'feminino') NULL AFTER criterio_faixa_etaria");
        }
        $scheduleColumns = [
            'professor_conta_id' => 'BIGINT UNSIGNED NULL AFTER nivel_modalidade_id',
            'dias_semana' => 'VARCHAR(120) NULL AFTER nome',
            'hora_inicio' => 'TIME NULL AFTER dias_semana',
            'hora_fim' => 'TIME NULL AFTER hora_inicio',
        ];
        foreach ($scheduleColumns as $name => $definition) {
            $scheduleStmt = $pdo->query('SHOW COLUMNS FROM turmas LIKE ' . $pdo->quote($name));
            if (!$scheduleStmt || !$scheduleStmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->exec("ALTER TABLE turmas ADD COLUMN {$name} {$definition}");
            }
        }
        self::$courseAgeCriterionSchemaChecked = true;
    }

    private function accountIsProfessor(PDO $pdo, int $accountId): bool
    {
        if ($accountId <= 0) { return false; }
        $stmt = $pdo->prepare("SELECT 1 FROM conta_papeis cp INNER JOIN papeis p ON p.id = cp.papel_id INNER JOIN contas c ON c.id = cp.conta_id WHERE cp.conta_id = :id AND p.slug = 'teacher' AND c.ativo = 1 LIMIT 1");
        $stmt->execute([':id' => $accountId]);
        return (bool) $stmt->fetchColumn();
    }

    private function classProfessorId(PDO $pdo, int $classId): int
    {
        if ($classId <= 0) { return 0; }
        $stmt = $pdo->prepare('SELECT professor_conta_id FROM turmas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $classId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function findSeasonOrigin(PDO $pdo, int $originId): ?array
    {
        if ($originId <= 0) { return null; }
        $stmt = $pdo->prepare('SELECT id, nome, ativo FROM origens_temporada WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $originId]);
        $origin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $origin ?: null;
    }

    private function ensureCourseSeasonSchema(PDO $pdo): void
    {
        if (self::$courseSeasonSchemaChecked) { return; }
        $originTableCheck = $pdo->query("SHOW TABLES LIKE 'origens_temporada'");
        $originTableAlreadyExisted = $originTableCheck && $originTableCheck->fetchColumn();
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS origens_temporada (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(180) NOT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_origem_temporada_nome (nome)
            ) ENGINE=InnoDB
        ');
        $columns = [
            'origem_temporada' => 'VARCHAR(180) NULL AFTER nome',
            'origem_temporada_id' => 'BIGINT UNSIGNED NULL AFTER origem_temporada',
            'possui_edital' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER origem_temporada',
            'numero_edital' => 'VARCHAR(100) NULL AFTER possui_edital',
            'link_edital' => 'VARCHAR(2048) NULL AFTER numero_edital',
            'permitir_inscricao_periodo_matricula' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER matriculas_fim',
            'data_liberacao_segunda_inscricao' => 'DATETIME NULL AFTER limite_inscricoes_periodo',
            'data_liberacao_inscricoes_adicionais' => 'DATETIME NULL AFTER data_liberacao_segunda_inscricao',
            'limite_inscricoes_adicionais' => 'INT UNSIGNED NOT NULL DEFAULT 3 AFTER data_liberacao_inscricoes_adicionais',
        ];
        $originIdColumnAdded = false;
        foreach ($columns as $name => $definition) {
            $stmt = $pdo->query('SHOW COLUMNS FROM temporadas LIKE ' . $pdo->quote($name));
            if (!$stmt || !$stmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->exec("ALTER TABLE temporadas ADD COLUMN {$name} {$definition}");
                if ($name === 'origem_temporada_id') {
                    $originIdColumnAdded = true;
                }
            }
        }
        $modalityScheduleColumn = $pdo->query("SHOW COLUMNS FROM cronogramas_modalidade LIKE 'permitir_inscricao_periodo_matricula'");
        if (!$modalityScheduleColumn || !$modalityScheduleColumn->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE cronogramas_modalidade ADD COLUMN permitir_inscricao_periodo_matricula TINYINT(1) NOT NULL DEFAULT 0 AFTER matriculas_fim');
        }
        if (!$originTableAlreadyExisted || $originIdColumnAdded) {
            $defaultOrigin = 'Secretaria de Esportes e Lazer de São Bernardo do Campo';
            $defaultStmt = $pdo->prepare('INSERT IGNORE INTO origens_temporada (nome, ativo) VALUES (:nome, 1)');
            $defaultStmt->execute([':nome' => $defaultOrigin]);
            $pdo->exec('
                INSERT IGNORE INTO origens_temporada (nome, ativo)
                SELECT DISTINCT TRIM(origem_temporada), 1
                FROM temporadas
                WHERE origem_temporada IS NOT NULL AND TRIM(origem_temporada) <> ""
            ');
            $pdo->exec('
                UPDATE temporadas te
                INNER JOIN origens_temporada ot ON ot.nome = TRIM(te.origem_temporada)
                SET te.origem_temporada_id = ot.id
                WHERE te.origem_temporada_id IS NULL
            ');
        }
        $foreignKeyStmt = $pdo->query('
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = "temporadas"
              AND CONSTRAINT_NAME = "fk_temporada_origem"
            LIMIT 1
        ');
        if (!$foreignKeyStmt || !$foreignKeyStmt->fetchColumn()) {
            $pdo->exec('ALTER TABLE temporadas ADD CONSTRAINT fk_temporada_origem FOREIGN KEY (origem_temporada_id) REFERENCES origens_temporada(id)');
        }
        self::$courseSeasonSchemaChecked = true;
    }
}
