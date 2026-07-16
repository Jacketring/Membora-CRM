<?php

declare(strict_types=1);

final class TrialRegistrationRepository
{
    private const TRIAL_DAYS = 14;

    public static function validationErrors(array $payload): array
    {
        $errors = [];
        $name = trim((string) ($payload['nombre'] ?? ''));
        $company = trim((string) ($payload['empresa'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));

        if ($name === '' || mb_strlen($name) > 160) {
            $errors[] = 'Indica tu nombre.';
        }
        if ($company === '' || mb_strlen($company) > 191) {
            $errors[] = 'Indica el nombre de tu gimnasio.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            $errors[] = 'Indica un email válido.';
        }
        if ((string) ($payload['acepta_rgpd'] ?? '') !== '1') {
            $errors[] = 'Debes aceptar la política de privacidad.';
        }

        return $errors;
    }

    public static function provisioningData(array $registration, string $clientId, string $adminPassword): array
    {
        return [
            'client_id' => $clientId,
            'name' => (string) ($registration['company_name'] ?? ''),
            'contact_email' => (string) ($registration['email'] ?? ''),
            'plan' => 'TRIAL',
            'status' => 'TRIAL',
            'payment_status' => 'TRIAL',
            'monthly_price' => '0',
            'trial_days' => (string) self::TRIAL_DAYS,
            'subscription_started_at' => date('Y-m-d'),
            'renewal_period' => 'MONTHLY',
            'renewal_status' => 'ACTIVE',
            'notes' => 'Alta self-service desde membora.es.',
            'create_tenant' => '1',
            'admin_name' => (string) ($registration['name'] ?? ''),
            'admin_email' => (string) ($registration['email'] ?? ''),
            'admin_password' => $adminPassword,
        ];
    }

    public static function request(array $payload): array
    {
        if (trim((string) ($payload['website'] ?? '')) !== '') {
            return ['success' => true, 'message' => 'Revisa tu correo para continuar.'];
        }

        $errors = self::validationErrors($payload);
        if ($errors !== []) {
            return ['success' => false, 'message' => $errors[0]];
        }

        self::ensureTable();
        self::deleteExpired();

        $name = trim((string) $payload['nombre']);
        $company = trim((string) $payload['empresa']);
        $deliveryEmail = strtolower(trim((string) $payload['email']));
        $ipHash = hash('sha256', substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64));

        if (self::isRateLimited($ipHash, $deliveryEmail)) {
            return ['success' => false, 'message' => 'Demasiadas solicitudes. Inténtalo de nuevo dentro de una hora.'];
        }

        $accountEmail = self::availableAccountEmail($deliveryEmail);

        $token = bin2hex(random_bytes(32));
        $id = cuid();
        $stmt = Database::connection()->prepare(
            'INSERT INTO trial_registrations
             (id, name, company_name, email, delivery_email, token_hash, ip_hash, status, expires_at, created_at)
             VALUES (:id, :name, :company_name, :email, :delivery_email, :token_hash, :ip_hash, "PENDING", DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'company_name' => $company,
            'email' => $accountEmail,
            'delivery_email' => $deliveryEmail,
            'token_hash' => hash('sha256', $token),
            'ip_hash' => $ipHash,
        ]);

        $activationUrl = self::publicAppUrl() . '/index.php?route=activate-trial&token=' . urlencode($token);
        if (!Mailer::sendTrialActivation($deliveryEmail, $name, $company, $activationUrl, $accountEmail)) {
            Database::connection()->prepare('DELETE FROM trial_registrations WHERE id = :id')->execute(['id' => $id]);
            $error = Mailer::lastError();
            log_server_error(new RuntimeException($error), 'trial_activation_email');
            self::logEmailDiagnostic(
                'email_error',
                'Correo de activación de prueba: ' . $error,
                $deliveryEmail
            );
            return ['success' => false, 'message' => 'No se pudo enviar el correo de activación. Inténtalo más tarde.'];
        }

        self::logEmailDiagnostic(
            'trial_email',
            'Correo de activación de prueba aceptado por el transporte de envío.',
            $deliveryEmail
        );

        return ['success' => true, 'message' => 'Revisa tu correo para activar la prueba.'];
    }

    public static function activate(string $token): string
    {
        self::ensureTable();
        $token = self::normalizeActivationToken($token);

        $stmt = Database::connection()->prepare(
            'SELECT * FROM trial_registrations
             WHERE token_hash = :token_hash AND status = "PENDING" AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
        $registration = $stmt->fetch();
        if (!$registration) {
            throw new RuntimeException('El enlace de activación ha caducado o ya se ha utilizado.');
        }

        $claim = Database::connection()->prepare(
            'UPDATE trial_registrations SET status = "ACTIVATING"
             WHERE id = :id AND status = "PENDING"'
        );
        $claim->execute(['id' => $registration['id']]);
        if ($claim->rowCount() !== 1) {
            throw new RuntimeException('Esta prueba ya se está activando.');
        }

        $newClient = PlatformClientRepository::findByEmail((string) $registration['email']) === null;
        $clientId = '';
        $empresaCreated = false;

        try {
            $clientId = PlatformClientRepository::upsertTrialCustomer(
                (string) $registration['company_name'],
                (string) $registration['name'],
                (string) $registration['email']
            );

            EmpresaRepository::create(self::provisioningData(
                $registration,
                $clientId,
                bin2hex(random_bytes(24))
            ));
            $empresaCreated = true;

            $userStmt = Database::connection()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $userStmt->execute(['email' => $registration['email']]);
            $userId = (string) $userStmt->fetchColumn();
            if ($userId === '') {
                throw new RuntimeException('No se pudo localizar el usuario de la prueba.');
            }

            $resetToken = AuthTokenRepository::issuePasswordReset($userId);
            if ($resetToken === null) {
                throw new RuntimeException('No se pudo preparar la contraseña de acceso.');
            }

            Database::connection()->prepare(
                'UPDATE trial_registrations SET status = "ACTIVATED", activated_at = NOW() WHERE id = :id'
            )->execute(['id' => $registration['id']]);

            return $resetToken;
        } catch (Throwable $exception) {
            if ($newClient && !$empresaCreated && $clientId !== '') {
                try {
                    PlatformClientRepository::delete($clientId);
                } catch (Throwable $cleanupException) {
                    log_server_error($cleanupException, 'trial_client_cleanup');
                }
            }
            Database::connection()->prepare(
                'UPDATE trial_registrations SET status = "FAILED" WHERE id = :id'
            )->execute(['id' => $registration['id']]);
            throw $exception;
        }
    }

    public static function validateActivationToken(string $token): void
    {
        self::ensureTable();
        $token = self::normalizeActivationToken($token);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM trial_registrations
             WHERE token_hash = :token_hash AND status = "PENDING" AND expires_at > NOW()'
        );
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('El enlace de activación ha caducado o ya se ha utilizado.');
        }
    }

    public static function resetAttempts(string $email): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Indica un email válido.');
        }

        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'DELETE FROM trial_registrations
             WHERE (email = :account_email OR delivery_email = :delivery_email)
               AND status IN ("PENDING", "FAILED", "ACTIVATING")'
        );
        $stmt->execute([
            'account_email' => $email,
            'delivery_email' => $email,
        ]);

        return $stmt->rowCount();
    }

    private static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS trial_registrations (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                name VARCHAR(160) NOT NULL,
                company_name VARCHAR(191) NOT NULL,
                email VARCHAR(191) NOT NULL,
                delivery_email VARCHAR(191) NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                ip_hash CHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT "PENDING",
                expires_at DATETIME NOT NULL,
                activated_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX trial_registration_ip_idx (ip_hash, created_at),
                INDEX trial_registration_email_idx (email, created_at),
                INDEX trial_registration_status_idx (status, expires_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        $column = Database::connection()->query("SHOW COLUMNS FROM trial_registrations LIKE 'delivery_email'")->fetch();
        if (!$column) {
            Database::connection()->exec('ALTER TABLE trial_registrations ADD COLUMN delivery_email VARCHAR(191) NULL AFTER email');
        }
        Database::connection()->exec('UPDATE trial_registrations SET delivery_email = email WHERE delivery_email IS NULL');
    }

    private static function normalizeActivationToken(string $token): string
    {
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new RuntimeException('El enlace de activación no es válido.');
        }

        return $token;
    }

    private static function availableAccountEmail(string $deliveryEmail): string
    {
        $exists = Database::connection()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $exists->execute(['email' => $deliveryEmail]);
        if ((int) $exists->fetchColumn() === 0) {
            return $deliveryEmail;
        }

        [$local, $domain] = array_pad(explode('@', $deliveryEmail, 2), 2, '');
        $baseLocal = explode('+', $local, 2)[0];
        do {
            $suffix = '+trial-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
            $maxBaseLength = max(1, 191 - strlen($domain) - strlen($suffix) - 1);
            $candidate = substr($baseLocal, 0, $maxBaseLength) . $suffix . '@' . $domain;
            $exists->execute(['email' => $candidate]);
        } while ((int) $exists->fetchColumn() > 0);

        return $candidate;
    }

    public static function publicAppUrl(): string
    {
        $configured = rtrim(trim((string) getenv('TRIAL_PUBLIC_URL')), '/');
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
            return $configured;
        }

        $webUrls = explode(',', (string) (getenv('WEB_APP_URL') ?: 'https://membora.es'));
        $webOrigin = 'https://membora.es';
        foreach ($webUrls as $candidate) {
            $candidate = rtrim(trim($candidate), '/');
            $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
            if (in_array($host, ['membora.es', 'www.membora.es'], true)) {
                $webOrigin = 'https://membora.es';
                break;
            }
        }
        $appPath = '/' . trim((string) (getenv('MEMBORA_APP_PATH') ?: '/app'), '/');

        return $webOrigin . $appPath;
    }

    private static function logEmailDiagnostic(string $status, string $message, string $email): void
    {
        try {
            WebhookIntegrationRepository::logPlatformEmailDiagnostic($status, $message, $email);
        } catch (Throwable $exception) {
            log_server_error($exception, 'trial_email_diagnostic');
        }
    }

    private static function isRateLimited(string $ipHash, string $email): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                SUM(ip_hash = :ip_hash) AS ip_attempts,
                SUM(COALESCE(delivery_email, email) = :email) AS email_attempts
             FROM trial_registrations
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $stmt->execute(['ip_hash' => $ipHash, 'email' => $email]);
        $attempts = $stmt->fetch() ?: [];

        return (int) ($attempts['ip_attempts'] ?? 0) >= 3 || (int) ($attempts['email_attempts'] ?? 0) >= 2;
    }

    private static function deleteExpired(): void
    {
        Database::connection()->exec(
            'DELETE FROM trial_registrations
             WHERE (status = "PENDING" AND expires_at <= NOW())
                OR created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
    }
}
