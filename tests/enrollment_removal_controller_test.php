<?php
namespace App\Core {class Auth {public static function check():bool{return true;}}}
namespace App\Services {
 class UserService {public static array $roles=[['slug'=>'master_admin']];public function currentAccountWithRoles():?array{return ['conta_id'=>40,'roles'=>self::$roles];}}
 class ProfileService {public function getAuthenticatedPerson():array{return ['cadastro_completo'=>1];}public function getRegistrationBlockForAuthenticatedPerson():?array{return null;}}
 class EnrollmentRemovalService {public static array $calls=[];public function remove(...$args):array{self::$calls[]=$args;return ['inscricao_id'=>5,'message'=>'Removida.'];}}
}
namespace {
define('ROOT_PATH',dirname(__DIR__));require __DIR__.'/../app/Helpers/functions.php';require __DIR__.'/../app/Core/Controller.php';require __DIR__.'/../app/Controllers/AdminController.php';
class RemovalControllerProbe extends App\Controllers\AdminController {
 public array $response=[];public int $status=0;
 public function __construct(){(new ReflectionProperty(App\Controllers\AdminController::class,'userService'))->setValue($this,new App\Services\UserService());}
 protected function isAjaxRequest():bool{return true;}
 protected function jsonResponse(array $payload,int $statusCode=200):void{$this->response=$payload;$this->status=$statusCode;}
}
function controllerRemovalCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$_SERVER['REQUEST_URI']='/admin/inscricoes/remover';$_SERVER['REQUEST_METHOD']='POST';$_SESSION=['enrollment_removal_csrf'=>str_repeat('a',64)];
$_POST=['inscricao_id'=>'5','motivo'=>'Inscrição indevida.','confirmacao'=>'REMOVER','csrf'=>str_repeat('b',64),'conta_id'=>999,'roles'=>['master_admin']];
$controller=new RemovalControllerProbe();$controller->removeCourseEnrollment();controllerRemovalCheck($controller->status===422&&App\Services\EnrollmentRemovalService::$calls===[],'CSRF inválido não pode chegar ao serviço.');
$_POST['csrf']=$_SESSION['enrollment_removal_csrf'];App\Services\UserService::$roles=[['slug'=>'admin']];$controller->removeCourseEnrollment();controllerRemovalCheck($controller->status===403&&App\Services\EnrollmentRemovalService::$calls===[],'Admin comum não pode acessar a remoção, mesmo forjando papéis no POST.');
App\Services\UserService::$roles=[['slug'=>'master_admin']];$controller->removeCourseEnrollment();controllerRemovalCheck($controller->status===200&&$controller->response['success']===true,'Permitir Master com sessão válida.');
controllerRemovalCheck(App\Services\EnrollmentRemovalService::$calls===[[5,40,'Inscrição indevida.','REMOVER']],'Usar o administrador autenticado, sem aceitar conta forjada no POST.');
echo "Rota Remover: CSRF, exclusividade do Master e identidade autenticada verificados.\n";
}
