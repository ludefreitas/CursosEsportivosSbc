<?php

declare(strict_types=1);

/**
 * Front controller canônico da raiz do projeto.
 * Também entrega assets físicos armazenados em public/assets.
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

// Vídeos de ajuda são públicos e precisam aceitar pedidos de intervalos de bytes.
require_once __DIR__ . '/app/Services/TutorialVideoStreamService.php';
if (\App\Services\TutorialVideoStreamService::serve(__DIR__, $requestPath)) exit;

// Imagens públicas de divulgação e identidade visual. Não expor uploads
// particulares (atestados e documentos) pelo front controller.
if (preg_match('~^/uploads/(posters/[a-f0-9]{32}\\.(?:jpg|png|webp)|pdf-branding/logo-[a-f0-9]{24}\\.(?:jpg|png))$~', $requestPath)) {
    $imagePath = __DIR__ . '/public' . $requestPath;
    if (!is_file($imagePath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Imagem não encontrada.';
        exit;
    }
    $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    header('Content-Type: ' . ['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$extension]);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . (string) filesize($imagePath));
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($imagePath);
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
        echo 'Asset não encontrado.';
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
