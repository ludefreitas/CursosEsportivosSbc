<?php
namespace App\Core {
 class Database {public static \PDO $pdo;public static function connection():\PDO{return self::$pdo;}}
 class Auth {public static function id():int{return 40;}}
}
namespace {
require __DIR__.'/../app/Helpers/functions.php';require __DIR__.'/fixtures/enrollment_removal_database.php';
require __DIR__.'/../app/Services/AuditLogService.php';require __DIR__.'/../app/Services/EnrollmentRemovalService.php';require __DIR__.'/../app/Services/EnrollmentMovementService.php';require __DIR__.'/../app/Services/CourseEnrollmentService.php';
use App\Services\EnrollmentRemovalService;
function removalCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function removalFails(callable $action,string $message):void{try{$action();throw new LogicException('Remoção indevida aceita.');}catch(RuntimeException $e){if($e instanceof LogicException)throw$e;removalCheck(str_contains($e->getMessage(),$message),$e->getMessage());}}
$db=removalDatabase();App\Core\Database::$pdo=$db;$service=new EnrollmentRemovalService($db);
foreach([10,11,20,30] as $actor)removalFails(fn()=>$service->remove(5,$actor,'Teste.','REMOVER'),'Admin Master');
$db->exec('DELETE FROM conta_papeis WHERE conta_id=40 AND papel_id=3');removalFails(fn()=>$service->remove(5,40,'Teste.','REMOVER'),'Admin Master');$db->exec('INSERT INTO conta_papeis VALUES(40,3)');
$db->exec('UPDATE contas SET ativo=0 WHERE id=40');removalFails(fn()=>$service->remove(5,40,'Teste.','REMOVER'),'Admin Master');$db->exec('UPDATE contas SET ativo=1 WHERE id=40');
removalFails(fn()=>$service->remove(5,40,'','REMOVER'),'motivo');removalFails(fn()=>$service->remove(5,40,'Teste.','EXCLUIR'),'REMOVER');
$filters=['demand_from'=>'2026-01-01','demand_to'=>'2026-12-31','season_ids'=>[1],'location'=>0,'modality'=>0,'public'=>'','status'=>'','period'=>''];
$movement=new App\Services\EnrollmentMovementService($db);$before=$movement->report($filters);
removalCheck($before['totals']['copias']===1,'A base deve ter movimentação visível antes da remoção.');
$people=$db->query('SELECT * FROM pessoas ORDER BY id')->fetchAll();
$db->exec("INSERT INTO tipos_certificados VALUES(1,'pcd');INSERT INTO certificados_pessoa VALUES(1,105,1,'validado','2099-12-31');INSERT INTO documentos_certificados VALUES(1,1)");
$service->remove(5,40,'Inscrição indevida de teste.','REMOVER');
removalCheck((int)$db->query('SELECT COUNT(*) FROM inscricoes_turma WHERE id=5')->fetchColumn()===0,'A inscrição não pode permanecer em nenhuma consulta do usuário ou estatística.');
removalCheck((int)$db->query('SELECT COUNT(*) FROM turmas_chamadas WHERE inscricao_turma_id=5')->fetchColumn()===0,'Remover também chamadas para excluir a frequência dos indicadores.');
removalCheck((int)$db->query('SELECT COUNT(*) FROM declaracoes_inscricao WHERE inscricao_turma_id=5')->fetchColumn()===0,'Invalidar a consulta de declarações da inscrição removida.');
removalCheck($people===$db->query('SELECT * FROM pessoas ORDER BY id')->fetchAll()&&(int)$db->query('SELECT COUNT(*) FROM documentos_certificados')->fetchColumn()===1,'Preservar pessoas e documentos.');
removalCheck((int)$db->query('SELECT COUNT(*) FROM inscricoes_turma WHERE id=8')->fetchColumn()===1,'Preservar outra inscrição da mesma pessoa em outra turma.');
removalCheck($db->query('SELECT posicao_lista_espera FROM inscricoes_turma WHERE id=6')->fetchColumn()===1&&$db->query('SELECT posicao_lista_espera FROM inscricoes_turma WHERE id=7')->fetchColumn()===2,'Reorganizar a fila da cota removida.');
removalCheck((int)$db->query('SELECT posicao_lista_espera FROM inscricoes_turma WHERE id=9')->fetchColumn()===7,'Preservar a fila de outras cotas.');
removalCheck($db->query('SELECT status FROM tokens_inscricao_turma WHERE id=1')->fetchColumn()==='usado'&&$db->query('SELECT status FROM tokens_inscricao_turma WHERE id=2')->fetchColumn()==='cancelado','Manter token usado inutilizável e cancelar autorização antiga ativa.');
removalCheck($db->query('SELECT status FROM tokens_inscricao_turma WHERE id=3')->fetchColumn()==='ativo','Preservar tokens de outra turma.');
removalCheck((bool)$db->query('SELECT arquivada_em FROM notificacoes_destinatarios WHERE id=1')->fetchColumn()&&(bool)$db->query('SELECT arquivada_em FROM notificacoes_destinatarios WHERE id=3')->fetchColumn(),'Retirar do usuário as notificações da inscrição e do convite antigo.');
removalCheck(!$db->query('SELECT arquivada_em FROM notificacoes_destinatarios WHERE id=2')->fetchColumn(),'Preservar notificação da outra inscrição.');
$audit=$db->query("SELECT * FROM logs_auditoria WHERE tipo_evento='inscricao_turma.removida_master'")->fetch();$payload=json_decode($audit['payload_json'],true);
removalCheck((int)$audit['conta_id']===40&&(int)$audit['entidade_id']===5&&$payload['motivo']==='Inscrição indevida de teste.'&&$payload['chamadas_removidas']===1,'Registrar autor, inscrição, motivo e vínculos removidos na auditoria.');
removalCheck((int)$db->query('SELECT COUNT(*) FROM movimentacoes_inscricoes')->fetchColumn()===1,'Preservar o retrato administrativo da movimentação.');
$after=$movement->report($filters);removalCheck($after['totals']['copias']===0&&$after['totals']['excluidas']===0,'Nem mesmo indicadores de movimentações excluídas podem contar a remoção do Master.');
removalCheck($db->query('SELECT status_novo FROM rematricula_convites_historico WHERE convite_id=1 ORDER BY id DESC LIMIT 1')->fetchColumn()==='inscricao_removida','Registrar evidência para o reenvio explícito de rematrícula.');
removalFails(fn()=>$service->remove(5,40,'Repetição.','REMOVER'),'já removida');
// A auditoria faz parte da transação: se falhar, a remoção inteira é revertida.
$db->exec("CREATE TRIGGER falhar_auditoria BEFORE INSERT ON logs_auditoria BEGIN SELECT RAISE(ABORT,'Falha simulada de auditoria'); END");
removalFails(fn()=>$service->remove(1,40,'Teste de reversão.','REMOVER'),'Falha simulada');
removalCheck((int)$db->query('SELECT COUNT(*) FROM inscricoes_turma WHERE id=1')->fetchColumn()===1&&(int)$db->query('SELECT COUNT(*) FROM inscricoes_turma_historico WHERE inscricao_turma_id=1')->fetchColumn()===1,'Reverter inscrição e histórico na falha.');
removalCheck($db->query('SELECT status FROM tokens_inscricao_turma WHERE id=4')->fetchColumn()==='ativo','Reverter o cancelamento de tokens na falha.');
// A capacidade é liberada na categoria efetivamente ocupada.
$available=new ReflectionMethod(App\Services\CourseEnrollmentService::class,'availableSeats');
foreach(['geral','pcd','plm','pvs'] as $public){
 $quota=removalDatabase();App\Core\Database::$pdo=$quota;$quota->exec("UPDATE inscricoes_turma SET publico_alvo='$public' WHERE id=5");
 $class=$quota->query('SELECT * FROM turmas WHERE id=1')->fetch();$course=new App\Services\CourseEnrollmentService();$beforeSeats=$available->invoke($course,$quota,$class,$public);
 (new EnrollmentRemovalService($quota))->remove(5,40,'Correção da inscrição.','REMOVER');
 removalCheck($available->invoke($course,$quota,$class,$public)===$beforeSeats+1,'Liberar exatamente uma vaga em '.$public.'.');
}
echo "Remover inscrição: exclusividade do Master, confirmação, motivo, chamadas, documentos preservados, outras turmas, quatro cotas, fila, tokens, notificações, auditoria, indicadores e reversão atômica verificados.\n";
}
