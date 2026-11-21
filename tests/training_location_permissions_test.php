<?php
namespace App\Core {
    class Auth { public static bool $authenticated = true; public static function check(): bool { return self::$authenticated; } }
}
namespace App\Services {
    class UserService {
        public static array $roles = [];
        public function currentAccountWithRoles(): array { return ['conta_id'=>1,'roles'=>self::$roles]; }
    }
    class ProfileService {
        public function getAuthenticatedPerson(): array { return ['cadastro_completo'=>1]; }
        public function getRegistrationBlockForAuthenticatedPerson(): ?array { return null; }
    }
}
namespace {
define('ROOT_PATH', dirname(__DIR__));
require __DIR__ . '/../app/Helpers/functions.php';
require __DIR__ . '/../app/Core/Controller.php';
require __DIR__ . '/../app/Controllers/AdminController.php';

class PermissionResponse extends RuntimeException {}
class LocationPermissionController extends App\Controllers\AdminController {
    protected function isAjaxRequest(): bool { return true; }
    protected function jsonResponse(array $payload, int $statusCode = 200): void { throw new PermissionResponse($payload['message'] ?? '', $statusCode); }
}
$controller = (new ReflectionClass(LocationPermissionController::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(App\Controllers\AdminController::class, 'userService'))->setValue($controller, new App\Services\UserService());
$assertAccess = new ReflectionMethod(App\Controllers\AdminController::class, 'assertAdminAccess');
$_SERVER['SCRIPT_NAME'] = '/index.php';
function expectAccess(string $role, string $method, string $path, bool $allowed): void {
    global $controller, $assertAccess;
    App\Services\UserService::$roles = [['slug'=>$role]];
    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['REQUEST_METHOD'] = $method;
    try {
        $assertAccess->invoke($controller);
        if (!$allowed) throw new RuntimeException("Acesso indevido: $role $method $path");
    } catch (PermissionResponse $response) {
        if ($allowed || !in_array($response->getCode(), [401,403], true)) throw new RuntimeException("Acesso incorreto: $role $method $path: " . $response->getMessage());
    }
}
foreach (['/admin/migracao-locais/lista','/admin/migracao-espacos/lista'] as $path) {
    foreach (['admin','master_admin','supervisor','coordinator','teacher','intern'] as $role) expectAccess($role, 'GET', $path . '?search=Centro', in_array($role, ['admin','master_admin'], true));
    expectAccess('admin', 'POST', $path, false);
}
foreach (['/admin/migracao-locais/executar','/admin/migracao-espacos/excluir','/admin/migracao-pessoas/lista','/admin/home-conteudo','/admin/ceps-lista','/admin/migracao-locais/lista/outro'] as $path) {
    expectAccess('admin', 'GET', $path, false);
    expectAccess('master_admin', 'GET', $path, true);
}
foreach (['/admin/locais','/admin/espacos','/admin/locais/excluir','/admin/espacos/excluir'] as $path) {
    expectAccess('admin', 'POST', $path, true);
    expectAccess('teacher', 'POST', $path, false);
    expectAccess('intern', 'POST', $path, false);
}
App\Core\Auth::$authenticated = false;
expectAccess('admin', 'GET', '/admin/migracao-locais/lista', false);
echo "Permissões de cadastro de locais e espaços: admin comum liberado, demais migrações protegidas e autenticação preservada.\n";
}
