<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/Helpers/functions.php';
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});
date_default_timezone_set('America/Sao_Paulo');

try {
    $count = (new \App\Services\EnrollmentSuspensionService())->processExpired();
    echo "Rematrículas automáticas realizadas: {$count}\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Falha ao processar suspensões: ' . $error->getMessage() . "\n");
    exit(1);
}
