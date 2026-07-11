<?php

declare(strict_types=1);

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
