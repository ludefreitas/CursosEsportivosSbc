<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

final class EnrollmentDeclarationService
{
    public function issue(int $enrollmentId, array $user): array
    {
        $pdo = Database::connection();
        $enrollment = $this->enrollment($pdo, $enrollmentId);
        $accountId = (int) ($user['conta_id'] ?? 0);
        $courses = new CourseEnrollmentService();
        $owner = in_array((int) $enrollment['pessoa_id'], array_map('intval', array_column($courses->listPeopleForAuthenticatedAccount(), 'id')), true);
        $admin = has_role($user['roles'] ?? [], 'master_admin') || has_role($user['roles'] ?? [], 'admin');
        if (!$owner && !$admin && !$courses->professorCanManageEnrollment($accountId, $enrollmentId)) {
            throw new RuntimeException('Inscrição não encontrada ou sem permissão de acesso.');
        }
        $months = $this->months($pdo, $enrollmentId);
        if ($months === []) {
            throw new RuntimeException('Não existe frequência, para gerar ou imprimir declaração para esta inscrição.');
        }
        // A estrutura é aplicada exclusivamente pela migração explícita.
        $pdo->prepare('INSERT INTO declaracoes_inscricao (inscricao_turma_id, codigo_consulta) VALUES (:inscricao, :codigo) ON DUPLICATE KEY UPDATE inscricao_turma_id = VALUES(inscricao_turma_id)')
            ->execute([':inscricao' => $enrollmentId, ':codigo' => bin2hex(random_bytes(32))]);
        $query = $pdo->prepare('SELECT codigo_consulta FROM declaracoes_inscricao WHERE inscricao_turma_id=:id');
        $query->execute([':id' => $enrollmentId]);
        return ['enrollment' => $enrollment, 'code' => (string) $query->fetchColumn(), 'months' => $months];
    }

    public function frequency(string $code, string $month): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $code) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) {
            throw new RuntimeException('Consulta de frequência não encontrada.');
        }
        $pdo = Database::connection();
        $query = $pdo->prepare('SELECT inscricao_turma_id FROM declaracoes_inscricao WHERE codigo_consulta=:codigo');
        $query->execute([':codigo' => $code]);
        $id = (int) $query->fetchColumn();
        if ($id <= 0) { throw new RuntimeException('Consulta de frequência não encontrada.'); }
        $months = $this->months($pdo, $id);
        if (!in_array($month, $months, true)) { throw new RuntimeException('Não há chamada registrada neste mês.'); }
        $start = new \DateTimeImmutable($month . '-01');
        $query = $pdo->prepare('SELECT data_aula, status FROM turmas_chamadas WHERE inscricao_turma_id=:id AND data_aula>=:inicio AND data_aula<:fim ORDER BY data_aula');
        $query->execute([':id' => $id, ':inicio' => $start->format('Y-m-d'), ':fim' => $start->modify('+1 month')->format('Y-m-d')]);
        return ['enrollment' => $this->enrollment($pdo, $id), 'months' => $months, 'month' => $month, 'code' => $code, 'attendance' => $query->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    private function months(PDO $pdo, int $id): array
    {
        $query = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(data_aula, '%Y-%m') AS mes FROM turmas_chamadas WHERE inscricao_turma_id=:id AND status IN ('presente','ausente','justificado') ORDER BY mes");
        $query->execute([':id' => $id]);
        return $query->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private function enrollment(PDO $pdo, int $id): array
    {
        $query = $pdo->prepare('SELECT i.id, i.pessoa_id, i.status, p.nome_completo, p.cpf, t.id AS turma_id, t.nome AS turma_nome, t.dias_semana, t.hora_inicio, t.hora_fim, te.nome AS temporada_nome, m.nome AS modalidade_nome, COALESCE(NULLIF(l.apelido_local,\'\'),l.nome_local) AS local_nome, l.logradouro, l.numero_endereco, l.cidade, e.nome AS espaco_nome FROM inscricoes_turma i INNER JOIN pessoas p ON p.id=i.pessoa_id INNER JOIN turmas t ON t.id=i.turma_id INNER JOIN temporadas te ON te.id=t.temporada_id INNER JOIN modalidades m ON m.id=t.modalidade_id INNER JOIN locais_treino l ON l.id=t.local_treino_id INNER JOIN espacos_treino e ON e.id=t.espaco_treino_id WHERE i.id=:id LIMIT 1');
        $query->execute([':id' => $id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new RuntimeException('Inscrição não encontrada.'); }
        $labels = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
        $days = array_filter(array_map(static fn ($day) => $labels[(int) $day] ?? '', explode(',', (string) $row['dias_semana'])));
        $row['dias_semana_descricao'] = implode(' e ', $days);
        return $row;
    }
}
