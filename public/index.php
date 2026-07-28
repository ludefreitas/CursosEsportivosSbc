<?php

declare(strict_types=1);

// Toda resposta textual da aplicação usa UTF-8 e português do Brasil.
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($requestPath === '/public' || $requestPath === '/public/') {
    header('Location: /', true, 302);
    exit;
}

if (str_starts_with($requestPath, '/public/')) {
    $redirectPath = substr($requestPath, strlen('/public'));
    $redirectPath = $redirectPath === '' ? '/' : $redirectPath;

    if (!empty($_SERVER['QUERY_STRING'])) {
        $redirectPath .= '?' . $_SERVER['QUERY_STRING'];
    }

    header('Location: ' . $redirectPath, true, 302);
    exit;
}

session_name((require dirname(__DIR__) . '/config/app.php')['session_name']);
session_start();

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/app/Helpers/functions.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = ROOT_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

set_exception_handler(function (\Throwable $e): void {
    error_log('[CursosEsportivosSbc] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    render_error_page(500);
});

$routes = require ROOT_PATH . '/config/routes.php';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentPath = current_path();

foreach ($routes as [$method, $path, $handler]) {
    if ($method !== $requestMethod || $path !== $currentPath) {
        continue;
    }

    [$controllerClass, $action] = $handler;
    $controller = new $controllerClass();
    $controller->$action();
    exit;
}

render_error_page(404);
