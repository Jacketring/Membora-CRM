<?php

declare(strict_types=1);

final class PlatformLeadRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS platform_leads (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                company_name VARCHAR(191) NULL,
                contact_name VARCHAR(191) NOT NULL,
                email VARCHAR(191) NULL,
                phone VARCHAR(64) NULL,
                message TEXT NULL,
                source VARCHAR(64) NOT NULL DEFAULT "WEB",
                status VARCHAR(32) NOT NULL DEFAULT "NEW",
                client_id VARCHAR(191) NULL,
                source_url VARCHAR(500) NULL,
                payload_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                converted_at DATETIME NULL,
                INDEX platform_leads_status_idx (status),
                INDEX platform_leads_email_idx (email),
                INDEX platform_leads_created_at_idx (created_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public static function metrics(): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        return [
            'new' => self::count($pdo, 'status = "NEW"'),
            'contacted' => self::count($pdo, 'status = "CONTACTED"'),
            'qualified' => self::count($pdo, 'status = "QUALIFIED"'),
            'converted' => self::count($pdo, 'status = "CONVERTED"'),
            'lost' => self::count($pdo, 'status = "LOST"'),
        ];
    }

    public static function all(string $query = '', string $status = ''): array
    {
        self::ensureTable();
        $params = [];
        $where = ['1 = 1'];

        if ($query !== '') {
            $where[] = '(company_name LIKE :query OR contact_name LIKE :query OR email LIKE :query OR phone LIKE :query OR message LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT platform_leads.*,
                    platform_clients.company_name AS client_company_name,
                    platform_clients.status AS client_status
             FROM platform_leads
             LEFT JOIN platform_clients ON platform_clients.id = platform_leads.client_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(platform_leads.status, "NEW", "CONTACTED", "QUALIFIED", "CONVERTED", "LOST"), platform_leads.created_at DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function find(string $id): ?array
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('SELECT * FROM platform_leads WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $lead = $stmt->fetch();

        return $lead ?: null;
    }

    public static function createFromPayload(array $payload): string
    {
        self::ensureTable();
        $normalized = self::normalizePayload($payload);
        $existing = self::findOpenDuplicate($normalized['email'], $normalized['phone']);

        if ($existing) {
            $stmt = Database::connection()->prepare(
                'UPDATE platform_leads
                 SET message = CONCAT(COALESCE(message, ""), IF(COALESCE(message, "") = "" OR COALESCE(:message_append, "") = "", "", "\n\n"), COALESCE(:message_text, "")),
                     company_name = COALESCE(NULLIF(:company_name, ""), company_name),
                     source_url = :source_url,
                     payload_json = :payload_json,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'message_append' => $normalized['message'],
                'message_text' => $normalized['message'],
                'company_name' => $normalized['company_name'],
                'source_url' => $normalized['source_url'],
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                'id' => $existing['id'],
            ]);

            return (string) $existing['id'];
        }

        $id = cuid();
        $stmt = Database::connection()->prepare(
            'INSERT INTO platform_leads (id, company_name, contact_name, email, phone, message, source, status, source_url, payload_json, created_at, updated_at)
             VALUES (:id, :company_name, :contact_name, :email, :phone, :message, :source, "NEW", :source_url, :payload_json, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => $id,
            'company_name' => $normalized['company_name'],
            'contact_name' => $normalized['contact_name'],
            'email' => $normalized['email'],
            'phone' => $normalized['phone'],
            'message' => $normalized['message'],
            'source' => $normalized['source'],
            'source_url' => $normalized['source_url'],
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
        ]);

        return $id;
    }

    public static function update(string $id, array $data): void
    {
        self::ensureTable();
        $status = self::statusFromData($data['status'] ?? 'NEW');
        $stmt = Database::connection()->prepare(
            'UPDATE platform_leads
             SET company_name = :company_name,
                 contact_name = :contact_name,
                 email = :email,
                 phone = :phone,
                 message = :message,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'company_name' => trim((string) ($data['company_name'] ?? '')) ?: null,
            'contact_name' => trim((string) ($data['contact_name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'message' => trim((string) ($data['message'] ?? '')) ?: null,
            'status' => $status,
            'id' => $id,
        ]);
    }

    public static function convertToClient(string $id): string
    {
        self::ensureTable();
        PlatformClientRepository::ensureTable();
        $lead = self::find($id);

        if (!$lead) {
            throw new RuntimeException('No se encontro el lead.');
        }

        if (!empty($lead['client_id'])) {
            PlatformClientRepository::markCustomer((string) $lead['client_id']);
            return (string) $lead['client_id'];
        }

        $clientId = cuid();
        $stmt = Database::connection()->prepare(
            'INSERT INTO platform_clients (id, company_name, contact_name, email, phone, status, notes, created_at, updated_at)
             VALUES (:id, :company_name, :contact_name, :email, :phone, "CUSTOMER", :notes, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => $clientId,
            'company_name' => $lead['company_name'] ?: ('Centro de ' . $lead['contact_name']),
            'contact_name' => $lead['contact_name'],
            'email' => $lead['email'] ?: null,
            'phone' => $lead['phone'] ?: null,
            'notes' => trim((string) ($lead['message'] ?? '')) ?: 'Cliente creado desde lead web.',
        ]);

        $update = Database::connection()->prepare(
            'UPDATE platform_leads SET status = "CONVERTED", client_id = :client_id, converted_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $update->execute(['client_id' => $clientId, 'id' => $id]);

        return $clientId;
    }

    public static function delete(string $id): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('DELETE FROM platform_leads WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private static function normalizePayload(array $payload): array
    {
        $name = trim((string) ($payload['nombre'] ?? $payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['apellidos'] ?? $payload['last_name'] ?? ''));
        $contactName = trim($name . ' ' . $lastName);
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $phone = trim((string) ($payload['telefono'] ?? $payload['phone'] ?? ''));
        $message = trim((string) ($payload['mensaje'] ?? $payload['message'] ?? ''));
        $company = self::companyFromPayload($payload, $message);

        if ($contactName === '') {
            $contactName = $email ?: ($phone ?: 'Lead web');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email no valido.');
        }

        if ($email === '' && $phone === '') {
            throw new RuntimeException('El lead debe incluir email o telefono.');
        }

        return [
            'company_name' => $company,
            'contact_name' => substr($contactName, 0, 191),
            'email' => $email ?: null,
            'phone' => substr($phone, 0, 64) ?: null,
            'message' => substr($message, 0, 4000) ?: null,
            'source' => substr(strtoupper((string) ($payload['origen'] ?? 'WEB')), 0, 64) ?: 'WEB',
            'source_url' => substr((string) ($payload['url_origen'] ?? $payload['source_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 500) ?: null,
        ];
    }

    private static function companyFromPayload(array $payload, string $message): ?string
    {
        $company = trim((string) ($payload['empresa'] ?? $payload['company'] ?? $payload['company_name'] ?? ''));
        if ($company !== '') {
            return substr($company, 0, 191);
        }

        if (preg_match('/Empresa\/gimnasio:\s*(.+)$/mi', $message, $matches)) {
            return substr(trim($matches[1]), 0, 191) ?: null;
        }

        return null;
    }

    private static function findOpenDuplicate(?string $email, ?string $phone): ?array
    {
        $conditions = [];
        $params = [];

        if ($email) {
            $conditions[] = 'LOWER(email) = :email';
            $params['email'] = strtolower($email);
        }

        if ($phone) {
            $conditions[] = 'phone = :phone';
            $params['phone'] = $phone;
        }

        if (!$conditions) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id FROM platform_leads
             WHERE status IN ("NEW", "CONTACTED", "QUALIFIED")
             AND (' . implode(' OR ', $conditions) . ')
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute($params);
        $lead = $stmt->fetch();

        return $lead ?: null;
    }

    private static function statusFromData(string $status): string
    {
        return in_array($status, ['NEW', 'CONTACTED', 'QUALIFIED', 'CONVERTED', 'LOST'], true) ? $status : 'NEW';
    }

    private static function count(PDO $pdo, string $where): int
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM platform_leads WHERE {$where}");
        return (int) $stmt->fetchColumn();
    }
}
