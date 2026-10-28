<?php
namespace App\Core {
    class Database {
        public static \PDO $pdo;
        public static function connection(): \PDO { return self::$pdo; }
    }
}
namespace {
require __DIR__.'/../app/Services/CourseInterestService.php';
require __DIR__.'/../app/Services/ModalityScheduleClassService.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
\App\Core\Database::$pdo=$db;
$db->exec('CREATE TABLE cronogramas_modalidade(id INTEGER,nome TEXT); CREATE TABLE locais_treino(id INTEGER,nome_local TEXT,apelido_local TEXT); CREATE TABLE turmas(id INTEGER,nome TEXT,dias_semana TEXT,hora_inicio TEXT,hora_fim TEXT,local_treino_id INTEGER,cronograma_modalidade_id INTEGER)');
$db->exec("INSERT INTO cronogramas_modalidade VALUES(1,'Natação'),(2,'Ginástica'),(3,'Vazio'); INSERT INTO locais_treino VALUES(1,'Centro Esportivo','Orquídeas'); INSERT INTO turmas VALUES(10,'Turma <teste>','2,4','07:30:00','08:40:00',1,1),(11,'Outra turma','1,3','10:00:00','11:00:00',1,2),(12,'Sem horário',NULL,NULL,NULL,NULL,1)");
$service=new \App\Services\ModalityScheduleClassService();
$result=$service->list(1);
if(count($result['turmas'])!==2 || in_array(11,array_column($result['turmas'],'id')))throw new RuntimeException('Misturou turmas de cronogramas diferentes.');
$rows=array_column($result['turmas'],null,'id');
if($rows[10]['dias_horarios']!=='Terça-feira, Quinta-feira · 07:30 às 08:40' || $rows[10]['local']!=='Orquídeas')throw new RuntimeException('Dados de dias, horários e local incorretos.');
if($rows[12]['dias_horarios']!=='Dias não informados · Horário não informado')throw new RuntimeException('Tratar dados não cadastrados.');
if($service->list(3)['turmas']!==[])throw new RuntimeException('Cronograma vazio.');
foreach([0,999]as$id){try{$service->list($id);throw new LogicException('Cronograma inválido aceito.');}catch(RuntimeException$e){if($e instanceof LogicException)throw$e;}}
echo "Turmas por cronograma: vínculo, dias, horários, local e casos vazios OK.\n";
}
