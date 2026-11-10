<?php

namespace App\Services;

use PDO;

final class HealthCertificateRules
{
    public const TYPES = ['clinico' => 'clínico', 'dermatologico' => 'dermatológico'];
    public const MODES = ['global', 'exigir', 'dispensar'];

    public static function requires(array $activity, string $type, string $default): bool
    {
        return match ($activity['regra_atestado_' . $type] ?? $default) {
            'exigir' => true,
            'dispensar' => false,
            default => $type === 'clinico' || ($type === 'dermatologico' && ($activity['tipo_ambiente'] ?? '') === 'aquatica'),
        };
    }

    public static function blockReason(PDO $pdo, int $personId, array $activity, string $default, string $action): ?string
    {
        foreach (self::TYPES as $type => $label) {
            if (!self::requires($activity, $type, $default)) {
                continue;
            }

            $stmt = $pdo->prepare('SELECT validade_certificado, validade_certificado >= CURDATE() AS vigente FROM (
                SELECT a.validade_certificado FROM atestados_saude a
                WHERE a.pessoa_id = :pessoa_interna AND a.tipo_atestado = :tipo_interno AND a.status_validacao = "validado"
                UNION ALL
                SELECT i.validade_certificado FROM atestados_saude_importados i
                INNER JOIN pessoas p ON p.cpf = i.cpf
                WHERE p.id = :pessoa_externa AND i.tipo_atestado = :tipo_externo AND i.status_importacao = "ativo"
            ) certificados
            WHERE validade_certificado IS NOT NULL
            ORDER BY validade_certificado DESC LIMIT 1');
            $stmt->execute([
                ':pessoa_interna' => $personId,
                ':tipo_interno' => $type,
                ':pessoa_externa' => $personId,
                ':tipo_externo' => $type,
            ]);
            $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($certificate && (int) $certificate['vigente'] === 1) {
                continue;
            }
            if ($certificate) {
                $date = date('d/m/Y', strtotime($certificate['validade_certificado']));
                return 'O atestado ' . $label . ' venceu em ' . $date . '. Atualize o atestado para ' . $action . '.';
            }
            return 'Sem atestado ' . $label . ' validado não é possível ' . $action . '.';
        }

        return null;
    }
}
