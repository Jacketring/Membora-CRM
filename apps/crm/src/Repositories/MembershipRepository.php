<?php

declare(strict_types=1);

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
                next_billing_at DATE NULL,
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
        self::ensureColumn('subscriptions', 'next_billing_at', 'DATE NULL');
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
            'next_billing' => self::count($pdo, 'subscriptions', $tenantId, 'status = "ACTIVE" AND COALESCE(next_billing_at, starts_at) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'),
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
             ORDER BY COALESCE(subscriptions.next_billing_at, subscriptions.starts_at) ASC, subscriptions.ends_at ASC
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
            'INSERT INTO subscriptions (id, tenant_id, member_id, membership_plan_id, status, starts_at, ends_at, next_billing_at, created_at, updated_at)
             VALUES (:id, :tenant_id, :member_id, :membership_plan_id, "ACTIVE", :starts_at, :ends_at, :next_billing_at, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'membership_plan_id' => $planId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'next_billing_at' => $startsAt,
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
                     next_billing_at = :next_billing_at,
                     updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $update->execute([
                'ends_at' => $newEndsAt,
                'next_billing_at' => $newEndsAt,
                'id' => $subscription['id'],
                'tenant_id' => $tenantId,
            ]);

            $payment = $pdo->prepare(
                'INSERT INTO payments (id, tenant_id, member_id, subscription_id, amount, currency, payment_method, status, paid_at, due_at, period_start_at, period_end_at, notes, created_at, updated_at)
                 VALUES (:id, :tenant_id, :member_id, :subscription_id, :amount, "EUR", "OTHER", "PAID", CURDATE(), :due_at, :period_start_at, :period_end_at, :notes, NOW(), NOW())'
            );
            $payment->execute([
                'id' => cuid(),
                'tenant_id' => $tenantId,
                'member_id' => $memberId,
                'subscription_id' => $subscription['id'],
                'amount' => $amount,
                'due_at' => $currentEnd->format('Y-m-d'),
                'period_start_at' => $baseDate->format('Y-m-d'),
                'period_end_at' => $newEndsAt,
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
