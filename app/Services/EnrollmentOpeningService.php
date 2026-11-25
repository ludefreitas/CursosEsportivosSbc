<?php
namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class EnrollmentOpeningService
{
    /** O token pode ser gerado antes da abertura, mas não antecipa a inscrição. */
    public static function blockReason(array $schedule, ?DateTimeImmutable $now = null): ?string
    {
        $value = trim((string) ($schedule['cronograma_inscricoes_inicio'] ?? $schedule['inscricoes_inicio'] ?? ''));
        if ($value === '') return null;
        $zone = new DateTimeZone('America/Sao_Paulo');
        try {
            $start = new DateTimeImmutable($value, $zone);
        } catch (Throwable $error) {
            return 'A data de início das inscrições iniciais desta turma precisa ser corrigida pela administração.';
        }
        $now ??= new DateTimeImmutable('now', $zone);
        if ($now >= $start) return null;
        return 'As inscrições iniciais desta turma começam em ' . $start->format('d/m/Y \à\s H:i')
            . '. Aguarde a abertura para realizar a inscrição, inclusive com token de rematrícula.';
    }
}
