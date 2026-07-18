<?php

final class StripeBillingConfig
{
    public static function mode(): string
    {
        return strtolower(trim((string) (getenv('PAYMENTS_MODE') ?: 'manual')));
    }

    public static function enabled(): bool
    {
        return self::mode() === 'stripe_test';
    }

    public static function secretKey(): string
    {
        return trim((string) (getenv('STRIPE_SECRET_KEY') ?: ''));
    }

    public static function webhookSecret(): string
    {
        return trim((string) (getenv('STRIPE_WEBHOOK_SECRET') ?: ''));
    }

    public static function publishableKey(): string
    {
        return trim((string) (getenv('STRIPE_PUBLISHABLE_KEY') ?: ''));
    }

    public static function checkoutProvider(): string
    {
        $provider = strtolower(trim((string) (getenv('CHECKOUT_PROVIDER') ?: 'stripe')));

        return in_array($provider, ['simulated', 'stripe'], true) ? $provider : 'stripe';
    }

    public static function simulatedCheckoutEnabled(): bool
    {
        return self::checkoutProvider() === 'simulated' && self::mode() === 'stripe_test';
    }

    public static function webhookUrl(): string
    {
        return app_base_url() . '/stripe/webhook';
    }

    public static function assertReady(): void
    {
        if (!self::enabled()) {
            throw new RuntimeException('Stripe no esta activo. Configura PAYMENTS_MODE=stripe_test.');
        }

        if (!class_exists(\Stripe\Stripe::class)) {
            throw new RuntimeException('No esta instalado stripe/stripe-php. Ejecuta composer install en apps/crm.');
        }

        $secretKey = self::secretKey();
        if ($secretKey === '') {
            throw new RuntimeException('Falta STRIPE_SECRET_KEY en apps/crm/.env.');
        }

        if (!str_starts_with($secretKey, 'sk_test_')) {
            throw new RuntimeException('En desarrollo solo se permite una clave de test sk_test_.');
        }
    }
}

final class StripeBillingRepository
{
    public static function ensureSchema(): void
    {
        EmpresaRepository::ensureTables();
        PlatformPlanRepository::ensureTable();
        PlatformPaymentRepository::ensureTable();
        PlatformInvoiceRepository::ensureTable();

        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS stripe_events (
                id VARCHAR(191) NOT NULL PRIMARY KEY,
                stripe_event_id VARCHAR(191) NOT NULL,
                event_type VARCHAR(191) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT "RECEIVED",
                payload LONGTEXT NULL,
                error_message TEXT NULL,
                received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY stripe_events_event_unique (stripe_event_id),
                INDEX stripe_events_type_idx (event_type),
                INDEX stripe_events_status_idx (status)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        foreach ([
            ['empresas', 'stripe_customer_id', 'ALTER TABLE empresas ADD COLUMN stripe_customer_id VARCHAR(191) NULL AFTER contact_email'],
            ['empresas', 'stripe_subscription_id', 'ALTER TABLE empresas ADD COLUMN stripe_subscription_id VARCHAR(191) NULL AFTER stripe_customer_id'],
            ['empresas', 'stripe_subscription_status', 'ALTER TABLE empresas ADD COLUMN stripe_subscription_status VARCHAR(64) NULL AFTER stripe_subscription_id'],
            ['empresas', 'stripe_current_period_start', 'ALTER TABLE empresas ADD COLUMN stripe_current_period_start DATETIME NULL AFTER stripe_subscription_status'],
            ['empresas', 'stripe_current_period_end', 'ALTER TABLE empresas ADD COLUMN stripe_current_period_end DATETIME NULL AFTER stripe_current_period_start'],
            ['empresas', 'stripe_cancel_at_period_end', 'ALTER TABLE empresas ADD COLUMN stripe_cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0 AFTER stripe_current_period_end'],
            ['empresas', 'stripe_checkout_session_id', 'ALTER TABLE empresas ADD COLUMN stripe_checkout_session_id VARCHAR(191) NULL AFTER stripe_cancel_at_period_end'],
            ['empresas', 'stripe_pending_plan_code', 'ALTER TABLE empresas ADD COLUMN stripe_pending_plan_code VARCHAR(64) NULL AFTER stripe_checkout_session_id'],
            ['empresas', 'stripe_pending_renewal_period', 'ALTER TABLE empresas ADD COLUMN stripe_pending_renewal_period VARCHAR(16) NULL AFTER stripe_pending_plan_code'],
            ['empresas', 'stripe_last_error', 'ALTER TABLE empresas ADD COLUMN stripe_last_error TEXT NULL AFTER stripe_pending_renewal_period'],
            ['saas_plans', 'stripe_monthly_price_id', 'ALTER TABLE saas_plans ADD COLUMN stripe_monthly_price_id VARCHAR(191) NULL AFTER discount_label'],
            ['saas_plans', 'stripe_annual_price_id', 'ALTER TABLE saas_plans ADD COLUMN stripe_annual_price_id VARCHAR(191) NULL AFTER stripe_monthly_price_id'],
            ['empresa_payments', 'stripe_invoice_id', 'ALTER TABLE empresa_payments ADD COLUMN stripe_invoice_id VARCHAR(191) NULL AFTER empresa_id'],
            ['empresa_payments', 'stripe_payment_intent_id', 'ALTER TABLE empresa_payments ADD COLUMN stripe_payment_intent_id VARCHAR(191) NULL AFTER stripe_invoice_id'],
            ['empresa_payments', 'stripe_status', 'ALTER TABLE empresa_payments ADD COLUMN stripe_status VARCHAR(64) NULL AFTER stripe_payment_intent_id'],
            ['empresa_payments', 'hosted_invoice_url', 'ALTER TABLE empresa_payments ADD COLUMN hosted_invoice_url TEXT NULL AFTER stripe_status'],
            ['empresa_payments', 'invoice_pdf', 'ALTER TABLE empresa_payments ADD COLUMN invoice_pdf TEXT NULL AFTER hosted_invoice_url'],
            ['empresa_payments', 'billing_reason', 'ALTER TABLE empresa_payments ADD COLUMN billing_reason VARCHAR(191) NULL AFTER invoice_pdf'],
            ['platform_invoices', 'stripe_invoice_id', 'ALTER TABLE platform_invoices ADD COLUMN stripe_invoice_id VARCHAR(191) NULL AFTER payment_id'],
            ['platform_invoices', 'stripe_payment_intent_id', 'ALTER TABLE platform_invoices ADD COLUMN stripe_payment_intent_id VARCHAR(191) NULL AFTER stripe_invoice_id'],
            ['platform_invoices', 'hosted_invoice_url', 'ALTER TABLE platform_invoices ADD COLUMN hosted_invoice_url TEXT NULL AFTER stripe_payment_intent_id'],
            ['platform_invoices', 'invoice_pdf', 'ALTER TABLE platform_invoices ADD COLUMN invoice_pdf TEXT NULL AFTER hosted_invoice_url'],
            ['platform_invoice_payments', 'stripe_payment_intent_id', 'ALTER TABLE platform_invoice_payments ADD COLUMN stripe_payment_intent_id VARCHAR(191) NULL AFTER invoice_id'],
        ] as [$table, $column, $sql]) {
            self::ensureColumn($table, $column, $sql);
        }

        foreach ([
            'ALTER TABLE empresas ADD UNIQUE KEY empresas_stripe_customer_unique (stripe_customer_id)',
            'ALTER TABLE empresas ADD UNIQUE KEY empresas_stripe_subscription_unique (stripe_subscription_id)',
            'ALTER TABLE empresa_payments ADD UNIQUE KEY empresa_payments_stripe_invoice_unique (stripe_invoice_id)',
            'ALTER TABLE platform_invoices ADD UNIQUE KEY platform_invoices_stripe_invoice_unique (stripe_invoice_id)',
        ] as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable) {
            }
        }
    }

    public static function stripeDiagnostics(int $limit = 12): array
    {
        self::ensureSchema();
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM stripe_events
             ORDER BY received_at DESC
             LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function beginEvent(string $eventId, string $eventType, string $payload): bool
    {
        self::ensureSchema();
        try {
            Database::connection()->prepare(
                'INSERT INTO stripe_events (id, stripe_event_id, event_type, status, payload, received_at, created_at, updated_at)
                 VALUES (:id, :stripe_event_id, :event_type, "RECEIVED", :payload, NOW(), NOW(), NOW())'
            )->execute([
                'id' => cuid(),
                'stripe_event_id' => $eventId,
                'event_type' => $eventType,
                'payload' => $payload,
            ]);

            return true;
        } catch (Throwable) {
            $stmt = Database::connection()->prepare('SELECT status FROM stripe_events WHERE stripe_event_id = :stripe_event_id LIMIT 1');
            $stmt->execute(['stripe_event_id' => $eventId]);
            $status = (string) ($stmt->fetchColumn() ?: '');
            if ($status === 'ERROR') {
                Database::connection()->prepare(
                    'UPDATE stripe_events
                     SET status = "RECEIVED",
                         event_type = :event_type,
                         payload = :payload,
                         error_message = NULL,
                         updated_at = NOW()
                     WHERE stripe_event_id = :stripe_event_id'
                )->execute([
                    'stripe_event_id' => $eventId,
                    'event_type' => $eventType,
                    'payload' => $payload,
                ]);

                return true;
            }

            return false;
        }
    }

    public static function finishEvent(string $eventId): void
    {
        Database::connection()->prepare(
            'UPDATE stripe_events
             SET status = "PROCESSED", error_message = NULL, processed_at = NOW(), updated_at = NOW()
             WHERE stripe_event_id = :stripe_event_id'
        )->execute(['stripe_event_id' => $eventId]);
    }

    public static function failEvent(string $eventId, string $message): void
    {
        Database::connection()->prepare(
            'UPDATE stripe_events
             SET status = "ERROR", error_message = :error_message, updated_at = NOW()
             WHERE stripe_event_id = :stripe_event_id'
        )->execute([
            'stripe_event_id' => $eventId,
            'error_message' => substr($message, 0, 2000),
        ]);
    }

    public static function planByCode(string $code): ?array
    {
        self::ensureSchema();
        $stmt = Database::connection()->prepare('SELECT * FROM saas_plans WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => strtoupper($code)]);
        $plan = $stmt->fetch();

        return $plan ?: null;
    }

    public static function updateEmpresaStripeCustomer(string $empresaId, string $customerId): void
    {
        self::ensureSchema();
        Database::connection()->prepare(
            'UPDATE empresas
             SET stripe_customer_id = :stripe_customer_id,
                 stripe_last_error = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute(['id' => $empresaId, 'stripe_customer_id' => $customerId]);
    }

    public static function markCheckoutSession(string $empresaId, string $sessionId, string $planCode, string $renewalPeriod): void
    {
        self::ensureSchema();
        Database::connection()->prepare(
            'UPDATE empresas
             SET stripe_checkout_session_id = :stripe_checkout_session_id,
                 stripe_pending_plan_code = :stripe_pending_plan_code,
                 stripe_pending_renewal_period = :stripe_pending_renewal_period,
                 stripe_last_error = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'id' => $empresaId,
            'stripe_checkout_session_id' => $sessionId,
            'stripe_pending_plan_code' => $planCode,
            'stripe_pending_renewal_period' => $renewalPeriod,
        ]);
    }

    public static function recordEmpresaError(string $empresaId, string $message): void
    {
        self::ensureSchema();
        Database::connection()->prepare(
            'UPDATE empresas
             SET stripe_last_error = :stripe_last_error,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute(['id' => $empresaId, 'stripe_last_error' => substr($message, 0, 2000)]);
    }

    public static function syncCheckoutSession(array $session): void
    {
        self::ensureSchema();
        $empresaId = self::metadataValue($session, 'empresa_id') ?: (string) ($session['client_reference_id'] ?? '');
        if ($empresaId === '') {
            return;
        }

        $subscriptionId = self::objectId($session['subscription'] ?? null);
        $customerId = self::objectId($session['customer'] ?? null);
        $planCode = strtoupper(self::metadataValue($session, 'plan_code'));
        $renewalPeriod = strtoupper(self::metadataValue($session, 'renewal_period'));
        $stmt = Database::connection()->prepare(
            'UPDATE empresas
             SET stripe_customer_id = COALESCE(:stripe_customer_id, stripe_customer_id),
                 stripe_subscription_id = COALESCE(:stripe_subscription_id, stripe_subscription_id),
                 stripe_checkout_session_id = :stripe_checkout_session_id,
                 stripe_pending_plan_code = COALESCE(:stripe_pending_plan_code, stripe_pending_plan_code),
                 stripe_pending_renewal_period = COALESCE(:stripe_pending_renewal_period, stripe_pending_renewal_period),
                 payment_status = "PENDING",
                 stripe_last_error = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $empresaId,
            'stripe_customer_id' => $customerId ?: null,
            'stripe_subscription_id' => $subscriptionId ?: null,
            'stripe_checkout_session_id' => (string) ($session['id'] ?? ''),
            'stripe_pending_plan_code' => $planCode !== '' && $planCode !== 'TRIAL' ? $planCode : null,
            'stripe_pending_renewal_period' => in_array($renewalPeriod, ['MONTHLY', 'ANNUAL'], true) ? $renewalPeriod : null,
        ]);
    }

    public static function syncSubscription(array $subscription): void
    {
        self::ensureSchema();
        $subscriptionId = (string) ($subscription['id'] ?? '');
        if ($subscriptionId === '') {
            return;
        }

        $empresa = self::empresaForStripeObject($subscription);
        if (!$empresa) {
            return;
        }

        $currentPeriodStart = self::timestampDateTime($subscription['current_period_start'] ?? null);
        $currentPeriodEnd = self::timestampDateTime($subscription['current_period_end'] ?? null);
        $currentPeriodEndDate = self::timestampDate($subscription['current_period_end'] ?? null);
        $cancelAtPeriodEnd = !empty($subscription['cancel_at_period_end']);
        $stripeStatus = (string) ($subscription['status'] ?? '');
        $localRenewalStatus = $cancelAtPeriodEnd ? 'CANCEL_AT_PERIOD_END' : (in_array($stripeStatus, ['canceled', 'unpaid'], true) ? 'CANCELLED' : 'ACTIVE');
        $localStatus = in_array($stripeStatus, ['canceled', 'unpaid'], true) ? 'CANCELLED' : (string) ($empresa['status'] ?? 'ACTIVE');

        Database::connection()->prepare(
            'UPDATE empresas
             SET stripe_subscription_id = :stripe_subscription_id,
                 stripe_subscription_status = :stripe_subscription_status,
                 stripe_current_period_start = :stripe_current_period_start,
                 stripe_current_period_end = :stripe_current_period_end,
                 stripe_cancel_at_period_end = :stripe_cancel_at_period_end,
                 renewal_status = :renewal_status,
                 status = :status,
                 access_until = CASE
                     WHEN :cancel_at_period_end_flag = 1 AND :access_until_date IS NOT NULL THEN :access_until_date
                     ELSE access_until
                 END,
                 cancelled_at = CASE
                     WHEN :renewal_cancelled = "CANCELLED" THEN COALESCE(cancelled_at, CURDATE())
                     WHEN :renewal_pending = "CANCEL_AT_PERIOD_END" THEN COALESCE(cancelled_at, CURDATE())
                     ELSE NULL
                 END,
                 updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'id' => $empresa['id'],
            'stripe_subscription_id' => $subscriptionId,
            'stripe_subscription_status' => $stripeStatus ?: null,
            'stripe_current_period_start' => $currentPeriodStart,
            'stripe_current_period_end' => $currentPeriodEnd,
            'stripe_cancel_at_period_end' => $cancelAtPeriodEnd ? 1 : 0,
            'renewal_status' => $localRenewalStatus,
            'status' => $localStatus,
            'cancel_at_period_end_flag' => $cancelAtPeriodEnd ? 1 : 0,
            'access_until_date' => $currentPeriodEndDate,
            'renewal_cancelled' => $localRenewalStatus,
            'renewal_pending' => $localRenewalStatus,
        ]);
    }

    public static function syncDeletedSubscription(array $subscription): void
    {
        self::ensureSchema();
        $subscriptionId = (string) ($subscription['id'] ?? '');
        if ($subscriptionId === '') {
            return;
        }

        Database::connection()->prepare(
            'UPDATE empresas
             SET stripe_subscription_status = "canceled",
                 stripe_cancel_at_period_end = 0,
                 renewal_status = "CANCELLED",
                 status = "CANCELLED",
                 payment_status = "PENDING",
                 cancelled_at = COALESCE(cancelled_at, CURDATE()),
                 updated_at = NOW()
             WHERE stripe_subscription_id = :stripe_subscription_id'
        )->execute(['stripe_subscription_id' => $subscriptionId]);
    }

    public static function syncInvoicePaid(array $invoice): void
    {
        self::ensureSchema();
        $empresa = self::empresaForStripeObject($invoice);
        if (!$empresa) {
            return;
        }

        $invoiceId = (string) ($invoice['id'] ?? '');
        $paymentIntentId = self::paymentIntentId($invoice);
        $periodEndDate = self::invoicePeriodEndDate($invoice);
        $periodStartDate = self::invoicePeriodStartDate($invoice);
        $paidAt = self::timestampDate($invoice['status_transitions']['paid_at'] ?? null) ?: date('Y-m-d');
        $amount = self::centsToDecimal((int) ($invoice['amount_paid'] ?? $invoice['total'] ?? 0));
        $concept = self::invoiceDescription($invoice);
        $subscriptionMetadata = $invoice['_membora_subscription_metadata'] ?? [];
        if (!is_array($subscriptionMetadata)) {
            $subscriptionMetadata = [];
        }
        $paidPlanCode = strtoupper(trim((string) ($subscriptionMetadata['plan_code'] ?? $empresa['stripe_pending_plan_code'] ?? '')));
        $paidPlan = $paidPlanCode !== '' ? self::planByCode($paidPlanCode) : null;
        if (!$paidPlan || $paidPlanCode === 'TRIAL') {
            $paidPlanCode = strtoupper((string) ($empresa['plan'] ?? ''));
            $paidPlan = self::planByCode($paidPlanCode);
        }
        $paidRenewalPeriod = strtoupper(trim((string) ($subscriptionMetadata['renewal_period'] ?? $empresa['stripe_pending_renewal_period'] ?? $empresa['renewal_period'] ?? 'MONTHLY')));
        if (!in_array($paidRenewalPeriod, ['MONTHLY', 'ANNUAL'], true)) {
            $paidRenewalPeriod = 'MONTHLY';
        }
        $planPrices = $paidPlan ? PlatformPlanRepository::priceMap() : [];
        $paidMonthlyPrice = $planPrices[$paidPlanCode] ?? $empresa['monthly_price'];

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::upsertPlatformPayment($invoice, $empresa, 'PAID', $amount, $paidAt, $concept, $paymentIntentId);
            self::upsertPlatformInvoice($invoice, $empresa, 'PAID', 'PAID', $amount, $periodStartDate, $periodEndDate, $paymentIntentId);
            Database::connection()->prepare(
                'UPDATE empresas
                 SET status = "ACTIVE",
                     payment_status = "PAID",
                     plan = :plan,
                     monthly_price = :monthly_price,
                     renewal_period = :renewal_period,
                     stripe_customer_id = COALESCE(:stripe_customer_id, stripe_customer_id),
                     stripe_subscription_id = COALESCE(:stripe_subscription_id, stripe_subscription_id),
                     stripe_subscription_status = COALESCE(:stripe_subscription_status, stripe_subscription_status),
                     next_payment_at = COALESCE(:next_payment_at, next_payment_at),
                     access_until = COALESCE(:access_until, access_until),
                     subscription_started_at = COALESCE(subscription_started_at, CURDATE()),
                     paid_since = COALESCE(paid_since, CURDATE()),
                     renewal_status = CASE WHEN stripe_cancel_at_period_end = 1 THEN "CANCEL_AT_PERIOD_END" ELSE "ACTIVE" END,
                     stripe_pending_plan_code = NULL,
                     stripe_pending_renewal_period = NULL,
                     stripe_last_error = NULL,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'id' => $empresa['id'],
                'plan' => $paidPlanCode,
                'monthly_price' => $paidMonthlyPrice,
                'renewal_period' => $paidRenewalPeriod,
                'stripe_customer_id' => self::objectId($invoice['customer'] ?? null) ?: null,
                'stripe_subscription_id' => self::objectId($invoice['subscription'] ?? null) ?: null,
                'stripe_subscription_status' => null,
                'next_payment_at' => $periodEndDate,
                'access_until' => $periodEndDate,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function syncInvoicePaymentFailed(array $invoice): void
    {
        self::ensureSchema();
        $empresa = self::empresaForStripeObject($invoice);
        if (!$empresa) {
            return;
        }

        $amount = self::centsToDecimal((int) ($invoice['amount_due'] ?? $invoice['total'] ?? 0));
        $dueAt = self::timestampDate($invoice['due_date'] ?? null) ?: date('Y-m-d');
        $concept = self::invoiceDescription($invoice);
        $paymentIntentId = self::paymentIntentId($invoice);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::upsertPlatformPayment($invoice, $empresa, 'OVERDUE', $amount, null, $concept, $paymentIntentId, $dueAt);
            self::upsertPlatformInvoice($invoice, $empresa, 'ISSUED', 'OVERDUE', $amount, self::invoicePeriodStartDate($invoice), self::invoicePeriodEndDate($invoice), $paymentIntentId);
            Database::connection()->prepare(
                'UPDATE empresas
                 SET payment_status = "OVERDUE",
                     stripe_last_error = :stripe_last_error,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'id' => $empresa['id'],
                'stripe_last_error' => 'Pago Stripe fallido para factura ' . ((string) ($invoice['number'] ?? $invoice['id'] ?? '')),
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function empresaForStripeObject(array $object): ?array
    {
        self::ensureSchema();
        $empresaId = self::metadataValue($object, 'empresa_id');
        if ($empresaId !== '') {
            $empresa = EmpresaRepository::find($empresaId);
            if ($empresa) {
                return $empresa;
            }
        }

        $subscriptionId = self::objectId($object['subscription'] ?? ($object['id'] ?? null));
        if ($subscriptionId !== '') {
            $stmt = Database::connection()->prepare('SELECT * FROM empresas WHERE stripe_subscription_id = :stripe_subscription_id LIMIT 1');
            $stmt->execute(['stripe_subscription_id' => $subscriptionId]);
            $empresa = $stmt->fetch();
            if ($empresa) {
                return $empresa;
            }
        }

        $customerId = self::objectId($object['customer'] ?? null);
        if ($customerId !== '') {
            $stmt = Database::connection()->prepare('SELECT * FROM empresas WHERE stripe_customer_id = :stripe_customer_id LIMIT 1');
            $stmt->execute(['stripe_customer_id' => $customerId]);
            $empresa = $stmt->fetch();
            if ($empresa) {
                return $empresa;
            }
        }

        return null;
    }

    private static function upsertPlatformPayment(array $invoice, array $empresa, string $status, string $amount, ?string $paidAt, string $concept, string $paymentIntentId, ?string $dueAt = null): void
    {
        $simulated = !empty($invoice['_membora_simulated']);
        $invoiceId = (string) ($invoice['id'] ?? '');
        $stmt = Database::connection()->prepare('SELECT id FROM empresa_payments WHERE stripe_invoice_id = :stripe_invoice_id LIMIT 1');
        $stmt->execute(['stripe_invoice_id' => $invoiceId]);
        $paymentId = $stmt->fetchColumn();
        $params = [
            'empresa_id' => $empresa['id'],
            'stripe_invoice_id' => $invoiceId,
            'stripe_payment_intent_id' => $paymentIntentId ?: null,
            'stripe_status' => $simulated ? 'simulated_paid' : (string) ($invoice['status'] ?? ''),
            'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
            'billing_reason' => $invoice['billing_reason'] ?? null,
            'concept' => $concept,
            'amount' => $amount,
            'status' => $status,
            'due_at' => $dueAt ?: self::invoicePeriodEndDate($invoice),
            'paid_at' => $paidAt,
            'notes' => $simulated
                ? 'Pago simulado desde el checkout interno de Membora. No es un cargo bancario y no contiene datos de tarjeta.'
                : 'Sincronizado desde Stripe. No contiene datos de tarjeta.',
        ];

        if ($paymentId) {
            Database::connection()->prepare(
                'UPDATE empresa_payments
                 SET empresa_id = :empresa_id,
                     stripe_payment_intent_id = :stripe_payment_intent_id,
                     stripe_status = :stripe_status,
                     hosted_invoice_url = :hosted_invoice_url,
                     invoice_pdf = :invoice_pdf,
                     billing_reason = :billing_reason,
                     concept = :concept,
                     amount = :amount,
                     status = :status,
                     due_at = :due_at,
                     paid_at = :paid_at,
                     notes = :notes,
                     updated_at = NOW()
                 WHERE stripe_invoice_id = :stripe_invoice_id'
            )->execute($params);
            return;
        }

        Database::connection()->prepare(
            'INSERT INTO empresa_payments (id, empresa_id, stripe_invoice_id, stripe_payment_intent_id, stripe_status, hosted_invoice_url, invoice_pdf, billing_reason, concept, amount, status, due_at, paid_at, notes, created_at, updated_at)
             VALUES (:id, :empresa_id, :stripe_invoice_id, :stripe_payment_intent_id, :stripe_status, :hosted_invoice_url, :invoice_pdf, :billing_reason, :concept, :amount, :status, :due_at, :paid_at, :notes, NOW(), NOW())'
        )->execute($params + ['id' => cuid()]);
    }

    private static function upsertPlatformInvoice(array $invoice, array $empresa, string $invoiceStatus, string $collectionStatus, string $amount, ?string $periodStart, ?string $periodEnd, string $paymentIntentId): void
    {
        $simulated = !empty($invoice['_membora_simulated']);
        $invoiceId = (string) ($invoice['id'] ?? '');
        $stmt = Database::connection()->prepare('SELECT id FROM platform_invoices WHERE stripe_invoice_id = :stripe_invoice_id LIMIT 1');
        $stmt->execute(['stripe_invoice_id' => $invoiceId]);
        $localInvoiceId = $stmt->fetchColumn();
        $issuedAt = self::timestampDate($invoice['created'] ?? null) ?: date('Y-m-d');
        $invoiceCode = (string) ($invoice['number'] ?? $invoiceId);
        $concept = self::invoiceDescription($invoice);
        $currency = strtoupper((string) ($invoice['currency'] ?? 'EUR'));
        $params = [
            'empresa_id' => $empresa['id'],
            'stripe_invoice_id' => $invoiceId,
            'stripe_payment_intent_id' => $paymentIntentId ?: null,
            'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
            'invoice_series' => $simulated ? 'DEMO' : 'STRIPE',
            'invoice_code' => $invoiceCode,
            'invoice_status' => $invoiceStatus,
            'collection_status' => $collectionStatus,
            'issued_at' => $issuedAt,
            'period_start_at' => $periodStart,
            'period_end_at' => $periodEnd,
            'due_at' => self::timestampDate($invoice['due_date'] ?? null) ?: $periodEnd,
            'issuer_name' => getenv('INVOICE_ISSUER_NAME') ?: getenv('APP_NAME') ?: 'Membora CRM',
            'issuer_tax_id' => getenv('INVOICE_ISSUER_TAX_ID') ?: '',
            'issuer_address' => getenv('INVOICE_ISSUER_ADDRESS') ?: '',
            'issuer_postal_code' => getenv('INVOICE_ISSUER_POSTAL_CODE') ?: '',
            'issuer_city' => getenv('INVOICE_ISSUER_CITY') ?: '',
            'issuer_province' => getenv('INVOICE_ISSUER_PROVINCE') ?: '',
            'issuer_country' => getenv('INVOICE_ISSUER_COUNTRY') ?: 'Espana',
            'issuer_email' => getenv('INVOICE_ISSUER_EMAIL') ?: getenv('MAIL_FROM_EMAIL') ?: '',
            'issuer_phone' => getenv('INVOICE_ISSUER_PHONE') ?: '',
            'customer_name' => $empresa['name'],
            'customer_email' => $empresa['contact_email'] ?? '',
            'concept' => $concept,
            'subtotal_amount' => $amount,
            'taxable_base' => $amount,
            'tax_rate' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => $amount,
            'paid_amount' => $collectionStatus === 'PAID' ? $amount : '0.00',
            'pending_amount' => $collectionStatus === 'PAID' ? '0.00' : $amount,
            'currency' => substr($currency, 0, 3),
            'payment_method' => $simulated ? 'SIMULATED' : 'STRIPE',
            'status' => $collectionStatus === 'PAID' ? 'PAID' : 'SENT',
            'notes' => $simulated
                ? 'Justificante de demostracion generado por un pago simulado. No acredita un cargo bancario real.'
                : 'Factura sincronizada desde Stripe. PDF/hosted URL se guardan si Stripe los entrega.',
        ];

        if ($localInvoiceId) {
            Database::connection()->prepare(
                'UPDATE platform_invoices
                 SET stripe_payment_intent_id = :stripe_payment_intent_id,
                     hosted_invoice_url = :hosted_invoice_url,
                     invoice_pdf = :invoice_pdf,
                     invoice_code = :invoice_code,
                     invoice_status = :invoice_status,
                     collection_status = :collection_status,
                     period_start_at = :period_start_at,
                     period_end_at = :period_end_at,
                     due_at = :due_at,
                     total_amount = :total_amount,
                     paid_amount = :paid_amount,
                     pending_amount = :pending_amount,
                     status = :status,
                     notes = :notes,
                     updated_at = NOW()
                 WHERE stripe_invoice_id = :stripe_invoice_id'
            )->execute(array_intersect_key($params, array_flip([
                'stripe_invoice_id', 'stripe_payment_intent_id', 'hosted_invoice_url', 'invoice_pdf', 'invoice_code',
                'invoice_status', 'collection_status', 'period_start_at', 'period_end_at', 'due_at', 'total_amount',
                'paid_amount', 'pending_amount', 'status', 'notes',
            ])));
            self::ensureStripeInvoicePayment((string) $localInvoiceId, $params, $paymentIntentId);
            return;
        }

        $id = cuid();
        Database::connection()->prepare(
            'INSERT INTO platform_invoices (
                id, empresa_id, invoice_scope, stripe_invoice_id, stripe_payment_intent_id, hosted_invoice_url, invoice_pdf,
                invoice_series, invoice_number, invoice_code, invoice_type, invoice_status, collection_status,
                issued_at, period_start_at, period_end_at, due_at,
                issuer_name, issuer_tax_id, issuer_address, issuer_postal_code, issuer_city, issuer_province, issuer_country, issuer_email, issuer_phone,
                customer_name, customer_email, concept, subtotal_amount, discount_amount, taxable_base, tax_rate, tax_amount, total_amount,
                paid_amount, pending_amount, currency, payment_method, fiscal_treatment, status, notes, created_at, updated_at
             ) VALUES (
                :id, :empresa_id, "PLATFORM", :stripe_invoice_id, :stripe_payment_intent_id, :hosted_invoice_url, :invoice_pdf,
                :invoice_series, NULL, :invoice_code, "ORDINARY", :invoice_status, :collection_status,
                :issued_at, :period_start_at, :period_end_at, :due_at,
                :issuer_name, :issuer_tax_id, :issuer_address, :issuer_postal_code, :issuer_city, :issuer_province, :issuer_country, :issuer_email, :issuer_phone,
                :customer_name, :customer_email, :concept, :subtotal_amount, 0, :taxable_base, :tax_rate, :tax_amount, :total_amount,
                :paid_amount, :pending_amount, :currency, :payment_method, "VAT_SUBJECT", :status, :notes, NOW(), NOW()
             )'
        )->execute($params + ['id' => $id]);
        self::ensureStripeInvoicePayment($id, $params, $paymentIntentId);
    }

    private static function ensureStripeInvoicePayment(string $localInvoiceId, array $invoiceParams, string $paymentIntentId): void
    {
        if (($invoiceParams['collection_status'] ?? '') !== 'PAID' || $paymentIntentId === '') {
            return;
        }

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM platform_invoice_payments WHERE invoice_id = :invoice_id AND stripe_payment_intent_id = :stripe_payment_intent_id');
        $stmt->execute(['invoice_id' => $localInvoiceId, 'stripe_payment_intent_id' => $paymentIntentId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        Database::connection()->prepare(
            'INSERT INTO platform_invoice_payments (id, invoice_id, stripe_payment_intent_id, paid_at, amount, payment_method, reference, notes, created_at, updated_at)
             VALUES (:id, :invoice_id, :stripe_payment_intent_id, :paid_at, :amount, :payment_method, :reference, :notes, NOW(), NOW())'
        )->execute([
            'id' => cuid(),
            'invoice_id' => $localInvoiceId,
            'stripe_payment_intent_id' => $paymentIntentId,
            'paid_at' => date('Y-m-d'),
            'amount' => $invoiceParams['paid_amount'] ?? $invoiceParams['total_amount'] ?? '0.00',
            'payment_method' => $invoiceParams['payment_method'] ?? 'STRIPE',
            'reference' => $paymentIntentId,
            'notes' => ($invoiceParams['payment_method'] ?? 'STRIPE') === 'SIMULATED'
                ? 'Pago simulado sin cargo bancario.'
                : 'Sincronizado desde Stripe.',
        ]);
    }

    private static function ensureColumn(string $table, string $column, string $sql): void
    {
        $stmt = Database::connection()->query('SHOW COLUMNS FROM ' . $table . ' LIKE ' . Database::connection()->quote($column));
        if (!$stmt->fetch()) {
            Database::connection()->exec($sql);
        }
    }

    private static function metadataValue(array $object, string $key): string
    {
        $metadata = $object['metadata'] ?? [];
        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            $metadata = $metadata->toArray();
        }
        if (!is_array($metadata)) {
            return '';
        }

        return trim((string) ($metadata[$key] ?? ''));
    }

    private static function objectId(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            return (string) ($value['id'] ?? '');
        }
        if (is_object($value) && isset($value->id)) {
            return (string) $value->id;
        }

        return '';
    }

    private static function paymentIntentId(array $invoice): string
    {
        $direct = self::objectId($invoice['payment_intent'] ?? null);
        if ($direct !== '') {
            return $direct;
        }

        $payments = $invoice['payments']['data'] ?? [];
        if (is_array($payments)) {
            foreach ($payments as $payment) {
                $intent = self::objectId($payment['payment']['payment_intent'] ?? $payment['payment_intent'] ?? null);
                if ($intent !== '') {
                    return $intent;
                }
            }
        }

        return '';
    }

    private static function invoiceDescription(array $invoice): string
    {
        $lines = $invoice['lines']['data'] ?? [];
        if (is_array($lines) && isset($lines[0]) && is_array($lines[0])) {
            $description = trim((string) ($lines[0]['description'] ?? ''));
            if ($description !== '') {
                return $description;
            }
        }

        return 'Suscripcion Membora CRM';
    }

    private static function invoicePeriodStartDate(array $invoice): ?string
    {
        $lines = $invoice['lines']['data'] ?? [];
        $timestamp = is_array($lines) && isset($lines[0]['period']['start']) ? $lines[0]['period']['start'] : null;

        return self::timestampDate($timestamp);
    }

    private static function invoicePeriodEndDate(array $invoice): ?string
    {
        $lines = $invoice['lines']['data'] ?? [];
        $timestamp = is_array($lines) && isset($lines[0]['period']['end']) ? $lines[0]['period']['end'] : null;

        return self::timestampDate($timestamp);
    }

    private static function timestampDateTime(mixed $timestamp): ?string
    {
        if (!$timestamp || !is_numeric($timestamp)) {
            return null;
        }

        return date('Y-m-d H:i:s', (int) $timestamp);
    }

    private static function timestampDate(mixed $timestamp): ?string
    {
        if (!$timestamp || !is_numeric($timestamp)) {
            return null;
        }

        return date('Y-m-d', (int) $timestamp);
    }

    private static function centsToDecimal(int $cents): string
    {
        return number_format(max(0, $cents) / 100, 2, '.', '');
    }
}

final class SimulatedCheckoutService
{
    public const TEST_CARD_NUMBER = '4242424242424242';

    public static function validateCard(string $number, string $expiry, string $cvc): void
    {
        $normalizedNumber = preg_replace('/\D+/', '', $number) ?: '';
        if (!hash_equals(self::TEST_CARD_NUMBER, $normalizedNumber)) {
            throw new RuntimeException('Usa la tarjeta ficticia 4242 4242 4242 4242. No introduzcas una tarjeta real.');
        }

        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', trim($expiry), $matches)) {
            throw new RuntimeException('La caducidad ficticia debe tener formato MM/AA.');
        }

        $expiryMonth = (int) $matches[1];
        $expiryYear = 2000 + (int) $matches[2];
        if ($expiryYear < (int) date('Y') || ($expiryYear === (int) date('Y') && $expiryMonth < (int) date('n'))) {
            throw new RuntimeException('Usa una fecha de caducidad ficticia futura.');
        }

        if (!preg_match('/^\d{3}$/', trim($cvc))) {
            throw new RuntimeException('El CVC ficticio debe tener tres numeros.');
        }
    }

    public static function complete(string $empresaId, string $planCode, string $period): array
    {
        if (!StripeBillingConfig::simulatedCheckoutEnabled()) {
            throw new RuntimeException('El checkout simulado no esta habilitado.');
        }

        StripeBillingRepository::ensureSchema();
        $empresa = EmpresaRepository::find($empresaId);
        if (!$empresa) {
            throw new RuntimeException('No se encontro la empresa vinculada al pago.');
        }

        $planCode = strtoupper(trim($planCode));
        $plan = StripeBillingRepository::planByCode($planCode);
        if (!$plan || $planCode === 'TRIAL' || (string) ($plan['status'] ?? '') !== 'ACTIVE') {
            throw new RuntimeException('Selecciona un plan de pago activo.');
        }
        if (!PlatformPlanRepository::canUpgrade((string) ($empresa['plan'] ?? ''), $planCode)) {
            throw new RuntimeException('Selecciona un plan superior al que tienes actualmente.');
        }

        $period = strtoupper(trim($period));
        if (!in_array($period, ['MONTHLY', 'ANNUAL'], true)) {
            throw new RuntimeException('Selecciona una periodicidad valida.');
        }

        $monthlyAmount = (float) (PlatformPlanRepository::priceMap()[$planCode] ?? 0);
        $amount = $period === 'ANNUAL' ? $monthlyAmount * 12 : $monthlyAmount;
        if ($amount <= 0) {
            throw new RuntimeException('El plan seleccionado no tiene un precio valido.');
        }

        $now = time();
        $periodEnd = strtotime($period === 'ANNUAL' ? '+1 year' : '+1 month', $now);
        $simulationId = 'sim_' . cuid();
        $invoice = [
            'id' => 'sim_invoice_' . $simulationId,
            'number' => 'DEMO-' . strtoupper(substr(hash('sha256', $simulationId), 0, 10)),
            'status' => 'paid',
            'currency' => 'eur',
            'amount_paid' => (int) round($amount * 100),
            'total' => (int) round($amount * 100),
            'created' => $now,
            'status_transitions' => ['paid_at' => $now],
            'payment_intent' => 'sim_payment_' . $simulationId,
            'billing_reason' => 'simulated_subscription',
            'metadata' => ['empresa_id' => $empresaId],
            '_membora_subscription_metadata' => [
                'plan_code' => $planCode,
                'renewal_period' => $period,
            ],
            '_membora_simulated' => true,
            'lines' => [
                'data' => [[
                    'description' => 'Pago simulado plan ' . (string) ($plan['name'] ?? $planCode) . ' - ' . empresa_renewal_period_label($period),
                    'period' => ['start' => $now, 'end' => $periodEnd],
                ]],
            ],
        ];

        StripeBillingRepository::syncInvoicePaid($invoice);

        return [
            'amount' => number_format($amount, 2, '.', ''),
            'period' => $period,
            'plan_code' => $planCode,
        ];
    }
}

final class StripeBillingService
{
    public static function createCheckoutSession(string $empresaId, ?string $requestedPlanCode = null, ?string $requestedPeriod = null, bool $tenantCheckout = false): string
    {
        StripeBillingConfig::assertReady();
        StripeBillingRepository::ensureSchema();
        \Stripe\Stripe::setApiKey(StripeBillingConfig::secretKey());

        $empresa = EmpresaRepository::find($empresaId);
        if (!$empresa) {
            throw new RuntimeException('No se encontro la empresa.');
        }

        $planCode = strtoupper(trim((string) ($requestedPlanCode ?: ($empresa['plan'] ?? ''))));
        $plan = StripeBillingRepository::planByCode($planCode);
        if (!$plan || strtoupper((string) $plan['code']) === 'TRIAL' || (string) ($plan['status'] ?? '') !== 'ACTIVE') {
            throw new RuntimeException('Selecciona un plan de pago antes de crear el checkout.');
        }

        $period = strtoupper(trim((string) ($requestedPeriod ?: ($empresa['renewal_period'] ?? 'MONTHLY'))));
        if (!in_array($period, ['MONTHLY', 'ANNUAL'], true)) {
            throw new RuntimeException('Selecciona una periodicidad de pago valida.');
        }
        $configuredPriceId = $period === 'ANNUAL'
            ? trim((string) ($plan['stripe_annual_price_id'] ?? ''))
            : trim((string) ($plan['stripe_monthly_price_id'] ?? ''));
        if ($configuredPriceId === '') {
            throw new RuntimeException('Falta el Price ID de Stripe para el plan ' . $plan['code'] . ' en modalidad ' . empresa_renewal_period_label($period) . '.');
        }
        $priceId = self::resolveConfiguredPriceId($configuredPriceId, $period);

        $customerId = trim((string) ($empresa['stripe_customer_id'] ?? ''));
        if ($customerId === '') {
            $customer = \Stripe\Customer::create([
                'name' => (string) $empresa['name'],
                'email' => (string) ($empresa['contact_email'] ?? ''),
                'metadata' => [
                    'empresa_id' => (string) $empresa['id'],
                    'client_id' => (string) ($empresa['client_id'] ?? ''),
                    'payments_mode' => StripeBillingConfig::mode(),
                ],
            ]);
            $customerId = (string) $customer->id;
            StripeBillingRepository::updateEmpresaStripeCustomer($empresaId, $customerId);
        } else {
            \Stripe\Customer::update($customerId, [
                'name' => (string) $empresa['name'],
                'email' => (string) ($empresa['contact_email'] ?? ''),
                'metadata' => [
                    'empresa_id' => (string) $empresa['id'],
                    'client_id' => (string) ($empresa['client_id'] ?? ''),
                    'payments_mode' => StripeBillingConfig::mode(),
                ],
            ]);
        }

        $metadata = [
            'empresa_id' => (string) $empresa['id'],
            'client_id' => (string) ($empresa['client_id'] ?? ''),
            'plan_code' => (string) $plan['code'],
            'renewal_period' => $period,
            'payments_mode' => StripeBillingConfig::mode(),
        ];

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => (string) $empresa['id'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => app_base_url() . '/stripe/checkout/success?source=' . ($tenantCheckout ? 'tenant' : 'platform') . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => app_base_url() . '/stripe/checkout/cancel?source=' . ($tenantCheckout ? 'tenant' : 'platform') . '&empresa_id=' . rawurlencode((string) $empresa['id']),
            'allow_promotion_codes' => true,
            'metadata' => $metadata,
            'subscription_data' => [
                'metadata' => $metadata,
            ],
        ]);

        StripeBillingRepository::markCheckoutSession($empresaId, (string) $session->id, (string) $plan['code'], $period);

        return (string) $session->url;
    }

    /**
     * Activa la suscripcion en cuanto el usuario vuelve del pago, sin depender del webhook.
     * El webhook sigue siendo la fuente de verdad para eventos posteriores (renovaciones, impagos,
     * cancelaciones), pero la activacion inmediata no debe quedar bloqueada si el webhook no llega.
     * Devuelve true si el pago esta confirmado y la empresa ha quedado activada.
     */
    public static function activateFromCheckoutSession(string $sessionId): bool
    {
        if (trim($sessionId) === '') {
            return false;
        }

        StripeBillingConfig::assertReady();
        StripeBillingRepository::ensureSchema();
        \Stripe\Stripe::setApiKey(StripeBillingConfig::secretKey());

        $session = \Stripe\Checkout\Session::retrieve([
            'id' => $sessionId,
            'expand' => ['subscription', 'subscription.latest_invoice', 'customer'],
        ]);
        $sessionArray = $session->toArray();

        // Vincula cliente y suscripcion de Stripe con la empresa (aunque el pago aun no este confirmado).
        StripeBillingRepository::syncCheckoutSession($sessionArray);

        $paid = ($sessionArray['payment_status'] ?? '') === 'paid'
            || ($sessionArray['status'] ?? '') === 'complete';
        if (!$paid) {
            return false;
        }

        $subscription = $sessionArray['subscription'] ?? null;
        if (is_array($subscription)) {
            StripeBillingRepository::syncSubscription($subscription);

            $invoice = $subscription['latest_invoice'] ?? null;
            if (is_array($invoice) && ($invoice['status'] ?? '') === 'paid') {
                StripeBillingRepository::syncInvoicePaid($invoice);
            }
        }

        return true;
    }

    private static function resolveConfiguredPriceId(string $configuredId, string $period): string
    {
        if (str_starts_with($configuredId, 'price_')) {
            return $configuredId;
        }

        if (!str_starts_with($configuredId, 'prod_')) {
            throw new RuntimeException('La referencia de Stripe debe comenzar por price_ o prod_.');
        }

        try {
            $prices = \Stripe\Price::all([
                'active' => true,
                'limit' => 100,
                'product' => $configuredId,
                'type' => 'recurring',
            ]);
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            throw new RuntimeException('No se pudo consultar el producto ' . $configuredId . ' en Stripe: ' . $exception->getMessage(), 0, $exception);
        }

        $expectedInterval = $period === 'ANNUAL' ? 'year' : 'month';
        foreach ($prices->data as $price) {
            $interval = (string) ($price->recurring->interval ?? '');
            $intervalCount = (int) ($price->recurring->interval_count ?? 1);
            if ($interval === $expectedInterval && $intervalCount === 1) {
                return (string) $price->id;
            }
        }

        throw new RuntimeException(
            'El producto ' . $configuredId . ' no tiene una tarifa recurrente '
            . ($period === 'ANNUAL' ? 'anual' : 'mensual') . ' activa en Stripe.'
        );
    }

    public static function cancelAtPeriodEnd(string $empresaId): void
    {
        StripeBillingConfig::assertReady();
        StripeBillingRepository::ensureSchema();
        \Stripe\Stripe::setApiKey(StripeBillingConfig::secretKey());

        $empresa = EmpresaRepository::find($empresaId);
        if (!$empresa || trim((string) ($empresa['stripe_subscription_id'] ?? '')) === '') {
            throw new RuntimeException('La empresa no tiene una suscripcion Stripe vinculada.');
        }

        $subscription = \Stripe\Subscription::update((string) $empresa['stripe_subscription_id'], [
            'cancel_at_period_end' => true,
        ]);
        StripeBillingRepository::syncSubscription($subscription->toArray());
    }

    public static function handleWebhook(string $payload, string $signature): array
    {
        StripeBillingConfig::assertReady();
        $webhookSecret = StripeBillingConfig::webhookSecret();
        if ($webhookSecret === '') {
            throw new RuntimeException('Falta STRIPE_WEBHOOK_SECRET en apps/crm/.env.');
        }

        \Stripe\Stripe::setApiKey(StripeBillingConfig::secretKey());
        $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
        $eventArray = $event->toArray();
        $eventId = (string) ($eventArray['id'] ?? '');
        $eventType = (string) ($eventArray['type'] ?? '');

        if ($eventId === '') {
            throw new RuntimeException('Evento Stripe sin id.');
        }

        if (!StripeBillingRepository::beginEvent($eventId, $eventType, $payload)) {
            return ['received' => true, 'duplicate' => true];
        }

        try {
            $object = $eventArray['data']['object'] ?? [];
            if (!is_array($object)) {
                $object = [];
            }
            if (in_array($eventType, ['invoice.paid', 'invoice.payment_failed'], true)) {
                $object = self::withSubscriptionMetadata($object);
            }

            match ($eventType) {
                'checkout.session.completed' => StripeBillingRepository::syncCheckoutSession($object),
                'invoice.paid' => StripeBillingRepository::syncInvoicePaid($object),
                'invoice.payment_failed' => StripeBillingRepository::syncInvoicePaymentFailed($object),
                'customer.subscription.created',
                'customer.subscription.updated' => StripeBillingRepository::syncSubscription($object),
                'customer.subscription.deleted' => StripeBillingRepository::syncDeletedSubscription($object),
                default => null,
            };

            StripeBillingRepository::finishEvent($eventId);
            return ['received' => true, 'event' => $eventType];
        } catch (Throwable $exception) {
            StripeBillingRepository::failEvent($eventId, $exception->getMessage());
            throw $exception;
        }
    }

    private static function withSubscriptionMetadata(array $invoice): array
    {
        $subscription = $invoice['subscription'] ?? null;
        $subscriptionId = is_string($subscription)
            ? $subscription
            : (is_array($subscription) ? (string) ($subscription['id'] ?? '') : '');
        if ($subscriptionId === '') {
            return $invoice;
        }

        $subscriptionObject = \Stripe\Subscription::retrieve($subscriptionId);
        $subscriptionData = $subscriptionObject->toArray();
        $metadata = $subscriptionData['metadata'] ?? [];
        $invoice['_membora_subscription_metadata'] = is_array($metadata) ? $metadata : [];

        return $invoice;
    }
}
