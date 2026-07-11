<?php

declare(strict_types=1);

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
