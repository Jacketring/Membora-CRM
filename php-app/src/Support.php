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

function country_dial_codes(): array
{
    return [
        ['country' => 'Espana', 'flag' => '🇪🇸', 'code' => '+34'],
        ['country' => 'Portugal', 'flag' => '🇵🇹', 'code' => '+351'],
        ['country' => 'Francia', 'flag' => '🇫🇷', 'code' => '+33'],
        ['country' => 'Italia', 'flag' => '🇮🇹', 'code' => '+39'],
        ['country' => 'Alemania', 'flag' => '🇩🇪', 'code' => '+49'],
        ['country' => 'Reino Unido', 'flag' => '🇬🇧', 'code' => '+44'],
        ['country' => 'Irlanda', 'flag' => '🇮🇪', 'code' => '+353'],
        ['country' => 'Paises Bajos', 'flag' => '🇳🇱', 'code' => '+31'],
        ['country' => 'Belgica', 'flag' => '🇧🇪', 'code' => '+32'],
        ['country' => 'Suiza', 'flag' => '🇨🇭', 'code' => '+41'],
        ['country' => 'Austria', 'flag' => '🇦🇹', 'code' => '+43'],
        ['country' => 'Dinamarca', 'flag' => '🇩🇰', 'code' => '+45'],
        ['country' => 'Suecia', 'flag' => '🇸🇪', 'code' => '+46'],
        ['country' => 'Noruega', 'flag' => '🇳🇴', 'code' => '+47'],
        ['country' => 'Finlandia', 'flag' => '🇫🇮', 'code' => '+358'],
        ['country' => 'Polonia', 'flag' => '🇵🇱', 'code' => '+48'],
        ['country' => 'Rumania', 'flag' => '🇷🇴', 'code' => '+40'],
        ['country' => 'Marruecos', 'flag' => '🇲🇦', 'code' => '+212'],
        ['country' => 'Estados Unidos', 'flag' => '🇺🇸', 'code' => '+1'],
        ['country' => 'Canada', 'flag' => '🇨🇦', 'code' => '+1'],
        ['country' => 'Mexico', 'flag' => '🇲🇽', 'code' => '+52'],
        ['country' => 'Argentina', 'flag' => '🇦🇷', 'code' => '+54'],
        ['country' => 'Chile', 'flag' => '🇨🇱', 'code' => '+56'],
        ['country' => 'Colombia', 'flag' => '🇨🇴', 'code' => '+57'],
        ['country' => 'Peru', 'flag' => '🇵🇪', 'code' => '+51'],
        ['country' => 'Ecuador', 'flag' => '🇪🇨', 'code' => '+593'],
        ['country' => 'Venezuela', 'flag' => '🇻🇪', 'code' => '+58'],
        ['country' => 'Uruguay', 'flag' => '🇺🇾', 'code' => '+598'],
        ['country' => 'Paraguay', 'flag' => '🇵🇾', 'code' => '+595'],
        ['country' => 'Brasil', 'flag' => '🇧🇷', 'code' => '+55'],
        ['country' => 'China', 'flag' => '🇨🇳', 'code' => '+86'],
        ['country' => 'Japon', 'flag' => '🇯🇵', 'code' => '+81'],
        ['country' => 'Corea del Sur', 'flag' => '🇰🇷', 'code' => '+82'],
        ['country' => 'India', 'flag' => '🇮🇳', 'code' => '+91'],
        ['country' => 'Australia', 'flag' => '🇦🇺', 'code' => '+61'],
    ];
}

function country_dial_options(): array
{
    $options = [];
    foreach (country_dial_codes() as $entry) {
        $options[] = $entry['flag'] . ' ' . $entry['code'] . ' ' . $entry['country'];
    }

    return $options;
}

function phone_country_value(?string $phone): string
{
    $phone = trim((string) $phone);
    if ($phone === '') {
        return '🇪🇸 +34 Espana';
    }

    $codes = country_dial_codes();
    usort($codes, fn (array $a, array $b): int => strlen($b['code']) <=> strlen($a['code']));

    foreach ($codes as $entry) {
        $code = $entry['code'];
        if (str_starts_with($phone, $code)) {
            return $entry['flag'] . ' ' . $code . ' ' . $entry['country'];
        }
    }

    if (preg_match('/^(\+\d{1,4})/', $phone, $matches)) {
        return $matches[1];
    }

    return '🇪🇸 +34 Espana';
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
    ]);
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
