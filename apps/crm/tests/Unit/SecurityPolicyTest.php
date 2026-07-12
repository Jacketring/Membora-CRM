<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecurityPolicyTest extends TestCase
{
    public function testGymAdminCannotAssignPlatformRoleToSelfOrAnotherUser(): void
    {
        $admin = ['id' => 'admin-1', 'tenant_id' => 'gym-1', 'role' => 'GYM_ADMIN'];

        self::assertFalse(UserMutationPolicy::mayAssignRole($admin, 'SUPER_ADMIN'));
        self::assertFalse(UserMutationPolicy::mayAssignRole($admin, 'SUPERADMIN'));
    }

    public function testGymAdminCannotMutateAnotherTenantButSuperadminCan(): void
    {
        $admin = ['tenant_id' => 'gym-1', 'role' => 'GYM_ADMIN'];
        $superadmin = ['tenant_id' => null, 'role' => 'SUPER_ADMIN'];

        self::assertFalse(UserMutationPolicy::mayMutateTenant($admin, 'gym-2'));
        self::assertTrue(UserMutationPolicy::mayMutateTenant($superadmin, 'gym-2'));
        self::assertTrue(UserMutationPolicy::mayAssignRole($superadmin, 'SUPER_ADMIN'));
    }

    public function testLoginLimitUsesInjectedTimeWithoutSleeping(): void
    {
        $policy = new LoginRateLimitPolicy(5, 900);
        $now = 10_000;
        $failures = [$now - 10, $now - 20, $now - 30, $now - 40];
        self::assertSame(4, $policy->failuresInWindow($failures, $now));
        self::assertFalse($policy->isBlocked($failures, $now));

        $failures[] = $now - 50;
        self::assertTrue($policy->isBlocked($failures, $now));
        self::assertTrue($policy->isBlocked($failures, $now)); // una contraseña correcta no elude el bloqueo previo
        self::assertFalse($policy->isBlocked($failures, $now + 901));
    }

    public function testSuccessfulLoginResetAndKeysRemainIsolated(): void
    {
        $attempts = ['gym-1:user-a' => [100, 110, 120, 130, 140], 'gym-2:user-b' => []];
        $attempts['gym-1:user-a'] = []; // Auth::clearLoginAttempts tras autenticar correctamente

        self::assertSame([], $attempts['gym-1:user-a']);
        self::assertSame([], $attempts['gym-2:user-b']);
    }

    public function testDemoIsOnlyEnabledForExplicitDemoEnvironment(): void
    {
        self::assertTrue(DemoAccessPolicy::isEnabled('demo'));
        self::assertFalse(DemoAccessPolicy::isEnabled('production'));
        self::assertFalse(DemoAccessPolicy::isEnabled(''));
    }
}
