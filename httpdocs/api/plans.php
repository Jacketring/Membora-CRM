<?php

declare(strict_types=1);

require __DIR__ . '/_origin.php';

$remoteUrl = membora_public_origin() . '/app/api/plans';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function fetch_remote_plans(string $url): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [$status, is_string($body) ? $body : '', $error];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n",
            'timeout' => 10,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [$status, is_string($body) ? $body : '', ''];
}

[$status, $body, $error] = fetch_remote_plans($remoteUrl);
$payload = json_decode($body, true);

if ($status < 200 || $status >= 300 || !is_array($payload) || empty($payload['success']) || !isset($payload['plans']) || !is_array($payload['plans'])) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudieron cargar los planes publicos de la plataforma.',
        'status' => $status,
        'error' => $error ?: null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
