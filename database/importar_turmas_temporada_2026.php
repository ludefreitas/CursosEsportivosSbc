<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/app/Helpers/functions.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

try {
    $result = (new \App\Services\ClassCopyService())->importLegacySeason2026();
    echo 'Importação concluída: ' . (int) $result['importadas'] . ' registro(s) lidos e '
        . (int) $result['armazenadas'] . " turma(s) armazenadas da temporada externa 8.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Falha na importação: ' . $e->getMessage() . "\n");
    exit(1);
}
