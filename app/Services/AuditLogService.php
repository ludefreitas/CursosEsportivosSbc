<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

class AuditLogService
{
    /**
     * Registra uma trilha de auditoria para alterações sensiveis.
     */
    public static function record(string $eventType, string $entityType, ?int $entityId = null, array $payload = []): void
    {
        self::write(Auth::id(), $eventType, $entityType, $entityId, $payload);
    }

    /**
     * Registra uma alteração automática executada pelo sistema.
     */
    public static function recordSystem(string $eventType, string $entityType, ?int $entityId = null, array $payload = []): void
    {
        self::write(null, $eventType, $entityType, $entityId, $payload);
    }

    private static function write(?int $accountId, string $eventType, string $entityType, ?int $entityId, array $payload): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO logs_auditoria (conta_id, tipo_evento, tipo_entidade, entidade_id, payload_json, ip_usuario)
            VALUES (:conta_id, :tipo_evento, :tipo_entidade, :entidade_id, :payload_json, :ip_usuario)
        ');

        $stmt->execute([
            ':conta_id' => $accountId,
            ':tipo_evento' => $eventType,
            ':tipo_entidade' => $entityType,
            ':entidade_id' => $entityId,
            ':payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ':ip_usuario' => request_ip(),
        ]);
    }
}
