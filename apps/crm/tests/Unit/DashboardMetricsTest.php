<?php

declare(strict_types=1);

use Membora\DashboardMetrics;
use PHPUnit\Framework\TestCase;

final class DashboardMetricsTest extends TestCase
{
    public function testCalculatesDashboardRatesAndAttention(): void
    {
        self::assertSame([
            'conversionRate' => 25,
            'openLeadRate' => 50,
            'lostLeadRate' => 25,
            'taskCompletionRate' => 75,
            'attentionRequired' => 6,
        ], DashboardMetrics::calculate([
            'totalLeads' => 20, 'convertedLeads' => 5, 'openLeads' => 10, 'lostLeads' => 5,
            'pendingTasks' => 2, 'completedTasks' => 6,
            'overdueTasks' => 1, 'openAlerts' => 2, 'pendingPayments' => 3,
        ]));
    }

    public function testHandlesEmptyAndOutOfRangeInputSafely(): void
    {
        self::assertSame(0, DashboardMetrics::calculate([])['conversionRate']);
        self::assertSame(0, DashboardMetrics::calculate([])['taskCompletionRate']);
        self::assertSame(100, DashboardMetrics::calculate(['totalLeads' => 1, 'convertedLeads' => 9])['conversionRate']);
        self::assertSame(0, DashboardMetrics::calculate(['overdueTasks' => -5])['attentionRequired']);
    }
}
