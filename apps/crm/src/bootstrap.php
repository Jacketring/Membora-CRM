<?php

declare(strict_types=1);

require __DIR__ . '/Support.php';

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

$sentryDsn = trim((string) getenv('SENTRY_DSN'));
if ($sentryDsn !== '' && function_exists('Sentry\\init')) {
    \Sentry\init([
        'dsn' => $sentryDsn,
        'environment' => getenv('SENTRY_ENVIRONMENT') ?: 'production',
        'send_default_pii' => false,
    ]);
}

$isSecureRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isSecureRequest,
    'httponly' => true,
    'samesite' => 'Lax',
]);

send_security_headers($isSecureRequest);

session_start();
ob_start('inject_csrf_fields');

require __DIR__ . '/Database.php';
require __DIR__ . '/Security/DemoAccessPolicy.php';
require __DIR__ . '/Security/LoginRateLimitPolicy.php';
require __DIR__ . '/Security/UserMutationPolicy.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Mailer.php';
require __DIR__ . '/Repositories.php';
require __DIR__ . '/StripeBilling.php';
require __DIR__ . '/Actions.php';
