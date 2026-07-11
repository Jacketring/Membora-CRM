<?php
$isEditingInvoice = isset($invoice) && is_array($invoice);
$clientInvoiceMode = !empty($clientInvoiceMode);
$invoiceItems = $isEditingInvoice ? PlatformInvoiceRepository::items((string) $invoice['id']) : [];
$invoicePayments = $isEditingInvoice ? PlatformInvoiceRepository::payments((string) $invoice['id']) : [];
$invoiceValues = $isEditingInvoice ? $invoice : [
    'id' => '',
    'empresa_id' => $empresas[0]['id'] ?? '',
    'payment_id' => '',
    'invoice_series' => $nextInvoiceSeries ?? PlatformInvoiceRepository::defaultSeries(),
    'invoice_number' => null,
    'invoice_code' => '',
    'invoice_type' => 'ORDINARY',
    'invoice_status' => 'DRAFT',
    'collection_status' => 'PENDING',
    'issued_at' => date('Y-m-d'),
    'operation_at' => date('Y-m-d'),
    'period_start_at' => '',
    'period_end_at' => '',
    'due_at' => date('Y-m-d', strtotime('+15 days')),
    'issuer_name' => getenv('INVOICE_ISSUER_NAME') ?: getenv('APP_NAME') ?: 'Membora CRM',
    'issuer_tax_id' => getenv('INVOICE_ISSUER_TAX_ID') ?: '',
    'issuer_address' => getenv('INVOICE_ISSUER_ADDRESS') ?: '',
    'issuer_postal_code' => getenv('INVOICE_ISSUER_POSTAL_CODE') ?: '',
    'issuer_city' => getenv('INVOICE_ISSUER_CITY') ?: '',
    'issuer_province' => getenv('INVOICE_ISSUER_PROVINCE') ?: '',
    'issuer_country' => getenv('INVOICE_ISSUER_COUNTRY') ?: 'España',
    'issuer_email' => getenv('INVOICE_ISSUER_EMAIL') ?: getenv('MAIL_FROM_EMAIL') ?: '',
    'issuer_phone' => getenv('INVOICE_ISSUER_PHONE') ?: '',
    'customer_name' => '',
    'customer_tax_id' => '',
    'customer_address' => '',
    'customer_postal_code' => '',
    'customer_city' => '',
    'customer_province' => '',
    'customer_country' => 'España',
    'customer_email' => '',
    'customer_phone' => '',
    'concept' => '',
    'subtotal_amount' => '0.00',
    'discount_amount' => '0.00',
    'taxable_base' => '0.00',
    'tax_amount' => '0.00',
    'total_amount' => '0.00',
    'paid_amount' => '0.00',
    'pending_amount' => '0.00',
    'currency' => 'EUR',
    'payment_method' => 'TRANSFER',
    'fiscal_treatment' => 'VAT_SUBJECT',
    'fiscal_note' => '',
    'public_notes' => '',
    'status' => 'ISSUED',
    'notes' => '',
];
if (!$invoiceItems) {
    $invoiceItems = [[
        'description' => $invoiceValues['concept'] ?: 'Suscripción Membora CRM',
        'quantity' => '1.000',
        'unit' => 'ud',
        'unit_price' => $invoiceValues['taxable_base'] ?: '0.00',
        'discount_type' => 'PERCENT',
        'discount_value' => '0.00',
        'tax_rate' => $invoiceValues['tax_rate'] ?? '21.00',
    ]];
}
$isIssued = ($invoiceValues['invoice_status'] ?? 'DRAFT') !== 'DRAFT';
$invoiceTypeOptions = ['ORDINARY' => 'Ordinaria', 'SIMPLIFIED' => 'Simplificada', 'RECTIFYING' => 'Rectificativa'];
$invoiceStatusOptions = ['DRAFT' => 'Borrador', 'ISSUED' => 'Emitida', 'RECTIFIED' => 'Rectificada'];
$collectionStatusOptions = ['PENDING' => 'Pendiente', 'PARTIAL' => 'Parcial', 'PAID' => 'Pagada', 'OVERDUE' => 'Vencida', 'REFUNDED' => 'Reembolsada'];
$paymentMethods = ['TRANSFER' => 'Transferencia', 'CARD' => 'Tarjeta', 'STRIPE' => 'Stripe', 'CASH' => 'Efectivo', 'OTHER' => 'Otro'];
$fiscalOptions = ['VAT_SUBJECT' => 'Sujeto a IVA', 'EXEMPT' => 'Exento', 'NOT_SUBJECT' => 'No sujeto', 'REVERSE_CHARGE' => 'Inversion del sujeto pasivo', 'OTHER' => 'Otro'];
?>

<form class="empresa-form invoice-form" method="post" data-invoice-form>
  <input type="hidden" name="action" value="<?= $isEditingInvoice ? ($clientInvoiceMode ? 'update_client_invoice' : 'update_platform_invoice') : ($clientInvoiceMode ? 'create_client_invoice' : 'create_platform_invoice') ?>">
  <input type="hidden" name="invoice_status" value="<?= e($invoiceValues['invoice_status'] ?? 'DRAFT') ?>">
  <?php if ($isEditingInvoice): ?>
    <input type="hidden" name="id" value="<?= e($invoiceValues['id']) ?>">
  <?php endif; ?>

  <div class="form-full platform-form-divider">
    <strong>Emisor y cliente</strong>
    <span>Los datos fiscales se guardan como copia historica de la factura.</span>
  </div>

  <label class="field">
    <span>Empresa emisora</span>
    <input name="issuer_name" required <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['issuer_name']) ?>">
  </label>
  <label class="field">
    <span>NIF/CIF emisor</span>
    <input name="issuer_tax_id" required <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['issuer_tax_id']) ?>">
  </label>
  <label class="field form-full">
    <span>Direccion fiscal emisor</span>
    <input name="issuer_address" required <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['issuer_address']) ?>">
  </label>
  <label class="field">
    <span>CP emisor</span>
    <input name="issuer_postal_code" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['issuer_postal_code']) ?>">
  </label>
  <label class="field">
    <span>Localidad emisor</span>
    <input name="issuer_city" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['issuer_city']) ?>">
  </label>
  <label class="field">
    <span>Provincia emisor</span>
    <input name="issuer_province" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['issuer_province']) ?>">
  </label>
  <label class="field">
    <span>Pais emisor</span>
    <input name="issuer_country" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['issuer_country']) ?>">
  </label>
  <label class="field">
    <span>Email emisor</span>
    <input name="issuer_email" type="email" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['issuer_email']) ?>">
  </label>
  <label class="field">
    <span>Teléfono emisor</span>
    <input name="issuer_phone" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['issuer_phone']) ?>">
  </label>

  <label class="field">
    <span>Cliente existente</span>
    <select name="empresa_id" required <?= $isIssued ? 'disabled' : '' ?>>
      <?php foreach ($empresas as $empresaOption): ?>
        <option value="<?= e($empresaOption['id']) ?>" data-customer-name="<?= e($empresaOption['name']) ?>" data-customer-email="<?= e($empresaOption['contact_email'] ?? '') ?>" <?= $invoiceValues['empresa_id'] === $empresaOption['id'] ? 'selected' : '' ?>>
          <?= e($empresaOption['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($isIssued): ?><input type="hidden" name="empresa_id" value="<?= e($invoiceValues['empresa_id']) ?>"><?php endif; ?>
  </label>
  <label class="field">
    <span>Razon social cliente</span>
    <input name="customer_name" required <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['customer_name'] ?: $invoiceValues['empresa_name'] ?? '') ?>">
  </label>
  <label class="field">
    <span>NIF/CIF/NIE cliente</span>
    <input name="customer_tax_id" required <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['customer_tax_id']) ?>">
  </label>
  <label class="field form-full">
    <span>Direccion fiscal cliente</span>
    <input name="customer_address" required <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['customer_address']) ?>">
  </label>
  <label class="field">
    <span>CP cliente</span>
    <input name="customer_postal_code" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['customer_postal_code']) ?>">
  </label>
  <label class="field">
    <span>Localidad cliente</span>
    <input name="customer_city" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['customer_city']) ?>">
  </label>
  <label class="field">
    <span>Provincia cliente</span>
    <input name="customer_province" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['customer_province']) ?>">
  </label>
  <label class="field">
    <span>Pais cliente</span>
    <input name="customer_country" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['customer_country']) ?>">
  </label>
  <label class="field">
    <span>Email cliente</span>
    <input name="customer_email" type="email" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['customer_email'] ?: $invoiceValues['contact_email'] ?? '') ?>">
  </label>
  <label class="field">
    <span>Teléfono cliente</span>
    <input name="customer_phone" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['customer_phone']) ?>">
  </label>

  <div class="form-full platform-form-divider">
    <strong>Datos de factura</strong>
    <span>El número es solo una sugerencia hasta emitir.</span>
  </div>
  <label class="field">
    <span>Tipo</span>
    <select name="invoice_type" <?= $isIssued ? 'disabled' : '' ?>>
      <?php foreach ($invoiceTypeOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= ($invoiceValues['invoice_type'] ?? 'ORDINARY') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($isIssued): ?><input type="hidden" name="invoice_type" value="<?= e($invoiceValues['invoice_type']) ?>"><?php endif; ?>
  </label>
  <label class="field">
    <span>Serie</span>
    <input name="invoice_series" required <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['invoice_series']) ?>">
  </label>
  <label class="field">
    <span>Número</span>
    <input name="invoice_number" readonly value="<?= e($invoiceValues['invoice_number'] ? (string) $invoiceValues['invoice_number'] : (string) ($nextInvoiceNumber ?? PlatformInvoiceRepository::nextInvoiceNumber())) ?>">
  </label>
  <label class="field">
    <span>Estado factura</span>
    <input readonly value="<?= e(platform_invoice_status_label($invoiceValues['invoice_status'] ?? 'DRAFT')) ?>">
  </label>
  <label class="field">
    <span>Estado cobro</span>
    <input readonly value="<?= e(platform_invoice_status_label($invoiceValues['collection_status'] ?? 'PENDING')) ?>">
  </label>
  <label class="field">
    <span>Fecha expedicion</span>
    <input name="issued_at" type="date" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['issued_at'] ? date('Y-m-d', strtotime($invoiceValues['issued_at'])) : date('Y-m-d')) ?>">
  </label>
  <label class="field">
    <span>Fecha operación</span>
    <input name="operation_at" type="date" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['operation_at'] ? date('Y-m-d', strtotime($invoiceValues['operation_at'])) : '') ?>">
  </label>
  <label class="field">
    <span>Periodo inicio</span>
    <input name="period_start_at" type="date" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['period_start_at'] ? date('Y-m-d', strtotime($invoiceValues['period_start_at'])) : '') ?>">
  </label>
  <label class="field">
    <span>Periodo fin</span>
    <input name="period_end_at" type="date" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['period_end_at'] ? date('Y-m-d', strtotime($invoiceValues['period_end_at'])) : '') ?>">
  </label>
  <label class="field">
    <span>Vencimiento</span>
    <input name="due_at" type="date" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['due_at'] ? date('Y-m-d', strtotime($invoiceValues['due_at'])) : '') ?>">
  </label>
  <label class="field">
    <span>Moneda</span>
    <input name="currency" maxlength="3" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($invoiceValues['currency'] ?? 'EUR') ?>">
  </label>
  <label class="field">
    <span>Pago asociado</span>
    <select name="payment_id" <?= $isIssued ? 'disabled' : '' ?>>
      <option value="">Sin pago asociado</option>
      <?php foreach (($payments ?? []) as $paymentOption): ?>
        <option value="<?= e($paymentOption['id']) ?>" <?= ($invoiceValues['payment_id'] ?? '') === $paymentOption['id'] ? 'selected' : '' ?>>
          <?= e($paymentOption['empresa_name'] . ' - ' . $paymentOption['concept'] . ' - ' . money_amount($paymentOption['amount'])) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($isIssued): ?><input type="hidden" name="payment_id" value="<?= e($invoiceValues['payment_id']) ?>"><?php endif; ?>
  </label>

  <div class="form-full platform-form-divider">
    <strong>Lineas</strong>
    <span>Los importes se recalculan también en servidor.</span>
  </div>
  <div class="form-full invoice-lines" data-invoice-lines>
    <?php foreach (array_values($invoiceItems) as $index => $item): ?>
      <div class="invoice-line" data-invoice-line>
        <input type="hidden" name="items[<?= $index ?>][sort_order]" value="<?= $index + 1 ?>" data-line-order>
        <label class="field invoice-line-description">
          <span>Descripción</span>
          <input name="items[<?= $index ?>][description]" required <?= $isIssued ? 'readonly' : '' ?> value="<?= e($item['description']) ?>">
        </label>
        <label class="field">
          <span>Cantidad</span>
          <input name="items[<?= $index ?>][quantity]" inputmode="decimal" <?= $isIssued ? 'readonly' : '' ?> value="<?= e((string) $item['quantity']) ?>" data-line-quantity>
        </label>
        <label class="field">
          <span>Unidad</span>
          <input name="items[<?= $index ?>][unit]" <?= $isIssued ? 'readonly' : '' ?> value="<?= e($item['unit'] ?? 'ud') ?>">
        </label>
        <label class="field">
          <span>Precio sin IVA</span>
          <input name="items[<?= $index ?>][unit_price]" inputmode="decimal" <?= $isIssued ? 'readonly' : '' ?> value="<?= e((string) $item['unit_price']) ?>" data-line-price>
        </label>
        <label class="field">
          <span>Dto tipo</span>
          <select name="items[<?= $index ?>][discount_type]" <?= $isIssued ? 'disabled' : '' ?> data-line-discount-type>
            <option value="PERCENT" <?= ($item['discount_type'] ?? 'PERCENT') === 'PERCENT' ? 'selected' : '' ?>>%</option>
            <option value="FIXED" <?= ($item['discount_type'] ?? '') === 'FIXED' ? 'selected' : '' ?>>EUR</option>
          </select>
        </label>
        <label class="field">
          <span>Dto</span>
          <input name="items[<?= $index ?>][discount_value]" inputmode="decimal" <?= $isIssued ? 'readonly' : '' ?> value="<?= e((string) ($item['discount_value'] ?? '0.00')) ?>" data-line-discount>
        </label>
        <label class="field">
          <span>IVA %</span>
          <input name="items[<?= $index ?>][tax_rate]" inputmode="decimal" <?= $isIssued ? 'readonly' : '' ?> value="<?= e((string) $item['tax_rate']) ?>" data-line-tax>
        </label>
        <output class="invoice-line-total" data-line-total><?= e(money_amount($item['total_amount'] ?? 0)) ?></output>
        <?php if (!$isIssued): ?>
          <button class="note-delete-button invoice-line-delete" type="button" data-remove-invoice-line aria-label="Eliminar linea" title="Eliminar linea">
            <svg viewBox="0 0 24 24"><path d="M9 4h6l1 2h4v2H4V6h4l1-2Zm1 6h2v8h-2v-8Zm4 0h2v8h-2v-8ZM7 10h2l1 10h4l1-10h2l-1.2 12H8.2L7 10Z"/></svg>
          </button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!$isIssued): ?>
    <button class="secondary-action form-full" type="button" data-add-invoice-line>Añadir linea</button>
  <?php endif; ?>

  <div class="form-full platform-form-divider">
    <strong>Totales</strong>
  </div>
  <div class="invoice-totals form-full">
    <span>Subtotal <strong data-invoice-subtotal><?= e(money_amount($invoiceValues['subtotal_amount'])) ?></strong></span>
    <span>Descuento <strong data-invoice-discount><?= e(money_amount($invoiceValues['discount_amount'])) ?></strong></span>
    <span>Base <strong data-invoice-base><?= e(money_amount($invoiceValues['taxable_base'])) ?></strong></span>
    <span>IVA <strong data-invoice-tax><?= e(money_amount($invoiceValues['tax_amount'])) ?></strong></span>
    <span>Total <strong data-invoice-total><?= e(money_amount($invoiceValues['total_amount'])) ?></strong></span>
    <span>Pagado <strong><?= e(money_amount($invoiceValues['paid_amount'] ?? 0)) ?></strong></span>
    <span>Pendiente <strong><?= e(money_amount($invoiceValues['pending_amount'] ?? $invoiceValues['total_amount'])) ?></strong></span>
  </div>

  <div class="form-full platform-form-divider">
    <strong>Pago y fiscalidad</strong>
  </div>
  <label class="field">
    <span>Forma de pago</span>
    <select name="payment_method">
      <?php foreach ($paymentMethods as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $invoiceValues['payment_method'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field">
    <span>Tratamiento fiscal</span>
    <select name="fiscal_treatment" <?= $isIssued ? 'disabled' : '' ?>>
      <?php foreach ($fiscalOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= ($invoiceValues['fiscal_treatment'] ?? 'VAT_SUBJECT') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($isIssued): ?><input type="hidden" name="fiscal_treatment" value="<?= e($invoiceValues['fiscal_treatment']) ?>"><?php endif; ?>
  </label>
  <label class="field form-full">
    <span>Mencion fiscal</span>
    <textarea name="fiscal_note" rows="2" <?= $isIssued ? 'readonly' : '' ?>><?= e($invoiceValues['fiscal_note']) ?></textarea>
  </label>
  <label class="field form-full">
    <span>Observaciones visibles</span>
    <textarea name="public_notes" rows="3" <?= $isIssued ? 'readonly' : '' ?>><?= e($invoiceValues['public_notes']) ?></textarea>
  </label>
  <label class="field form-full">
    <span>Notas internas</span>
    <textarea name="notes" rows="3"><?= e($invoiceValues['notes']) ?></textarea>
  </label>

  <div class="form-actions form-full">
    <button class="secondary-action" type="button" data-close-modal>Cancelar</button>
    <?php if (!$isIssued): ?>
      <button class="secondary-action" type="submit">Guardar borrador</button>
    <?php endif; ?>
    <?php if ($isEditingInvoice): ?>
      <a class="secondary-action" href="index.php?route=platform-invoice&id=<?= urlencode($invoiceValues['id']) ?>&preview=1" target="_blank" rel="noopener">Vista previa</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($isEditingInvoice && !$isIssued): ?>
  <form class="platform-subscription-actions" method="post" data-confirm-message="Se asignara el siguiente número correlativo y la factura quedara bloqueada." data-confirm-action-label="Emitir factura">
    <input type="hidden" name="action" value="<?= $clientInvoiceMode ? 'issue_client_invoice' : 'issue_platform_invoice' ?>">
    <input type="hidden" name="id" value="<?= e($invoiceValues['id']) ?>">
    <button class="primary-action" type="submit">Emitir factura</button>
  </form>
<?php endif; ?>

<?php if ($isEditingInvoice && $isIssued): ?>
  <div class="form-full platform-form-divider">
    <strong>Pagos registrados</strong>
  </div>
  <div class="invoice-payment-list">
    <?php foreach ($invoicePayments as $paymentRow): ?>
      <p><?= e(format_date_short($paymentRow['paid_at'])) ?> · <?= e(money_amount($paymentRow['amount'])) ?> · <?= e(payment_method_label($paymentRow['payment_method'])) ?><?= $paymentRow['reference'] ? ' · ' . e($paymentRow['reference']) : '' ?></p>
    <?php endforeach; ?>
    <?php if (!$invoicePayments): ?><p>Sin pagos registrados.</p><?php endif; ?>
  </div>
  <form class="empresa-form" method="post">
    <input type="hidden" name="action" value="<?= $clientInvoiceMode ? 'add_client_invoice_payment' : 'add_platform_invoice_payment' ?>">
    <input type="hidden" name="invoice_id" value="<?= e($invoiceValues['id']) ?>">
    <label class="field">
      <span>Fecha pago</span>
      <input name="paid_at" type="date" value="<?= e(date('Y-m-d')) ?>">
    </label>
    <label class="field">
      <span>Importe</span>
      <input name="amount" inputmode="decimal" value="<?= e((string) ($invoiceValues['pending_amount'] ?? '0.00')) ?>">
    </label>
    <label class="field">
      <span>Forma de pago</span>
      <select name="payment_method">
        <?php foreach ($paymentMethods as $value => $label): ?>
          <option value="<?= e($value) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>Referencia</span>
      <input name="reference" placeholder="Stripe, transferencia, recibo...">
    </label>
    <label class="field form-full">
      <span>Notas</span>
      <textarea name="notes" rows="2"></textarea>
    </label>
    <div class="form-actions form-full">
      <button class="primary-action" type="submit">Registrar pago</button>
    </div>
  </form>
<?php endif; ?>
