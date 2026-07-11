<?php

declare(strict_types=1);

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
        self::ensureColumn('payments', 'period_start_at', 'DATE NULL');
        self::ensureColumn('payments', 'period_end_at', 'DATE NULL');
        self::ensureColumn('payments', 'reference', 'VARCHAR(191) NULL');
        self::ensureColumn('payments', 'paid_notes', 'TEXT NULL');
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
            'pending_amount' => self::sum($pdo, $tenantId, 'status IN ("DRAFT", "PENDING", "OVERDUE")'),
            'draft_count' => self::count($pdo, $tenantId, 'status = "DRAFT"'),
            'pending_count' => self::count($pdo, $tenantId, 'status = "PENDING"'),
            'overdue_count' => self::count($pdo, $tenantId, 'status = "OVERDUE" OR (status = "PENDING" AND due_at IS NOT NULL AND due_at < CURDATE())'),
            'next_due_count' => self::count($pdo, $tenantId, 'status IN ("DRAFT", "PENDING") AND due_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'),
        ];
    }

    public static function all(string $tenantId, string $query = '', string $status = '', string $dateFrom = '', string $dateTo = '', int $limit = 200): array
    {
        self::ensureTable();
        MembershipRepository::ensureTables();

        $params = ['tenant_id' => $tenantId];
        $where = ['payments.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(members.first_name LIKE :query OR members.last_name LIKE :query OR members.email LIKE :query OR membership_plans.name LIKE :query OR payments.notes LIKE :query OR payments.reference LIKE :query)';
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
             ORDER BY FIELD(payments.status, "OVERDUE", "PENDING", "DRAFT", "PAID", "CANCELLED"), payments.due_at IS NULL, payments.due_at ASC, payments.created_at DESC
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
            'INSERT INTO payments (id, tenant_id, member_id, subscription_id, amount, currency, payment_method, status, paid_at, due_at, period_start_at, period_end_at, reference, paid_notes, notes, created_at, updated_at)
             VALUES (:id, :tenant_id, :member_id, :subscription_id, :amount, :currency, :payment_method, :status, :paid_at, :due_at, :period_start_at, :period_end_at, :reference, :paid_notes, :notes, NOW(), NOW())'
        );
        $stmt->execute($params + ['id' => cuid()]);

        if ($params['status'] === 'PAID' && $params['subscription_id'] && $params['period_end_at']) {
            self::advanceSubscriptionBilling($tenantId, (string) $params['subscription_id'], (string) $params['period_end_at']);
        }
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
                 period_start_at = :period_start_at,
                 period_end_at = :period_end_at,
                 reference = :reference,
                 paid_notes = :paid_notes,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute($params);

        if ($params['status'] === 'PAID' && $params['subscription_id'] && $params['period_end_at']) {
            self::advanceSubscriptionBilling($tenantId, (string) $params['subscription_id'], (string) $params['period_end_at']);
        }
    }

    public static function delete(string $tenantId, string $id): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM payments WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    public static function markPaid(string $tenantId, string $id, array $data): void
    {
        self::ensureTable();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = :id AND tenant_id = :tenant_id FOR UPDATE');
            $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
            $payment = $stmt->fetch();
            if (!$payment) {
                throw new RuntimeException('No se encontro el pago.');
            }
            if ((string) $payment['status'] === 'PAID') {
                throw new RuntimeException('Este pago ya estaba cobrado.');
            }
            if ((string) $payment['status'] === 'CANCELLED') {
                throw new RuntimeException('No se puede cobrar un pago anulado.');
            }

            $method = strtoupper(trim((string) ($data['payment_method'] ?? $payment['payment_method'] ?? 'OTHER')));
            if (!in_array($method, ['CASH', 'CARD', 'TRANSFER', 'TPV', 'DIRECT_DEBIT', 'OTHER'], true)) {
                $method = 'OTHER';
            }

            $update = $pdo->prepare(
                'UPDATE payments
                 SET status = "PAID",
                     paid_at = :paid_at,
                     payment_method = :payment_method,
                     reference = :reference,
                     paid_notes = :paid_notes,
                     updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $update->execute([
                'paid_at' => trim((string) ($data['paid_at'] ?? '')) ?: date('Y-m-d'),
                'payment_method' => $method,
                'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
                'paid_notes' => trim((string) ($data['paid_notes'] ?? '')) ?: null,
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);

            if (!empty($payment['subscription_id']) && !empty($payment['period_end_at'])) {
                self::advanceSubscriptionBilling($tenantId, (string) $payment['subscription_id'], (string) $payment['period_end_at']);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function generateRecurringDrafts(string $tenantId, ?string $untilDate = null): int
    {
        self::ensureTable();
        MembershipRepository::ensureTables();
        $untilDate = $untilDate ?: date('Y-m-d');
        $created = 0;
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT subscriptions.*, membership_plans.price, membership_plans.billing_period, membership_plans.name AS plan_name
             FROM subscriptions
             INNER JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id
                AND membership_plans.tenant_id = subscriptions.tenant_id
             WHERE subscriptions.tenant_id = :tenant_id
             AND subscriptions.status = "ACTIVE"
             AND COALESCE(subscriptions.next_billing_at, subscriptions.starts_at) <= :until_date'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'until_date' => $untilDate]);

        foreach ($stmt->fetchAll() as $subscription) {
            $periodStart = (string) ($subscription['next_billing_at'] ?: $subscription['starts_at']);
            if ($periodStart === '') {
                continue;
            }
            $periodEnd = membership_end_date($periodStart, (string) $subscription['billing_period']);
            if (self::paymentExistsForPeriod($tenantId, (string) $subscription['id'], $periodStart, $periodEnd)) {
                continue;
            }

            $insert = $pdo->prepare(
                'INSERT INTO payments (id, tenant_id, member_id, subscription_id, amount, currency, payment_method, status, paid_at, due_at, period_start_at, period_end_at, notes, created_at, updated_at)
                 VALUES (:id, :tenant_id, :member_id, :subscription_id, :amount, "EUR", "OTHER", "DRAFT", NULL, :due_at, :period_start_at, :period_end_at, :notes, NOW(), NOW())'
            );
            $insert->execute([
                'id' => cuid(),
                'tenant_id' => $tenantId,
                'member_id' => $subscription['member_id'],
                'subscription_id' => $subscription['id'],
                'amount' => number_format((float) $subscription['price'], 2, '.', ''),
                'due_at' => $periodStart,
                'period_start_at' => $periodStart,
                'period_end_at' => $periodEnd,
                'notes' => 'Borrador recurrente: ' . $subscription['plan_name'],
            ]);
            $created++;
        }

        return $created;
    }

    private static function paymentExistsForPeriod(string $tenantId, string $subscriptionId, string $periodStart, string $periodEnd): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM payments
             WHERE tenant_id = :tenant_id
             AND subscription_id = :subscription_id
             AND period_start_at = :period_start_at
             AND period_end_at = :period_end_at
             AND status <> "CANCELLED"'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'subscription_id' => $subscriptionId,
            'period_start_at' => $periodStart,
            'period_end_at' => $periodEnd,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function advanceSubscriptionBilling(string $tenantId, string $subscriptionId, string $periodEnd): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE subscriptions
             SET next_billing_at = :next_billing_at,
                 ends_at = GREATEST(COALESCE(ends_at, :next_billing_at_for_ends), :next_billing_at_for_ends),
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'next_billing_at' => $periodEnd,
            'next_billing_at_for_ends' => $periodEnd,
            'id' => $subscriptionId,
            'tenant_id' => $tenantId,
        ]);
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
                    subscriptions.next_billing_at,
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
             ORDER BY COALESCE(subscriptions.next_billing_at, subscriptions.starts_at) ASC, subscriptions.ends_at ASC'
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
        if (!in_array($status, ['DRAFT', 'PAID', 'PENDING', 'OVERDUE', 'CANCELLED'], true)) {
            $status = 'PENDING';
        }

        $method = strtoupper(trim((string) ($data['payment_method'] ?? 'OTHER')));
        if (!in_array($method, ['CASH', 'CARD', 'TRANSFER', 'TPV', 'DIRECT_DEBIT', 'OTHER'], true)) {
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
            'period_start_at' => trim((string) ($data['period_start_at'] ?? '')) ?: null,
            'period_end_at' => trim((string) ($data['period_end_at'] ?? '')) ?: null,
            'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
            'paid_notes' => trim((string) ($data['paid_notes'] ?? '')) ?: null,
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
