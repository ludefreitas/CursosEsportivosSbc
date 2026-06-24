<?php

declare(strict_types=1);

/**
 * Front controller canonico da raiz do projeto.
 * Tambem entrega assets fisicos armazenados em public/assets.
 */
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

if (str_starts_with($requestPath, '/assets/')) {
    $assetRelativePath = substr($requestPath, strlen('/assets/'));
    $assetFullPath = __DIR__ . '/public/assets/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $assetRelativePath);
    $realAssetPath = realpath($assetFullPath);
    $realAssetsBase = realpath(__DIR__ . '/public/assets');

    if (
        $realAssetPath === false ||
        $realAssetsBase === false ||
        !str_starts_with($realAssetPath, $realAssetsBase) ||
        !is_file($realAssetPath)
    ) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Asset nao encontrado.';
        exit;
    }

    $extension = strtolower(pathinfo($realAssetPath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
    ];

    header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($realAssetPath));
    readfile($realAssetPath);
    exit;
}

require __DIR__ . '/public/index.php';
