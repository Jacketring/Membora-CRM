<?php

declare(strict_types=1);

trait EmpresaTenantProvisioningTrait
{
    private static function createTenantAndAdmin(string $companyName, array $data, ?array $client = null): string
    {
        $companyName = trim($companyName);
        if ($companyName === '') {
            throw new RuntimeException('Indica el nombre de la empresa.');
        }

        $adminEmail = strtolower(trim((string) ($data['admin_email'] ?? '')));
        $adminName = trim((string) ($data['admin_name'] ?? '')) ?: ($client['contact_name'] ?? 'Administrador');
        $adminPassword = trim((string) ($data['admin_password'] ?? ''));
        if ($adminEmail === '' && $client) {
            $adminEmail = strtolower((string) $client['email']);
        }

        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Indica un email valido para el administrador de la empresa.');
        }

        if (strlen($adminPassword) < 8) {
            throw new RuntimeException('La contrasena del administrador debe tener al menos 8 caracteres.');
        }

        $pdo = Database::connection();
        $tenantId = cuid();
        $tenantColumns = self::tableColumns('tenants');
        $tenantValues = [
            'id' => $tenantId,
            'name' => $companyName,
            'slug' => self::uniqueTenantSlug($companyName),
            'primary_color' => '#004bf2',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $insertTenantColumns = array_values(array_intersect(array_keys($tenantValues), $tenantColumns));
        $tenantPlaceholders = array_map(static fn (string $column): string => ':' . $column, $insertTenantColumns);
        $tenantParams = array_intersect_key($tenantValues, array_flip($insertTenantColumns));
        $tenantInsert = $pdo->prepare(
            'INSERT INTO tenants (' . implode(', ', $insertTenantColumns) . ')
             VALUES (' . implode(', ', $tenantPlaceholders) . ')'
        );
        $tenantInsert->execute($tenantParams);

        self::ensureTenantAdminUser($tenantId, $adminName, $adminEmail, $adminPassword);

        self::seedTenantPipeline($tenantId);

        return $tenantId;
    }

    private static function ensureGymAdminRole(): string
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT id FROM roles WHERE `key` IN ("GYM_ADMIN", "ADMIN") ORDER BY `key` = "GYM_ADMIN" DESC LIMIT 1');
        $roleId = $stmt->fetchColumn();
        if ($roleId) {
            return (string) $roleId;
        }

        $roleId = cuid();
        $columns = self::tableColumns('roles');
        $values = [
            'id' => $roleId,
            'key' => 'GYM_ADMIN',
            'name' => 'Administrador',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $insertColumns = array_values(array_intersect(array_keys($values), $columns));
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $insertColumns);
        $params = array_intersect_key($values, array_flip($insertColumns));
        $insert = $pdo->prepare(
            'INSERT INTO roles (' . implode(', ', array_map(static fn (string $column): string => $column === 'key' ? '`key`' : $column, $insertColumns)) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $insert->execute($params);

        return $roleId;
    }

    private static function seedTenantPipeline(string $tenantId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pipeline_stages WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $tenantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO pipeline_stages (id, tenant_id, `key`, name, `order`, created_at, updated_at)
             VALUES (:id, :tenant_id, :key, :name, :order, NOW(), NOW())'
        );

        foreach ([
            ['NEW', 'Nuevo lead'],
            ['CONTACTED', 'Contactado'],
            ['TRIAL_SCHEDULED', 'Visita o prueba agendada'],
            ['PROPOSAL', 'Alta propuesta'],
            ['CONVERTED', 'Cliente'],
            ['LOST', 'Perdido'],
        ] as $index => $stage) {
            $insert->execute([
                'id' => cuid(),
                'tenant_id' => $tenantId,
                'key' => $stage[0],
                'name' => $stage[1],
                'order' => $index + 1,
            ]);
        }
    }

    private static function tenantSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? 'empresa'));
        return trim($slug, '-') ?: 'empresa';
    }

    private static function uniqueTenantSlug(string $name): string
    {
        $columns = self::tableColumns('tenants');
        $base = self::tenantSlug($name);
        if (!in_array('slug', $columns, true)) {
            return $base;
        }

        $pdo = Database::connection();
        $slug = $base;
        $suffix = 2;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE slug = :slug');
        while (true) {
            $stmt->execute(['slug' => $slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }

            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }

    private static function usersTenantAllowsNull(): bool
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM users LIKE "tenant_id"');
        $column = $stmt->fetch();

        return !$column || strtoupper((string) ($column['Null'] ?? 'YES')) === 'YES';
    }

    private static function firstTenantId(): ?string
    {
        try {
            $stmt = Database::connection()->query('SELECT id FROM tenants ORDER BY created_at ASC LIMIT 1');
            return $stmt->fetchColumn() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function count(PDO $pdo, string $where): int
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM empresas WHERE {$where}");
        return (int) $stmt->fetchColumn();
    }
}
