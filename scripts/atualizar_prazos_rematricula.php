<?php
declare(strict_types=1);

// Executar separadamente na hospedagem após publicar esta atualização:
// php scripts/atualizar_prazos_rematricula.php
// Atualiza somente prazos ainda vigentes. Pode ser executado novamente.
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

$pdo = null;
try {
    $pdo = \App\Core\Database::connection();
    $pdo->beginTransaction();
    $service = new \App\Services\RematriculationDeadlineService($pdo);
    $campaigns = $service->updateActiveCampaigns();
    $tokens = $service->updateActiveTokens();
    $pdo->commit();
    echo "Campanhas com prazo ampliado: {$campaigns}\n";
    echo "Tokens de rematrícula com validade ampliada: {$tokens}\n";
} catch (Throwable $error) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Falha ao atualizar os prazos de rematrícula: ' . $error->getMessage() . "\n");
    exit(1);
}
