<?php
$filters = $filters ?? ['q' => '', 'status' => ''];
$invoices = $invoices ?? [];
$empresas = $empresas ?? [];
$payments = $payments ?? [];
$metrics = $metrics ?? ['issued_month' => 0, 'pending_amount' => 0, 'paid_month' => 0, 'overdue' => 0];
$statusOptions = [
    '' => 'Todas',
    'DRAFT' => 'Borrador',
    'ISSUED' => 'Emitida',
    'RECTIFIED' => 'Rectificada',
    'PENDING' => 'Pendiente',
    'PARTIAL' => 'Parcial',
    'PAID' => 'Pagada',
    'OVERDUE' => 'Vencida',
    'REFUNDED' => 'Reembolsada',
];
$clientInvoiceMode = !empty($clientInvoiceMode);
$invoiceListRoute = $clientInvoiceMode ? 'billing' : 'platform-invoices';
$invoiceDocumentRoute = $clientInvoiceMode ? 'client-invoice' : 'platform-invoice';
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2><?= $clientInvoiceMode ? 'Facturación' : 'Facturas de plataforma' ?></h2>
    <p><?= $clientInvoiceMode ? 'Facturas del gimnasio a sus clientes, con serie, impuestos y estado de cobro.' : 'Facturas emitidas por Membora a gimnasios cliente, con serie, número, IVA y estado de cobro.' ?></p>
  </div>
  <button class="primary-action" type="button" data-open-modal="invoice-create-modal">Nueva factura</button>
</div>

<section class="dashboard-metrics">
  <article class="dashboard-metric dashboard-metric--primary">
    <span>Emitido este mes</span>
    <strong><?= e(money_amount($metrics['issued_month'])) ?></strong>
    <small>Facturas no canceladas</small>
  </article>
  <article class="dashboard-metric dashboard-metric--orange">
    <span>Pendiente</span>
    <strong><?= e(money_amount($metrics['pending_amount'])) ?></strong>
    <small>Emitidas, enviadas o vencidas</small>
  </article>
  <article class="dashboard-metric dashboard-metric--green">
    <span>Cobrado este mes</span>
    <strong><?= e(money_amount($metrics['paid_month'])) ?></strong>
    <small>Facturas marcadas cobradas</small>
  </article>
  <article class="dashboard-metric dashboard-metric--danger">
    <span>Vencidas</span>
    <strong><?= (int) $metrics['overdue'] ?></strong>
    <small>Requieren seguimiento</small>
  </article>
</section>

<form class="lead-toolbar platform-toolbar platform-toolbar--payments" method="get" action="index.php" data-auto-filter-form>
  <input type="hidden" name="route" value="<?= e($invoiceListRoute) ?>">
  <label class="field platform-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Número, empresa, concepto o notas" data-auto-submit-input>
  </label>
  <label class="field platform-filter-field">
    <span>Estado</span>
    <select name="status" data-auto-submit-input>
      <?php foreach ($statusOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="primary-action" type="submit">Filtrar</button>
</form>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Listado de facturas</h3>
      <span><?= count($invoices) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table platform-table platform-table--payments">
      <thead>
        <tr>
          <th>Factura</th>
          <th>Cliente</th>
          <th>Cliente</th>
          <th>Fecha</th>
          <th>Vencimiento</th>
          <th>Base</th>
          <th>IVA</th>
          <th>Total</th>
          <th>Factura</th>
          <th>Cobro</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $invoice): ?>
          <?php
            $invoiceStatusClass = strtolower((string) ($invoice['invoice_status'] ?? 'DRAFT'));
            $collectionStatusClass = strtolower((string) ($invoice['collection_status'] ?? 'PENDING'));
            $displayNumber = $invoice['invoice_code'] ?: ($invoice['invoice_series'] . '/' . str_pad((string) ($nextInvoiceNumber ?? 1), 4, '0', STR_PAD_LEFT) . ' sugerido');
          ?>
          <tr class="lead-data-row clickable-row" tabindex="0" data-open-modal="invoice-edit-<?= e($invoice['id']) ?>">
            <td><strong><?= e($displayNumber) ?></strong></td>
            <td>
              <?= e($invoice['customer_name'] ?: $invoice['empresa_name']) ?>
              <span class="table-subtext"><?= e($invoice['customer_tax_id'] ?: 'Sin NIF/CIF') ?></span>
            </td>
            <td><?= e(format_date_short($invoice['issued_at'])) ?></td>
            <td><?= e(format_date_short($invoice['due_at'])) ?></td>
            <td><?= e(money_amount($invoice['taxable_base'])) ?></td>
            <td><?= e(money_amount($invoice['tax_amount'])) ?></td>
            <td><strong><?= e(money_amount($invoice['total_amount'])) ?></strong></td>
            <td><span class="status-badge status-badge--<?= e($invoiceStatusClass) ?>"><?= e(platform_invoice_status_label($invoice['invoice_status'] ?? 'DRAFT')) ?></span></td>
            <td><span class="status-badge status-badge--<?= e($collectionStatusClass) ?>"><?= e(platform_invoice_status_label($invoice['collection_status'] ?? 'PENDING')) ?></span></td>
            <td>
              <div class="platform-row-actions">
                <a class="support-invoice-action" href="index.php?route=<?= e($invoiceDocumentRoute) ?>&id=<?= urlencode($invoice['id']) ?>" target="_blank" rel="noopener" aria-label="Ver factura <?= e($displayNumber) ?>">
                  <svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v15H6V2Zm8 1.8V8h4.2L14 3.8ZM8 11h8v2H8v-2Zm0 4h8v2H8v-2Zm0 4h5v1H8v-1Z"/></svg>
                  <span><?= ($invoice['invoice_status'] ?? 'DRAFT') === 'DRAFT' ? 'Preview' : 'PDF' ?></span>
                </a>
                <button class="support-edit-action" type="button" data-open-modal="invoice-edit-<?= e($invoice['id']) ?>" aria-label="Editar factura <?= e($displayNumber) ?>">
                  <svg viewBox="0 0 24 24"><path d="M4 17.3V20h2.7L17.9 8.8l-2.7-2.7L4 17.3Zm15.8-10.6a1 1 0 0 0 0-1.4l-1.1-1.1a1 1 0 0 0-1.4 0l-.9.9 2.7 2.7.7-.8Z"/></svg>
                  <span><?= ($invoice['invoice_status'] ?? 'DRAFT') === 'DRAFT' ? 'Editar' : 'Ver' ?></span>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$invoices): ?>
          <tr><td colspan="10" class="empty-state">No hay facturas que coincidan con los filtros actuales.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php unset($invoice); ?>
<dialog class="modal-card empresa-modal invoice-modal" id="invoice-create-modal">
  <header>
    <div>
      <h2>Nueva factura</h2>
      <p>Emite una factura manual con serie y número sugerido.</p>
    </div>
    <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
  </header>
  <?php require __DIR__ . '/partials/platform-invoice-form.php'; ?>
</dialog>

<?php foreach ($invoices as $invoice): ?>
  <dialog class="modal-card empresa-modal invoice-modal" id="invoice-edit-<?= e($invoice['id']) ?>">
    <header>
      <div>
        <h2><?= e($invoice['invoice_code'] ?: 'Borrador') ?></h2>
        <p><?= e(($invoice['customer_name'] ?: $invoice['empresa_name']) . ' - ' . platform_invoice_status_label($invoice['invoice_status'] ?? 'DRAFT')) ?></p>
      </div>
      <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
    </header>
    <?php require __DIR__ . '/partials/platform-invoice-form.php'; ?>
  </dialog>
<?php endforeach; ?>
