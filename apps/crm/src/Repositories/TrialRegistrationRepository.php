<?php

declare(strict_types=1);

final class TrialRegistrationRepository
{
    private const TRIAL_DAYS = 14;
    public const CREDENTIAL_EMAIL_FAILED = 'La cuenta está preparada, pero no se pudo enviar el correo de acceso.';

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
        $deliveryEmail = (string) ($registration['delivery_email'] ?? $registration['email'] ?? '');
        return [
            'client_id' => $clientId,
            'name' => (string) ($registration['company_name'] ?? ''),
            'contact_email' => $deliveryEmail,
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

    public static function generateInitialPassword(): string
    {
        return 'Mb-' . implode('-', str_split(bin2hex(random_bytes(9)), 6));
    }

    public static function trialRateLimitEnabled(): bool
    {
        return filter_var(
            (string) (getenv('TRIAL_RATE_LIMIT_ENABLED') ?: 'false'),
            FILTER_VALIDATE_BOOL
        );
    }

    public static function activationStatusCanBeRetried(string $status): bool
    {
        return in_array($status, ['PENDING', 'PROVISION_FAILED', 'PROVISIONED', 'EMAIL_FAILED'], true);
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

        if (self::trialRateLimitEnabled() && self::isRateLimited($ipHash, $deliveryEmail)) {
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

    public static function activate(string $token): void
    {
        self::ensureTable();
        $token = self::normalizeActivationToken($token);

        $stmt = Database::connection()->prepare(
            'SELECT * FROM trial_registrations
             WHERE token_hash = :token_hash
               AND status IN ("PENDING", "PROVISION_FAILED", "PROVISIONED", "EMAIL_FAILED")
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
        $registration = $stmt->fetch();
        if (!$registration) {
            throw new RuntimeException('El enlace de activación ha caducado o ya se ha utilizado.');
        }

        $previousStatus = (string) $registration['status'];
        $claim = Database::connection()->prepare(
            'UPDATE trial_registrations SET status = "ACTIVATING"
             WHERE id = :id AND status = :previous_status'
        );
        $claim->execute([
            'id' => $registration['id'],
            'previous_status' => $previousStatus,
        ]);
        if ($claim->rowCount() !== 1) {
            throw new RuntimeException('Esta prueba ya se está activando.');
        }

        try {
            $account = self::provisionAccount($registration);
            $credentialToken = self::prepareCredentialToken($registration, $account);
            $deliveryEmail = (string) ($registration['delivery_email'] ?: $registration['email']);
            $credentialUrl = self::publicAppUrl() . '/index.php?route=trial-credentials&token=' . urlencode($credentialToken);
            if (!Mailer::sendTrialCredentials(
                $deliveryEmail,
                (string) $registration['name'],
                (string) $registration['company_name'],
                (string) $registration['email'],
                $credentialUrl
            )) {
                self::logEmailDiagnostic(
                    'email_error',
                    'Correo de credenciales de prueba: ' . Mailer::lastError(),
                    $deliveryEmail
                );
                self::setActivationState((string) $registration['id'], 'EMAIL_FAILED');
                throw new RuntimeException(self::CREDENTIAL_EMAIL_FAILED);
            }
            self::logEmailDiagnostic(
                'trial_email',
                'Correo con enlace de credenciales de un solo uso aceptado por el transporte de envío.',
                $deliveryEmail
            );

            self::setActivationState((string) $registration['id'], 'ACTIVATED', true);
        } catch (Throwable $exception) {
            if ($exception->getMessage() === self::CREDENTIAL_EMAIL_FAILED) {
                throw $exception;
            }
            self::setActivationState((string) $registration['id'], 'PROVISION_FAILED');
            throw $exception;
        }
    }

    public static function validateActivationToken(string $token): void
    {
        self::ensureTable();
        $token = self::normalizeActivationToken($token);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM trial_registrations
             WHERE token_hash = :token_hash
               AND status IN ("PENDING", "PROVISION_FAILED", "PROVISIONED", "EMAIL_FAILED")
               AND expires_at > NOW()'
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
               AND status IN ("PENDING", "FAILED", "PROVISION_FAILED", "PROVISIONED", "EMAIL_FAILED", "ACTIVATING")'
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
        foreach ([
            'client_id' => 'ALTER TABLE trial_registrations ADD COLUMN client_id VARCHAR(191) NULL AFTER delivery_email',
            'empresa_id' => 'ALTER TABLE trial_registrations ADD COLUMN empresa_id VARCHAR(191) NULL AFTER client_id',
            'tenant_id' => 'ALTER TABLE trial_registrations ADD COLUMN tenant_id VARCHAR(191) NULL AFTER empresa_id',
            'user_id' => 'ALTER TABLE trial_registrations ADD COLUMN user_id VARCHAR(191) NULL AFTER tenant_id',
        ] as $name => $sql) {
            $referenceColumn = Database::connection()->query(
                'SHOW COLUMNS FROM trial_registrations LIKE ' . Database::connection()->quote($name)
            )->fetch();
            if (!$referenceColumn) {
                Database::connection()->exec($sql);
            }
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

    private static function provisionAccount(array $registration): array
    {
        $deliveryEmail = (string) ($registration['delivery_email'] ?: $registration['email']);
        $clientId = trim((string) ($registration['client_id'] ?? ''));
        $client = $clientId !== '' ? PlatformClientRepository::find($clientId) : null;
        if (!$client) {
            $client = PlatformClientRepository::findByEmail($deliveryEmail);
            $clientId = (string) ($client['id'] ?? '');
        }
        if ($clientId === '') {
            $clientId = PlatformClientRepository::upsertTrialCustomer(
                (string) $registration['company_name'],
                (string) $registration['name'],
                $deliveryEmail
            );
        } else {
            PlatformClientRepository::markCustomer($clientId);
        }
        self::persistAccountReferences((string) $registration['id'], $clientId);

        $empresaId = trim((string) ($registration['empresa_id'] ?? ''));
        $empresa = $empresaId !== '' ? EmpresaRepository::find($empresaId) : null;
        if (!$empresa) {
            $empresa = self::findPreparedEmpresa($registration, $clientId);
            $empresaId = (string) ($empresa['id'] ?? '');
        }
        if ($empresaId === '') {
            $empresaId = EmpresaRepository::create(self::provisioningData(
                $registration,
                $clientId,
                self::generateInitialPassword()
            ));
            $empresa = EmpresaRepository::find($empresaId);
        }

        $tenantId = trim((string) ($empresa['tenant_id'] ?? ''));
        if (!$empresa || $tenantId === '' || !hash_equals($clientId, (string) ($empresa['client_id'] ?? ''))) {
            throw new RuntimeException('No se pudo preparar la empresa y su espacio en Membora.');
        }

        $userId = trim((string) ($registration['user_id'] ?? ''));
        $user = $userId !== '' ? self::findPreparedUser($tenantId, (string) $registration['email'], $userId) : null;
        if (!$user) {
            $user = self::findPreparedUser($tenantId, (string) $registration['email']);
            $userId = (string) ($user['id'] ?? '');
        }
        if ($userId === '') {
            $userId = EmpresaRepository::ensureTenantAdminUser(
                $tenantId,
                (string) $registration['name'],
                (string) $registration['email'],
                self::generateInitialPassword()
            );
        }

        self::persistAccountReferences(
            (string) $registration['id'],
            $clientId,
            $empresaId,
            $tenantId,
            $userId,
            'PROVISIONED'
        );

        return compact('clientId', 'empresaId', 'tenantId', 'userId');
    }

    private static function prepareCredentialToken(array $registration, array $account): string
    {
        $userId = (string) $account['userId'];
        $credentialToken = TrialCredentialRepository::rotateTokenForUser($userId);
        if ($credentialToken) {
            return $credentialToken;
        }

        $initialPassword = self::generateInitialPassword();
        EmpresaRepository::ensureTenantAdminUser(
            (string) $account['tenantId'],
            (string) $registration['name'],
            (string) $registration['email'],
            $initialPassword
        );

        return TrialCredentialRepository::issue(
            $userId,
            (string) $registration['email'],
            (string) $registration['company_name'],
            $initialPassword
        );
    }

    private static function findPreparedEmpresa(array $registration, string $clientId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT empresas.* FROM empresas
             INNER JOIN users ON users.tenant_id = empresas.tenant_id
             WHERE empresas.client_id = :client_id AND users.email = :email
             ORDER BY empresas.created_at DESC LIMIT 1'
        );
        $stmt->execute([
            'client_id' => $clientId,
            'email' => (string) $registration['email'],
        ]);
        $empresa = $stmt->fetch();
        if ($empresa) {
            return $empresa;
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM empresas
             WHERE client_id = :client_id AND name = :name AND status = "TRIAL"
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([
            'client_id' => $clientId,
            'name' => (string) $registration['company_name'],
        ]);

        return $stmt->fetch() ?: null;
    }

    private static function findPreparedUser(string $tenantId, string $email, string $userId = ''): ?array
    {
        $sql = 'SELECT id, tenant_id, email FROM users WHERE tenant_id = :tenant_id AND email = :email';
        $params = ['tenant_id' => $tenantId, 'email' => $email];
        if ($userId !== '') {
            $sql .= ' AND id = :id';
            $params['id'] = $userId;
        }
        $stmt = Database::connection()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetch() ?: null;
    }

    private static function persistAccountReferences(
        string $registrationId,
        string $clientId,
        ?string $empresaId = null,
        ?string $tenantId = null,
        ?string $userId = null,
        ?string $status = null
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE trial_registrations
             SET client_id = :client_id,
                 empresa_id = COALESCE(:empresa_id, empresa_id),
                 tenant_id = COALESCE(:tenant_id, tenant_id),
                 user_id = COALESCE(:user_id, user_id),
                 status = COALESCE(:status, status),
                 expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $registrationId,
            'client_id' => $clientId,
            'empresa_id' => $empresaId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'status' => $status,
        ]);
    }

    private static function setActivationState(string $registrationId, string $status, bool $activated = false): void
    {
        $sql = 'UPDATE trial_registrations SET status = :status, expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)';
        if ($activated) {
            $sql .= ', activated_at = NOW()';
        }
        $stmt = Database::connection()->prepare($sql . ' WHERE id = :id');
        $stmt->execute(['id' => $registrationId, 'status' => $status]);
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
