<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function send_security_headers(bool $isSecureRequest = false): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://flagcdn.com; style-src 'self' 'unsafe-inline'; script-src 'self'");

    if ($isSecureRequest) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function request_origin_allowed(): bool
{
    $origin = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    $referer = rtrim((string) ($_SERVER['HTTP_REFERER'] ?? ''), '/');
    $currentOrigin = current_request_origin();
    $appPath = '/' . trim((string) (getenv('MEMBORA_APP_PATH') ?: '/app'), '/');
    $current = $currentOrigin . $appPath;

    if ($origin !== '') {
        return hash_equals($currentOrigin, $origin);
    }

    if ($referer !== '') {
        return str_starts_with($referer, $current . '/') || hash_equals($current, $referer);
    }

    return strtolower((string) (getenv('APP_STRICT_POST_ORIGIN') ?: 'false')) !== 'true';
}

function current_request_origin(): string
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '' || !preg_match('/^[a-z0-9.-]+(?::\d+)?$/', $host)) {
        $configured = parse_url(app_base_url());
        $host = isset($configured['host'])
            ? $configured['host'] . (isset($configured['port']) ? ':' . $configured['port'] : '')
            : 'membora.es';
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    return ($https ? 'https://' : 'http://') . $host;
}

function enforce_internal_post_security(): void
{
    $demoType = post_value('demo_type', 'client') === 'admin' ? 'admin' : 'client';
    if (post_value('action', '') === 'demo_login'
        && DemoAccessPolicy::isTypeEnabled((string) getenv('APP_ENV'), $demoType)
        && demo_origin_allowed()) {
        return;
    }

    if (request_origin_allowed()) {
        return;
    }

    flash('Solicitud bloqueada por seguridad. Recarga la página e inténtalo de nuevo.', 'error');
    redirect('dashboard');
}

function demo_origin_allowed(): bool
{
    $demoType = post_value('demo_type', 'client') === 'admin' ? 'admin' : 'client';
    if (!DemoAccessPolicy::isTypeEnabled((string) getenv('APP_ENV'), $demoType)) {
        return false;
    }
    $origin = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    $referer = rtrim((string) ($_SERVER['HTTP_REFERER'] ?? ''), '/');
    $allowedWebOrigins = array_values(array_filter(array_map(
        static fn (string $value): string => rtrim(trim($value), '/'),
        explode(',', (string) (getenv('WEB_APP_URL') ?: 'https://membora.es'))
    )));

    if ($origin !== '') {
        return in_array($origin, $allowedWebOrigins, true);
    }

    if ($referer !== '') {
        foreach ($allowedWebOrigins as $allowedWeb) {
            if (str_starts_with($referer, $allowedWeb . '/') || hash_equals($allowedWeb, $referer)) {
                return true;
            }
        }

        return false;
    }

    return true;
}

function redirect(string $route): never
{
    header('Location: index.php?route=' . urlencode($route));
    exit;
}

function app_base_url(): string
{
    $configured = getenv('APP_URL');
    if ($configured) {
        $configured = rtrim($configured, '/');
        $configuredPath = (string) (parse_url($configured, PHP_URL_PATH) ?: '');
        $appPath = '/' . trim((string) (getenv('MEMBORA_APP_PATH') ?: '/app'), '/');

        // Production exposes the CRM below /app. Keep return URLs inside the
        // application even when APP_URL was configured with only the origin.
        if (($configuredPath === '' || $configuredPath === '/') && $appPath !== '/') {
            return $configured . $appPath;
        }

        return $configured;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'membora.es';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $appPath = '/' . trim((string) (getenv('MEMBORA_APP_PATH') ?: '/app'), '/');
    $basePath = $scriptName === $appPath || str_starts_with($scriptName, $appPath . '/') ? $appPath : '';
    return ($https ? 'https://' : 'http://') . $host . $basePath;
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

function form_token(string $key): string
{
    $token = bin2hex(random_bytes(16));
    $_SESSION['form_tokens'][$key] = $token;

    return $token;
}

function consume_form_token(string $key, ?string $token): bool
{
    $storedToken = $_SESSION['form_tokens'][$key] ?? null;
    unset($_SESSION['form_tokens'][$key]);

    return is_string($storedToken) && is_string($token) && hash_equals($storedToken, $token);
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

function format_date_short(?string $value): string
{
    if (!$value) {
        return 'Sin fecha';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : 'Sin fecha';
}

function money_amount(mixed $value): string
{
    return number_format((float) $value, 2, ',', '.') . ' EUR';
}

function hex_color_or_default(?string $value, string $default = '#004bf2'): string
{
    $value = trim((string) $value);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $default;
}

function country_dial_codes(): array
{
    return [
        ['country' => 'España', 'iso' => 'es', 'code' => '+34'],
        ['country' => 'Portugal', 'iso' => 'pt', 'code' => '+351'],
        ['country' => 'Francia', 'iso' => 'fr', 'code' => '+33'],
        ['country' => 'Italia', 'iso' => 'it', 'code' => '+39'],
        ['country' => 'Alemania', 'iso' => 'de', 'code' => '+49'],
        ['country' => 'Reino Unido', 'iso' => 'gb', 'code' => '+44'],
        ['country' => 'Irlanda', 'iso' => 'ie', 'code' => '+353'],
        ['country' => 'Países Bajos', 'iso' => 'nl', 'code' => '+31'],
        ['country' => 'Bélgica', 'iso' => 'be', 'code' => '+32'],
        ['country' => 'Suiza', 'iso' => 'ch', 'code' => '+41'],
        ['country' => 'Austria', 'iso' => 'at', 'code' => '+43'],
        ['country' => 'Dinamarca', 'iso' => 'dk', 'code' => '+45'],
        ['country' => 'Suecia', 'iso' => 'se', 'code' => '+46'],
        ['country' => 'Noruega', 'iso' => 'no', 'code' => '+47'],
        ['country' => 'Finlandia', 'iso' => 'fi', 'code' => '+358'],
        ['country' => 'Polonia', 'iso' => 'pl', 'code' => '+48'],
        ['country' => 'Rumania', 'iso' => 'ro', 'code' => '+40'],
        ['country' => 'Marruecos', 'iso' => 'ma', 'code' => '+212'],
        ['country' => 'Estados Unidos', 'iso' => 'us', 'code' => '+1'],
        ['country' => 'Canada', 'iso' => 'ca', 'code' => '+1'],
        ['country' => 'Mexico', 'iso' => 'mx', 'code' => '+52'],
        ['country' => 'Argentina', 'iso' => 'ar', 'code' => '+54'],
        ['country' => 'Chile', 'iso' => 'cl', 'code' => '+56'],
        ['country' => 'Colombia', 'iso' => 'co', 'code' => '+57'],
        ['country' => 'Peru', 'iso' => 'pe', 'code' => '+51'],
        ['country' => 'Ecuador', 'iso' => 'ec', 'code' => '+593'],
        ['country' => 'Venezuela', 'iso' => 've', 'code' => '+58'],
        ['country' => 'Uruguay', 'iso' => 'uy', 'code' => '+598'],
        ['country' => 'Paraguay', 'iso' => 'py', 'code' => '+595'],
        ['country' => 'Brasil', 'iso' => 'br', 'code' => '+55'],
        ['country' => 'China', 'iso' => 'cn', 'code' => '+86'],
        ['country' => 'Japon', 'iso' => 'jp', 'code' => '+81'],
        ['country' => 'Corea del Sur', 'iso' => 'kr', 'code' => '+82'],
        ['country' => 'India', 'iso' => 'in', 'code' => '+91'],
        ['country' => 'Australia', 'iso' => 'au', 'code' => '+61'],
    ];
}

function country_dial_options(): array
{
    return country_dial_codes();
}

function phone_country_value(?string $phone): string
{
    $phone = trim((string) $phone);
    if ($phone === '') {
        return '+34';
    }

    $codes = country_dial_codes();
    usort($codes, fn (array $a, array $b): int => strlen($b['code']) <=> strlen($a['code']));

    foreach ($codes as $entry) {
        $code = $entry['code'];
        if (str_starts_with($phone, $code)) {
            return $code;
        }
    }

    if (preg_match('/^(\+\d{1,4})/', $phone, $matches)) {
        return $matches[1];
    }

    return '+34';
}

function phone_country_entry(?string $phone): array
{
    $code = phone_country_value($phone);

    foreach (country_dial_codes() as $entry) {
        if ($entry['code'] === $code) {
            return $entry;
        }
    }

    return country_dial_codes()[0];
}

function phone_local_value(?string $phone): string
{
    $phone = trim((string) $phone);
    if ($phone === '') {
        return '';
    }

    $countryValue = phone_country_value($phone);
    if (preg_match('/(\+\d{1,4})/', $countryValue, $matches) && str_starts_with($phone, $matches[1])) {
        return trim(substr($phone, strlen($matches[1])));
    }

    return $phone;
}

function phone_from_post(): ?string
{
    $country = post_value('phone_country', '');
    $number = post_value('phone_number', '');

    if ($number === '') {
        return null;
    }

    preg_match('/(\+\d{1,4})/', (string) $country, $matches);
    $prefix = $matches[1] ?? '';
    $cleanNumber = preg_replace('/[^\d\s().-]/', '', $number) ?? $number;

    return trim($prefix . ' ' . trim($cleanNumber));
}

function initials(?string $firstName, ?string $lastName = null): string
{
    $first = trim((string) $firstName);
    $last = trim((string) $lastName);
    $letters = '';

    if ($first !== '') {
        $letters .= substr($first, 0, 1);
    }

    if ($last !== '') {
        $letters .= substr($last, 0, 1);
    }

    return strtoupper($letters !== '' ? $letters : 'S');
}

function enum_label(string $value, array $labels, ?string $default = null): string
{
    return $labels[$value] ?? $default ?? $value;
}

function status_label(?string $status): string
{
    return enum_label((string) $status, [
        'OPEN' => 'Abierto',
        'CONVERTED' => 'Cliente',
        'LOST' => 'Perdido',
        'PENDING' => 'Pendiente',
        'COMPLETED' => 'Completada',
        'RESOLVED' => 'Resuelta',
        'DISMISSED' => 'Descartada',
        'CANCELLED' => 'Cancelada',
        'SCHEDULED' => 'Programada',
        'reserved' => 'Reservada',
        'cancelled' => 'Cancelada',
        'attended' => 'Asisti?',
        'no_show' => 'No-show',
        'ACTIVE' => 'Activo',
        'INACTIVE' => 'Inactivo',
        'PAYMENT_PENDING' => 'Pago pendiente',
        'AT_RISK' => 'En riesgo',
    ]);
}

function role_label(?string $role): string
{
    $key = strtoupper(trim((string) $role));

    return enum_label($key, [
        'SUPER_ADMIN' => 'Superadmin',
        'SUPERADMIN' => 'Superadmin',
        'GYM_ADMIN' => 'Administrador',
        'ADMIN' => 'Administrador',
        'RECEPTION' => 'Recepción',
        'SALES' => 'Comercial',
        'SALES_RECEPTION' => 'Recepción / Comercial',
        'TRAINER' => 'Entrenador',
        'STAFF' => 'Equipo',
    ]);
}

function is_platform_admin(?array $user = null): bool
{
    $user = $user ?? Auth::user();
    if (($user['tenant_context'] ?? false) === true) {
        return false;
    }

    $role = strtoupper((string) ($user['role'] ?? ''));
    return in_array($role, ['SUPER_ADMIN', 'SUPERADMIN'], true);
}

function is_platform_support_context(): bool
{
    return (Auth::user()['tenant_context'] ?? false) === true && isset($_SESSION['platform_admin_user']);
}

function user_role_key(?array $user = null): string
{
    $user = $user ?? Auth::user();
    return strtoupper(trim((string) ($user['role'] ?? '')));
}

function is_gym_admin(?array $user = null): bool
{
    return in_array(user_role_key($user), ['GYM_ADMIN', 'ADMIN', 'SUPER_ADMIN', 'SUPERADMIN'], true);
}

function can_access_route(string $route, ?array $user = null): bool
{
    $user = $user ?? Auth::user();
    $role = user_role_key($user);

    if ($route === 'login') {
        return true;
    }

    if ($route === 'upgrade-plan') {
        return $user !== null && !is_platform_admin($user) && empty($user['tenant_context']);
    }

    if ($route === 'simulated-checkout') {
        return is_gym_admin($user) && !is_platform_admin($user) && empty($user['tenant_context']);
    }

    if (is_platform_admin($user)) {
        return str_starts_with($route, 'platform-') || in_array($route, ['profile', 'settings', 'novedades', 'global-search'], true);
    }

    if (is_gym_admin($user)) {
        return !str_starts_with($route, 'platform-');
    }

    $routesByRole = [
        'SALES_RECEPTION' => ['dashboard', 'leads', 'members', 'memberships', 'payments', 'payment-invoice', 'billing', 'client-invoice', 'checkins', 'classes', 'tasks', 'alerts', 'profile', 'settings', 'novedades', 'global-search'],
        'RECEPTION' => ['dashboard', 'leads', 'members', 'memberships', 'payments', 'payment-invoice', 'billing', 'client-invoice', 'checkins', 'classes', 'tasks', 'alerts', 'profile', 'settings', 'novedades', 'global-search'],
        'SALES' => ['dashboard', 'leads', 'members', 'memberships', 'payments', 'payment-invoice', 'billing', 'client-invoice', 'tasks', 'alerts', 'profile', 'settings', 'novedades', 'global-search'],
        'TRAINER' => ['dashboard', 'members', 'checkins', 'classes', 'tasks', 'profile', 'settings', 'novedades', 'global-search'],
        'STAFF' => ['dashboard', 'members', 'checkins', 'classes', 'tasks', 'profile', 'settings', 'novedades', 'global-search'],
    ];

    return in_array($route, $routesByRole[$role] ?? ['dashboard', 'profile', 'settings', 'novedades'], true);
}

function can_perform_action(string $action, ?array $user = null): bool
{
    $user = $user ?? Auth::user();
    $role = user_role_key($user);

    if (in_array($action, ['login', 'demo_login', 'keep_demo_session', 'schedule_demo_cleanup', 'request_password_reset', 'reset_password', 'confirm_trial_activation', 'reveal_trial_credentials', 'logout', 'update_profile'], true)) {
        return true;
    }

    if ($action === 'exit_empresa_crm' && is_platform_support_context()) {
        return true;
    }

    if (is_platform_admin($user)) {
        return str_contains($action, 'platform') || str_contains($action, 'empresa') || in_array($action, ['enter_empresa_crm', 'exit_empresa_crm'], true);
    }

    if (is_gym_admin($user)) {
        return !str_contains($action, 'platform') && !str_contains($action, 'empresa');
    }

    $actionsByRole = [
        'SALES_RECEPTION' => [
            'create_lead', 'update_lead', 'add_lead_note', 'update_lead_note', 'delete_lead_note', 'update_lead_stage', 'convert_lead', 'mark_lead_lost',
            'create_member', 'update_member',
            'renew_member_subscription', 'create_payment', 'update_payment', 'mark_payment_paid',
            'save_billing_integration', 'sync_billing_integration',
            'create_client_invoice', 'update_client_invoice', 'issue_client_invoice', 'add_client_invoice_payment',
            'create_checkin',
            'create_reservation', 'update_reservation_status',
            'create_task', 'update_task', 'update_task_status',
            'update_risk_alert_status',
        ],
        'RECEPTION' => [
            'create_lead', 'update_lead', 'add_lead_note', 'update_lead_note', 'delete_lead_note', 'update_lead_stage', 'convert_lead', 'mark_lead_lost',
            'create_member', 'update_member',
            'renew_member_subscription', 'create_payment', 'update_payment', 'mark_payment_paid',
            'save_billing_integration', 'sync_billing_integration',
            'create_client_invoice', 'update_client_invoice', 'issue_client_invoice', 'add_client_invoice_payment',
            'create_checkin',
            'create_reservation', 'update_reservation_status',
            'create_task', 'update_task', 'update_task_status',
            'update_risk_alert_status',
        ],
        'SALES' => [
            'create_lead', 'update_lead', 'add_lead_note', 'update_lead_note', 'delete_lead_note', 'update_lead_stage', 'convert_lead', 'mark_lead_lost',
            'create_member', 'update_member',
            'renew_member_subscription', 'create_payment', 'update_payment', 'mark_payment_paid',
            'save_billing_integration', 'sync_billing_integration',
            'create_client_invoice', 'update_client_invoice', 'issue_client_invoice', 'add_client_invoice_payment',
            'create_task', 'update_task', 'update_task_status',
            'update_risk_alert_status',
        ],
        'TRAINER' => [
            'create_checkin',
            'create_reservation', 'update_reservation_status',
            'create_task', 'update_task', 'update_task_status',
        ],
        'STAFF' => [
            'create_checkin',
            'create_reservation', 'update_reservation_status',
            'update_task_status',
        ],
    ];

    return in_array($action, $actionsByRole[$role] ?? [], true);
}

function empresa_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'ACTIVE' => 'Activo',
        'TRIAL' => 'Prueba',
        'SUSPENDED' => 'Suspendido',
        'CANCELLED' => 'Cancelado',
    ]);
}

function empresa_payment_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'PAID' => 'Al día',
        'PENDING' => 'Pendiente',
        'OVERDUE' => 'Vencido',
        'TRIAL' => 'Prueba',
    ]);
}

function empresa_renewal_period_label(?string $period): string
{
    return enum_label((string) $period, [
        'MONTHLY' => 'Mensual',
        'ANNUAL' => 'Anual',
    ]);
}

function empresa_renewal_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'ACTIVE' => 'Renovación activa',
        'CANCEL_AT_PERIOD_END' => 'Cancelada al final del periodo',
        'CANCELLED' => 'Cancelada',
    ]);
}

function platform_client_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'LEAD' => 'Lead',
        'QUALIFIED' => 'Cualificado',
        'CUSTOMER' => 'Cliente',
        'LOST' => 'Perdido',
    ]);
}

function platform_lead_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'NEW' => 'Nuevo',
        'CONTACTED' => 'Contactado',
        'QUALIFIED' => 'Cualificado',
        'CONVERTED' => 'Cliente',
        'LOST' => 'Perdido',
    ]);
}

function platform_payment_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'DRAFT' => 'Borrador',
        'PAID' => 'Pagado',
        'PENDING' => 'Pendiente',
        'OVERDUE' => 'Vencido',
        'CANCELLED' => 'Anulado',
    ]);
}

function trial_period_details(?string $startedAt, int $trialDays, ?DateTimeImmutable $today = null): array
{
    try {
        $started = new DateTimeImmutable(trim((string) $startedAt) ?: 'now');
    } catch (Throwable) {
        $started = new DateTimeImmutable('now');
    }

    $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
    $expiresAt = $started->setTime(0, 0)->modify('+' . max(1, min(365, $trialDays)) . ' days');
    $expired = $expiresAt <= $today;

    return [
        'expires_at' => $expiresAt->format('Y-m-d'),
        'remaining_days' => $expired ? 0 : (int) $today->diff($expiresAt)->format('%a'),
        'expired' => $expired,
    ];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $stored = $_SESSION['csrf_token'] ?? null;
    $submitted = post_value('csrf_token');
    return is_string($stored) && is_string($submitted) && hash_equals($stored, $submitted);
}

function inject_csrf_fields(string $html): string
{
    return preg_replace_callback(
        '/<form\b(?=[^>]*\bmethod\s*=\s*(["\'])?post\1)[^>]*>/i',
        static fn (array $match): string => $match[0] . csrf_field(),
        $html
    ) ?? $html;
}

function log_server_error(Throwable $exception, string $context): void
{
    error_log('[Membora CRM][' . $context . '] ' . $exception::class . ': ' . $exception->getMessage());
}

function platform_invoice_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'DRAFT' => 'Borrador',
        'ISSUED' => 'Emitida',
        'RECTIFIED' => 'Rectificada',
        'SENT' => 'Enviada',
        'PENDING' => 'Pendiente',
        'PARTIAL' => 'Parcial',
        'PAID' => 'Pagada',
        'OVERDUE' => 'Vencida',
        'REFUNDED' => 'Reembolsada',
        'CANCELLED' => 'Cancelada',
    ]);
}

function payment_method_label(?string $method): string
{
    return enum_label((string) $method, [
        'CASH' => 'Efectivo',
        'CARD' => 'Tarjeta',
        'STRIPE' => 'Stripe',
        'SIMULATED' => 'Tarjeta de prueba',
        'TRANSFER' => 'Transferencia',
        'TPV' => 'TPV',
        'DIRECT_DEBIT' => 'Domiciliacion',
        'BIZUM' => 'Bizum',
        'OTHER' => 'Otro',
    ]);
}

function billing_sync_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'PENDING' => 'Pendiente',
        'EXPORTED' => 'Exportado',
        'SYNCED' => 'Sincronizado',
        'ERROR' => 'Error',
        'SUCCESS' => 'Correcto',
        'ACTIVE' => 'Activa',
        'INACTIVE' => 'Inactiva',
    ]);
}

function billing_operation_label(?string $operation): string
{
    return enum_label((string) $operation, [
        'EXPORT' => 'Exportacion',
        'SYNC' => 'Sincronizacion',
    ]);
}

function checkin_method_label(?string $method): string
{
    return enum_label((string) $method, [
        'MANUAL' => 'Manual',
        'QR' => 'QR',
        'AUTOMATIC' => 'Automático',
    ]);
}

function risk_alert_type_label(?string $type): string
{
    return enum_label((string) $type, [
        'PAYMENT_OVERDUE' => 'Pago vencido',
        'TASK_OVERDUE' => 'Tarea vencida',
        'MEMBERSHIP_EXPIRED' => 'Membresía por renovar',
        'MEMBER_INACTIVE' => 'Socio sin actividad',
        'LEAD_STALE' => 'Lead sin seguimiento',
        'CLASS_FULL' => 'Clase llena',
        'PAYMENT_PENDING' => 'Pago pendiente',
        'INACTIVE_MEMBER' => 'Socio inactivo',
        'LEAD_WITHOUT_FOLLOW_UP' => 'Lead sin seguimiento',
        'OVERDUE_TASK' => 'Tarea vencida',
        'HIGH_CLASS_OCCUPANCY' => 'Clase con alta ocupacion',
    ]);
}

function risk_alert_severity_label(?string $severity): string
{
    return enum_label((string) $severity, [
        'HIGH' => 'Alta',
        'MEDIUM' => 'Media',
        'LOW' => 'Baja',
    ]);
}

function audit_action_label(?string $action): string
{
    $value = (string) $action;
    $labels = [
        'SEED_DEMO_TENANT' => 'Carga de datos demo',
        'login' => 'Inicio de sesión',
        'demo_login' => 'Inicio de demo',
        'logout' => 'Cierre de sesión',
        'update_profile' => 'Actualizacion de perfil',
        'create_user' => 'Crear usuario',
        'update_user' => 'Modificar usuario',
        'delete_user' => 'Eliminar usuario',
        'create_lead' => 'Creación de lead',
        'update_lead' => 'Actualizacion de lead',
        'add_lead_note' => 'Nota de lead',
        'update_lead_note' => 'Actualizacion de nota',
        'delete_lead_note' => 'Eliminación de nota',
        'update_lead_stage' => 'Cambio de etapa',
        'convert_lead' => 'Conversión de lead',
        'mark_lead_lost' => 'Lead perdido',
        'delete_lead' => 'Eliminación de lead',
        'create_member' => 'Crear socio',
        'update_member' => 'Modificar socio',
        'delete_member' => 'Eliminar socio',
        'renew_member_subscription' => 'Renovar membresía',
        'create_membership_plan' => 'Crear membresía',
        'update_membership_plan' => 'Modificar membresía',
        'delete_membership_plan' => 'Eliminar membresía',
        'create_payment' => 'Creación de pago',
        'update_payment' => 'Actualizacion de pago',
        'mark_payment_paid' => 'Pago marcado como cobrado',
        'generate_recurring_payments' => 'Generacion de borradores recurrentes',
        'delete_payment' => 'Eliminación de pago',
        'create_checkin' => 'Crear check-in',
        'delete_checkin' => 'Eliminar check-in',
        'update_risk_alert_status' => 'Cambiar alerta',
        'save_billing_integration' => 'Configuración de facturación',
        'sync_billing_integration' => 'Sincronizacion de facturación',
        'create_class_type' => 'Crear tipo de clase',
        'create_class_session' => 'Crear clase',
        'update_class_session' => 'Modificar clase',
        'delete_class_session' => 'Eliminar clase',
        'create_reservation' => 'Creación de reserva',
        'update_reservation_status' => 'Cambio de reserva',
        'create_task' => 'Crear tarea',
        'update_task' => 'Modificar tarea',
        'update_task_status' => 'Cambiar tarea',
        'delete_task' => 'Eliminar tarea',
        'view_audit' => 'Ver auditoría',
        'update_platform_lead' => 'Actualizacion de lead web',
        'convert_platform_lead' => 'Conversión de lead web',
        'delete_platform_lead' => 'Eliminación de lead web',
        'send_platform_test_email' => 'Prueba de email',
        'reset_platform_trial_attempts' => 'Reinicio de alta de prueba',
        'create_platform_client' => 'Creación de cliente CRM',
        'update_platform_client' => 'Actualizacion de cliente CRM',
        'delete_platform_client' => 'Eliminación de cliente CRM',
        'create_empresa' => 'Creación de empresa',
        'update_empresa' => 'Actualizacion de empresa',
        'delete_empresa' => 'Eliminación de empresa',
        'update_empresa_subscription' => 'Actualizacion de suscripción',
        'renew_empresa_subscription' => 'Renovación de suscripción',
        'cancel_empresa_subscription' => 'Cancelacion de suscripción',
        'resume_empresa_subscription' => 'Reactivacion de suscripción',
        'create_empresa_stripe_checkout' => 'Inicio de Stripe Checkout',
        'create_tenant_stripe_checkout' => 'Mejora de plan con Stripe Checkout',
        'open_tenant_simulated_checkout' => 'Inicio de checkout simulado',
        'complete_tenant_simulated_checkout' => 'Pago simulado de plan',
        'cancel_empresa_stripe_subscription' => 'Cancelacion de Stripe al final del periodo',
        'create_platform_payment' => 'Creación de pago CRM',
        'update_platform_payment' => 'Actualizacion de pago CRM',
        'create_platform_invoice' => 'Creación de factura CRM',
        'update_platform_invoice' => 'Actualizacion de factura CRM',
        'issue_platform_invoice' => 'Emision de factura CRM',
        'add_platform_invoice_payment' => 'Pago parcial de factura CRM',
        'create_platform_plan' => 'Creación de plan CRM',
        'update_platform_plan' => 'Actualizacion de plan CRM',
        'create_platform_user' => 'Creación de usuario administrador',
        'update_platform_user' => 'Actualización de usuario administrador',
        'delete_platform_user' => 'Eliminación de usuario administrador',
        'enter_empresa_crm' => 'Entrada en soporte',
        'exit_empresa_crm' => 'Salida de soporte',
    ];

    if (isset($labels[$value])) {
        return $labels[$value];
    }

    $readable = strtolower(str_replace(['_', '-'], ' ', $value));
    $readable = trim(preg_replace('/\s+/', ' ', $readable) ?: '');

    return $readable !== '' ? ucfirst($readable) : 'Actividad registrada';
}

function audit_entity_label(?string $entity): string
{
    $label = enum_label((string) $entity, [
        'user' => 'Usuario',
        'users' => 'Usuario',
        'member' => 'Socio',
        'members' => 'Socio',
        'membership_plan' => 'Membresía',
        'membership_plans' => 'Membresía',
        'checkin' => 'Check-in',
        'checkins' => 'Check-in',
        'class_type' => 'Tipo de clase',
        'class_session' => 'Clase',
        'class_sessions' => 'Clase',
        'task' => 'Tarea',
        'tasks' => 'Tarea',
        'risk_alert' => 'Alerta',
        'risk_alert_status' => 'Alerta',
        'empresa' => 'Empresa',
        'empresa_crm' => 'Empresa',
        'empresa_subscription' => 'Empresa',
        'platform_client' => 'Cliente CRM',
        'platform_lead' => 'Lead web',
        'platform_payment' => 'Pago CRM',
        'platform_invoice' => 'Factura CRM',
        'platform_plan' => 'Plan CRM',
        'audit' => 'Auditoría',
        'view_audit' => 'Auditoría',
        'lead_note' => 'Nota',
        'lead_stage' => 'Lead',
        'lead_lost' => 'Lead',
        'billing_integration' => 'Facturación',
        'reservation' => 'Reserva',
        'reservation_status' => 'Reserva',
        'task_status' => 'Tarea',
    ], 'General');

    return $label === '' || str_contains($label, '_') ? 'Actividad' : $label;
}

function audit_area_label(?string $route): string
{
    $label = enum_label((string) $route, [
        'users' => 'Usuarios',
        'members' => 'Socios',
        'memberships' => 'Membresías',
        'checkins' => 'Check-ins',
        'classes' => 'Clases',
        'tasks' => 'Tareas',
        'alerts' => 'Alertas',
        'audit' => 'Auditoría',
        'platform-audit' => 'Logs CRM',
        'platform-companies' => 'Empresas',
        'platform-contacts' => 'Contactos CRM',
        'platform-clients' => 'Contactos CRM',
        'platform-leads' => 'Contactos CRM',
        'platform-payments' => 'Pagos CRM',
        'platform-invoices' => 'Facturas CRM',
        'platform-invoice' => 'Factura CRM',
        'platform-plans' => 'Planes CRM',
        'platform-users' => 'Usuarios Admin',
        'platform-web' => 'Web comercial',
        'upgrade-plan' => 'Mejorar plan',
        'simulated-checkout' => 'Checkout de prueba',
        'dashboard' => 'Panel',
        'profile' => 'Perfil',
        'settings' => 'Configuración',
        'novedades' => 'Novedades',
    ], 'CRM');

    return $label === '' || str_contains($label, '_') || str_contains($label, '-') ? 'CRM' : $label;
}

function audit_metadata_summary(?string $metadata): string
{
    if (!$metadata) {
        return 'Sin detalles visibles';
    }

    $data = json_decode($metadata, true);
    if (!is_array($data)) {
        return 'Detalle interno oculto';
    }

    $blockedKeys = ['id', 'tenant_id', 'user_id', 'role_id', 'member_id', 'lead_id', 'task_id', 'payment_id', 'reservation_id', 'class_session_id', 'empresa_id', 'client_id', 'plan_id', 'form_token', 'csrf', 'token', 'password', 'password_hash', 'route', 'action', 'scope', 'filters', 'ip', 'ip_address', 'user_agent'];
    $labels = [
        'name' => 'Nombre',
        'first_name' => 'Nombre',
        'last_name' => 'Apellidos',
        'email' => 'Email',
        'phone' => 'Teléfono',
        'status' => 'Estado',
        'title' => 'Título',
        'type' => 'Tipo',
        'method' => 'Método',
        'notes' => 'Notas',
        'description' => 'Descripción',
        'due_at' => 'Fecha límite',
        'checked_in_at' => 'Fecha de check-in',
        'starts_at' => 'Inicio',
        'ends_at' => 'Fin',
        'capacity' => 'Aforo',
        'plan' => 'Plan',
        'payment_status' => 'Estado de pago',
        'contact_email' => 'Email de contacto',
        'admin_email' => 'Email administrador',
        'admin_name' => 'Administrador',
        'amount' => 'Importe',
        'monthly_price' => 'Precio mensual',
        'next_payment_at' => 'Próximo pago',
        'contact_name' => 'Contacto',
        'company_name' => 'Empresa',
    ];

    $parts = [];
    foreach ($data as $key => $value) {
        $normalizedKey = strtolower((string) $key);
        if (
            in_array($normalizedKey, $blockedKeys, true)
            || str_contains($normalizedKey, 'token')
            || str_contains($normalizedKey, 'password')
            || str_ends_with($normalizedKey, '_id')
        ) {
            continue;
        }

        if (is_array($value)) {
            continue;
        }

        $text = trim((string) $value);
        if ($text === '') {
            continue;
        }

        $label = $labels[$normalizedKey] ?? ucfirst(str_replace('_', ' ', $normalizedKey));
        if (str_contains($label, '_')) {
            continue;
        }

        $parts[] = $label . ': ' . $text;
        if (count($parts) >= 4) {
            break;
        }
    }

    return $parts ? implode(' · ', $parts) : 'Detalle interno oculto';
}

function platform_plan_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'ACTIVE' => 'Activo',
        'INACTIVE' => 'Inactivo',
        'ARCHIVED' => 'Archivado',
    ]);
}

function webhook_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'success' => 'Recibido',
        'duplicate' => 'Duplicado',
        'blocked' => 'Bloqueado',
        'email_test' => 'Prueba enviada',
        'trial_email' => 'Email de activación',
        'email_error' => 'Email no enviado',
        'error' => 'Error',
    ]);
}

function format_time(?string $value): string
{
    if (!$value) {
        return '--:--';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('H:i', $timestamp) : '--:--';
}

function stage_color_class(?string $value): string
{
    $stage = strtolower((string) $value);

    if (str_contains($stage, 'lost') || str_contains($stage, 'perdido')) {
        return 'lost';
    }

    if (str_contains($stage, 'convert') || str_contains($stage, 'socio')) {
        return 'converted';
    }

    if (str_contains($stage, 'trial') || str_contains($stage, 'prueba') || str_contains($stage, 'visit')) {
        return 'trial';
    }

    if (str_contains($stage, 'proposal') || str_contains($stage, 'propuesta') || str_contains($stage, 'alta')) {
        return 'proposal';
    }

    if (str_contains($stage, 'contact')) {
        return 'contacted';
    }

    if (str_contains($stage, 'new') || str_contains($stage, 'nuevo')) {
        return 'new';
    }

    return 'default';
}

function source_label(?string $source): string
{
    return enum_label((string) $source, [
        'WALK_IN' => 'Visita',
        'WEBSITE' => 'Web',
        'PHONE' => 'Teléfono',
        'SOCIAL_MEDIA' => 'Redes',
        'REFERRAL' => 'Recomendacion',
        'WEB' => 'Web',
        'LANDING' => 'Landing',
        'FORMULARIO_WEB' => 'Formulario web',
        'OTHER' => 'Otro',
    ]);
}

function task_type_label(?string $type): string
{
    return enum_label((string) $type, [
        'SALES' => 'Bienvenida / alta',
        'RETENTION' => 'Seguimiento de socio',
        'PAYMENT' => 'Cobro o renovación',
        'OPERATIONAL' => 'Operación interna',
        'OTHER' => 'Otra',
    ]);
}

function membership_period_label(?string $period): string
{
    return enum_label((string) $period, [
        'WEEKLY' => 'Semanal',
        'MONTHLY' => 'Mensual',
        'BIMONTHLY' => 'Bimestral',
        'QUARTERLY' => 'Trimestral',
        'YEARLY' => 'Anual',
    ]);
}

function membership_duration_days(?string $period): int
{
    return match ($period) {
        'WEEKLY' => 7,
        'BIMONTHLY' => 60,
        'QUARTERLY' => 90,
        'YEARLY' => 365,
        default => 30,
    };
}

function membership_end_date(?string $startDate, ?string $period): string
{
    $startDate = $startDate ?: date('Y-m-d');
    $date = new DateTimeImmutable($startDate);

    return match ($period) {
        'WEEKLY' => $date->modify('+7 days')->format('Y-m-d'),
        'BIMONTHLY' => $date->modify('+2 months')->format('Y-m-d'),
        'QUARTERLY' => $date->modify('+3 months')->format('Y-m-d'),
        'YEARLY' => $date->modify('+1 year')->format('Y-m-d'),
        default => $date->modify('+1 month')->format('Y-m-d'),
    };
}

function month_title(string $month): string
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01') ?: new DateTimeImmutable('first day of this month');
    $months = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    return ucfirst($months[(int) $date->format('n')] ?? '') . ' ' . $date->format('Y');
}
