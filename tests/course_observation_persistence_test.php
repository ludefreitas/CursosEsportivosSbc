<?php
namespace App\Core { class Database { public static \PDO $pdo; public static function connection(): \PDO { return self::$pdo; } } }
namespace App\Services { class AuditLogService { public static function record(...$args): void {} } class ClassCopyService { public function recordCopy(...$args): void {} } }
namespace {
require __DIR__.'/../app/Services/CourseEnrollmentService.php';
require __DIR__.'/../app/Services/HealthCertificateRules.php';
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
$base=['temporada_id'=>1,'modalidade_id'=>1,'local_treino_id'=>1,'espaco_treino_id'=>1,'cronograma_modalidade_id'=>1,'nome'=>'Turma de teste','criterio_faixa_etaria'=>'ano_nascimento','vagas_totais'=>16,'vagas_geral'=>16,'vagas_espera_geral'=>3];
foreach (['Texto editado com acentuação: observação.','', '0', null] as $note) {
    $data=$base;if($note!==null)$data['observacao']=$note;
    $result=$service->createClass(8,$data);
    $stmt=$db->prepare('SELECT observacao FROM turmas WHERE id=?');$stmt->execute([$result['id']]);$saved=$stmt->fetchColumn();
    $expected=$note===null?'Texto do cronograma':($note===''?null:$note);
    if($saved!==$expected)throw new RuntimeException('Observação não persistida corretamente: '.var_export([$saved,$expected],true));
}
$stmt=$db->prepare('SELECT vagas_totais, vagas_geral, vagas_pcd, vagas_plm, vagas_pvs, vagas_espera_geral FROM turmas WHERE id=?');
$stmt->execute([$result['id']]);
$slots=array_map('intval',$stmt->fetch(PDO::FETCH_NUM));
if($slots!==[16,11,2,2,1,3])throw new RuntimeException('A distribuição fixa deve preservar o total e a espera informada.');
$classId = $result['id'];
$db->exec('UPDATE turmas SET vagas_totais=75, vagas_geral=60, vagas_pcd=5, vagas_plm=5, vagas_pvs=5 WHERE id=' . $classId);
$edit = ['id' => $classId, 'nome' => 'Turma editada'] + $base;
unset($edit['vagas_totais']);
$service->createClass(8, $edit);
$stmt->execute([$classId]);
if (array_map('intval', $stmt->fetch(PDO::FETCH_NUM)) !== [75, 60, 5, 5, 5, 3]) {
    throw new RuntimeException('Editar outros dados deve preservar a distribuição existente de vagas.');
}
$edit['vagas_totais'] = 26;
$service->createClass(8, $edit);
$stmt->execute([$classId]);
if (array_map('intval', $stmt->fetch(PDO::FETCH_NUM)) !== [26, 18, 3, 3, 2, 3]) {
    throw new RuntimeException('Alterar o total deve aplicar a distribuição fixa e preservar a espera.');
}
$reserved = [
    'sem_vagas_gerais' => '1',
    'vagas_pcd' => '13',
    'vagas_plm' => '2',
    'vagas_pvs' => '0',
    'vagas_espera_pvs' => '4',
] + $base;
unset($reserved['vagas_totais']);
$reservedId = $service->createClass(8, $reserved)['id'];
$reservedStmt = $db->prepare('SELECT vagas_totais, vagas_geral, vagas_pcd, vagas_plm, vagas_pvs, vagas_espera_geral,
    vagas_espera_pcd, vagas_espera_plm, vagas_espera_pvs FROM turmas WHERE id=?');
$reservedStmt->execute([$reservedId]);
if (array_map('intval', $reservedStmt->fetch(PDO::FETCH_NUM)) !== [15, 0, 13, 2, 0, 0, 3, 0, 4]) {
    throw new RuntimeException('A criação deve salvar as cotas reservadas e sugerir a espera sem mínimo de uma vaga.');
}
$reserved['id'] = $classId;
$reserved['vagas_pcd'] = '8';
$reserved['vagas_espera_pcd'] = '0';
$service->createClass(8, $reserved);
$reservedStmt->execute([$classId]);
if (array_map('intval', $reservedStmt->fetch(PDO::FETCH_NUM)) !== [10, 0, 8, 2, 0, 0, 0, 0, 4]) {
    throw new RuntimeException('A edição deve zerar as vagas gerais e respeitar a espera informada manualmente.');
}
echo "Observação e vagas da turma: criação e edição verificadas em banco de teste.\n";
}
