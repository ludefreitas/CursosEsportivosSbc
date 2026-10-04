<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use InvalidArgumentException;

class ScheduleGridService
{
    public const STATUS_OPTIONS = [
        'planejada' => 'Planejada',
        'processo_inicial' => 'Em processo inicial de inscrição',
        'periodo_matricula' => 'Período de matrícula',
        'inscricoes_abertas' => 'Inscrições abertas',
        'inscricoes_suspensas' => 'Inscrições suspensas',
        'inscricoes_encerradas' => 'Inscrições encerradas',
    ];

    public function search(int $seasonId, int $locationId, int $spaceId, ?array $statuses = null): array
    {
        $statusOptions = self::STATUS_OPTIONS;
        $selectedStatuses = $statuses === null ? array_keys($statusOptions) : array_values(array_unique(array_intersect(array_keys($statusOptions), $statuses)));
        $pdo = Database::connection();
        $seasons = $pdo->query("SELECT id,nome,status FROM temporadas ORDER BY (status='ativa') DESC,data_inicio DESC,id DESC")->fetchAll(PDO::FETCH_ASSOC);
        if ($seasonId === 0 && $seasons !== []) $seasonId = (int) $seasons[0]['id'];
        $season = $this->option($seasons, $seasonId);
        if ($seasonId !== 0 && !$season) throw new InvalidArgumentException('Temporada inválida.');
        $locations = $pdo->query("SELECT id,nome_local,apelido_local,logradouro,numero_endereco,complemento,bairro FROM locais_treino WHERE ativo=1 ORDER BY COALESCE(NULLIF(apelido_local,''),nome_local)")->fetchAll(PDO::FETCH_ASSOC);
        $location = $this->option($locations, $locationId);
        if ($locationId !== 0 && !$location) throw new InvalidArgumentException('Local inválido.');
        $spaces = [];
        $sheets = [];
        $unscheduled = 0;
        if ($location) {
            $stmt = $pdo->prepare('SELECT id,nome FROM espacos_treino WHERE local_treino_id=:local AND ativo=1 ORDER BY nome');
            $stmt->execute([':local' => $locationId]);
            $spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($spaceId !== 0 && !$this->option($spaces, $spaceId)) throw new InvalidArgumentException('O espaço selecionado não pertence a este local.');
            $params = [':season' => $seasonId, ':local' => $locationId];
            $statusPlaceholders = [];
            foreach ($selectedStatuses as $index => $status) {
                $placeholder = ':status' . $index;
                $statusPlaceholders[] = $placeholder;
                $params[$placeholder] = $status;
            }
            $statusFilter = $statusPlaceholders ? 't.status IN (' . implode(',', $statusPlaceholders) . ')' : '1=0';
            $stmt = $pdo->prepare("SELECT t.id,t.nome,t.status,t.espaco_treino_id,t.dias_semana,t.hora_inicio,t.hora_fim,t.programa,t.idade_minima,t.idade_maxima,t.criterio_faixa_etaria,m.nome AS modalidade,
                (SELECT p.nome_completo FROM contas c INNER JOIN pessoas p ON p.cpf=c.cpf WHERE c.id=t.professor_conta_id LIMIT 1) AS professor,
                (SELECT GROUP_CONCAT(DISTINCT p.nome_completo ORDER BY p.nome_completo SEPARATOR ' / ')
                 FROM turmas_professores tp INNER JOIN contas c ON c.id=tp.professor_conta_id INNER JOIN pessoas p ON p.cpf=c.cpf
                 WHERE tp.turma_id=t.id AND (t.professor_conta_id IS NULL OR tp.professor_conta_id<>t.professor_conta_id)) AS professores_auxiliares,
                (SELECT GROUP_CONCAT(DISTINCT p.nome_completo ORDER BY p.nome_completo SEPARATOR ' / ')
                 FROM turmas_estagiarios ti INNER JOIN contas c ON c.id=ti.estagiario_conta_id INNER JOIN pessoas p ON p.cpf=c.cpf
                 WHERE ti.turma_id=t.id) AS estagiarios
                FROM turmas t INNER JOIN modalidades m ON m.id=t.modalidade_id
                INNER JOIN espacos_treino e ON e.id=t.espaco_treino_id AND e.local_treino_id=t.local_treino_id
                WHERE t.temporada_id=:season AND t.local_treino_id=:local AND t.ativo=1 AND e.ativo=1 AND $statusFilter
                ORDER BY t.hora_inicio,t.nome,t.id");
            $stmt->execute($params);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($spaces as $space) {
                if ($spaceId !== 0 && (int) $space['id'] !== $spaceId) continue;
                $items = array_values(array_filter($classes, static fn(array $class): bool => (int) $class['espaco_treino_id'] === (int) $space['id']));
                $grid = $this->buildGrid($items);
                $unscheduled += $grid['sem_horario'];
                $sheets[] = ['espaco' => $space, 'periodos' => $grid['periodos'], 'total' => count($items)];
            }
        }
        return compact('seasons', 'seasonId', 'season', 'locations', 'locationId', 'location', 'spaces', 'spaceId', 'sheets', 'unscheduled', 'statusOptions', 'selectedStatuses');
    }

    /** Mantém início, meio e fim do período mesmo quando há uma única aula. */
    public function buildGrid(array $classes): array
    {
        $definitions = [
            ['nome' => 'MANHÃ', 'inicio' => 0, 'fim' => 720, 'visual_inicio' => 420, 'visual_fim' => 720],
            ['nome' => 'TARDE', 'inicio' => 720, 'fim' => 1080, 'visual_inicio' => 720, 'visual_fim' => 1080],
            ['nome' => 'NOITE', 'inicio' => 1080, 'fim' => 1440, 'visual_inicio' => 1080, 'visual_fim' => 1320],
        ];
        $events = [[], [], []];
        $missing = 0;
        foreach ($classes as $class) {
            $start = $this->minutes((string) ($class['hora_inicio'] ?? ''));
            $end = $this->minutes((string) ($class['hora_fim'] ?? ''));
            $days = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($class['dias_semana'] ?? ''))), static fn(int $day): bool => $day >= 1 && $day <= 7)));
            if ($start === null || $end === null || $end <= $start || $days === []) { $missing++; continue; }
            foreach ($definitions as $index => $period) {
                $from = max($start, $period['inicio']);
                $to = min($end, $period['fim']);
                if ($from >= $to) continue;
                $definitions[$index]['visual_inicio'] = min($definitions[$index]['visual_inicio'], $from);
                $definitions[$index]['visual_fim'] = max($definitions[$index]['visual_fim'], $to);
                foreach ($days as $day) {
                    $events[$index][] = $class + ['dia' => $day, 'centro' => ($from + $to) / 2, 'inicio_minutos' => $start];
                }
            }
        }
        $periods = [];
        foreach ($definitions as $index => $period) {
            $days = array_fill(1, 7, []);
            usort($events[$index], static fn(array $a, array $b): int => [$a['inicio_minutos'], $a['nome'], $a['id']] <=> [$b['inicio_minutos'], $b['nome'], $b['id']]);
            foreach ($events[$index] as $event) {
                $fraction = ($event['centro'] - $period['visual_inicio']) / ($period['visual_fim'] - $period['visual_inicio']);
                $event['posicao'] = min(1, max(0, $fraction));
                $days[$event['dia']][] = $event;
            }
            // A mesma altura comporta 3, 4 ou 5 linhas, conforme o dia mais cheio.
            $count = max(3, ...array_map('count', $days));
            $rows = array_fill(0, $count, array_fill(1, 7, null));
            foreach ($days as $day => $items) {
                foreach ($this->assignRows($items, $count) as $eventIndex => $rowIndex) {
                    $rows[$rowIndex][$day] = $items[$eventIndex];
                }
            }
            $periods[] = ['nome' => $period['nome'], 'linhas' => $rows];
        }
        return ['periodos' => $periods, 'sem_horario' => $missing];
    }

    /** Escolhe linhas próximas ao horário, mantendo ordem e uma aula por célula. */
    private function assignRows(array $items, int $count): array
    {
        $memo = [];
        $solve = function (int $index, int $firstRow) use (&$solve, &$memo, $items, $count): array {
            if ($index === count($items)) return ['custo' => 0, 'linhas' => []];
            $key = $index . ':' . $firstRow;
            if (isset($memo[$key])) return $memo[$key];
            $best = ['custo' => INF, 'linhas' => []];
            $lastRow = $count - (count($items) - $index);
            for ($row = $firstRow; $row <= $lastRow; $row++) {
                $tail = $solve($index + 1, $row + 1);
                $cost = (($row + 0.5) / $count - $items[$index]['posicao']) ** 2 + $tail['custo'];
                if ($cost < $best['custo']) $best = ['custo' => $cost, 'linhas' => array_merge([$row], $tail['linhas'])];
            }
            return $memo[$key] = $best;
        };
        return $solve(0, 0)['linhas'];
    }

    private function minutes(string $time): ?int
    {
        if (!preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $time, $parts)) return null;
        $hours = (int) $parts[1]; $minutes = (int) $parts[2];
        return $hours <= 23 && $minutes <= 59 ? $hours * 60 + $minutes : null;
    }

    private function option(array $options, int $id): ?array
    {
        foreach ($options as $option) if ((int) $option['id'] === $id) return $option;
        return null;
    }
}
