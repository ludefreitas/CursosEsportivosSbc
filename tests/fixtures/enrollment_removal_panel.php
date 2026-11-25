<?php
namespace App\Services {class ParQService {public static function summaries(array $ids):array{return [];}public static function state(?array $record):string{return 'missing';}}}
namespace {
if(PHP_SAPI!=='cli')exit;
define('ROOT_PATH',dirname(__DIR__,2));require ROOT_PATH.'/app/Helpers/functions.php';
set_error_handler(static function($severity,$message,$file,$line){throw new RuntimeException($message.' em '.$line);});
$_SESSION=[];$role=$argv[1]??'master_admin';$user=['roles'=>[['slug'=>$role]]];$professorView=$role==='teacher';$internView=false;
$courseEnrollmentClassId=1;$courseEnrollmentClassName='Turma de teste';$courseEnrollmentPage=1;
$courseEnrollmentStatusSummary=['total'=>1,'por_status'=>[],'por_condicao'=>[]];
$courseEnrollmentsManagement=[['id'=>5,'pessoa_id'=>105,'nome_completo'=>'Aluno <teste>','cpf'=>'52998224725','idade'=>7,'data_nascimento'=>'2019-01-01','numero_ordem'=>5,'turma_id'=>1,'turma_nome'=>'Turma de teste','temporada_nome'=>'2027','modalidade_nome'=>'Judô','local_nome'=>'Local','espaco_nome'=>'Espaço','status'=>'matriculada','status_label'=>'Matriculada','publico_alvo'=>'geral','created_at'=>'2026-11-25 10:00:00','professor_pode_gerenciar'=>true]];
require ROOT_PATH.'/app/Views/admin/partials/course_enrollment_panel.php';
}
