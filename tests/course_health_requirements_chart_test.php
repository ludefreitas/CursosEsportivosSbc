<?php
require __DIR__ . '/fixtures/chart_database.php';
require __DIR__ . '/../app/Services/EnrollmentMovementService.php';
require __DIR__ . '/../app/Services/AdminChartService.php';
require __DIR__ . '/../app/Services/CourseAttendanceChartService.php';
require __DIR__ . '/../app/Services/CourseInterestService.php';
function checkRequirements(bool $valid, string $message): void { if (!$valid) throw new RuntimeException($message); }
$pdo = createChartTestDatabase();
$pdo->exec("UPDATE turmas SET regra_atestado_clinico='exigir' WHERE id IN(1,3);
    UPDATE turmas SET regra_atestado_dermatologico='exigir' WHERE id IN(2,3)");
$filters = ['modo'=>'multi','temporadas'=>[1,2,3],'max_temporadas'=>6];
$service = new App\Services\AdminChartService($pdo);
$health = $service->report($filters)['health_requirements'];
$counts = array_column($health['combinations'],'count','id');
checkRequirements($counts === ['clinico'=>1,'dermatologico'=>1,'ambos'=>1,'nenhum'=>1], 'Contar as quatro combinações sem sobreposição.');
checkRequirements($health['total']===4 && $health['clinico']===2 && $health['dermatologico']===2, 'Totais de exigência clínica e dermatológica.');
foreach (['locations','modalities','seasons'] as $kind) checkRequirements(array_sum(array_map(static fn($row)=>array_sum($row['counts']),$health['groups'][$kind]))===4, 'Agrupamentos devem reconciliar com o total de turmas.');
checkRequirements(array_column($health['classes'],'combination','id')[4]==='nenhum', 'Incluir turma sem inscrições.');
foreach ([['publico'=>'pcd'],['status'=>'cancelada'],['procura_de'=>'2020-01-01','procura_ate'=>'2020-01-01'],['periodo'=>'madrugada']] as $extra) {
    checkRequirements($service->report(array_merge($filters,$extra))['health_requirements']===$health, 'Filtro de inscrições/procura não altera a configuração da turma.');
}
checkRequirements($service->report($filters+['local_id'=>1])['health_requirements']['total']===2, 'Respeitar filtro de local.');
checkRequirements($service->report($filters+['modalidade_id'=>2])['health_requirements']['total']===2, 'Respeitar filtro de modalidade.');
$teacher = new App\Services\AdminChartService($pdo, professorAccountId:101);
$teacherFilters = array_merge($filters,['temporadas'=>[1,2]]);
checkRequirements(array_column($teacher->report($teacherFilters)['health_requirements']['classes'],'id')===[1,2], 'Professor principal deve ver somente suas turmas.');
$assistant = new App\Services\AdminChartService($pdo, professorAccountId:102);
$assistantIds = array_column($assistant->report($teacherFilters)['health_requirements']['classes'],'id');
sort($assistantIds);
checkRequirements($assistantIds===[1,3], 'Professor auxiliar pode ver turmas vinculadas, sem acessar as demais.');
checkRequirements($teacher->report($teacherFilters+['turma_id'=>3])['health_requirements']['total']===0, 'Não acessar turma de outro professor forjando filtro.');
$pdo->exec("UPDATE modalidades SET tipo_ambiente='aquatica' WHERE id=2;
    UPDATE turmas SET regra_atestado_clinico='global',regra_atestado_dermatologico='global' WHERE id IN(1,3);
    UPDATE turmas SET regra_atestado_clinico='dispensar',regra_atestado_dermatologico='global' WHERE id IN(2,4)");
$pdo->exec('PRAGMA query_only=ON');
$global = $service->report($filters)['health_requirements'];
$byId = array_column($global['classes'],null,'id');
checkRequirements($byId[1]['combination']==='clinico' && $byId[2]['combination']==='nenhum', 'Aplicar regra global em modalidade terrestre e respeitar dispensa explícita.');
checkRequirements($byId[3]['combination']==='ambos' && $byId[4]['combination']==='dermatologico', 'Aplicar regra global aquática e respeitar dispensa explícita do clínico.');
checkRequirements($service->report($filters+['local_id'=>999])['health_requirements']['combinations'][0]['count']===0, 'Tratar resultado vazio sem inventar turmas.');
echo "Exigência de atestados por turma: quatro combinações, regra global, dispensas, grupos, filtros, turmas vazias e escopo de professores verificados somente com leitura.\n";
