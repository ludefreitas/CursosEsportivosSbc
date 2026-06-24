<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

class AuditLogService
{
    /**
     * Registra uma trilha de auditoria para alteracoes sensiveis.
     */
    public static function record(string $eventType, string $entityType, ?int $entityId = null, array $payload = []): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO logs_auditoria (conta_id, tipo_evento, tipo_entidade, entidade_id, payload_json, ip_usuario)
            VALUES (:conta_id, :tipo_evento, :tipo_entidade, :entidade_id, :payload_json, :ip_usuario)
        ');

        $stmt->execute([
            ':conta_id' => Auth::id(),
            ':tipo_evento' => $eventType,
            ':tipo_entidade' => $entityType,
            ':entidade_id' => $entityId,
            ':payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ':ip_usuario' => request_ip(),
        ]);
    }
}
