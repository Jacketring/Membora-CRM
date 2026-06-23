<?php

final class DashboardRepository
{
    public static function summary(string $tenantId): array
    {
        $pdo = Database::connection();

        return [
            'activeMembers' => self::count($pdo, 'members', 'status = "ACTIVE"', $tenantId),
            'openLeads' => self::count($pdo, 'leads', 'status = "OPEN"', $tenantId),
            'pendingTasks' => self::count($pdo, 'tasks', 'status = "PENDING"', $tenantId),
            'overdueTasks' => self::count($pdo, 'tasks', 'status = "PENDING" AND due_at < NOW()', $tenantId),
            'openAlerts' => self::count($pdo, 'risk_alerts', 'status = "OPEN"', $tenantId),
            'pendingPayments' => self::count($pdo, 'payments', 'status IN ("PENDING", "OVERDUE")', $tenantId),
            'recentLeads' => LeadRepository::all($tenantId, '', '', '', 5),
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
}

final class LeadRepository
{
    public static function all(
        string $tenantId,
        string $query = '',
        string $stageId = '',
        string $status = '',
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

        $sql = 'SELECT leads.*, pipeline_stages.name AS stage_name, users.name AS assigned_name
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
}

final class TaskRepository
{
    public static function all(string $tenantId, string $query = '', string $status = '', int $limit = 100): array
    {
        $params = ['tenant_id' => $tenantId];
        $where = ['tasks.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(tasks.title LIKE :query OR tasks.description LIKE :query OR leads.first_name LIKE :query OR leads.last_name LIKE :query OR members.first_name LIKE :query OR members.last_name LIKE :query OR users.name LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'tasks.status = :status';
            $params['status'] = $status;
        }

        $sql = 'SELECT tasks.*, users.name AS assigned_name,
                       leads.first_name AS lead_first_name, leads.last_name AS lead_last_name,
                       members.first_name AS member_first_name, members.last_name AS member_last_name
                FROM tasks
                LEFT JOIN users ON users.id = tasks.assigned_user_id
                LEFT JOIN leads ON leads.id = tasks.lead_id
                LEFT JOIN members ON members.id = tasks.member_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY tasks.status ASC, tasks.due_at ASC, tasks.created_at DESC
                LIMIT ' . max(1, min($limit, 200));

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
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
