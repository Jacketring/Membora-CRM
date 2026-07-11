<?php

declare(strict_types=1);

final class TenantRepository
{
    public static function ensureSettingsColumns(): void
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM tenants LIKE "primary_color"');
        if (!$stmt->fetch()) {
            Database::connection()->exec('ALTER TABLE tenants ADD COLUMN primary_color VARCHAR(16) NULL AFTER name');
        }
    }

    public static function updateSettings(string $tenantId, string $name, string $primaryColor): void
    {
        self::ensureSettingsColumns();
        $stmt = Database::connection()->prepare(
            'UPDATE tenants SET name = :name, primary_color = :primary_color WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'primary_color' => $primaryColor,
            'id' => $tenantId,
        ]);
    }
}
