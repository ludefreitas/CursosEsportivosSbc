<?php

declare(strict_types=1);

/**
 * Router para o servidor embutido do PHP usando a raiz do projeto
 * como ponto de entrada canonico.
 */
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rootPath = realpath(__DIR__);

if ($requestPath === false || $rootPath === false) {
    require __DIR__ . '/index.php';
    return;
}

$directFile = realpath($rootPath . DIRECTORY_SEPARATOR . ltrim($requestPath, '/'));

if (
    $directFile !== false &&
    str_starts_with($directFile, $rootPath) &&
    is_file($directFile)
) {
    return false;
}

if (str_starts_with($requestPath, '/assets/')) {
    $assetFile = realpath(__DIR__ . '/public/assets/' . ltrim(substr($requestPath, strlen('/assets')), '/\\'));
    $assetsBase = realpath(__DIR__ . '/public/assets');

    if (
        $assetFile !== false &&
        $assetsBase !== false &&
        str_starts_with($assetFile, $assetsBase) &&
        is_file($assetFile)
    ) {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        require __DIR__ . '/index.php';
        return;
    }
}

if (str_starts_with($requestPath, '/uploads/')) {
    $uploadFile = realpath(__DIR__ . '/public/uploads/' . ltrim(substr($requestPath, strlen('/uploads')), '/\\'));
    $uploadsBase = realpath(__DIR__ . '/public/uploads');

    if (
        $uploadFile !== false &&
        $uploadsBase !== false &&
        str_starts_with($uploadFile, $uploadsBase) &&
        is_file($uploadFile)
    ) {
        return false;
    }
}

require __DIR__ . '/index.php';
