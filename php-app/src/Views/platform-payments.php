<?php
$filters = $filters ?? ['q' => '', 'status' => ''];
$payments = $payments ?? [];
$empresas = $empresas ?? [];
$metrics = $metrics ?? ['paid_month' => 0, 'pending_amount' => 0, 'overdue' => 0, 'due_week' => 0];
$statusOptions = [
    '' => 'Todos',
    'PENDING' => 'Pendiente',
    'PAID' => 'Pagado',
    'OVERDUE' => 'Vencido',
    'CANCELLED' => 'Cancelado',
];
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Pagos CRM</h2>
    <p>Controla cobros de empresas cliente, pagos vencidos y facturacion mensual del SaaS.</p>
  </div>
  <button class="primary-action" type="button" data-open-modal="payment-create-modal">Nuevo pago</button>
</div>

<section class="dashboard-metrics">
  <article class="dashboard-metric dashboard-metric--green">
    <span>Cobrado este mes</span>
    <strong><?= e(money_amount($metrics['paid_month'])) ?></strong>
    <small>Pagos marcados como pagados</small>
  </article>
  <article class="dashboard-metric dashboard-metric--orange">
    <span>Pendiente</span>
    <strong><?= e(money_amount($metrics['pending_amount'])) ?></strong>
    <small>Importe por cobrar</small>
  </article>
  <article class="dashboard-metric dashboard-metric--danger">
    <span>Vencidos</span>
    <strong><?= (int) $metrics['overdue'] ?></strong>
    <small>Requieren seguimiento</small>
  </article>
  <article class="dashboard-metric dashboard-metric--primary">
    <span>Proximos 7 dias</span>
    <strong><?= (int) $metrics['due_week'] ?></strong>
    <small>Cobros programados</small>
  </article>
</section>

<form class="lead-toolbar platform-toolbar platform-toolbar--payments" method="get" action="index.php" data-auto-filter-form>
  <input type="hidden" name="route" value="platform-payments">
  <label class="field platform-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Empresa, concepto o notas" data-auto-submit-input>
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
      <h3>Listado de pagos</h3>
      <span><?= count($payments) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table platform-table platform-table--payments">
      <thead>
        <tr>
          <th>Concepto</th>
          <th>Empresa</th>
          <th>Importe</th>
          <th>Estado</th>
          <th>Vencimiento</th>
          <th>Pagado</th>
          <th>Notas</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $payment): ?>
          <?php $statusClass = strtolower((string) $payment['status']); ?>
          <tr class="lead-data-row clickable-row" tabindex="0" data-open-modal="payment-edit-<?= e($payment['id']) ?>">
            <td><strong><?= e($payment['concept']) ?></strong></td>
            <td>
              <?= e($payment['empresa_name']) ?>
              <span class="table-subtext"><?= e($payment['contact_email'] ?: 'Sin contacto') ?></span>
            </td>
            <td><?= e(money_amount($payment['amount'])) ?></td>
            <td><span class="status-badge status-badge--<?= e($statusClass) ?>"><?= e(platform_payment_status_label($payment['status'])) ?></span></td>
            <td><?= e(format_date_short($payment['due_at'])) ?></td>
            <td><?= e(format_date_short($payment['paid_at'])) ?></td>
            <td><?= e($payment['notes'] ? substr($payment['notes'], 0, 70) . (strlen($payment['notes']) > 70 ? '...' : '') : 'Sin notas') ?></td>
            <td>
              <div class="platform-row-actions">
                <a class="support-invoice-action" href="index.php?route=platform-payment-invoice&id=<?= urlencode($payment['id']) ?>" target="_blank" rel="noopener" aria-label="Generar factura del pago <?= e($payment['concept']) ?>">
                  <svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v15H6V2Zm8 1.8V8h4.2L14 3.8ZM8 11h8v2H8v-2Zm0 4h8v2H8v-2Zm0 4h5v1H8v-1Z"/></svg>
                  <span>Factura PDF</span>
                </a>
                <button class="support-edit-action" type="button" data-open-modal="payment-edit-<?= e($payment['id']) ?>" aria-label="Editar pago <?= e($payment['concept']) ?>">
                  <svg viewBox="0 0 24 24"><path d="M4 17.3V20h2.7L17.9 8.8l-2.7-2.7L4 17.3Zm15.8-10.6a1 1 0 0 0 0-1.4l-1.1-1.1a1 1 0 0 0-1.4 0l-.9.9 2.7 2.7.7-.8Z"/></svg>
                  <span>Editar</span>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?>
          <tr><td colspan="8" class="empty-state">No hay pagos que coincidan con los filtros actuales.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php unset($payment); ?>
<dialog class="modal-card empresa-modal" id="payment-create-modal">
  <header>
    <div>
      <h2>Nuevo pago</h2>
      <p>Registra un cobro manual de una empresa cliente.</p>
    </div>
    <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
  </header>
  <?php require __DIR__ . '/partials/platform-payment-form.php'; ?>
</dialog>

<?php foreach ($payments as $payment): ?>
  <dialog class="modal-card empresa-modal" id="payment-edit-<?= e($payment['id']) ?>">
    <header>
      <div>
        <h2><?= e($payment['concept']) ?></h2>
        <p><?= e($payment['empresa_name']) ?> - <?= e(platform_payment_status_label($payment['status'])) ?></p>
      </div>
      <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
    </header>
    <?php require __DIR__ . '/partials/platform-payment-form.php'; ?>
  </dialog>
<?php endforeach; ?>
