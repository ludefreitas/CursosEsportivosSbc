<?php
require __DIR__ . '/fixtures/chart_database.php';
require __DIR__ . '/../app/Services/AdminChartService.php';
require __DIR__ . '/../app/Services/CourseAttendanceChartService.php';
require __DIR__ . '/../app/Services/CourseInterestService.php';
use App\Services\AdminChartService;

if (($argv[1] ?? '') === '--database') { createChartTestDatabase('sqlite:' . $argv[2]); echo "Base de teste preparada por CLI.\n"; exit; }
function expectChart(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$pdo = createChartTestDatabase();
$service = new AdminChartService($pdo);
$filters = ['modo' => 'multi', 'temporadas' => [1,2], 'max_temporadas' => 6];
$report = $service->report($filters);
expectChart($report['totals'] === ['classes'=>3,'locations'=>2,'modalities'=>2,'offered'=>26,'available'=>15,'enrollments'=>22], 'Totais incorretos ou turma duplicada pela junção de inscrições.');
expectChart($report['anomalies'] === 1, 'Divergência de capacidade não sinalizada.');
expectChart(array_sum(array_column($report['statuses'], 'count')) === 22, 'Status não reconciliados.');
expectChart(array_sum(array_column($report['publics'], 'offered')) === 26, 'Oferta por público incorreta.');
expectChart($report['locations'][0]['count'] === 2 && $report['locations'][0]['parts'][0]['count'] === 2, 'Agrupamento de turmas incorreto.');
$status = $service->report($filters + ['status'=>'aguardando_matricula']);
expectChart($status['totals']['enrollments'] === 1 && $status['totals']['available'] === 15, 'Filtrar status alterou a reserva real de vagas.');
$general = $service->report($filters + ['publico'=>'geral']);
expectChart($general['totals']['offered'] === 18 && $general['totals']['available'] === 8 && $general['totals']['enrollments'] === 18, 'Regra de público ou de excesso de ocupação incorreta.');
$pcd = $service->report($filters + ['publico'=>'pcd']);
expectChart($pcd['totals']['offered'] === 4 && $pcd['totals']['available'] === 3 && $pcd['totals']['enrollments'] === 4, 'Lista de espera ocupou vaga indevidamente.');
$range = $service->report(['modo'=>'range','de'=>1,'ate'=>3,'max_temporadas'=>3,'local_id'=>1]);
expectChart($range['totals']['classes'] === 2 && count($range['comparison']) === 3 && $range['comparison'][2]['classes'] === 0, 'Temporada sem turmas sumiu da comparação.');
$empty = $service->report($filters + ['local_id'=>1,'modalidade_id'=>2]);
expectChart($empty['totals']['classes'] === 0 && $empty['totals']['offered'] === 0 && count($empty['comparison']) === 2, 'Resultado vazio incorreto.');
$single = $service->report(['modo'=>'single','temporadas'=>[3]]);
expectChart($single['totals']['offered'] === 8 && $single['totals']['available'] === 8, 'Consulta de temporada isolada incorreta.');
foreach ([['temporadas'=>[999]],['modo'=>'range','de'=>3,'ate'=>1],['max_temporadas'=>1],['max_temporadas'=>13],['publico'=>'invalido'],['status'=>'invalido'],['temporadas'=>[]],['local_id'=>'1 OR 1=1'],['modo'=>'single'],['max_temporadas'=>[]]] as $bad) {
    try { $service->report(array_replace($filters,$bad)); throw new LogicException('Filtro inválido aceito: '.json_encode($bad)); } catch (RuntimeException $error) { if ($error instanceof LogicException) throw $error; }
}
// Paginação não modifica os totais dos gráficos.
$insert = $pdo->prepare("INSERT INTO turmas(id,temporada_id,nome,local_treino_id,modalidade_id,status,vagas_totais,vagas_geral,vagas_pcd,vagas_plm,vagas_pvs) VALUES(?,?,?,1,1,'inscricoes_abertas',10,10,0,0,0)");
for ($id=10;$id<45;$id++) $insert->execute([$id,2,'Turma '.$id]);
$page1 = $service->report($filters);
$page2 = $service->report($filters + ['pagina'=>2]);
expectChart(count($page1['details']['rows']) === 30 && count($page2['details']['rows']) === 8, 'Paginação incorreta.');
expectChart($page1['totals'] === $page2['totals'], 'Paginação alterou o resumo.');
expectChart(!array_intersect(array_column($page1['details']['rows'],'id'),array_column($page2['details']['rows'],'id')), 'Turma repetida entre páginas.');
// Datas são inclusivas e a procura não altera a capacidade ou o total histórico.
$interval = $filters + ['procura_de'=>'2025-06-15','procura_ate'=>'2025-06-15'];
$demand = $service->report($interval);
expectChart($demand['demand']['enrollments']['classes'][0]['id'] === 1 && $demand['demand']['enrollments']['classes'][0]['count'] === 12,'Ranking de procura incorreto.');
expectChart(array_sum(array_column($demand['demand']['enrollments']['periods'],'count'))===22,'Períodos da procura não reconciliados.');
$outside = $service->report(array_replace($interval,['procura_de'=>'2025-06-16','procura_ate'=>'2025-06-20']));
expectChart($outside['demand']['enrollments']['classes']===[] && $outside['totals']===$demand['totals'],'Intervalo de procura alterou o histórico geral.');
$morning = $service->report($interval+['periodo'=>'manha']);
expectChart(count($morning['demand']['enrollments']['classes'])===1 && $morning['demand']['enrollments']['classes'][0]['id']===3,'Filtro do período da aula incorreto.');
foreach([['procura_de'=>'2025-02-30'],['procura_de'=>'2025-07-01','procura_ate'=>'2025-06-01'],['periodo'=>'madrugada']]as$bad){try{$service->report(array_replace($filters,$bad));throw new LogicException('Data ou período inválido aceito.');}catch(RuntimeException $error){if($error instanceof LogicException)throw$error;}}
// O adaptador abaixo traduz apenas o UPSERT para testar a gravação MySQL em SQLite isolado.
$clickPdo = new class('sqlite::memory:') extends PDO {
    public function prepare(string $query,array $options=[]):PDOStatement|false {
        return parent::prepare(str_replace('ON DUPLICATE KEY UPDATE total=total+1','ON CONFLICT(dia,tipo,entidade_id) DO UPDATE SET total=total+1',$query),$options);
    }
};
createChartTestDatabase('sqlite::memory:',$clickPdo);
$interest = new \App\Services\CourseInterestService($clickPdo);
expectChart($interest->record('turma',1) && $interest->record('turma',1),'Clique válido não foi registrado.');
expectChart($interest->record('local',1) && $interest->record('modalidade',1),'Seleções de local/modalidade não registradas.');
expectChart(!$interest->record('turma',9999),'Clique em turma inexistente foi aceito.');
$clickReport=(new AdminChartService($clickPdo))->report($filters+['publico'=>'pcd','status'=>'cancelada']);
expectChart($clickReport['demand']['clicks']['classes'][0]['count']===2,'Filtro de público/status adulterou cliques anônimos.');
expectChart($clickReport['demand']['clicks']['periods'][2]['count']===2,'Período do clique não corresponde à aula.');
expectChart($clickReport['demand']['navigation']['locations'][0]['count']===1,'Clique de navegação misturado às turmas.');
expectChart($clickPdo->query('SELECT COUNT(*) FROM curso_interesse_diario')->fetchColumn()==3,'Contadores diários não foram agregados.');
foreach(['04:59'=>'noite','05:00'=>'manha','11:59'=>'manha','12:00'=>'tarde','17:59'=>'tarde','18:00'=>'noite',''=>'sem_horario','99:99'=>'sem_horario']as$time=>$period)expectChart(\App\Services\CourseInterestService::period($time)===$period,'Limite de horário incorreto.');
echo "OK: totais, públicos, status, reserva, capacidade excedida, intervalos, filtros inválidos, histórico, agrupamentos e paginação.\n";
echo "OK: procura por data, limites do dia, período das aulas, cliques reais agregados e ausência de associação de cliques a público/status.\n";
