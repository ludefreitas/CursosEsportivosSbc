<?php
require __DIR__ . '/fixtures/chart_database.php';
require __DIR__ . '/../app/Services/EnrollmentMovementService.php';
require __DIR__ . '/../app/Services/AdminChartService.php';
require __DIR__ . '/../app/Services/CourseAttendanceChartService.php';
require __DIR__ . '/../app/Services/CourseInterestService.php';
function relationshipCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$pdo = createChartTestDatabase();
$service = new App\Services\AdminChartService($pdo);
$input = ['modo'=>'multi','temporadas'=>[1,2],'max_temporadas'=>6];
$today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
$insert = $pdo->prepare('INSERT INTO pessoas(cpf,nome_completo,id,data_nascimento) VALUES(?,?,?,?)');
$insert->execute(['aluno1','Aluno de teste',201,$today->modify('-20 years')->format('Y-m-d')]);
$insert->execute(['aluno2','Outro aluno',202,'2020-02-30']);
$pdo->exec('UPDATE inscricoes_turma SET pessoa_id=201 WHERE id IN (1,2); UPDATE inscricoes_turma SET pessoa_id=202 WHERE id=3');
$report = $service->report($input);
$relations = $report['age_relationships'];
relationshipCheck(array_sum(array_column($relations['groups'],'offered'))===26,'Não multiplicar capacidade pelas inscrições.');
relationshipCheck(array_sum(array_column($relations['groups'],'available'))===15,'Disponibilidade deve reconciliar com o painel.');
relationshipCheck(array_sum(array_column($relations['groups'],'enrollments'))===22,'Inscrições devem reconciliar com o painel.');
relationshipCheck(array_sum(array_column($relations['ages'],'enrollments'))===22,'Todas as inscrições precisam de uma faixa, inclusive dados ausentes.');
$young = array_values(array_filter($relations['ages'],fn($row)=>$row['age']==='16–20 anos'));
relationshipCheck($young[0]['enrollments']===2,'Contar inscrições, mesmo quando pertencem à mesma pessoa.');
$filtered=$service->report($input+['publico'=>'geral','status'=>'cancelada'])['age_relationships'];
relationshipCheck(array_sum(array_column($filtered['groups'],'reserved'))===11,'Status cancelado não pode alterar as reservas atuais.');
relationshipCheck(array_sum(array_column($filtered['groups'],'enrollments'))===1,'Respeitar filtro de status.');
relationshipCheck(array_sum(array_column($filtered['ages'],'enrollments'))===1,'Filtro de status nas idades reais.');
$pdo->exec("UPDATE turmas SET criterio_faixa_etaria='ano_nascimento',idade_minima=18,idade_maxima=40 WHERE id=1");
$local=$service->report($input+['local_id'=>1,'modalidade_id'=>1])['age_relationships'];
relationshipCheck(count($local['groups'])===2,'Regras por nascimento e idade exata não podem ser fundidas.');
relationshipCheck(count(array_filter($local['groups'],fn($row)=>$row['age_rule']==='Nascidos entre 1984 e 2006'))===1,'Regra de nascimento deve usar ano da temporada.');
relationshipCheck(max(array_column($local['groups'],'occupancy'))===100.0,'Ocupação por reservas da combinação.');
$pdo->exec('UPDATE turmas SET vagas_geral=0,vagas_pcd=0,vagas_plm=0,vagas_pvs=0 WHERE id=2');
$zero=$service->report($input)['age_relationships'];
relationshipCheck(count(array_filter($zero['groups'],fn($row)=>$row['offered']===0 && $row['reserved']===6 && $row['occupancy']===null))===1,'Sem capacidade, taxa nula e reservas preservadas.');
echo "OK: cruzamentos por local/modalidade/regra, capacidade sem duplicação, reservas, status, público, idade real, dados inválidos e capacidade zero.\n";

$pdo = createChartTestDatabase();
$service = new App\Services\AdminChartService($pdo);
$pdo->prepare('UPDATE temporadas SET data_inicio=? WHERE id=3')->execute([$today->format('Y-m-d')]);
$pdo->exec("UPDATE turmas SET idade_minima=6,idade_maxima=10,criterio_faixa_etaria='idade_exata' WHERE id=4");
$pdo->exec("INSERT INTO turmas(id,temporada_id,nome,local_treino_id,modalidade_id,status,vagas_totais,vagas_geral,vagas_pcd,vagas_plm,vagas_pvs,idade_minima,idade_maxima,criterio_faixa_etaria) VALUES(5,3,'Faixa compartilhada',2,2,'inscricoes_abertas',10,10,0,0,0,6,15,'idade_exata')");
foreach ([6,10,11] as $age) {
    $id = 300 + $age;
    $pdo->prepare('INSERT INTO pessoas(cpf,nome_completo,id,data_nascimento) VALUES(?,?,?,?)')->execute(['idade'.$age,'Pessoa de teste',$id,$today->modify('-'.$age.' years')->format('Y-m-d')]);
    $pdo->prepare("INSERT INTO inscricoes_turma(turma_id,pessoa_id,publico_alvo,status) VALUES(4,?,'geral','matriculada')")->execute([$id]);
}
$pdo->exec("INSERT INTO inscricoes_turma(turma_id,publico_alvo,status) VALUES(4,'geral','lista_espera')");
$ageData = $service->report(['modo'=>'single','temporadas'=>[3],'max_temporadas'=>6])['age_relationships'];
$bands = array_column($ageData['ages'], null, 'age');
relationshipCheck($bands['6–10 anos']['enrollments']===2 && $bands['11–15 anos']['enrollments']===1, 'Limites das faixas de cinco anos.');
relationshipCheck($bands['6–10 anos']['offered']===18 && $bands['6–10 anos']['available']===15, 'Contar cada turma uma vez na faixa, sem multiplicar pelas inscrições.');
relationshipCheck($bands['11–15 anos']['offered']===10, 'Capacidade apenas das turmas que atendem alguma idade da faixa.');
relationshipCheck($bands['Sem idade válida']['offered']===null && $bands['Sem idade válida']['available']===null, 'Sem idade não pode receber capacidade inventada.');
$pdo->prepare('UPDATE temporadas SET data_inicio=? WHERE id=3')->execute([$today->format('Y').'-01-01']);
$pdo->exec("UPDATE turmas SET criterio_faixa_etaria='ano_nascimento' WHERE id=4");
$birthYearData = $service->report(['modo'=>'single','temporadas'=>[3],'max_temporadas'=>6])['age_relationships'];
relationshipCheck(count(array_filter($birthYearData['ages'],fn($row)=>$row['age']==='6–10 anos' && $row['offered']===18))===1, 'Respeitar também o critério por ano de nascimento.');
echo "OK: faixas de cinco anos, limites, vagas compartilhadas sem duplicação por aluno, disponibilidade e idade desconhecida.\n";
