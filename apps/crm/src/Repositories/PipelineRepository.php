<?php

declare(strict_types=1);

final class PipelineRepository
{
    public static function all(string $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM pipeline_stages WHERE tenant_id = :tenant_id ORDER BY `order` ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    public static function firstId(string $tenantId): ?string
    {
        $stages = self::all($tenantId);
        return $stages[0]['id'] ?? null;
    }

    public static function lostId(string $tenantId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM pipeline_stages WHERE tenant_id = :tenant_id AND `key` = "LOST" LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchColumn() ?: null;
    }

    public static function convertedId(string $tenantId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM pipeline_stages
             WHERE tenant_id = :tenant_id
             AND (`key` LIKE "%CONVERT%" OR LOWER(name) LIKE "%convert%")
             ORDER BY `order` ASC
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchColumn() ?: null;
    }

    public static function contactedId(string $tenantId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM pipeline_stages
             WHERE tenant_id = :tenant_id
             AND (`key` LIKE "%CONTACT%" OR LOWER(name) LIKE "%contact%")
             ORDER BY `order` ASC
             LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchColumn() ?: self::firstId($tenantId);
    }

    public static function find(string $tenantId, string $stageId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM pipeline_stages WHERE tenant_id = :tenant_id AND id = :id LIMIT 1'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $stageId]);
        $stage = $stmt->fetch();

        return $stage ?: null;
    }

    public static function statusForStage(?array $stage): string
    {
        $key = strtoupper((string) ($stage['key'] ?? ''));
        $name = strtolower((string) ($stage['name'] ?? ''));

        if (str_contains($key, 'LOST') || str_contains($name, 'perdido')) {
            return 'LOST';
        }

        if (str_contains($key, 'CONVERT') || str_contains($name, 'convertido')) {
            return 'CONVERTED';
        }

        return 'OPEN';
    }
}
