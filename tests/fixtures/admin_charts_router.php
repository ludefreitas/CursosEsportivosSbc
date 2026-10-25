<?php
namespace App\Core {
    class Auth { public static function check():bool{return ($_SERVER['HTTP_X_TEST_ROLE']??'admin')!=='guest';} public static function id():int{return 1;} }
    class ChartTestPdo extends \PDO {
        public function prepare(string $query,array $options=[]):\PDOStatement|false {
            return parent::prepare(str_replace('ON DUPLICATE KEY UPDATE total=total+1','ON CONFLICT(dia,tipo,entidade_id) DO UPDATE SET total=total+1',$query),$options);
        }
    }
    class Database {
        public static function connection():\PDO {
            $path=getenv('CHART_TEST_DATABASE');
            if(!$path || !is_file($path))throw new \RuntimeException('Base de teste não preparada.');
            return new ChartTestPdo('sqlite:'.$path,null,null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC]);
        }
    }
}
namespace App\Services {
    class UserService { public function currentAccountWithRoles():array{return ['conta_id'=>1,'roles'=>[['slug'=>$_SERVER['HTTP_X_TEST_ROLE']??'admin']]];} }
    class ProfileService { public function getAuthenticatedPerson():array{return ['cadastro_completo'=>(int)(($_SERVER['HTTP_X_TEST_ROLE']??'')!=='incomplete')];} public function getRegistrationBlockForAuthenticatedPerson():?array{return null;} }
}
namespace {
    if(PHP_SAPI!=='cli-server'||!getenv('CHART_TEST_DATABASE')){http_response_code(404);exit;}
    define('ROOT_PATH',dirname(__DIR__,2));
    require ROOT_PATH.'/app/Helpers/functions.php';
    spl_autoload_register(static function($class){if(str_starts_with($class,'App\\')){ $file=ROOT_PATH.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require $file;}});
    session_name('chart_fixture_session');session_start();
    $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
    if(str_starts_with($path,'/assets/')){
        $file=realpath(ROOT_PATH.'/public'.$path);$base=realpath(ROOT_PATH.'/public/assets');
        if(!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit;}
        header('Content-Type: '.(str_ends_with($file,'.js')?'application/javascript':'text/css'));readfile($file);exit;
    }
    if($path==='/jquery.js'){header('Content-Type: application/javascript');readfile(ROOT_PATH.'/tmp/tutorial-realista/jquery.min.js');exit;}
    if($path==='/cursos/interesse'){(new \App\Controllers\CourseInterestController())->record();exit;}
    if($path==='/admin/graficos/dados'||$path==='/admin/graficos-agendamento/dados'||$path==='/admin/secao'){
        $controller=(new \ReflectionClass(\App\Controllers\AdminController::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($controller,'userService'))->setValue($controller,new \App\Services\UserService());
        if($path==='/admin/secao')$controller->section();elseif($path==='/admin/graficos-agendamento/dados')$controller->agendaChartData();else$controller->chartData();exit;
    }
    if($path==='/'){
        echo '<!doctype html><meta charset="utf-8"><button data-home-location-select="1">Baetão</button><button data-home-course-modality-select="1">Futsal</button><button data-home-course-enroll="1">Abrir turma</button><script src="/jquery.js"></script><script>window.App={core:{buildUrl:p=>p}};</script><script src="/assets/js/course-interest.js"></script>';exit;
    }
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/core.css"><link rel="stylesheet" href="/assets/css/admin.css"><link rel="stylesheet" href="/assets/css/admin-charts.css"><body class="pagina-admin"><main style="max-width:1180px;margin:auto;padding:12px"><button data-admin-nav-target="graficos">Gráficos e indicadores</button><button data-admin-nav-target="graficos-agendamento">Gráficos do agendamento</button><div data-admin-section-host data-admin-section-url="/admin/secao"></div></main><script src="/jquery.js"></script><script src="/assets/js/core.js"></script><script src="/assets/js/admin.js"></script><script src="/assets/js/chart-themes.js"></script><script src="/assets/js/course-attendance-charts.js"></script><script src="/assets/js/admin-charts.js"></script><script src="/assets/js/agenda-charts.js"></script><script>App.admin.iniciarSecoesAdmin();</script></body></html>';
}
