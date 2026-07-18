<?php

declare(strict_types=1);

// Load the application environment before any service reads configuration.
// Previously this happened only when the first database connection was opened,
// so payment configuration could fall back to the simulated checkout.
require __DIR__ . '/../config/config.php';

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

// A dedicated cookie name avoids stale PHPSESSID cookies from the public site or old domains.
$configuredSessionName = trim((string) (getenv('SESSION_COOKIE_NAME') ?: 'MEMBORA_CRM_SESSION'));
$sessionName = preg_replace('/[^A-Za-z0-9_]/', '', $configuredSessionName) ?: 'MEMBORA_CRM_SESSION';
if (!preg_match('/^[A-Za-z]/', $sessionName)) {
    $sessionName = 'M_' . $sessionName;
}
session_name($sessionName);

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
require __DIR__ . '/DashboardMetrics.php';
require __DIR__ . '/Security/DemoAccessPolicy.php';
require __DIR__ . '/Security/LoginRateLimitPolicy.php';
require __DIR__ . '/Security/UserMutationPolicy.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Mailer.php';
require __DIR__ . '/Repositories.php';
require __DIR__ . '/StripeBilling.php';
require __DIR__ . '/Actions.php';
