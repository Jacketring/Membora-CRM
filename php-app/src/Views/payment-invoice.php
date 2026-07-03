<?php
$tenantName = Auth::user()['tenant_name'] ?? 'Gimnasio';
$memberName = trim((string) ($payment['first_name'] ?? '') . ' ' . (string) ($payment['last_name'] ?? ''));
$invoiceNumber = 'FAC-' . date('Y', strtotime((string) ($payment['created_at'] ?? 'now'))) . '-' . strtoupper(substr((string) $payment['id'], -8));
$issuedAt = $payment['paid_at'] ?: ($payment['created_at'] ?? date('Y-m-d'));
$dueAt = $payment['due_at'] ?: $issuedAt;
$baseAmount = (float) ($payment['amount'] ?? 0);
$taxRate = 0.21;
$taxAmount = round($baseAmount * $taxRate, 2);
$totalAmount = round($baseAmount + $taxAmount, 2);
$planName = $payment['plan_name'] ?: 'Membresia';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Factura <?= e($invoiceNumber) ?> - <?= e($tenantName) ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <style>
    :root {
      --ink: #102033;
      --muted: #64748b;
      --line: #d9e2ef;
      --primary: #0754d6;
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
      margin: 0 0 8px;
    }
    .brand p,
    .invoice-meta p,
    .party p,
    .notes p,
    .footer {
      color: var(--muted);
      line-height: 1.55;
      margin: 0;
    }
    .invoice-meta {
      text-align: right;
    }
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
    .totals span {
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
    .totals table {
      min-width: 320px;
    }
    .totals td {
      padding: 10px 0 10px 28px;
    }
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
      font-size: 12px;
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
    <button type="button" onclick="window.print()">Imprimir / guardar PDF</button>
    <a href="index.php?route=payments">Volver a pagos</a>
  </div>

  <article class="invoice">
    <header class="invoice-header">
      <div class="brand">
        <h1><?= e($tenantName) ?></h1>
        <p>Centro deportivo y gestion de membresias.</p>
        <p>Factura emitida desde Membora CRM.</p>
      </div>
      <div class="invoice-meta">
        <strong>Factura</strong>
        <p><b><?= e($invoiceNumber) ?></b></p>
        <p>Emision: <?= e(format_date_short($issuedAt)) ?></p>
        <p>Vencimiento: <?= e(format_date_short($dueAt)) ?></p>
      </div>
    </header>

    <section class="summary-grid">
      <div class="party">
        <span>Emisor</span>
        <h2><?= e($tenantName) ?></h2>
        <p>Actividad: servicios deportivos, clases y membresias.</p>
        <p>Datos fiscales pendientes de completar por el centro.</p>
      </div>
      <div class="party">
        <span>Cliente</span>
        <h2><?= e($memberName ?: 'Socio') ?></h2>
        <p><?= e($payment['email'] ?: 'Sin email de contacto') ?></p>
        <p><?= e($payment['phone'] ?: 'Sin telefono') ?></p>
        <p>Estado del pago: <?= e(platform_payment_status_label($payment['status'])) ?></p>
      </div>
    </section>

    <table aria-label="Detalle de factura">
      <thead>
        <tr>
          <th>Concepto</th>
          <th>Periodo / detalle</th>
          <th class="amount">Base</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong><?= e($planName) ?></strong></td>
          <td>
            Cuota o renovacion de membresia.
            <?php if (!empty($payment['subscription_starts_at']) || !empty($payment['subscription_ends_at'])): ?>
              <br>Periodo: <?= e(format_date_short($payment['subscription_starts_at'])) ?> - <?= e(format_date_short($payment['subscription_ends_at'])) ?>
            <?php endif; ?>
            <?php if (!empty($payment['notes'])): ?>
              <br><?= e($payment['notes']) ?>
            <?php endif; ?>
          </td>
          <td class="amount"><?= e(money_amount($baseAmount)) ?></td>
        </tr>
      </tbody>
    </table>

    <section class="totals" aria-label="Totales">
      <table>
        <tr>
          <td>Base imponible</td>
          <td class="amount"><?= e(money_amount($baseAmount)) ?></td>
        </tr>
        <tr>
          <td>IVA 21%</td>
          <td class="amount"><?= e(money_amount($taxAmount)) ?></td>
        </tr>
        <tr>
          <td>Total factura</td>
          <td class="amount"><?= e(money_amount($totalAmount)) ?></td>
        </tr>
      </table>
    </section>

    <section class="notes">
      <p>Forma de pago: <?= e(payment_method_label($payment['payment_method'])) ?>.</p>
      <p>Este documento sirve como justificante del pago registrado en el CRM del centro.</p>
    </section>

    <p class="footer">
      Factura generada automaticamente por Membora CRM. Revisa los datos fiscales antes de usarla como documento definitivo de facturacion.
    </p>
  </article>
</body>
</html>
