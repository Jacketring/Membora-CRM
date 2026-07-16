<?php

declare(strict_types=1);

final class PlatformClientRepository
{
    public static function ensureTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS platform_clients (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                company_name VARCHAR(191) NOT NULL,
                contact_name VARCHAR(191) NULL,
                email VARCHAR(191) NULL,
                phone VARCHAR(64) NULL,
                status VARCHAR(32) NOT NULL DEFAULT "LEAD",
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX platform_clients_status_idx (status),
                INDEX platform_clients_email_idx (email)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    public static function metrics(): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        return [
            'lead' => self::count($pdo, 'status = "LEAD"'),
            'qualified' => self::count($pdo, 'status = "QUALIFIED"'),
            'customer' => self::count($pdo, 'status = "CUSTOMER"'),
            'lost' => self::count($pdo, 'status = "LOST"'),
        ];
    }

    public static function all(string $query = '', string $status = '', bool $includeLeadStatus = false): array
    {
        self::ensureTable();
        $params = [];
        $where = ['1 = 1'];

        if ($query !== '') {
            $where[] = '(company_name LIKE :query OR contact_name LIKE :query OR email LIKE :query OR phone LIKE :query OR notes LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        } elseif (!$includeLeadStatus) {
            $where[] = 'status <> "LEAD"';
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM platform_clients
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(status, "QUALIFIED", "LEAD", "CUSTOMER", "LOST"), updated_at DESC, company_name ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function find(string $id): ?array
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare('SELECT * FROM platform_clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $client = $stmt->fetch();

        return $client ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'SELECT * FROM platform_clients
             WHERE LOWER(email) = :email
             ORDER BY updated_at DESC
             LIMIT 1'
        );
        $stmt->execute(['email' => strtolower(trim($email))]);
        $client = $stmt->fetch();

        return $client ?: null;
    }

    public static function upsertTrialCustomer(string $company, string $contactName, string $email): string
    {
        self::ensureTable();

        $company = trim($company);
        $contactName = trim($contactName);
        $email = strtolower(trim($email));
        $pdo = Database::connection();
        $existing = self::findByEmail($email);
        $clientId = (string) ($existing['id'] ?? '');

        if ($clientId !== '') {
            $stmt = $pdo->prepare(
                'UPDATE platform_clients
                 SET company_name = :company_name,
                     contact_name = :contact_name,
                     email = :email,
                     status = "CUSTOMER",
                     notes = CASE
                         WHEN notes IS NULL OR notes = "" THEN :notes_empty
                         WHEN notes LIKE "%Alta self-service de prueba%" THEN notes
                         ELSE CONCAT(notes, "\n", :notes_append)
                     END,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $clientId,
                'company_name' => $company,
                'contact_name' => $contactName ?: null,
                'email' => $email,
                'notes_empty' => 'Alta self-service de prueba durante 14 dias.',
                'notes_append' => 'Alta self-service de prueba durante 14 dias.',
            ]);
            self::markLinkedLeadConverted($clientId);

            return $clientId;
        }

        $clientId = cuid();
        $stmt = $pdo->prepare(
            'INSERT INTO platform_clients
             (id, company_name, contact_name, email, phone, status, notes, created_at, updated_at)
             VALUES (:id, :company_name, :contact_name, :email, NULL, "CUSTOMER", :notes, NOW(), NOW())'
        );
        $stmt->execute([
            'id' => $clientId,
            'company_name' => $company,
            'contact_name' => $contactName ?: null,
            'email' => $email,
            'notes' => 'Alta self-service de prueba durante 14 dias.',
        ]);

        return $clientId;
    }

    public static function create(array $data): void
    {
        self::ensureTable();
        $params = self::clientParams($data);
        $clientId = cuid();
        $stmt = Database::connection()->prepare(
            'INSERT INTO platform_clients (id, company_name, contact_name, email, phone, status, notes, created_at, updated_at)
             VALUES (:id, :company_name, :contact_name, :email, :phone, :status, :notes, NOW(), NOW())'
        );
        $stmt->execute($params + ['id' => $clientId]);

        if ($params['status'] === 'LEAD') {
            self::syncLeadFromClient($clientId, $params);
        }
    }

    public static function update(string $id, array $data): void
    {
        self::ensureTable();
        $params = self::clientParams($data);
        $stmt = Database::connection()->prepare(
            'UPDATE platform_clients
             SET company_name = :company_name,
                 contact_name = :contact_name,
                 email = :email,
                 phone = :phone,
                 status = :status,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute($params + ['id' => $id]);

        if ($params['status'] === 'LEAD') {
            self::syncLeadFromClient($id, $params);
        } else {
            self::markLinkedLeadConverted($id);
        }
    }

    public static function markCustomer(string $id): void
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'UPDATE platform_clients SET status = "CUSTOMER", updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function delete(string $id): void
    {
        self::ensureTable();
        PlatformLeadRepository::ensureTable();
        EmpresaRepository::ensureTables();

        $pdo = Database::connection();
        $pdo->prepare('UPDATE empresas SET client_id = NULL, updated_at = NOW() WHERE client_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM platform_leads WHERE client_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM platform_clients WHERE id = :id')->execute(['id' => $id]);
    }

    private static function markLinkedLeadConverted(string $clientId): void
    {
        PlatformLeadRepository::ensureTable();
        $stmt = Database::connection()->prepare(
            'UPDATE platform_leads
             SET status = "CONVERTED",
                 converted_at = COALESCE(converted_at, NOW()),
                 updated_at = NOW()
             WHERE client_id = :client_id'
        );
        $stmt->execute(['client_id' => $clientId]);
    }

    private static function clientParams(array $data): array
    {
        $status = in_array($data['status'] ?? '', ['LEAD', 'QUALIFIED', 'CUSTOMER', 'LOST'], true) ? $data['status'] : 'LEAD';
        if (($data['contact_type'] ?? '') === 'lead') {
            $status = 'LEAD';
        } elseif (($data['contact_type'] ?? '') === 'client' && $status === 'LEAD') {
            $status = 'QUALIFIED';
        }

        return [
            'company_name' => trim((string) ($data['company_name'] ?? '')),
            'contact_name' => trim((string) ($data['contact_name'] ?? '')) ?: null,
            'email' => strtolower(trim((string) ($data['email'] ?? ''))) ?: null,
            'phone' => phone_from_post() ?: (trim((string) ($data['phone'] ?? '')) ?: null),
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    private static function syncLeadFromClient(string $clientId, array $client): void
    {
        PlatformLeadRepository::ensureTable();
        $pdo = Database::connection();

        $existing = $pdo->prepare('SELECT id FROM platform_leads WHERE client_id = :client_id LIMIT 1');
        $existing->execute(['client_id' => $clientId]);
        $leadId = $existing->fetchColumn();

        if ($leadId) {
            $update = $pdo->prepare(
                'UPDATE platform_leads
                 SET company_name = :company_name,
                     contact_name = :contact_name,
                     email = :email,
                     phone = :phone,
                     message = :message,
                     status = "NEW",
                     converted_at = NULL,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'company_name' => $client['company_name'],
                'contact_name' => $client['contact_name'] ?: $client['company_name'],
                'email' => $client['email'],
                'phone' => $client['phone'],
                'message' => $client['notes'] ?: 'Cliente devuelto a lead desde el panel CRM.',
                'id' => $leadId,
            ]);
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO platform_leads (id, company_name, contact_name, email, phone, message, status, client_id, converted_at, created_at, updated_at)
             VALUES (:id, :company_name, :contact_name, :email, :phone, :message, "NEW", :client_id, NULL, NOW(), NOW())'
        );
        $insert->execute([
            'id' => cuid(),
            'company_name' => $client['company_name'],
            'contact_name' => $client['contact_name'] ?: $client['company_name'],
            'email' => $client['email'],
            'phone' => $client['phone'],
            'message' => $client['notes'] ?: 'Cliente devuelto a lead desde el panel CRM.',
            'client_id' => $clientId,
        ]);
    }

    public static function syncLeadStatusClients(): void
    {
        self::ensureTable();
        PlatformLeadRepository::ensureTable();

        $stmt = Database::connection()->query(
            'SELECT platform_clients.*
             FROM platform_clients
             LEFT JOIN platform_leads ON platform_leads.client_id = platform_clients.id
             WHERE platform_clients.status = "LEAD"
               AND platform_leads.id IS NULL'
        );

        foreach ($stmt->fetchAll() as $client) {
            self::syncLeadFromClient((string) $client['id'], [
                'company_name' => $client['company_name'],
                'contact_name' => $client['contact_name'],
                'email' => $client['email'],
                'phone' => $client['phone'],
                'status' => 'LEAD',
                'notes' => $client['notes'],
            ]);
        }
    }

    private static function count(PDO $pdo, string $where): int
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM platform_clients WHERE {$where}");
        return (int) $stmt->fetchColumn();
    }
}
