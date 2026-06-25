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

function hex_color_or_default(?string $value, string $default = '#0754d6'): string
{
    $value = trim((string) $value);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $default;
}

function country_dial_codes(): array
{
    return [
        ['country' => 'Espana', 'iso' => 'es', 'code' => '+34'],
        ['country' => 'Portugal', 'iso' => 'pt', 'code' => '+351'],
        ['country' => 'Francia', 'iso' => 'fr', 'code' => '+33'],
        ['country' => 'Italia', 'iso' => 'it', 'code' => '+39'],
        ['country' => 'Alemania', 'iso' => 'de', 'code' => '+49'],
        ['country' => 'Reino Unido', 'iso' => 'gb', 'code' => '+44'],
        ['country' => 'Irlanda', 'iso' => 'ie', 'code' => '+353'],
        ['country' => 'Paises Bajos', 'iso' => 'nl', 'code' => '+31'],
        ['country' => 'Belgica', 'iso' => 'be', 'code' => '+32'],
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

function enum_label(string $value, array $labels): string
{
    return $labels[$value] ?? $value;
}

function status_label(?string $status): string
{
    return enum_label((string) $status, [
        'OPEN' => 'Abierto',
        'CONVERTED' => 'Convertido',
        'LOST' => 'Perdido',
        'PENDING' => 'Pendiente',
        'COMPLETED' => 'Completada',
        'CANCELLED' => 'Cancelada',
        'SCHEDULED' => 'Programada',
        'ACTIVE' => 'Activo',
        'INACTIVE' => 'Inactivo',
        'PAYMENT_PENDING' => 'Activo',
        'AT_RISK' => 'Activo',
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
        'RECEPTION' => 'Recepcion',
        'SALES' => 'Comercial',
        'SALES_RECEPTION' => 'Recepcion / Comercial',
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
        'PAID' => 'Al dia',
        'PENDING' => 'Pendiente',
        'OVERDUE' => 'Vencido',
        'TRIAL' => 'Prueba',
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

function platform_payment_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'PAID' => 'Pagado',
        'PENDING' => 'Pendiente',
        'OVERDUE' => 'Vencido',
        'CANCELLED' => 'Cancelado',
    ]);
}

function platform_plan_status_label(?string $status): string
{
    return enum_label((string) $status, [
        'ACTIVE' => 'Activo',
        'INACTIVE' => 'Inactivo',
        'ARCHIVED' => 'Archivado',
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
        'PHONE' => 'Telefono',
        'SOCIAL_MEDIA' => 'Redes',
        'REFERRAL' => 'Recomendacion',
        'OTHER' => 'Otro',
    ]);
}

function task_type_label(?string $type): string
{
    return enum_label((string) $type, [
        'SALES' => 'Comercial',
        'RETENTION' => 'Retencion',
        'PAYMENT' => 'Pago',
        'OPERATIONAL' => 'Operativa',
        'OTHER' => 'Otra',
    ]);
}

function membership_period_label(?string $period): string
{
    return enum_label((string) $period, [
        'WEEKLY' => 'Semanal',
        'MONTHLY' => 'Mensual',
        'YEARLY' => 'Anual',
    ]);
}

function membership_duration_days(?string $period): int
{
    return match ($period) {
        'WEEKLY' => 7,
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
