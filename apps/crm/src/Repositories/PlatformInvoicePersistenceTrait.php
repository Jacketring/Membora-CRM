<?php

declare(strict_types=1);

trait PlatformInvoicePersistenceTrait
{
    private static function normalizedPayments(array $data): array
    {
        $rows = $data['invoice_payments'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $payments = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $amountCents = self::moneyToCents((string) ($row['amount'] ?? '0'));
            if ($amountCents <= 0) {
                continue;
            }
            $payments[] = [
                'paid_at' => trim((string) ($row['paid_at'] ?? '')) ?: date('Y-m-d'),
                'amount' => self::centsToDecimal($amountCents),
                'amount_cents' => $amountCents,
                'payment_method' => self::paymentMethod((string) ($row['payment_method'] ?? 'TRANSFER')),
                'reference' => trim((string) ($row['reference'] ?? '')) ?: null,
                'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
            ];
        }

        return $payments;
    }

    private static function replaceItems(string $invoiceId, array $items): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM platform_invoice_items WHERE invoice_id = :invoice_id')->execute(['invoice_id' => $invoiceId]);
        $stmt = $pdo->prepare(
            'INSERT INTO platform_invoice_items (id, invoice_id, description, quantity, unit, unit_price, discount_type, discount_value, discount_amount, taxable_base, tax_rate, tax_amount, total_amount, sort_order, created_at, updated_at)
             VALUES (:id, :invoice_id, :description, :quantity, :unit, :unit_price, :discount_type, :discount_value, :discount_amount, :taxable_base, :tax_rate, :tax_amount, :total_amount, :sort_order, NOW(), NOW())'
        );
        foreach ($items as $item) {
            $stmt->execute(array_intersect_key($item, array_flip([
                'description', 'quantity', 'unit', 'unit_price', 'discount_type', 'discount_value', 'discount_amount', 'taxable_base', 'tax_rate', 'tax_amount', 'total_amount', 'sort_order',
            ])) + ['id' => cuid(), 'invoice_id' => $invoiceId]);
        }
    }

    private static function replacePayments(string $invoiceId, array $payments): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM platform_invoice_payments WHERE invoice_id = :invoice_id')->execute(['invoice_id' => $invoiceId]);
        $stmt = $pdo->prepare(
            'INSERT INTO platform_invoice_payments (id, invoice_id, paid_at, amount, payment_method, reference, notes, created_at, updated_at)
             VALUES (:id, :invoice_id, :paid_at, :amount, :payment_method, :reference, :notes, NOW(), NOW())'
        );
        foreach ($payments as $payment) {
            $stmt->execute([
                'id' => cuid(),
                'invoice_id' => $invoiceId,
                'paid_at' => $payment['paid_at'],
                'amount' => $payment['amount'],
                'payment_method' => $payment['payment_method'],
                'reference' => $payment['reference'],
                'notes' => $payment['notes'],
            ]);
        }
    }

    public static function items(string $invoiceId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM platform_invoice_items WHERE invoice_id = :invoice_id ORDER BY sort_order ASC, created_at ASC');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetchAll();
    }

    public static function payments(string $invoiceId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM platform_invoice_payments WHERE invoice_id = :invoice_id ORDER BY paid_at ASC, created_at ASC');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetchAll();
    }

    private static function refreshPaymentTotals(string $invoiceId): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT total_amount, due_at FROM platform_invoices WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $invoiceId]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            return;
        }
        $paid = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM platform_invoice_payments WHERE invoice_id = :invoice_id');
        $paid->execute(['invoice_id' => $invoiceId]);
        $paidCents = self::moneyToCents((string) $paid->fetchColumn());
        $totalCents = self::moneyToCents((string) $invoice['total_amount']);
        $pendingCents = max(0, $totalCents - $paidCents);
        $collectionStatus = self::collectionStatus($totalCents, $paidCents, (string) ($invoice['due_at'] ?? ''));
        $status = $collectionStatus === 'PAID' ? 'PAID' : ($collectionStatus === 'OVERDUE' ? 'OVERDUE' : 'SENT');
        $update = $pdo->prepare('UPDATE platform_invoices SET paid_amount = :paid, pending_amount = :pending, collection_status = :collection_status, status = :status, updated_at = NOW() WHERE id = :id');
        $update->execute([
            'paid' => self::centsToDecimal($paidCents),
            'pending' => self::centsToDecimal($pendingCents),
            'collection_status' => $collectionStatus,
            'status' => $status,
            'id' => $invoiceId,
        ]);
    }

    private static function validateIssue(array $invoice, array $items): void
    {
        $required = [
            'issuer_name' => 'Falta la razon social del emisor.',
            'issuer_tax_id' => 'Falta el NIF/CIF del emisor.',
            'issuer_address' => 'Falta la direccion fiscal del emisor.',
            'customer_name' => 'Falta la razon social del cliente.',
            'customer_tax_id' => 'Falta el NIF/CIF del cliente.',
            'customer_address' => 'Falta la direccion fiscal del cliente.',
        ];
        foreach ($required as $field => $message) {
            if (trim((string) ($invoice[$field] ?? '')) === '') {
                throw new RuntimeException($message);
            }
        }
        if ($items === []) {
            throw new RuntimeException('Anade al menos una linea valida antes de emitir.');
        }
        foreach ($items as $item) {
            if ((float) $item['quantity'] <= 0 || self::moneyToCents((string) $item['unit_price']) < 0) {
                throw new RuntimeException('Revisa cantidades y precios de las lineas.');
            }
        }
    }

    private static function nextInvoiceNumberForUpdate(string $series, string $empresaId): int
    {
        $stmt = Database::connection()->prepare('SELECT COALESCE(MAX(invoice_number), 0) + 1 FROM platform_invoices WHERE empresa_id = :empresa_id AND invoice_series = :series FOR UPDATE');
        $stmt->execute(['empresa_id' => $empresaId, 'series' => $series]);

        return max(1, (int) $stmt->fetchColumn());
    }

    private static function moneyToCents(string $value): int
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '' || !preg_match('/^-?\d+(\.\d{1,4})?$/', $value)) {
            return 0;
        }
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$euros, $cents] = array_pad(explode('.', $value, 2), 2, '0');
        $amount = ((int) $euros * 100) + (int) substr(str_pad($cents, 2, '0'), 0, 2);

        return $negative ? -$amount : $amount;
    }

    private static function centsToDecimal(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '') . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function dominantTaxRate(array $items): string
    {
        return $items[0]['tax_rate'] ?? '21.00';
    }

    private static function paymentMethod(string $method): string
    {
        return in_array($method, ['TRANSFER', 'CARD', 'STRIPE', 'CASH', 'OTHER'], true) ? $method : 'TRANSFER';
    }

    private static function collectionStatus(int $totalCents, int $paidCents, string $dueAt): string
    {
        if ($paidCents >= $totalCents && $totalCents > 0) {
            return 'PAID';
        }
        if ($paidCents > 0) {
            return 'PARTIAL';
        }
        if ($dueAt !== '' && strtotime($dueAt) !== false && strtotime($dueAt) < strtotime(date('Y-m-d'))) {
            return 'OVERDUE';
        }

        return 'PENDING';
    }

    public static function invoiceCode(string $series, int $number): string
    {
        return self::normalizeSeries($series) . '/' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    public static function defaultSeries(): string
    {
        return 'M-' . date('Y');
    }

    private static function ensureColumn(string $table, string $column, string $sql): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = :table
             AND COLUMN_NAME = :column'
        );
        $stmt->execute(['table' => $table, 'column' => $column]);

        if ((int) $stmt->fetchColumn() === 0) {
            Database::connection()->exec($sql);
        }
    }
}
