<?php

namespace App\Services;

use RuntimeException;

final class ReservedSlotAllocation
{
    private const MAX_TOTAL = 2147483647;

    public static function fromTotal($value): array
    {
        $total = self::validateCount($value, 'Vagas totais');
        $remainder = $total % 10;
        if ($remainder >= 3 && $remainder <= 7) {
            $lower = $total - $remainder + 2;
            $upper = $lower + 6;
            $suggestion = $upper <= self::MAX_TOTAL
                ? "Use o total inferior de {$lower} ou o superior de {$upper}."
                : "Use o total inferior de {$lower}.";
            throw new RuntimeException('PCD, PLM e PVS devem ter a mesma quantidade na distribuição automática. ' . $suggestion);
        }

        $reserved = intdiv($total, 10) + (int) ($remainder >= 8);
        return [
            'vagas_geral' => $total - 3 * $reserved,
            'vagas_pcd' => $reserved,
            'vagas_plm' => $reserved,
            'vagas_pvs' => $reserved,
        ];
    }

    public static function withoutGeneral(array $data): array
    {
        $slots = ['vagas_geral' => 0];
        foreach (['pcd', 'plm', 'pvs'] as $category) {
            $field = 'vagas_' . $category;
            $slots[$field] = self::validateCount($data[$field] ?? null, 'Vagas ' . strtoupper($category));
        }
        if (array_sum($slots) > self::MAX_TOTAL) {
            throw new RuntimeException('A soma das vagas reservadas excede o limite permitido.');
        }
        return $slots;
    }

    private static function validateCount($value, string $label): int
    {
        $count = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => self::MAX_TOTAL],
        ]);
        if ($count === false) {
            throw new RuntimeException("Informe um número inteiro válido no campo {$label}.");
        }
        return $count;
    }
}
