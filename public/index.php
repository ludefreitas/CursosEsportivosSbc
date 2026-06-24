<?php

declare(strict_types=1);

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

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo 'Pagina nao encontrada.';
