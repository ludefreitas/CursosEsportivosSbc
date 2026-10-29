<?php
namespace App\Services { class AuditLogService { public static function record(...$args): void {} } }
namespace {
require __DIR__.'/fixtures/enrollment_movement_database.php';
require __DIR__.'/../app/Helpers/functions.php';
require __DIR__.'/../app/Services/CourseInterestService.php';
require __DIR__.'/../app/Services/CourseEnrollmentService.php';
require __DIR__.'/../app/Services/EnrollmentMovementService.php';
use App\Services\EnrollmentMovementService;
function compatibilityCheck(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
function compatibilityFailure(callable $action): void {try{$action();throw new LogicException('Envio incompatível sem confirmação foi aceito.');}catch(RuntimeException $e){if($e instanceof LogicException)throw$e;compatibilityCheck(str_contains($e->getMessage(),'incompatibilidades'),$e->getMessage());}}
function compatibilityRequest(array $extra=[]): array {return array_replace(['turma_origem_id'=>1,'turma_destino_id'=>2,'temporada_destino_id'=>1,'modo'=>'copiar','inscricoes'=>[1],'operacao_uuid'=>bin2hex(random_bytes(16)),'motivo'=>'Movimentação excepcional confirmada','confirmar_outro_professor'=>1,'confirmar_exclusao_restrita'=>1],$extra);}
$db=movementDatabase();$service=new EnrollmentMovementService($db);
$db->exec("UPDATE turmas SET idade_minima=60,idade_maxima=80,sexo='feminino',vagas_geral=0,vagas_espera_geral=0,vagas_plm=0,vagas_espera_plm=0,vagas_pvs=0,vagas_espera_pvs=0 WHERE id=2");
$request=compatibilityRequest();$preview=$service->preview($request,10,false);
compatibilityCheck(count($preview['avisos'])===1 && count($preview['avisos'][0]['motivos'])===3,'Informar idade, sexo e ausência de cota Geral em turma só de PCD.');
compatibilityFailure(fn()=>$service->move($request,10,false));
compatibilityFailure(fn()=>$service->move($request+['confirmar_incompatibilidades'=>1],10,false));
compatibilityFailure(fn()=>$service->move($request+['confirmar_incompatibilidades'=>1,'compatibilidade_hash'=>'forjado'],10,false));
$db->exec('UPDATE turmas SET idade_minima=65 WHERE id=2');
compatibilityFailure(fn()=>$service->move($request+['confirmar_incompatibilidades'=>1,'compatibilidade_hash'=>$preview['hash']],10,false));
$preview=$service->preview($request,10,false);$service->move($request+['confirmar_incompatibilidades'=>1,'compatibilidade_hash'=>$preview['hash']],10,false);
compatibilityCheck($db->query('SELECT publico_alvo FROM inscricoes_turma WHERE turma_id=2')->fetchColumn()==='geral','Manter público da inscrição, sem converter Geral em PCD.');
$snapshot=json_decode($db->query('SELECT destino_json FROM movimentacoes_inscricoes')->fetchColumn(),true);
compatibilityCheck($snapshot['_confirmacoes']['compatibilidade']['confirmada'] && count($snapshot['_confirmacoes']['compatibilidade']['motivos'])===3,'Guardar motivos e confirmação da exceção no histórico.');
foreach(['pcd','plm','pvs']as$condition){
    $conditionDb=movementDatabase();$conditionService=new EnrollmentMovementService($conditionDb);
    $conditionDb->exec("UPDATE inscricoes_turma SET publico_alvo='$condition' WHERE id=1; UPDATE turmas SET vagas_geral=0,vagas_espera_geral=0,vagas_plm=0,vagas_espera_plm=0,vagas_pvs=0,vagas_espera_pvs=0 WHERE id=2");
    $data=compatibilityRequest();$check=$conditionService->preview($data,10,false);$reasons=implode(' ',$check['avisos'][0]['motivos']);
    compatibilityCheck(str_contains($reasons,strtoupper($condition)) && str_contains($reasons,'comprovação'),'Conferir cadastro e comprovação de '.$condition);
    if($condition!=='pcd')compatibilityCheck(str_contains($reasons,'cota configurada'),'Avisar que a cota da inscrição não existe no destino.');
    $conditionService->move($data+['confirmar_incompatibilidades'=>1,'compatibilidade_hash'=>$check['hash']],10,false);
    compatibilityCheck($conditionDb->query('SELECT publico_alvo FROM inscricoes_turma WHERE turma_id=2')->fetchColumn()===$condition,'Exceção não pode alterar a condição original.');
}
$ageDb=movementDatabase();$ageService=new EnrollmentMovementService($ageDb);
$ageDb->exec("UPDATE inscricoes_turma SET publico_alvo='pcd' WHERE id=1; UPDATE pessoas SET eh_pcd=1 WHERE id=101; UPDATE turmas SET idade_minima=60,idade_maxima=80,excecoes_idade_json='{\"pcd\":{\"enabled\":true,\"min\":20,\"max\":30}}' WHERE id=2; INSERT INTO tipos_certificados VALUES(1,'pcd'); INSERT INTO certificados_pessoa VALUES(1,101,1,'validado','2099-12-31'); INSERT INTO documentos_certificados VALUES(1,1)");
compatibilityCheck($ageService->preview(compatibilityRequest(),10,false)['avisos']===[],'Aplicar exceção etária de PCD com comprovação válida.');
$ageDb->exec("UPDATE certificados_pessoa SET validade_certificado='2000-01-01'");
compatibilityCheck(count($ageService->preview(compatibilityRequest(),10,false)['avisos'][0]['motivos'])===2,'Comprovação vencida exige confirmação e não concede exceção etária automaticamente.');
$yearDb=movementDatabase();$yearService=new EnrollmentMovementService($yearDb);
$yearDb->exec("UPDATE pessoas SET data_nascimento='2006-12-31' WHERE id=101; UPDATE turmas SET idade_minima=20,idade_maxima=20,criterio_faixa_etaria='ano_nascimento' WHERE id=2");
compatibilityCheck($yearService->preview(compatibilityRequest(),10,false)['avisos']===[],'Respeitar critério por ano de nascimento.');
$yearDb->exec("UPDATE turmas SET criterio_faixa_etaria='idade_exata' WHERE id=2");
compatibilityCheck(count($yearService->preview(compatibilityRequest(),10,false)['avisos'])===1,'Idade exata deve considerar aniversário na referência da aula.');
echo "Compatibilidade: idade e exceções, sexo, cotas Geral/PCD/PLM/PVS, documentos, confirmação obrigatória, revalidação e auditoria OK.\n";
}
