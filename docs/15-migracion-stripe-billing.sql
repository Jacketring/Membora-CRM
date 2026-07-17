-- Migracion opcional para Stripe Billing administrativo.
-- La aplicacion PHP tambien crea estos campos de forma incremental.
-- Ejecutar solo si la base de datos no tiene todavia estos campos.

ALTER TABLE empresas
  ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(191) NULL AFTER contact_email,
  ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(191) NULL AFTER stripe_customer_id,
  ADD COLUMN IF NOT EXISTS stripe_subscription_status VARCHAR(64) NULL AFTER stripe_subscription_id,
  ADD COLUMN IF NOT EXISTS stripe_current_period_start DATETIME NULL AFTER stripe_subscription_status,
  ADD COLUMN IF NOT EXISTS stripe_current_period_end DATETIME NULL AFTER stripe_current_period_start,
  ADD COLUMN IF NOT EXISTS stripe_cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0 AFTER stripe_current_period_end,
  ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(191) NULL AFTER stripe_cancel_at_period_end,
  ADD COLUMN IF NOT EXISTS stripe_pending_plan_code VARCHAR(64) NULL AFTER stripe_checkout_session_id,
  ADD COLUMN IF NOT EXISTS stripe_pending_renewal_period VARCHAR(16) NULL AFTER stripe_pending_plan_code,
  ADD COLUMN IF NOT EXISTS stripe_last_error TEXT NULL AFTER stripe_pending_renewal_period;

ALTER TABLE saas_plans
  ADD COLUMN IF NOT EXISTS stripe_monthly_price_id VARCHAR(191) NULL AFTER discount_label,
  ADD COLUMN IF NOT EXISTS stripe_annual_price_id VARCHAR(191) NULL AFTER stripe_monthly_price_id;

ALTER TABLE empresa_payments
  ADD COLUMN IF NOT EXISTS stripe_invoice_id VARCHAR(191) NULL AFTER empresa_id,
  ADD COLUMN IF NOT EXISTS stripe_payment_intent_id VARCHAR(191) NULL AFTER stripe_invoice_id,
  ADD COLUMN IF NOT EXISTS stripe_status VARCHAR(64) NULL AFTER stripe_payment_intent_id,
  ADD COLUMN IF NOT EXISTS hosted_invoice_url TEXT NULL AFTER stripe_status,
  ADD COLUMN IF NOT EXISTS invoice_pdf TEXT NULL AFTER hosted_invoice_url,
  ADD COLUMN IF NOT EXISTS billing_reason VARCHAR(191) NULL AFTER invoice_pdf;

ALTER TABLE platform_invoices
  ADD COLUMN IF NOT EXISTS stripe_invoice_id VARCHAR(191) NULL AFTER payment_id,
  ADD COLUMN IF NOT EXISTS stripe_payment_intent_id VARCHAR(191) NULL AFTER stripe_invoice_id,
  ADD COLUMN IF NOT EXISTS hosted_invoice_url TEXT NULL AFTER stripe_payment_intent_id,
  ADD COLUMN IF NOT EXISTS invoice_pdf TEXT NULL AFTER hosted_invoice_url;

ALTER TABLE platform_invoice_payments
  ADD COLUMN IF NOT EXISTS stripe_payment_intent_id VARCHAR(191) NULL AFTER invoice_id;

CREATE TABLE IF NOT EXISTS stripe_events (
  id VARCHAR(191) NOT NULL PRIMARY KEY,
  stripe_event_id VARCHAR(191) NOT NULL,
  event_type VARCHAR(191) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'RECEIVED',
  payload LONGTEXT NULL,
  error_message TEXT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY stripe_events_event_unique (stripe_event_id),
  INDEX stripe_events_type_idx (event_type),
  INDEX stripe_events_status_idx (status)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Si tu version de MariaDB permite indices IF NOT EXISTS, puedes ejecutar estos indices.
-- Si ya existen, ignora el error de "Duplicate key name".
ALTER TABLE empresas ADD UNIQUE KEY empresas_stripe_customer_unique (stripe_customer_id);
ALTER TABLE empresas ADD UNIQUE KEY empresas_stripe_subscription_unique (stripe_subscription_id);
ALTER TABLE empresa_payments ADD UNIQUE KEY empresa_payments_stripe_invoice_unique (stripe_invoice_id);
ALTER TABLE platform_invoices ADD UNIQUE KEY platform_invoices_stripe_invoice_unique (stripe_invoice_id);
