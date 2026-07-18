<?php

declare(strict_types=1);

final class WebhookIntegrationRepository
{
    public static function ensureTables(): void
    {
        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS webhook_settings (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                token_lookup CHAR(64) NULL,
                token_preview VARCHAR(32) NOT NULL,
                token_encrypted TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                regenerated_at DATETIME NULL,
                UNIQUE KEY webhook_settings_tenant_unique (tenant_id),
                UNIQUE KEY webhook_settings_token_lookup_unique (token_lookup),
                INDEX webhook_settings_active_idx (is_active)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS webhook_logs (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NULL,
                lead_id VARCHAR(191) NULL,
                status VARCHAR(32) NOT NULL,
                error_message VARCHAR(500) NULL,
                payload_json TEXT NULL,
                ip_address VARCHAR(64) NULL,
                user_agent VARCHAR(500) NULL,
                source_url VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX webhook_logs_tenant_id_idx (tenant_id),
                INDEX webhook_logs_status_idx (status),
                INDEX webhook_logs_created_at_idx (created_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('webhook_settings', 'token_encrypted', 'ALTER TABLE webhook_settings ADD COLUMN token_encrypted TEXT NULL AFTER token_preview');
        self::ensureColumn('webhook_settings', 'token_lookup', 'ALTER TABLE webhook_settings ADD COLUMN token_lookup CHAR(64) NULL AFTER token_hash, ADD UNIQUE INDEX webhook_settings_token_lookup_unique (token_lookup)');
    }

    public static function settings(string $tenantId): array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare('SELECT * FROM webhook_settings WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $settings = $stmt->fetch();

        if ($settings) {
            $settings['token'] = self::decryptToken((string) ($settings['token_encrypted'] ?? '')) ?: null;
            if (empty($settings['token_lookup']) && is_string($settings['token'])) {
                $settings['token_lookup'] = hash('sha256', $settings['token']);
                $lookup = Database::connection()->prepare('UPDATE webhook_settings SET token_lookup = :token_lookup WHERE tenant_id = :tenant_id');
                $lookup->execute(['token_lookup' => $settings['token_lookup'], 'tenant_id' => $tenantId]);
            }
            return $settings;
        }

        $token = self::newToken();
        $settings = [
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'token_hash' => password_hash($token, PASSWORD_BCRYPT),
            'token_lookup' => hash('sha256', $token),
            'token_preview' => self::tokenPreview($token),
            'token_encrypted' => self::encryptToken($token),
            'is_active' => 1,
        ];

        $insert = Database::connection()->prepare(
            'INSERT INTO webhook_settings (id, tenant_id, token_hash, token_lookup, token_preview, token_encrypted, is_active, created_at, updated_at, regenerated_at)
             VALUES (:id, :tenant_id, :token_hash, :token_lookup, :token_preview, :token_encrypted, 1, NOW(), NOW(), NOW())'
        );
        $insert->execute([
            'id' => $settings['id'],
            'tenant_id' => $settings['tenant_id'],
            'token_hash' => $settings['token_hash'],
            'token_lookup' => $settings['token_lookup'],
            'token_preview' => $settings['token_preview'],
            'token_encrypted' => $settings['token_encrypted'],
        ]);
        $settings['token'] = $token;

        return $settings;
    }

    public static function regenerateToken(string $tenantId): string
    {
        self::ensureTables();
        self::settings($tenantId);
        $token = self::newToken();

        $stmt = Database::connection()->prepare(
            'UPDATE webhook_settings
             SET token_hash = :token_hash,
                 token_lookup = :token_lookup,
                 token_preview = :token_preview,
                 token_encrypted = :token_encrypted,
                 regenerated_at = NOW(),
                 updated_at = NOW()
             WHERE tenant_id = :tenant_id'
        );
        $stmt->execute([
            'token_hash' => password_hash($token, PASSWORD_BCRYPT),
            'token_lookup' => hash('sha256', $token),
            'token_preview' => self::tokenPreview($token),
            'token_encrypted' => self::encryptToken($token),
            'tenant_id' => $tenantId,
        ]);

        return $token;
    }

    public static function setActive(string $tenantId, bool $active): void
    {
        self::ensureTables();
        self::settings($tenantId);
        $stmt = Database::connection()->prepare('UPDATE webhook_settings SET is_active = :active, updated_at = NOW() WHERE tenant_id = :tenant_id');
        $stmt->execute(['active' => $active ? 1 : 0, 'tenant_id' => $tenantId]);
    }

    public static function recentLogs(string $tenantId, int $limit = 20): array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare(
            'SELECT webhook_logs.*, leads.first_name, leads.last_name, leads.email, leads.phone
             FROM webhook_logs
             LEFT JOIN leads ON leads.id = webhook_logs.lead_id AND leads.tenant_id = webhook_logs.tenant_id
             WHERE webhook_logs.tenant_id = :tenant_id
             ORDER BY webhook_logs.created_at DESC
             LIMIT ' . max(1, min($limit, 50))
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    public static function handleIncoming(array $payload, ?string $headerToken = null): array
    {
        self::ensureTables();
        $token = trim((string) ($headerToken ?: ($payload['token'] ?? '')));
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $tenantId = null;

        if (self::isPlatformRateLimited($ip)) {
            return self::jsonResult(false, 'Demasiados envios. Intentalo mas tarde.');
        }

        if ($token !== '') {
            $settings = self::settingsByToken($token);
            if (!$settings) {
                self::log(null, null, 'error', 'Token invalido.', $payload, $ip, $userAgent);
                return self::jsonResult(false, 'No se pudo enviar la solicitud.');
            }

            $tenantId = (string) $settings['tenant_id'];
            if ((int) $settings['is_active'] !== 1) {
                self::log($tenantId, null, 'error', 'Integracion inactiva.', $payload, $ip, $userAgent);
                return self::jsonResult(false, 'Integracion inactiva');
            }
        } else {
            if (!self::isAllowedWebsiteOrigin()) {
                self::log(null, null, 'error', 'Origen web no permitido.', $payload, $ip, $userAgent);
                return self::jsonResult(false, 'No se pudo enviar la solicitud.');
            }

            if (self::isPlatformRateLimited($ip)) {
                self::log(null, null, 'error', 'Rate limit web superado.', $payload, $ip, $userAgent);
                return self::jsonResult(false, 'Demasiados envios. Intentalo mas tarde.');
            }

            if (trim((string) ($payload['website'] ?? $payload['honeypot'] ?? '')) !== '') {
                self::log(null, null, 'blocked', 'Honeypot completado.', $payload, $ip, $userAgent);
                return self::jsonResult(false, 'No se pudo enviar la solicitud.');
            }

            try {
                $platformLeadId = PlatformLeadRepository::createFromPayload($payload);
                self::log(null, null, 'success', 'Solicitud web registrada como lead comercial: ' . $platformLeadId, $payload, $ip, $userAgent);
                if (!Mailer::sendWebLeadConfirmation($payload, $platformLeadId)) {
                    self::log(null, null, 'email_error', 'Lead creado, pero no se pudo enviar el correo de confirmacion. ' . Mailer::lastError(), $payload, $ip, $userAgent);
                }

                return [
                    'success' => true,
                    'message' => 'Solicitud recibida correctamente',
                    'lead_id' => $platformLeadId,
                ];
            } catch (Throwable $exception) {
                self::log(null, null, 'error', $exception->getMessage(), $payload, $ip, $userAgent);
                return self::jsonResult(false, 'No se pudo enviar la solicitud. Revisa email o telefono.');
            }
        }

        if (!self::tenantAcceptsWebhooks($tenantId)) {
            self::log($tenantId, null, 'error', 'Empresa suspendida o cancelada.', $payload, $ip, $userAgent);
            return self::jsonResult(false, 'Integracion inactiva');
        }

        if (self::isRateLimited($tenantId, $ip)) {
            self::log($tenantId, null, 'error', 'Rate limit superado.', $payload, $ip, $userAgent);
            return self::jsonResult(false, 'Demasiados envios. Intentalo mas tarde.');
        }

        if (trim((string) ($payload['website'] ?? $payload['honeypot'] ?? '')) !== '') {
            self::log($tenantId, null, 'blocked', 'Honeypot completado.', $payload, $ip, $userAgent);
            return self::jsonResult(false, 'Token invalido o lead incompleto');
        }

        try {
            $normalized = self::normalizePayload($payload);
            $leadId = self::createOrUpdateLead($tenantId, $normalized);
            self::log($tenantId, $leadId, $normalized['was_duplicate'] ? 'duplicate' : 'success', $normalized['was_duplicate'] ? 'Lead existente actualizado por webhook.' : null, $payload, $ip, $userAgent);

            return [
                'success' => true,
                'message' => $normalized['was_duplicate'] ? 'Lead recibido correctamente. Ya existia en la plataforma.' : 'Lead recibido correctamente',
                'lead_id' => $leadId,
            ];
        } catch (Throwable $exception) {
            self::log($tenantId, null, 'error', $exception->getMessage(), $payload, $ip, $userAgent);
            return self::jsonResult(false, 'No se pudo enviar la solicitud. Revisa email o telefono.');
        }
    }

    public static function recentPlatformLogs(int $limit = 30): array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare(
            'SELECT webhook_logs.*, leads.first_name, leads.last_name, leads.email, leads.phone, empresas.name AS empresa_name
             FROM webhook_logs
             LEFT JOIN leads ON leads.id = webhook_logs.lead_id AND leads.tenant_id = webhook_logs.tenant_id
             LEFT JOIN empresas ON empresas.tenant_id = webhook_logs.tenant_id
             ORDER BY webhook_logs.created_at DESC
             LIMIT ' . max(1, min($limit, 80))
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function logPlatformEmailDiagnostic(string $status, string $message, string $email): void
    {
        self::ensureTables();
        self::log(
            null,
            null,
            $status,
            $message,
            ['email' => $email, 'diagnostics' => Mailer::diagnostics()],
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)
        );
    }

    private static function settingsByToken(string $token): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM webhook_settings WHERE token_lookup = :token_lookup LIMIT 1');
        $stmt->execute(['token_lookup' => hash('sha256', $token)]);
        $settings = $stmt->fetch();
        return $settings && password_verify($token, (string) $settings['token_hash']) ? $settings : null;
    }

    private static function isAllowedWebsiteOrigin(): bool
    {
        $origin = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
        $referer = rtrim((string) ($_SERVER['HTTP_REFERER'] ?? ''), '/');
        $allowed = [];
        foreach ([getenv('WEB_APP_URL') ?: 'https://membora.es', getenv('APP_WEB_URL') ?: ''] as $origins) {
            foreach (explode(',', (string) $origins) as $allowedOrigin) {
                $allowedOrigin = rtrim(trim($allowedOrigin), '/');
                if ($allowedOrigin !== '') {
                    $allowed[] = $allowedOrigin;
                }
            }
        }

        foreach ($allowed as $allowedOrigin) {
            if ($origin === $allowedOrigin || str_starts_with($referer, $allowedOrigin . '/')) {
                return true;
            }
        }

        return false;
    }

    private static function normalizePayload(array $payload): array
    {
        $firstName = self::clean((string) ($payload['nombre'] ?? $payload['first_name'] ?? ''), 120);
        $lastName = self::clean((string) ($payload['apellidos'] ?? $payload['last_name'] ?? ''), 160);
        if ($firstName !== '' && $lastName === '' && str_contains($firstName, ' ')) {
            [$firstName, $lastName] = array_pad(explode(' ', $firstName, 2), 2, '');
        }

        $email = strtolower(self::clean((string) ($payload['email'] ?? ''), 190));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email no valido.');
        }

        $phone = self::phoneFromPayload($payload);
        if ($phone !== '' && !preg_match('/^\+?[0-9\s().-]{6,30}$/', $phone)) {
            throw new RuntimeException('Telefono no valido.');
        }

        if ($email === '' && $phone === '') {
            throw new RuntimeException('El lead debe incluir email o telefono.');
        }

        $source = strtoupper(self::clean((string) ($payload['origen'] ?? 'FORMULARIO_WEB'), 40));
        $allowedSources = ['WEB', 'WEBSITE', 'LANDING', 'FORMULARIO_WEB'];
        if (!in_array($source, $allowedSources, true)) {
            $source = 'FORMULARIO_WEB';
        }

        $message = self::clean((string) ($payload['mensaje'] ?? $payload['message'] ?? ''), 1200);
        $utm = array_filter([
            'utm_source' => self::clean((string) ($payload['utm_source'] ?? ''), 120),
            'utm_medium' => self::clean((string) ($payload['utm_medium'] ?? ''), 120),
            'utm_campaign' => self::clean((string) ($payload['utm_campaign'] ?? ''), 160),
            'url_origen' => self::clean((string) ($payload['url_origen'] ?? $payload['source_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 500),
            'acepta_rgpd' => self::clean((string) ($payload['acepta_rgpd'] ?? ''), 20),
        ], static fn ($value) => $value !== '');

        return [
            'first_name' => $firstName !== '' ? $firstName : 'Lead web',
            'last_name' => $lastName ?: null,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'source' => $source,
            'interest' => $message !== '' ? $message : 'Lead recibido desde formulario web',
            'message' => $message,
            'utm' => $utm,
            'was_duplicate' => false,
        ];
    }

    private static function createOrUpdateLead(string $tenantId, array &$data): string
    {
        LeadRepository::ensureNotesTable();
        $pdo = Database::connection();
        $existing = self::findExistingLead($tenantId, $data['email'], $data['phone']);

        if ($existing) {
            $data['was_duplicate'] = true;
            $stmt = $pdo->prepare('UPDATE leads SET updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $existing['id'], 'tenant_id' => $tenantId]);
            self::addLeadNote($tenantId, (string) $existing['id'], self::noteText($data, true));
            return (string) $existing['id'];
        }

        $leadId = cuid();
        $stageId = PipelineRepository::firstId($tenantId);
        if (!$stageId) {
            throw new RuntimeException('No hay etapa inicial de pipeline configurada.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO leads (id, tenant_id, pipeline_stage_id, assigned_user_id, first_name, last_name, email, phone, source, interest, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :pipeline_stage_id, NULL, :first_name, :last_name, :email, :phone, :source, :interest, "OPEN", NOW(), NOW())'
        );
        $stmt->execute([
            'id' => $leadId,
            'tenant_id' => $tenantId,
            'pipeline_stage_id' => $stageId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'source' => $data['source'],
            'interest' => $data['interest'],
        ]);

        self::addLeadNote($tenantId, $leadId, self::noteText($data, false));
        return $leadId;
    }

    private static function findExistingLead(string $tenantId, ?string $email, ?string $phone): ?array
    {
        if ($email) {
            $stmt = Database::connection()->prepare('SELECT id FROM leads WHERE tenant_id = :tenant_id AND LOWER(email) = :email ORDER BY created_at DESC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId, 'email' => strtolower($email)]);
            $lead = $stmt->fetch();
            if ($lead) {
                return $lead;
            }
        }

        if ($phone) {
            $stmt = Database::connection()->prepare('SELECT id FROM leads WHERE tenant_id = :tenant_id AND phone = :phone ORDER BY created_at DESC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId, 'phone' => $phone]);
            $lead = $stmt->fetch();
            if ($lead) {
                return $lead;
            }
        }

        return null;
    }

    private static function addLeadNote(string $tenantId, string $leadId, string $note): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO lead_notes (id, tenant_id, lead_id, user_id, note, created_at)
             VALUES (:id, :tenant_id, :lead_id, NULL, :note, NOW())'
        );
        $stmt->execute(['id' => cuid(), 'tenant_id' => $tenantId, 'lead_id' => $leadId, 'note' => $note]);
    }

    private static function noteText(array $data, bool $duplicate): string
    {
        $lines = [$duplicate ? 'Nuevo envio recibido por formulario web para un lead existente.' : 'Lead creado automaticamente desde formulario web.'];
        if ($data['message'] !== '') {
            $lines[] = 'Mensaje: ' . $data['message'];
        }
        foreach ($data['utm'] as $key => $value) {
            $lines[] = $key . ': ' . $value;
        }

        return implode("\n", $lines);
    }

    private static function tenantAcceptsWebhooks(string $tenantId): bool
    {
        EmpresaRepository::ensureTables();
        $stmt = Database::connection()->prepare('SELECT status FROM empresas WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $status = $stmt->fetchColumn();
        return !$status || in_array($status, ['ACTIVE', 'TRIAL'], true);
    }

    private static function isRateLimited(string $tenantId, string $ip): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM webhook_logs
             WHERE tenant_id = :tenant_id
             AND ip_address = :ip_address
             AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'ip_address' => $ip]);
        return (int) $stmt->fetchColumn() >= 30;
    }

    /** @phpstan-impure */
    private static function isPlatformRateLimited(string $ip): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM webhook_logs
             WHERE ip_address = :ip_address
             AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );
        $stmt->execute(['ip_address' => $ip]);
        return (int) $stmt->fetchColumn() >= 30;
    }

    private static function log(?string $tenantId, ?string $leadId, string $status, ?string $error, array $payload, string $ip, string $userAgent): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO webhook_logs (id, tenant_id, lead_id, status, error_message, payload_json, ip_address, user_agent, source_url, created_at)
             VALUES (:id, :tenant_id, :lead_id, :status, :error_message, :payload_json, :ip_address, :user_agent, :source_url, NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'lead_id' => $leadId,
            'status' => $status,
            'error_message' => $error ? substr($error, 0, 500) : null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'ip_address' => $ip ?: null,
            'user_agent' => $userAgent ?: null,
            'source_url' => self::clean((string) ($payload['url_origen'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 500) ?: null,
        ]);
    }

    private static function jsonResult(bool $success, string $message): array
    {
        return ['success' => $success, 'message' => $message];
    }

    private static function clean(string $value, int $maxLength): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return substr($value, 0, $maxLength);
    }

    private static function phoneFromPayload(array $payload): string
    {
        $phone = self::clean((string) ($payload['telefono'] ?? $payload['phone'] ?? ''), 40);
        $prefix = self::clean((string) ($payload['prefijo_telefono'] ?? $payload['phone_prefix'] ?? ''), 8);

        if ($phone === '') {
            return '';
        }

        if ($prefix !== '' && !str_starts_with($phone, '+')) {
            $phone = $prefix . ' ' . $phone;
        }

        return substr($phone, 0, 40);
    }

    private static function newToken(): string
    {
        return 'membora_wh_' . bin2hex(random_bytes(24));
    }

    private static function tokenPreview(string $token): string
    {
        return substr($token, 0, 14) . '...' . substr($token, -6);
    }

    private static function encryptionKey(): string
    {
        $seed = trim((string) (getenv('APP_KEY') ?: ''));
        if ($seed === '') {
            throw new RuntimeException('APP_KEY es obligatoria para cifrar tokens webhook.');
        }
        return hash('sha256', $seed, true);
    }

    private static function encryptToken(string $token): ?string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL es obligatorio para cifrar tokens webhook.');
        }

        $iv = random_bytes(16);
        $cipher = openssl_encrypt($token, 'aes-256-cbc', self::encryptionKey(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return null;
        }

        return base64_encode($iv . $cipher);
    }

    private static function decryptToken(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $raw = base64_decode($value, true);
        if ($raw === false) {
            return null;
        }

        if (!function_exists('openssl_decrypt') || strlen($raw) <= 16) {
            return $raw ?: null;
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $token = openssl_decrypt($cipher, 'aes-256-cbc', self::encryptionKey(), OPENSSL_RAW_DATA, $iv);
        return $token === false ? null : $token;
    }

    private static function ensureColumn(string $table, string $column, string $sql): void
    {
        try {
            $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table . ' LIKE "' . $column . '"');
            if (!$stmt->fetch()) {
                Database::connection()->exec($sql);
            }
        } catch (Throwable) {
        }
    }
}
