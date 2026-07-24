<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class SpaceSuspensionService
{
    /**
     * Inativa e audita suspensões cujo período já terminou.
     */
    public static function expireElapsed(): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('
            SELECT id, espaco_treino_id, data_inicio, data_fim
            FROM suspensoes_espaco_treino
            WHERE ativo = 1
              AND data_fim < CURDATE()
            ORDER BY id
        ');
        $expired = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($expired === []) {
            return 0;
        }

        $pdo->beginTransaction();

        try {
            $update = $pdo->prepare('
                UPDATE suspensoes_espaco_treino
                SET ativo = 0
                WHERE id = :id
                  AND ativo = 1
                  AND data_fim < CURDATE()
            ');
            $total = 0;

            foreach ($expired as $suspension) {
                $update->execute([':id' => (int) $suspension['id']]);

                if ($update->rowCount() === 0) {
                    continue;
                }

                $total++;
                AuditLogService::recordSystem(
                    'sistema.suspensao_espaco_expirada',
                    'suspensoes_espaco_treino',
                    (int) $suspension['id'],
                    [
                        'espaco_treino_id' => (int) $suspension['espaco_treino_id'],
                        'data_inicio' => (string) $suspension['data_inicio'],
                        'data_fim' => (string) $suspension['data_fim'],
                        'ativo_anterior' => 1,
                        'ativo_novo' => 0,
                        'motivo_inativacao' => 'Data final da suspensão expirada.',
                    ]
                );
            }

            $pdo->commit();
            return $total;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
