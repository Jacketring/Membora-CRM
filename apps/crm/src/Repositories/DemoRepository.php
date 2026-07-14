<?php

declare(strict_types=1);

final class DemoRepository
{
    public const CLIENT_EMAIL = 'demo.cliente@membora.crm';
    private const TENANT_ID = 'demo_tenant_cliente';
    private const RESET_KEY = 'client_demo';

    public static function clientPassword(): string
    {
        return trim((string) getenv('DEMO_CLIENT_PASSWORD'));
    }

    private static function assertDemoEnvironment(string $type = 'client'): void
    {
        if (!DemoAccessPolicy::isTypeEnabled((string) getenv('APP_ENV'), $type)) {
            throw new LogicException('Demo data cannot be created outside the demo environment.');
        }
    }

    public static function prepareClientDemo(): void
    {
        self::assertDemoEnvironment();
        self::ensureResetTable();
        self::ensureTenantAndUser();

        if (self::shouldReset()) {
            self::resetTenantData();
            self::seedTenantData();
            self::markReset();
        }

        self::deactivateSeedUser();
    }

    public static function prepareAdminDemo(): void
    {
        self::assertDemoEnvironment('admin');
        EmpresaRepository::ensureTables();
        EmpresaRepository::ensurePlatformAdmin();
        PlatformPlanRepository::ensureTable();
        self::prepareClientDemo();
    }

    public static function createTemporaryUser(string $type): array
    {
        $type = $type === 'admin' ? 'admin' : 'client';
        $type === 'admin' ? self::prepareAdminDemo() : self::prepareClientDemo();
        self::ensureTemporaryUsersTable();
        self::maintainTemporaryUsers();

        $pdo = Database::connection();
        $userId = cuid();
        $password = bin2hex(random_bytes(16));
        $cleanupToken = bin2hex(random_bytes(24));
        $email = 'demo.' . $type . '.' . substr(hash('sha256', $userId), 0, 16) . '@membora.invalid';
        $roleId = $type === 'admin' ? self::platformAdminRoleId() : self::ensureRole('GYM_ADMIN', 'Administrador');

        $insertUser = $pdo->prepare(
            'INSERT INTO users (id, tenant_id, role_id, name, email, password_hash, status, created_at, updated_at)
             VALUES (:id, :tenant_id, :role_id, :name, :email, :password_hash, "ACTIVE", NOW(), NOW())'
        );
        $insertUser->execute([
            'id' => $userId,
            'tenant_id' => $type === 'client' ? self::TENANT_ID : null,
            'role_id' => $roleId,
            'name' => $type === 'admin' ? 'Administrador Demo Temporal' : 'Cliente Demo Temporal',
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ]);

        $insertDemo = $pdo->prepare(
            'INSERT INTO demo_users (user_id, demo_type, cleanup_token_hash, expires_at, cleanup_after, created_at)
             VALUES (:user_id, :demo_type, :cleanup_token_hash, DATE_ADD(NOW(), INTERVAL 20 MINUTE), NULL, NOW())'
        );
        $insertDemo->execute([
            'user_id' => $userId,
            'demo_type' => $type,
            'cleanup_token_hash' => hash('sha256', $cleanupToken),
        ]);

        return [
            'id' => $userId,
            'email' => $email,
            'password' => $password,
            'cleanup_token' => $cleanupToken,
        ];
    }

    public static function maintainTemporaryUsers(): void
    {
        if (!DemoAccessPolicy::isClientEnabled((string) getenv('APP_ENV'))) {
            return;
        }

        self::ensureTemporaryUsersTable();
        $stmt = Database::connection()->query(
            'SELECT user_id FROM demo_users
             WHERE expires_at <= NOW() OR (cleanup_after IS NOT NULL AND cleanup_after <= NOW())'
        );
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
            self::deleteTemporaryUserById((string) $userId);
        }
    }

    public static function cancelScheduledCleanup(string $userId, string $cleanupToken): void
    {
        if (!self::temporaryUserTokenIsValid($userId, $cleanupToken)) {
            return;
        }

        $stmt = Database::connection()->prepare('UPDATE demo_users SET cleanup_after = NULL WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public static function scheduleCleanup(string $userId, string $cleanupToken): bool
    {
        if (!self::temporaryUserTokenIsValid($userId, $cleanupToken)) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE demo_users SET cleanup_after = DATE_ADD(NOW(), INTERVAL 10 SECOND) WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        return true;
    }

    public static function deleteTemporaryUser(string $userId, string $cleanupToken): bool
    {
        if (!self::temporaryUserTokenIsValid($userId, $cleanupToken)) {
            return false;
        }

        self::deleteTemporaryUserById($userId);
        return true;
    }

    public static function temporaryUserIsActive(string $userId, string $cleanupToken): bool
    {
        return self::temporaryUserTokenIsValid($userId, $cleanupToken);
    }

    private static function ensureTemporaryUsersTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS demo_users (
                user_id VARCHAR(191) NOT NULL PRIMARY KEY,
                demo_type VARCHAR(16) NOT NULL,
                cleanup_token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                cleanup_after DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX demo_users_expiry_idx (expires_at),
                INDEX demo_users_cleanup_idx (cleanup_after)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
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
                'primary_color' => '#004bf2',
            ]);
        } else {
            $updateTenant = $pdo->prepare('UPDATE tenants SET name = :name, primary_color = :primary_color, updated_at = NOW() WHERE id = :id');
            $updateTenant->execute([
                'id' => self::TENANT_ID,
                'name' => 'Membora Demo Fitness',
                'primary_color' => '#004bf2',
            ]);
        }

        self::ensurePipeline();
        $roleId = self::ensureRole('GYM_ADMIN', 'Administrador');
        $user = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $user->execute(['email' => self::CLIENT_EMAIL]);
        $userId = (string) ($user->fetchColumn() ?: cuid());
        $seedPassword = self::clientPassword() ?: bin2hex(random_bytes(16));
        $passwordHash = password_hash($seedPassword, PASSWORD_BCRYPT);

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

    private static function deactivateSeedUser(): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET status = "INACTIVE", updated_at = NOW() WHERE email = :email');
        $stmt->execute(['email' => self::CLIENT_EMAIL]);
    }

    private static function platformAdminRoleId(): string
    {
        $stmt = Database::connection()->query(
            'SELECT id FROM roles
             WHERE `key` IN ("SUPERADMIN", "SUPER_ADMIN")
             ORDER BY `key` = "SUPERADMIN" DESC
             LIMIT 1'
        );
        $roleId = $stmt->fetchColumn();

        return $roleId ? (string) $roleId : self::ensureRole('SUPERADMIN', 'Superadmin');
    }

    private static function temporaryUserTokenIsValid(string $userId, string $cleanupToken): bool
    {
        if ($userId === '' || !preg_match('/^[a-f0-9]{48}$/', $cleanupToken)) {
            return false;
        }

        self::ensureTemporaryUsersTable();
        $stmt = Database::connection()->prepare(
            'SELECT cleanup_token_hash FROM demo_users WHERE user_id = :user_id AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $storedHash = $stmt->fetchColumn();

        return is_string($storedHash) && hash_equals($storedHash, hash('sha256', $cleanupToken));
    }

    private static function deleteTemporaryUserById(string $userId): void
    {
        try {
            AuthTokenRepository::deleteForUser($userId);
        } catch (Throwable) {
        }

        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $pdo->prepare('DELETE FROM demo_users WHERE user_id = :user_id')->execute(['user_id' => $userId]);
            $pdo->prepare('DELETE FROM users WHERE id = :user_id')->execute(['user_id' => $userId]);
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
}
