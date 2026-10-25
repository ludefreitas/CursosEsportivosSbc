<?php
require __DIR__.'/fixtures/agenda_chart_database.php';
require __DIR__.'/../app/Services/AgendaChartService.php';
if (($argv[1]??'')==='--database') {createAgendaChartDatabase('sqlite:'.$argv[2]);exit;}
use App\Services\AgendaChartService;
function agendaExpect(bool $pass,string $message): void {if(!$pass)throw new RuntimeException($message);}
$db=createAgendaChartDatabase();$service=new AgendaChartService($db,new DateTimeImmutable('2026-10-25 12:00:00'));
$db->exec('PRAGMA query_only=ON');
$f=['from'=>'2026-10-01','to'=>'2026-10-31'];$r=$service->report($f);$t=$r['totals'];
agendaExpect($t['offered']===66 && $t['sessions']===8 && $t['closed']===2,'Oferta por ocorrência, sessões vazias e exclusão de fechamentos/suspensões.');
agendaExpect($t['reserved']===7 && $t['present']===3 && $t['absent']===1 && $t['justified']===1 && $t['cancelled']===1,'Reconciliação de status.');
agendaExpect($t['pending']===1 && $t['future']===1 && $t['attendance']===60.0 && $t['occupancy']===10.6,'Denominadores e cobertura da chamada.');
agendaExpect($t['available']===59 && $t['called']===5,'Disponibilidade separada da frequência.');
$pcd=$service->report($f+['public'=>'pcd']);agendaExpect($pcd['totals']['offered']===6 && $pcd['totals']['reserved']===1,'Público.');
$status=$service->report($f+['status'=>'falta']);agendaExpect($status['totals']['selected']===1 && $status['totals']['offered']===66 && $status['totals']['reserved']===7,'Filtro de status não altera denominador.');
$future=$service->report(['from'=>'2026-10-26','to'=>'2026-10-31']);agendaExpect($future['totals']['future']===1 && $future['totals']['absent']===0 && $future['totals']['pending']===0 && $future['totals']['attendance']===null,'Futuro não é falta.');
$special=$service->report($f+['type'=>'special']);agendaExpect(!$special['attendance_available'] && $special['totals']['offered']===32 && $special['totals']['unassessed']===1 && $special['totals']['future']===1 && $special['totals']['cancelled']===1,'Especiais sem chamada.');
$filtered=$service->report($f+['locations'=>['1'],'modalities'=>['1'],'spaces'=>['1'],'hour'=>'08:00','weekday'=>'1','period'=>'manha']);agendaExpect($filtered['totals']['offered']===42 && $filtered['totals']['sessions']===4,'Combinação dos filtros.');
$comparison=$service->report($f+['compare'=>'1']);agendaExpect($comparison['comparison']['to']==='2026-09-30' && $comparison['comparison']['from']==='2026-08-31','Comparação de intervalos inclusivos.');
$paged=$service->report(['from'=>'2026-10-01','to'=>'2027-09-30','page'=>'2']);
agendaExpect($paged['details']['page']===2 && count($paged['details']['items'])===30 && $paged['details']['pages']>2,'Paginação sem truncar os totais.');
foreach([['from'=>'2026-02-30'],['from'=>'2024-01-01'],['from'=>'2027-01-01'],['period'=>'x'],['locations'=>['1 OR 1=1']],['hour'=>'25:00']] as $invalid){try{$service->report(array_merge($f,$invalid));throw new LogicException('Filtro inválido aceito.');}catch(RuntimeException $e){}}
// Capacidade não duplica com reservas, excesso tem disponibilidade zero por sessão/público.
$db->exec('PRAGMA query_only=OFF');
$db->exec("INSERT INTO agendamentos(horario_semanal_id,data_agendada,publico_alvo,status) SELECT 1,'2026-10-05 08:00:00','geral','presente' FROM agendamentos");
$over=$service->report($f);agendaExpect($over['totals']['offered']===66,'Capacidade duplicada.');
agendaExpect($over['totals']['available']===53,'Piso da disponibilidade por público e sessão.');
$db->exec('DROP TABLE agenda_horarios_especiais_inscricoes');
try {$service->report($f+['type'=>'special']);throw new LogicException('Ausência da estrutura não sinalizada.');} catch (RuntimeException $e) {agendaExpect(str_contains($e->getMessage(),'migração separada'),'Mensagem da migração.');}
echo "OK: oferta por ocorrência, zero reservas, fechamentos, suspensões, status, frequência, futuros, filtros combinados, especiais e comparação.\n";
