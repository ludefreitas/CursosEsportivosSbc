<?php
if (PHP_SAPI !== 'cli-server') throw new RuntimeException('Fixture exclusiva do servidor local de testes.');
define('ROOT_PATH', dirname(__DIR__, 2));
require ROOT_PATH . '/app/Helpers/functions.php';
spl_autoload_register(static function ($class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require_once $file;
});
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/')) {
    $file = realpath(ROOT_PATH . '/public' . $path);
    $assetRoot = realpath(ROOT_PATH . '/public/assets') . DIRECTORY_SEPARATOR;
    if (!$file || !str_starts_with($file, $assetRoot)) { http_response_code(404); exit; }
    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'application/javascript'));
    readfile($file);
    exit;
}
$pdo = new PDO('sqlite:' . getenv('PEOPLE_CHART_TEST_DATABASE'), null, null, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA query_only=ON');
$service = new App\Services\PeopleChartService($pdo);
if (str_ends_with($path, '/dados')) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        echo json_encode(['success'=>true,'data'=>$service->report($_GET)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $error) {
        http_response_code(422);
        echo json_encode(['success'=>false,'message'=>$error->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
$professorView = $path === '/professor';
$peopleChartOptions = $service->options();
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/css/core.css"><link rel="stylesheet" href="/assets/css/admin.css"><link rel="stylesheet" href="/assets/css/admin-charts.css"><link rel="stylesheet" href="/assets/css/people-charts.css">
</head><body><main style="max-width:1280px;margin:0 auto;padding:16px">
<section class="content-card"><h1><?php echo $professorView ? 'Área do professor' : 'Área administrativa'; ?></h1></section>
<div class="admin-sections-shell"><nav class="content-card admin-nav-card">Menu administrativo</nav><div class="admin-section-host">
<?php require ROOT_PATH . '/app/Views/admin/partials/people_charts_panel.php'; ?>
</div></div></main><script src="/assets/js/chart-themes.js"></script><script src="/assets/js/people-charts.js"></script></body></html>
