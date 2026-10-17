<?php
namespace App\Services;

final class CourseClassTimelineService
{
    public static function build(array $classes, array $schedules, int $seasonId, string $periodKey): array
    {
        $periods = [
            'disponibilidade' => ['Disponibilidade', 'data_inicio', 'data_fim'],
            'inscricao-inicial' => ['Inscrição inicial', 'inscricoes_inicio', 'inscricoes_fim'],
            'matricula' => ['Período de matrícula', 'matriculas_inicio', 'matriculas_fim'],
            'inscricoes-abertas' => ['Inscrições abertas', 'inscricoes_abertas_inicio', 'inscricoes_abertas_fim'],
            'aulas' => ['Período de aulas', 'aulas_inicio', 'aulas_fim'],
        ];
        $seasons = [];
        $scheduleMap = [];
        foreach ($schedules as $schedule) $scheduleMap[(int) $schedule['id']] = $schedule;
        foreach ($classes as $class) $seasons[(int) $class['temporada_id']] = (string) ($class['temporada_nome'] ?? 'Temporada');
        if (!isset($seasons[$seasonId])) $seasonId = (int) (array_key_first($seasons) ?? 0);
        $groups = [];
        foreach ($periods as $type => [$label, $startField, $endField]) {
            foreach ($classes as $class) {
                if ((int) $class['temporada_id'] !== $seasonId) continue;
                $schedule = $scheduleMap[(int) ($class['cronograma_modalidade_id'] ?? 0)] ?? null;
                if (!$schedule || (int) $schedule['temporada_id'] !== $seasonId || (int) $schedule['modalidade_id'] !== (int) $class['modalidade_id']) continue;
                $start = (string) ($schedule[$startField] ?? '');
                $end = (string) ($schedule[$endField] ?? '');
                if ($start === '' && $end === '') continue;
                $key = $type . '|' . $start . '|' . $end;
                if (!isset($groups[$key])) $groups[$key] = ['label' => $label, 'start' => $start, 'end' => $end, 'classes' => []];
                $groups[$key]['classes'][(int) $class['id']] = $class;
            }
        }
        return ['seasons' => $seasons, 'season_id' => $seasonId, 'groups' => $groups,
            'period_key' => isset($groups[$periodKey]) ? $periodKey : '',
            'classes' => array_values($groups[$periodKey]['classes'] ?? [])];
    }
}
