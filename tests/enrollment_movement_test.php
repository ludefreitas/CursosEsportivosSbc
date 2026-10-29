<?php
namespace App\Core { class Database {public static \PDO $pdo;public static function connection(): \PDO {return self::$pdo;}} }
namespace App\Services { class AuditLogService {public static int $count=0;public static int $failAt=0; public static function record(...$args): void {self::$count++;if(self::$failAt===self::$count)throw new \RuntimeException('Falha simulada');} } }
namespace {
require __DIR__.'/fixtures/enrollment_movement_database.php';
require __DIR__.'/../app/Helpers/functions.php';
require __DIR__.'/../app/Services/CourseInterestService.php';
require __DIR__.'/../app/Services/EnrollmentMovementService.php';
require __DIR__.'/../app/Services/CourseEnrollmentService.php';
use App\Services\EnrollmentMovementService;
function checkMove(bool $value,string $message): void {if(!$value)throw new RuntimeException($message);}
function failsMove(callable $action,string $reason): void {try{$action();throw new LogicException('Ação deveria falhar: '.$reason);}catch(RuntimeException $e){if($e instanceof LogicException)throw$e;checkMove(str_contains($e->getMessage(),$reason),$e->getMessage());}}
function requestMove(array $replace=[]): array {return array_replace(['turma_origem_id'=>1,'turma_destino_id'=>2,'temporada_destino_id'=>1,'confirmar_outro_professor'=>1,'modo'=>'copiar','inscricoes'=>[1],'operacao_uuid'=>bin2hex(random_bytes(16)),'motivo'=>'Transferência solicitada','confirmar_exclusao_restrita'=>1,'status_confirmados'=>[]],$replace);}
$db=movementDatabase();$service=new EnrollmentMovementService($db);
$context=$service->context(1,10,false);checkMove(count($context['inscricoes'])===4,'Exibir somente os três status permitidos.');
checkMove($context['inscricoes'][0]['nome_completo']==='Aluno <teste>','Preservar nome para renderização segura.');
failsMove(fn()=>$service->context(1,20,false),'não integra');
failsMove(fn()=>$service->move(requestMove(),20,false),'não integra');
checkMove(count($service->context(1,11,false)['inscricoes'])===4,'Auxiliar professor vinculado pode enviar.');
$rows=array_column($context['inscricoes'],null,'id');checkMove(in_array('chamada registrada',$rows[5]['impedimentos_substituicao']),'Apresentar motivo de impedimento.');
failsMove(fn()=>$service->move(requestMove(['modo'=>'substituir','inscricoes'=>[5]]),10,false),'chamada registrada');
failsMove(fn()=>$service->move(requestMove(['modo'=>'substituir','turma_destino_id'=>3]),10,false),'mesma modalidade');
failsMove(fn()=>$service->move(requestMove(['turma_destino_id'=>3]),10,false),'outra modalidade');
failsMove(fn()=>$service->move(requestMove(['inscricoes'=>[2,3]]),10,false),'Confirme o status');
failsMove(fn()=>$service->move(requestMove(['inscricoes'=>[2,3],'status_confirmados'=>['aguardando_matricula']]),10,false),'Fila de espera');
failsMove(fn()=>$service->move(requestMove(['inscricoes'=>[4]]),10,false),'Somente matrículas');
failsMove(fn()=>$service->move(requestMove(['inscricoes'=>[999]]),10,false),'não pertence');
failsMove(fn()=>$service->move(requestMove(['confirmar_exclusao_restrita'=>0]),10,false),'somente o professor');
$fullDb=movementDatabase();$fullDb->exec('UPDATE turmas SET vagas_geral=0 WHERE id=2');$fullService=new EnrollmentMovementService($fullDb);$fullService->move(requestMove(['modo'=>'substituir']),10,false);
checkMove((int)$fullDb->query("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id=2 AND status='matriculada'")->fetchColumn()===1,'Permitir movimentação mesmo sem vagas, consumindo a cota normalmente.');
checkMove((int)$fullDb->query("SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id=1 AND status IN ('matriculada','aguardando_matricula')")->fetchColumn()===2,'Substituição libera a vaga na origem.');
$historyDb=movementDatabase();$historyDb->exec("UPDATE inscricoes_turma SET updated_at='2026-01-02',motivo_status='Histórico' WHERE id=1; INSERT INTO inscricoes_turma_historico(inscricao_turma_id,status_anterior,status_novo) VALUES(1,'aguardando_matricula','matriculada'); INSERT INTO tokens_inscricao_turma VALUES(3,1); INSERT INTO declaracoes_inscricao VALUES(1); INSERT INTO notificacoes_destinatarios VALUES(3,1)");
$historyService=new EnrollmentMovementService($historyDb);checkMove($historyService->blockers(1)===[],'Somente chamada impede substituição.');$historyService->move(requestMove(['modo'=>'substituir']),10,false);
checkMove((int)$historyDb->query('SELECT COUNT(*) FROM declaracoes_inscricao WHERE inscricao_turma_id=1')->fetchColumn()===1,'Conservar dados vinculados na substituição.');
$historyService->move(requestMove(['modo'=>'substituir','turma_origem_id'=>2,'turma_destino_id'=>1]),20,false);checkMove((int)$historyDb->query('SELECT turma_id FROM inscricoes_turma WHERE id=1')->fetchColumn()===1,'Movimentação anterior não impede nova substituição sem chamada.');
failsMove(fn()=>$service->move(requestMove(['confirmar_outro_professor'=>0]),10,false),'outro professor');
failsMove(fn()=>$service->move(requestMove(['temporada_destino_id'=>2]),10,false),'temporada selecionada');
$closedDb=movementDatabase();$closedDb->exec("UPDATE turmas SET status='inscricoes_encerradas' WHERE id=2");$closedService=new EnrollmentMovementService($closedDb);checkMove(!in_array(2,array_column($closedService->context(1,10,false)['destinos'],'id')),'Ocultar turma encerrada.');failsMove(fn()=>$closedService->move(requestMove(),10,false),'turma encerrada');
$closedDb->exec("UPDATE turmas SET status='planejada' WHERE id=2; UPDATE temporadas SET status='encerrada'");checkMove($closedService->context(1,10,false)['destinos']===[],'Ocultar turmas de temporada encerrada.');failsMove(fn()=>$closedService->move(requestMove(),10,false),'temporada encerrada');
$request=requestMove(['inscricoes'=>[1,2,3],'status_confirmados'=>['aguardando_matricula','lista_espera']]);
checkMove($service->move($request,10,false)['quantidade']===3,'Mover lote completo.');
checkMove($service->move($request,10,false)['reenvio'],'Reenvio idempotente.');
checkMove((int)$db->query('SELECT COUNT(*) FROM movimentacoes_inscricoes')->fetchColumn()===3,'Sem duplicação em reenvio.');
checkMove((int)$db->query('SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id=1')->fetchColumn()===5,'Cópia mantém originais e chamadas.');
checkMove($db->query('SELECT status FROM inscricoes_turma WHERE turma_id=2 AND pessoa_id=103')->fetchColumn()==='lista_espera','Preservar status de fila.');
failsMove(fn()=>$service->move(requestMove(),10,false),'já tem inscrição');
failsMove(fn()=>$service->move(array_replace($request,['motivo'=>'Outro motivo']),10,false),'já utilizado');
$newId=(int)$db->query('SELECT inscricao_destino_id FROM movimentacoes_inscricoes WHERE inscricao_origem_id=1')->fetchColumn();
failsMove(fn()=>$service->assertRemovalAllowed($newId),'somente o professor principal');
\App\Core\Database::$pdo=$db;$legacy=new \App\Services\CourseEnrollmentService();
failsMove(fn()=>$legacy->cancel($newId),'somente o professor principal');
failsMove(fn()=>$legacy->changeStatus($newId,'desistente',10),'somente o professor principal');
failsMove(fn()=>$legacy->changeAttendanceEnrollmentStatus(2,$newId,'desistente',10),'somente o professor principal');
failsMove(fn()=>$legacy->deletePermanentlyForProfessor($newId,20),'somente o professor principal');
failsMove(fn()=>$service->delete($newId,10),'Somente o professor principal');
failsMove(fn()=>$service->delete($newId,40),'Somente o professor principal');
$db->exec('INSERT INTO turmas_chamadas VALUES(2,'.$newId.',2,\'presente\'); INSERT INTO declaracoes_inscricao VALUES('.$newId.'); INSERT INTO tokens_inscricao_turma VALUES(1,'.$newId.'); INSERT INTO notificacoes_destinatarios VALUES(1,'.$newId.')');
$copiedSource=$service->annotate([['id'=>1,'professor_principal_conta_id'=>10]],10);checkMove($copiedSource[0]['inscricao_copiada'],'Marcar inscrição original copiada.');
checkMove(count($service->copies(1,10,false)['destinos'])===1,'Relacionar destinos da origem.');
checkMove($service->copies($newId,20,false)['destinos'][0]['turma_id']===2,'Relacionar destino ao abrir uma inscrição recebida por cópia.');
failsMove(fn()=>$service->copies(1,30,false),'não integra');
$annotated=$service->annotate([['id'=>$newId,'professor_principal_conta_id'=>20]],20);checkMove($annotated[0]['pode_excluir_movida'],'Ação exclusiva do principal do destino.');
$service->delete($newId,20);
checkMove((int)$db->query('SELECT COUNT(*) FROM inscricoes_turma WHERE id='.$newId)->fetchColumn()===0,'Excluir definitivamente.');
checkMove($db->query('SELECT excluida_em FROM movimentacoes_inscricoes WHERE inscricao_destino_id='.$newId)->fetchColumn()!==null,'Preservar histórico de envio e exclusão.');
checkMove((int)$db->query('SELECT COUNT(*) FROM turmas_chamadas WHERE inscricao_turma_id=5')->fetchColumn()===1,'Não afetar chamada da origem.');
checkMove($service->copies(1,10,false)['destinos'][0]['excluida_em']!==null,'Mostrar histórico de destinos excluídos.');
$copyCross=requestMove(['inscricoes'=>[5],'turma_destino_id'=>3,'confirmar_modalidade_diferente'=>1]);$service->move($copyCross,10,false);
checkMove((int)$db->query('SELECT COUNT(*) FROM turmas_chamadas WHERE inscricao_turma_id=5')->fetchColumn()===1,'Cópia entre modalidades preserva vínculos da origem.');
$replacementDb=movementDatabase();$replacement=new EnrollmentMovementService($replacementDb);$replacement->move(requestMove(['modo'=>'substituir']),10,false);
checkMove((int)$replacementDb->query('SELECT turma_id FROM inscricoes_turma WHERE id=1')->fetchColumn()===2,'Substituição preserva ID e altera turma.');
checkMove((int)$replacementDb->query('SELECT COUNT(*) FROM inscricoes_turma')->fetchColumn()===5,'Substituição não duplica inscrição.');
$report=$service->report(['demand_from'=>'2020-01-01','demand_to'=>'2099-12-31','season_ids'=>[1],'location'=>0,'modality'=>0,'public'=>'','status'=>'','period'=>'']);
checkMove($report['totals']['copias']===4 && $report['totals']['excluidas']===1,'Indicadores por envio e exclusão reconciliados.');
$atomicDb=movementDatabase();$atomic=new EnrollmentMovementService($atomicDb);\App\Services\AuditLogService::$count=0;\App\Services\AuditLogService::$failAt=2;
failsMove(fn()=>$atomic->move(requestMove(['inscricoes'=>[1,2],'status_confirmados'=>['aguardando_matricula']]),10,false),'Falha simulada');
checkMove((int)$atomicDb->query('SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id=2')->fetchColumn()===0 && (int)$atomicDb->query('SELECT COUNT(*) FROM movimentacoes_inscricoes')->fetchColumn()===0,'Falha no meio do lote deve reverter tudo.');
\App\Services\AuditLogService::$failAt=0;
$phaseDb=movementDatabase();$phaseDb->exec("UPDATE turmas SET status='periodo_matricula',vagas_geral=1,vagas_espera_geral=0 WHERE id=2");$phase=new EnrollmentMovementService($phaseDb);
$phase->move(requestMove(['inscricoes'=>[3],'status_confirmados'=>['lista_espera']]),10,false);
$phase->move(requestMove(['inscricoes'=>[2],'status_confirmados'=>['aguardando_matricula']]),10,false);checkMove((int)$phaseDb->query('SELECT COUNT(*) FROM inscricoes_turma WHERE turma_id=2')->fetchColumn()===2,'Permitir exceder a capacidade também com fila de espera.');
$db->exec('DELETE FROM turmas WHERE id=2');
$afterClassDelete=$service->report(['demand_from'=>'2020-01-01','demand_to'=>'2099-12-31','season_ids'=>[1],'location'=>0,'modality'=>0,'public'=>'','status'=>'','period'=>'']);checkMove($afterClassDelete['totals']['copias']===4,'Histórico e indicadores não somem com a turma de destino removida.');
checkMove($service->copies(1,10,false)['destinos'][0]['nome']==='Ginástica de destino','Recuperar nome do retrato quando turma não existe mais.');
$missing=new PDO('sqlite::memory:');$missing->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);failsMove(fn()=>(new EnrollmentMovementService($missing))->context(1,10,false),'migração explícita');
echo "Movimentações: permissões, cópia, substituição, bloqueios, status, capacidade, idempotência, exclusão pelo principal, auditoria e indicadores OK.\n";
}
