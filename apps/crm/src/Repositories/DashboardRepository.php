<?php

declare(strict_types=1);

final class DashboardRepository
{
    public static function summary(string $tenantId): array
    {
        $pdo = Database::connection();
        PaymentRepository::ensureTable();
        RiskAlertRepository::ensureTable();
        RiskAlertRepository::generate($tenantId);

        return [
            'activeMembers' => self::count($pdo, 'members', 'status <> "INACTIVE"', $tenantId),
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
            'recentLeads' => LeadRepository::all($tenantId, '', '', '', '', '', '', 5),
            'tasks' => TaskRepository::all($tenantId, '', '', '', '', '', '', 5),
        ];
    }

    private static function count(PDO $pdo, string $table, string $where, string $tenantId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }
}
