<?php
namespace App\Services;

final class CourseClassStatusFilter
{
    public static function filter(array $classes, int $seasonId, string $status, int $modalityId, int $locationId): array
    {
        $seasons = $statuses = $modalities = $locations = $result = [];
        foreach ($classes as $class) $seasons[(int) $class['temporada_id']] = (string) ($class['temporada_nome'] ?? 'Temporada');
        if (!isset($seasons[$seasonId])) $seasonId = (int) (array_key_first($seasons) ?? 0);
        foreach ($classes as $class) {
            if ((int) $class['temporada_id'] !== $seasonId) continue;
            $key = (string) ($class['status'] ?? '');
            if ($key !== '') $statuses[$key] = (string) ($class['status_label'] ?? $key);
            if ($status === '' || $key !== $status) continue;
            $modalities[(int) $class['modalidade_id']] = (string) ($class['modalidade_nome'] ?? 'Modalidade');
            if ($modalityId <= 0 || (int) $class['modalidade_id'] !== $modalityId) continue;
            $locations[(int) $class['local_treino_id']] = (string) ($class['local_nome'] ?? 'Local');
            if ($locationId > 0 && (int) $class['local_treino_id'] === $locationId) $result[] = $class;
        }
        if (!isset($statuses[$status])) $status = '';
        if (!isset($modalities[$modalityId])) $modalityId = 0;
        if (!isset($locations[$locationId])) $locationId = 0;
        natcasesort($statuses); natcasesort($modalities); natcasesort($locations);
        return ['seasons' => $seasons, 'statuses' => $statuses, 'modalities' => $modalities, 'locations' => $locations,
            'season_id' => $seasonId, 'status' => $status, 'modality_id' => $modalityId, 'location_id' => $locationId, 'classes' => $result];
    }
}
