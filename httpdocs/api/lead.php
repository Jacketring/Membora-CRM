<?php

declare(strict_types=1);

$remoteUrl = 'https://app.crm.josehurtado.dev/webhook/lead';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

if (!empty($payload['website'])) {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($body === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solicitud no valida'], JSON_UNESCAPED_UNICODE);
    exit;
}

function post_remote_lead(string $url, string $body): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Origin: https://app.web.josehurtado.dev',
                'Referer: https://app.web.josehurtado.dev/',
            ],
        ]);
        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [$status, is_string($responseBody) ? $responseBody : '', $error];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Accept: application/json\r\nContent-Type: application/json\r\nOrigin: https://app.web.josehurtado.dev\r\nReferer: https://app.web.josehurtado.dev/\r\n",
            'content' => $body,
            'timeout' => 15,
        ],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [$status, is_string($responseBody) ? $responseBody : '', ''];
}

[$status, $responseBody, $error] = post_remote_lead($remoteUrl, $body);
$responsePayload = json_decode($responseBody, true);

if ($status < 200 || $status >= 300 || !is_array($responsePayload) || empty($responsePayload['success'])) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => is_array($responsePayload) && isset($responsePayload['message'])
            ? (string) $responsePayload['message']
            : 'No se pudo enviar la solicitud. Inténtalo mas tarde.',
        'status' => $status,
        'error' => $error ?: null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($responsePayload, JSON_UNESCAPED_UNICODE);
