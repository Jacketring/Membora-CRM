<?php

final class DashboardRepository
{
    public static function summary(string $tenantId): array
    {
        $pdo = Database::connection();

        return [
            'activeMembers' => self::count($pdo, 'members', 'status <> "INACTIVE"', $tenantId),
            'totalMembers' => self::count($pdo, 'members', '1 = 1', $tenantId),
            'openLeads' => self::count($pdo, 'leads', 'status = "OPEN"', $tenantId),
            'convertedLeads' => self::count($pdo, 'leads', 'status = "CONVERTED"', $tenantId),
            'lostLeads' => self::count($pdo, 'leads', 'status = "LOST"', $tenantId),
            'totalLeads' => self::count($pdo, 'leads', '1 = 1', $tenantId),
            'pendingTasks' => self::count($pdo, 'tasks', 'status = "PENDING"', $tenantId),
            'completedTasks' => self::count($pdo, 'tasks', 'status = "COMPLETED"', $tenantId),
            'todayTasks' => self::count($pdo, 'tasks', 'DATE(due_at) = CURDATE()', $tenantId),
            'overdueTasks' => self::count($pdo, 'tasks', 'status = "PENDING" AND due_at < NOW()', $tenantId),
            'openAlerts' => self::count($pdo, 'risk_alerts', 'status = "OPEN"', $tenantId),
            'pendingPayments' => self::count($pdo, 'payments', 'status IN ("PENDING", "OVERDUE")', $tenantId),
            'recentLeads' => LeadRepository::all($tenantId, '', '', '', '', '', 5),
            'tasks' => TaskRepository::all($tenantId, '', '', 5),
        ];
    }

    private static function count(PDO $pdo, string $table, string $where, string $tenantId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }
}

final class StaffRepository
{
    public static function all(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT users.id, users.name, users.email, roles.key AS role_key
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.tenant_id = :tenant_id AND users.status = "ACTIVE"
             ORDER BY users.name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }
}

final class UserRepository
{
    public static function ensureAvatarColumn(): void
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM users LIKE "avatar_path"');
        if (!$stmt->fetch()) {
            Database::connection()->exec('ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER email');
        }
    }

    public static function all(string $tenantId, string $query = '', string $roleId = '', string $status = ''): array
    {
        self::ensureAvatarColumn();
        $params = ['tenant_id' => $tenantId];
        $where = ['users.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(users.name LIKE :query OR users.email LIKE :query OR roles.key LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($roleId !== '') {
            $where[] = 'users.role_id = :role_id';
            $params['role_id'] = $roleId;
        }

        if ($status !== '') {
            $where[] = 'users.status = :status';
            $params['status'] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT users.id,
                    users.name,
                    users.email,
                    users.avatar_path,
                    users.status,
                    users.created_at,
                    users.updated_at,
                    users.last_login_at,
                    users.role_id,
                    roles.key AS role_key
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY users.status ASC, users.name ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function roles(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, `key` AS role_key
             FROM roles
             ORDER BY CASE `key`
                WHEN "SUPER_ADMIN" THEN 1
                WHEN "SUPERADMIN" THEN 1
                WHEN "GYM_ADMIN" THEN 2
                WHEN "ADMIN" THEN 3
                WHEN "SALES_RECEPTION" THEN 4
                WHEN "RECEPTION" THEN 5
                WHEN "SALES" THEN 6
                WHEN "TRAINER" THEN 7
                WHEN "STAFF" THEN 8
                ELSE 99
             END, `key` ASC'
        );

        return $stmt->fetchAll();
    }

    public static function roleExists(string $roleId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM roles WHERE id = :id');
        $stmt->execute(['id' => $roleId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function metrics(string $tenantId): array
    {
        $pdo = Database::connection();

        return [
            'active' => self::count($pdo, $tenantId, 'users.status = "ACTIVE"'),
            'inactive' => self::count($pdo, $tenantId, 'users.status = "INACTIVE"'),
            'admins' => self::count($pdo, $tenantId, 'users.status = "ACTIVE" AND roles.key IN ("SUPER_ADMIN", "GYM_ADMIN", "ADMIN")'),
            'total' => self::count($pdo, $tenantId, '1 = 1'),
        ];
    }

    public static function emailExists(string $tenantId, string $email, ?string $exceptId = null): bool
    {
        $params = ['email' => $email];
        $where = 'email = :email';

        if ($exceptId) {
            $where .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }

        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM users WHERE {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function count(PDO $pdo, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.tenant_id = :tenant_id AND {$where}"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }
}

final class TenantRepository
{
    public static function ensureSettingsColumns(): void
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM tenants LIKE "primary_color"');
        if (!$stmt->fetch()) {
            Database::connection()->exec('ALTER TABLE tenants ADD COLUMN primary_color VARCHAR(16) NULL AFTER name');
        }
    }

    public static function updateSettings(string $tenantId, string $name, string $primaryColor): void
    {
        self::ensureSettingsColumns();
        $stmt = Database::connection()->prepare(
            'UPDATE tenants SET name = :name, primary_color = :primary_color WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'primary_color' => $primaryColor,
            'id' => $tenantId,
        ]);
    }
}

final class EmpresaRepository
{
    public const PLATFORM_ADMIN_EMAIL = 'admin@membora.crm';
    public const PLATFORM_ADMIN_PASSWORD = 'MemboraAdmin2026!';

    public static function ensureTables(): void
    {
        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS empresas (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NULL,
                name VARCHAR(191) NOT NULL,
                contact_email VARCHAR(191) NULL,
                plan VARCHAR(64) NOT NULL DEFAULT "BASIC",
                status VARCHAR(32) NOT NULL DEFAULT "ACTIVE",
                payment_status VARCHAR(32) NOT NULL DEFAULT "PAID",
                monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                next_payment_at DATE NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY empresas_tenant_unique (tenant_id),
                INDEX empresas_status_idx (status),
                INDEX empresas_payment_status_idx (payment_status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::syncFromTenants();
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
            'password_hash' => password_hash(self::PLATFORM_ADMIN_PASSWORD, PASSWORD_BCRYPT),
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

    public static function metrics(): array
    {
        self::ensureTables();
        $pdo = Database::connection();

        return [
            'active' => self::count($pdo, 'status = "ACTIVE"'),
            'trial' => self::count($pdo, 'status = "TRIAL"'),
            'payments_pending' => self::count($pdo, 'payment_status IN ("PENDING", "OVERDUE")'),
            'mrr' => (float) $pdo->query('SELECT COALESCE(SUM(monthly_price), 0) FROM empresas WHERE status IN ("ACTIVE", "TRIAL")')->fetchColumn(),
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

    public static function create(array $data): void
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare(
            'INSERT INTO empresas (id, tenant_id, name, contact_email, plan, status, payment_status, monthly_price, next_payment_at, notes, created_at, updated_at)
             VALUES (:id, NULL, :name, :contact_email, :plan, :status, :payment_status, :monthly_price, :next_payment_at, :notes, NOW(), NOW())'
        );
        $stmt->execute(self::empresaParams($data) + ['id' => cuid()]);
    }

    public static function update(string $id, array $data): void
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare(
            'UPDATE empresas
             SET name = :name,
                 contact_email = :contact_email,
                 plan = :plan,
                 status = :status,
                 payment_status = :payment_status,
                 monthly_price = :monthly_price,
                 next_payment_at = :next_payment_at,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(self::empresaParams($data) + ['id' => $id]);
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

        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'contact_email' => trim((string) ($data['contact_email'] ?? '')) ?: null,
            'plan' => strtoupper(trim((string) ($data['plan'] ?? 'BASIC'))) ?: 'BASIC',
            'status' => $status,
            'payment_status' => $paymentStatus,
            'monthly_price' => number_format(max(0, (float) $price), 2, '.', ''),
            'next_payment_at' => trim((string) ($data['next_payment_at'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    private static function tableColumns(string $table): array
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table);
        return array_map(static fn (array $column): string => $column['Field'], $stmt->fetchAll());
    }

    private static function usersTenantAllowsNull(): bool
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM users LIKE "tenant_id"');
        $column = $stmt->fetch();

        return !$column || strtoupper((string) ($column['Null'] ?? 'YES')) === 'YES';
    }

    private static function firstTenantId(): ?string
    {
        try {
            $stmt = Database::connection()->query('SELECT id FROM tenants ORDER BY created_at ASC LIMIT 1');
            return $stmt->fetchColumn() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function count(PDO $pdo, string $where): int
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM empresas WHERE {$where}");
        return (int) $stmt->fetchColumn();
    }
}

final class PlatformPaymentRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS empresa_payments (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                empresa_id VARCHAR(191) NOT NULL,
                concept VARCHAR(191) NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                status VARCHAR(32) NOT NULL DEFAULT "PENDING",
                due_at DATE NULL,
                paid_at DATE NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX empresa_payments_empresa_idx (empresa_id),
                INDEX empresa_payments_status_idx (status),
                INDEX empresa_payments_due_idx (due_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public static function metrics(): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        $paidMonth = $pdo->query(
            'SELECT COALESCE(SUM(amount), 0)
             FROM empresa_payments
             WHERE status = "PAID"
             AND paid_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'
        )->fetchColumn();

        $pending = $pdo->query(
            'SELECT COALESCE(SUM(amount), 0)
             FROM empresa_payments
             WHERE status IN ("PENDING", "OVERDUE")'
        )->fetchColumn();

        $overdue = $pdo->query(
            'SELECT COUNT(*)
             FROM empresa_payments
             WHERE status = "OVERDUE"
             OR (status = "PENDING" AND due_at IS NOT NULL AND due_at < CURDATE())'
        )->fetchColumn();

        $dueWeek = $pdo->query(
            'SELECT COUNT(*)
             FROM empresa_payments
             WHERE status = "PENDING"
             AND due_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'
        )->fetchColumn();

        return [
            'paid_month' => (float) $paidMonth,
            'pending_amount' => (float) $pending,
            'overdue' => (int) $overdue,
            'due_week' => (int) $dueWeek,
        ];
    }

    public static function all(string $query = '', string $status = ''): array
    {
        self::ensureTable();
        EmpresaRepository::ensureTables();

        $params = [];
        $where = ['1 = 1'];
        if ($query !== '') {
            $where[] = '(p.concept LIKE :query OR p.notes LIKE :query OR e.name LIKE :query OR e.contact_email LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT p.*, e.name AS empresa_name, e.contact_email
             FROM empresa_payments p
             INNER JOIN empresas e ON e.id = p.empresa_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.due_at IS NULL, p.due_at ASC, p.created_at DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function create(array $data): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'INSERT INTO empresa_payments (id, empresa_id, concept, amount, status, due_at, paid_at, notes, created_at, updated_at)
             VALUES (:id, :empresa_id, :concept, :amount, :status, :due_at, :paid_at, :notes, NOW(), NOW())'
        );
        $stmt->execute(self::paymentParams($data) + ['id' => cuid()]);
    }

    public static function update(string $id, array $data): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'UPDATE empresa_payments
             SET empresa_id = :empresa_id,
                 concept = :concept,
                 amount = :amount,
                 status = :status,
                 due_at = :due_at,
                 paid_at = :paid_at,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(self::paymentParams($data) + ['id' => $id]);
    }

    private static function paymentParams(array $data): array
    {
        $amount = str_replace(',', '.', (string) ($data['amount'] ?? '0'));
        $status = in_array($data['status'] ?? '', ['PAID', 'PENDING', 'OVERDUE', 'CANCELLED'], true) ? $data['status'] : 'PENDING';

        return [
            'empresa_id' => trim((string) ($data['empresa_id'] ?? '')),
            'concept' => trim((string) ($data['concept'] ?? '')),
            'amount' => number_format(max(0, (float) $amount), 2, '.', ''),
            'status' => $status,
            'due_at' => trim((string) ($data['due_at'] ?? '')) ?: null,
            'paid_at' => trim((string) ($data['paid_at'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }
}

final class PlatformPlanRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS saas_plans (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                code VARCHAR(64) NOT NULL UNIQUE,
                name VARCHAR(191) NOT NULL,
                monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                setup_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                max_users INT NULL,
                max_members INT NULL,
                status VARCHAR(32) NOT NULL DEFAULT "ACTIVE",
                features TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX saas_plans_status_idx (status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::seedDefaults();
    }

    public static function metrics(): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        return [
            'active' => (int) $pdo->query('SELECT COUNT(*) FROM saas_plans WHERE status = "ACTIVE"')->fetchColumn(),
            'average_price' => (float) $pdo->query('SELECT COALESCE(AVG(monthly_price), 0) FROM saas_plans WHERE status = "ACTIVE"')->fetchColumn(),
            'enterprise' => (int) $pdo->query('SELECT COUNT(*) FROM saas_plans WHERE code = "ENTERPRISE" AND status = "ACTIVE"')->fetchColumn(),
        ];
    }

    public static function all(string $query = '', string $status = ''): array
    {
        self::ensureTable();
        $params = [];
        $where = ['1 = 1'];

        if ($query !== '') {
            $where[] = '(name LIKE :query OR code LIKE :query OR features LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM saas_plans
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY monthly_price ASC, name ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::all('', 'ACTIVE') as $plan) {
            $options[$plan['code']] = $plan['name'];
        }

        return $options ?: ['BASIC' => 'Basico', 'PRO' => 'Pro', 'BUSINESS' => 'Business', 'ENTERPRISE' => 'Enterprise'];
    }

    public static function create(array $data): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'INSERT INTO saas_plans (id, code, name, monthly_price, setup_price, max_users, max_members, status, features, created_at, updated_at)
             VALUES (:id, :code, :name, :monthly_price, :setup_price, :max_users, :max_members, :status, :features, NOW(), NOW())'
        );
        $stmt->execute(self::planParams($data) + ['id' => cuid()]);
    }

    public static function update(string $id, array $data): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'UPDATE saas_plans
             SET code = :code,
                 name = :name,
                 monthly_price = :monthly_price,
                 setup_price = :setup_price,
                 max_users = :max_users,
                 max_members = :max_members,
                 status = :status,
                 features = :features,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(self::planParams($data) + ['id' => $id]);
    }

    private static function seedDefaults(): void
    {
        $pdo = Database::connection();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM saas_plans')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO saas_plans (id, code, name, monthly_price, setup_price, max_users, max_members, status, features, created_at, updated_at)
             VALUES (:id, :code, :name, :monthly_price, :setup_price, :max_users, :max_members, "ACTIVE", :features, NOW(), NOW())'
        );

        foreach ([
            ['BASIC', 'Basico', '49.00', '0.00', 3, 300, 'Leads, socios, tareas y membresias base.'],
            ['PRO', 'Pro', '89.00', '99.00', 8, 1000, 'Calendario de clases, usuarios y soporte prioritario.'],
            ['BUSINESS', 'Business', '149.00', '199.00', 20, 3000, 'Multi-equipo, reporting avanzado y soporte preferente.'],
            ['ENTERPRISE', 'Enterprise', '299.00', '499.00', null, null, 'Condiciones personalizadas para cadenas o franquicias.'],
        ] as $plan) {
            $insert->execute([
                'id' => cuid(),
                'code' => $plan[0],
                'name' => $plan[1],
                'monthly_price' => $plan[2],
                'setup_price' => $plan[3],
                'max_users' => $plan[4],
                'max_members' => $plan[5],
                'features' => $plan[6],
            ]);
        }
    }

    private static function planParams(array $data): array
    {
        $monthlyPrice = str_replace(',', '.', (string) ($data['monthly_price'] ?? '0'));
        $setupPrice = str_replace(',', '.', (string) ($data['setup_price'] ?? '0'));
        $status = in_array($data['status'] ?? '', ['ACTIVE', 'INACTIVE', 'ARCHIVED'], true) ? $data['status'] : 'ACTIVE';

        return [
            'code' => strtoupper(preg_replace('/[^A-Z0-9_]/', '', trim((string) ($data['code'] ?? '')))) ?: 'CUSTOM',
            'name' => trim((string) ($data['name'] ?? '')),
            'monthly_price' => number_format(max(0, (float) $monthlyPrice), 2, '.', ''),
            'setup_price' => number_format(max(0, (float) $setupPrice), 2, '.', ''),
            'max_users' => trim((string) ($data['max_users'] ?? '')) !== '' ? max(0, (int) $data['max_users']) : null,
            'max_members' => trim((string) ($data['max_members'] ?? '')) !== '' ? max(0, (int) $data['max_members']) : null,
            'status' => $status,
            'features' => trim((string) ($data['features'] ?? '')) ?: null,
        ];
    }
}

final class PipelineRepository
{
    public static function all(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM pipeline_stages WHERE tenant_id = :tenant_id ORDER BY `order` ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    public static function firstId(string $tenantId): ?string
    {
        $stages = self::all($tenantId);
        return $stages[0]['id'] ?? null;
    }

    public static function lostId(string $tenantId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM pipeline_stages WHERE tenant_id = :tenant_id AND `key` = "LOST" LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchColumn() ?: null;
    }

    public static function convertedId(string $tenantId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM pipeline_stages
             WHERE tenant_id = :tenant_id
             AND (`key` LIKE "%CONVERT%" OR LOWER(name) LIKE "%convert%")
             ORDER BY `order` ASC
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchColumn() ?: null;
    }

    public static function contactedId(string $tenantId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM pipeline_stages
             WHERE tenant_id = :tenant_id
             AND (`key` LIKE "%CONTACT%" OR LOWER(name) LIKE "%contact%")
             ORDER BY `order` ASC
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchColumn() ?: self::firstId($tenantId);
    }

    public static function find(string $tenantId, string $stageId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM pipeline_stages WHERE tenant_id = :tenant_id AND id = :id LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $stageId]);
        $stage = $stmt->fetch();

        return $stage ?: null;
    }

    public static function statusForStage(?array $stage): string
    {
        $key = strtoupper((string) ($stage['key'] ?? ''));
        $name = strtolower((string) ($stage['name'] ?? ''));

        if (str_contains($key, 'LOST') || str_contains($name, 'perdido')) {
            return 'LOST';
        }

        if (str_contains($key, 'CONVERT') || str_contains($name, 'convertido')) {
            return 'CONVERTED';
        }

        return 'OPEN';
    }
}

final class MemberRepository
{
    public static function all(
        string $tenantId,
        string $query = '',
        string $status = '',
        string $dateFrom = '',
        string $dateTo = '',
        int $limit = 200
    ): array
    {
        self::ensurePhotoColumn();
        MembershipRepository::ensureTables();

        $params = ['tenant_id' => $tenantId];
        $where = ['members.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(members.first_name LIKE :query OR members.last_name LIKE :query OR members.email LIKE :query OR members.phone LIKE :query OR membership_plans.name LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status === 'ACTIVE') {
            $where[] = 'members.status <> "INACTIVE"';
        } elseif ($status === 'INACTIVE') {
            $where[] = 'members.status = "INACTIVE"';
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(members.joined_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(members.joined_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $stmt = Database::connection()->prepare(
            'SELECT members.id, members.first_name, members.last_name, members.email, members.phone,
                    CASE WHEN members.status = "INACTIVE" THEN "INACTIVE" ELSE "ACTIVE" END AS status,
                    members.photo_path, members.joined_at, members.created_at, members.updated_at,
                    subscriptions.id AS subscription_id,
                    subscriptions.membership_plan_id,
                    subscriptions.starts_at AS membership_starts_at,
                    subscriptions.ends_at AS membership_ends_at,
                    membership_plans.name AS membership_name,
                    membership_plans.price AS membership_price,
                    membership_plans.billing_period AS membership_period
             FROM members
             LEFT JOIN subscriptions ON subscriptions.member_id = members.id
                AND subscriptions.tenant_id = members.tenant_id
                AND subscriptions.status = "ACTIVE"
                AND subscriptions.id = (
                    SELECT latest_subscription.id
                    FROM subscriptions latest_subscription
                    WHERE latest_subscription.member_id = members.id
                    AND latest_subscription.tenant_id = members.tenant_id
                    AND latest_subscription.status = "ACTIVE"
                    ORDER BY latest_subscription.ends_at DESC, latest_subscription.created_at DESC
                    LIMIT 1
                )
             LEFT JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY members.joined_at DESC, members.created_at DESC
             LIMIT ' . max(1, min($limit, 300))
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function metrics(string $tenantId): array
    {
        self::ensurePhotoColumn();

        $pdo = Database::connection();

        return [
            'active' => self::count($pdo, $tenantId, 'status <> "INACTIVE"'),
            'inactive' => self::count($pdo, $tenantId, 'status = "INACTIVE"'),
            'new_month' => self::count($pdo, $tenantId, 'joined_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'),
            'total' => self::count($pdo, $tenantId, '1 = 1'),
        ];
    }

    private static function count(PDO $pdo, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public static function ensurePhotoColumn(): void
    {
        try {
            $stmt = Database::connection()->query('SHOW COLUMNS FROM members LIKE "photo_path"');
            if ($stmt && $stmt->fetchColumn()) {
                return;
            }

            Database::connection()->exec('ALTER TABLE members ADD COLUMN photo_path VARCHAR(255) NULL AFTER phone');
        } catch (Throwable) {
            // The app still works without photos if the DB user cannot alter the table.
        }
    }
}

final class MembershipRepository
{
    public static function ensureTables(): void
    {
        $pdo = Database::connection();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS membership_plans (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                name VARCHAR(191) NOT NULL,
                description TEXT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                billing_period VARCHAR(32) NOT NULL DEFAULT "MONTHLY",
                duration_days INT NOT NULL DEFAULT 30,
                status VARCHAR(32) NOT NULL DEFAULT "ACTIVE",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX membership_plans_tenant_id_idx (tenant_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS subscriptions (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                member_id VARCHAR(191) NOT NULL,
                membership_plan_id VARCHAR(191) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT "ACTIVE",
                starts_at DATE NOT NULL,
                ends_at DATE NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX subscriptions_tenant_id_idx (tenant_id),
                INDEX subscriptions_member_id_idx (member_id),
                INDEX subscriptions_membership_plan_id_idx (membership_plan_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('membership_plans', 'price', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
        self::ensureColumn('membership_plans', 'billing_period', 'VARCHAR(32) NOT NULL DEFAULT "MONTHLY"');
        self::ensureColumn('membership_plans', 'duration_days', 'INT NOT NULL DEFAULT 30');
        self::ensureColumn('membership_plans', 'status', 'VARCHAR(32) NOT NULL DEFAULT "ACTIVE"');
        self::ensureColumn('subscriptions', 'membership_plan_id', 'VARCHAR(191) NULL');
        self::ensureColumn('subscriptions', 'starts_at', 'DATE NULL');
        self::ensureColumn('subscriptions', 'ends_at', 'DATE NULL');
        self::ensureColumn('subscriptions', 'status', 'VARCHAR(32) NOT NULL DEFAULT "ACTIVE"');
    }

    public static function plans(string $tenantId, string $query = '', string $status = '', int $limit = 200): array
    {
        self::ensureTables();

        $params = ['tenant_id' => $tenantId];
        $where = ['tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(name LIKE :query OR description LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM membership_plans
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY status ASC, price ASC, name ASC
             LIMIT ' . max(1, min($limit, 300))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function metrics(string $tenantId): array
    {
        self::ensureTables();
        $pdo = Database::connection();

        return [
            'plans' => self::count($pdo, 'membership_plans', $tenantId, 'status = "ACTIVE"'),
            'assigned' => self::count($pdo, 'subscriptions', $tenantId, 'status = "ACTIVE"'),
            'expiring' => self::count($pdo, 'subscriptions', $tenantId, 'status = "ACTIVE" AND ends_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'),
            'expired' => self::count($pdo, 'subscriptions', $tenantId, 'status = "ACTIVE" AND ends_at < CURDATE()'),
        ];
    }

    public static function subscriptions(string $tenantId, string $query = '', int $limit = 200): array
    {
        self::ensureTables();

        $params = ['tenant_id' => $tenantId];
        $where = ['subscriptions.tenant_id = :tenant_id', 'subscriptions.status = "ACTIVE"'];

        if ($query !== '') {
            $where[] = '(members.first_name LIKE :query OR members.last_name LIKE :query OR membership_plans.name LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        $stmt = Database::connection()->prepare(
            'SELECT subscriptions.*, members.first_name, members.last_name, membership_plans.name AS plan_name,
                    membership_plans.price, membership_plans.billing_period
             FROM subscriptions
             INNER JOIN members ON members.id = subscriptions.member_id
             INNER JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY subscriptions.ends_at ASC
             LIMIT ' . max(1, min($limit, 300))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function assignToMember(string $tenantId, string $memberId, ?string $planId, ?string $startsAt = null, ?string $endsAt = null): void
    {
        self::ensureTables();
        $pdo = Database::connection();

        $cancel = $pdo->prepare('UPDATE subscriptions SET status = "CANCELLED", updated_at = NOW() WHERE tenant_id = :tenant_id AND member_id = :member_id AND status = "ACTIVE"');
        $cancel->execute(['tenant_id' => $tenantId, 'member_id' => $memberId]);

        if (!$planId) {
            return;
        }

        $planStmt = $pdo->prepare('SELECT billing_period FROM membership_plans WHERE id = :id AND tenant_id = :tenant_id AND status = "ACTIVE" LIMIT 1');
        $planStmt->execute(['id' => $planId, 'tenant_id' => $tenantId]);
        $period = $planStmt->fetchColumn();

        if (!$period) {
            return;
        }

        $startsAt = $startsAt ?: date('Y-m-d');
        $endsAt = $endsAt ?: membership_end_date($startsAt, (string) $period);

        $stmt = $pdo->prepare(
            'INSERT INTO subscriptions (id, tenant_id, member_id, membership_plan_id, status, starts_at, ends_at, created_at, updated_at)
             VALUES (:id, :tenant_id, :member_id, :membership_plan_id, "ACTIVE", :starts_at, :ends_at, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'membership_plan_id' => $planId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    private static function count(PDO $pdo, string $table, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private static function ensureColumn(string $table, string $column, string $definition): void
    {
        try {
            $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table . ' LIKE "' . $column . '"');
            if ($stmt && $stmt->fetchColumn()) {
                return;
            }

            Database::connection()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (Throwable) {
        }
    }
}

final class LeadRepository
{
    public static function all(
        string $tenantId,
        string $query = '',
        string $stageId = '',
        string $status = '',
        string $dateFrom = '',
        string $dateTo = '',
        int $limit = 100
    ): array {
        $params = ['tenant_id' => $tenantId];
        $where = ['leads.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(leads.first_name LIKE :query OR leads.last_name LIKE :query OR leads.email LIKE :query OR leads.phone LIKE :query OR leads.interest LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($stageId !== '') {
            $where[] = 'leads.pipeline_stage_id = :stage_id';
            $params['stage_id'] = $stageId;
        }

        if ($status !== '') {
            $where[] = 'leads.status = :status';
            $params['status'] = $status;
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(leads.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(leads.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql = 'SELECT leads.*, pipeline_stages.name AS stage_name, pipeline_stages.key AS stage_key, users.name AS assigned_name
                FROM leads
                INNER JOIN pipeline_stages ON pipeline_stages.id = leads.pipeline_stage_id
                LEFT JOIN users ON users.id = leads.assigned_user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY leads.created_at DESC
                LIMIT ' . max(1, min($limit, 200));

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function notesByLeadIds(string $tenantId, array $leadIds): array
    {
        self::ensureNotesTable();

        if (!$leadIds) {
            return [];
        }

        $placeholders = [];
        $params = ['tenant_id' => $tenantId];
        foreach (array_values($leadIds) as $index => $leadId) {
            $key = 'lead_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $leadId;
        }

        $stmt = Database::connection()->prepare(
            'SELECT lead_notes.*, users.name AS user_name
             FROM lead_notes
             LEFT JOIN users ON users.id = lead_notes.user_id
             WHERE lead_notes.tenant_id = :tenant_id
             AND lead_notes.lead_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY lead_notes.created_at DESC'
        );
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll() as $note) {
            $grouped[$note['lead_id']][] = $note;
        }

        return $grouped;
    }

    public static function metrics(string $tenantId): array
    {
        $pdo = Database::connection();
        $total = self::countByStatus($pdo, $tenantId, null);
        $converted = self::countByStatus($pdo, $tenantId, 'CONVERTED');

        return [
            'open' => self::countByStatus($pdo, $tenantId, 'OPEN'),
            'converted' => $converted,
            'lost' => self::countByStatus($pdo, $tenantId, 'LOST'),
            'conversion' => $total > 0 ? (int) round(($converted / $total) * 100) : 0,
        ];
    }

    private static function countByStatus(PDO $pdo, string $tenantId, ?string $status): int
    {
        if ($status === null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE tenant_id = :tenant_id');
            $stmt->execute(['tenant_id' => $tenantId]);
            return (int) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE tenant_id = :tenant_id AND status = :status');
        $stmt->execute(['tenant_id' => $tenantId, 'status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    public static function ensureNotesTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS lead_notes (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                lead_id VARCHAR(191) NOT NULL,
                user_id VARCHAR(191) NULL,
                note TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX lead_notes_tenant_id_idx (tenant_id),
                INDEX lead_notes_lead_id_idx (lead_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }
}

final class TaskRepository
{
    public static function all(
        string $tenantId,
        string $query = '',
        string $status = '',
        string $type = '',
        string $assignedUserId = '',
        string $dateFrom = '',
        string $dateTo = '',
        int $limit = 100
    ): array
    {
        self::ensureMemberLinksTable();

        $params = ['tenant_id' => $tenantId];
        $where = ['tasks.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(tasks.title LIKE :query OR tasks.description LIKE :query OR leads.first_name LIKE :query OR leads.last_name LIKE :query OR legacy_members.first_name LIKE :query OR legacy_members.last_name LIKE :query OR users.name LIKE :query OR EXISTS (
                SELECT 1
                FROM task_members tm_search
                INNER JOIN members member_search ON member_search.id = tm_search.member_id
                WHERE tm_search.task_id = tasks.id
                AND tm_search.tenant_id = tasks.tenant_id
                AND (member_search.first_name LIKE :query OR member_search.last_name LIKE :query)
            ))';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'tasks.status = :status';
            $params['status'] = $status;
        }

        if ($type !== '') {
            $where[] = 'tasks.type = :type';
            $params['type'] = $type;
        }

        if ($assignedUserId !== '') {
            $where[] = 'tasks.assigned_user_id = :assigned_user_id';
            $params['assigned_user_id'] = $assignedUserId;
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(tasks.due_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(tasks.due_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql = 'SELECT tasks.*, users.name AS assigned_name,
                       leads.first_name AS lead_first_name, leads.last_name AS lead_last_name,
                       legacy_members.first_name AS member_first_name, legacy_members.last_name AS member_last_name,
                       (
                           SELECT GROUP_CONCAT(DISTINCT CONCAT(linked_members.first_name, " ", COALESCE(linked_members.last_name, "")) ORDER BY linked_members.first_name SEPARATOR "||")
                           FROM task_members
                           INNER JOIN members linked_members ON linked_members.id = task_members.member_id
                           WHERE task_members.task_id = tasks.id
                           AND task_members.tenant_id = tasks.tenant_id
                       ) AS linked_member_names,
                       (
                           SELECT GROUP_CONCAT(task_members.member_id SEPARATOR "||")
                           FROM task_members
                           WHERE task_members.task_id = tasks.id
                           AND task_members.tenant_id = tasks.tenant_id
                       ) AS linked_member_ids
                FROM tasks
                LEFT JOIN users ON users.id = tasks.assigned_user_id
                LEFT JOIN leads ON leads.id = tasks.lead_id
                LEFT JOIN members legacy_members ON legacy_members.id = tasks.member_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY tasks.status ASC, tasks.due_at ASC, tasks.created_at DESC
                LIMIT ' . max(1, min($limit, 200));

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function ensureMemberLinksTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS task_members (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                task_id VARCHAR(191) NOT NULL,
                member_id VARCHAR(191) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY task_members_task_member_unique (task_id, member_id),
                INDEX task_members_tenant_id_idx (tenant_id),
                INDEX task_members_task_id_idx (task_id),
                INDEX task_members_member_id_idx (member_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public static function metrics(string $tenantId): array
    {
        $pdo = Database::connection();
        return [
            'pending' => self::count($pdo, $tenantId, 'status = "PENDING"'),
            'completed' => self::count($pdo, $tenantId, 'status = "COMPLETED"'),
            'overdue' => self::count($pdo, $tenantId, 'status = "PENDING" AND due_at < NOW()'),
            'today' => self::count($pdo, $tenantId, 'DATE(due_at) = CURDATE()'),
        ];
    }

    private static function count(PDO $pdo, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }
}

final class ClassRepository
{
    public static function ensureTables(): void
    {
        $pdo = Database::connection();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS class_types (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                name VARCHAR(191) NOT NULL,
                description TEXT NULL,
                capacity INT NOT NULL DEFAULT 12,
                duration_minutes INT NOT NULL DEFAULT 60,
                status VARCHAR(32) NOT NULL DEFAULT "ACTIVE",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX class_types_tenant_id_idx (tenant_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS class_sessions (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                class_type_id VARCHAR(191) NOT NULL,
                instructor_user_id VARCHAR(191) NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NOT NULL,
                capacity INT NOT NULL DEFAULT 12,
                status VARCHAR(32) NOT NULL DEFAULT "SCHEDULED",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX class_sessions_tenant_id_idx (tenant_id),
                INDEX class_sessions_class_type_id_idx (class_type_id),
                INDEX class_sessions_starts_at_idx (starts_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('class_types', 'description', 'TEXT NULL');
        self::ensureColumn('class_types', 'capacity', 'INT NOT NULL DEFAULT 12');
        self::ensureColumn('class_types', 'duration_minutes', 'INT NOT NULL DEFAULT 60');
        self::ensureColumn('class_types', 'status', 'VARCHAR(32) NOT NULL DEFAULT "ACTIVE"');
        self::ensureColumn('class_sessions', 'class_type_id', 'VARCHAR(191) NULL');
        self::ensureColumn('class_sessions', 'instructor_user_id', 'VARCHAR(191) NULL');
        self::ensureColumn('class_sessions', 'starts_at', 'DATETIME NULL');
        self::ensureColumn('class_sessions', 'ends_at', 'DATETIME NULL');
        self::ensureColumn('class_sessions', 'capacity', 'INT NOT NULL DEFAULT 12');
        self::ensureColumn('class_sessions', 'status', 'VARCHAR(32) NOT NULL DEFAULT "SCHEDULED"');
    }

    public static function types(string $tenantId, bool $activeOnly = false): array
    {
        self::ensureTables();
        $where = ['tenant_id = :tenant_id'];
        if ($activeOnly) {
            $where[] = 'status = "ACTIVE"';
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM class_types
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    public static function sessions(
        string $tenantId,
        string $query = '',
        string $typeId = '',
        string $dateFrom = '',
        string $dateTo = '',
        int $limit = 200
    ): array {
        self::ensureTables();
        $params = ['tenant_id' => $tenantId];
        $where = ['class_sessions.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(class_types.name LIKE :query OR class_types.description LIKE :query OR users.name LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($typeId !== '') {
            $where[] = 'class_sessions.class_type_id = :type_id';
            $params['type_id'] = $typeId;
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(class_sessions.starts_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(class_sessions.starts_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $stmt = Database::connection()->prepare(
            'SELECT class_sessions.*, class_types.name AS class_name, class_types.description AS class_description,
                    class_types.duration_minutes, users.name AS instructor_name
             FROM class_sessions
             INNER JOIN class_types ON class_types.id = class_sessions.class_type_id
             LEFT JOIN users ON users.id = class_sessions.instructor_user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY class_sessions.starts_at ASC
             LIMIT ' . max(1, min($limit, 300))
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function metrics(string $tenantId): array
    {
        self::ensureTables();
        $pdo = Database::connection();

        return [
            'today' => self::count($pdo, $tenantId, 'DATE(starts_at) = CURDATE()'),
            'week' => self::count($pdo, $tenantId, 'starts_at >= CURDATE() AND starts_at < DATE_ADD(CURDATE(), INTERVAL 7 DAY)'),
            'scheduled' => self::count($pdo, $tenantId, 'status = "SCHEDULED"'),
            'types' => self::countTypes($pdo, $tenantId),
        ];
    }

    public static function calendar(string $tenantId, string $month): array
    {
        $firstDay = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01') ?: new DateTimeImmutable('first day of this month');
        $dateFrom = $firstDay->format('Y-m-d');
        $dateTo = $firstDay->modify('last day of this month')->format('Y-m-d');
        $sessions = self::sessions($tenantId, '', '', $dateFrom, $dateTo, 500);
        $days = [];

        foreach ($sessions as $session) {
            $key = date('Y-m-d', strtotime($session['starts_at']));
            $days[$key][] = $session;
        }

        return [
            'month' => $firstDay->format('Y-m'),
            'title' => month_title($firstDay->format('Y-m')),
            'first_weekday' => (int) $firstDay->format('N'),
            'days_in_month' => (int) $firstDay->format('t'),
            'sessions_by_day' => $days,
        ];
    }

    private static function count(PDO $pdo, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_sessions WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private static function countTypes(PDO $pdo, string $tenantId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM class_types WHERE tenant_id = :tenant_id AND status = "ACTIVE"');
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private static function ensureColumn(string $table, string $column, string $definition): void
    {
        try {
            $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table . ' LIKE "' . $column . '"');
            if ($stmt && $stmt->fetchColumn()) {
                return;
            }

            Database::connection()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (Throwable) {
        }
    }
}

final class GlobalSearchRepository
{
    public static function autocomplete(string $tenantId, string $query): array
    {
        $results = self::search($tenantId, $query);
        $items = [];

        foreach ($results['leads'] as $lead) {
            $name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
            $items[] = [
                'type' => 'Lead',
                'kind' => 'lead',
                'title' => $name,
                'description' => $lead['email'] ?: ($lead['phone'] ?: ($lead['interest'] ?: status_label($lead['status']))),
                'href' => 'index.php?route=leads&q=' . urlencode($query),
            ];
        }

        foreach ($results['tasks'] as $task) {
            $items[] = [
                'type' => 'Tarea',
                'kind' => 'task',
                'title' => $task['title'],
                'description' => format_date($task['due_at']) . ' - ' . ($task['assigned_name'] ?: 'Sin responsable'),
                'href' => 'index.php?route=tasks&q=' . urlencode($query),
            ];
        }

        foreach ($results['members'] as $member) {
            $name = trim($member['first_name'] . ' ' . ($member['last_name'] ?? ''));
            $items[] = [
                'type' => 'Socio',
                'kind' => 'member',
                'title' => $name,
                'description' => $member['email'] ?: ($member['phone'] ?: status_label($member['status'])),
                'href' => 'index.php?route=members&q=' . urlencode($query),
            ];
        }

        foreach ($results['memberships'] as $plan) {
            $items[] = [
                'type' => 'Membresia',
                'kind' => 'membership',
                'title' => $plan['name'],
                'description' => $plan['description'] ?: 'Plan de membresia',
                'href' => '',
            ];
        }

        foreach ($results['classes'] as $class) {
            $items[] = [
                'type' => 'Clase',
                'kind' => 'class',
                'title' => $class['name'],
                'description' => $class['description'] ?: 'Tipo de clase',
                'href' => 'index.php?route=classes&q=' . urlencode($query),
            ];
        }

        return array_slice($items, 0, 10);
    }

    public static function search(string $tenantId, string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'leads' => [],
                'tasks' => [],
                'members' => [],
                'memberships' => [],
                'classes' => [],
            ];
        }

        return [
            'leads' => LeadRepository::all($tenantId, $query, '', '', '', '', 6),
            'tasks' => TaskRepository::all($tenantId, $query, '', '', '', '', '', 6),
            'members' => self::members($tenantId, $query),
            'memberships' => self::membershipPlans($tenantId, $query),
            'classes' => self::classes($tenantId, $query),
        ];
    }

    private static function members(string $tenantId, string $query): array
    {
        return self::safeFetch(
            'SELECT id, first_name, last_name, email, phone, status
             FROM members
             WHERE tenant_id = :tenant_id
             AND (first_name LIKE :query OR last_name LIKE :query OR email LIKE :query OR phone LIKE :query)
             ORDER BY updated_at DESC, created_at DESC
             LIMIT 6',
            $tenantId,
            $query
        );
    }

    private static function membershipPlans(string $tenantId, string $query): array
    {
        if (!self::tableExists('membership_plans')) {
            return [];
        }

        return self::safeFetch(
            'SELECT id, name, description
             FROM membership_plans
             WHERE tenant_id = :tenant_id
             AND (name LIKE :query OR description LIKE :query)
             ORDER BY name ASC
             LIMIT 6',
            $tenantId,
            $query
        );
    }

    private static function classes(string $tenantId, string $query): array
    {
        if (!self::tableExists('class_types')) {
            return [];
        }

        return self::safeFetch(
            'SELECT id, name, description
             FROM class_types
             WHERE tenant_id = :tenant_id
             AND (name LIKE :query OR description LIKE :query)
             ORDER BY name ASC
             LIMIT 6',
            $tenantId,
            $query
        );
    }

    private static function safeFetch(string $sql, string $tenantId, string $query): array
    {
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'tenant_id' => $tenantId,
                'query' => '%' . $query . '%',
            ]);

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private static function tableExists(string $table): bool
    {
        try {
            $stmt = Database::connection()->prepare('SHOW TABLES LIKE :table_name');
            $stmt->execute(['table_name' => $table]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
