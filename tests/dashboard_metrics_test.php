<?php
namespace App\Core { class Database { public static \PDO $pdo; public static function connection(): \PDO { return self::$pdo; } } }
namespace {
require __DIR__.'/../app/Services/UserService.php';
require __DIR__.'/../app/Services/ProfileService.php';
function checkMetric(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$db = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
App\Core\Database::$pdo = $db;
$db->exec('CREATE TABLE pessoas(id INTEGER PRIMARY KEY,cpf TEXT,nome_completo TEXT,eh_pcd INTEGER,eh_plm INTEGER,eh_pvs INTEGER);
    CREATE TABLE contas(id INTEGER PRIMARY KEY,cpf TEXT);
    CREATE TABLE vinculos_responsaveis(id INTEGER PRIMARY KEY,responsavel_pessoa_id INTEGER,dependente_pessoa_id INTEGER,data_fim TEXT);
    CREATE TABLE agendamentos(id INTEGER PRIMARY KEY,pessoa_id INTEGER,status TEXT,data_agendada TEXT);
    CREATE TABLE tipos_certificados(id INTEGER PRIMARY KEY,slug TEXT);
    CREATE TABLE certificados_pessoa(id INTEGER PRIMARY KEY,pessoa_id INTEGER,tipo_certificado_id INTEGER,status TEXT,updated_at TEXT,created_at TEXT);
    CREATE TABLE postagens_blog(id INTEGER PRIMARY KEY,criado_por_conta_id INTEGER,ativo INTEGER,status TEXT,data_publicacao TEXT,publicado_em TEXT,created_at TEXT);
    CREATE TABLE atestados_saude(id INTEGER,pessoa_id INTEGER,tipo_atestado TEXT,updated_at TEXT,created_at TEXT);
    CREATE TABLE atestados_saude_importados(cpf TEXT,status_importacao TEXT,tipo_atestado TEXT,validade_certificado TEXT);
    INSERT INTO pessoas VALUES(1,\'111\',\'Titular\',1,0,1),(2,\'222\',\'Dependente\',1,1,1),(3,\'333\',\'Vínculo encerrado\',1,0,0),(4,\'444\',\'Outra conta\',1,0,0),(5,\'555\',\'Dependente sem condição\',0,0,0),(6,\'666\',\'Outro titular\',0,0,0),(7,\'777\',\'Outro dependente\',1,0,0);
    INSERT INTO contas VALUES(10,\'111\'),(60,\'666\'),(99,\'sem-pessoa\');
    INSERT INTO vinculos_responsaveis VALUES(1,1,1,NULL),(2,1,2,NULL),(3,1,2,\'2020-01-01\'),(4,1,3,\'2020-01-01\'),(5,1,5,NULL),(6,6,7,NULL);
    INSERT INTO tipos_certificados VALUES(1,\'pcd\'),(2,\'plm\'),(3,\'pvs\');
    INSERT INTO certificados_pessoa VALUES
        (1,1,1,\'pendente\',\'2020-01-01\',\'2020-01-01\'),(2,1,1,\'validado\',\'2026-01-01\',\'2026-01-01\'),
        (3,1,2,\'pendente\',\'2026-01-01\',\'2026-01-01\'),(4,1,3,\'pendente\',\'2026-01-01\',\'2026-01-01\'),
        (5,2,1,\'pendente\',\'2020-01-01\',\'2020-01-01\'),(6,2,1,\'pendente\',\'2026-01-01\',\'2026-01-01\'),
        (7,2,2,\'pendente\',\'2026-01-01\',\'2026-01-01\'),(8,2,3,\'validado_parcial\',\'2026-01-01\',\'2026-01-01\'),
        (9,3,1,\'pendente\',\'2026-01-01\',\'2026-01-01\'),(10,4,1,\'pendente\',\'2026-01-01\',\'2026-01-01\'),
        (11,5,1,\'pendente\',\'2026-01-01\',\'2026-01-01\'),(12,7,1,\'pendente\',\'2026-01-01\',\'2026-01-01\');');
$now = new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo'));
$future = $now->modify('+1 hour')->format('Y-m-d H:i:s');
$past = $now->modify('-1 hour')->format('Y-m-d H:i:s');
$booking = $db->prepare('INSERT INTO agendamentos(pessoa_id,status,data_agendada) VALUES(?,?,?)');
foreach ([[1,'agendado',$future],[1,'agendado',$past],[2,'agendado',$future],[2,'agendado',$future],[5,'agendado',$future],[3,'agendado',$future],[4,'agendado',$future],[1,'cancelado',$future],[2,'presente',$future],[6,'agendado',$future],[7,'agendado',$future]] as $row) $booking->execute($row);
$post = $db->prepare('INSERT INTO postagens_blog(criado_por_conta_id,ativo,status,data_publicacao,publicado_em,created_at) VALUES(?,?,?,?,?,?)');
foreach ([[10,1,'publicado',$past,null,$past],[10,1,'publicado',$future,$past,$past],[10,1,'rascunho',$past,null,$past],[10,0,'publicado',$past,null,$past],[60,1,'publicado',null,$past,$past],[10,1,'publicado',null,null,$past],[10,1,'publicado',null,null,null],[99,1,'publicado',$past,null,$past]] as $row) $post->execute($row);
$db->exec('PRAGMA query_only=ON');
$service = new App\Services\UserService();
checkMetric($service->dashboardMetrics(1)===['dependentes'=>2,'agendamentos_futuros'=>4,'documentos_pendentes'=>3,'postagens_blog'=>3], 'Restringir pendências e agendamentos à conta, com vínculo ativo e sem duplicar histórico.');
checkMetric($service->dashboardMetrics(6)===['dependentes'=>1,'agendamentos_futuros'=>2,'documentos_pendentes'=>1,'postagens_blog'=>3], 'Cada titular recebe somente os seus totais; publicações são conteúdo público.');
checkMetric($service->dashboardMetrics(999)===['dependentes'=>0,'agendamentos_futuros'=>0,'documentos_pendentes'=>0,'postagens_blog'=>3], 'Não expor dados de outras contas quando não há registros próprios.');
class MetricsProfile extends App\Services\ProfileService {
    public function __construct() {}
    public function getAuthenticatedPerson(): ?array { return ['id'=>1,'cpf'=>'111']; }
}
$dependentIds = array_column((new MetricsProfile())->listDependents(),'id');
sort($dependentIds);
checkMetric($dependentIds===[1,2,5], 'A lista usada pelo painel deve acompanhar os vínculos ativos dos totais.');
echo "Painel: documentos da conta e dependentes ativos, certificado atual, condições declaradas, agendamentos do titular, horários passados, publicações disponíveis e isolamento entre contas verificados somente com leitura.\n";
}
