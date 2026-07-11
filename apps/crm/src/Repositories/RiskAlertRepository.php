<?php

declare(strict_types=1);

final class RiskAlertRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS risk_alerts (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                member_id VARCHAR(191) NULL,
                lead_id VARCHAR(191) NULL,
                task_id VARCHAR(191) NULL,
                payment_id VARCHAR(191) NULL,
                class_session_id VARCHAR(191) NULL,
                type VARCHAR(64) NOT NULL,
                severity VARCHAR(32) NOT NULL DEFAULT "MEDIUM",
                status VARCHAR(32) NOT NULL DEFAULT "OPEN",
                message TEXT NOT NULL,
                detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX risk_alerts_tenant_id_idx (tenant_id),
                INDEX risk_alerts_status_idx (status),
                INDEX risk_alerts_type_idx (type),
                INDEX risk_alerts_member_id_idx (member_id),
                INDEX risk_alerts_lead_id_idx (lead_id),
                INDEX risk_alerts_task_id_idx (task_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('risk_alerts', 'payment_id', 'VARCHAR(191) NULL');
        self::ensureColumn('risk_alerts', 'class_session_id', 'VARCHAR(191) NULL');
        self::ensureColumn('risk_alerts', 'severity', 'VARCHAR(32) NOT NULL DEFAULT "MEDIUM"');
        self::ensureColumn('risk_alerts', 'status', 'VARCHAR(32) NOT NULL DEFAULT "OPEN"');
        self::ensureColumn('risk_alerts', 'message', 'TEXT NULL');
        self::ensureColumn('risk_alerts', 'detected_at', 'DATETIME NULL');
        self::ensureColumn('risk_alerts', 'resolved_at', 'DATETIME NULL');
        self::ensureColumn('risk_alerts', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        self::ensureColumn('risk_alerts', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

        foreach (['member_id', 'lead_id', 'task_id', 'payment_id', 'class_session_id'] as $field) {
            Database::connection()->exec('UPDATE risk_alerts SET ' . $field . ' = NULL WHERE ' . $field . ' = ""');
        }
    }

    public static function generate(string $tenantId): void
    {
        self::ensureTable();
        PaymentRepository::ensureTable();
        MembershipRepository::ensureTables();
        CheckinRepository::ensureTable();
        self::deduplicate($tenantId);

        $paymentAlerts = self::paymentAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'PAYMENT_OVERDUE', 'payment_id', array_column($paymentAlerts, 'payment_id'));
        foreach ($paymentAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }
        $taskAlerts = self::taskAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'TASK_OVERDUE', 'task_id', array_column($taskAlerts, 'task_id'));
        foreach ($taskAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }
        $membershipAlerts = self::membershipAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'MEMBERSHIP_EXPIRED', 'member_id', array_column($membershipAlerts, 'member_id'));
        foreach ($membershipAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }
        $inactiveMemberAlerts = self::inactiveMemberAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'MEMBER_INACTIVE', 'member_id', array_column($inactiveMemberAlerts, 'member_id'));
        foreach ($inactiveMemberAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }
        $staleLeadAlerts = self::staleLeadAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'LEAD_STALE', 'lead_id', array_column($staleLeadAlerts, 'lead_id'));
        foreach ($staleLeadAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }
        $classCapacityAlerts = self::classCapacityAlerts($tenantId);
        self::closeStaleEntityAlerts($tenantId, 'CLASS_FULL', 'class_session_id', array_column($classCapacityAlerts, 'class_session_id'));
        foreach ($classCapacityAlerts as $alert) {
            self::createIfOpenMissing($tenantId, $alert);
        }

        self::deduplicate($tenantId);
    }

    public static function metrics(string $tenantId): array
    {
        self::generate($tenantId);
        $pdo = Database::connection();

        return [
            'open' => self::count($pdo, $tenantId, 'status = "OPEN"'),
            'high' => self::count($pdo, $tenantId, 'status = "OPEN" AND severity = "HIGH"'),
            'medium' => self::count($pdo, $tenantId, 'status = "OPEN" AND severity = "MEDIUM"'),
            'resolved' => self::count($pdo, $tenantId, 'status = "RESOLVED"'),
        ];
    }

    public static function all(string $tenantId, string $query = '', string $status = 'OPEN', string $type = '', int $limit = 200): array
    {
        self::generate($tenantId);

        $params = ['tenant_id' => $tenantId];
        $where = ['risk_alerts.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(risk_alerts.message LIKE :query OR members.first_name LIKE :query OR members.last_name LIKE :query OR leads.first_name LIKE :query OR leads.last_name LIKE :query OR tasks.title LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'risk_alerts.status = :status';
            $params['status'] = $status;
        }

        if ($type !== '') {
            $where[] = 'risk_alerts.type = :type';
            $params['type'] = $type;
        }

        $stmt = Database::connection()->prepare(
            'SELECT risk_alerts.*,
                    members.first_name AS member_first_name,
                    members.last_name AS member_last_name,
                    leads.first_name AS lead_first_name,
                    leads.last_name AS lead_last_name,
                    tasks.title AS task_title,
                    payments.amount AS payment_amount,
                    class_types.name AS class_name,
                    class_sessions.starts_at AS class_starts_at
             FROM risk_alerts
             LEFT JOIN members ON members.id = risk_alerts.member_id AND members.tenant_id = risk_alerts.tenant_id
             LEFT JOIN leads ON leads.id = risk_alerts.lead_id AND leads.tenant_id = risk_alerts.tenant_id
             LEFT JOIN tasks ON tasks.id = risk_alerts.task_id AND tasks.tenant_id = risk_alerts.tenant_id
             LEFT JOIN payments ON payments.id = risk_alerts.payment_id AND payments.tenant_id = risk_alerts.tenant_id
             LEFT JOIN class_sessions ON class_sessions.id = risk_alerts.class_session_id AND class_sessions.tenant_id = risk_alerts.tenant_id
             LEFT JOIN class_types ON class_types.id = class_sessions.class_type_id AND class_types.tenant_id = risk_alerts.tenant_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(risk_alerts.status, "OPEN", "DISMISSED", "RESOLVED"),
                      FIELD(risk_alerts.severity, "HIGH", "MEDIUM", "LOW"),
                      risk_alerts.detected_at DESC
             LIMIT ' . max(1, min($limit, 300))
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function updateStatus(string $tenantId, string $id, string $status): void
    {
        self::ensureTable();
        $status = in_array($status, ['OPEN', 'RESOLVED', 'DISMISSED'], true) ? $status : 'OPEN';
        $stmt = Database::connection()->prepare(
            'UPDATE risk_alerts
             SET status = :status,
                 resolved_at = CASE WHEN :status_done IN ("RESOLVED", "DISMISSED") THEN NOW() ELSE NULL END,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'status' => $status,
            'status_done' => $status,
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public static function typeOptions(): array
    {
        return [
            '' => 'Todas',
            'PAYMENT_OVERDUE' => 'Pagos vencidos',
            'TASK_OVERDUE' => 'Tareas vencidas',
            'MEMBERSHIP_EXPIRED' => 'Membresias por renovar',
            'MEMBER_INACTIVE' => 'Socios sin actividad',
            'LEAD_STALE' => 'Leads sin seguimiento',
            'CLASS_FULL' => 'Clases llenas',
        ];
    }

    private static function createIfOpenMissing(string $tenantId, array $alert): void
    {
        $alert = self::normalizeAlert($alert);
        $where = ['tenant_id = :tenant_id', 'type = :type'];
        $params = [
            'tenant_id' => $tenantId,
            'type' => $alert['type'],
        ];

        foreach (['member_id', 'lead_id', 'task_id', 'payment_id', 'class_session_id'] as $field) {
            if (!empty($alert[$field])) {
                $where[] = $field . ' = :' . $field;
                $params[$field] = $alert[$field];
            } else {
                $where[] = $field . ' IS NULL';
            }
        }

        $openWhere = array_merge($where, ['status = "OPEN"']);
        $exists = Database::connection()->prepare('SELECT COUNT(*) FROM risk_alerts WHERE ' . implode(' AND ', $openWhere));
        $exists->execute($params);
        if ((int) $exists->fetchColumn() > 0) {
            $update = Database::connection()->prepare(
                'UPDATE risk_alerts
                 SET severity = :severity,
                     message = :message,
                     updated_at = NOW()
                 WHERE ' . implode(' AND ', $openWhere)
            );
            $update->execute($params + [
                'severity' => $alert['severity'],
                'message' => $alert['message'],
            ]);
            return;
        }

        $closedWhere = array_merge($where, ['status IN ("RESOLVED", "DISMISSED")']);
        $reopen = Database::connection()->prepare(
            'UPDATE risk_alerts
             SET severity = :severity,
                 status = "OPEN",
                 message = :message,
                 detected_at = NOW(),
                 resolved_at = NULL,
                 updated_at = NOW()
             WHERE ' . implode(' AND ', $closedWhere) . '
             LIMIT 1'
        );
        $reopen->execute($params + [
            'severity' => $alert['severity'],
            'message' => $alert['message'],
        ]);
        if ($reopen->rowCount() > 0) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO risk_alerts (id, tenant_id, member_id, lead_id, task_id, payment_id, class_session_id, type, severity, status, message, detected_at, created_at, updated_at)
             VALUES (:id, :tenant_id, :member_id, :lead_id, :task_id, :payment_id, :class_session_id, :type, :severity, "OPEN", :message, NOW(), NOW(), NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'member_id' => $alert['member_id'] ?? null,
            'lead_id' => $alert['lead_id'] ?? null,
            'task_id' => $alert['task_id'] ?? null,
            'payment_id' => $alert['payment_id'] ?? null,
            'class_session_id' => $alert['class_session_id'] ?? null,
            'type' => $alert['type'],
            'severity' => $alert['severity'],
            'message' => $alert['message'],
        ]);
    }

    private static function normalizeAlert(array $alert): array
    {
        foreach (['member_id', 'lead_id', 'task_id', 'payment_id', 'class_session_id'] as $field) {
            $value = trim((string) ($alert[$field] ?? ''));
            $alert[$field] = $value !== '' ? $value : null;
        }

        return $alert;
    }

    private static function deduplicate(string $tenantId): void
    {
        $pdo = Database::connection();
        $groups = $pdo->prepare(
            'SELECT type,
                    COALESCE(member_id, "") AS member_id,
                    COALESCE(lead_id, "") AS lead_id,
                    COALESCE(task_id, "") AS task_id,
                    COALESCE(payment_id, "") AS payment_id,
                    COALESCE(class_session_id, "") AS class_session_id,
                    COUNT(*) AS total
             FROM risk_alerts
             WHERE tenant_id = :tenant_id
             GROUP BY type,
                      COALESCE(member_id, ""),
                      COALESCE(lead_id, ""),
                      COALESCE(task_id, ""),
                      COALESCE(payment_id, ""),
                      COALESCE(class_session_id, "")
             HAVING total > 1
             LIMIT 200'
        );
        $groups->execute(['tenant_id' => $tenantId]);

        $delete = $pdo->prepare('DELETE FROM risk_alerts WHERE tenant_id = :tenant_id AND id = :id');
        foreach ($groups->fetchAll() as $group) {
            $params = [
                'tenant_id' => $tenantId,
                'type' => $group['type'],
                'member_id' => $group['member_id'] !== '' ? $group['member_id'] : null,
                'lead_id' => $group['lead_id'] !== '' ? $group['lead_id'] : null,
                'task_id' => $group['task_id'] !== '' ? $group['task_id'] : null,
                'payment_id' => $group['payment_id'] !== '' ? $group['payment_id'] : null,
                'class_session_id' => $group['class_session_id'] !== '' ? $group['class_session_id'] : null,
            ];
            $alerts = $pdo->prepare(
                'SELECT id
                 FROM risk_alerts
                 WHERE tenant_id = :tenant_id
                   AND type = :type
                   AND member_id <=> :member_id
                   AND lead_id <=> :lead_id
                   AND task_id <=> :task_id
                   AND payment_id <=> :payment_id
                   AND class_session_id <=> :class_session_id
                 ORDER BY FIELD(status, "OPEN", "DISMISSED", "RESOLVED"),
                          updated_at DESC,
                          created_at DESC'
            );
            $alerts->execute($params);
            $ids = array_column($alerts->fetchAll(), 'id');
            array_shift($ids);

            foreach ($ids as $id) {
                $delete->execute(['tenant_id' => $tenantId, 'id' => $id]);
            }
        }
    }

    private static function closeStaleEntityAlerts(string $tenantId, string $type, string $entityField, array $activeEntityIds): void
    {
        $activeEntityIds = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $activeEntityIds
        ))));

        $params = [
            'tenant_id' => $tenantId,
            'type' => $type,
        ];
        $where = ['tenant_id = :tenant_id', 'type = :type', 'status = "OPEN"'];

        if ($activeEntityIds) {
            $placeholders = [];
            foreach ($activeEntityIds as $index => $id) {
                $key = 'entity_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $where[] = $entityField . ' NOT IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = Database::connection()->prepare(
            'UPDATE risk_alerts
             SET status = "RESOLVED",
                 resolved_at = NOW(),
                 updated_at = NOW()
             WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
    }

    private static function paymentAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT payments.id, payments.member_id, payments.amount, payments.due_at, members.first_name, members.last_name
             FROM payments
             INNER JOIN members ON members.id = payments.member_id AND members.tenant_id = payments.tenant_id
             WHERE payments.tenant_id = :tenant_id
             AND payments.status IN ("PENDING", "OVERDUE")
             AND payments.due_at IS NOT NULL
             AND payments.due_at < CURDATE()
             LIMIT 50'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static function (array $row): array {
            $name = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
            return [
                'type' => 'PAYMENT_OVERDUE',
                'severity' => 'HIGH',
                'member_id' => $row['member_id'],
                'payment_id' => $row['id'],
                'message' => 'Pago vencido de ' . $name . ' por ' . money_amount($row['amount']) . '.',
            ];
        }, $stmt->fetchAll());
    }

    private static function taskAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, member_id, lead_id, title, due_at
             FROM tasks
             WHERE tenant_id = :tenant_id
             AND status = "PENDING"
             AND due_at IS NOT NULL
             AND due_at < NOW()
             LIMIT 50'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static fn (array $row): array => [
            'type' => 'TASK_OVERDUE',
            'severity' => 'MEDIUM',
            'member_id' => $row['member_id'] ?? null,
            'lead_id' => $row['lead_id'] ?? null,
            'task_id' => $row['id'],
            'message' => 'Tarea vencida: ' . $row['title'] . '.',
        ], $stmt->fetchAll());
    }

    private static function membershipAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT subscriptions.id, subscriptions.member_id, subscriptions.ends_at, members.first_name, members.last_name, membership_plans.name AS plan_name
             FROM subscriptions
             INNER JOIN members ON members.id = subscriptions.member_id AND members.tenant_id = subscriptions.tenant_id
             INNER JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id AND membership_plans.tenant_id = subscriptions.tenant_id
             WHERE subscriptions.tenant_id = :tenant_id
             AND subscriptions.status = "ACTIVE"
             AND subscriptions.ends_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             LIMIT 50'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static function (array $row): array {
            $name = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
            $endsAt = (string) ($row['ends_at'] ?? '');
            $isExpired = $endsAt !== '' && $endsAt < date('Y-m-d');

            return [
                'type' => 'MEMBERSHIP_EXPIRED',
                'severity' => 'HIGH',
                'member_id' => $row['member_id'],
                'message' => ($isExpired ? 'Membresia caducada de ' : 'Renovacion proxima de ') . $name . ': ' . $row['plan_name'] . ' vence el ' . format_date_short($endsAt) . '.',
            ];
        }, $stmt->fetchAll());
    }

    private static function inactiveMemberAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT members.id, members.first_name, members.last_name, members.created_at, MAX(checkins.checked_in_at) AS last_checkin
             FROM members
             LEFT JOIN checkins ON checkins.member_id = members.id AND checkins.tenant_id = members.tenant_id
             WHERE members.tenant_id = :tenant_id
             AND members.status = "ACTIVE"
             GROUP BY members.id, members.first_name, members.last_name, members.created_at
             HAVING (last_checkin IS NULL AND members.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
                 OR last_checkin < DATE_SUB(NOW(), INTERVAL 30 DAY)
             LIMIT 20'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static function (array $row): array {
            $name = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
            return [
                'type' => 'MEMBER_INACTIVE',
                'severity' => 'MEDIUM',
                'member_id' => $row['id'],
                'message' => $name . ' no tiene check-ins recientes.',
            ];
        }, $stmt->fetchAll());
    }

    private static function staleLeadAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, first_name, last_name, updated_at
             FROM leads
             WHERE tenant_id = :tenant_id
             AND status = "OPEN"
             AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
             LIMIT 50'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static function (array $row): array {
            $name = trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
            return [
                'type' => 'LEAD_STALE',
                'severity' => 'LOW',
                'lead_id' => $row['id'],
                'message' => 'Lead sin seguimiento reciente: ' . $name . '.',
            ];
        }, $stmt->fetchAll());
    }

    private static function classCapacityAlerts(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT class_sessions.id, class_sessions.capacity, class_sessions.starts_at, class_types.name AS class_name,
                    COUNT(reservations.id) AS active_reservations
             FROM class_sessions
             INNER JOIN class_types ON class_types.id = class_sessions.class_type_id AND class_types.tenant_id = class_sessions.tenant_id
             LEFT JOIN reservations ON reservations.class_session_id = class_sessions.id
                AND reservations.tenant_id = class_sessions.tenant_id
                AND reservations.status IN ("reserved", "attended", "no_show")
             WHERE class_sessions.tenant_id = :tenant_id
             AND class_sessions.status = "SCHEDULED"
             AND class_sessions.starts_at >= NOW()
             GROUP BY class_sessions.id, class_sessions.capacity, class_sessions.starts_at, class_types.name
             HAVING active_reservations >= class_sessions.capacity
             LIMIT 50'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return array_map(static fn (array $row): array => [
            'type' => 'CLASS_FULL',
            'severity' => 'LOW',
            'class_session_id' => $row['id'],
            'message' => 'Clase llena: ' . $row['class_name'] . ' del ' . format_date_short($row['starts_at']) . '.',
        ], $stmt->fetchAll());
    }

    private static function count(PDO $pdo, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM risk_alerts WHERE tenant_id = :tenant_id AND {$where}");
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
