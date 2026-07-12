<?php
$issuedAt = $invoice['issued_at'] ?: date('Y-m-d');
$dueAt = $invoice['due_at'] ?: $issuedAt;
$planLabel = $invoice['plan'] ? strtoupper((string) $invoice['plan']) : 'CRM';
$items = $invoice['items'] ?? [];
$payments = $invoice['payments'] ?? [];
$invoiceCode = $invoice['invoice_code'] ?: ($invoice['invoice_series'] . '/BORRADOR');
$taxBreakdown = json_decode((string) ($invoice['tax_breakdown'] ?? '[]'), true);
$taxBreakdown = is_array($taxBreakdown) ? $taxBreakdown : [];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Factura <?= e($invoiceCode) ?> - Membora CRM</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <style>
    :root {
      --ink: #102033;
      --muted: #64748b;
      --line: #d9e2ef;
      --primary: #004bf2;
      --soft: #f5f8fd;
    }
    * { box-sizing: border-box; }
    body {
      background: #e9eef7;
      color: var(--ink);
      font-family: Arial, Helvetica, sans-serif;
      margin: 0;
      padding: 28px;
    }
    .invoice-actions {
      align-items: center;
      display: flex;
      gap: 12px;
      justify-content: center;
      margin-bottom: 22px;
    }
    .invoice-actions button,
    .invoice-actions a {
      border-radius: 10px;
      font-weight: 800;
      min-height: 42px;
      padding: 0 16px;
      text-decoration: none;
    }
    .invoice-actions button {
      background: var(--primary);
      border: 1px solid var(--primary);
      color: #fff;
      cursor: pointer;
    }
    .invoice-actions a {
      align-items: center;
      background: #fff;
      border: 1px solid var(--line);
      color: var(--ink);
      display: inline-flex;
    }
    .invoice {
      background: #fff;
      box-shadow: 0 24px 70px rgba(15, 23, 42, 0.16);
      margin: 0 auto;
      max-width: 920px;
      min-height: 1180px;
      padding: 52px;
    }
    .invoice-header {
      align-items: flex-start;
      border-bottom: 2px solid var(--ink);
      display: flex;
      justify-content: space-between;
      padding-bottom: 30px;
    }
    .brand h1 {
      font-size: 30px;
      letter-spacing: 0;
      margin: 0 0 8px;
    }
    .brand p,
    .invoice-meta p,
    .party p,
    .notes p {
      color: var(--muted);
      line-height: 1.55;
      margin: 0;
    }
    .invoice-meta { text-align: right; }
    .invoice-meta strong {
      display: block;
      font-size: 34px;
      margin-bottom: 10px;
      text-transform: uppercase;
    }
    .summary-grid {
      display: grid;
      gap: 18px;
      grid-template-columns: 1fr 1fr;
      margin: 34px 0;
    }
    .party {
      background: var(--soft);
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 20px;
    }
    .party span,
    .totals span,
    .notes span {
      color: var(--muted);
      display: block;
      font-size: 12px;
      font-weight: 800;
      margin-bottom: 8px;
      text-transform: uppercase;
    }
    .party h2 {
      font-size: 20px;
      margin: 0 0 8px;
    }
    table {
      border-collapse: collapse;
      width: 100%;
    }
    th {
      background: var(--ink);
      color: #fff;
      font-size: 12px;
      padding: 14px;
      text-align: left;
      text-transform: uppercase;
    }
    td {
      border-bottom: 1px solid var(--line);
      padding: 16px 14px;
      vertical-align: top;
    }
    .amount {
      text-align: right;
      white-space: nowrap;
    }
    .totals {
      display: grid;
      justify-content: end;
      margin-top: 28px;
    }
    .totals table { min-width: 320px; }
    .totals td { padding: 10px 0 10px 28px; }
    .totals tr:last-child td {
      border-top: 2px solid var(--ink);
      color: var(--ink);
      font-size: 20px;
      font-weight: 900;
    }
    .notes {
      border-top: 1px solid var(--line);
      margin-top: 44px;
      padding-top: 22px;
    }
    .footer {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.5;
      margin-top: 42px;
    }
    @media print {
      body { background: #fff; padding: 0; }
      .invoice-actions { display: none; }
      .invoice {
        box-shadow: none;
        margin: 0;
        max-width: none;
        min-height: auto;
        padding: 32px;
      }
    }
  </style>
</head>
<body>
  <div class="invoice-actions">
    <button type="button" class="js-print-invoice">Imprimir / guardar PDF</button>
    <a href="index.php?route=platform-invoices">Volver a facturas</a>
  </div>

  <article class="invoice">
    <header class="invoice-header">
      <div class="brand">
        <h1>Membora CRM</h1>
        <p>SaaS para gimnasios, estudios deportivos y centros fitness.</p>
        <p>contacto@josehurtado.dev</p>
      </div>
      <div class="invoice-meta">
        <strong>Factura</strong>
        <p><b><?= e($invoiceCode) ?></b></p>
        <p><?= e(platform_invoice_status_label($invoice['invoice_status'] ?? 'DRAFT')) ?></p>
        <p>Emision: <?= e(format_date_short($issuedAt)) ?></p>
        <p>Vencimiento: <?= e(format_date_short($dueAt)) ?></p>
      </div>
    </header>

    <section class="summary-grid">
      <div class="party">
        <span>Emisor</span>
        <h2><?= e($invoice['issuer_name'] ?: 'Membora CRM') ?></h2>
        <p><?= e($invoice['issuer_tax_id'] ?: 'NIF/CIF pendiente') ?></p>
        <p><?= e($invoice['issuer_address'] ?: 'Direccion fiscal pendiente') ?></p>
        <p><?= e(trim(($invoice['issuer_postal_code'] ?? '') . ' ' . ($invoice['issuer_city'] ?? '') . ' ' . ($invoice['issuer_province'] ?? ''))) ?></p>
        <p><?= e($invoice['issuer_country'] ?: 'España') ?></p>
      </div>
      <div class="party">
        <span>Cliente</span>
        <h2><?= e($invoice['customer_name'] ?: $invoice['empresa_name']) ?></h2>
        <p><?= e($invoice['customer_tax_id'] ?: 'NIF/CIF pendiente') ?></p>
        <p><?= e($invoice['customer_address'] ?: 'Direccion fiscal pendiente') ?></p>
        <p><?= e(trim(($invoice['customer_postal_code'] ?? '') . ' ' . ($invoice['customer_city'] ?? '') . ' ' . ($invoice['customer_province'] ?? ''))) ?></p>
        <p><?= e($invoice['customer_country'] ?: 'España') ?></p>
        <p><?= e($invoice['customer_email'] ?: $invoice['contact_email'] ?: 'Sin email de contacto') ?></p>
        <p>Plan: <?= e($planLabel) ?></p>
        <p>Cobro: <?= e(platform_invoice_status_label($invoice['collection_status'] ?? 'PENDING')) ?></p>
      </div>
    </section>

    <table aria-label="Detalle de factura">
      <thead>
        <tr>
          <th>Concepto</th>
          <th>Cant.</th>
          <th>Precio</th>
          <th>Dto.</th>
          <th>IVA</th>
          <th class="amount">Base</th>
          <th class="amount">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><strong><?= e($item['description']) ?></strong></td>
            <td><?= e(number_format((float) $item['quantity'], 3, ',', '.')) ?> <?= e($item['unit']) ?></td>
            <td class="amount"><?= e(money_amount($item['unit_price'])) ?></td>
            <td class="amount"><?= e(money_amount($item['discount_amount'])) ?></td>
            <td><?= e(number_format((float) $item['tax_rate'], 2, ',', '.')) ?>%</td>
            <td class="amount"><?= e(money_amount($item['taxable_base'])) ?></td>
            <td class="amount"><?= e(money_amount($item['total_amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <section class="totals" aria-label="Totales">
      <table>
        <tr>
          <td>Subtotal</td>
          <td class="amount"><?= e(money_amount($invoice['subtotal_amount'])) ?></td>
        </tr>
        <tr>
          <td>Descuento</td>
          <td class="amount"><?= e(money_amount($invoice['discount_amount'])) ?></td>
        </tr>
        <tr>
          <td>Base imponible</td>
          <td class="amount"><?= e(money_amount($invoice['taxable_base'])) ?></td>
        </tr>
        <?php foreach ($taxBreakdown as $taxRow): ?>
          <tr>
            <td>IVA <?= e(number_format((float) ($taxRow['rate'] ?? 0), 2, ',', '.')) ?>%</td>
            <td class="amount"><?= e(money_amount($taxRow['tax'] ?? 0)) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td>Total factura</td>
          <td class="amount"><?= e(money_amount($invoice['total_amount'])) ?></td>
        </tr>
        <tr>
          <td>Pagado</td>
          <td class="amount"><?= e(money_amount($invoice['paid_amount'] ?? 0)) ?></td>
        </tr>
        <tr>
          <td>Pendiente</td>
          <td class="amount"><?= e(money_amount($invoice['pending_amount'] ?? 0)) ?></td>
        </tr>
      </table>
    </section>

    <section class="notes">
      <span>Observaciones</span>
      <p>Forma de pago: <?= e(payment_method_label($invoice['payment_method'])) ?>.</p>
      <?php if (!empty($invoice['fiscal_note'])): ?><p><?= e($invoice['fiscal_note']) ?></p><?php endif; ?>
      <?php if (!empty($invoice['public_notes'])): ?><p><?= e($invoice['public_notes']) ?></p><?php endif; ?>
      <?php if ($payments): ?>
        <p>Pagos: <?= e(implode(' · ', array_map(static fn (array $payment): string => format_date_short($payment['paid_at']) . ' ' . money_amount($payment['amount']), $payments))) ?></p>
      <?php endif; ?>
    </section>

    <p class="footer">Factura generada por Membora CRM. Conserva este documento junto con el justificante de pago correspondiente.</p>
  </article>
  <script src="assets/app.js"></script>
</body>
</html>
