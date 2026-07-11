<?php

declare(strict_types=1);

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
