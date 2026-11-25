<?php
namespace App\Core {
    class Database { public static \PDO $pdo; public static function connection(): \PDO { return self::$pdo; } }
    class Auth { public static function check(): bool { return false; } }
}
namespace App\Services {
    // O processamento de suspensões é independente da abertura de novas inscrições.
    class EnrollmentSuspensionService { public function __construct(\PDO $pdo) {} public function processExpired(?int $id=null): void {} }
}
namespace {
require __DIR__.'/../app/Helpers/functions.php';
require __DIR__.'/../app/Services/EnrollmentOpeningService.php';
require __DIR__.'/../app/Services/CourseEnrollmentService.php';
use App\Services\EnrollmentOpeningService;
function openingCheck(bool $condition,string $message): void { if(!$condition)throw new RuntimeException($message); }
$zone=new DateTimeZone('America/Sao_Paulo');
$schedule=['cronograma_inscricoes_inicio'=>'2027-02-02 10:00:00','inscricoes_inicio'=>'2026-01-01 00:00:00'];
foreach(['2026-11-25 00:09:00','2027-02-02 09:59:59'] as $time){
    $block=EnrollmentOpeningService::blockReason($schedule,new DateTimeImmutable($time,$zone));
    openingCheck($block!==null&&str_contains($block,'02/02/2027 às 10:00'),'Bloquear antes da abertura e informar data e horário do cronograma da turma.');
}
foreach(['2027-02-02 10:00:00','2027-02-02 10:00:01'] as $time){
    openingCheck(EnrollmentOpeningService::blockReason($schedule,new DateTimeImmutable($time,$zone))===null,'Liberar ao atingir exatamente o instante de abertura.');
}
openingCheck(EnrollmentOpeningService::blockReason($schedule,new DateTimeImmutable('2027-02-02 12:59:59',new DateTimeZone('UTC')))!==null,'Considerar o fuso de São Paulo.');
openingCheck(EnrollmentOpeningService::blockReason($schedule,new DateTimeImmutable('2027-02-02 13:00:00',new DateTimeZone('UTC')))===null,'Reconhecer o mesmo instante em UTC.');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
App\Core\Database::$pdo=$db;
$db->sqliteCreateFunction('NOW',static fn()=>'2026-11-25 00:09:00');
$db->exec("CREATE TABLE temporadas(id INTEGER PRIMARY KEY,nome TEXT,data_inicio TEXT,data_fim TEXT);
CREATE TABLE modalidades(id INTEGER PRIMARY KEY,nome TEXT,tipo_ambiente TEXT);
CREATE TABLE locais_treino(id INTEGER PRIMARY KEY,nome_local TEXT,apelido_local TEXT);
CREATE TABLE espacos_treino(id INTEGER PRIMARY KEY,nome TEXT);
CREATE TABLE cronogramas_modalidade(id INTEGER PRIMARY KEY,data_inicio TEXT,data_fim TEXT,aulas_inicio TEXT,aulas_fim TEXT,inscricoes_inicio TEXT,inscricoes_fim TEXT,matriculas_inicio TEXT,matriculas_fim TEXT,permitir_inscricao_periodo_matricula INTEGER,inscricoes_abertas_inicio TEXT,inscricoes_abertas_fim TEXT,permitir_multiplas_inscricoes_modalidade INTEGER,limite_inscricoes_modalidade INTEGER,data_liberacao_multiplas_inscricoes_modalidade TEXT);
CREATE TABLE turmas(id INTEGER PRIMARY KEY,nome TEXT,temporada_id INTEGER,modalidade_id INTEGER,cronograma_modalidade_id INTEGER,local_treino_id INTEGER,espaco_treino_id INTEGER,status TEXT,ativo INTEGER,inscricoes_abertas INTEGER,exigir_token_inscricao INTEGER,programa TEXT,dias_semana TEXT,hora_inicio TEXT,hora_fim TEXT,idade_minima INTEGER,idade_maxima INTEGER,vagas_geral INTEGER,vagas_pcd INTEGER,vagas_plm INTEGER,vagas_pvs INTEGER,vagas_espera_geral INTEGER,vagas_espera_pcd INTEGER,vagas_espera_plm INTEGER,vagas_espera_pvs INTEGER);
CREATE TABLE pessoas(id INTEGER PRIMARY KEY,cpf TEXT,nome_completo TEXT,cadastro_completo INTEGER);
CREATE TABLE contas(id INTEGER PRIMARY KEY,cpf TEXT);
CREATE TABLE vinculos_responsaveis(dependente_pessoa_id INTEGER,responsavel_pessoa_id INTEGER,data_fim TEXT);
CREATE TABLE tokens_inscricao_turma(id INTEGER PRIMARY KEY,numero_token TEXT,cpf TEXT,publico_alvo TEXT,validade TEXT,turma_id INTEGER,status TEXT,ativo INTEGER);
CREATE TABLE rematricula_convites(id INTEGER PRIMARY KEY,token_id INTEGER);
CREATE TABLE inscricoes_turma(id INTEGER PRIMARY KEY,turma_id INTEGER,pessoa_id INTEGER,publico_alvo TEXT,status TEXT,suspensao_libera_vaga INTEGER,suspensa_fim TEXT);
INSERT INTO temporadas VALUES(1,'Temporada', '2099-01-01','2099-12-31');
INSERT INTO modalidades VALUES(1,'Judô','terrestre');INSERT INTO locais_treino VALUES(1,'Local','Local');INSERT INTO espacos_treino VALUES(1,'Espaço');
INSERT INTO cronogramas_modalidade VALUES(1,'2099-01-01','2099-12-31','2099-03-01','2099-12-04','2099-02-02 10:00:00','2099-02-14 23:59:00','2099-02-15 06:00:00','2099-02-28 23:59:00',0,'2099-02-28 10:00:00','2099-09-30 23:59:00',0,1,NULL);
INSERT INTO turmas VALUES(524,'Turma 524',1,1,1,1,1,'planejada',1,0,1,'Programa','5','17:30','18:30',4,7,20,3,3,3,4,1,1,1);
INSERT INTO pessoas VALUES(1,'52998224725','Pessoa fictícia',1);INSERT INTO contas VALUES(10,'52998224725');
INSERT INTO tokens_inscricao_turma VALUES(1,'1234','52998224725','geral','2099-02-16 10:00:00',524,'ativo',1),(2,'5678','52998224725','geral','2099-02-16 10:00:00',524,'ativo',1);
INSERT INTO rematricula_convites VALUES(1,2);");
$tokensBefore=$db->query('SELECT * FROM tokens_inscricao_turma')->fetchAll();
$db->exec('PRAGMA query_only=ON');
$service=new App\Services\CourseEnrollmentService();
foreach(['1234','5678',''] as $token){
    try {
        $service->enroll(['turma_id'=>524,'pessoa_id'=>1,'cpf'=>'52998224725','token'=>$token,'aceite_termos'=>1,'aceite_edital'=>1]);
        throw new LogicException('Inscrição antecipada foi aceita.');
    } catch(RuntimeException $error){
        if($error instanceof LogicException)throw $error;
        openingCheck(str_contains($error->getMessage(),'02/02/2099 às 10:00'),'O servidor deve rejeitar a inscrição antes da abertura, com token comum, de rematrícula ou sem token.');
    }
}
openingCheck($tokensBefore===$db->query('SELECT * FROM tokens_inscricao_turma')->fetchAll(),'O bloqueio não pode consumir nem cancelar os tokens antecipados.');
openingCheck((int)$db->query('SELECT COUNT(*) FROM inscricoes_turma')->fetchColumn()===0,'O bloqueio não pode gerar inscrição nem lista de espera.');
$details=$service->getClassEnrollmentDetails(524);
openingCheck(!empty($details['class']['inscricao_bloqueio_prazo']),'A tela deve receber o mesmo bloqueio do servidor.');
$db->exec('PRAGMA query_only=OFF');
$pending=$service->pendingEnrollmentTokensForAccount(10);
openingCheck(count($pending)===2,'Manter visíveis os tokens gerados antes da abertura.');
openingCheck($pending[0]['inscricao_bloqueio_prazo']!==null&&$pending[1]['inscricao_bloqueio_prazo']!==null,'Informar a abertura nos alertas de tokens comuns e de rematrícula.');
echo "Abertura de inscrições: horário exato, fuso, cronograma da turma, bloqueio real no servidor com/sem token, nenhuma inscrição/fila antecipada e preservação dos tokens verificados.\n";
}
