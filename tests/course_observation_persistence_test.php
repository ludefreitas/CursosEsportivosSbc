<?php
namespace App\Core { class Database { public static \PDO $pdo; public static function connection(): \PDO { return self::$pdo; } } }
namespace App\Services { class AuditLogService { public static function record(...$args): void {} } class ClassCopyService { public function recordCopy(...$args): void {} } }
namespace {
require __DIR__.'/../app/Services/CourseEnrollmentService.php';
function normalize_age_rule_mode(string $value): string { return $value ?: 'idade_exata'; }
function normalize_modality_levels($value): array { return []; }
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);\App\Core\Database::$pdo=$db;
$source=file_get_contents(__DIR__.'/../app/Services/CourseEnrollmentService.php');
preg_match('/INSERT INTO turmas \(([^)]+)\) VALUES/',$source,$match);
$columns=array_map('trim',explode(',',$match[1]));
$db->exec('CREATE TABLE turmas (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT DEFAULT "planejada", inscricoes_abertas INTEGER DEFAULT 0, '.implode(', ',array_map(fn($name)=>$name.' TEXT',$columns)).')');
$db->exec('CREATE TABLE cronogramas_modalidade (id INTEGER PRIMARY KEY, temporada_id INTEGER, modalidade_id INTEGER, observacao TEXT, matriculas_inicio TEXT, matriculas_fim TEXT, inscricoes_inicio TEXT, inscricoes_fim TEXT, permitir_inscricao_periodo_matricula INTEGER, inscricoes_abertas_inicio TEXT, inscricoes_abertas_fim TEXT)');
$db->exec("INSERT INTO cronogramas_modalidade (id,temporada_id,modalidade_id,observacao) VALUES (1,1,1,'Texto do cronograma')");
$db->exec('CREATE TABLE espacos_treino (id INTEGER PRIMARY KEY, local_treino_id INTEGER, ativo INTEGER)');
$db->exec('INSERT INTO espacos_treino VALUES (1,1,1)');
$db->exec('CREATE TABLE turmas_professores (turma_id INTEGER, professor_conta_id INTEGER)');
// SQLite equivale a INSERT OR IGNORE; a turma deste teste não atribui equipe.
$db->exec('CREATE TABLE conta_papeis (conta_id INTEGER, papel_id INTEGER)');
$db->exec('CREATE TABLE papeis (id INTEGER, slug TEXT)');
$db->exec('CREATE TABLE contas (id INTEGER, ativo INTEGER)');
$service=new \App\Services\CourseEnrollmentService();
$base=['temporada_id'=>1,'modalidade_id'=>1,'local_treino_id'=>1,'espaco_treino_id'=>1,'cronograma_modalidade_id'=>1,'nome'=>'Turma de teste'];
foreach (['Texto editado com acentuação: observação.','', '0', null] as $note) {
    $data=$base;if($note!==null)$data['observacao']=$note;
    $result=$service->createClass(8,$data);
    $stmt=$db->prepare('SELECT observacao FROM turmas WHERE id=?');$stmt->execute([$result['id']]);$saved=$stmt->fetchColumn();
    $expected=$note===null?'Texto do cronograma':($note===''?null:$note);
    if($saved!==$expected)throw new RuntimeException('Observação não persistida corretamente: '.var_export([$saved,$expected],true));
}
echo "Observação da turma: gravação real em banco de teste, texto manual, vazio, zero e cópia do cronograma OK.\n";
}
