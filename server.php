<?php

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$publicPath = __DIR__ . '/public';
$targetFile = $publicPath . $uri;

// 1. Si es un archivo físico existente dentro de /public (ej: /openapi.yaml, /favicon.ico, CSS, JS)
if ($uri !== '/' && is_file($targetFile)) {
    // Si el servidor PHP fue iniciado con '-t public', dejamos que PHP lo entregue de forma nativa
    if (realpath($_SERVER['DOCUMENT_ROOT'] ?? '') === realpath($publicPath)) {
        return false;
    }

    // Si fue iniciado desde la raíz (ej: php -S localhost:8088 server.php), lo servimos directamente
    $extension = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $mimeTypes = [
        'yaml' => 'text/yaml; charset=UTF-8',
        'yml'  => 'text/yaml; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'css'  => 'text/css; charset=UTF-8',
        'js'   => 'application/javascript; charset=UTF-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'txt'  => 'text/plain; charset=UTF-8',
    ];

    $contentType = $mimeTypes[$extension] ?? (mime_content_type($targetFile) ?: 'application/octet-stream');
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($targetFile));
    readfile($targetFile);
    exit;
}

// 2. Si se solicita la documentación (/docs o /docs/ o /docs/index.html)
if (rtrim($uri, '/') === '/docs' || $uri === '/docs/index.html') {
    $docsIndex = $publicPath . '/docs/index.html';
    if (is_file($docsIndex)) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Length: ' . filesize($docsIndex));
        readfile($docsIndex);
        exit;
    }
}

// 3. Si es un directorio que contiene index.html
if (is_dir($targetFile) && is_file($targetFile . '/index.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Length: ' . filesize($targetFile . '/index.html'));
    readfile($targetFile . '/index.html');
    exit;
}

// 4. Todo lo demás se delega a index.php para el enrutamiento de la API
require_once $publicPath . '/index.php';