<?php

declare(strict_types=1);

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
