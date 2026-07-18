<?php

declare(strict_types=1);

final class PlatformInvoiceRepository
{
    use PlatformInvoicePersistenceTrait;

    public static function ensureTable(): void
    {
        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS platform_invoices (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                empresa_id VARCHAR(191) NOT NULL,
                payment_id VARCHAR(191) NULL,
                invoice_series VARCHAR(32) NOT NULL DEFAULT "M-2026",
                invoice_number INT NULL,
                invoice_code VARCHAR(64) NULL,
                invoice_type VARCHAR(32) NOT NULL DEFAULT "ORDINARY",
                invoice_status VARCHAR(32) NOT NULL DEFAULT "DRAFT",
                collection_status VARCHAR(32) NOT NULL DEFAULT "PENDING",
                issued_at DATE NOT NULL,
                operation_at DATE NULL,
                period_start_at DATE NULL,
                period_end_at DATE NULL,
                due_at DATE NULL,
                issuer_name VARCHAR(191) NULL,
                issuer_tax_id VARCHAR(64) NULL,
                issuer_address VARCHAR(255) NULL,
                issuer_postal_code VARCHAR(32) NULL,
                issuer_city VARCHAR(120) NULL,
                issuer_province VARCHAR(120) NULL,
                issuer_country VARCHAR(120) NULL,
                issuer_email VARCHAR(191) NULL,
                issuer_phone VARCHAR(64) NULL,
                customer_name VARCHAR(191) NULL,
                customer_tax_id VARCHAR(64) NULL,
                customer_address VARCHAR(255) NULL,
                customer_postal_code VARCHAR(32) NULL,
                customer_city VARCHAR(120) NULL,
                customer_province VARCHAR(120) NULL,
                customer_country VARCHAR(120) NULL,
                customer_email VARCHAR(191) NULL,
                customer_phone VARCHAR(64) NULL,
                concept VARCHAR(191) NOT NULL,
                subtotal_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                taxable_base DECIMAL(10,2) NOT NULL DEFAULT 0,
                tax_breakdown TEXT NULL,
                tax_rate DECIMAL(5,2) NOT NULL DEFAULT 21.00,
                tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                pending_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                currency VARCHAR(3) NOT NULL DEFAULT "EUR",
                payment_method VARCHAR(64) NOT NULL DEFAULT "TRANSFER",
                fiscal_treatment VARCHAR(64) NOT NULL DEFAULT "VAT_SUBJECT",
                fiscal_note TEXT NULL,
                public_notes TEXT NULL,
                status VARCHAR(32) NOT NULL DEFAULT "ISSUED",
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY platform_invoices_empresa_series_number_unique (empresa_id, invoice_series, invoice_number),
                INDEX platform_invoices_empresa_idx (empresa_id),
                INDEX platform_invoices_payment_idx (payment_id),
                INDEX platform_invoices_status_idx (status),
                INDEX platform_invoices_invoice_status_idx (invoice_status),
                INDEX platform_invoices_collection_status_idx (collection_status),
                INDEX platform_invoices_issued_idx (issued_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS platform_invoice_items (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                invoice_id VARCHAR(191) NOT NULL,
                description VARCHAR(255) NOT NULL,
                quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
                unit VARCHAR(32) NOT NULL DEFAULT "ud",
                unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_type VARCHAR(16) NOT NULL DEFAULT "PERCENT",
                discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                taxable_base DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax_rate DECIMAL(5,2) NOT NULL DEFAULT 21.00,
                tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX platform_invoice_items_invoice_idx (invoice_id),
                INDEX platform_invoice_items_order_idx (invoice_id, sort_order)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS platform_invoice_payments (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                invoice_id VARCHAR(191) NOT NULL,
                paid_at DATE NOT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                payment_method VARCHAR(64) NOT NULL DEFAULT "TRANSFER",
                reference VARCHAR(191) NULL,
                notes TEXT NULL,
                attachment_path VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX platform_invoice_payments_invoice_idx (invoice_id),
                INDEX platform_invoice_payments_paid_idx (paid_at)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        foreach ([
            'invoice_scope' => 'ALTER TABLE platform_invoices ADD COLUMN invoice_scope VARCHAR(16) NOT NULL DEFAULT "PLATFORM" AFTER empresa_id',
            'payment_id' => 'ALTER TABLE platform_invoices ADD COLUMN payment_id VARCHAR(191) NULL AFTER empresa_id',
            'invoice_type' => 'ALTER TABLE platform_invoices ADD COLUMN invoice_type VARCHAR(32) NOT NULL DEFAULT "ORDINARY" AFTER invoice_code',
            'invoice_status' => 'ALTER TABLE platform_invoices ADD COLUMN invoice_status VARCHAR(32) NOT NULL DEFAULT "DRAFT" AFTER invoice_type',
            'collection_status' => 'ALTER TABLE platform_invoices ADD COLUMN collection_status VARCHAR(32) NOT NULL DEFAULT "PENDING" AFTER invoice_status',
            'operation_at' => 'ALTER TABLE platform_invoices ADD COLUMN operation_at DATE NULL AFTER issued_at',
            'period_start_at' => 'ALTER TABLE platform_invoices ADD COLUMN period_start_at DATE NULL AFTER operation_at',
            'period_end_at' => 'ALTER TABLE platform_invoices ADD COLUMN period_end_at DATE NULL AFTER period_start_at',
            'issuer_name' => 'ALTER TABLE platform_invoices ADD COLUMN issuer_name VARCHAR(191) NULL AFTER due_at',
            'issuer_tax_id' => 'ALTER TABLE platform_invoices ADD COLUMN issuer_tax_id VARCHAR(64) NULL AFTER issuer_name',
            'issuer_address' => 'ALTER TABLE platform_invoices ADD COLUMN issuer_address VARCHAR(255) NULL AFTER issuer_tax_id',
            'issuer_postal_code' => 'ALTER TABLE platform_invoices ADD COLUMN issuer_postal_code VARCHAR(32) NULL AFTER issuer_address',
            'issuer_city' => 'ALTER TABLE platform_invoices ADD COLUMN issuer_city VARCHAR(120) NULL AFTER issuer_postal_code',
            'issuer_province' => 'ALTER TABLE platform_invoices ADD COLUMN issuer_province VARCHAR(120) NULL AFTER issuer_city',
            'issuer_country' => 'ALTER TABLE platform_invoices ADD COLUMN issuer_country VARCHAR(120) NULL AFTER issuer_province',
            'issuer_email' => 'ALTER TABLE platform_invoices ADD COLUMN issuer_email VARCHAR(191) NULL AFTER issuer_country',
            'issuer_phone' => 'ALTER TABLE platform_invoices ADD COLUMN issuer_phone VARCHAR(64) NULL AFTER issuer_email',
            'customer_name' => 'ALTER TABLE platform_invoices ADD COLUMN customer_name VARCHAR(191) NULL AFTER issuer_phone',
            'customer_tax_id' => 'ALTER TABLE platform_invoices ADD COLUMN customer_tax_id VARCHAR(64) NULL AFTER customer_name',
            'customer_address' => 'ALTER TABLE platform_invoices ADD COLUMN customer_address VARCHAR(255) NULL AFTER customer_tax_id',
            'customer_postal_code' => 'ALTER TABLE platform_invoices ADD COLUMN customer_postal_code VARCHAR(32) NULL AFTER customer_address',
            'customer_city' => 'ALTER TABLE platform_invoices ADD COLUMN customer_city VARCHAR(120) NULL AFTER customer_postal_code',
            'customer_province' => 'ALTER TABLE platform_invoices ADD COLUMN customer_province VARCHAR(120) NULL AFTER customer_city',
            'customer_country' => 'ALTER TABLE platform_invoices ADD COLUMN customer_country VARCHAR(120) NULL AFTER customer_province',
            'customer_email' => 'ALTER TABLE platform_invoices ADD COLUMN customer_email VARCHAR(191) NULL AFTER customer_country',
            'customer_phone' => 'ALTER TABLE platform_invoices ADD COLUMN customer_phone VARCHAR(64) NULL AFTER customer_email',
            'subtotal_amount' => 'ALTER TABLE platform_invoices ADD COLUMN subtotal_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER concept',
            'discount_amount' => 'ALTER TABLE platform_invoices ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal_amount',
            'tax_breakdown' => 'ALTER TABLE platform_invoices ADD COLUMN tax_breakdown TEXT NULL AFTER taxable_base',
            'paid_amount' => 'ALTER TABLE platform_invoices ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_amount',
            'pending_amount' => 'ALTER TABLE platform_invoices ADD COLUMN pending_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER paid_amount',
            'currency' => 'ALTER TABLE platform_invoices ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT "EUR" AFTER pending_amount',
            'fiscal_treatment' => 'ALTER TABLE platform_invoices ADD COLUMN fiscal_treatment VARCHAR(64) NOT NULL DEFAULT "VAT_SUBJECT" AFTER payment_method',
            'fiscal_note' => 'ALTER TABLE platform_invoices ADD COLUMN fiscal_note TEXT NULL AFTER fiscal_treatment',
            'public_notes' => 'ALTER TABLE platform_invoices ADD COLUMN public_notes TEXT NULL AFTER fiscal_note',
        ] as $column => $sql) {
            self::ensureColumn('platform_invoices', $column, $sql);
        }

        try {
            $pdo->exec('ALTER TABLE platform_invoices MODIFY invoice_number INT NULL');
            $pdo->exec('ALTER TABLE platform_invoices MODIFY invoice_code VARCHAR(64) NULL');
            $pdo->exec('ALTER TABLE platform_invoices DROP INDEX platform_invoices_series_number_unique');
        } catch (Throwable) {
            // Compatible con despliegues donde el indice no exista o el usuario SQL no permita cambios estructurales.
        }
        try {
            $pdo->exec('ALTER TABLE platform_invoices ADD UNIQUE KEY platform_invoices_empresa_series_number_unique (empresa_id, invoice_series, invoice_number)');
        } catch (Throwable) {
            // El indice ya puede existir.
        }
    }

    public static function metrics(string $scope = 'PLATFORM', ?string $empresaId = null): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        $scopeWhere = 'invoice_scope = ' . $pdo->quote($scope);
        if ($empresaId !== null) {
            $scopeWhere .= ' AND empresa_id = ' . $pdo->quote($empresaId);
        }
        $issuedMonth = $pdo->query(
            'SELECT COALESCE(SUM(total_amount), 0)
             FROM platform_invoices
             WHERE ' . $scopeWhere . ' AND issued_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")
             AND invoice_status <> "DRAFT"'
        )->fetchColumn();

        $pending = $pdo->query(
            'SELECT COALESCE(SUM(total_amount), 0)
             FROM platform_invoices
             WHERE ' . $scopeWhere . ' AND collection_status IN ("PENDING", "PARTIAL", "OVERDUE")'
        )->fetchColumn();

        $paidMonth = $pdo->query(
            'SELECT COALESCE(SUM(total_amount), 0)
             FROM platform_invoices
             WHERE ' . $scopeWhere . ' AND collection_status = "PAID"
             AND issued_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'
        )->fetchColumn();

        $overdue = $pdo->query(
            'SELECT COUNT(*)
             FROM platform_invoices
             WHERE ' . $scopeWhere . ' AND (collection_status = "OVERDUE"
             OR (collection_status IN ("PENDING", "PARTIAL") AND due_at IS NOT NULL AND due_at < CURDATE()))'
        )->fetchColumn();

        return [
            'issued_month' => (float) $issuedMonth,
            'pending_amount' => (float) $pending,
            'paid_month' => (float) $paidMonth,
            'overdue' => (int) $overdue,
        ];
    }

    public static function all(string $query = '', string $status = '', string $scope = 'PLATFORM', ?string $empresaId = null): array
    {
        self::ensureTable();
        EmpresaRepository::ensureTables();

        $params = ['scope' => $scope];
        $where = ['i.invoice_scope = :scope'];
        if ($empresaId !== null) {
            $where[] = 'i.empresa_id = :empresa_id';
            $params['empresa_id'] = $empresaId;
        }
        if ($query !== '') {
            $where[] = '(i.invoice_code LIKE :query OR i.concept LIKE :query OR i.notes LIKE :query OR i.customer_name LIKE :query OR i.customer_tax_id LIKE :query OR e.name LIKE :query OR e.contact_email LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($status !== '') {
            $where[] = '(i.invoice_status = :status OR i.collection_status = :status OR i.status = :status)';
            $params['status'] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT i.*, e.name AS empresa_name, e.contact_email, e.plan
             FROM platform_invoices i
             INNER JOIN empresas e ON e.id = i.empresa_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY i.issued_at DESC, i.invoice_series ASC, i.invoice_number DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findWithEmpresa(string $id, ?string $scope = null, ?string $empresaId = null): ?array
    {
        self::ensureTable();
        EmpresaRepository::ensureTables();

        $stmt = Database::connection()->prepare(
            'SELECT i.*, e.name AS empresa_name, e.contact_email, e.plan
             FROM platform_invoices i
             INNER JOIN empresas e ON e.id = i.empresa_id
             WHERE i.id = :id' . ($scope !== null ? ' AND i.invoice_scope = :scope' : '') . ($empresaId !== null ? ' AND i.empresa_id = :empresa_id' : '') . '
             LIMIT 1'
        );
        $params = ['id' => $id];
        if ($scope !== null) $params['scope'] = $scope;
        if ($empresaId !== null) $params['empresa_id'] = $empresaId;
        $stmt->execute($params);
        $invoice = $stmt->fetch();

        return $invoice ?: null;
    }

    public static function findFull(string $id, ?string $scope = null, ?string $empresaId = null): ?array
    {
        $invoice = self::findWithEmpresa($id, $scope, $empresaId);
        if (!$invoice) {
            return null;
        }

        $invoice['items'] = self::items($id);
        $invoice['payments'] = self::payments($id);

        return $invoice;
    }

    public static function nextInvoiceNumber(string $series = ''): int
    {
        self::ensureTable();
        $series = self::normalizeSeries($series ?: self::defaultSeries());
        $stmt = Database::connection()->prepare('SELECT COALESCE(MAX(invoice_number), 0) + 1 FROM platform_invoices WHERE invoice_series = :series');
        $stmt->execute(['series' => $series]);

        return max(1, (int) $stmt->fetchColumn());
    }

    public static function create(array $data): void
    {
        self::ensureTable();
        $pdo = Database::connection();
        $params = self::invoiceParams($data);
        $id = cuid();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
            'INSERT INTO platform_invoices (id, empresa_id, payment_id, invoice_series, invoice_number, invoice_code, issued_at, due_at, concept, taxable_base, tax_rate, tax_amount, total_amount, payment_method, status, notes, created_at, updated_at)
             VALUES (:id, :empresa_id, :payment_id, :invoice_series, :invoice_number, :invoice_code, :issued_at, :due_at, :concept, :taxable_base, :tax_rate, :tax_amount, :total_amount, :payment_method, :status, :notes, NOW(), NOW())'
            );
            $stmt->execute(self::legacyInvoiceParams($params) + ['id' => $id]);
            self::updateExtendedInvoice($id, $params);
            $pdo->prepare('UPDATE platform_invoices SET invoice_scope = :scope WHERE id = :id')->execute([
                'scope' => ($data['invoice_scope'] ?? '') === 'CLIENT' ? 'CLIENT' : 'PLATFORM',
                'id' => $id,
            ]);
            self::replaceItems($id, $params['items']);
            self::replacePayments($id, $params['payments']);
            self::refreshPaymentTotals($id);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function update(string $id, array $data): void
    {
        self::ensureTable();
        $invoice = self::findWithEmpresa($id);
        if (!$invoice) {
            throw new RuntimeException('No se encontro la factura.');
        }
        if (($invoice['invoice_status'] ?? 'DRAFT') !== 'DRAFT') {
            throw new RuntimeException('Una factura emitida no puede editarse directamente.');
        }

        $pdo = Database::connection();
        $params = self::invoiceParams($data);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
            'UPDATE platform_invoices
             SET empresa_id = :empresa_id,
                 payment_id = :payment_id,
                 invoice_series = :invoice_series,
                 invoice_number = :invoice_number,
                 invoice_code = :invoice_code,
                 issued_at = :issued_at,
                 due_at = :due_at,
                 concept = :concept,
                 taxable_base = :taxable_base,
                 tax_rate = :tax_rate,
                 tax_amount = :tax_amount,
                 total_amount = :total_amount,
                 payment_method = :payment_method,
                 status = :status,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id'
            );
            $stmt->execute(self::legacyInvoiceParams($params) + ['id' => $id]);
            self::updateExtendedInvoice($id, $params);
            self::replaceItems($id, $params['items']);
            self::replacePayments($id, $params['payments']);
            self::refreshPaymentTotals($id);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function issue(string $id): void
    {
        self::ensureTable();
        $pdo = Database::connection();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM platform_invoices WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $invoice = $stmt->fetch();
            if (!$invoice) {
                throw new RuntimeException('No se encontro la factura.');
            }
            if (($invoice['invoice_status'] ?? 'DRAFT') !== 'DRAFT') {
                throw new RuntimeException('La factura ya esta emitida.');
            }

            $items = self::items($id);
            self::validateIssue($invoice, $items);
            $series = self::normalizeSeries((string) ($invoice['invoice_series'] ?: self::defaultSeries()));
            $number = self::nextInvoiceNumberForUpdate($series, (string) $invoice['empresa_id']);
            $invoiceCode = self::invoiceCode($series, $number);

            $update = $pdo->prepare(
                'UPDATE platform_invoices
                 SET invoice_series = :series,
                     invoice_number = :number,
                     invoice_code = :code,
                     invoice_status = "ISSUED",
                     status = "SENT",
                     issued_at = COALESCE(NULLIF(issued_at, "0000-00-00"), CURDATE()),
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'series' => $series,
                'number' => $number,
                'code' => $invoiceCode,
                'id' => $id,
            ]);
            self::refreshPaymentTotals($id);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function addPayment(string $invoiceId, array $data): void
    {
        self::ensureTable();
        $invoice = self::findWithEmpresa($invoiceId);
        if (!$invoice) {
            throw new RuntimeException('No se encontro la factura.');
        }

        $amountCents = self::moneyToCents((string) ($data['amount'] ?? '0'));
        if ($amountCents <= 0) {
            throw new RuntimeException('El importe del pago debe ser mayor que cero.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO platform_invoice_payments (id, invoice_id, paid_at, amount, payment_method, reference, notes, attachment_path, created_at, updated_at)
                 VALUES (:id, :invoice_id, :paid_at, :amount, :payment_method, :reference, :notes, :attachment_path, NOW(), NOW())'
            );
            $stmt->execute([
                'id' => cuid(),
                'invoice_id' => $invoiceId,
                'paid_at' => trim((string) ($data['paid_at'] ?? '')) ?: date('Y-m-d'),
                'amount' => self::centsToDecimal($amountCents),
                'payment_method' => self::paymentMethod((string) ($data['payment_method'] ?? 'TRANSFER')),
                'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'attachment_path' => null,
            ]);
            self::refreshPaymentTotals($invoiceId);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private static function invoiceParams(array $data): array
    {
        $series = self::normalizeSeries((string) ($data['invoice_series'] ?? self::defaultSeries()));
        $items = self::normalizedItems($data);
        $totals = self::invoiceTotals($items);
        $payments = self::normalizedPayments($data);
        $paidCents = array_sum(array_column($payments, 'amount_cents'));
        $pendingCents = max(0, $totals['total_cents'] - $paidCents);
        $number = null;
        $status = in_array($data['status'] ?? '', ['ISSUED', 'SENT', 'PAID', 'OVERDUE', 'CANCELLED'], true) ? $data['status'] : 'ISSUED';
        $collectionStatus = self::collectionStatus($totals['total_cents'], $paidCents, trim((string) ($data['due_at'] ?? '')));
        $invoiceStatus = in_array($data['invoice_status'] ?? '', ['DRAFT', 'ISSUED', 'RECTIFIED'], true) ? $data['invoice_status'] : 'DRAFT';
        $paymentMethod = self::paymentMethod((string) ($data['payment_method'] ?? 'TRANSFER'));
        $invoiceType = in_array($data['invoice_type'] ?? '', ['ORDINARY', 'SIMPLIFIED', 'RECTIFYING'], true) ? $data['invoice_type'] : 'ORDINARY';
        $fiscalTreatment = in_array($data['fiscal_treatment'] ?? '', ['VAT_SUBJECT', 'EXEMPT', 'NOT_SUBJECT', 'REVERSE_CHARGE', 'OTHER'], true) ? $data['fiscal_treatment'] : 'VAT_SUBJECT';
        $emitter = self::issuerSnapshot($data);
        $customer = self::customerSnapshot($data);

        return [
            'empresa_id' => trim((string) ($data['empresa_id'] ?? '')),
            'payment_id' => trim((string) ($data['payment_id'] ?? '')) ?: null,
            'invoice_series' => $series,
            'invoice_number' => $number,
            'invoice_code' => null,
            'invoice_type' => $invoiceType,
            'invoice_status' => $invoiceStatus,
            'collection_status' => $collectionStatus,
            'issued_at' => trim((string) ($data['issued_at'] ?? '')) ?: date('Y-m-d'),
            'operation_at' => trim((string) ($data['operation_at'] ?? '')) ?: null,
            'period_start_at' => trim((string) ($data['period_start_at'] ?? '')) ?: null,
            'period_end_at' => trim((string) ($data['period_end_at'] ?? '')) ?: null,
            'due_at' => trim((string) ($data['due_at'] ?? '')) ?: null,
            'issuer_name' => $emitter['name'],
            'issuer_tax_id' => $emitter['tax_id'],
            'issuer_address' => $emitter['address'],
            'issuer_postal_code' => $emitter['postal_code'],
            'issuer_city' => $emitter['city'],
            'issuer_province' => $emitter['province'],
            'issuer_country' => $emitter['country'],
            'issuer_email' => $emitter['email'],
            'issuer_phone' => $emitter['phone'],
            'customer_name' => $customer['name'],
            'customer_tax_id' => $customer['tax_id'],
            'customer_address' => $customer['address'],
            'customer_postal_code' => $customer['postal_code'],
            'customer_city' => $customer['city'],
            'customer_province' => $customer['province'],
            'customer_country' => $customer['country'],
            'customer_email' => $customer['email'],
            'customer_phone' => $customer['phone'],
            'concept' => trim((string) ($data['concept'] ?? ($items[0]['description'] ?? 'Factura Membora'))),
            'subtotal_amount' => self::centsToDecimal($totals['subtotal_cents']),
            'discount_amount' => self::centsToDecimal($totals['discount_cents']),
            'taxable_base' => self::centsToDecimal($totals['base_cents']),
            'tax_breakdown' => json_encode($totals['tax_breakdown'], JSON_UNESCAPED_UNICODE),
            'tax_rate' => self::dominantTaxRate($items),
            'tax_amount' => self::centsToDecimal($totals['tax_cents']),
            'total_amount' => self::centsToDecimal($totals['total_cents']),
            'paid_amount' => self::centsToDecimal($paidCents),
            'pending_amount' => self::centsToDecimal($pendingCents),
            'currency' => strtoupper(substr(trim((string) ($data['currency'] ?? 'EUR')), 0, 3)) ?: 'EUR',
            'payment_method' => $paymentMethod,
            'fiscal_treatment' => $fiscalTreatment,
            'fiscal_note' => trim((string) ($data['fiscal_note'] ?? '')) ?: null,
            'public_notes' => trim((string) ($data['public_notes'] ?? '')) ?: null,
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'items' => $items,
            'payments' => $payments,
        ];
    }

    private static function normalizeSeries(string $series): string
    {
        $series = strtoupper(trim($series));
        $series = preg_replace('/[^A-Z0-9-]/', '', $series) ?: 'M';

        return substr($series, 0, 32);
    }

    private static function legacyInvoiceParams(array $params): array
    {
        return array_intersect_key($params, array_flip([
            'empresa_id',
            'payment_id',
            'invoice_series',
            'invoice_number',
            'invoice_code',
            'issued_at',
            'due_at',
            'concept',
            'taxable_base',
            'tax_rate',
            'tax_amount',
            'total_amount',
            'payment_method',
            'status',
            'notes',
        ]));
    }

    private static function updateExtendedInvoice(string $id, array $params): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE platform_invoices
             SET invoice_type = :invoice_type,
                 invoice_status = :invoice_status,
                 collection_status = :collection_status,
                 operation_at = :operation_at,
                 period_start_at = :period_start_at,
                 period_end_at = :period_end_at,
                 issuer_name = :issuer_name,
                 issuer_tax_id = :issuer_tax_id,
                 issuer_address = :issuer_address,
                 issuer_postal_code = :issuer_postal_code,
                 issuer_city = :issuer_city,
                 issuer_province = :issuer_province,
                 issuer_country = :issuer_country,
                 issuer_email = :issuer_email,
                 issuer_phone = :issuer_phone,
                 customer_name = :customer_name,
                 customer_tax_id = :customer_tax_id,
                 customer_address = :customer_address,
                 customer_postal_code = :customer_postal_code,
                 customer_city = :customer_city,
                 customer_province = :customer_province,
                 customer_country = :customer_country,
                 customer_email = :customer_email,
                 customer_phone = :customer_phone,
                 subtotal_amount = :subtotal_amount,
                 discount_amount = :discount_amount,
                 tax_breakdown = :tax_breakdown,
                 paid_amount = :paid_amount,
                 pending_amount = :pending_amount,
                 currency = :currency,
                 fiscal_treatment = :fiscal_treatment,
                 fiscal_note = :fiscal_note,
                 public_notes = :public_notes
             WHERE id = :id'
        );
        $stmt->execute(array_intersect_key($params, array_flip([
            'invoice_type', 'invoice_status', 'collection_status', 'operation_at', 'period_start_at', 'period_end_at',
            'issuer_name', 'issuer_tax_id', 'issuer_address', 'issuer_postal_code', 'issuer_city', 'issuer_province', 'issuer_country', 'issuer_email', 'issuer_phone',
            'customer_name', 'customer_tax_id', 'customer_address', 'customer_postal_code', 'customer_city', 'customer_province', 'customer_country', 'customer_email', 'customer_phone',
            'subtotal_amount', 'discount_amount', 'tax_breakdown', 'paid_amount', 'pending_amount', 'currency', 'fiscal_treatment', 'fiscal_note', 'public_notes',
        ])) + ['id' => $id]);
    }

    private static function issuerSnapshot(array $data): array
    {
        return [
            'name' => trim((string) ($data['issuer_name'] ?? getenv('INVOICE_ISSUER_NAME') ?: getenv('APP_NAME') ?: 'Membora')),
            'tax_id' => trim((string) ($data['issuer_tax_id'] ?? getenv('INVOICE_ISSUER_TAX_ID') ?: '')),
            'address' => trim((string) ($data['issuer_address'] ?? getenv('INVOICE_ISSUER_ADDRESS') ?: '')),
            'postal_code' => trim((string) ($data['issuer_postal_code'] ?? getenv('INVOICE_ISSUER_POSTAL_CODE') ?: '')),
            'city' => trim((string) ($data['issuer_city'] ?? getenv('INVOICE_ISSUER_CITY') ?: '')),
            'province' => trim((string) ($data['issuer_province'] ?? getenv('INVOICE_ISSUER_PROVINCE') ?: '')),
            'country' => trim((string) ($data['issuer_country'] ?? getenv('INVOICE_ISSUER_COUNTRY') ?: 'Espana')),
            'email' => trim((string) ($data['issuer_email'] ?? getenv('INVOICE_ISSUER_EMAIL') ?: getenv('MAIL_FROM_EMAIL') ?: '')),
            'phone' => trim((string) ($data['issuer_phone'] ?? getenv('INVOICE_ISSUER_PHONE') ?: '')),
        ];
    }

    private static function customerSnapshot(array $data): array
    {
        $empresa = EmpresaRepository::find(trim((string) ($data['empresa_id'] ?? ''))) ?: [];

        return [
            'name' => trim((string) ($data['customer_name'] ?? '')) ?: (string) ($empresa['name'] ?? ''),
            'tax_id' => trim((string) ($data['customer_tax_id'] ?? '')),
            'address' => trim((string) ($data['customer_address'] ?? '')),
            'postal_code' => trim((string) ($data['customer_postal_code'] ?? '')),
            'city' => trim((string) ($data['customer_city'] ?? '')),
            'province' => trim((string) ($data['customer_province'] ?? '')),
            'country' => trim((string) ($data['customer_country'] ?? 'Espana')),
            'email' => trim((string) ($data['customer_email'] ?? '')) ?: (string) ($empresa['contact_email'] ?? ''),
            'phone' => trim((string) ($data['customer_phone'] ?? '')),
        ];
    }

    private static function normalizedItems(array $data): array
    {
        $rows = $data['items'] ?? [];
        if (!is_array($rows) || $rows === []) {
            $rows = [[
                'description' => $data['concept'] ?? 'Servicio Membora',
                'quantity' => '1',
                'unit' => 'ud',
                'unit_price' => $data['taxable_base'] ?? '0',
                'discount_type' => 'PERCENT',
                'discount_value' => '0',
                'tax_rate' => $data['tax_rate'] ?? '21',
            ]];
        }

        $items = [];
        $order = 1;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $quantity = max(0, (int) round(((float) str_replace(',', '.', (string) ($row['quantity'] ?? '1'))) * 1000));
            $unitPriceCents = self::moneyToCents((string) ($row['unit_price'] ?? '0'));
            $subtotalCents = (int) floor(($quantity * $unitPriceCents) / 1000);
            $discountType = strtoupper((string) ($row['discount_type'] ?? 'PERCENT')) === 'FIXED' ? 'FIXED' : 'PERCENT';
            $discountValue = str_replace(',', '.', (string) ($row['discount_value'] ?? '0'));
            $discountCents = $discountType === 'FIXED'
                ? self::moneyToCents($discountValue)
                : (int) round($subtotalCents * max(0, (float) $discountValue) / 100);
            $discountCents = min($subtotalCents, max(0, $discountCents));
            $baseCents = max(0, $subtotalCents - $discountCents);
            $taxRate = max(0, (float) str_replace(',', '.', (string) ($row['tax_rate'] ?? '21')));
            $taxCents = (int) round($baseCents * $taxRate / 100);
            $totalCents = $baseCents + $taxCents;
            $items[] = [
                'description' => $description,
                'quantity' => number_format($quantity / 1000, 3, '.', ''),
                'unit' => substr(trim((string) ($row['unit'] ?? 'ud')) ?: 'ud', 0, 32),
                'unit_price' => self::centsToDecimal($unitPriceCents),
                'discount_type' => $discountType,
                'discount_value' => number_format(max(0, (float) $discountValue), 2, '.', ''),
                'discount_amount' => self::centsToDecimal($discountCents),
                'taxable_base' => self::centsToDecimal($baseCents),
                'tax_rate' => number_format($taxRate, 2, '.', ''),
                'tax_amount' => self::centsToDecimal($taxCents),
                'total_amount' => self::centsToDecimal($totalCents),
                'sort_order' => $order++,
                'subtotal_cents' => $subtotalCents,
                'discount_cents' => $discountCents,
                'base_cents' => $baseCents,
                'tax_cents' => $taxCents,
                'total_cents' => $totalCents,
            ];
        }

        return $items;
    }

    private static function invoiceTotals(array $items): array
    {
        $breakdown = [];
        $totals = ['subtotal_cents' => 0, 'discount_cents' => 0, 'base_cents' => 0, 'tax_cents' => 0, 'total_cents' => 0];
        foreach ($items as $item) {
            $totals['subtotal_cents'] += (int) $item['subtotal_cents'];
            $totals['discount_cents'] += (int) $item['discount_cents'];
            $totals['base_cents'] += (int) $item['base_cents'];
            $totals['tax_cents'] += (int) $item['tax_cents'];
            $totals['total_cents'] += (int) $item['total_cents'];
            $rate = (string) $item['tax_rate'];
            $breakdown[$rate] ??= ['rate' => $rate, 'base' => '0.00', 'tax' => '0.00', 'base_cents' => 0, 'tax_cents' => 0];
            $breakdown[$rate]['base_cents'] += (int) $item['base_cents'];
            $breakdown[$rate]['tax_cents'] += (int) $item['tax_cents'];
            $breakdown[$rate]['base'] = self::centsToDecimal($breakdown[$rate]['base_cents']);
            $breakdown[$rate]['tax'] = self::centsToDecimal($breakdown[$rate]['tax_cents']);
        }
        $totals['tax_breakdown'] = array_values($breakdown);

        return $totals;
    }

}
