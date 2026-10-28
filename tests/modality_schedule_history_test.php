<?php
namespace App\Core {
    class Database {
        public static \PDO $pdo;
        public static function connection(): \PDO { return self::$pdo; }
    }
}
namespace {
require __DIR__.'/../app/Services/ModalityScheduleHistoryService.php';
use App\Services\ModalityScheduleHistoryService;
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
\App\Core\Database::$pdo = $db;
$db->exec('CREATE TABLE contas(id INTEGER,cpf TEXT); CREATE TABLE pessoas(cpf TEXT,nome_completo TEXT); CREATE TABLE cronogramas_modalidade(id INTEGER,nome TEXT); CREATE TABLE logs_auditoria(id INTEGER,entidade_id INTEGER,conta_id INTEGER,tipo_entidade TEXT,tipo_evento TEXT,created_at TEXT,payload_json TEXT)');
$db->exec("INSERT INTO contas VALUES(1,'1'),(2,'2'),(3,'3'); INSERT INTO pessoas VALUES('1','Ana'),('2','João'),('3','Clara'); INSERT INTO cronogramas_modalidade VALUES(10,'Cronograma de natação')");
$insert = $db->prepare('INSERT INTO logs_auditoria VALUES(?,?,?,?,?,?,?)');
$insert->execute([1,10,1,'cronogramas_modalidade','cronograma_modalidade.criado','2026-10-01 10:00:00','{}']);
$insert->execute([2,10,3,'cronogramas_modalidade','cronograma_modalidade.atualizado','2026-10-02 10:00:00','{}']);
$insert->execute([3,10,1,'cronogramas_modalidade','cronograma_modalidade.atualizado','2026-10-03 10:00:00','{}']);
$payload = json_encode(['antes'=>['nome'=>'Anterior','possui_edital'=>0,'aulas_inicio'=>'2026-10-01'], 'depois'=>['nome'=>'Novo <texto>','possui_edital'=>1,'aulas_inicio'=>'2026-11-01','segredo'=>'não exibir']],JSON_UNESCAPED_UNICODE);
$insert->execute([4,10,2,'cronogramas_modalidade','cronograma_modalidade.atualizado','2026-10-04 10:00:00',$payload]);
$insert->execute([5,10,2,'cronogramas_modalidade','cronograma_modalidade.atualizado','2026-10-05 10:00:00',$payload]);
$insert->execute([6,10,3,'outra_entidade','cronograma_modalidade.atualizado','2026-10-06 10:00:00','{}']);
function expectHistory(bool $value,string $message): void { if (!$value) throw new RuntimeException($message); }
$rows = ModalityScheduleHistoryService::attach([['id'=>10]]);
expectHistory(array_column($rows[0]['historico']['alteracoes'],'nome')===['João','Ana'],'Dois últimos usuários distintos, em ordem recente.');
expectHistory($rows[0]['historico']['criacao']['nome']==='Ana','Preservar criador.');
$detail = ModalityScheduleHistoryService::details(4);
expectHistory($detail['detalhes_disponiveis'] && count($detail['alteracoes'])===3,'Comparar campos permitidos.');
$changes=array_column($detail['alteracoes'],null,'campo');
expectHistory($changes['Possui edital']['antes']==='Não' && $changes['Possui edital']['depois']==='Sim','Booleanos em português.');
expectHistory($changes['Início das aulas']['depois']==='01/11/2026','Datas legíveis.');
expectHistory(!str_contains(json_encode($detail),'não exibir'),'Não expor campos arbitrários do payload.');
expectHistory(!ModalityScheduleHistoryService::details(2)['detalhes_disponiveis'],'Histórico antigo sem detalhes não pode inventar mudanças.');
foreach ([6,999] as $id) {
    try { ModalityScheduleHistoryService::details($id); throw new LogicException('Log inválido aceito.'); }
    catch (RuntimeException $e) { if ($e instanceof LogicException) throw $e; }
}
echo "Histórico de cronogramas: autoria, dois usuários distintos, diferenças, registros antigos e isolamento de entidade OK.\n";
}
