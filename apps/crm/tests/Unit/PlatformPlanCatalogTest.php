<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlatformPlanCatalogTest extends TestCase
{
    public function testCanonicalPaidPlanCatalog(): void
    {
        $method = new ReflectionMethod(PlatformPlanRepository::class, 'defaultPlans');
        $plans = array_values(array_filter(
            $method->invoke(null),
            static fn (array $plan): bool => $plan['code'] !== 'TRIAL'
        ));

        self::assertSame(
            [
                ['code' => 'BASIC', 'name' => 'Basic', 'monthly_price' => '49.00', 'max_users' => 3, 'max_members' => 300],
                ['code' => 'PRO', 'name' => 'Pro', 'monthly_price' => '89.00', 'max_users' => 8, 'max_members' => 1000],
                ['code' => 'BUSINESS', 'name' => 'Business', 'monthly_price' => '149.00', 'max_users' => 20, 'max_members' => 3000],
                ['code' => 'ENTERPRISE', 'name' => 'Enterprise', 'monthly_price' => '299.00', 'max_users' => null, 'max_members' => null],
            ],
            array_map(
                static fn (array $plan): array => array_intersect_key($plan, array_flip(['code', 'name', 'monthly_price', 'max_users', 'max_members'])),
                $plans
            )
        );

        foreach ($plans as $plan) {
            self::assertNotEmpty($plan['features']);
        }
    }
}
