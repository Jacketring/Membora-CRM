<?php

declare(strict_types=1);

final class GlobalSearchRepository
{
    public static function autocomplete(string $tenantId, string $query): array
    {
        $results = self::search($tenantId, $query);
        $items = [];

        foreach ($results['leads'] as $lead) {
            $name = trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''));
            $items[] = [
                'type' => 'Lead',
                'kind' => 'lead',
                'title' => $name,
                'description' => $lead['email'] ?: ($lead['phone'] ?: ($lead['interest'] ?: status_label($lead['status']))),
                'href' => 'index.php?route=leads&q=' . urlencode($query),
            ];
        }

        foreach ($results['tasks'] as $task) {
            $items[] = [
                'type' => 'Tarea',
                'kind' => 'task',
                'title' => $task['title'],
                'description' => format_date($task['due_at']) . ' - ' . ($task['assigned_name'] ?: 'Sin responsable'),
                'href' => 'index.php?route=tasks&q=' . urlencode($query),
            ];
        }

        foreach ($results['members'] as $member) {
            $name = trim($member['first_name'] . ' ' . ($member['last_name'] ?? ''));
            $items[] = [
                'type' => 'Socio',
                'kind' => 'member',
                'title' => $name,
                'description' => $member['email'] ?: ($member['phone'] ?: status_label($member['status'])),
                'href' => 'index.php?route=members&q=' . urlencode($query),
            ];
        }

        foreach ($results['memberships'] as $plan) {
            $items[] = [
                'type' => 'Membresia',
                'kind' => 'membership',
                'title' => $plan['name'],
                'description' => $plan['description'] ?: 'Plan de membresia',
                'href' => '',
            ];
        }

        foreach ($results['classes'] as $class) {
            $items[] = [
                'type' => 'Clase',
                'kind' => 'class',
                'title' => $class['name'],
                'description' => $class['description'] ?: 'Tipo de clase',
                'href' => 'index.php?route=classes&q=' . urlencode($query),
            ];
        }

        return array_slice($items, 0, 10);
    }

    public static function search(string $tenantId, string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'leads' => [],
                'tasks' => [],
                'members' => [],
                'memberships' => [],
                'classes' => [],
            ];
        }

        return [
            'leads' => LeadRepository::all($tenantId, $query, '', '', '', '', '', 6),
            'tasks' => TaskRepository::all($tenantId, $query, '', '', '', '', '', 6),
            'members' => self::members($tenantId, $query),
            'memberships' => self::membershipPlans($tenantId, $query),
            'classes' => self::classes($tenantId, $query),
        ];
    }

    private static function members(string $tenantId, string $query): array
    {
        return self::safeFetch(
            'SELECT id, first_name, last_name, email, phone, status
             FROM members
             WHERE tenant_id = :tenant_id
             AND (first_name LIKE :query OR last_name LIKE :query OR email LIKE :query OR phone LIKE :query)
             ORDER BY updated_at DESC, created_at DESC
             LIMIT 6',
            $tenantId,
            $query
        );
    }

    private static function membershipPlans(string $tenantId, string $query): array
    {
        if (!self::tableExists('membership_plans')) {
            return [];
        }

        return self::safeFetch(
            'SELECT id, name, description
             FROM membership_plans
             WHERE tenant_id = :tenant_id
             AND (name LIKE :query OR description LIKE :query)
             ORDER BY name ASC
             LIMIT 6',
            $tenantId,
            $query
        );
    }

    private static function classes(string $tenantId, string $query): array
    {
        if (!self::tableExists('class_types')) {
            return [];
        }

        return self::safeFetch(
            'SELECT id, name, description
             FROM class_types
             WHERE tenant_id = :tenant_id
             AND (name LIKE :query OR description LIKE :query)
             ORDER BY name ASC
             LIMIT 6',
            $tenantId,
            $query
        );
    }

    private static function safeFetch(string $sql, string $tenantId, string $query): array
    {
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'tenant_id' => $tenantId,
                'query' => '%' . $query . '%',
            ]);

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private static function tableExists(string $table): bool
    {
        try {
            $stmt = Database::connection()->prepare('SHOW TABLES LIKE :table_name');
            $stmt->execute(['table_name' => $table]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
