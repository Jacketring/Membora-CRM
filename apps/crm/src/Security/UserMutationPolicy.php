<?php

declare(strict_types=1);

final class UserMutationPolicy
{
    public static function mayAssignRole(array $actor, string $roleKey): bool
    {
        $platformRole = in_array(strtoupper($roleKey), ['SUPER_ADMIN', 'SUPERADMIN'], true);

        return !$platformRole || is_platform_admin($actor);
    }

    public static function mayMutateTenant(array $actor, string $targetTenantId): bool
    {
        return is_platform_admin($actor) || hash_equals((string) ($actor['tenant_id'] ?? ''), $targetTenantId);
    }
}
