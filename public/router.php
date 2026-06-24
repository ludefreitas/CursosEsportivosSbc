<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$publicPath = realpath(__DIR__);
$requestedFile = realpath($publicPath . DIRECTORY_SEPARATOR . ltrim($requestPath, '/'));

if ($requestedFile && str_starts_with($requestedFile, $publicPath) && is_file($requestedFile)) {
    return false;
}

require __DIR__ . '/index.php';
