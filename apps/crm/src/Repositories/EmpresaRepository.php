<?php

declare(strict_types=1);

final class EmpresaRepository
{
    use EmpresaTenantProvisioningTrait;

    public const PLATFORM_ADMIN_EMAIL = 'admin@membora.crm';

    public static function ensureTables(): void
    {
        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS empresas (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NULL,
                client_id VARCHAR(191) NULL,
                name VARCHAR(191) NOT NULL,
                contact_email VARCHAR(191) NULL,
                plan VARCHAR(64) NOT NULL DEFAULT "BASIC",
                status VARCHAR(32) NOT NULL DEFAULT "ACTIVE",
                payment_status VARCHAR(32) NOT NULL DEFAULT "PAID",
                monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                next_payment_at DATE NULL,
                trial_days INT NOT NULL DEFAULT 30,
                subscription_started_at DATE NULL,
                paid_since DATE NULL,
                access_until DATE NULL,
                renewal_period VARCHAR(16) NOT NULL DEFAULT "MONTHLY",
                renewal_status VARCHAR(32) NOT NULL DEFAULT "ACTIVE",
                cancelled_at DATE NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY empresas_tenant_unique (tenant_id),
                INDEX empresas_status_idx (status),
                INDEX empresas_payment_status_idx (payment_status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('empresas', 'client_id', 'ALTER TABLE empresas ADD COLUMN client_id VARCHAR(191) NULL AFTER tenant_id');
        self::ensureColumn('empresas', 'trial_days', 'ALTER TABLE empresas ADD COLUMN trial_days INT NOT NULL DEFAULT 30 AFTER next_payment_at');
        self::ensureColumn('empresas', 'subscription_started_at', 'ALTER TABLE empresas ADD COLUMN subscription_started_at DATE NULL AFTER trial_days');
        self::ensureColumn('empresas', 'paid_since', 'ALTER TABLE empresas ADD COLUMN paid_since DATE NULL AFTER subscription_started_at');
        self::ensureColumn('empresas', 'access_until', 'ALTER TABLE empresas ADD COLUMN access_until DATE NULL AFTER paid_since');
        self::ensureColumn('empresas', 'renewal_period', 'ALTER TABLE empresas ADD COLUMN renewal_period VARCHAR(16) NOT NULL DEFAULT "MONTHLY" AFTER access_until');
        self::ensureColumn('empresas', 'renewal_status', 'ALTER TABLE empresas ADD COLUMN renewal_status VARCHAR(32) NOT NULL DEFAULT "ACTIVE" AFTER renewal_period');
        self::ensureColumn('empresas', 'cancelled_at', 'ALTER TABLE empresas ADD COLUMN cancelled_at DATE NULL AFTER renewal_status');
        self::syncFromTenants();
        self::markOverduePayments();
        self::expireCancelledSubscriptions();
        self::normalizeConvertedLeadStages();
    }

    public static function ensurePlatformAdmin(): void
    {
        $pdo = Database::connection();
        $roleId = self::ensureSuperAdminRole();

        UserRepository::ensureAvatarColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute(['email' => self::PLATFORM_ADMIN_EMAIL]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $columns = self::tableColumns('users');
        $values = [
            'id' => cuid(),
            'tenant_id' => self::usersTenantAllowsNull() ? null : self::firstTenantId(),
            'role_id' => $roleId,
            'name' => 'Administrador Membora',
            'email' => self::PLATFORM_ADMIN_EMAIL,
            'password_hash' => password_hash(self::initialPlatformAdminPassword(), PASSWORD_BCRYPT),
            'status' => 'ACTIVE',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $insertColumns = array_values(array_intersect(array_keys($values), $columns));
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $insertColumns);
        $params = array_intersect_key($values, array_flip($insertColumns));

        $insert = $pdo->prepare(
            'INSERT INTO users (' . implode(', ', $insertColumns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $insert->execute($params);
    }

    public static function initialPlatformAdminPassword(): string
    {
        $configured = trim((string) (getenv('PLATFORM_ADMIN_PASSWORD') ?: ''));
        if ($configured !== '') {
            if (strlen($configured) < 12) {
                throw new RuntimeException('PLATFORM_ADMIN_PASSWORD debe tener al menos 12 caracteres.');
            }
            return $configured;
        }

        $generated = bin2hex(random_bytes(12));
        error_log('[Membora CRM] Contrasena inicial unica del administrador de plataforma: ' . $generated);
        return $generated;
    }

    public static function metrics(): array
    {
        self::ensureTables();
        $pdo = Database::connection();

        return [
            'active' => self::count($pdo, 'status = "ACTIVE"'),
            'trial' => self::count($pdo, 'status = "TRIAL"'),
            'payments_pending' => self::count($pdo, 'payment_status IN ("PENDING", "OVERDUE")'),
            'mrr' => (float) $pdo->query('SELECT COALESCE(SUM(monthly_price), 0) FROM empresas WHERE status = "ACTIVE" AND renewal_status <> "CANCELLED"')->fetchColumn(),
        ];
    }

    public static function all(string $query = '', string $status = '', string $paymentStatus = ''): array
    {
        self::ensureTables();
        $params = [];
        $where = ['1 = 1'];

        if ($query !== '') {
            $where[] = '(name LIKE :query OR contact_email LIKE :query OR plan LIKE :query OR notes LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($paymentStatus !== '') {
            $where[] = 'payment_status = :payment_status';
            $params['payment_status'] = $paymentStatus;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM empresas
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(status, "ACTIVE", "TRIAL", "SUSPENDED", "CANCELLED"), next_payment_at IS NULL, next_payment_at ASC, name ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function find(string $id): ?array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare('SELECT * FROM empresas WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $empresa = $stmt->fetch();

        return $empresa ?: null;
    }

    public static function findByClient(string $clientId): ?array
    {
        self::ensureTables();
        if (trim($clientId) === '') {
            return null;
        }

        $stmt = Database::connection()->prepare('SELECT * FROM empresas WHERE client_id = :client_id ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['client_id' => $clientId]);
        $empresa = $stmt->fetch();

        return $empresa ?: null;
    }

    public static function ensureTenantAdminUser(string $tenantId, string $name, string $email, string $password): string
    {
        $tenantId = trim($tenantId);
        $name = trim($name) ?: 'Administrador';
        $email = strtolower(trim($email));
        if ($tenantId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new RuntimeException('No se pudo preparar el usuario administrador de la empresa.');
        }

        $pdo = Database::connection();
        $tenant = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE id = :id');
        $tenant->execute(['id' => $tenantId]);
        if ((int) $tenant->fetchColumn() !== 1) {
            throw new RuntimeException('La empresa no tiene un tenant válido para vincular al usuario.');
        }

        $existing = $pdo->prepare('SELECT id, tenant_id FROM users WHERE email = :email LIMIT 1');
        $existing->execute(['email' => $email]);
        $user = $existing->fetch();
        if ($user) {
            if (!hash_equals($tenantId, (string) ($user['tenant_id'] ?? ''))) {
                throw new RuntimeException('Ya existe un usuario con ese email vinculado a otra empresa.');
            }

            $pdo->prepare(
                'UPDATE users
                 SET name = :name,
                     role_id = :role_id,
                     password_hash = :password_hash,
                     status = "ACTIVE",
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'id' => $user['id'],
                'name' => $name,
                'role_id' => self::ensureGymAdminRole(),
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ]);
            return (string) $user['id'];
        }

        $userId = cuid();
        $values = [
            'id' => $userId,
            'tenant_id' => $tenantId,
            'role_id' => self::ensureGymAdminRole(),
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'status' => 'ACTIVE',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $columns = array_values(array_intersect(array_keys($values), self::tableColumns('users')));
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $stmt = $pdo->prepare(
            'INSERT INTO users (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute(array_intersect_key($values, array_flip($columns)));

        return $userId;
    }

    public static function create(array $data): void
    {
        self::ensureTables();
        PlatformClientRepository::ensureTable();
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $params = self::empresaParams($data);
            $client = null;

            if ($params['client_id']) {
                $client = PlatformClientRepository::find($params['client_id']);
                if ($client) {
                    $params['name'] = $params['name'] ?: $client['company_name'];
                    $params['contact_email'] = $params['contact_email'] ?: $client['email'];
                }
            }

            $tenantId = null;
            if (($data['create_tenant'] ?? '') === '1' || trim((string) ($data['admin_email'] ?? '')) !== '') {
                $tenantId = self::createTenantAndAdmin($params['name'], $data, $client);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO empresas (id, tenant_id, client_id, name, contact_email, plan, status, payment_status, monthly_price, next_payment_at, trial_days, subscription_started_at, paid_since, access_until, renewal_period, renewal_status, cancelled_at, notes, created_at, updated_at)
                 VALUES (:id, :tenant_id, :client_id, :name, :contact_email, :plan, :status, :payment_status, :monthly_price, :next_payment_at, :trial_days, :subscription_started_at, :paid_since, :access_until, :renewal_period, :renewal_status, :cancelled_at, :notes, NOW(), NOW())'
            );
            $stmt->execute($params + ['id' => cuid(), 'tenant_id' => $tenantId]);

            if ($client) {
                PlatformClientRepository::markCustomer($client['id']);
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function update(string $id, array $data): void
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare(
            'UPDATE empresas
             SET name = :name,
                 contact_email = :contact_email,
                 client_id = :client_id,
                 plan = :plan,
                 status = :status,
                 payment_status = :payment_status,
                 monthly_price = :monthly_price,
                 next_payment_at = :next_payment_at,
                 trial_days = :trial_days,
                 subscription_started_at = :subscription_started_at,
                 paid_since = :paid_since,
                 access_until = :access_until,
                 renewal_period = :renewal_period,
                 renewal_status = :renewal_status,
                 cancelled_at = :cancelled_at,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(self::empresaParams($data) + ['id' => $id]);
    }

    public static function delete(string $id): void
    {
        self::ensureTables();
        $empresa = self::find($id);
        if (!$empresa) {
            throw new RuntimeException('No se encontró la empresa.');
        }

        $pdo = Database::connection();
        $tenantId = trim((string) ($empresa['tenant_id'] ?? ''));
        $pdo->beginTransaction();

        try {
            self::deletePlatformInvoiceRows($pdo, $id);
            self::deleteRowsByColumn($pdo, 'empresa_payments', 'empresa_id', $id);

            if ($tenantId !== '') {
                self::detachPlatformAdminsFromTenant($pdo, $tenantId);
                self::deleteTenantRows($pdo, $tenantId);
            }

            $stmt = $pdo->prepare('DELETE FROM empresas WHERE id = :id');
            $stmt->execute(['id' => $id]);

            if ($tenantId !== '') {
                self::deleteRowsByColumn($pdo, 'pipeline_stages', 'tenant_id', $tenantId);
                self::deleteRowsByColumn($pdo, 'users', 'tenant_id', $tenantId);
                self::deleteRowsByColumn($pdo, 'tenants', 'id', $tenantId);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function updateSubscription(string $id, array $data): void
    {
        self::ensureTables();
        $empresa = self::find($id);
        if (!$empresa) {
            throw new RuntimeException('No se encontro la empresa.');
        }

        $params = self::empresaParams(array_merge($empresa, [
            'plan' => $data['plan'] ?? $empresa['plan'],
            'status' => $data['status'] ?? $empresa['status'],
            'payment_status' => $data['payment_status'] ?? $empresa['payment_status'],
            'monthly_price' => $data['monthly_price'] ?? $empresa['monthly_price'],
            'next_payment_at' => $data['next_payment_at'] ?? $empresa['next_payment_at'],
            'trial_days' => $data['trial_days'] ?? $empresa['trial_days'],
            'subscription_started_at' => $data['subscription_started_at'] ?? $empresa['subscription_started_at'],
            'access_until' => $data['access_until'] ?? $empresa['access_until'],
            'renewal_period' => $data['renewal_period'] ?? $empresa['renewal_period'],
            'renewal_status' => $data['renewal_status'] ?? $empresa['renewal_status'],
            'cancelled_at' => $data['cancelled_at'] ?? $empresa['cancelled_at'],
        ]));

        $stmt = Database::connection()->prepare(
            'UPDATE empresas
             SET plan = :plan,
                 status = :status,
                 payment_status = :payment_status,
                 monthly_price = :monthly_price,
                 next_payment_at = :next_payment_at,
                 trial_days = :trial_days,
                 subscription_started_at = :subscription_started_at,
                 paid_since = :paid_since,
                 access_until = :access_until,
                 renewal_period = :renewal_period,
                 renewal_status = :renewal_status,
                 cancelled_at = :cancelled_at,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'plan' => $params['plan'],
            'status' => $params['status'],
            'payment_status' => $params['payment_status'],
            'monthly_price' => $params['monthly_price'],
            'next_payment_at' => $params['next_payment_at'],
            'trial_days' => $params['trial_days'],
            'subscription_started_at' => $params['subscription_started_at'],
            'paid_since' => $params['paid_since'],
            'access_until' => $params['access_until'],
            'renewal_period' => $params['renewal_period'],
            'renewal_status' => $params['renewal_status'],
            'cancelled_at' => $params['cancelled_at'],
            'id' => $id,
        ]);
    }

    public static function renewSubscription(string $id): void
    {
        self::ensureTables();
        PlatformPaymentRepository::ensureTable();
        $empresa = self::find($id);

        if (!$empresa) {
            throw new RuntimeException('No se encontro la empresa.');
        }

        if (!in_array((string) $empresa['status'], ['ACTIVE', 'TRIAL'], true)) {
            throw new RuntimeException('Solo se pueden renovar empresas activas o en prueba.');
        }

        $dueDate = trim((string) ($empresa['next_payment_at'] ?? ''));
        if ($dueDate === '') {
            throw new RuntimeException('La empresa no tiene fecha de proximo pago.');
        }

        $due = DateTimeImmutable::createFromFormat('Y-m-d', $dueDate);
        if (!$due) {
            throw new RuntimeException('La fecha de proximo pago no es valida.');
        }

        $today = new DateTimeImmutable('today');
        if ($due > $today) {
            throw new RuntimeException('La renovacion solo esta disponible cuando el pago vence hoy o esta vencido.');
        }

        $amount = (float) ($empresa['monthly_price'] ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('La empresa no tiene precio mensual configurado.');
        }

        $period = (string) ($empresa['renewal_period'] ?? 'MONTHLY');
        $nextPaymentAt = $due->modify($period === 'ANNUAL' ? '+1 year' : '+1 month')->format('Y-m-d');
        $concept = 'Renovacion suscripcion CRM - ' . $due->format('m/Y');
        $notes = 'Renovacion creada desde Admin CRM para ' . $empresa['name'] . '.';

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $payment = $pdo->prepare(
                'INSERT INTO empresa_payments (id, empresa_id, concept, amount, status, due_at, paid_at, notes, created_at, updated_at)
                 VALUES (:id, :empresa_id, :concept, :amount, "PAID", :due_at, CURDATE(), :notes, NOW(), NOW())'
            );
            $payment->execute([
                'id' => cuid(),
                'empresa_id' => $id,
                'concept' => $concept,
                'amount' => number_format($amount, 2, '.', ''),
                'due_at' => $due->format('Y-m-d'),
                'notes' => $notes,
            ]);

            $update = $pdo->prepare(
                'UPDATE empresas
                 SET payment_status = "PAID",
                     next_payment_at = :next_payment_at,
                     access_until = :next_payment_at,
                     paid_since = COALESCE(paid_since, CURDATE()),
                     subscription_started_at = COALESCE(subscription_started_at, CURDATE()),
                     renewal_status = "ACTIVE",
                     cancelled_at = NULL,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'id' => $id,
                'next_payment_at' => $nextPaymentAt,
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function cancelSubscription(string $id): void
    {
        self::ensureTables();
        $empresa = self::find($id);
        if (!$empresa) {
            throw new RuntimeException('No se encontro la empresa.');
        }

        $accessUntil = trim((string) ($empresa['access_until'] ?? ''))
            ?: trim((string) ($empresa['next_payment_at'] ?? ''))
            ?: date('Y-m-d');

        Database::connection()->prepare(
            'UPDATE empresas
             SET renewal_status = "CANCEL_AT_PERIOD_END",
                 access_until = :access_until,
                 cancelled_at = CURDATE(),
                 updated_at = NOW()
             WHERE id = :id'
        )->execute(['id' => $id, 'access_until' => $accessUntil]);
    }

    public static function resumeSubscription(string $id): void
    {
        self::ensureTables();
        $empresa = self::find($id);
        if (!$empresa) {
            throw new RuntimeException('No se encontro la empresa.');
        }

        $nextPaymentAt = trim((string) ($empresa['next_payment_at'] ?? ''));
        if ($nextPaymentAt === '' || strtotime($nextPaymentAt) <= strtotime(date('Y-m-d'))) {
            $nextPaymentAt = self::defaultNextPaymentDate((string) ($empresa['renewal_period'] ?? 'MONTHLY'));
        }

        Database::connection()->prepare(
            'UPDATE empresas
             SET status = CASE WHEN status = "CANCELLED" THEN "ACTIVE" ELSE status END,
                 payment_status = CASE WHEN payment_status = "TRIAL" AND plan <> "TRIAL" THEN "PAID" ELSE payment_status END,
                 renewal_status = "ACTIVE",
                 next_payment_at = :next_payment_at,
                 access_until = :next_payment_at,
                 cancelled_at = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute(['id' => $id, 'next_payment_at' => $nextPaymentAt]);
    }

    public static function findByTenant(string $tenantId): ?array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare('SELECT * FROM empresas WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $empresa = $stmt->fetch();

        return $empresa ?: null;
    }

    public static function accessStateForTenant(string $tenantId): ?array
    {
        $empresa = self::findByTenant($tenantId);
        if (!$empresa) {
            return null;
        }

        $today = new DateTimeImmutable('today');
        $status = (string) ($empresa['status'] ?? '');
        if ($status === 'SUSPENDED') {
            return [
                'blocked' => true,
                'kind' => 'subscription_suspended',
                'title' => 'Tu suscripcion esta suspendida',
                'message' => 'Contacta con Membora o elige un plan para recuperar el acceso al CRM.',
            ];
        }

        $isTrial = strtoupper((string) ($empresa['plan'] ?? '')) === 'TRIAL' || (string) ($empresa['status'] ?? '') === 'TRIAL';
        if ($isTrial) {
            $trialStartedAt = (string) ($empresa['subscription_started_at'] ?: $empresa['created_at'] ?: 'now');
            $trialPeriod = trial_period_details($trialStartedAt, (int) ($empresa['trial_days'] ?? 30), $today);
            if ($trialPeriod['expired']) {
                return [
                    'blocked' => true,
                    'kind' => 'trial_expired',
                    'title' => 'Tu prueba ha caducado',
                    'message' => 'El periodo de prueba finalizo el ' . format_date_short($trialPeriod['expires_at']) . '. Elige un plan para continuar usando Membora.',
                    'expires_at' => $trialPeriod['expires_at'],
                    'remaining_days' => 0,
                    'is_trial' => true,
                    'empresa' => $empresa,
                ];
            }

            return [
                'blocked' => false,
                'kind' => 'trial_active',
                'is_trial' => true,
                'expires_at' => $trialPeriod['expires_at'],
                'remaining_days' => $trialPeriod['remaining_days'],
                'empresa' => $empresa,
            ];
        }

        $accessUntil = trim((string) ($empresa['access_until'] ?? ''));
        if ($accessUntil !== '') {
            $accessDate = new DateTimeImmutable($accessUntil);
            if ($accessDate < $today) {
                return [
                    'blocked' => true,
                    'kind' => 'access_expired',
                    'title' => 'Tu acceso ha finalizado',
                    'message' => 'La suscripcion estuvo activa hasta el ' . format_date_short($accessUntil) . '. Elige un plan para reactivar el CRM.',
                    'expires_at' => $accessUntil,
                ];
            }
        }

        if ($status === 'CANCELLED' && $accessUntil === '') {
            return [
                'blocked' => true,
                'kind' => 'subscription_cancelled',
                'title' => 'Tu suscripcion esta cancelada',
                'message' => 'Elige un plan para reactivar el acceso a Membora.',
            ];
        }

        return ['blocked' => false, 'is_trial' => false, 'empresa' => $empresa];
    }

    private static function syncFromTenants(): void
    {
        $pdo = Database::connection();
        $tenants = $pdo->query('SELECT id, name, created_at FROM tenants ORDER BY created_at ASC')->fetchAll();
        $exists = $pdo->prepare('SELECT COUNT(*) FROM empresas WHERE tenant_id = :tenant_id');
        $insert = $pdo->prepare(
            'INSERT INTO empresas (id, tenant_id, name, status, payment_status, monthly_price, created_at, updated_at)
             VALUES (:id, :tenant_id, :name, "ACTIVE", "PAID", 0, :created_at, NOW())'
        );

        foreach ($tenants as $tenant) {
            $exists->execute(['tenant_id' => $tenant['id']]);
            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }

            $insert->execute([
                'id' => cuid(),
                'tenant_id' => $tenant['id'],
                'name' => $tenant['name'],
                'created_at' => $tenant['created_at'] ?: date('Y-m-d H:i:s'),
            ]);
        }
    }

    private static function ensureSuperAdminRole(): string
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id FROM roles WHERE `key` IN ("SUPERADMIN", "SUPER_ADMIN") ORDER BY `key` = "SUPERADMIN" DESC LIMIT 1');
        $roleId = $stmt->fetchColumn();
        if ($roleId) {
            return (string) $roleId;
        }

        $roleId = cuid();
        $columns = self::tableColumns('roles');
        $values = [
            'id' => $roleId,
            'key' => 'SUPERADMIN',
            'name' => 'Superadmin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $insertColumns = array_values(array_intersect(array_keys($values), $columns));
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $insertColumns);
        $params = array_intersect_key($values, array_flip($insertColumns));
        $insert = $pdo->prepare(
            'INSERT INTO roles (' . implode(', ', array_map(static fn (string $column): string => $column === 'key' ? '`key`' : $column, $insertColumns)) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $insert->execute($params);

        return $roleId;
    }

    private static function empresaParams(array $data): array
    {
        $status = in_array($data['status'] ?? '', ['ACTIVE', 'TRIAL', 'SUSPENDED', 'CANCELLED'], true) ? $data['status'] : 'ACTIVE';
        $paymentStatus = in_array($data['payment_status'] ?? '', ['PAID', 'PENDING', 'OVERDUE', 'TRIAL'], true) ? $data['payment_status'] : 'PAID';
        $price = str_replace(',', '.', (string) ($data['monthly_price'] ?? '0'));
        $plan = strtoupper(trim((string) ($data['plan'] ?? 'BASIC'))) ?: 'BASIC';
        $nextPaymentAt = trim((string) ($data['next_payment_at'] ?? '')) ?: null;
        $trialDays = max(1, min(365, (int) ($data['trial_days'] ?? 30)));
        $renewalPeriod = in_array($data['renewal_period'] ?? '', ['MONTHLY', 'ANNUAL'], true) ? $data['renewal_period'] : 'MONTHLY';
        $renewalStatus = in_array($data['renewal_status'] ?? '', ['ACTIVE', 'CANCEL_AT_PERIOD_END', 'CANCELLED'], true) ? $data['renewal_status'] : 'ACTIVE';
        $subscriptionStartedAt = trim((string) ($data['subscription_started_at'] ?? '')) ?: null;
        $paidSince = trim((string) ($data['paid_since'] ?? '')) ?: null;
        $accessUntil = trim((string) ($data['access_until'] ?? '')) ?: null;
        $cancelledAt = trim((string) ($data['cancelled_at'] ?? '')) ?: null;
        if ($plan === 'TRIAL') {
            $status = 'TRIAL';
            $paymentStatus = 'TRIAL';
            $nextPaymentAt = null;
            $paidSince = null;
            $accessUntil = null;
            $price = '0';
        } elseif ($nextPaymentAt === null && $status !== 'CANCELLED') {
            $nextPaymentAt = self::defaultNextPaymentDate($renewalPeriod);
        }

        if ($subscriptionStartedAt === null) {
            $subscriptionStartedAt = date('Y-m-d');
        }
        if ($plan !== 'TRIAL' && $paidSince === null && in_array($paymentStatus, ['PAID', 'PENDING', 'OVERDUE'], true)) {
            $paidSince = date('Y-m-d');
        }
        if ($plan !== 'TRIAL') {
            $accessUntil = $nextPaymentAt;
        } elseif ($accessUntil === null) {
            $accessUntil = $nextPaymentAt;
        }
        if ($renewalStatus === 'CANCELLED' && $cancelledAt === null) {
            $cancelledAt = date('Y-m-d');
        }

        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'contact_email' => trim((string) ($data['contact_email'] ?? '')) ?: null,
            'client_id' => trim((string) ($data['client_id'] ?? '')) ?: null,
            'plan' => $plan,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'monthly_price' => number_format(max(0, (float) $price), 2, '.', ''),
            'next_payment_at' => $nextPaymentAt,
            'trial_days' => $trialDays,
            'subscription_started_at' => $subscriptionStartedAt,
            'paid_since' => $paidSince,
            'access_until' => $accessUntil,
            'renewal_period' => $renewalPeriod,
            'renewal_status' => $renewalStatus,
            'cancelled_at' => $cancelledAt,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    private static function markOverduePayments(): void
    {
        Database::connection()->exec(
            'UPDATE empresas
             SET payment_status = "OVERDUE",
                 updated_at = NOW()
             WHERE status IN ("ACTIVE", "TRIAL")
               AND payment_status IN ("PAID", "PENDING")
               AND plan <> "TRIAL"
               AND monthly_price > 0
               AND next_payment_at IS NOT NULL
               AND next_payment_at < CURDATE()'
        );
    }

    private static function expireCancelledSubscriptions(): void
    {
        Database::connection()->exec(
            'UPDATE empresas
             SET status = "CANCELLED",
                 renewal_status = "CANCELLED",
                 payment_status = "PENDING",
                 updated_at = NOW()
             WHERE renewal_status = "CANCEL_AT_PERIOD_END"
               AND access_until IS NOT NULL
               AND access_until < CURDATE()'
        );
    }

    private static function normalizeConvertedLeadStages(): void
    {
        try {
            Database::connection()->exec(
                'UPDATE pipeline_stages
                 SET name = "Cliente"
                 WHERE `key` = "CONVERTED"
                   AND name IN ("Convertido", "Convertido a socio")'
            );
        } catch (Throwable) {
        }
    }

    private static function defaultNextPaymentDate(string $period = 'MONTHLY'): string
    {
        $today = new DateTimeImmutable('today');
        if ($period === 'ANNUAL') {
            return $today->modify('+1 year')->format('Y-m-d');
        }

        $nextMonthStart = $today->modify('first day of next month');
        $lastDayOfNextMonth = (int) $nextMonthStart->format('t');
        $day = min((int) $today->format('j'), $lastDayOfNextMonth);

        return $nextMonthStart->setDate(
            (int) $nextMonthStart->format('Y'),
            (int) $nextMonthStart->format('m'),
            $day
        )->format('Y-m-d');
    }

    private static function tableColumns(string $table): array
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table);
        return array_map(static fn (array $column): string => $column['Field'], $stmt->fetchAll());
    }

    private static function deleteTenantRows(PDO $pdo, string $tenantId): void
    {
        if (self::tableExists($pdo, 'auth_tokens') && self::tableExists($pdo, 'users')) {
            $stmt = $pdo->prepare(
                'DELETE FROM auth_tokens
                 WHERE user_id IN (SELECT id FROM users WHERE tenant_id = :tenant_id)'
            );
            $stmt->execute(['tenant_id' => $tenantId]);
        }
        if (self::tableExists($pdo, 'trial_credential_deliveries') && self::tableExists($pdo, 'users')) {
            $stmt = $pdo->prepare(
                'DELETE FROM trial_credential_deliveries
                 WHERE user_id IN (SELECT id FROM users WHERE tenant_id = :tenant_id)'
            );
            $stmt->execute(['tenant_id' => $tenantId]);
        }

        foreach ([
            'risk_alerts',
            'billing_sync_logs',
            'billing_integrations',
            'webhook_logs',
            'webhook_settings',
            'checkins',
            'reservations',
            'payments',
            'subscriptions',
            'task_members',
            'tasks',
            'class_sessions',
            'class_types',
            'lead_notes',
            'leads',
            'members',
            'membership_plans',
        ] as $table) {
            self::deleteRowsByColumn($pdo, $table, 'tenant_id', $tenantId);
        }
    }

    private static function detachPlatformAdminsFromTenant(PDO $pdo, string $tenantId): void
    {
        $roleWhere = 'role_id IN (SELECT id FROM roles WHERE `key` IN ("SUPERADMIN", "SUPER_ADMIN"))';
        $count = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = :tenant_id AND ' . $roleWhere);
        $count->execute(['tenant_id' => $tenantId]);
        if ((int) $count->fetchColumn() === 0) {
            return;
        }

        if (self::usersTenantAllowsNull()) {
            $stmt = $pdo->prepare('UPDATE users SET tenant_id = NULL WHERE tenant_id = :tenant_id AND ' . $roleWhere);
            $stmt->execute(['tenant_id' => $tenantId]);
            return;
        }

        $replacement = $pdo->prepare('SELECT id FROM tenants WHERE id <> :tenant_id ORDER BY created_at ASC LIMIT 1');
        $replacement->execute(['tenant_id' => $tenantId]);
        $replacementTenantId = trim((string) $replacement->fetchColumn());
        if ($replacementTenantId === '') {
            throw new RuntimeException('No se puede eliminar la única empresa mientras un superadministrador dependa de su tenant.');
        }

        $stmt = $pdo->prepare(
            'UPDATE users SET tenant_id = :replacement_tenant_id
             WHERE tenant_id = :tenant_id AND ' . $roleWhere
        );
        $stmt->execute([
            'replacement_tenant_id' => $replacementTenantId,
            'tenant_id' => $tenantId,
        ]);
    }

    private static function deletePlatformInvoiceRows(PDO $pdo, string $empresaId): void
    {
        if (!self::tableExists($pdo, 'platform_invoices')) {
            return;
        }

        foreach (['platform_invoice_payments', 'platform_invoice_items'] as $table) {
            if (!self::tableExists($pdo, $table)) {
                continue;
            }

            $stmt = $pdo->prepare(
                'DELETE FROM ' . $table . '
                 WHERE invoice_id IN (SELECT id FROM platform_invoices WHERE empresa_id = :empresa_id)'
            );
            $stmt->execute(['empresa_id' => $empresaId]);
        }

        self::deleteRowsByColumn($pdo, 'platform_invoices', 'empresa_id', $empresaId);
    }

    private static function deleteRowsByColumn(PDO $pdo, string $table, string $column, string $value): void
    {
        if (!self::tableExists($pdo, $table)) {
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $column . ' = :value');
        $stmt->execute(['value' => $value]);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute(['table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function ensureColumn(string $table, string $column, string $sql): void
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table . ' LIKE "' . $column . '"');
        if (!$stmt->fetch()) {
            Database::connection()->exec($sql);
        }
    }

}
