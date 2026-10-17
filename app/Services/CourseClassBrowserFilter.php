<?php
namespace App\Services;

final class CourseClassBrowserFilter
{
    public static function filter(array $classes, string $view, int $seasonId, int $groupId, int $secondaryId): array
    {
        $byLocation = $view === 'turmas-locais';
        $primary = $byLocation ? 'local_treino_id' : 'modalidade_id';
        $primaryName = $byLocation ? 'local_nome' : 'modalidade_nome';
        $secondary = $byLocation ? 'modalidade_id' : 'local_treino_id';
        $secondaryName = $byLocation ? 'modalidade_nome' : 'local_nome';
        $groups = $secondaryGroups = $filtered = [];
        foreach ($classes as $class) {
            if ((int) ($class['temporada_id'] ?? 0) !== $seasonId) continue;
            $id = (int) ($class[$primary] ?? 0);
            if ($id > 0) $groups[$id] = (string) ($class[$primaryName] ?? '');
            if ($groupId <= 0 || $id !== $groupId) continue;
            $secondId = (int) ($class[$secondary] ?? 0);
            if ($secondId > 0) $secondaryGroups[$secondId] = (string) ($class[$secondaryName] ?? '');
            if ($secondaryId > 0 && $secondId === $secondaryId) $filtered[] = $class;
        }
        natcasesort($groups);
        natcasesort($secondaryGroups);
        return ['groups' => $groups, 'secondary_groups' => $secondaryGroups, 'classes' => $filtered];
    }
}
