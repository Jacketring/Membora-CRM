<?php

declare(strict_types=1);

final class LeadRepository
{
    public static function all(
        string $tenantId,
        string $query = '',
        string $stageId = '',
        string $status = '',
        string $source = '',
        string $dateFrom = '',
        string $dateTo = '',
        int $limit = 100
    ): array {
        $params = ['tenant_id' => $tenantId];
        $where = ['leads.tenant_id = :tenant_id'];

        if ($query !== '') {
            $where[] = '(leads.first_name LIKE :query OR leads.last_name LIKE :query OR leads.email LIKE :query OR leads.phone LIKE :query OR leads.interest LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($stageId !== '') {
            $where[] = 'leads.pipeline_stage_id = :stage_id';
            $params['stage_id'] = $stageId;
        }

        if ($status !== '') {
            $where[] = 'leads.status = :status';
            $params['status'] = $status;
        }

        if ($source !== '') {
            $where[] = 'leads.source = :source';
            $params['source'] = $source;
        }

        if ($dateFrom !== '') {
            $where[] = 'DATE(leads.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = 'DATE(leads.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $sql = 'SELECT leads.*, pipeline_stages.name AS stage_name, pipeline_stages.key AS stage_key, users.name AS assigned_name
                FROM leads
                INNER JOIN pipeline_stages ON pipeline_stages.id = leads.pipeline_stage_id
                LEFT JOIN users ON users.id = leads.assigned_user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY leads.created_at DESC
                LIMIT ' . max(1, min($limit, 200));

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function notesByLeadIds(string $tenantId, array $leadIds): array
    {
        self::ensureNotesTable();

        if (!$leadIds) {
            return [];
        }

        $placeholders = [];
        $params = ['tenant_id' => $tenantId];
        foreach (array_values($leadIds) as $index => $leadId) {
            $key = 'lead_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $leadId;
        }

        $stmt = Database::connection()->prepare(
            'SELECT lead_notes.*, users.name AS user_name
             FROM lead_notes
             LEFT JOIN users ON users.id = lead_notes.user_id
             WHERE lead_notes.tenant_id = :tenant_id
             AND lead_notes.lead_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY lead_notes.created_at DESC'
        );
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll() as $note) {
            $grouped[$note['lead_id']][] = $note;
        }

        return $grouped;
    }

    public static function metrics(string $tenantId): array
    {
        $pdo = Database::connection();
        $total = self::countByStatus($pdo, $tenantId, null);
        $converted = self::countByStatus($pdo, $tenantId, 'CONVERTED');

        return [
            'open' => self::countByStatus($pdo, $tenantId, 'OPEN'),
            'converted' => $converted,
            'lost' => self::countByStatus($pdo, $tenantId, 'LOST'),
            'conversion' => $total > 0 ? (int) round(($converted / $total) * 100) : 0,
        ];
    }

    private static function countByStatus(PDO $pdo, string $tenantId, ?string $status): int
    {
        if ($status === null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE tenant_id = :tenant_id');
            $stmt->execute(['tenant_id' => $tenantId]);
            return (int) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE tenant_id = :tenant_id AND status = :status');
        $stmt->execute(['tenant_id' => $tenantId, 'status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    public static function ensureNotesTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS lead_notes (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                lead_id VARCHAR(191) NOT NULL,
                user_id VARCHAR(191) NULL,
                note TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX lead_notes_tenant_id_idx (tenant_id),
                INDEX lead_notes_lead_id_idx (lead_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }
}
