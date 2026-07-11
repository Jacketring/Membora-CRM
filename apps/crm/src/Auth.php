<?php

final class Auth
{
    private static bool $lastAttemptRateLimited = false;

    public static function lastAttemptWasRateLimited(): bool
    {
        return self::$lastAttemptRateLimited;
    }
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function tenantId(): string
    {
        $tenantId = self::user()['tenant_id'] ?? null;
        if (!$tenantId) {
            self::logout();
            flash('No hay ningun centro configurado para este usuario.', 'error');
            redirect('login');
        }

        return $tenantId;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            redirect('login');
        }

        return $user;
    }

    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        self::$lastAttemptRateLimited = false;
        self::ensureLoginAttemptsTable($pdo);
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
        $emailKey = hash('sha256', strtolower(trim($email)));
        if (self::tooManyLoginAttempts($pdo, $ip, $emailKey)) {
            self::$lastAttemptRateLimited = true;
            return false;
        }
        UserRepository::ensureAvatarColumn();
        TenantRepository::ensureSettingsColumns();
        EmpresaRepository::ensureTables();
        EmpresaRepository::ensurePlatformAdmin();
        $stmt = $pdo->prepare(
            'SELECT users.*, tenants.name AS tenant_name, tenants.primary_color AS tenant_primary_color, roles.key AS role_key
             FROM users
             LEFT JOIN tenants ON tenants.id = users.tenant_id
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'ACTIVE' || !password_verify($password, $user['password_hash'])) {
            self::recordFailedLogin($pdo, $ip, $emailKey);
            return false;
        }

        self::clearLoginAttempts($pdo, $ip, $emailKey);

        session_regenerate_id(true);

        $isPlatformAdmin = in_array(strtoupper((string) $user['role_key']), ['SUPER_ADMIN', 'SUPERADMIN'], true);

        if ($isPlatformAdmin) {
            $user['tenant_name'] = 'Membora CRM';
            $user['tenant_primary_color'] = '#004bf2';
        } elseif (!$user['tenant_id']) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'tenant_name' => $user['tenant_name'],
            'tenant_primary_color' => $user['tenant_primary_color'] ?: '#004bf2',
            'name' => $user['name'],
            'email' => $user['email'],
            'avatar_path' => $user['avatar_path'] ?? null,
            'role' => $user['role_key'],
        ];

        $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => $user['id']]);

        return true;
    }

    public static function attemptDemo(string $type): bool
    {
        if ($type === 'admin') {
            DemoRepository::prepareAdminDemo();
            $password = (string) (getenv('PLATFORM_ADMIN_PASSWORD') ?: '');
            $success = $password !== '' && self::attempt(EmpresaRepository::PLATFORM_ADMIN_EMAIL, $password);
            if ($success) {
                self::markDemoSession('admin');
            }

            return $success;
        }

        DemoRepository::prepareClientDemo();
        $success = self::attempt(DemoRepository::CLIENT_EMAIL, DemoRepository::CLIENT_PASSWORD);
        if ($success) {
            self::markDemoSession('client');
        }

        return $success;
    }

    public static function enforceDemoExpiry(): void
    {
        if (!self::user() || empty($_SESSION['demo_expires_at'])) {
            return;
        }

        if ((int) $_SESSION['demo_expires_at'] > time()) {
            return;
        }

        self::logout();
        header('Location: ' . self::demoReturnUrl());
        exit;
    }

    public static function demoRemainingSeconds(): int
    {
        if (empty($_SESSION['demo_expires_at'])) {
            return 0;
        }

        return max(0, (int) $_SESSION['demo_expires_at'] - time());
    }

    public static function isDemoSession(): bool
    {
        return self::demoRemainingSeconds() > 0;
    }

    public static function demoReturnUrl(): string
    {
        return rtrim((string) (getenv('WEB_APP_URL') ?: 'https://app.web.josehurtado.dev'), '/') . '/';
    }

    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['platform_admin_user']);
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }
    }

    public static function enterTenantContext(array $empresa): void
    {
        if (empty($empresa['tenant_id'])) {
            flash('Esta empresa no tiene un CRM conectado todavia.', 'error');
            redirect('platform-dashboard');
        }

        if (!isset($_SESSION['platform_admin_user'])) {
            $_SESSION['platform_admin_user'] = self::requireUser();
        }

        TenantRepository::ensureSettingsColumns();
        $stmt = Database::connection()->prepare('SELECT id, name, primary_color FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $empresa['tenant_id']]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            flash('No se encontro el CRM conectado a esta empresa.', 'error');
            redirect('platform-dashboard');
        }

        $_SESSION['user']['tenant_id'] = $tenant['id'];
        $_SESSION['user']['tenant_name'] = $tenant['name'];
        $_SESSION['user']['tenant_primary_color'] = $tenant['primary_color'] ?? '#004bf2';
        $_SESSION['user']['tenant_context'] = true;
        $_SESSION['user']['support_company_name'] = $empresa['name'];
    }

    public static function exitTenantContext(): void
    {
        if (isset($_SESSION['platform_admin_user'])) {
            $_SESSION['user'] = $_SESSION['platform_admin_user'];
            unset($_SESSION['platform_admin_user']);
        }
    }

    private static function ensureLoginAttemptsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(64) NOT NULL,
            email_hash CHAR(64) NOT NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX login_attempts_ip_time_idx (ip_address, attempted_at),
            INDEX login_attempts_email_time_idx (email_hash, attempted_at)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private static function tooManyLoginAttempts(PDO $pdo, string $ip, string $emailHash): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND (ip_address = :ip OR email_hash = :email_hash)');
        $stmt->execute(['ip' => $ip, 'email_hash' => $emailHash]);
        return (int) $stmt->fetchColumn() >= 5;
    }

    private static function recordFailedLogin(PDO $pdo, string $ip, string $emailHash): void
    {
        $pdo->prepare('INSERT INTO login_attempts (ip_address, email_hash, attempted_at) VALUES (:ip, :email_hash, NOW())')
            ->execute(['ip' => $ip, 'email_hash' => $emailHash]);
    }

    private static function clearLoginAttempts(PDO $pdo, string $ip, string $emailHash): void
    {
        $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = :ip OR email_hash = :email_hash')
            ->execute(['ip' => $ip, 'email_hash' => $emailHash]);
    }

    private static function markDemoSession(string $type): void
    {
        $_SESSION['demo_type'] = $type;
        $_SESSION['demo_expires_at'] = time() + 20 * 60;
    }
}
