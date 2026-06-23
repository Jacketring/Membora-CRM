<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $route): never
{
    header('Location: index.php?route=' . urlencode($route));
    exit;
}

function post_value(string $key, ?string $default = null): ?string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function cuid(): string
{
    return 'php_' . bin2hex(random_bytes(12));
}

function format_date(?string $value): string
{
    if (!$value) {
        return 'Sin fecha';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Sin fecha';
}

function enum_label(string $value, array $labels): string
{
    return $labels[$value] ?? $value;
}
