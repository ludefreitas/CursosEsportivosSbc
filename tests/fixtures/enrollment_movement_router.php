<?php
namespace App\Core {
    class Auth {
        public static function check(): bool { return ($_SERVER['HTTP_X_TEST_ROLE']??'teacher')!=='guest'; }
        public static function id(): int { return (int)($_SERVER['HTTP_X_TEST_ACTOR']??10); }
    }
    class Database {
        public static function connection(): \PDO {
            static $db;
            return $db ??= new \PDO('sqlite:'.getenv('MOVEMENT_TEST_DATABASE'),null,null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC]);
        }
    }
}
namespace App\Services {
    class UserService { public function currentAccountWithRoles(): array { return ['conta_id'=>\App\Core\Auth::id(),'roles'=>[['slug'=>$_SERVER['HTTP_X_TEST_ROLE']??'teacher']]]; } }
    class ProfileService { public function getAuthenticatedPerson(): array {return ['cadastro_completo'=>1];} public function getRegistrationBlockForAuthenticatedPerson(): ?array {return null;} }
}
namespace {
    if(PHP_SAPI!=='cli-server'||!getenv('MOVEMENT_TEST_DATABASE')){http_response_code(404);exit;}
    define('ROOT_PATH',dirname(__DIR__,2));require ROOT_PATH.'/app/Helpers/functions.php';
    spl_autoload_register(static function($class){if(str_starts_with($class,'App\\')){$file=ROOT_PATH.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require$file;}});
    session_start();$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    if($path==='/jquery.js'){header('Content-Type: application/javascript');readfile(ROOT_PATH.'/tmp/tutorial-realista/jquery.min.js');exit;}
    if(str_starts_with($path,'/assets/')){$file=realpath(ROOT_PATH.'/public'.$path);$base=realpath(ROOT_PATH.'/public/assets');if(!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit;}header('Content-Type: '.(str_ends_with($file,'.js')?'application/javascript':'text/css'));readfile($file);exit;}
    if(preg_match('~^/(admin|professor)/inscricoes/(mover|movidas/excluir|copias|mover/compatibilidade)$~',$path,$match)){
        $class=$match[1]==='admin'?\App\Controllers\AdminController::class:\App\Controllers\ProfessorController::class;
        $controller=(new \ReflectionClass($class))->newInstanceWithoutConstructor();(new \ReflectionProperty($controller,'userService'))->setValue($controller,new \App\Services\UserService());
        $method=$match[2]==='mover/compatibilidade'?'enrollmentMovementPreview':($match[2]==='copias'?'enrollmentMovementCopies':($match[2]==='movidas/excluir'?'enrollmentMovementDelete':($_SERVER['REQUEST_METHOD']==='POST'?'enrollmentMovementSave':'enrollmentMovementContext')));$controller->$method();exit;
    }
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/core.css"><link rel="stylesheet" href="/assets/css/admin.css"><body><main><button data-enrollment-move="1">Mover inscritos</button>'.($path==='/professor'?'<div data-professor-mode="1"></div>':'').'<div id="message" role="status"></div></main><script src="/jquery.js"></script><script>window.App={core:{buildUrl:p=>p,abrirPopup:(type,message)=>{document.querySelector("#message").textContent=message;},extrairMensagemErroAjax:xhr=>({mensagem:xhr.responseJSON?.message||"Falha"})},admin:{activateSection:()=>{}}};</script><script src="/assets/js/enrollment-movements.js"></script></body></html>';
}
