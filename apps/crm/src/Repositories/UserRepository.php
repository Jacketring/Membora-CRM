<?php

declare(strict_types=1);

final class UserRepository
{
    public static function ensureAvatarColumn(): void
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM users LIKE "avatar_path"');
        if (!$stmt->fetch()) {
            Database::connection()->exec('ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER email');
        }
    }

    public static function all(string $tenantId, string $query = '', string $roleId = '', string $status = ''): array
    {
        self::ensureAvatarColumn();
        $params = ['tenant_id' => $tenantId];
        $where = ['users.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(users.name LIKE :query OR users.email LIKE :query OR roles.key LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($roleId !== '') {
            $where[] = 'users.role_id = :role_id';
            $params['role_id'] = $roleId;
        }

        if ($status !== '') {
            $where[] = 'users.status = :status';
            $params['status'] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT users.id,
                    users.name,
                    users.email,
                    users.avatar_path,
                    users.status,
                    users.created_at,
                    users.updated_at,
                    users.last_login_at,
                    users.role_id,
                    roles.key AS role_key
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY users.status ASC, users.name ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function roles(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, `key` AS role_key
             FROM roles
             WHERE `key` NOT IN ("SUPER_ADMIN", "SUPERADMIN")
             ORDER BY CASE `key`
                WHEN "SUPER_ADMIN" THEN 1
                WHEN "SUPERADMIN" THEN 1
                WHEN "GYM_ADMIN" THEN 2
                WHEN "ADMIN" THEN 3
                WHEN "SALES_RECEPTION" THEN 4
                WHEN "RECEPTION" THEN 5
                WHEN "SALES" THEN 6
                WHEN "TRAINER" THEN 7
                WHEN "STAFF" THEN 8
                ELSE 99
             END, `key` ASC'
        );

        return $stmt->fetchAll();
    }

    public static function roleExists(string $roleId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM roles WHERE id = :id');
        $stmt->execute(['id' => $roleId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function assignableRoleExists(string $roleId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM roles WHERE id = :id AND `key` NOT IN ("SUPER_ADMIN", "SUPERADMIN")');
        $stmt->execute(['id' => $roleId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function isPlatformRole(string $roleId): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM roles WHERE id = :id AND `key` IN ("SUPER_ADMIN", "SUPERADMIN")');
        $stmt->execute(['id' => $roleId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function metrics(string $tenantId): array
    {
        $pdo = Database::connection();

        return [
            'active' => self::count($pdo, $tenantId, 'users.status = "ACTIVE"'),
            'inactive' => self::count($pdo, $tenantId, 'users.status = "INACTIVE"'),
            'admins' => self::count($pdo, $tenantId, 'users.status = "ACTIVE" AND roles.key IN ("SUPER_ADMIN", "GYM_ADMIN", "ADMIN")'),
            'total' => self::count($pdo, $tenantId, '1 = 1'),
        ];
    }

    public static function emailExists(?string $tenantId, string $email, ?string $exceptId = null): bool
    {
        $params = ['email' => $email];
        $where = 'email = :email';

        if ($exceptId) {
            $where .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }

        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM users WHERE {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function count(PDO $pdo, string $tenantId, string $where): int
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.tenant_id = :tenant_id AND {$where}"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }
}
