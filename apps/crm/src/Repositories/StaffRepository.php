<?php

declare(strict_types=1);

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
