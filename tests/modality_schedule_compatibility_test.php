<?php
namespace App\Core { class Database { public static \PDO $pdo; public static function connection(): \PDO { return self::$pdo; } } }
namespace App\Services {
    class AuditLogService { public static array $events=[]; public static function record(...$args): void {self::$events[]=$args;} }
    class ModalityCreatorService { public static function assertScheduleOwner(int $id,int $account): void {if($account!==8)throw new \RuntimeException('Sem permissão.');} }
}
namespace {
require __DIR__.'/../app/Services/ModalityScheduleCompatibilityService.php';
require __DIR__.'/../app/Services/CourseEnrollmentService.php';
class CompatibilityPdo extends PDO {
    public array $queries=[];
    public function __construct(){parent::__construct('sqlite::memory:');$this->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}
    public function prepare(string $query,array $options=[]): PDOStatement|false {$this->queries[]=$query;return parent::prepare(preg_replace('/ LIMIT 1\b/','',$query),$options);}
}
function expectCompatible(bool $value,string $message): void {if(!$value)throw new RuntimeException($message);}
$db=\App\Core\Database::$pdo=new CompatibilityPdo();
$source=file_get_contents(__DIR__.'/../app/Services/CourseEnrollmentService.php');
preg_match('/INSERT INTO cronogramas_modalidade \(([^)]+)\) VALUES/',$source,$m);
$columns=array_map('trim',explode(',',$m[1]));
$db->exec('CREATE TABLE cronogramas_modalidade (id INTEGER PRIMARY KEY,'.implode(',',array_map(fn($c)=>$c.' TEXT',$columns)).')');
$db->exec('CREATE TABLE turmas(id INTEGER PRIMARY KEY,nome TEXT,dias_semana TEXT,cronograma_modalidade_id INTEGER,ativo INTEGER);CREATE TABLE turmas_chamadas(id INTEGER PRIMARY KEY,turma_id INTEGER,data_aula TEXT)');
$base=['cronograma_modalidade_id'=>1,'temporada_id'=>1,'modalidade_id'=>2,'nome'=>'Cronograma de teste','data_inicio'=>'2027-01-01','data_fim'=>'2027-12-31','abrangencia_semanal'=>'segunda_domingo','matriculas_inicio'=>'2027-01-04 08:00:00','matriculas_fim'=>'2027-01-10 23:59:00','inscricoes_inicio'=>'2027-01-01 08:00:00','inscricoes_fim'=>'2027-01-03 23:59:00','inscricoes_abertas_inicio'=>'2027-01-11 08:00:00','inscricoes_abertas_fim'=>'2027-11-30 23:59:00','aulas_inicio'=>'2027-02-01','aulas_fim'=>'2027-12-31'];
$row=$base;unset($row['cronograma_modalidade_id']);$row['id']=1;$row['permitir_multiplas_inscricoes_modalidade']=0;$row['limite_inscricoes_modalidade']=1;
$db->prepare('INSERT INTO cronogramas_modalidade ('.implode(',',array_keys($row)).') VALUES('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
$db->exec("INSERT INTO turmas VALUES(10,'Terça e domingo','2,7',1,1),(11,'Sábado inativo','6',1,0);INSERT INTO turmas_chamadas VALUES(1,10,'2027-03-07'),(2,10,'2027-10-03')");
$service=new \App\Services\CourseEnrollmentService();
$block=function(array $change,array $texts,bool $professor=false)use($service,$base,$db):void{
 $before=$db->query('SELECT * FROM cronogramas_modalidade')->fetchAll(PDO::FETCH_ASSOC);$db->queries=[];$events=count(\App\Services\AuditLogService::$events);
 try{$service->saveModalitySchedule(8,array_replace($base,$change),$professor);throw new LogicException('Alteração incompatível aceita.');}
 catch(RuntimeException $error){foreach($texts as $text)expectCompatible(str_contains($error->getMessage(),$text),'Motivo ausente: '.$text.' / '.$error->getMessage());}
 expectCompatible($before===$db->query('SELECT * FROM cronogramas_modalidade')->fetchAll(PDO::FETCH_ASSOC),'Cronograma alterado após bloqueio.');
 expectCompatible(count(\App\Services\AuditLogService::$events)===$events,'Auditoria gravada apesar do bloqueio.');
 foreach($db->queries as $query)expectCompatible(!preg_match('/^\s*(UPDATE|INSERT|DELETE|CREATE|ALTER|SHOW)\b/i',$query),'Gravação/DDL antes da validação.');
};
foreach([false,true] as $professor)$block(['abrangencia_semanal'=>'segunda_sexta'],['[10] Terça e domingo','domingos','[11] Sábado inativo','sábados'],$professor);
$block(['aulas_inicio'=>'2027-03-08'],['chamada registrada em 07/03/2027']);
$block(['aulas_fim'=>'2027-10-02'],['chamada registrada em 03/10/2027']);
$block(['aulas_inicio'=>'2027-03-01','aulas_fim'=>'2027-03-01'],['[10]','nenhum dos seus dias de aula','[11]']);
$block(['temporada_id'=>3],['temporada e a modalidade não podem ser alteradas']);
$block(['modalidade_id'=>3],['temporada e a modalidade não podem ser alteradas']);
$guard=new \App\Services\ModalityScheduleCompatibilityService();
try{$guard->assertUpdate($db,1,$base,array_replace($base,['matriculas_inicio'=>'2027-01-11','matriculas_fim'=>'2027-01-11']));throw new LogicException('Matrícula sem dia aceita.');}catch(RuntimeException $e){expectCompatible(str_contains($e->getMessage(),'novo período de matrícula'),'Motivo de matrícula.');}
// Alterações textuais, ampliação das datas e expansão da abrangência são permitidas.
$service->saveModalitySchedule(8,$base+['observacao'=>'Nova observação']);
expectCompatible($db->query('SELECT observacao FROM cronogramas_modalidade WHERE id=1')->fetchColumn()==='Nova observação','Edição compatível não salva.');
$service->saveModalitySchedule(8,array_replace($base,['aulas_inicio'=>'2027-01-01','aulas_fim'=>'2028-01-01']),true);
expectCompatible(count(\App\Services\AuditLogService::$events)===2,'Auditar edições permitidas.');
// Legado inconsistente não impede editar apenas a observação; formato de hora não é mudança de dia.
$legacy=$base;$legacy['abrangencia_semanal']='segunda_sexta';
$guard->assertUpdate($db,1,$legacy,array_replace($legacy,['nome'=>'Outro nome','matriculas_inicio'=>'2027-01-04T08:00']));
$guard->assertUpdate($db,1,$legacy,array_replace($legacy,['abrangencia_semanal'=>'segunda_domingo']));
echo "OK: domingo misto, sábado inativo, matrícula, período sem aula, chamadas fora do período, vínculos, admin/professor, bloqueio sem gravação e edições compatíveis.\n";
}
