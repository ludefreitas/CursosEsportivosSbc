<?php
require __DIR__.'/fixtures/chart_database.php';
require __DIR__.'/../app/Services/CourseInterestService.php';
require __DIR__.'/../app/Services/CourseAttendanceChartService.php';
function attendanceFixture(string $dsn,string $today): PDO
{
    $db=createChartTestDatabase($dsn);
    $db->exec("UPDATE turmas SET hora_inicio='09:00:00'; INSERT INTO turmas(id,temporada_id,nome,local_treino_id,modalidade_id,status,vagas_totais,vagas_geral,vagas_pcd,vagas_plm,vagas_pvs,hora_inicio) VALUES(5,2,'Turma noturna',1,1,'inscricoes_abertas',10,10,0,0,0,'19:00:00'); INSERT INTO inscricoes_turma(turma_id,publico_alvo,status) VALUES(5,'geral','matriculada');");
    $insert=$db->prepare('INSERT INTO turmas_chamadas(turma_id,inscricao_turma_id,data_aula,status) VALUES(?,?,?,?)');
    $first=$db->query("SELECT id FROM inscricoes_turma WHERE turma_id=1 AND status='matriculada' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $insert->execute([1,$first[0],$today,'presente']);$insert->execute([1,$first[1],$today,'ausente']);
    $second=$db->query("SELECT id FROM inscricoes_turma WHERE turma_id=2 AND status='matriculada' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    foreach($second as $i=>$id)$insert->execute([2,$id,$today,$i===0?'justificado':'presente']);
    $insert->execute([1,$first[0],(new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d'),'presente']);
    return $db;
}
if(($argv[1]??'')==='--database'){$fixture=attendanceFixture('sqlite:'.$argv[2],(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d'));$fixture->exec("UPDATE turmas SET hora_inicio='00:00:00' WHERE id<5");exit;}
$db=attendanceFixture('sqlite::memory:','2026-10-25');$service=new App\Services\CourseAttendanceChartService($db,new DateTimeImmutable('2026-10-25 10:00:00'));
$filters=['public'=>'','period'=>''];$input=['frequencia_de'=>'2026-10-25','frequencia_ate'=>'2026-10-26'];
$db->exec('PRAGMA query_only=ON');$r=$service->report('t.temporada_id IN (:a,:b)',[':a'=>1,':b'=>2],$filters,$input);
function attendanceExpect(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
attendanceExpect($r['totals']['present']===6&&$r['totals']['absent']===1&&$r['totals']['justified']===1&&$r['totals']['attendance']===75.0,'Totais e denominadores.');
attendanceExpect($r['totals']['future_excluded']===1&&$r['totals']['records']===8,'Frequências futuras não viram presenças realizadas.');
$classGroups=array_column($r['groups']['classes'],null,'id');
attendanceExpect(str_contains($classGroups[1]['label'],'Segunda-feira') && str_contains($classGroups[1]['label'],'Domingo · 09:00 às 20:00'), 'Exibir dias da semana e horário da turma na frequência.');
$map=array_column($r['monitor'],null,'id');attendanceExpect($map[1]['state']==='parcial'&&$map[1]['expected']===3&&$map[1]['pending']===1,'Chamada parcial por aluno.');
attendanceExpect($map[2]['state']==='completa'&&$map[2]['completed']===6,'Chamada completa.');
attendanceExpect($map[3]['state']==='nao_iniciada'&&$map[5]['state']==='aguardando','Não iniciada versus horário futuro.');
$pcd=$service->report('t.id=:id',[':id'=>1],['public'=>'pcd','period'=>''],$input);attendanceExpect($pcd['monitor'][0]['expected']===1&&$pcd['monitor'][0]['state']==='nao_iniciada','Cobertura pelo público.');
$night=$service->report('t.id>0',[],['public'=>'','period'=>'noite'],$input);attendanceExpect(count($night['monitor'])===1&&$night['monitor'][0]['id']===5,'Período aplicado à frequência e monitor.');
$week=$service->report('t.id>0',[],$filters,$input+['monitor_de'=>'2026-10-19','monitor_ate'=>'2026-10-25','monitor_turma'=>'1']);
attendanceExpect(count($week['monitor'])===7&&$week['states']['nao_iniciada']===6&&$week['states']['parcial']===1,'Todas as aulas do intervalo, inclusive sem chamada.');
attendanceExpect($week['monitor_totals']['expected']===21&&$week['monitor_totals']['completed']===2&&$week['monitor_totals']['pending']===19,'Cobertura por aula no período.');
$paged=$service->report('t.id>0',[],$filters,$input+['monitor_de'=>'2026-10-01','monitor_ate'=>'2026-10-31','monitor_pagina'=>'2']);
attendanceExpect(count($paged['monitor'])===30&&$paged['monitor_pagination']['page']===2&&$paged['monitor_scheduled']===155,'Paginação não trunca as aulas monitoradas.');
foreach([['frequencia_de'=>'2026-02-30'],['frequencia_de'=>'2024-01-01'],['frequencia_ate'=>'2025-01-01']] as $invalid){try{$service->report('t.id>0',[],$filters,array_merge($input,$invalid));throw new LogicException('Data inválida aceita.');}catch(RuntimeException $error){}}
$db->exec('PRAGMA query_only=OFF');$db->exec("UPDATE turmas SET dias_semana='1' WHERE id=1; UPDATE turmas SET cronograma_modalidade_id=NULL WHERE id=3");
$changed=$service->report('t.id>0',[],$filters,$input+['monitor_situacao'=>'']);$map=array_column($changed['monitor'],null,'id');attendanceExpect($map[1]['state']==='sem_aula'&&$map[3]['state']==='sem_cronograma','Sem aula não é falha de chamada.');
$db->exec('DROP TABLE turmas_chamadas');$missing=$service->report('t.id>0',[],$filters,$input);attendanceExpect($missing['available']===false,'Ausência de migração sinalizada.');
echo "OK: frequência, denominadores, futuro, chamada parcial/completa/não iniciada, horário futuro, públicos, períodos, calendário e ausência de estrutura; consultas somente leitura.\n";
