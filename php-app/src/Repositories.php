<?php

final class DashboardRepository
{
    public static function summary(string $tenantId): array
    {
        $pdo = Database::connection();

        return [
            'activeMembers' => self::count($pdo, 'members', 'status = "ACTIVE"', $tenantId),
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

        $params = ['tenant_id' => $tenantId];
        $where = ['tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(first_name LIKE :query OR last_name LIKE :query OR email LIKE :query OR phone LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(joined_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(joined_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, first_name, last_name, email, phone, status, photo_path, joined_at, created_at, updated_at
             FROM members
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY joined_at DESC, created_at DESC
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
            'active' => self::count($pdo, $tenantId, 'status = "ACTIVE"'),
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
                'href' => '',
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
