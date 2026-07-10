<?php

$envPaths = [
    dirname(__DIR__) . '/.env',
    dirname(__DIR__, 3) . '/php-app/.env',
    dirname(__DIR__, 3) . '/.env',
];

foreach ($envPaths as $envPath) {
    if (!is_file($envPath)) {
        continue;
    }

    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

return [
    'app_name' => getenv('APP_NAME') ?: 'Membora CRM',
    'database_url' => getenv('DATABASE_URL') ?: '',
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: '',
        'username' => getenv('DB_USERNAME') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
];
