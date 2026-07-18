<?php

declare(strict_types=1);

$crmPublicRoot = realpath(__DIR__ . '/../../apps/crm/public');
if ($crmPublicRoot === false) {
    http_response_code(503);
    exit('La plataforma no esta disponible.');
}

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/app/'), PHP_URL_PATH) ?: '/app/';
$appPrefix = '/' . trim((string) (getenv('MEMBORA_APP_PATH') ?: '/app'), '/');

if ($requestPath !== $appPrefix && !str_starts_with($requestPath, $appPrefix . '/')) {
    http_response_code(404);
    exit('Ruta no encontrada.');
}

$relativePath = '/' . ltrim(substr($requestPath, strlen($appPrefix)), '/');

if (str_starts_with($relativePath, '/assets/') || str_starts_with($relativePath, '/uploads/')) {
    $allowedRoot = str_starts_with($relativePath, '/assets/')
        ? realpath($crmPublicRoot . '/assets')
        : realpath($crmPublicRoot . '/uploads');
    $file = realpath($crmPublicRoot . $relativePath);

    if ($allowedRoot === false || $file === false || !is_file($file)
        || !str_starts_with($file, $allowedRoot . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $allowedExtensions = str_starts_with($relativePath, '/assets/')
        ? ['css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'ico', 'woff', 'woff2']
        : ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    if (!in_array($extension, $allowedExtensions, true)) {
        http_response_code(403);
        exit('Tipo de archivo no permitido.');
    }

    $mimeTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($file));
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        readfile($file);
    }
    exit;
}

$query = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
$_SERVER['REQUEST_URI'] = $relativePath . ($query !== null && $query !== '' ? '?' . $query : '');

chdir($crmPublicRoot);
require $crmPublicRoot . '/index.php';
