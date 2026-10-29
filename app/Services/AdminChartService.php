<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/** Consultas exclusivamente de leitura; capacidade e inscrições são agregadas separadamente. */
class AdminChartService
{
    public const MAX_SEASONS = 12;
    public const DETAIL_PAGE_SIZE = 30;
    public const PUBLICS = ['geral' => 'Geral', 'pcd' => 'PCD', 'plm' => 'PLM', 'pvs' => 'PVS'];
    public const STATUSES = [
        'matriculada' => 'Matriculada', 'aguardando_matricula' => 'Aguardando matrícula',
        'lista_espera' => 'Lista de espera', 'suspensa' => 'Suspensa', 'cancelada' => 'Cancelada',
        'excluida' => 'Excluída', 'excluida_por_falta' => 'Excluída por falta', 'desistente' => 'Desistente',
    ];

    public function __construct(private ?PDO $pdo = null) {}

    private function connection(): PDO { return $this->pdo ?? Database::connection(); }

    public function seasons(): array
    {
        return $this->connection()->query('SELECT id, nome, data_inicio, data_fim, status FROM temporadas ORDER BY data_inicio, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function options(): array
    {
        $pdo = $this->connection();
        return [
            'seasons' => $this->seasons(),
            'locations' => $pdo->query("SELECT DISTINCT l.id, COALESCE(NULLIF(TRIM(l.apelido_local), ''), l.nome_local) AS nome FROM locais_treino l INNER JOIN turmas t ON t.local_treino_id=l.id ORDER BY nome, l.id")->fetchAll(PDO::FETCH_ASSOC),
            'modalities' => $pdo->query('SELECT DISTINCT m.id, m.nome FROM modalidades m INNER JOIN turmas t ON t.modalidade_id=m.id ORDER BY m.nome, m.id')->fetchAll(PDO::FETCH_ASSOC),
            'publics' => self::PUBLICS, 'statuses' => self::STATUSES, 'max_seasons' => self::MAX_SEASONS,
        ];
    }

    private function positiveInt(mixed $value, string $label): int
    {
        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
            throw new RuntimeException('Informe um valor válido para ' . $label . '.');
        }
        return (int) $value;
    }

    public function normalizeFilters(array $input, array $seasons): array
    {
        $mode = $input['modo'] ?? 'multi';
        if (!in_array($mode, ['single', 'multi', 'range'], true)) throw new RuntimeException('Selecione uma forma válida de consultar as temporadas.');
        $limit = $this->positiveInt($input['max_temporadas'] ?? 6, 'o máximo de temporadas');
        if ($limit > self::MAX_SEASONS) throw new RuntimeException('O máximo permitido é de ' . self::MAX_SEASONS . ' temporadas por consulta.');
        $known = array_map('intval', array_column($seasons, 'id'));
        if ($mode === 'range') {
            $from = array_search($this->positiveInt($input['de'] ?? 0, 'a primeira temporada'), $known, true);
            $to = array_search($this->positiveInt($input['ate'] ?? 0, 'a última temporada'), $known, true);
            if ($from === false || $to === false || $from > $to) throw new RuntimeException('Selecione um intervalo válido de temporadas, da mais antiga à mais recente.');
            $ids = array_slice($known, $from, $to - $from + 1);
        } else {
            $values = $input['temporadas'] ?? [];
            if (!is_array($values) || count($values) > self::MAX_SEASONS) throw new RuntimeException('Selecione até ' . self::MAX_SEASONS . ' temporadas.');
            $ids = array_values(array_unique(array_map(fn ($id) => $this->positiveInt($id, 'a temporada'), $values)));
            if (array_diff($ids, $known)) throw new RuntimeException('Uma das temporadas selecionadas não existe.');
            if ($mode === 'single' && count($ids) !== 1) throw new RuntimeException('Selecione uma única temporada.');
        }
        if (!$ids) throw new RuntimeException('Selecione pelo menos uma temporada.');
        if (count($ids) > $limit) throw new RuntimeException('A seleção contém ' . count($ids) . ' temporadas. Aumente o limite ou reduza a seleção.');
        $public = $input['publico'] ?? '';
        $status = $input['status'] ?? '';
        if (!is_string($public) || ($public !== '' && !isset(self::PUBLICS[$public]))) throw new RuntimeException('Selecione um público válido.');
        if (!is_string($status) || ($status !== '' && !isset(self::STATUSES[$status]))) throw new RuntimeException('Selecione um status de inscrição válido.');
        $location = empty($input['local_id']) ? 0 : $this->positiveInt($input['local_id'], 'o local');
        $modality = empty($input['modalidade_id']) ? 0 : $this->positiveInt($input['modalidade_id'], 'a modalidade');
        $page = $this->positiveInt($input['pagina'] ?? 1, 'a página');
        $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Sao_Paulo'));
        $from = $input['procura_de'] ?? $today->modify('-29 days')->format('Y-m-d');
        $to = $input['procura_ate'] ?? $today->format('Y-m-d');
        foreach ([$from,$to] as $date) {
            if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || ($parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date)) === false || $parsed->format('Y-m-d') !== $date) throw new RuntimeException('Informe datas válidas para o intervalo de procura.');
        }
        if ($from > $to) throw new RuntimeException('A data inicial da procura deve ser anterior ou igual à data final.');
        $period = $input['periodo'] ?? '';
        if (!is_string($period) || ($period !== '' && !isset(CourseInterestService::PERIODS[$period]))) throw new RuntimeException('Selecione um período da aula válido.');
        return ['season_ids' => array_values(array_intersect($known, $ids)), 'mode' => $mode, 'limit' => $limit, 'location' => $location, 'modality' => $modality, 'public' => $public, 'status' => $status, 'page' => $page, 'demand_from'=>$from,'demand_to'=>$to,'period'=>$period];
    }

    public function report(array $input): array
    {
        $seasons = $this->seasons();
        $filters = $this->normalizeFilters($input, $seasons);
        $params = []; $placeholders = [];
        foreach ($filters['season_ids'] as $index => $id) { $key = ':season' . $index; $placeholders[] = $key; $params[$key] = $id; }
        $where = 't.temporada_id IN (' . implode(',', $placeholders) . ')';
        if ($filters['location']) { $where .= ' AND t.local_treino_id=:local'; $params[':local'] = $filters['location']; }
        if ($filters['modality']) { $where .= ' AND t.modalidade_id=:modalidade'; $params[':modalidade'] = $filters['modality']; }
        $pdo = $this->connection();
        $statement = $pdo->prepare("SELECT t.professor_conta_id, principal.nome_completo AS professor_nome,t.id,t.temporada_id,t.nome,t.local_treino_id,t.modalidade_id,t.status,t.hora_inicio,t.hora_fim,t.dias_semana,t.vagas_totais,t.vagas_geral,t.vagas_pcd,t.vagas_plm,t.vagas_pvs,
            COALESCE(NULLIF(TRIM(l.apelido_local), ''),l.nome_local) AS local_nome,m.nome AS modalidade_nome
            FROM turmas t INNER JOIN locais_treino l ON l.id=t.local_treino_id INNER JOIN modalidades m ON m.id=t.modalidade_id LEFT JOIN contas principal_conta ON principal_conta.id=t.professor_conta_id LEFT JOIN pessoas principal ON principal.cpf=principal_conta.cpf
            WHERE $where ORDER BY local_nome,m.nome,t.nome,t.id");
        $statement->execute($params);
        $classes = $statement->fetchAll(PDO::FETCH_ASSOC);
        $teams = [];
        foreach (['auxiliares'=>['turmas_professores','professor_conta_id'], 'estagiarios'=>['turmas_estagiarios','estagiario_conta_id']] as $kind=>[$table,$column]) {
            $team = $pdo->prepare("SELECT t.id, p.nome_completo FROM turmas t INNER JOIN $table equipe ON equipe.turma_id=t.id INNER JOIN contas c ON c.id=equipe.$column INNER JOIN pessoas p ON p.cpf=c.cpf WHERE $where" . ($kind==='auxiliares' ? ' AND (t.professor_conta_id IS NULL OR equipe.professor_conta_id<>t.professor_conta_id)' : '') . ' ORDER BY p.nome_completo');
            $team->execute($params);
            foreach ($team->fetchAll(PDO::FETCH_ASSOC) as $member) $teams[(int)$member['id']][$kind][] = $member['nome_completo'];
        }
        foreach ($classes as &$class) $class['equipe'] = $teams[(int)$class['id']] ?? [];
        unset($class);
        // O status escolhido não entra neste WHERE: a reserva de vagas exige todos os status ocupantes.
        $statement = $pdo->prepare("SELECT i.turma_id,i.publico_alvo,i.status,COUNT(*) AS total FROM inscricoes_turma i INNER JOIN turmas t ON t.id=i.turma_id WHERE $where GROUP BY i.turma_id,i.publico_alvo,i.status");
        $statement->execute($params);
        $result = $this->aggregate($classes, $statement->fetchAll(PDO::FETCH_ASSOC), $seasons, $filters);
        $statement = $pdo->prepare("SELECT i.turma_id,i.publico_alvo,i.status,COUNT(*) AS total FROM inscricoes_turma i INNER JOIN turmas t ON t.id=i.turma_id WHERE $where AND i.created_at>=:procura_de AND i.created_at<:procura_fim GROUP BY i.turma_id,i.publico_alvo,i.status");
        $statement->execute($params+[':procura_de'=>$filters['demand_from'].' 00:00:00',':procura_fim'=>(new \DateTimeImmutable($filters['demand_to']))->modify('+1 day')->format('Y-m-d').' 00:00:00']);
        $result['demand'] = $this->aggregateDemand($classes,$statement->fetchAll(PDO::FETCH_ASSOC),(new CourseInterestService($pdo))->report($filters),$filters);
        $result['enrollment_trend'] = $this->enrollmentTrend($where, $params, $classes, $filters, $input);
        $result['movements'] = (new EnrollmentMovementService($pdo))->report($filters);
        $result['attendance'] = (new CourseAttendanceChartService($pdo))->report($where,$params,$filters,$input);
        $attendance = array_column($result['attendance']['groups']['professors'] ?? [], null, 'id');
        $classAttendance = array_column($result['attendance']['groups']['classes'] ?? [], null, 'id');
        foreach ($result['professors'] as &$professor) {
            foreach ($professor['rows'] as &$row) $row['frequency'] = ($result['attendance']['available'] ?? false) ? ($classAttendance[$row['id']] ?? ['records'=>0,'attendance'=>null]) : null;
            unset($row);
            $professor['frequency'] = ($result['attendance']['available'] ?? false) ? ($attendance[$professor['id']] ?? ['present'=>0,'absent'=>0,'justified'=>0,'records'=>0,'attendance'=>null]) : null;
        }
        unset($professor);
        return $result;
    }

    private function enrollmentTrend(string $where, array $params, array $classes, array $filters, array $input): array
    {
        $year = $this->positiveInt($input['evolucao_ano'] ?? (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y'), 'o ano da evolução');
        if ($year < 2000 || $year > 2100) throw new RuntimeException('Selecione um ano entre 2000 e 2100 para a evolução.');
        $params[':evolucao_inicio'] = $year . '-01-01 00:00:00';
        $params[':evolucao_fim'] = ($year + 1) . '-01-01 00:00:00';
        if ($filters['public'] !== '') { $where .= ' AND i.publico_alvo=:evolucao_publico'; $params[':evolucao_publico'] = $filters['public']; }
        if ($filters['status'] !== '') { $where .= ' AND i.status=:evolucao_status'; $params[':evolucao_status'] = $filters['status']; }
        $stmt = $this->connection()->prepare("SELECT i.turma_id, DATE(i.created_at) AS dia, COUNT(*) AS total FROM inscricoes_turma i INNER JOIN turmas t ON t.id=i.turma_id WHERE $where AND i.created_at>=:evolucao_inicio AND i.created_at<:evolucao_fim GROUP BY i.turma_id, DATE(i.created_at) ORDER BY dia");
        $stmt->execute($params);
        $daily = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $daily[(int)$row['turma_id']][$row['dia']] = (int)$row['total'];
        $modalities = [];
        foreach ($classes as $class) {
            if ($filters['period'] !== '' && CourseInterestService::period((string)($class['hora_inicio'] ?? '')) !== $filters['period']) continue;
            $id = (int)$class['modalidade_id'];
            $modalities[$id] ??= ['id'=>$id, 'label'=>$class['modalidade_nome'], 'classes'=>[]];
            $modalities[$id]['classes'][] = ['id'=>(int)$class['id'], 'label'=>'['.$class['id'].'] '.$class['nome'].' · '.$class['local_nome'], 'schedule'=>CourseInterestService::describeSchedule($class), 'days'=>$daily[(int)$class['id']] ?? []];
        }
        $modalities = array_values($modalities);
        usort($modalities, static fn($a,$b)=>strnatcasecmp($a['label'],$b['label']));
        return ['year'=>$year, 'modalities'=>$modalities];
    }

    public function aggregateDemand(array $classes, array $enrollments, array $clicks, array $filters): array
    {
        $newMetrics = static fn()=>['locations'=>[],'modalities'=>[],'classes'=>[],'periods'=>array_map(static fn($id,$label)=>['id'=>$id,'label'=>$label,'count'=>0,'modalities'=>[]],array_keys(CourseInterestService::PERIODS),array_values(CourseInterestService::PERIODS))];
        $registered=$newMetrics(); $interested=$newMetrics(); $counts=[];
        foreach ($enrollments as $entry) {
            if ($filters['public']!=='' && $filters['public']!==(string)$entry['publico_alvo']) continue;
            if ($filters['status']!=='' && $filters['status']!==(string)$entry['status']) continue;
            $id=(int)$entry['turma_id']; $counts[$id]=($counts[$id]??0)+(int)$entry['total'];
        }
        $append = static function(array &$metrics,string $kind,int $id,string $label,int $count,string $context=''):void {
            $metrics[$kind][$id]??=['id'=>$id,'label'=>$label,'count'=>0,'context'=>$context];
            $metrics[$kind][$id]['count']+=$count;
        };
        $add = static function(array &$metrics,array $row,int $count,string $period)use($append):void {
            if (!$count) return;
            $append($metrics,'locations',(int)$row['local_id'],(string)$row['local_label'],$count);
            $append($metrics,'modalities',(int)$row['modality_id'],(string)$row['modality_label'],$count);
            $append($metrics,'classes',(int)$row['id'],'['.$row['id'].'] '.$row['name'],$count,$row['local_label'].' · '.$row['modality_label'].' · '.$row['schedule']);
            foreach($metrics['periods'] as &$item) if($item['id']===$period) {
                $item['count'] += $count;
                $id = (int) $row['modality_id'];
                $item['modalities'][$id] ??= ['id'=>$id, 'label'=>(string)$row['modality_label'], 'count'=>0];
                $item['modalities'][$id]['count'] += $count;
            }
            unset($item);
        };
        foreach($classes as $class){
            $period=CourseInterestService::period((string)($class['hora_inicio']??''));
            if($filters['period']!=='' && $filters['period']!==$period)continue;
            $add($registered,['id'=>(int)$class['id'],'name'=>$class['nome'],'local_id'=>(int)$class['local_treino_id'],'local_label'=>$class['local_nome'],'modality_id'=>(int)$class['modalidade_id'],'modality_label'=>$class['modalidade_nome'],'schedule'=>CourseInterestService::describeSchedule($class)],$counts[(int)$class['id']]??0,$period);
        }
        foreach($clicks['classes'] as $row){
            $period=isset($row['hora_inicio']) ? CourseInterestService::period((string)$row['hora_inicio']) : (string)$row['periodo'];
            if($filters['period']!=='' && $filters['period']!==$period)continue;
            $add($interested,['id'=>(int)$row['turma_id'],'name'=>$row['turma_nome']??'Turma removida','local_id'=>(int)$row['local_id'],'local_label'=>trim((string)($row['apelido_local']??''))?:($row['nome_local']??'Local removido'),'modality_id'=>(int)$row['modalidade_id'],'modality_label'=>$row['modalidade_nome']??'Modalidade removida','schedule'=>CourseInterestService::describeSchedule($row)],(int)$row['total'],$period);
        }
        $sort = static function(array $metrics):array {
            foreach(['locations','modalities','classes'] as $key){$values=array_values($metrics[$key]);usort($values,static fn($a,$b)=>($b['count']<=>$a['count'])?:strnatcasecmp($a['label'],$b['label']));$metrics[$key]=array_slice($values,0,10);}
            foreach ($metrics['periods'] as &$period) {
                $period['modalities'] = array_values($period['modalities']);
                usort($period['modalities'], static fn($a,$b)=>($b['count']<=>$a['count'])?:strnatcasecmp($a['label'],$b['label']));
            }
            unset($period);
            return $metrics;
        };
        $navigation=['locations'=>[],'modalities'=>[]];
        foreach($clicks['navigation'] as $row){$kind=$row['tipo']==='local'?'locations':'modalities';$navigation[$kind][]=['id'=>(int)$row['entidade_id'],'label'=>$kind==='locations'?(trim((string)($row['apelido_local']??''))?:($row['nome_local']??'Local removido')):($row['modalidade_nome']??'Modalidade removida'),'count'=>(int)$row['total']];}
        foreach($navigation as &$items){usort($items,static fn($a,$b)=>($b['count']<=>$a['count'])?:strnatcasecmp($a['label'],$b['label']));$items=array_slice($items,0,10);}unset($items);
        return ['from'=>$filters['demand_from'],'to'=>$filters['demand_to'],'period'=>$filters['period'],'clicks_available'=>$clicks['available'],'enrollments'=>$sort($registered),'clicks'=>$sort($interested),'navigation'=>$navigation];
    }

    public function aggregate(array $classes, array $enrollments, array $seasons, array $filters): array
    {
        $seasonMap = [];
        foreach ($seasons as $season) if (in_array((int) $season['id'], $filters['season_ids'], true)) {
            $seasonMap[(int) $season['id']] = ['id' => (int) $season['id'], 'label' => (string) $season['nome'], 'offered' => 0, 'available' => 0, 'enrollments' => 0, 'classes' => 0];
        }
        $indexed = [];
        foreach ($enrollments as $entry) {
            $classId = (int) $entry['turma_id']; $public = (string) $entry['publico_alvo']; $status = (string) $entry['status'];
            $indexed[$classId][$public][$status] = ($indexed[$classId][$public][$status] ?? 0) + (int) $entry['total'];
        }
        $publics = [];
        foreach (self::PUBLICS as $key => $label) $publics[$key] = ['id' => $key, 'label' => $label, 'offered' => 0, 'available' => 0, 'enrollments' => 0];
        $statuses = [];
        foreach (self::STATUSES as $key => $label) $statuses[$key] = ['id' => $key, 'label' => $label, 'count' => 0];
        $totals = ['classes' => 0, 'locations' => 0, 'modalities' => 0, 'offered' => 0, 'available' => 0, 'enrollments' => 0];
        $locations = []; $modalities = []; $detailRows = []; $anomalies = 0; $professors = [];
        foreach ($classes as $class) {
            $id = (int) $class['id']; $seasonId = (int) $class['temporada_id'];
            if (!isset($seasonMap[$seasonId])) continue;
            $localId = (int) $class['local_treino_id']; $modalityId = (int) $class['modalidade_id'];
            $localLabel = (string) $class['local_nome']; $modalityLabel = (string) $class['modalidade_nome'];
            $locations[$localId] ??= ['id' => $localId, 'label' => $localLabel, 'count' => 0, 'parts' => []];
            $modalities[$modalityId] ??= ['id' => $modalityId, 'label' => $modalityLabel, 'count' => 0, 'parts' => []];
            $locations[$localId]['count']++; $modalities[$modalityId]['count']++;
            $locations[$localId]['parts'][$modalityId] ??= ['id' => $modalityId, 'label' => $modalityLabel, 'count' => 0];
            $modalities[$modalityId]['parts'][$localId] ??= ['id' => $localId, 'label' => $localLabel, 'count' => 0];
            $locations[$localId]['parts'][$modalityId]['count']++; $modalities[$modalityId]['parts'][$localId]['count']++;
            $offered = 0; $available = 0; $count = 0; $capacitySum = 0;
            $enrolled = 0; $awaiting = 0; $waitingList = 0;
            foreach (self::PUBLICS as $public => $label) {
                $capacity = max(0, (int) ($class['vagas_' . $public] ?? 0)); $capacitySum += $capacity;
                if ($filters['public'] !== '' && $filters['public'] !== $public) continue;
                $entries = $indexed[$id][$public] ?? [];
                $enrolled += (int) ($entries['matriculada'] ?? 0);
                $awaiting += (int) ($entries['aguardando_matricula'] ?? 0);
                $waitingList += (int) ($entries['lista_espera'] ?? 0);
                $occupied = (int) ($entries['matriculada'] ?? 0) + (int) ($entries['aguardando_matricula'] ?? 0) + (int) ($entries['suspensa'] ?? 0);
                $free = max(0, $capacity - $occupied);
                $offered += $capacity; $available += $free;
                $publics[$public]['offered'] += $capacity; $publics[$public]['available'] += $free;
                foreach ($entries as $status => $amount) {
                    if ($filters['status'] !== '' && $filters['status'] !== $status) continue;
                    $statuses[$status] ??= ['id' => $status, 'label' => ucfirst(str_replace('_', ' ', $status)), 'count' => 0];
                    $statuses[$status]['count'] += $amount; $count += $amount;
                    $publics[$public]['enrollments'] += $amount;
                }
            }
            $mismatch = $capacitySum !== (int) ($class['vagas_totais'] ?? 0);
            if ($mismatch) $anomalies++;
            $totals['classes']++; $totals['offered'] += $offered; $totals['available'] += $available; $totals['enrollments'] += $count;
            $seasonMap[$seasonId]['classes']++; $seasonMap[$seasonId]['offered'] += $offered; $seasonMap[$seasonId]['available'] += $available; $seasonMap[$seasonId]['enrollments'] += $count;
            $detailRows[] = ['id' => $id, 'name' => (string) $class['nome'], 'season' => $seasonMap[$seasonId]['label'], 'location' => $localLabel, 'modality' => $modalityLabel, 'schedule' => CourseInterestService::describeSchedule($class), 'offered' => $offered, 'available' => $available, 'enrollments' => $count, 'enrolled' => $enrolled, 'awaiting_enrollment' => $awaiting, 'waiting_list' => $waitingList, 'capacity_mismatch' => $mismatch];
            $professorId = (int) ($class['professor_conta_id'] ?? 0);
            $professors[$professorId] ??= ['id'=>$professorId, 'label'=>$professorId ? ((string)($class['professor_nome'] ?? '') ?: 'Professor #'.$professorId) : 'Sem professor principal', 'classes'=>0, 'offered'=>0, 'available'=>0, 'enrollments'=>0, 'enrolled'=>0, 'awaiting_enrollment'=>0, 'waiting_list'=>0, 'rows'=>[]];
            $professors[$professorId]['classes']++;
            foreach (['offered'=>$offered,'available'=>$available,'enrollments'=>$count,'enrolled'=>$enrolled,'awaiting_enrollment'=>$awaiting,'waiting_list'=>$waitingList] as $key=>$value) $professors[$professorId][$key] += $value;
            $professors[$professorId]['rows'][] = $detailRows[array_key_last($detailRows)] + ['auxiliares'=>$class['equipe']['auxiliares'] ?? [], 'estagiarios'=>$class['equipe']['estagiarios'] ?? []];

        }
        $sortGroups = static function (array $groups): array {
            $groups = array_values($groups);
            usort($groups, static fn ($a, $b) => strnatcasecmp($a['label'], $b['label']));
            foreach ($groups as &$group) { $group['parts'] = array_values($group['parts']); usort($group['parts'], static fn ($a, $b) => strnatcasecmp($a['label'], $b['label'])); }
            unset($group);
            return $groups;
        };
        $totals['locations'] = count($locations); $totals['modalities'] = count($modalities);
        $locations = $sortGroups($locations); $modalities = $sortGroups($modalities);
        $pages = max(1, (int) ceil(count($detailRows) / self::DETAIL_PAGE_SIZE));
        $page = min($filters['page'], $pages);
        return [
            'professors' => array_values($professors),
            'totals' => $totals, 'publics' => array_values($publics), 'statuses' => array_values($statuses),
            'locations' => $locations, 'modalities' => $modalities, 'comparison' => array_values($seasonMap),
            'anomalies' => $anomalies,
            'details' => ['rows' => array_slice($detailRows, ($page - 1) * self::DETAIL_PAGE_SIZE, self::DETAIL_PAGE_SIZE), 'page' => $page, 'pages' => $pages, 'total' => count($detailRows)],
            'filters' => $filters, 'generated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i:s'),
        ];
    }
}
