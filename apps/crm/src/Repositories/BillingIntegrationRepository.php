<?php

declare(strict_types=1);

final class BillingIntegrationRepository
{
    public static function ensureTables(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS billing_integrations (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                provider_name VARCHAR(120) NOT NULL,
                endpoint_url VARCHAR(255) NULL,
                api_key_mask VARCHAR(64) NULL,
                status VARCHAR(32) NOT NULL DEFAULT "INACTIVE",
                export_format VARCHAR(16) NOT NULL DEFAULT "CSV",
                notes TEXT NULL,
                last_sync_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX billing_integrations_tenant_id_idx (tenant_id),
                INDEX billing_integrations_status_idx (status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS billing_sync_logs (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                tenant_id VARCHAR(191) NOT NULL,
                integration_id VARCHAR(191) NULL,
                operation VARCHAR(32) NOT NULL,
                status VARCHAR(32) NOT NULL,
                payments_count INT NOT NULL DEFAULT 0,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                message TEXT NULL,
                payload TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX billing_sync_logs_tenant_id_idx (tenant_id),
                INDEX billing_sync_logs_operation_idx (operation),
                INDEX billing_sync_logs_status_idx (status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        self::ensureColumn('billing_integrations', 'endpoint_url', 'VARCHAR(255) NULL');
        self::ensureColumn('billing_integrations', 'api_key_mask', 'VARCHAR(64) NULL');
        self::ensureColumn('billing_integrations', 'export_format', 'VARCHAR(16) NOT NULL DEFAULT "CSV"');
        self::ensureColumn('billing_integrations', 'last_sync_at', 'DATETIME NULL');
        self::ensureColumn('billing_sync_logs', 'payload', 'TEXT NULL');
        PaymentRepository::ensureTable();
    }

    public static function settings(string $tenantId): array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare('SELECT * FROM billing_integrations WHERE tenant_id = :tenant_id ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
        $settings = $stmt->fetch();

        return $settings ?: [
            'id' => '',
            'tenant_id' => $tenantId,
            'provider_name' => 'Proveedor externo',
            'endpoint_url' => '',
            'api_key_mask' => '',
            'status' => 'INACTIVE',
            'export_format' => 'CSV',
            'notes' => '',
            'last_sync_at' => null,
        ];
    }

    public static function saveSettings(string $tenantId, array $data): void
    {
        self::ensureTables();
        $current = self::settings($tenantId);
        $apiKey = trim((string) ($data['api_key'] ?? ''));
        $params = [
            'tenant_id' => $tenantId,
            'provider_name' => trim((string) ($data['provider_name'] ?? '')) ?: 'Proveedor externo',
            'endpoint_url' => trim((string) ($data['endpoint_url'] ?? '')) ?: null,
            'api_key_mask' => $apiKey !== '' ? self::maskToken($apiKey) : (($current['api_key_mask'] ?? '') ?: null),
            'status' => in_array(($data['status'] ?? ''), ['ACTIVE', 'INACTIVE'], true) ? $data['status'] : 'INACTIVE',
            'export_format' => in_array(($data['export_format'] ?? ''), ['CSV', 'JSON'], true) ? $data['export_format'] : 'CSV',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];

        if (!empty($current['id'])) {
            $stmt = Database::connection()->prepare(
                'UPDATE billing_integrations
                 SET provider_name = :provider_name, endpoint_url = :endpoint_url, api_key_mask = :api_key_mask,
                     status = :status, export_format = :export_format, notes = :notes, updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute($params + ['id' => $current['id']]);
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO billing_integrations (id, tenant_id, provider_name, endpoint_url, api_key_mask, status, export_format, notes, created_at, updated_at)
             VALUES (:id, :tenant_id, :provider_name, :endpoint_url, :api_key_mask, :status, :export_format, :notes, NOW(), NOW())'
        );
        $stmt->execute($params + ['id' => cuid()]);
    }

    public static function metrics(string $tenantId): array
    {
        self::ensureTables();
        return [
            'pending' => self::countPayments($tenantId, 'status = "PAID" AND external_sync_status = "PENDING"'),
            'synced' => self::countPayments($tenantId, 'external_sync_status = "SYNCED"'),
            'exported' => self::countPayments($tenantId, 'external_sync_status = "EXPORTED"'),
            'errors' => self::countLogs($tenantId, 'status = "ERROR"'),
        ];
    }

    public static function eligiblePayments(string $tenantId, int $limit = 200): array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare(
            'SELECT payments.*,
                    members.first_name,
                    members.last_name,
                    members.email,
                    membership_plans.name AS plan_name
             FROM payments
             INNER JOIN members ON members.id = payments.member_id AND members.tenant_id = payments.tenant_id
             LEFT JOIN subscriptions ON subscriptions.id = payments.subscription_id AND subscriptions.tenant_id = payments.tenant_id
             LEFT JOIN membership_plans ON membership_plans.id = subscriptions.membership_plan_id AND membership_plans.tenant_id = payments.tenant_id
             WHERE payments.tenant_id = :tenant_id
             AND payments.status = "PAID"
             AND payments.external_sync_status IN ("PENDING", "EXPORTED", "ERROR")
             ORDER BY payments.paid_at DESC, payments.created_at DESC
             LIMIT ' . max(1, min($limit, 500))
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function logs(string $tenantId, int $limit = 100): array
    {
        self::ensureTables();
        $stmt = Database::connection()->prepare(
            'SELECT billing_sync_logs.*, billing_integrations.provider_name
             FROM billing_sync_logs
             LEFT JOIN billing_integrations ON billing_integrations.id = billing_sync_logs.integration_id
             WHERE billing_sync_logs.tenant_id = :tenant_id
             ORDER BY billing_sync_logs.created_at DESC
             LIMIT ' . max(1, min($limit, 200))
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function exportCsv(string $tenantId): string
    {
        $settings = self::settings($tenantId);
        $payments = self::eligiblePayments($tenantId, 500);
        $total = self::totalAmount($payments);
        $payload = self::payload($payments);

        $ids = array_column($payments, 'id');
        if ($ids) {
            self::markPayments($tenantId, $ids, 'EXPORTED', null);
        }
        self::createLog($tenantId, $settings['id'] ?: null, 'EXPORT', 'SUCCESS', count($payments), $total, 'Exportacion CSV generada.', $payload);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['id', 'socio', 'email', 'membresia', 'importe', 'moneda', 'metodo', 'pagado', 'referencia']);
        foreach ($payments as $payment) {
            fputcsv($handle, [
                $payment['id'],
                trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
                $payment['email'] ?? '',
                $payment['plan_name'] ?? '',
                $payment['amount'],
                $payment['currency'],
                payment_method_label($payment['payment_method']),
                $payment['paid_at'],
                $payment['external_reference'] ?: $payment['id'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    public static function sync(string $tenantId): array
    {
        $settings = self::settings($tenantId);
        if (($settings['status'] ?? 'INACTIVE') !== 'ACTIVE') {
            self::createLog($tenantId, $settings['id'] ?: null, 'SYNC', 'ERROR', 0, 0, 'La integracion no esta activa.', []);
            throw new RuntimeException('Activa la integracion antes de sincronizar pagos.');
        }

        $payments = self::eligiblePayments($tenantId, 500);
        $ids = array_column($payments, 'id');
        $total = self::totalAmount($payments);
        $payload = self::payload($payments);

        if ($ids) {
            self::markPayments($tenantId, $ids, 'SYNCED', 'EXT-' . date('YmdHis'));
        }

        if (!empty($settings['id'])) {
            $stmt = Database::connection()->prepare('UPDATE billing_integrations SET last_sync_at = NOW(), updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $settings['id'], 'tenant_id' => $tenantId]);
        }

        self::createLog($tenantId, $settings['id'] ?: null, 'SYNC', 'SUCCESS', count($payments), $total, 'Sincronizacion simulada completada.', $payload);

        return ['count' => count($payments), 'total' => $total];
    }

    private static function markPayments(string $tenantId, array $ids, string $status, ?string $referencePrefix): void
    {
        foreach ($ids as $id) {
            $stmt = Database::connection()->prepare(
                'UPDATE payments
                 SET external_sync_status = :status,
                     external_reference = COALESCE(external_reference, :reference),
                     external_synced_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute([
                'status' => $status,
                'reference' => $referencePrefix ? $referencePrefix . '-' . substr((string) $id, -8) : (string) $id,
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);
        }
    }

    private static function createLog(string $tenantId, ?string $integrationId, string $operation, string $status, int $count, float $total, string $message, array $payload): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO billing_sync_logs (id, tenant_id, integration_id, operation, status, payments_count, total_amount, message, payload, created_at)
             VALUES (:id, :tenant_id, :integration_id, :operation, :status, :payments_count, :total_amount, :message, :payload, NOW())'
        );
        $stmt->execute([
            'id' => cuid(),
            'tenant_id' => $tenantId,
            'integration_id' => $integrationId,
            'operation' => $operation,
            'status' => $status,
            'payments_count' => $count,
            'total_amount' => number_format($total, 2, '.', ''),
            'message' => $message,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private static function payload(array $payments): array
    {
        return array_map(static function (array $payment): array {
            return [
                'id' => $payment['id'],
                'member' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
                'amount' => (float) $payment['amount'],
                'currency' => $payment['currency'],
                'paid_at' => $payment['paid_at'],
            ];
        }, $payments);
    }

    private static function totalAmount(array $payments): float
    {
        return array_reduce($payments, static fn (float $total, array $payment): float => $total + (float) $payment['amount'], 0.0);
    }

    private static function countPayments(string $tenantId, string $where): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM payments WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private static function countLogs(string $tenantId, string $where): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM billing_sync_logs WHERE tenant_id = :tenant_id AND {$where}");
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private static function maskToken(string $token): string
    {
        $length = strlen($token);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($token, 0, 3) . str_repeat('*', max(4, $length - 6)) . substr($token, -3);
    }

    private static function ensureColumn(string $table, string $column, string $definition): void
    {
        try {
            $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table . ' LIKE "' . $column . '"');
            if ($stmt && $stmt->fetchColumn()) {
                return;
            }

            Database::connection()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (Throwable) {
        }
    }
}
