<?php

declare(strict_types=1);

final class AuditLogRepository
{
    private const ACTION_GROUPS = [
        'users' => [
            'label' => 'Usuarios',
            'actions' => ['create_user', 'update_user', 'delete_user'],
        ],
        'companies' => [
            'label' => 'Empresas',
            'actions' => ['create_empresa', 'update_empresa', 'update_empresa_subscription', 'renew_empresa_subscription', 'enter_empresa_crm', 'exit_empresa_crm'],
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
