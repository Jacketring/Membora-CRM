<?php

declare(strict_types=1);

namespace Membora;

final class DashboardMetrics
{
    /** @return array{conversionRate:int,openLeadRate:int,lostLeadRate:int,taskCompletionRate:int,attentionRequired:int} */
    public static function calculate(array $summary): array
    {
        $totalLeads = max(1, (int) ($summary['totalLeads'] ?? 0));
        $pendingTasks = (int) ($summary['pendingTasks'] ?? 0);
        $completedTasks = (int) ($summary['completedTasks'] ?? 0);
        $taskTotal = max(1, $pendingTasks + $completedTasks);

        return [
            'conversionRate' => self::percentage((int) ($summary['convertedLeads'] ?? 0), $totalLeads),
            'openLeadRate' => self::percentage((int) ($summary['openLeads'] ?? 0), $totalLeads),
            'lostLeadRate' => self::percentage((int) ($summary['lostLeads'] ?? 0), $totalLeads),
            'taskCompletionRate' => self::percentage($completedTasks, $taskTotal),
            'attentionRequired' => max(0,
                (int) ($summary['overdueTasks'] ?? 0)
                + (int) ($summary['openAlerts'] ?? 0)
                + (int) ($summary['pendingPayments'] ?? 0)
            ),
        ];
    }

    private static function percentage(int $part, int $total): int
    {
        return max(0, min(100, (int) round(($part / max(1, $total)) * 100)));
    }
}
