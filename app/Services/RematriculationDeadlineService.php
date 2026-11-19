<?php

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class RematriculationDeadlineService
{
    public function __construct(private ?PDO $pdo = null) {}

    public static function calculate(string $generatedAt, ?string $enrollmentStart, int $days): string
    {
        if (!in_array($days, [7, 14, 21, 28], true)) {
            throw new RuntimeException('Selecione prazo de 7, 14, 21 ou 28 dias.');
        }
        $base = new DateTimeImmutable($generatedAt);
        if ($enrollmentStart !== null && trim($enrollmentStart) !== '') {
            $start = new DateTimeImmutable($enrollmentStart);
            if ($start > $base) $base = $start;
        }
        return $base->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
    }

    public function updateActiveTokens(?int $campaignId = null): int
    {
        $pdo = $this->pdo ?? Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $sql = "SELECT tk.id,tk.created_at,tk.validade,r.id AS campanha_id,r.prazo_dias,cm.inscricoes_inicio
                FROM tokens_inscricao_turma tk
                INNER JOIN rematricula_convites c ON c.token_id=tk.id
                INNER JOIN rematriculas r ON r.id=c.rematricula_id AND r.destino_turma_id=tk.turma_id
                INNER JOIN turmas t ON t.id=tk.turma_id
                LEFT JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id
                WHERE tk.status='ativo' AND tk.ativo=1 AND tk.validade>=CURRENT_TIMESTAMP";
            $params = [];
            if ($campaignId !== null) {
                $sql .= ' AND r.id=?';
                $params[] = $campaignId;
            }
            $stmt = $pdo->prepare($sql . ' ORDER BY tk.id');
            $stmt->execute($params);
            $update = $pdo->prepare("UPDATE tokens_inscricao_turma SET validade=?
                WHERE id=? AND status='ativo' AND ativo=1 AND validade>=CURRENT_TIMESTAMP AND validade=? AND validade<?");
            $count = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $token) {
                $deadline = self::calculate($token['created_at'], $token['inscricoes_inicio'], (int) $token['prazo_dias']);
                $update->execute([$deadline, $token['id'], $token['validade'], $deadline]);
                if ($update->rowCount() === 0) continue;
                AuditLogService::recordSystem('inscricao_turma.token_validade_atualizada', 'tokens_inscricao_turma', (int) $token['id'], [
                    'rematricula_id' => (int) $token['campanha_id'],
                    'validade_anterior' => $token['validade'],
                    'validade_nova' => $deadline,
                ]);
                $count++;
            }
            if ($ownsTransaction) $pdo->commit();
            return $count;
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public function updateActiveCampaigns(): int
    {
        $pdo = $this->pdo ?? Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $stmt = $pdo->query("SELECT r.id,r.created_at,r.prazo_dias,r.prazo_final,cm.inscricoes_inicio
                FROM rematriculas r INNER JOIN turmas t ON t.id=r.destino_turma_id
                LEFT JOIN cronogramas_modalidade cm ON cm.id=t.cronograma_modalidade_id
                WHERE r.status='ativa' AND r.prazo_final>=CURRENT_TIMESTAMP ORDER BY r.id");
            $update = $pdo->prepare("UPDATE rematriculas SET prazo_final=?, updated_at=CURRENT_TIMESTAMP
                WHERE id=? AND status='ativa' AND prazo_final>=CURRENT_TIMESTAMP AND prazo_final=? AND prazo_final<?");
            $count = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $campaign) {
                $deadline = self::calculate($campaign['created_at'], $campaign['inscricoes_inicio'], (int) $campaign['prazo_dias']);
                $update->execute([$deadline, $campaign['id'], $campaign['prazo_final'], $deadline]);
                if ($update->rowCount() === 0) continue;
                AuditLogService::recordSystem('rematricula.prazo_atualizado', 'rematriculas', (int) $campaign['id'], [
                    'prazo_anterior' => $campaign['prazo_final'],
                    'prazo_novo' => $deadline,
                ]);
                $count++;
            }
            if ($ownsTransaction) $pdo->commit();
            return $count;
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
