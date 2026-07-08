<?php

final class DashboardRepository
{
    public static function summary(string $tenantId): array
    {
        $pdo = Database::connection();
        PaymentRepository::ensureTable();
        RiskAlertRepository::ensureTable();
        RiskAlertRepository::generate($tenantId);

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
            'recentLeads' => LeadRepository::all($tenantId, '', '', '', '', '', '', 5),
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

final class DemoRepository
{
    public const CLIENT_EMAIL = 'demo.cliente@membora.crm';
    public const CLIENT_PASSWORD = 'MemboraDemo2026!';
    private const TENANT_ID = 'demo_tenant_cliente';
    private const RESET_KEY = 'client_demo';

    public static function prepareClientDemo(): void
    {
        self::ensureResetTable();
        self::ensureTenantAndUser();

        if (!self::shouldReset()) {
            return;
        }

        self::resetTenantData();
        self::seedTenantData();
        self::markReset();
    }

    public static function prepareAdminDemo(): void
    {
        EmpresaRepository::ensureTables();
        EmpresaRepository::ensurePlatformAdmin();
        PlatformPlanRepository::ensureTable();
        self::ensureTenantAndUser();
    }

    private static function ensureResetTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS demo_resets (
                demo_key VARCHAR(64) NOT NULL PRIMARY KEY,
                reset_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    private static function shouldReset(): bool
    {
        $stmt = Database::connection()->prepare('SELECT reset_at FROM demo_resets WHERE demo_key = :demo_key LIMIT 1');
        $stmt->execute(['demo_key' => self::RESET_KEY]);
        $resetAt = $stmt->fetchColumn();

        return !$resetAt || strtotime((string) $resetAt) <= strtotime('-24 hours');
    }

    private static function markReset(): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO demo_resets (demo_key, reset_at, created_at, updated_at)
             VALUES (:demo_key, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE reset_at = NOW(), updated_at = NOW()'
        );
        $stmt->execute(['demo_key' => self::RESET_KEY]);
    }

    private static function ensureTenantAndUser(): void
    {
        $pdo = Database::connection();
        TenantRepository::ensureSettingsColumns();
        UserRepository::ensureAvatarColumn();
        EmpresaRepository::ensureTables();

        $tenant = $pdo->prepare('SELECT id FROM tenants WHERE id = :id LIMIT 1');
        $tenant->execute(['id' => self::TENANT_ID]);
        if (!$tenant->fetchColumn()) {
            $insertTenant = $pdo->prepare(
                'INSERT INTO tenants (id, name, primary_color, created_at, updated_at)
                 VALUES (:id, :name, :primary_color, NOW(), NOW())'
            );
            $insertTenant->execute([
                'id' => self::TENANT_ID,
                'name' => 'Membora Demo Fitness',
                'primary_color' => '#0754d6',
            ]);
        } else {
            $updateTenant = $pdo->prepare('UPDATE tenants SET name = :name, primary_color = :primary_color, updated_at = NOW() WHERE id = :id');
            $updateTenant->execute([
                'id' => self::TENANT_ID,
                'name' => 'Membora Demo Fitness',
                'primary_color' => '#0754d6',
            ]);
        }

        self::ensurePipeline();
        $roleId = self::ensureRole('GYM_ADMIN', 'Administrador');
        $user = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $user->execute(['email' => self::CLIENT_EMAIL]);
        $userId = (string) ($user->fetchColumn() ?: cuid());
        $passwordHash = password_hash(self::CLIENT_PASSWORD, PASSWORD_BCRYPT);

        if ($userId && self::userExists($userId)) {
            $updateUser = $pdo->prepare(
                'UPDATE users
                 SET tenant_id = :tenant_id,
                     role_id = :role_id,
                     name = :name,
                     password_hash = :password_hash,
                     status = "ACTIVE",
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $updateUser->execute([
                'id' => $userId,
                'tenant_id' => self::TENANT_ID,
                'role_id' => $roleId,
                'name' => 'Administrador Demo',
                'password_hash' => $passwordHash,
            ]);
        } else {
            $insertUser = $pdo->prepare(
                'INSERT INTO users (id, tenant_id, role_id, name, email, password_hash, status, created_at, updated_at)
                 VALUES (:id, :tenant_id, :role_id, :name, :email, :password_hash, "ACTIVE", NOW(), NOW())'
            );
            $insertUser->execute([
                'id' => $userId,
                'tenant_id' => self::TENANT_ID,
                'role_id' => $roleId,
                'name' => 'Administrador Demo',
                'email' => self::CLIENT_EMAIL,
                'password_hash' => $passwordHash,
            ]);
        }

        self::ensureDemoEmpresa();
    }

    private static function userExists(string $userId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function ensureRole(string $key, string $name): string
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $roleId = $stmt->fetchColumn();
        if ($roleId) {
            return (string) $roleId;
        }

        $roleId = cuid();
        $insert = $pdo->prepare('INSERT INTO roles (id, `key`, name, created_at, updated_at) VALUES (:id, :role_key, :name, NOW(), NOW())');
        $insert->execute([
            'id' => $roleId,
            'role_key' => $key,
            'name' => $name,
        ]);

        return $roleId;
    }

    private static function ensurePipeline(): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pipeline_stages WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => self::TENANT_ID]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $stages = [
            ['NEW', 'Nuevo', 1],
            ['CONTACTED', 'Contactado', 2],
            ['CONVERTED', 'Cliente', 3],
            ['LOST', 'Perdido', 4],
        ];
        $insert = $pdo->prepare(
            'INSERT INTO pipeline_stages (id, tenant_id, `key`, name, `order`, created_at, updated_at)
             VALUES (:id, :tenant_id, :stage_key, :name, :stage_order, NOW(), NOW())'
        );
        foreach ($stages as [$key, $name, $order]) {
            $insert->execute([
                'id' => cuid(),
                'tenant_id' => self::TENANT_ID,
                'stage_key' => $key,
                'name' => $name,
                'stage_order' => $order,
            ]);
        }
    }

    private static function ensureDemoEmpresa(): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM empresas WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => self::TENANT_ID]);
        if ($stmt->fetchColumn()) {
            $update = $pdo->prepare(
                'UPDATE empresas
                 SET name = "Membora Demo Fitness",
                     contact_email = :email,
                     plan = "BASIC",
                     status = "ACTIVE",
                     payment_status = "PAID",
                     monthly_price = "49.00",
                     next_payment_at = DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
                     notes = "Empresa demo con sesion temporal de 20 minutos y datos reiniciados periodicamente.",
                     updated_at = NOW()
                 WHERE tenant_id = :tenant_id'
            );
            $update->execute(['email' => self::CLIENT_EMAIL, 'tenant_id' => self::TENANT_ID]);
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO empresas (id, tenant_id, client_id, name, contact_email, plan, status, payment_status, monthly_price, next_payment_at, notes, created_at, updated_at)
             VALUES (:id, :tenant_id, NULL, "Membora Demo Fitness", :email, "BASIC", "ACTIVE", "PAID", "49.00", DATE_ADD(CURDATE(), INTERVAL 1 MONTH), :notes, NOW(), NOW())'
        );
        $insert->execute([
            'id' => cuid(),
            'tenant_id' => self::TENANT_ID,
            'email' => self::CLIENT_EMAIL,
            'notes' => 'Empresa demo con sesion temporal de 20 minutos y datos reiniciados periodicamente.',
        ]);
    }

    private static function resetTenantData(): void
    {
        $pdo = Database::connection();
        foreach ([
            'risk_alerts',
            'audit_logs',
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
            try {
                $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE tenant_id = :tenant_id');
                $stmt->execute(['tenant_id' => self::TENANT_ID]);
            } catch (Throwable) {
            }
        }
    }

    private static function seedTenantData(): void
    {
        $pdo = Database::connection();
        MembershipRepository::ensureTables();
        PaymentRepository::ensureTable();
        ClassRepository::ensureTables();
        ReservationRepository::ensureTable();
        CheckinRepository::ensureTable();
        TaskRepository::ensureMemberLinksTable();
        RiskAlertRepository::ensureTable();
        LeadRepository::ensureNotesTable();

        $adminId = self::demoUserId();
        $stageId = PipelineRepository::firstId(self::TENANT_ID);
        $contactedStageId = PipelineRepository::contactedId(self::TENANT_ID);
        $planId = cuid();
        $memberA = cuid();
        $memberB = cuid();
        $memberC = cuid();
        $classTypeId = cuid();
        $classSessionId = cuid();
        $reservationId = cuid();

        $pdo->prepare(
            'INSERT INTO membership_plans (id, tenant_id, name, description, price, billing_period, duration_days, status, created_at, updated_at)
             VALUES (:id, :tenant_id, "Plan Basico Demo", "Acceso general al gimnasio y clases colectivas.", "39.90", "MONTHLY", 30, "ACTIVE", NOW(), NOW())'
        )->execute(['id' => $planId, 'tenant_id' => self::TENANT_ID]);

        $insertMember = $pdo->prepare(
            'INSERT INTO members (id, tenant_id, lead_id, first_name, last_name, email, phone, status, joined_at, created_at, updated_at)
             VALUES (:id, :tenant_id, NULL, :first_name, :last_name, :email, :phone, :status, :joined_at, NOW(), NOW())'
        );
        foreach ([
            [$memberA, 'Miguel', 'Torres', 'miguel.torres@example.com', '+34 600 111 222', 'ACTIVE', date('Y-m-d', strtotime('-20 days'))],
            [$memberB, 'Laura', 'Martin', 'laura.martin@example.com', '+34 600 333 444', 'ACTIVE', date('Y-m-d', strtotime('-7 days'))],
            [$memberC, 'Carlos', 'Ruiz', 'carlos.ruiz@example.com', '+34 600 555 666', 'PAYMENT_PENDING', date('Y-m-d', strtotime('-40 days'))],
        ] as [$id, $firstName, $lastName, $email, $phone, $status, $joinedAt]) {
            $insertMember->execute([
                'id' => $id,
                'tenant_id' => self::TENANT_ID,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'status' => $status,
                'joined_at' => $joinedAt,
            ]);
            MembershipRepository::assignToMember(self::TENANT_ID, $id, $planId, date('Y-m-d', strtotime('-20 days')), date('Y-m-d', strtotime('+10 days')));
        }

        $pdo->prepare(
            'INSERT INTO payments (id, tenant_id, member_id, subscription_id, amount, currency, payment_method, status, paid_at, due_at, notes, created_at, updated_at)
             VALUES (:id, :tenant_id, :member_id, NULL, "39.90", "EUR", "CARD", "PAID", CURDATE(), CURDATE(), "Pago demo registrado automaticamente.", NOW(), NOW())'
        )->execute(['id' => cuid(), 'tenant_id' => self::TENANT_ID, 'member_id' => $memberA]);
        $pdo->prepare(
            'INSERT INTO payments (id, tenant_id, member_id, subscription_id, amount, currency, payment_method, status, paid_at, due_at, notes, created_at, updated_at)
             VALUES (:id, :tenant_id, :member_id, NULL, "39.90", "EUR", "TRANSFER", "PENDING", NULL, DATE_ADD(CURDATE(), INTERVAL 3 DAY), "Pago pendiente de ejemplo.", NOW(), NOW())'
        )->execute(['id' => cuid(), 'tenant_id' => self::TENANT_ID, 'member_id' => $memberC]);

        $leadInsert = $pdo->prepare(
            'INSERT INTO leads (id, tenant_id, pipeline_stage_id, assigned_user_id, first_name, last_name, email, phone, source, interest, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :pipeline_stage_id, :assigned_user_id, :first_name, :last_name, :email, :phone, :source, :interest, "OPEN", NOW(), NOW())'
        );
        foreach ([
            ['Ana', 'Lopez', 'ana.lopez@example.com', '+34 611 111 111', 'Web', 'Entrenamiento personal', $stageId],
            ['Javier', 'Santos', 'javier.santos@example.com', '+34 622 222 222', 'Instagram', 'Clases funcionales', $contactedStageId],
        ] as [$firstName, $lastName, $email, $phone, $source, $interest, $stage]) {
            $leadInsert->execute([
                'id' => cuid(),
                'tenant_id' => self::TENANT_ID,
                'pipeline_stage_id' => $stage,
                'assigned_user_id' => $adminId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'source' => $source,
                'interest' => $interest,
            ]);
        }

        $pdo->prepare(
            'INSERT INTO class_types (id, tenant_id, name, description, capacity, duration_minutes, status, created_at, updated_at)
             VALUES (:id, :tenant_id, "Full Body Demo", "Clase colectiva de fuerza y movilidad.", 12, 50, "ACTIVE", NOW(), NOW())'
        )->execute(['id' => $classTypeId, 'tenant_id' => self::TENANT_ID]);
        $pdo->prepare(
            'INSERT INTO class_sessions (id, tenant_id, class_type_id, instructor_user_id, starts_at, ends_at, capacity, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :class_type_id, :instructor_user_id, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 18 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 19 HOUR, 12, "SCHEDULED", NOW(), NOW())'
        )->execute(['id' => $classSessionId, 'tenant_id' => self::TENANT_ID, 'class_type_id' => $classTypeId, 'instructor_user_id' => $adminId]);
        $pdo->prepare(
            'INSERT INTO reservations (id, tenant_id, member_id, class_session_id, status, created_at, cancelled_at)
             VALUES (:id, :tenant_id, :member_id, :class_session_id, "reserved", NOW(), NULL)'
        )->execute(['id' => $reservationId, 'tenant_id' => self::TENANT_ID, 'member_id' => $memberA, 'class_session_id' => $classSessionId]);
        $pdo->prepare(
            'INSERT INTO checkins (id, tenant_id, member_id, class_session_id, reservation_id, method, checked_in_at, notes, created_by_user_id, created_at)
             VALUES (:id, :tenant_id, :member_id, NULL, NULL, "MANUAL", DATE_SUB(NOW(), INTERVAL 1 DAY), "Check-in demo.", :created_by_user_id, NOW())'
        )->execute(['id' => cuid(), 'tenant_id' => self::TENANT_ID, 'member_id' => $memberB, 'created_by_user_id' => $adminId]);
        $pdo->prepare(
            'INSERT INTO tasks (id, tenant_id, assigned_user_id, member_id, title, description, type, status, due_at, created_at, updated_at)
             VALUES (:id, :tenant_id, :assigned_user_id, NULL, "Llamar a lead demo", "Seguimiento comercial de ejemplo.", "FOLLOW_UP", "PENDING", DATE_ADD(NOW(), INTERVAL 1 DAY), NOW(), NOW())'
        )->execute(['id' => cuid(), 'tenant_id' => self::TENANT_ID, 'assigned_user_id' => $adminId]);
    }

    private static function demoUserId(): string
    {
        $stmt = Database::connection()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => self::CLIENT_EMAIL]);

        return (string) $stmt->fetchColumn();
    }
}

final class AuditLogRepository
{
    private const ACTION_GROUPS = [
        'users' => [
            'label' => 'Usuarios',
            'actions' => ['create_user', 'update_user', 'delete_user'],
        ],
        'companies' => [
            'label' => 'Empresas',
            'actions' => ['create_empresa', 'update_empresa', 'renew_empresa_subscription', 'enter_empresa_crm', 'exit_empresa_crm'],
        ],
        'members' => [
            'label' => 'Socios',
            'actions' => ['create_member', 'update_member', 'delete_member'],
        ],
        'memberships' => [
            'label' => 'Membresias',
            'actions' => ['create_membership_plan', 'update_membership_plan', 'delete_membership_plan'],
        ],
        'checkins' => [
            'label' => 'Check-ins',
            'actions' => ['create_checkin', 'delete_checkin'],
        ],
        'classes' => [
            'label' => 'Clases',
            'actions' => ['create_class_type', 'create_class_session', 'update_class_session', 'delete_class_session'],
        ],
        'tasks' => [
            'label' => 'Tareas',
            'actions' => ['create_task', 'update_task', 'update_task_status', 'delete_task'],
        ],
        'alerts' => [
            'label' => 'Alertas',
            'actions' => ['update_risk_alert_status'],
        ],
        'audit' => [
            'label' => 'Auditoria',
            'actions' => ['view_audit'],
        ],
    ];

    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS audit_logs (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NULL,
                user_id VARCHAR(191) NULL,
                action VARCHAR(96) NOT NULL,
                entity_type VARCHAR(96) NULL,
                entity_id VARCHAR(191) NULL,
                route VARCHAR(96) NULL,
                ip_address VARCHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                metadata TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX audit_logs_tenant_id_idx (tenant_id),
                INDEX audit_logs_user_id_idx (user_id),
                INDEX audit_logs_action_idx (action),
                INDEX audit_logs_created_at_idx (created_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('audit_logs', 'id', 'VARCHAR(191) NULL');
        self::ensureColumn('audit_logs', 'tenant_id', 'VARCHAR(191) NULL');
        self::ensureColumn('audit_logs', 'user_id', 'VARCHAR(191) NULL');
        self::ensureColumn('audit_logs', 'action', 'VARCHAR(96) NOT NULL DEFAULT ""');
        self::ensureColumn('audit_logs', 'entity_type', 'VARCHAR(96) NULL');
        self::ensureColumn('audit_logs', 'entity_id', 'VARCHAR(191) NULL');
        self::ensureColumn('audit_logs', 'route', 'VARCHAR(96) NULL');
        self::ensureColumn('audit_logs', 'ip_address', 'VARCHAR(64) NULL');
        self::ensureColumn('audit_logs', 'user_agent', 'VARCHAR(255) NULL');
        self::ensureColumn('audit_logs', 'metadata', 'TEXT NULL');
        self::ensureColumn('audit_logs', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        self::modifyColumn('audit_logs', 'tenant_id', 'VARCHAR(191) NULL');
        self::modifyColumn('audit_logs', 'user_id', 'VARCHAR(191) NULL');
        self::modifyColumn('audit_logs', 'entity_type', 'VARCHAR(96) NULL');
        self::modifyColumn('audit_logs', 'entity_id', 'VARCHAR(191) NULL');
        self::modifyColumn('audit_logs', 'route', 'VARCHAR(96) NULL');
        self::modifyColumn('audit_logs', 'ip_address', 'VARCHAR(64) NULL');
        self::modifyColumn('audit_logs', 'user_agent', 'VARCHAR(255) NULL');
        self::modifyColumn('audit_logs', 'metadata', 'TEXT NULL');
        self::relaxLegacyRequiredColumns('audit_logs');
    }

    public static function record(string $action, array $payload = []): void
    {
        self::ensureTable();

        $user = Auth::user();
        $sanitizedPayload = self::sanitizePayload($payload);
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_logs (id, tenant_id, user_id, action, entity_type, entity_id, route, ip_address, user_agent, metadata)
             VALUES (:id, :tenant_id, :user_id, :action, :entity_type, :entity_id, :route, :ip_address, :user_agent, :metadata)'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => self::tenantIdForAudit($payload, $user),
            'user_id' => $user['id'] ?? null,
            'action' => $action,
            'entity_type' => self::entityType($action) ?? '',
            'entity_id' => self::entityId($payload) ?? '',
            'route' => trim((string) ($_GET['route'] ?? '')),
            'ip_address' => null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'metadata' => json_encode($sanitizedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public static function metrics(?string $tenantId): array
    {
        self::ensureTable();
        $tenantWhere = self::tenantWhere($tenantId);
        $where = [$tenantWhere['sql']];
        $params = $tenantWhere['params'];
        self::addBusinessActionWhere($where, $params, 'action');
        $baseWhere = implode(' AND ', $where);

        return [
            'today' => self::count($baseWhere . ' AND DATE(created_at) = CURDATE()', $params),
            'week' => self::count($baseWhere . ' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', $params),
            'writes' => self::count($baseWhere . ' AND (action LIKE "create_%" OR action LIKE "update_%" OR action LIKE "delete_%" OR action = "view_audit")', $params),
            'deletes' => self::count($baseWhere . ' AND action LIKE "delete_%"', $params),
        ];
    }

    public static function all(?string $tenantId, string $query = '', string $action = '', string $userId = '', string $dateFrom = '', string $dateTo = '', int $limit = 250): array
    {
        self::ensureTable();

        $tenantWhere = self::tenantWhere($tenantId, 'audit_logs');
        $where = [$tenantWhere['sql']];
        $params = $tenantWhere['params'];

        self::addBusinessActionWhere($where, $params);

        if ($query !== '') {
            $where[] = '(audit_logs.action LIKE :query OR audit_logs.entity_type LIKE :query OR audit_logs.entity_id LIKE :query OR audit_logs.metadata LIKE :query OR users.name LIKE :query OR users.email LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($action !== '') {
            self::addSelectedActionWhere($where, $params, $action);
        }

        if ($userId !== '') {
            $where[] = 'audit_logs.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(audit_logs.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(audit_logs.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $stmt = Database::connection()->prepare(
            'SELECT audit_logs.*, users.name AS user_name, users.email AS user_email
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY audit_logs.created_at DESC
             LIMIT ' . max(1, min($limit, 500))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function actionOptions(?string $tenantId): array
    {
        return self::actionGroupOptions();
    }

    public static function platformMetrics(string $tenantId = ''): array
    {
        self::ensureTable();
        $filter = self::platformWhere($tenantId);
        $where = [$filter['sql']];
        $params = $filter['params'];
        self::addBusinessActionWhere($where, $params, 'action');
        $baseWhere = implode(' AND ', $where);

        return [
            'today' => self::count($baseWhere . ' AND DATE(created_at) = CURDATE()', $params),
            'week' => self::count($baseWhere . ' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', $params),
            'writes' => self::count($baseWhere . ' AND (action LIKE "create_%" OR action LIKE "update_%" OR action LIKE "delete_%" OR action = "view_audit")', $params),
            'tenants' => self::countDistinctTenants($baseWhere, $params),
        ];
    }

    public static function platformAll(string $tenantId = '', string $query = '', string $action = '', string $dateFrom = '', string $dateTo = '', int $limit = 300): array
    {
        self::ensureTable();

        $filter = self::platformWhere($tenantId, 'audit_logs');
        $where = [$filter['sql']];
        $params = $filter['params'];

        self::addBusinessActionWhere($where, $params);

        if ($query !== '') {
            $where[] = '(audit_logs.action LIKE :query OR audit_logs.entity_type LIKE :query OR audit_logs.entity_id LIKE :query OR audit_logs.metadata LIKE :query OR users.name LIKE :query OR users.email LIKE :query OR tenants.name LIKE :query OR empresas.name LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($action !== '') {
            self::addSelectedActionWhere($where, $params, $action);
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(audit_logs.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(audit_logs.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $stmt = Database::connection()->prepare(
            'SELECT audit_logs.*,
                    users.name AS user_name,
                    users.email AS user_email,
                    COALESCE(empresas.name, tenants.name) AS tenant_name
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             LEFT JOIN tenants ON tenants.id = audit_logs.tenant_id
             LEFT JOIN empresas ON empresas.tenant_id = audit_logs.tenant_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY audit_logs.created_at DESC
             LIMIT ' . max(1, min($limit, 600))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function platformActionOptions(string $tenantId = ''): array
    {
        return self::actionGroupOptions();
    }

    public static function tenantOptions(): array
    {
        self::ensureTable();
        $options = ['' => 'Todas las empresas', '__platform' => 'Admin CRM'];

        try {
            EmpresaRepository::ensureTables();
            $stmt = Database::connection()->query(
                'SELECT id AS value, name
                 FROM tenants
                 UNION
                 SELECT COALESCE(tenant_id, CONCAT("empresa:", id)) AS value, name
                 FROM empresas
                 ORDER BY name ASC'
            );

            foreach ($stmt->fetchAll() as $tenant) {
                if (!empty($tenant['value'])) {
                    $options[$tenant['value']] = $tenant['name'];
                }
            }
        } catch (Throwable) {
        }

        return $options;
    }

    private static function count(string $where, array $params): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM audit_logs WHERE ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private static function countDistinctTenants(string $where, array $params): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(DISTINCT tenant_id) FROM audit_logs WHERE ' . $where . ' AND tenant_id IS NOT NULL');
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private static function tenantWhere(?string $tenantId, string $alias = ''): array
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if ($tenantId === null || $tenantId === '') {
            return ['sql' => $prefix . 'tenant_id IS NULL', 'params' => []];
        }

        return ['sql' => $prefix . 'tenant_id = :tenant_id', 'params' => ['tenant_id' => $tenantId]];
    }

    private static function platformWhere(string $tenantId = '', string $alias = ''): array
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if ($tenantId === '__platform') {
            return ['sql' => $prefix . 'tenant_id IS NULL', 'params' => []];
        }

        if (str_starts_with($tenantId, 'empresa:')) {
            return ['sql' => '0 = 1', 'params' => []];
        }

        if ($tenantId !== '') {
            return ['sql' => $prefix . 'tenant_id = :tenant_id', 'params' => ['tenant_id' => $tenantId]];
        }

        return ['sql' => '1 = 1', 'params' => []];
    }

    private static function addBusinessActionWhere(array &$where, array &$params, string $column = 'audit_logs.action'): void
    {
        $placeholders = [];
        foreach (self::businessActions() as $index => $action) {
            $key = 'business_action_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $action;
        }

        $where[] = $column . ' IN (' . implode(', ', $placeholders) . ')';
    }

    private static function addSelectedActionWhere(array &$where, array &$params, string $selected, string $column = 'audit_logs.action'): void
    {
        $actions = self::ACTION_GROUPS[$selected]['actions'] ?? [$selected];
        $placeholders = [];

        foreach ($actions as $index => $action) {
            $key = 'selected_action_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $action;
        }

        $where[] = $column . ' IN (' . implode(', ', $placeholders) . ')';
    }

    private static function actionGroupOptions(): array
    {
        $options = ['' => 'Todas'];

        foreach (self::ACTION_GROUPS as $key => $group) {
            $options[$key] = $group['label'];
        }

        return $options;
    }

    private static function businessActions(): array
    {
        $actions = [];
        foreach (self::ACTION_GROUPS as $group) {
            foreach ($group['actions'] as $action) {
                $actions[] = $action;
            }
        }

        return array_values(array_unique($actions));
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

    private static function modifyColumn(string $table, string $column, string $definition): void
    {
        try {
            Database::connection()->exec('ALTER TABLE ' . $table . ' MODIFY COLUMN ' . $column . ' ' . $definition);
        } catch (Throwable) {
        }
    }

    private static function relaxLegacyRequiredColumns(string $table): void
    {
        $managedColumns = [
            'id',
            'tenant_id',
            'user_id',
            'action',
            'entity_type',
            'entity_id',
            'route',
            'ip_address',
            'user_agent',
            'metadata',
            'created_at',
        ];

        try {
            $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table);
            foreach ($stmt->fetchAll() as $column) {
                $field = (string) ($column['Field'] ?? '');
                $type = (string) ($column['Type'] ?? '');
                $isRequired = strtoupper((string) ($column['Null'] ?? '')) === 'NO';
                $hasDefault = array_key_exists('Default', $column) && $column['Default'] !== null;
                $extra = strtolower((string) ($column['Extra'] ?? ''));

                if ($field === '' || $type === '' || in_array($field, $managedColumns, true)) {
                    continue;
                }

                if ($isRequired && !$hasDefault && !str_contains($extra, 'auto_increment')) {
                    Database::connection()->exec('ALTER TABLE `' . $table . '` MODIFY COLUMN `' . $field . '` ' . $type . ' NULL');
                }
            }
        } catch (Throwable) {
        }
    }

    private static function tenantIdForAudit(array $payload, ?array $user): ?string
    {
        if (!empty($user['tenant_id'])) {
            return (string) $user['tenant_id'];
        }

        $empresaId = trim((string) ($payload['id'] ?? $payload['empresa_id'] ?? ''));
        if ($empresaId !== '') {
            try {
                EmpresaRepository::ensureTables();
                $stmt = Database::connection()->prepare('SELECT tenant_id FROM empresas WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $empresaId]);
                $tenantId = $stmt->fetchColumn();
                if ($tenantId) {
                    return (string) $tenantId;
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    private static function sanitizePayload(array $payload): array
    {
        $blocked = ['password', 'password_hash', 'token', 'csrf', 'form_token', 'webhook_token'];
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $blocked, true) || str_contains($normalizedKey, 'password') || str_contains($normalizedKey, 'token')) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizePayload($value);
                continue;
            }

            $sanitized[$key] = is_scalar($value) || $value === null ? substr((string) $value, 0, 500) : '[unsupported]';
        }

        return $sanitized;
    }

    private static function entityType(string $action): ?string
    {
        $action = preg_replace('/^(create|update|delete|convert|mark|add|send|enter|exit|renew)_/', '', $action) ?: $action;
        return $action !== '' ? $action : null;
    }

    private static function entityId(array $payload): ?string
    {
        foreach (['id', 'member_id', 'lead_id', 'task_id', 'payment_id', 'reservation_id', 'class_session_id', 'empresa_id', 'client_id', 'plan_id'] as $key) {
            if (!empty($payload[$key]) && is_scalar($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }
}

final class RiskAlertRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS risk_alerts (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                member_id VARCHAR(191) NULL,
                lead_id VARCHAR(191) NULL,
                task_id VARCHAR(191) NULL,
                payment_id VARCHAR(191) NULL,
                class_session_id VARCHAR(191) NULL,
                type VARCHAR(64) NOT NULL,
                severity VARCHAR(32) NOT NULL DEFAULT "MEDIUM",
                status VARCHAR(32) NOT NULL DEFAULT "OPEN",
                message TEXT NOT NULL,
                detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX risk_alerts_tenant_id_idx (tenant_id),
                INDEX risk_alerts_status_idx (status),
                INDEX risk_alerts_type_idx (type),
                INDEX risk_alerts_member_id_idx (member_id),
                INDEX risk_alerts_lead_id_idx (lead_id),
                INDEX risk_alerts_task_id_idx (task_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('risk_alerts', 'payment_id', 'VARCHAR(191) NULL');
        self::ensureColumn('risk_alerts', 'class_session_id', 'VARCHAR(191) NULL');
        self::ensureColumn('risk_alerts', 'severity', 'VARCHAR(32) NOT NULL DEFAULT "MEDIUM"');
        self::ensureColumn('risk_alerts', 'status', 'VARCHAR(32) NOT NULL DEFAULT "OPEN"');
        self::ensureColumn('risk_alerts', 'message', 'TEXT NULL');
        self::ensureColumn('risk_alerts', 'detected_at', 'DATETIME NULL');
        self::ensureColumn('risk_alerts', 'resolved_at', 'DATETIME NULL');
        self::ensureColumn('risk_alerts', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        self::ensureColumn('risk_alerts', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

        foreach (['member_id', 'lead_id', 'task_id', 'payment_id', 'class_session_id'] as $field) {
            Database::connection()->exec('UPDATE risk_alerts SET ' . $field . ' = NULL WHERE ' . $field . ' = ""');
        }
    }

    public static function generate(string $tenantId): void
    {
        self::ensureTable();
        PaymentRepository::ensureTable();
        MembershipRepository::ensureTables();
        CheckinRepository::ensureTable();
        self::deduplicate($tenantId);

        $paymentAlerts = self::paymentAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'PAYMENT_OVERDUE', 'payment_id', array_column($paymentAlerts, 'payment_id'));
        foreach ($paymentAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }
        $taskAlerts = self::taskAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'TASK_OVERDUE', 'task_id', array_column($taskAlerts, 'task_id'));
        foreach ($taskAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }
        $membershipAlerts = self::membershipAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'MEMBERSHIP_EXPIRED', 'member_id', array_column($membershipAlerts, 'member_id'));
        foreach ($membershipAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }
        $inactiveMemberAlerts = self::inactiveMemberAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'MEMBER_INACTIVE', 'member_id', array_column($inactiveMemberAlerts, 'member_id'));
        foreach ($inactiveMemberAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }
        $staleLeadAlerts = self::staleLeadAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'LEAD_STALE', 'lead_id', array_column($staleLeadAlerts, 'lead_id'));
        foreach ($staleLeadAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }
        $classCapacityAlerts = self::classCapacityAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'CLASS_FULL', 'class_session_id', array_column($classCapacityAlerts, 'class_session_id'));
        foreach ($classCapacityAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }

        self::deduplicate($tenantId);
    }

    public static function metrics(string $tenantId): array
    {
        self::generate($tenantId);
        $pdo = Database::connection();

        return [
            'open' => self::count($pdo, $tenantId, 'status = "OPEN"'),
            'high' => self::count($pdo, $tenantId, 'status = "OPEN" AND severity = "HIGH"'),
            'medium' => self::count($pdo, $tenantId, 'status = "OPEN" AND severity = "MEDIUM"'),
            'resolved' => self::count($pdo, $tenantId, 'status = "RESOLVED"'),
        ];
    }

    public static function all(string $tenantId, string $query = '', string $status = 'OPEN', string $type = '', int $limit = 200): array
    {
        self::generate($tenantId);

        $params = ['tenant_id' => $tenantId];
        $where = ['risk_alerts.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(risk_alerts.message LIKE :query OR members.first_name LIKE :query OR members.last_name LIKE :query OR leads.first_name LIKE :query OR leads.last_name LIKE :query OR tasks.title LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'risk_alerts.status = :status';
            $params['status'] = $status;
        }

        if ($type !== '') {
            $where[] = 'risk_alerts.type = :type';
            $params['type'] = $type;
        }

        $stmt = Database::connection()->prepare(
            'SELECT risk_alerts.*,
                    members.first_name AS member_first_name,
                    members.last_name AS member_last_name,
                    leads.first_name AS lead_first_name,
                    leads.last_name AS lead_last_name,
                    tasks.title AS task_title,
                    payments.amount AS payment_amount,
                    class_types.name AS class_name,
                    class_sessions.starts_at AS class_starts_at
             FROM risk_alerts
             LEFT JOIN members ON members.id = risk_alerts.member_id AND members.tenant_id = risk_alerts.tenant_id
             LEFT JOIN leads ON leads.id = risk_alerts.lead_id AND leads.tenant_id = risk_alerts.tenant_id
             LEFT JOIN tasks ON tasks.id = risk_alerts.task_id AND tasks.tenant_id = risk_alerts.tenant_id
             LEFT JOIN payments ON payments.id = risk_alerts.payment_id AND payments.tenant_id = risk_alerts.tenant_id
             LEFT JOIN class_sessions ON class_sessions.id = risk_alerts.class_session_id AND class_sessions.tenant_id = risk_alerts.tenant_id
             LEFT JOIN class_types ON class_types.id = class_sessions.class_type_id AND class_types.tenant_id = risk_alerts.tenant_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(risk_alerts.status, "OPEN", "DISMISSED", "RESOLVED"),
                      FIELD(risk_alerts.severity, "HIGH", "MEDIUM", "LOW"),
                      risk_alerts.detected_at DESC
             LIMIT ' . max(1, min($limit, 300))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function updateStatus(string $tenantId, string $id, string $status): void
    {
        self::ensureTable();
        $status = in_array($status, ['OPEN', 'RESOLVED', 'DISMISSED'], true) ? $status : 'OPEN';
        $stmt = Database::connection()->prepare(
            'UPDATE risk_alerts
             SET status = :status,
                 resolved_at = CASE WHEN :status_done IN ("RESOLVED", "DISMISSED") THEN NOW() ELSE NULL END,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'status' => $status,
            'status_done' => $status,
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public static function typeOptions(): array
    {
        return [
            '' => 'Todas',
            'PAYMENT_OVERDUE' => 'Pagos vencidos',
            'TASK_OVERDUE' => 'Tareas vencidas',
            'MEMBERSHIP_EXPIRED' => 'Membresias por renovar',
            'MEMBER_INACTIVE' => 'Socios sin actividad',
            'LEAD_STALE' => 'Leads sin seguimiento',
            'CLASS_FULL' => 'Clases llenas',
        ];
    }

    private static function createIfOpenMissing(string $tenantId, array $alert): void
    {
        $alert = self::normalizeAlert($alert);
        $where = ['tenant_id = :tenant_id', 'type = :type'];
        $params = [
            'tenant_id' => $tenantId,
            'type' => $alert['type'],
        ];

        foreach (['member_id', 'lead_id', 'task_id', 'payment_id', 'class_session_id'] as $field) {
            if (!empty($alert[$field])) {
                $where[] = $field . ' = :' . $field;
                $params[$field] = $alert[$field];
            } else {
                $where[] = $field . ' IS NULL';
            }
        }

        $openWhere = array_merge($where, ['status = "OPEN"']);
        $exists = Database::connection()->prepare('SELECT COUNT(*) FROM risk_alerts WHERE ' . implode(' AND ', $openWhere));
        $exists->execute($params);
        if ((int) $exists->fetchColumn() > 0) {
            $update = Database::connection()->prepare(
                'UPDATE risk_alerts
                 SET severity = :severity,
                     message = :message,
                     updated_at = NOW()
                 WHERE ' . implode(' AND ', $openWhere)
            );
            $update->execute($params + [
                'severity' => $alert['severity'],
                'message' => $alert['message'],
            ]);
            return;
        }

        $closedWhere = array_merge($where, ['status IN ("RESOLVED", "DISMISSED")']);
        $reopen = Database::connection()->prepare(
            'UPDATE risk_alerts
             SET severity = :severity,
                 status = "OPEN",
                 message = :message,
                 detected_at = NOW(),
                 resolved_at = NULL,
                 updated_at = NOW()
             WHERE ' . implode(' AND ', $closedWhere) . '
             LIMIT 1'
        );
        $reopen->execute($params + [
            'severity' => $alert['severity'],
            'message' => $alert['message'],
        ]);
        if ($reopen->rowCount() > 0) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO risk_alerts (id, tenant_id, member_id, lead_id, task_id, payment_id, class_session_id, type, severity, status, message, detected_at, created_at, updated_at)
             VALUES (:id, :tenant_id, :member_id, :lead_id, :task_id, :payment_id, :class_session_id, :type, :severity, "OPEN", :message, NOW(), NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'member_id' => $alert['member_id'] ?? null,
            'lead_id' => $alert['lead_id'] ?? null,
            'task_id' => $alert['task_id'] ?? null,
            'payment_id' => $alert['payment_id'] ?? null,
            'class_session_id' => $alert['class_session_id'] ?? null,
            'type' => $alert['type'],
            'severity' => $alert['severity'],
            'message' => $alert['message'],
        ]);
    }

    private static function normalizeAlert(array $alert): array
    {
        foreach (['member_id', 'lead_id', 'task_id', 'payment_id', 'class_session_id'] as $field) {
            $value = trim((string) ($alert[$field] ?? ''));
            $alert[$field] = $value !== '' ? $value : null;
        }

        return $alert;
    }

    private static function deduplicate(string $tenantId): void
    {
        $pdo = Database::connection();
        $groups = $pdo->prepare(
            'SELECT type,
                    COALESCE(member_id, "") AS member_id,
                    COALESCE(lead_id, "") AS lead_id,
                    COALESCE(task_id, "") AS task_id,
                    COALESCE(payment_id, "") AS payment_id,
                    COALESCE(class_session_id, "") AS class_session_id,
                    COUNT(*) AS total
             FROM risk_alerts
             WHERE tenant_id = :tenant_id
             GROUP BY type,
                      COALESCE(member_id, ""),
                      COALESCE(lead_id, ""),
                      COALESCE(task_id, ""),
                      COALESCE(payment_id, ""),
                      COALESCE(class_session_id, "")
             HAVING total > 1
             LIMIT 200'
        );
        $groups->execute(['tenant_id' => $tenantId]);

        $delete = $pdo->prepare('DELETE FROM risk_alerts WHERE tenant_id = :tenant_id AND id = :id');
        foreach ($groups->fetchAll() as $group) {
            $params = [
                'tenant_id' => $tenantId,
                'type' => $group['type'],
                'member_id' => $group['member_id'] !== '' ? $group['member_id'] : null,
                'lead_id' => $group['lead_id'] !== '' ? $group['lead_id'] : null,
                'task_id' => $group['task_id'] !== '' ? $group['task_id'] : null,
                'payment_id' => $group['payment_id'] !== '' ? $group['payment_id'] : null,
                'class_session_id' => $group['class_session_id'] !== '' ? $group['class_session_id'] : null,
            ];
            $alerts = $pdo->prepare(
                'SELECT id
                 FROM risk_alerts
                 WHERE tenant_id = :tenant_id
                   AND type = :type
                   AND member_id <=> :member_id
                   AND lead_id <=> :lead_id
                   AND task_id <=> :task_id
                   AND payment_id <=> :payment_id
                   AND class_session_id <=> :class_session_id
                 ORDER BY FIELD(status, "OPEN", "DISMISSED", "RESOLVED"),
                          updated_at DESC,
                          created_at DESC'
            );
            $alerts->execute($params);
            $ids = array_column($alerts->fetchAll(), 'id');
            array_shift($ids);

            foreach ($ids as $id) {
                $delete->execute(['tenant_id' => $tenantId, 'id' => $id]);
            }
        }
    }

    private static function closeStaleEntityAlerts(string $tenantId, string $type, string $entityField, array $activeEntityIds): void
    {
        $activeEntityIds = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $activeEntityIds
        ))));

        $params = [
            'tenant_id' => $tenantId,
            'type' => $type,
        ];
        $where = ['tenant_id = :tenant_id', 'type = :type', 'status = "OPEN"'];

        if ($activeEntityIds) {
            $placeholders = [];
            foreach ($activeEntityIds as $index => $id) {
                $key = 'entity_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $where[] = $entityField . ' NOT IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = Database::connection()->prepare(
            'UPDATE risk_alerts
             SET status = "RESOLVED",
                 resolved_at = NOW(),
                 updated_at = NOW()
             WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
    }

    private static function paymentAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT payments.id, payments.member_id, payments.amount, payments.due_at, members.first_name, members.last_name
             FROM payments
             INNER JOIN members ON members.id = payments.member_id AND members.tenant_id = payments.tenant_id
             WHERE payments.tenant_id = :tenant_id
             AND payments.status IN ("PENDING", "OVERDUE")
             AND payments.due_at IS NOT NULL
             AND payments.due_at < CURDATE()
             LIMIT 50'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static function (array $row): array {
            $name = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
            return [
                'type' => 'PAYMENT_OVERDUE',
                'severity' => 'HIGH',
                'member_id' => $row['member_id'],
                'payment_id' => $row['id'],
                'message' => 'Pago vencido de ' . $name . ' por ' . money_amount($row['amount']) . '.',
            ];
        }, $stmt->fetchAll());
    }

    private static function taskAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, member_id, lead_id, title, due_at
             FROM tasks
             WHERE tenant_id = :tenant_id
             AND status = "PENDING"
             AND due_at IS NOT NULL
             AND due_at < NOW()
             LIMIT 50'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static fn (array $row): array => [
            'type' => 'TASK_OVERDUE',
            'severity' => 'MEDIUM',
            'member_id' => $row['member_id'] ?? null,
            'lead_id' => $row['lead_id'] ?? null,
            'task_id' => $row['id'],
            'message' => 'Tarea vencida: ' . $row['title'] . '.',
        ], $stmt->fetchAll());
    }

    private static function membershipAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT subscriptions.id, subscriptions.member_id, subscriptions.ends_at, members.first_name, members.last_name, membership_plans.name AS plan_name
             FROM subscriptions
             INNER JOIN members ON members.id = subscriptions.member_id AND members.tenant_id = subscriptions.tenant_id
             INNER JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id AND membership_plans.tenant_id = subscriptions.tenant_id
             WHERE subscriptions.tenant_id = :tenant_id
             AND subscriptions.status = "ACTIVE"
             AND subscriptions.ends_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             LIMIT 50'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static function (array $row): array {
            $name = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
            $endsAt = (string) ($row['ends_at'] ?? '');
            $isExpired = $endsAt !== '' && $endsAt < date('Y-m-d');

            return [
                'type' => 'MEMBERSHIP_EXPIRED',
                'severity' => 'HIGH',
                'member_id' => $row['member_id'],
                'message' => ($isExpired ? 'Membresia caducada de ' : 'Renovacion proxima de ') . $name . ': ' . $row['plan_name'] . ' vence el ' . format_date_short($endsAt) . '.',
            ];
        }, $stmt->fetchAll());
    }

    private static function inactiveMemberAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT members.id, members.first_name, members.last_name, members.created_at, MAX(checkins.checked_in_at) AS last_checkin
             FROM members
             LEFT JOIN checkins ON checkins.member_id = members.id AND checkins.tenant_id = members.tenant_id
             WHERE members.tenant_id = :tenant_id
             AND members.status = "ACTIVE"
             GROUP BY members.id, members.first_name, members.last_name, members.created_at
             HAVING (last_checkin IS NULL AND members.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
                 OR last_checkin < DATE_SUB(NOW(), INTERVAL 30 DAY)
             LIMIT 20'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static function (array $row): array {
            $name = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
            return [
                'type' => 'MEMBER_INACTIVE',
                'severity' => 'MEDIUM',
                'member_id' => $row['id'],
                'message' => $name . ' no tiene check-ins recientes.',
            ];
        }, $stmt->fetchAll());
    }

    private static function staleLeadAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, first_name, last_name, updated_at
             FROM leads
             WHERE tenant_id = :tenant_id
             AND status = "OPEN"
             AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
             LIMIT 50'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static function (array $row): array {
            $name = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
            return [
                'type' => 'LEAD_STALE',
                'severity' => 'LOW',
                'lead_id' => $row['id'],
                'message' => 'Lead sin seguimiento reciente: ' . $name . '.',
            ];
        }, $stmt->fetchAll());
    }

    private static function classCapacityAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT class_sessions.id, class_sessions.capacity, class_sessions.starts_at, class_types.name AS class_name,
                    COUNT(reservations.id) AS active_reservations
             FROM class_sessions
             INNER JOIN class_types ON class_types.id = class_sessions.class_type_id AND class_types.tenant_id = class_sessions.tenant_id
             LEFT JOIN reservations ON reservations.class_session_id = class_sessions.id
                AND reservations.tenant_id = class_sessions.tenant_id
                AND reservations.status IN ("reserved", "attended", "no_show")
             WHERE class_sessions.tenant_id = :tenant_id
             AND class_sessions.status = "SCHEDULED"
             AND class_sessions.starts_at >= NOW()
             GROUP BY class_sessions.id, class_sessions.capacity, class_sessions.starts_at, class_types.name
             HAVING active_reservations >= class_sessions.capacity
             LIMIT 50'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static fn (array $row): array => [
            'type' => 'CLASS_FULL',
            'severity' => 'LOW',
            'class_session_id' => $row['id'],
            'message' => 'Clase llena: ' . $row['class_name'] . ' del ' . format_date_short($row['starts_at']) . '.',
        ], $stmt->fetchAll());
    }

    private static function count(PDO $pdo, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM risk_alerts WHERE tenant_id = :tenant_id AND {$where}");
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

final class PaymentRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS payments (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                member_id VARCHAR(191) NOT NULL,
                subscription_id VARCHAR(191) NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                currency VARCHAR(8) NOT NULL DEFAULT "EUR",
                payment_method VARCHAR(32) NOT NULL DEFAULT "OTHER",
                status VARCHAR(32) NOT NULL DEFAULT "PENDING",
                paid_at DATE NULL,
                due_at DATE NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX payments_tenant_id_idx (tenant_id),
                INDEX payments_member_id_idx (member_id),
                INDEX payments_subscription_id_idx (subscription_id),
                INDEX payments_status_idx (status),
                INDEX payments_due_at_idx (due_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('payments', 'tenant_id', 'VARCHAR(191) NULL');
        self::ensureColumn('payments', 'member_id', 'VARCHAR(191) NULL');
        self::ensureColumn('payments', 'subscription_id', 'VARCHAR(191) NULL');
        self::ensureColumn('payments', 'amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
        self::ensureColumn('payments', 'currency', 'VARCHAR(8) NOT NULL DEFAULT "EUR"');
        self::ensureColumn('payments', 'payment_method', 'VARCHAR(32) NOT NULL DEFAULT "OTHER"');
        self::ensureColumn('payments', 'status', 'VARCHAR(32) NOT NULL DEFAULT "PENDING"');
        self::ensureColumn('payments', 'paid_at', 'DATE NULL');
        self::ensureColumn('payments', 'due_at', 'DATE NULL');
        self::ensureColumn('payments', 'notes', 'TEXT NULL');
        self::ensureColumn('payments', 'external_sync_status', 'VARCHAR(32) NOT NULL DEFAULT "PENDING"');
        self::ensureColumn('payments', 'external_reference', 'VARCHAR(191) NULL');
        self::ensureColumn('payments', 'external_synced_at', 'DATETIME NULL');
        self::ensureColumn('payments', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        self::ensureColumn('payments', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public static function metrics(string $tenantId): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        return [
            'paid_month' => self::sum($pdo, $tenantId, 'status = "PAID" AND paid_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'),
            'pending_amount' => self::sum($pdo, $tenantId, 'status IN ("PENDING", "OVERDUE")'),
            'pending_count' => self::count($pdo, $tenantId, 'status = "PENDING"'),
            'overdue_count' => self::count($pdo, $tenantId, 'status = "OVERDUE" OR (status = "PENDING" AND due_at IS NOT NULL AND due_at < CURDATE())'),
        ];
    }

    public static function all(string $tenantId, string $query = '', string $status = '', string $dateFrom = '', string $dateTo = '', int $limit = 200): array
    {
        self::ensureTable();
        MembershipRepository::ensureTables();

        $params = ['tenant_id' => $tenantId];
        $where = ['payments.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(members.first_name LIKE :query OR members.last_name LIKE :query OR members.email LIKE :query OR membership_plans.name LIKE :query OR payments.notes LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'payments.status = :status';
            $params['status'] = $status;
        }

        if ($dateFrom !== '') {
            $where[] = 'COALESCE(payments.due_at, payments.paid_at, DATE(payments.created_at)) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'COALESCE(payments.due_at, payments.paid_at, DATE(payments.created_at)) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $stmt = Database::connection()->prepare(
            'SELECT payments.*,
                    members.first_name,
                    members.last_name,
                    members.email,
                    members.phone,
                    membership_plans.name AS plan_name,
                    subscriptions.starts_at AS subscription_starts_at,
                    subscriptions.ends_at AS subscription_ends_at
             FROM payments
             INNER JOIN members ON members.id = payments.member_id AND members.tenant_id = payments.tenant_id
             LEFT JOIN subscriptions ON subscriptions.id = payments.subscription_id AND subscriptions.tenant_id = payments.tenant_id
             LEFT JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id AND membership_plans.tenant_id = payments.tenant_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(payments.status, "OVERDUE", "PENDING", "PAID", "CANCELLED"), payments.due_at IS NULL, payments.due_at ASC, payments.created_at DESC
             LIMIT ' . max(1, min($limit, 300))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function create(string $tenantId, array $data): void
    {
        self::ensureTable();
        $params = self::params($tenantId, $data);
        $stmt = Database::connection()->prepare(
            'INSERT INTO payments (id, tenant_id, member_id, subscription_id, amount, currency, payment_method, status, paid_at, due_at, notes, created_at, updated_at)
             VALUES (:id, :tenant_id, :member_id, :subscription_id, :amount, :currency, :payment_method, :status, :paid_at, :due_at, :notes, NOW(), NOW())'
        );
        $stmt->execute($params + ['id' => cuid()]);
    }

    public static function update(string $tenantId, string $id, array $data): void
    {
        self::ensureTable();
        $params = self::params($tenantId, $data) + ['id' => $id];
        $stmt = Database::connection()->prepare(
            'UPDATE payments
             SET member_id = :member_id,
                 subscription_id = :subscription_id,
                 amount = :amount,
                 currency = :currency,
                 payment_method = :payment_method,
                 status = :status,
                 paid_at = :paid_at,
                 due_at = :due_at,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute($params);
    }

    public static function delete(string $tenantId, string $id): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM payments WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function findWithMember(string $tenantId, string $id): ?array
    {
        self::ensureTable();
        MembershipRepository::ensureTables();

        $stmt = Database::connection()->prepare(
            'SELECT payments.*,
                    members.first_name,
                    members.last_name,
                    members.email,
                    members.phone,
                    membership_plans.name AS plan_name,
                    membership_plans.billing_period,
                    subscriptions.starts_at AS subscription_starts_at,
                    subscriptions.ends_at AS subscription_ends_at
             FROM payments
             INNER JOIN members ON members.id = payments.member_id AND members.tenant_id = payments.tenant_id
             LEFT JOIN subscriptions ON subscriptions.id = payments.subscription_id AND subscriptions.tenant_id = payments.tenant_id
             LEFT JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id AND membership_plans.tenant_id = payments.tenant_id
             WHERE payments.id = :id AND payments.tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $payment = $stmt->fetch();

        return $payment ?: null;
    }

    public static function memberOptions(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, first_name, last_name, email
             FROM members
             WHERE tenant_id = :tenant_id AND status <> "INACTIVE"
             ORDER BY first_name ASC, last_name ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function subscriptionOptions(string $tenantId): array
    {
        MembershipRepository::ensureTables();
        $stmt = Database::connection()->prepare(
            'SELECT subscriptions.id,
                    subscriptions.member_id,
                    subscriptions.starts_at,
                    subscriptions.ends_at,
                    members.first_name,
                    members.last_name,
                    membership_plans.name AS plan_name,
                    membership_plans.price
             FROM subscriptions
             INNER JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id
                AND membership_plans.tenant_id = subscriptions.tenant_id
             INNER JOIN members ON members.id = subscriptions.member_id
                AND members.tenant_id = subscriptions.tenant_id
             WHERE subscriptions.tenant_id = :tenant_id
             AND subscriptions.status = "ACTIVE"
             ORDER BY subscriptions.ends_at ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['member_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    private static function params(string $tenantId, array $data): array
    {
        $memberId = trim((string) ($data['member_id'] ?? ''));
        if ($memberId === '' || !self::memberExists($tenantId, $memberId)) {
            throw new RuntimeException('Selecciona un socio valido para registrar el pago.');
        }

        $subscriptionId = trim((string) ($data['subscription_id'] ?? '')) ?: null;
        if ($subscriptionId !== null && !self::subscriptionBelongsToMember($tenantId, $subscriptionId, $memberId)) {
            throw new RuntimeException('La membresia seleccionada no pertenece al socio.');
        }

        $status = strtoupper(trim((string) ($data['status'] ?? 'PENDING')));
        if (!in_array($status, ['PAID', 'PENDING', 'OVERDUE', 'CANCELLED'], true)) {
            $status = 'PENDING';
        }

        $method = strtoupper(trim((string) ($data['payment_method'] ?? 'OTHER')));
        if (!in_array($method, ['CASH', 'CARD', 'TRANSFER', 'BIZUM', 'OTHER'], true)) {
            $method = 'OTHER';
        }

        $amount = str_replace(',', '.', trim((string) ($data['amount'] ?? '0')));
        $paidAt = trim((string) ($data['paid_at'] ?? '')) ?: null;
        if ($status === 'PAID' && $paidAt === null) {
            $paidAt = date('Y-m-d');
        }

        return [
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'subscription_id' => $subscriptionId,
            'amount' => number_format(max(0, (float) $amount), 2, '.', ''),
            'currency' => 'EUR',
            'payment_method' => $method,
            'status' => $status,
            'paid_at' => $paidAt,
            'due_at' => trim((string) ($data['due_at'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    private static function memberExists(string $tenantId, string $memberId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM members WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function subscriptionBelongsToMember(string $tenantId, string $subscriptionId, string $memberId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM subscriptions
             WHERE id = :id AND member_id = :member_id AND tenant_id = :tenant_id'
        );
        $stmt->execute(['id' => $subscriptionId, 'member_id' => $memberId, 'tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function count(PDO $pdo, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    private static function sum(PDO $pdo, string $tenantId, string $where): float
    {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);

        return (float) $stmt->fetchColumn();
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

final class BillingIntegrationRepository
{
    public static function ensureTables(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS billing_integrations (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                provider_name VARCHAR(120) NOT NULL,
                endpoint_url VARCHAR(255) NULL,
                api_key_mask VARCHAR(64) NULL,
                status VARCHAR(32) NOT NULL DEFAULT "INACTIVE",
                export_format VARCHAR(16) NOT NULL DEFAULT "CSV",
                notes TEXT NULL,
                last_sync_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX billing_integrations_tenant_id_idx (tenant_id),
                INDEX billing_integrations_status_idx (status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS billing_sync_logs (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                integration_id VARCHAR(191) NULL,
                operation VARCHAR(32) NOT NULL,
                status VARCHAR(32) NOT NULL,
                payments_count INT NOT NULL DEFAULT 0,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                message TEXT NULL,
                payload TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX billing_sync_logs_tenant_id_idx (tenant_id),
                INDEX billing_sync_logs_operation_idx (operation),
                INDEX billing_sync_logs_status_idx (status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('billing_integrations', 'endpoint_url', 'VARCHAR(255) NULL');
        self::ensureColumn('billing_integrations', 'api_key_mask', 'VARCHAR(64) NULL');
        self::ensureColumn('billing_integrations', 'export_format', 'VARCHAR(16) NOT NULL DEFAULT "CSV"');
        self::ensureColumn('billing_integrations', 'last_sync_at', 'DATETIME NULL');
        self::ensureColumn('billing_sync_logs', 'payload', 'TEXT NULL');
        PaymentRepository::ensureTable();
    }

    public static function settings(string $tenantId): array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare('SELECT * FROM billing_integrations WHERE tenant_id = :tenant_id ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $settings = $stmt->fetch();

        return $settings ?: [
            'id' => '',
            'tenant_id' => $tenantId,
            'provider_name' => 'Proveedor externo',
            'endpoint_url' => '',
            'api_key_mask' => '',
            'status' => 'INACTIVE',
            'export_format' => 'CSV',
            'notes' => '',
            'last_sync_at' => null,
        ];
    }

    public static function saveSettings(string $tenantId, array $data): void
    {
        self::ensureTables();
        $current = self::settings($tenantId);
        $apiKey = trim((string) ($data['api_key'] ?? ''));
        $params = [
            'tenant_id' => $tenantId,
            'provider_name' => trim((string) ($data['provider_name'] ?? '')) ?: 'Proveedor externo',
            'endpoint_url' => trim((string) ($data['endpoint_url'] ?? '')) ?: null,
            'api_key_mask' => $apiKey !== '' ? self::maskToken($apiKey) : (($current['api_key_mask'] ?? '') ?: null),
            'status' => in_array(($data['status'] ?? ''), ['ACTIVE', 'INACTIVE'], true) ? $data['status'] : 'INACTIVE',
            'export_format' => in_array(($data['export_format'] ?? ''), ['CSV', 'JSON'], true) ? $data['export_format'] : 'CSV',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];

        if (!empty($current['id'])) {
            $stmt = Database::connection()->prepare(
                'UPDATE billing_integrations
                 SET provider_name = :provider_name, endpoint_url = :endpoint_url, api_key_mask = :api_key_mask,
                     status = :status, export_format = :export_format, notes = :notes, updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute($params + ['id' => $current['id']]);
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO billing_integrations (id, tenant_id, provider_name, endpoint_url, api_key_mask, status, export_format, notes, created_at, updated_at)
             VALUES (:id, :tenant_id, :provider_name, :endpoint_url, :api_key_mask, :status, :export_format, :notes, NOW(), NOW())'
        );
        $stmt->execute($params + ['id' => cuid()]);
    }

    public static function metrics(string $tenantId): array
    {
        self::ensureTables();
        return [
            'pending' => self::countPayments($tenantId, 'status = "PAID" AND external_sync_status = "PENDING"'),
            'synced' => self::countPayments($tenantId, 'external_sync_status = "SYNCED"'),
            'exported' => self::countPayments($tenantId, 'external_sync_status = "EXPORTED"'),
            'errors' => self::countLogs($tenantId, 'status = "ERROR"'),
        ];
    }

    public static function eligiblePayments(string $tenantId, int $limit = 200): array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare(
            'SELECT payments.*,
                    members.first_name,
                    members.last_name,
                    members.email,
                    membership_plans.name AS plan_name
             FROM payments
             INNER JOIN members ON members.id = payments.member_id AND members.tenant_id = payments.tenant_id
             LEFT JOIN subscriptions ON subscriptions.id = payments.subscription_id AND subscriptions.tenant_id = payments.tenant_id
             LEFT JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id AND membership_plans.tenant_id = payments.tenant_id
             WHERE payments.tenant_id = :tenant_id
             AND payments.status = "PAID"
             AND payments.external_sync_status IN ("PENDING", "EXPORTED", "ERROR")
             ORDER BY payments.paid_at DESC, payments.created_at DESC
             LIMIT ' . max(1, min($limit, 500))
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function logs(string $tenantId, int $limit = 100): array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare(
            'SELECT billing_sync_logs.*, billing_integrations.provider_name
             FROM billing_sync_logs
             LEFT JOIN billing_integrations ON billing_integrations.id = billing_sync_logs.integration_id
             WHERE billing_sync_logs.tenant_id = :tenant_id
             ORDER BY billing_sync_logs.created_at DESC
             LIMIT ' . max(1, min($limit, 200))
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function exportCsv(string $tenantId): string
    {
        $settings = self::settings($tenantId);
        $payments = self::eligiblePayments($tenantId, 500);
        $total = self::totalAmount($payments);
        $payload = self::payload($payments);

        $ids = array_column($payments, 'id');
        if ($ids) {
            self::markPayments($tenantId, $ids, 'EXPORTED', null);
        }
        self::createLog($tenantId, $settings['id'] ?: null, 'EXPORT', 'SUCCESS', count($payments), $total, 'Exportacion CSV generada.', $payload);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['id', 'socio', 'email', 'membresia', 'importe', 'moneda', 'metodo', 'pagado', 'referencia']);
        foreach ($payments as $payment) {
            fputcsv($handle, [
                $payment['id'],
                trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
                $payment['email'] ?? '',
                $payment['plan_name'] ?? '',
                $payment['amount'],
                $payment['currency'],
                payment_method_label($payment['payment_method']),
                $payment['paid_at'],
                $payment['external_reference'] ?: $payment['id'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    public static function sync(string $tenantId): array
    {
        $settings = self::settings($tenantId);
        if (($settings['status'] ?? 'INACTIVE') !== 'ACTIVE') {
            self::createLog($tenantId, $settings['id'] ?: null, 'SYNC', 'ERROR', 0, 0, 'La integracion no esta activa.', []);
            throw new RuntimeException('Activa la integracion antes de sincronizar pagos.');
        }

        $payments = self::eligiblePayments($tenantId, 500);
        $ids = array_column($payments, 'id');
        $total = self::totalAmount($payments);
        $payload = self::payload($payments);

        if ($ids) {
            self::markPayments($tenantId, $ids, 'SYNCED', 'EXT-' . date('YmdHis'));
        }

        if (!empty($settings['id'])) {
            $stmt = Database::connection()->prepare('UPDATE billing_integrations SET last_sync_at = NOW(), updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $settings['id'], 'tenant_id' => $tenantId]);
        }

        self::createLog($tenantId, $settings['id'] ?: null, 'SYNC', 'SUCCESS', count($payments), $total, 'Sincronizacion simulada completada.', $payload);

        return ['count' => count($payments), 'total' => $total];
    }

    private static function markPayments(string $tenantId, array $ids, string $status, ?string $referencePrefix): void
    {
        foreach ($ids as $id) {
            $stmt = Database::connection()->prepare(
                'UPDATE payments
                 SET external_sync_status = :status,
                     external_reference = COALESCE(external_reference, :reference),
                     external_synced_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute([
                'status' => $status,
                'reference' => $referencePrefix ? $referencePrefix . '-' . substr((string) $id, -8) : (string) $id,
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);
        }
    }

    private static function createLog(string $tenantId, ?string $integrationId, string $operation, string $status, int $count, float $total, string $message, array $payload): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO billing_sync_logs (id, tenant_id, integration_id, operation, status, payments_count, total_amount, message, payload, created_at)
             VALUES (:id, :tenant_id, :integration_id, :operation, :status, :payments_count, :total_amount, :message, :payload, NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'integration_id' => $integrationId,
            'operation' => $operation,
            'status' => $status,
            'payments_count' => $count,
            'total_amount' => number_format($total, 2, '.', ''),
            'message' => $message,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private static function payload(array $payments): array
    {
        return array_map(static function (array $payment): array {
            return [
                'id' => $payment['id'],
                'member' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
                'amount' => (float) $payment['amount'],
                'currency' => $payment['currency'],
                'paid_at' => $payment['paid_at'],
            ];
        }, $payments);
    }

    private static function totalAmount(array $payments): float
    {
        return array_reduce($payments, static fn (float $total, array $payment): float => $total + (float) $payment['amount'], 0.0);
    }

    private static function countPayments(string $tenantId, string $where): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM payments WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private static function countLogs(string $tenantId, string $where): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM billing_sync_logs WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private static function maskToken(string $token): string
    {
        $length = strlen($token);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($token, 0, 3) . str_repeat('*', max(4, $length - 6)) . substr($token, -3);
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

final class PlatformClientRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS platform_clients (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                company_name VARCHAR(191) NOT NULL,
                contact_name VARCHAR(191) NULL,
                email VARCHAR(191) NULL,
                phone VARCHAR(64) NULL,
                status VARCHAR(32) NOT NULL DEFAULT "LEAD",
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX platform_clients_status_idx (status),
                INDEX platform_clients_email_idx (email)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public static function metrics(): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        return [
            'lead' => self::count($pdo, 'status = "LEAD"'),
            'qualified' => self::count($pdo, 'status = "QUALIFIED"'),
            'customer' => self::count($pdo, 'status = "CUSTOMER"'),
            'lost' => self::count($pdo, 'status = "LOST"'),
        ];
    }

    public static function all(string $query = '', string $status = '', bool $includeLeadStatus = false): array
    {
        self::ensureTable();
        $params = [];
        $where = ['1 = 1'];

        if ($query !== '') {
            $where[] = '(company_name LIKE :query OR contact_name LIKE :query OR email LIKE :query OR phone LIKE :query OR notes LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        } elseif (!$includeLeadStatus) {
            $where[] = 'status <> "LEAD"';
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM platform_clients
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(status, "QUALIFIED", "LEAD", "CUSTOMER", "LOST"), updated_at DESC, company_name ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function find(string $id): ?array
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('SELECT * FROM platform_clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $client = $stmt->fetch();

        return $client ?: null;
    }

    public static function create(array $data): void
    {
        self::ensureTable();
        $params = self::clientParams($data);
        $clientId = cuid();
        $stmt = Database::connection()->prepare(
            'INSERT INTO platform_clients (id, company_name, contact_name, email, phone, status, notes, created_at, updated_at)
             VALUES (:id, :company_name, :contact_name, :email, :phone, :status, :notes, NOW(), NOW())'
        );
        $stmt->execute($params + ['id' => $clientId]);

        if ($params['status'] === 'LEAD') {
            self::syncLeadFromClient($clientId, $params);
        }
    }

    public static function update(string $id, array $data): void
    {
        self::ensureTable();
        $params = self::clientParams($data);
        $stmt = Database::connection()->prepare(
            'UPDATE platform_clients
             SET company_name = :company_name,
                 contact_name = :contact_name,
                 email = :email,
                 phone = :phone,
                 status = :status,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute($params + ['id' => $id]);

        if ($params['status'] === 'LEAD') {
            self::syncLeadFromClient($id, $params);
        } else {
            self::markLinkedLeadConverted($id);
        }
    }

    public static function markCustomer(string $id): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'UPDATE platform_clients SET status = "CUSTOMER", updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function delete(string $id): void
    {
        self::ensureTable();
        PlatformLeadRepository::ensureTable();
        EmpresaRepository::ensureTables();

        $pdo = Database::connection();
        $pdo->prepare('UPDATE empresas SET client_id = NULL, updated_at = NOW() WHERE client_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM platform_leads WHERE client_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM platform_clients WHERE id = :id')->execute(['id' => $id]);
    }

    private static function markLinkedLeadConverted(string $clientId): void
    {
        PlatformLeadRepository::ensureTable();
        $stmt = Database::connection()->prepare(
            'UPDATE platform_leads
             SET status = "CONVERTED",
                 converted_at = COALESCE(converted_at, NOW()),
                 updated_at = NOW()
             WHERE client_id = :client_id'
        );
        $stmt->execute(['client_id' => $clientId]);
    }

    private static function clientParams(array $data): array
    {
        $status = in_array($data['status'] ?? '', ['LEAD', 'QUALIFIED', 'CUSTOMER', 'LOST'], true) ? $data['status'] : 'LEAD';
        if (($data['contact_type'] ?? '') === 'lead') {
            $status = 'LEAD';
        } elseif (($data['contact_type'] ?? '') === 'client' && $status === 'LEAD') {
            $status = 'QUALIFIED';
        }

        return [
            'company_name' => trim((string) ($data['company_name'] ?? '')),
            'contact_name' => trim((string) ($data['contact_name'] ?? '')) ?: null,
            'email' => strtolower(trim((string) ($data['email'] ?? ''))) ?: null,
            'phone' => phone_from_post() ?: (trim((string) ($data['phone'] ?? '')) ?: null),
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    private static function syncLeadFromClient(string $clientId, array $client): void
    {
        PlatformLeadRepository::ensureTable();
        $pdo = Database::connection();

        $existing = $pdo->prepare('SELECT id FROM platform_leads WHERE client_id = :client_id LIMIT 1');
        $existing->execute(['client_id' => $clientId]);
        $leadId = $existing->fetchColumn();

        if ($leadId) {
            $update = $pdo->prepare(
                'UPDATE platform_leads
                 SET company_name = :company_name,
                     contact_name = :contact_name,
                     email = :email,
                     phone = :phone,
                     message = :message,
                     status = "NEW",
                     converted_at = NULL,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'company_name' => $client['company_name'],
                'contact_name' => $client['contact_name'] ?: $client['company_name'],
                'email' => $client['email'],
                'phone' => $client['phone'],
                'message' => $client['notes'] ?: 'Cliente devuelto a lead desde el panel CRM.',
                'id' => $leadId,
            ]);
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO platform_leads (id, company_name, contact_name, email, phone, message, status, client_id, converted_at, created_at, updated_at)
             VALUES (:id, :company_name, :contact_name, :email, :phone, :message, "NEW", :client_id, NULL, NOW(), NOW())'
        );
        $insert->execute([
            'id' => cuid(),
            'company_name' => $client['company_name'],
            'contact_name' => $client['contact_name'] ?: $client['company_name'],
            'email' => $client['email'],
            'phone' => $client['phone'],
            'message' => $client['notes'] ?: 'Cliente devuelto a lead desde el panel CRM.',
            'client_id' => $clientId,
        ]);
    }

    public static function syncLeadStatusClients(): void
    {
        self::ensureTable();
        PlatformLeadRepository::ensureTable();

        $stmt = Database::connection()->query(
            'SELECT platform_clients.*
             FROM platform_clients
             LEFT JOIN platform_leads ON platform_leads.client_id = platform_clients.id
             WHERE platform_clients.status = "LEAD"
               AND platform_leads.id IS NULL'
        );

        foreach ($stmt->fetchAll() as $client) {
            self::syncLeadFromClient((string) $client['id'], [
                'company_name' => $client['company_name'],
                'contact_name' => $client['contact_name'],
                'email' => $client['email'],
                'phone' => $client['phone'],
                'status' => 'LEAD',
                'notes' => $client['notes'],
            ]);
        }
    }

    private static function count(PDO $pdo, string $where): int
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM platform_clients WHERE {$where}");
        return (int) $stmt->fetchColumn();
    }
}

final class PlatformContactRepository
{
    public static function metrics(): array
    {
        PlatformClientRepository::syncLeadStatusClients();
        PlatformLeadRepository::ensureTable();
        PlatformClientRepository::ensureTable();

        $leadMetrics = PlatformLeadRepository::metrics();
        $clientMetrics = PlatformClientRepository::metrics();

        return [
            'new' => (int) $leadMetrics['new'],
            'qualified' => (int) $leadMetrics['qualified'] + (int) $clientMetrics['qualified'],
            'customers' => self::convertedLeadsWithoutClient() + (int) $clientMetrics['customer'],
            'lost' => (int) $leadMetrics['lost'] + (int) $clientMetrics['lost'],
        ];
    }

    public static function all(string $query = '', string $status = '', string $type = ''): array
    {
        PlatformClientRepository::syncLeadStatusClients();

        $contacts = [];
        $leadStatus = $status === 'LEAD' ? '' : self::leadStatus($status);
        $clientStatus = self::clientStatus($status);

        if (($type === '' || $type === 'lead') && ($status === '' || $status === 'LEAD' || $leadStatus !== '')) {
            foreach (PlatformLeadRepository::all($query, $leadStatus) as $lead) {
                if (!empty($lead['client_id']) && ($lead['client_status'] ?? '') !== 'LEAD') {
                    continue;
                }

                if ($lead['status'] === 'CONVERTED' && !empty($lead['client_id'])) {
                    continue;
                }

                $contacts[] = [
                    'type' => 'lead',
                    'id' => $lead['id'],
                    'company_name' => $lead['company_name'] ?: 'Sin gimnasio',
                    'contact_name' => $lead['contact_name'],
                    'email' => $lead['email'],
                    'phone' => $lead['phone'],
                    'status' => $lead['status'],
                    'status_label' => platform_lead_status_label($lead['status']),
                    'status_class' => strtolower(str_replace('_', '-', (string) $lead['status'])),
                    'notes' => $lead['message'],
                    'source_label' => 'Lead web',
                    'created_at' => $lead['created_at'],
                    'updated_at' => $lead['updated_at'],
                    'raw' => $lead,
                ];
            }
        }

        if (($type === '' || $type === 'client') && ($status === '' || $clientStatus !== '')) {
            foreach (PlatformClientRepository::all($query, $clientStatus, true) as $client) {
                if ($client['status'] === 'LEAD') {
                    continue;
                }

                $contacts[] = [
                    'type' => 'client',
                    'id' => $client['id'],
                    'company_name' => $client['company_name'],
                    'contact_name' => $client['contact_name'],
                    'email' => $client['email'],
                    'phone' => $client['phone'],
                    'status' => $client['status'],
                    'status_label' => platform_client_status_label($client['status']),
                    'status_class' => strtolower((string) $client['status']),
                    'notes' => $client['notes'],
                    'source_label' => 'Cliente CRM',
                    'created_at' => $client['created_at'],
                    'updated_at' => $client['updated_at'],
                    'raw' => $client,
                ];
            }
        }

        usort($contacts, static function (array $a, array $b): int {
            $timeA = strtotime((string) ($a['updated_at'] ?: $a['created_at'])) ?: 0;
            $timeB = strtotime((string) ($b['updated_at'] ?: $b['created_at'])) ?: 0;

            return $timeB <=> $timeA;
        });

        return $contacts;
    }

    private static function leadStatus(string $status): string
    {
        return in_array($status, ['NEW', 'CONTACTED', 'QUALIFIED', 'CONVERTED', 'LOST'], true) ? $status : '';
    }

    private static function clientStatus(string $status): string
    {
        return in_array($status, ['LEAD', 'QUALIFIED', 'CUSTOMER', 'LOST'], true) ? $status : '';
    }

    private static function convertedLeadsWithoutClient(): int
    {
        $stmt = Database::connection()->query(
            'SELECT COUNT(*)
             FROM platform_leads
             WHERE status = "CONVERTED"
               AND (client_id IS NULL OR client_id = "")'
        );

        return (int) $stmt->fetchColumn();
    }
}

final class PlatformLeadRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS platform_leads (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                company_name VARCHAR(191) NULL,
                contact_name VARCHAR(191) NOT NULL,
                email VARCHAR(191) NULL,
                phone VARCHAR(64) NULL,
                message TEXT NULL,
                source VARCHAR(64) NOT NULL DEFAULT "WEB",
                status VARCHAR(32) NOT NULL DEFAULT "NEW",
                client_id VARCHAR(191) NULL,
                source_url VARCHAR(500) NULL,
                payload_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                converted_at DATETIME NULL,
                INDEX platform_leads_status_idx (status),
                INDEX platform_leads_email_idx (email),
                INDEX platform_leads_created_at_idx (created_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public static function metrics(): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        return [
            'new' => self::count($pdo, 'status = "NEW"'),
            'contacted' => self::count($pdo, 'status = "CONTACTED"'),
            'qualified' => self::count($pdo, 'status = "QUALIFIED"'),
            'converted' => self::count($pdo, 'status = "CONVERTED"'),
            'lost' => self::count($pdo, 'status = "LOST"'),
        ];
    }

    public static function all(string $query = '', string $status = ''): array
    {
        self::ensureTable();
        $params = [];
        $where = ['1 = 1'];

        if ($query !== '') {
            $where[] = '(company_name LIKE :query OR contact_name LIKE :query OR email LIKE :query OR phone LIKE :query OR message LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT platform_leads.*,
                    platform_clients.company_name AS client_company_name,
                    platform_clients.status AS client_status
             FROM platform_leads
             LEFT JOIN platform_clients ON platform_clients.id = platform_leads.client_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(platform_leads.status, "NEW", "CONTACTED", "QUALIFIED", "CONVERTED", "LOST"), platform_leads.created_at DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function find(string $id): ?array
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('SELECT * FROM platform_leads WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $lead = $stmt->fetch();

        return $lead ?: null;
    }

    public static function createFromPayload(array $payload): string
    {
        self::ensureTable();
        $normalized = self::normalizePayload($payload);
        $existing = self::findOpenDuplicate($normalized['email'], $normalized['phone']);

        if ($existing) {
            $stmt = Database::connection()->prepare(
                'UPDATE platform_leads
                 SET message = CONCAT(COALESCE(message, ""), IF(COALESCE(message, "") = "" OR COALESCE(:message_append, "") = "", "", "\n\n"), COALESCE(:message_text, "")),
                     company_name = COALESCE(NULLIF(:company_name, ""), company_name),
                     source_url = :source_url,
                     payload_json = :payload_json,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'message_append' => $normalized['message'],
                'message_text' => $normalized['message'],
                'company_name' => $normalized['company_name'],
                'source_url' => $normalized['source_url'],
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                'id' => $existing['id'],
            ]);

            return (string) $existing['id'];
        }

        $id = cuid();
        $stmt = Database::connection()->prepare(
            'INSERT INTO platform_leads (id, company_name, contact_name, email, phone, message, source, status, source_url, payload_json, created_at, updated_at)
             VALUES (:id, :company_name, :contact_name, :email, :phone, :message, :source, "NEW", :source_url, :payload_json, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => $id,
            'company_name' => $normalized['company_name'],
            'contact_name' => $normalized['contact_name'],
            'email' => $normalized['email'],
            'phone' => $normalized['phone'],
            'message' => $normalized['message'],
            'source' => $normalized['source'],
            'source_url' => $normalized['source_url'],
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
        ]);

        return $id;
    }

    public static function update(string $id, array $data): void
    {
        self::ensureTable();
        $status = self::statusFromData($data['status'] ?? 'NEW');
        $stmt = Database::connection()->prepare(
            'UPDATE platform_leads
             SET company_name = :company_name,
                 contact_name = :contact_name,
                 email = :email,
                 phone = :phone,
                 message = :message,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'company_name' => trim((string) ($data['company_name'] ?? '')) ?: null,
            'contact_name' => trim((string) ($data['contact_name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'message' => trim((string) ($data['message'] ?? '')) ?: null,
            'status' => $status,
            'id' => $id,
        ]);
    }

    public static function convertToClient(string $id): string
    {
        self::ensureTable();
        PlatformClientRepository::ensureTable();
        $lead = self::find($id);

        if (!$lead) {
            throw new RuntimeException('No se encontro el lead.');
        }

        if (!empty($lead['client_id'])) {
            PlatformClientRepository::markCustomer((string) $lead['client_id']);
            return (string) $lead['client_id'];
        }

        $clientId = cuid();
        $stmt = Database::connection()->prepare(
            'INSERT INTO platform_clients (id, company_name, contact_name, email, phone, status, notes, created_at, updated_at)
             VALUES (:id, :company_name, :contact_name, :email, :phone, "CUSTOMER", :notes, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => $clientId,
            'company_name' => $lead['company_name'] ?: ('Centro de ' . $lead['contact_name']),
            'contact_name' => $lead['contact_name'],
            'email' => $lead['email'] ?: null,
            'phone' => $lead['phone'] ?: null,
            'notes' => trim((string) ($lead['message'] ?? '')) ?: 'Cliente creado desde lead web.',
        ]);

        $update = Database::connection()->prepare(
            'UPDATE platform_leads SET status = "CONVERTED", client_id = :client_id, converted_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $update->execute(['client_id' => $clientId, 'id' => $id]);

        return $clientId;
    }

    public static function delete(string $id): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM platform_leads WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private static function normalizePayload(array $payload): array
    {
        $name = trim((string) ($payload['nombre'] ?? $payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['apellidos'] ?? $payload['last_name'] ?? ''));
        $contactName = trim($name . ' ' . $lastName);
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $phone = trim((string) ($payload['telefono'] ?? $payload['phone'] ?? ''));
        $message = trim((string) ($payload['mensaje'] ?? $payload['message'] ?? ''));
        $company = self::companyFromPayload($payload, $message);

        if ($contactName === '') {
            $contactName = $email ?: ($phone ?: 'Lead web');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email no valido.');
        }

        if ($email === '' && $phone === '') {
            throw new RuntimeException('El lead debe incluir email o telefono.');
        }

        return [
            'company_name' => $company,
            'contact_name' => substr($contactName, 0, 191),
            'email' => $email ?: null,
            'phone' => substr($phone, 0, 64) ?: null,
            'message' => substr($message, 0, 4000) ?: null,
            'source' => substr(strtoupper((string) ($payload['origen'] ?? 'WEB')), 0, 64) ?: 'WEB',
            'source_url' => substr((string) ($payload['url_origen'] ?? $payload['source_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 500) ?: null,
        ];
    }

    private static function companyFromPayload(array $payload, string $message): ?string
    {
        $company = trim((string) ($payload['empresa'] ?? $payload['company'] ?? $payload['company_name'] ?? ''));
        if ($company !== '') {
            return substr($company, 0, 191);
        }

        if (preg_match('/Empresa\/gimnasio:\s*(.+)$/mi', $message, $matches)) {
            return substr(trim($matches[1]), 0, 191) ?: null;
        }

        return null;
    }

    private static function findOpenDuplicate(?string $email, ?string $phone): ?array
    {
        $conditions = [];
        $params = [];

        if ($email) {
            $conditions[] = 'LOWER(email) = :email';
            $params['email'] = strtolower($email);
        }

        if ($phone) {
            $conditions[] = 'phone = :phone';
            $params['phone'] = $phone;
        }

        if (!$conditions) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id FROM platform_leads
             WHERE status IN ("NEW", "CONTACTED", "QUALIFIED")
             AND (' . implode(' OR ', $conditions) . ')
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute($params);
        $lead = $stmt->fetch();

        return $lead ?: null;
    }

    private static function statusFromData(string $status): string
    {
        return in_array($status, ['NEW', 'CONTACTED', 'QUALIFIED', 'CONVERTED', 'LOST'], true) ? $status : 'NEW';
    }

    private static function count(PDO $pdo, string $where): int
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM platform_leads WHERE {$where}");
        return (int) $stmt->fetchColumn();
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
                client_id VARCHAR(191) NULL,
                name VARCHAR(191) NOT NULL,
                contact_email VARCHAR(191) NULL,
                plan VARCHAR(64) NOT NULL DEFAULT "BASIC",
                status VARCHAR(32) NOT NULL DEFAULT "ACTIVE",
                payment_status VARCHAR(32) NOT NULL DEFAULT "PAID",
                monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                next_payment_at DATE NULL,
                trial_days INT NOT NULL DEFAULT 30,
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
        self::syncFromTenants();
        self::markOverduePayments();
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
        PlatformClientRepository::ensureTable();
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

        $stmt = Database::connection()->prepare(
            'INSERT INTO empresas (id, tenant_id, client_id, name, contact_email, plan, status, payment_status, monthly_price, next_payment_at, trial_days, notes, created_at, updated_at)
             VALUES (:id, :tenant_id, :client_id, :name, :contact_email, :plan, :status, :payment_status, :monthly_price, :next_payment_at, :trial_days, :notes, NOW(), NOW())'
        );
        $stmt->execute($params + ['id' => cuid(), 'tenant_id' => $tenantId]);

        if ($client) {
            PlatformClientRepository::markCustomer($client['id']);
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
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(self::empresaParams($data) + ['id' => $id]);
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

        $nextPaymentAt = $due->modify('+1 month')->format('Y-m-d');
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
        if ($plan === 'TRIAL') {
            $status = 'TRIAL';
            $paymentStatus = 'TRIAL';
            $nextPaymentAt = null;
            $price = '0';
        } elseif ($nextPaymentAt === null && $status !== 'CANCELLED' && $plan !== '') {
            $nextPaymentAt = self::defaultNextPaymentDate();
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

    private static function defaultNextPaymentDate(): string
    {
        $today = new DateTimeImmutable('today');
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

    private static function ensureColumn(string $table, string $column, string $sql): void
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table . ' LIKE "' . $column . '"');
        if (!$stmt->fetch()) {
            Database::connection()->exec($sql);
        }
    }

    private static function createTenantAndAdmin(string $companyName, array $data, ?array $client = null): string
    {
        $companyName = trim($companyName);
        if ($companyName === '') {
            throw new RuntimeException('Indica el nombre de la empresa.');
        }

        $adminEmail = strtolower(trim((string) ($data['admin_email'] ?? '')));
        $adminName = trim((string) ($data['admin_name'] ?? '')) ?: ($client['contact_name'] ?? 'Administrador');
        $adminPassword = trim((string) ($data['admin_password'] ?? '')) ?: 'MemboraDemo2026!';
        if ($adminEmail === '' && $client) {
            $adminEmail = strtolower((string) $client['email']);
        }

        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Indica un email valido para el administrador de la empresa.');
        }

        if (strlen($adminPassword) < 8) {
            throw new RuntimeException('La contrasena del administrador debe tener al menos 8 caracteres.');
        }

        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $exists->execute(['email' => $adminEmail]);
        if ((int) $exists->fetchColumn() > 0) {
            throw new RuntimeException('Ya existe un usuario con ese email. Usa otro email para el administrador.');
        }

        $tenantId = cuid();
        $tenantColumns = self::tableColumns('tenants');
        $tenantValues = [
            'id' => $tenantId,
            'name' => $companyName,
            'slug' => self::uniqueTenantSlug($companyName),
            'primary_color' => '#0754d6',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $insertTenantColumns = array_values(array_intersect(array_keys($tenantValues), $tenantColumns));
        $tenantPlaceholders = array_map(static fn (string $column): string => ':' . $column, $insertTenantColumns);
        $tenantParams = array_intersect_key($tenantValues, array_flip($insertTenantColumns));
        $tenantInsert = $pdo->prepare(
            'INSERT INTO tenants (' . implode(', ', $insertTenantColumns) . ')
             VALUES (' . implode(', ', $tenantPlaceholders) . ')'
        );
        $tenantInsert->execute($tenantParams);

        $roleId = self::ensureGymAdminRole();
        $userColumns = self::tableColumns('users');
        $userValues = [
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
            'name' => $adminName,
            'email' => $adminEmail,
            'password_hash' => password_hash($adminPassword, PASSWORD_BCRYPT),
            'status' => 'ACTIVE',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $insertUserColumns = array_values(array_intersect(array_keys($userValues), $userColumns));
        $userPlaceholders = array_map(static fn (string $column): string => ':' . $column, $insertUserColumns);
        $userParams = array_intersect_key($userValues, array_flip($insertUserColumns));
        $userInsert = $pdo->prepare(
            'INSERT INTO users (' . implode(', ', $insertUserColumns) . ')
             VALUES (' . implode(', ', $userPlaceholders) . ')'
        );
        $userInsert->execute($userParams);

        self::seedTenantPipeline($tenantId);

        return $tenantId;
    }

    private static function ensureGymAdminRole(): string
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id FROM roles WHERE `key` IN ("GYM_ADMIN", "ADMIN") ORDER BY `key` = "GYM_ADMIN" DESC LIMIT 1');
        $roleId = $stmt->fetchColumn();
        if ($roleId) {
            return (string) $roleId;
        }

        $roleId = cuid();
        $columns = self::tableColumns('roles');
        $values = [
            'id' => $roleId,
            'key' => 'GYM_ADMIN',
            'name' => 'Administrador',
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

    private static function seedTenantPipeline(string $tenantId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pipeline_stages WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $tenantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO pipeline_stages (id, tenant_id, `key`, name, `order`, created_at, updated_at)
             VALUES (:id, :tenant_id, :key, :name, :order, NOW(), NOW())'
        );

        foreach ([
            ['NEW', 'Nuevo lead'],
            ['CONTACTED', 'Contactado'],
            ['TRIAL_SCHEDULED', 'Visita o prueba agendada'],
            ['PROPOSAL', 'Alta propuesta'],
            ['CONVERTED', 'Cliente'],
            ['LOST', 'Perdido'],
        ] as $index => $stage) {
            $insert->execute([
                'id' => cuid(),
                'tenant_id' => $tenantId,
                'key' => $stage[0],
                'name' => $stage[1],
                'order' => $index + 1,
            ]);
        }
    }

    private static function tenantSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? 'empresa'));
        return trim($slug, '-') ?: 'empresa';
    }

    private static function uniqueTenantSlug(string $name): string
    {
        $columns = self::tableColumns('tenants');
        $base = self::tenantSlug($name);
        if (!in_array('slug', $columns, true)) {
            return $base;
        }

        $pdo = Database::connection();
        $slug = $base;
        $suffix = 2;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE slug = :slug');
        while (true) {
            $stmt->execute(['slug' => $slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }

            $slug = $base . '-' . $suffix;
            $suffix++;
        }
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

    public static function findWithEmpresa(string $id): ?array
    {
        self::ensureTable();
        EmpresaRepository::ensureTables();

        $stmt = Database::connection()->prepare(
            'SELECT p.*, e.name AS empresa_name, e.contact_email, e.plan, e.monthly_price, e.status AS empresa_status
             FROM empresa_payments p
             INNER JOIN empresas e ON e.id = p.empresa_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $payment = $stmt->fetch();

        return $payment ?: null;
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
                discount_price DECIMAL(10,2) NULL,
                discount_label VARCHAR(120) NULL,
                max_users INT NULL,
                max_members INT NULL,
                status VARCHAR(32) NOT NULL DEFAULT "ACTIVE",
                features TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX saas_plans_status_idx (status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('discount_price', 'ALTER TABLE saas_plans ADD COLUMN discount_price DECIMAL(10,2) NULL AFTER setup_price');
        self::ensureColumn('discount_label', 'ALTER TABLE saas_plans ADD COLUMN discount_label VARCHAR(120) NULL AFTER discount_price');
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

        return $options ?: ['TRIAL' => 'Prueba', 'BASIC' => 'Basico', 'PRO' => 'Pro', 'BUSINESS' => 'Business', 'ENTERPRISE' => 'Enterprise'];
    }

    public static function priceMap(): array
    {
        $prices = [];
        foreach (self::all('', 'ACTIVE') as $plan) {
            $prices[$plan['code']] = number_format(self::effectiveMonthlyPrice($plan), 2, '.', '');
        }

        return $prices ?: [
            'TRIAL' => '0.00',
            'BASIC' => '49.00',
            'PRO' => '89.00',
            'BUSINESS' => '149.00',
            'ENTERPRISE' => '299.00',
        ];
    }

    public static function create(array $data): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'INSERT INTO saas_plans (id, code, name, monthly_price, setup_price, discount_price, discount_label, max_users, max_members, status, features, created_at, updated_at)
             VALUES (:id, :code, :name, :monthly_price, :setup_price, :discount_price, :discount_label, :max_users, :max_members, :status, :features, NOW(), NOW())'
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
                 discount_price = :discount_price,
                 discount_label = :discount_label,
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
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO saas_plans (id, code, name, monthly_price, setup_price, max_users, max_members, status, features, created_at, updated_at)
             VALUES (:id, :code, :name, :monthly_price, :setup_price, :max_users, :max_members, "ACTIVE", :features, NOW(), NOW())'
        );

        foreach ([
            ['TRIAL', 'Prueba', '0.00', '0.00', 2, 100, 'Plan de prueba configurable sin cobro ni renovacion automatica.'],
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

        $pdo->exec(
            'UPDATE saas_plans
             SET name = "Prueba",
                 monthly_price = 0,
                 setup_price = 0,
                 status = "ACTIVE",
                 features = "Plan de prueba configurable sin cobro ni renovacion automatica.",
                 updated_at = NOW()
             WHERE code = "TRIAL"'
        );
    }

    private static function planParams(array $data): array
    {
        $monthlyPrice = str_replace(',', '.', (string) ($data['monthly_price'] ?? '0'));
        $setupPrice = str_replace(',', '.', (string) ($data['setup_price'] ?? '0'));
        $discountPrice = str_replace(',', '.', trim((string) ($data['discount_price'] ?? '')));
        $status = in_array($data['status'] ?? '', ['ACTIVE', 'INACTIVE', 'ARCHIVED'], true) ? $data['status'] : 'ACTIVE';
        $monthly = max(0, (float) $monthlyPrice);
        $discount = $discountPrice !== '' ? max(0, (float) $discountPrice) : null;
        if ($discount !== null && ($discount <= 0 || $discount >= $monthly)) {
            $discount = null;
        }

        return [
            'code' => strtoupper(preg_replace('/[^A-Z0-9_]/', '', trim((string) ($data['code'] ?? '')))) ?: 'CUSTOM',
            'name' => trim((string) ($data['name'] ?? '')),
            'monthly_price' => number_format($monthly, 2, '.', ''),
            'setup_price' => number_format(max(0, (float) $setupPrice), 2, '.', ''),
            'discount_price' => $discount !== null ? number_format($discount, 2, '.', '') : null,
            'discount_label' => trim((string) ($data['discount_label'] ?? '')) ?: null,
            'max_users' => trim((string) ($data['max_users'] ?? '')) !== '' ? max(0, (int) $data['max_users']) : null,
            'max_members' => trim((string) ($data['max_members'] ?? '')) !== '' ? max(0, (int) $data['max_members']) : null,
            'status' => $status,
            'features' => trim((string) ($data['features'] ?? '')) ?: null,
        ];
    }

    public static function publicPlans(): array
    {
        $plans = [];
        foreach (self::all('', 'ACTIVE') as $plan) {
            if (strtoupper((string) $plan['code']) === 'TRIAL') {
                continue;
            }

            $effectivePrice = self::effectiveMonthlyPrice($plan);
            $monthlyPrice = (float) $plan['monthly_price'];
            $features = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+|;/', (string) ($plan['features'] ?? '')) ?: [])));
            $plans[] = [
                'code' => $plan['code'],
                'name' => $plan['name'],
                'monthly_price' => number_format($effectivePrice, 2, '.', ''),
                'original_monthly_price' => $effectivePrice < $monthlyPrice ? number_format($monthlyPrice, 2, '.', '') : null,
                'setup_price' => number_format((float) $plan['setup_price'], 2, '.', ''),
                'discount_label' => $effectivePrice < $monthlyPrice ? ($plan['discount_label'] ?: 'Oferta activa') : null,
                'max_users' => $plan['max_users'] !== null ? (int) $plan['max_users'] : null,
                'max_members' => $plan['max_members'] !== null ? (int) $plan['max_members'] : null,
                'features' => $features,
            ];
        }

        return $plans;
    }

    private static function effectiveMonthlyPrice(array $plan): float
    {
        $monthlyPrice = (float) ($plan['monthly_price'] ?? 0);
        $discountPrice = isset($plan['discount_price']) ? (float) $plan['discount_price'] : 0.0;

        return $discountPrice > 0 && $discountPrice < $monthlyPrice ? $discountPrice : $monthlyPrice;
    }

    private static function ensureColumn(string $column, string $sql): void
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM saas_plans LIKE ' . Database::connection()->quote($column));
        if (!$stmt->fetch()) {
            Database::connection()->exec($sql);
        }
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

    public static function renewMemberSubscription(string $tenantId, string $memberId): void
    {
        self::ensureTables();
        PaymentRepository::ensureTable();
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT subscriptions.*,
                    members.first_name,
                    members.last_name,
                    membership_plans.name AS plan_name,
                    membership_plans.price,
                    membership_plans.billing_period
             FROM subscriptions
             INNER JOIN members ON members.id = subscriptions.member_id AND members.tenant_id = subscriptions.tenant_id
             INNER JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id AND membership_plans.tenant_id = subscriptions.tenant_id
             WHERE subscriptions.tenant_id = :tenant_id
             AND subscriptions.member_id = :member_id
             AND subscriptions.status = "ACTIVE"
             ORDER BY subscriptions.ends_at DESC, subscriptions.created_at DESC
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'member_id' => $memberId]);
        $subscription = $stmt->fetch();

        if (!$subscription) {
            throw new RuntimeException('El socio no tiene una membresia activa para renovar.');
        }

        $endsAt = trim((string) ($subscription['ends_at'] ?? ''));
        if ($endsAt === '') {
            throw new RuntimeException('La membresia no tiene fecha de caducidad.');
        }

        $currentEnd = DateTimeImmutable::createFromFormat('Y-m-d', $endsAt);
        if (!$currentEnd) {
            throw new RuntimeException('La fecha de caducidad de la membresia no es valida.');
        }

        $today = new DateTimeImmutable('today');
        $nextWeek = $today->modify('+7 days');
        if ($currentEnd > $nextWeek) {
            throw new RuntimeException('La renovacion solo esta disponible cuando la membresia vence en los proximos 7 dias o esta vencida.');
        }

        $baseDate = $currentEnd > $today ? $currentEnd : $today;
        $newEndsAt = membership_end_date($baseDate->format('Y-m-d'), (string) ($subscription['billing_period'] ?? 'MONTHLY'));
        $amount = number_format(max(0, (float) ($subscription['price'] ?? 0)), 2, '.', '');
        $memberName = trim((string) ($subscription['first_name'] ?? '') . ' ' . (string) ($subscription['last_name'] ?? ''));
        $concept = 'Renovacion de membresia - ' . ($subscription['plan_name'] ?? 'Membresia');

        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(
                'UPDATE subscriptions
                 SET ends_at = :ends_at,
                     updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $update->execute([
                'ends_at' => $newEndsAt,
                'id' => $subscription['id'],
                'tenant_id' => $tenantId,
            ]);

            $payment = $pdo->prepare(
                'INSERT INTO payments (id, tenant_id, member_id, subscription_id, amount, currency, payment_method, status, paid_at, due_at, notes, created_at, updated_at)
                 VALUES (:id, :tenant_id, :member_id, :subscription_id, :amount, "EUR", "OTHER", "PAID", CURDATE(), :due_at, :notes, NOW(), NOW())'
            );
            $payment->execute([
                'id' => cuid(),
                'tenant_id' => $tenantId,
                'member_id' => $memberId,
                'subscription_id' => $subscription['id'],
                'amount' => $amount,
                'due_at' => $currentEnd->format('Y-m-d'),
                'notes' => $concept . ' de ' . ($memberName ?: 'socio') . '. Nueva caducidad: ' . $newEndsAt . '.',
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
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
        string $source = '',
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

        if ($source !== '') {
            $where[] = 'leads.source = :source';
            $params['source'] = $source;
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
                token_preview VARCHAR(32) NOT NULL,
                token_encrypted TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                regenerated_at DATETIME NULL,
                UNIQUE KEY webhook_settings_tenant_unique (tenant_id),
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
    }

    public static function settings(string $tenantId): array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare('SELECT * FROM webhook_settings WHERE tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $settings = $stmt->fetch();

        if ($settings) {
            $settings['token'] = self::decryptToken((string) ($settings['token_encrypted'] ?? '')) ?: null;
            return $settings;
        }

        $token = self::newToken();
        $settings = [
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'token_hash' => password_hash($token, PASSWORD_BCRYPT),
            'token_preview' => self::tokenPreview($token),
            'token_encrypted' => self::encryptToken($token),
            'is_active' => 1,
        ];

        $insert = Database::connection()->prepare(
            'INSERT INTO webhook_settings (id, tenant_id, token_hash, token_preview, token_encrypted, is_active, created_at, updated_at, regenerated_at)
             VALUES (:id, :tenant_id, :token_hash, :token_preview, :token_encrypted, 1, NOW(), NOW(), NOW())'
        );
        $insert->execute([
            'id' => $settings['id'],
            'tenant_id' => $settings['tenant_id'],
            'token_hash' => $settings['token_hash'],
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
                 token_preview = :token_preview,
                 token_encrypted = :token_encrypted,
                 regenerated_at = NOW(),
                 updated_at = NOW()
             WHERE tenant_id = :tenant_id'
        );
        $stmt->execute([
            'token_hash' => password_hash($token, PASSWORD_BCRYPT),
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
                'message' => $normalized['was_duplicate'] ? 'Lead recibido correctamente. Ya existia en el CRM.' : 'Lead recibido correctamente',
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
        $stmt = Database::connection()->query('SELECT * FROM webhook_settings');
        foreach ($stmt->fetchAll() as $settings) {
            if (password_verify($token, (string) $settings['token_hash'])) {
                return $settings;
            }
        }

        return null;
    }

    private static function isAllowedWebsiteOrigin(): bool
    {
        $origin = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
        $referer = rtrim((string) ($_SERVER['HTTP_REFERER'] ?? ''), '/');
        $allowed = array_filter([
            rtrim((string) (getenv('WEB_APP_URL') ?: 'https://app.web.josehurtado.dev'), '/'),
            rtrim((string) (getenv('APP_WEB_URL') ?: ''), '/'),
        ]);

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

    private static function isPlatformRateLimited(string $ip): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM webhook_logs
             WHERE tenant_id IS NULL
             AND ip_address = :ip_address
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
        $seed = (getenv('APP_KEY') ?: '') . '|' . (getenv('DB_PASSWORD') ?: '') . '|' . (getenv('DATABASE_URL') ?: '');
        return hash('sha256', $seed ?: 'membora-crm-local-key', true);
    }

    private static function encryptToken(string $token): ?string
    {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($token);
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
            $where[] = '(tasks.title LIKE :query OR tasks.description LIKE :query OR users.name LIKE :query)';
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

        $sql = 'SELECT tasks.*, users.name AS assigned_name
                FROM tasks
                LEFT JOIN users ON users.id = tasks.assigned_user_id
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

final class ReservationRepository
{
    private const ACTIVE_STATUSES = ['reserved', 'attended', 'no_show'];

    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS reservations (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                member_id VARCHAR(191) NOT NULL,
                class_session_id VARCHAR(191) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT "reserved",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                cancelled_at DATETIME NULL,
                INDEX reservations_tenant_id_idx (tenant_id),
                INDEX reservations_member_id_idx (member_id),
                INDEX reservations_class_session_id_idx (class_session_id),
                UNIQUE KEY reservations_session_member_unique (tenant_id, class_session_id, member_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public static function bySessionIds(string $tenantId, array $sessionIds): array
    {
        self::ensureTable();

        if (!$sessionIds) {
            return [];
        }

        $params = ['tenant_id' => $tenantId];
        $placeholders = [];
        foreach (array_values(array_unique($sessionIds)) as $index => $sessionId) {
            $key = 'session_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $sessionId;
        }

        $stmt = Database::connection()->prepare(
            'SELECT reservations.*, members.first_name, members.last_name, members.email, members.phone
             FROM reservations
             INNER JOIN members ON members.id = reservations.member_id AND members.tenant_id = reservations.tenant_id
             WHERE reservations.tenant_id = :tenant_id
             AND reservations.class_session_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY FIELD(reservations.status, "reserved", "attended", "no_show", "cancelled"), reservations.created_at DESC'
        );
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll() as $reservation) {
            $grouped[$reservation['class_session_id']][] = $reservation;
        }

        return $grouped;
    }

    public static function byMemberIds(string $tenantId, array $memberIds): array
    {
        self::ensureTable();

        if (!$memberIds) {
            return [];
        }

        $params = ['tenant_id' => $tenantId];
        $placeholders = [];
        foreach (array_values(array_unique($memberIds)) as $index => $memberId) {
            $key = 'member_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $memberId;
        }

        $stmt = Database::connection()->prepare(
            'SELECT reservations.*, class_sessions.starts_at, class_sessions.ends_at,
                    class_types.name AS class_name, users.name AS instructor_name
             FROM reservations
             INNER JOIN class_sessions ON class_sessions.id = reservations.class_session_id
                AND class_sessions.tenant_id = reservations.tenant_id
             INNER JOIN class_types ON class_types.id = class_sessions.class_type_id
                AND class_types.tenant_id = reservations.tenant_id
             LEFT JOIN users ON users.id = class_sessions.instructor_user_id
                AND users.tenant_id = reservations.tenant_id
             WHERE reservations.tenant_id = :tenant_id
             AND reservations.member_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY class_sessions.starts_at DESC, reservations.created_at DESC'
        );
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll() as $reservation) {
            $grouped[$reservation['member_id']][] = $reservation;
        }

        return $grouped;
    }

    public static function activeCount(string $tenantId, string $sessionId): int
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM reservations
             WHERE tenant_id = :tenant_id
             AND class_session_id = :class_session_id
             AND status IN ("reserved", "attended", "no_show")'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'class_session_id' => $sessionId]);
        return (int) $stmt->fetchColumn();
    }

    public static function sessionCapacity(string $tenantId, string $sessionId): ?int
    {
        $stmt = Database::connection()->prepare('SELECT capacity FROM class_sessions WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $sessionId, 'tenant_id' => $tenantId]);
        $capacity = $stmt->fetchColumn();
        return $capacity === false ? null : (int) $capacity;
    }

    public static function create(string $tenantId, string $memberId, string $sessionId): void
    {
        self::ensureTable();
        $pdo = Database::connection();

        $memberStmt = $pdo->prepare('SELECT id FROM members WHERE id = :id AND tenant_id = :tenant_id AND status <> "INACTIVE" LIMIT 1');
        $memberStmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);
        if (!$memberStmt->fetchColumn()) {
            throw new RuntimeException('Selecciona un socio activo para reservar.');
        }

        $capacity = self::sessionCapacity($tenantId, $sessionId);
        if ($capacity === null) {
            throw new RuntimeException('La clase seleccionada no existe.');
        }

        $existingStmt = $pdo->prepare(
            'SELECT id, status FROM reservations
             WHERE tenant_id = :tenant_id
             AND member_id = :member_id
             AND class_session_id = :class_session_id
             LIMIT 1'
        );
        $existingStmt->execute([
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'class_session_id' => $sessionId,
        ]);
        $existing = $existingStmt->fetch();

        if ($existing && in_array($existing['status'], self::ACTIVE_STATUSES, true)) {
            throw new RuntimeException('Este socio ya tiene una reserva activa en esta clase.');
        }

        if (self::activeCount($tenantId, $sessionId) >= $capacity) {
            throw new RuntimeException('La clase esta llena. No se pueden anadir mas reservas activas.');
        }

        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE reservations
                 SET status = "reserved", cancelled_at = NULL, created_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute(['id' => $existing['id'], 'tenant_id' => $tenantId]);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO reservations (id, tenant_id, member_id, class_session_id, status, created_at, cancelled_at)
             VALUES (:id, :tenant_id, :member_id, :class_session_id, "reserved", NOW(), NULL)'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'class_session_id' => $sessionId,
        ]);
    }

    public static function updateStatus(string $tenantId, string $reservationId, string $status): void
    {
        self::ensureTable();
        $allowed = ['reserved', 'cancelled', 'attended', 'no_show'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Estado de reserva no valido.');
        }

        $currentStmt = Database::connection()->prepare(
            'SELECT id, class_session_id, status
             FROM reservations
             WHERE id = :id AND tenant_id = :tenant_id
             LIMIT 1'
        );
        $currentStmt->execute(['id' => $reservationId, 'tenant_id' => $tenantId]);
        $current = $currentStmt->fetch();
        if (!$current) {
            throw new RuntimeException('No se encontro la reserva seleccionada.');
        }

        if (in_array($status, self::ACTIVE_STATUSES, true) && !in_array($current['status'], self::ACTIVE_STATUSES, true)) {
            $capacity = self::sessionCapacity($tenantId, (string) $current['class_session_id']);
            if ($capacity !== null && self::activeCount($tenantId, (string) $current['class_session_id']) >= $capacity) {
                throw new RuntimeException('La clase esta llena. No se puede reactivar esta reserva.');
            }
        }

        $stmt = Database::connection()->prepare(
            'UPDATE reservations
             SET status = :status,
                 cancelled_at = CASE WHEN :status_cancelled = "cancelled" THEN NOW() ELSE NULL END
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'status' => $status,
            'status_cancelled' => $status,
            'id' => $reservationId,
            'tenant_id' => $tenantId,
        ]);
    }

    public static function deleteForMember(string $tenantId, string $memberId): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM reservations WHERE member_id = :member_id AND tenant_id = :tenant_id');
        $stmt->execute(['member_id' => $memberId, 'tenant_id' => $tenantId]);
    }

    public static function deleteForSession(string $tenantId, string $sessionId): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM reservations WHERE class_session_id = :class_session_id AND tenant_id = :tenant_id');
        $stmt->execute(['class_session_id' => $sessionId, 'tenant_id' => $tenantId]);
    }
}

final class CheckinRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS checkins (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                member_id VARCHAR(191) NOT NULL,
                class_session_id VARCHAR(191) NULL,
                reservation_id VARCHAR(191) NULL,
                method VARCHAR(32) NOT NULL DEFAULT "MANUAL",
                checked_in_at DATETIME NOT NULL,
                notes TEXT NULL,
                created_by_user_id VARCHAR(191) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX checkins_tenant_id_idx (tenant_id),
                INDEX checkins_member_id_idx (member_id),
                INDEX checkins_class_session_id_idx (class_session_id),
                INDEX checkins_reservation_id_idx (reservation_id),
                INDEX checkins_checked_in_at_idx (checked_in_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('checkins', 'tenant_id', 'VARCHAR(191) NULL');
        self::ensureColumn('checkins', 'member_id', 'VARCHAR(191) NULL');
        self::ensureColumn('checkins', 'class_session_id', 'VARCHAR(191) NULL');
        self::ensureColumn('checkins', 'reservation_id', 'VARCHAR(191) NULL');
        self::ensureColumn('checkins', 'method', 'VARCHAR(32) NOT NULL DEFAULT "MANUAL"');
        self::ensureColumn('checkins', 'checked_in_at', 'DATETIME NULL');
        self::ensureColumn('checkins', 'notes', 'TEXT NULL');
        self::ensureColumn('checkins', 'created_by_user_id', 'VARCHAR(191) NULL');
        self::ensureColumn('checkins', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public static function metrics(string $tenantId): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        return [
            'today' => self::count($pdo, $tenantId, 'DATE(checked_in_at) = CURDATE()'),
            'week' => self::count($pdo, $tenantId, 'checked_in_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            'manual' => self::count($pdo, $tenantId, 'method = "MANUAL"'),
            'with_class' => self::count($pdo, $tenantId, 'class_session_id IS NOT NULL'),
        ];
    }

    public static function all(string $tenantId, string $query = '', string $dateFrom = '', string $dateTo = '', int $limit = 200): array
    {
        self::ensureTable();
        ClassRepository::ensureTables();
        ReservationRepository::ensureTable();

        $params = ['tenant_id' => $tenantId];
        $where = ['checkins.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(members.first_name LIKE :query OR members.last_name LIKE :query OR members.email LIKE :query OR members.phone LIKE :query OR class_types.name LIKE :query OR checkins.notes LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(checkins.checked_in_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(checkins.checked_in_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $stmt = Database::connection()->prepare(
            'SELECT checkins.*,
                    members.first_name,
                    members.last_name,
                    members.email,
                    members.phone,
                    class_sessions.starts_at,
                    class_sessions.ends_at,
                    class_types.name AS class_name,
                    users.name AS created_by_name
             FROM checkins
             INNER JOIN members ON members.id = checkins.member_id AND members.tenant_id = checkins.tenant_id
             LEFT JOIN class_sessions ON class_sessions.id = checkins.class_session_id AND class_sessions.tenant_id = checkins.tenant_id
             LEFT JOIN class_types ON class_types.id = class_sessions.class_type_id AND class_types.tenant_id = checkins.tenant_id
             LEFT JOIN users ON users.id = checkins.created_by_user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY checkins.checked_in_at DESC, checkins.created_at DESC
             LIMIT ' . max(1, min($limit, 300))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function create(string $tenantId, array $data): void
    {
        self::ensureTable();
        $params = self::params($tenantId, $data);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO checkins (id, tenant_id, member_id, class_session_id, reservation_id, method, checked_in_at, notes, created_by_user_id, created_at)
                 VALUES (:id, :tenant_id, :member_id, :class_session_id, :reservation_id, :method, :checked_in_at, :notes, :created_by_user_id, NOW())'
            );
            $stmt->execute($params + ['id' => cuid()]);

            if (!empty($params['reservation_id'])) {
                $updateReservation = $pdo->prepare(
                    'UPDATE reservations
                     SET status = "attended"
                     WHERE id = :id AND tenant_id = :tenant_id AND member_id = :member_id'
                );
                $updateReservation->execute([
                    'id' => $params['reservation_id'],
                    'tenant_id' => $tenantId,
                    'member_id' => $params['member_id'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function delete(string $tenantId, string $id): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM checkins WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function memberOptions(string $tenantId): array
    {
        return PaymentRepository::memberOptions($tenantId);
    }

    public static function reservationOptions(string $tenantId): array
    {
        ReservationRepository::ensureTable();
        ClassRepository::ensureTables();

        $stmt = Database::connection()->prepare(
            'SELECT reservations.id,
                    reservations.member_id,
                    reservations.class_session_id,
                    reservations.status,
                    members.first_name,
                    members.last_name,
                    class_sessions.starts_at,
                    class_sessions.ends_at,
                    class_types.name AS class_name
             FROM reservations
             INNER JOIN members ON members.id = reservations.member_id AND members.tenant_id = reservations.tenant_id
             INNER JOIN class_sessions ON class_sessions.id = reservations.class_session_id AND class_sessions.tenant_id = reservations.tenant_id
             INNER JOIN class_types ON class_types.id = class_sessions.class_type_id AND class_types.tenant_id = reservations.tenant_id
             WHERE reservations.tenant_id = :tenant_id
             AND reservations.status IN ("reserved", "attended", "no_show")
             ORDER BY class_sessions.starts_at DESC
             LIMIT 300'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['member_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public static function deleteForMember(string $tenantId, string $memberId): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM checkins WHERE member_id = :member_id AND tenant_id = :tenant_id');
        $stmt->execute(['member_id' => $memberId, 'tenant_id' => $tenantId]);
    }

    public static function deleteForSession(string $tenantId, string $sessionId): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM checkins WHERE class_session_id = :class_session_id AND tenant_id = :tenant_id');
        $stmt->execute(['class_session_id' => $sessionId, 'tenant_id' => $tenantId]);
    }

    private static function params(string $tenantId, array $data): array
    {
        $memberId = trim((string) ($data['member_id'] ?? ''));
        if ($memberId === '' || !self::memberExists($tenantId, $memberId)) {
            throw new RuntimeException('Selecciona un socio valido para registrar el check-in.');
        }

        $reservationId = trim((string) ($data['reservation_id'] ?? '')) ?: null;
        $classSessionId = trim((string) ($data['class_session_id'] ?? '')) ?: null;

        if ($reservationId !== null) {
            $reservation = self::reservationForMember($tenantId, $reservationId, $memberId);
            if (!$reservation) {
                throw new RuntimeException('La reserva seleccionada no pertenece al socio.');
            }
            $classSessionId = (string) $reservation['class_session_id'];
        } elseif ($classSessionId !== null && !self::classSessionExists($tenantId, $classSessionId)) {
            throw new RuntimeException('La clase seleccionada no existe.');
        }

        $date = trim((string) ($data['checkin_date'] ?? '')) ?: date('Y-m-d');
        $time = trim((string) ($data['checkin_time'] ?? '')) ?: date('H:i');
        $checkedInAt = $date . ' ' . $time . ':00';

        if (!strtotime($checkedInAt)) {
            throw new RuntimeException('Indica una fecha y hora validas para el check-in.');
        }

        $method = strtoupper(trim((string) ($data['method'] ?? 'MANUAL')));
        if (!in_array($method, ['MANUAL', 'QR'], true)) {
            $method = 'MANUAL';
        }

        return [
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'class_session_id' => $classSessionId,
            'reservation_id' => $reservationId,
            'method' => $method,
            'checked_in_at' => $checkedInAt,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by_user_id' => Auth::user()['id'] ?? null,
        ];
    }

    private static function memberExists(string $tenantId, string $memberId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM members WHERE id = :id AND tenant_id = :tenant_id AND status <> "INACTIVE"');
        $stmt->execute(['id' => $memberId, 'tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function reservationForMember(string $tenantId, string $reservationId, string $memberId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM reservations
             WHERE id = :id AND tenant_id = :tenant_id AND member_id = :member_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $reservationId, 'tenant_id' => $tenantId, 'member_id' => $memberId]);
        $reservation = $stmt->fetch();

        return $reservation ?: null;
    }

    private static function classSessionExists(string $tenantId, string $classSessionId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM class_sessions WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $classSessionId, 'tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function count(PDO $pdo, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM checkins WHERE tenant_id = :tenant_id AND {$where}");
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
            'leads' => LeadRepository::all($tenantId, $query, '', '', '', '', '', 6),
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
