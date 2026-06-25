<?php
$filters = $filters ?? ['q' => '', 'status' => '', 'payment_status' => ''];
$empresas = $empresas ?? [];
$metrics = $metrics ?? ['active' => 0, 'trial' => 0, 'payments_pending' => 0, 'mrr' => 0];
$statusOptions = [
    '' => 'Todos',
    'ACTIVE' => 'Activo',
    'TRIAL' => 'Prueba',
    'SUSPENDED' => 'Suspendido',
    'CANCELLED' => 'Cancelado',
];
$paymentOptions = [
    '' => 'Todos',
    'PAID' => 'Al dia',
    'PENDING' => 'Pendiente',
    'OVERDUE' => 'Vencido',
    'TRIAL' => 'Prueba',
];
$planOptions = ['BASIC' => 'Basico', 'PRO' => 'Pro', 'BUSINESS' => 'Business', 'ENTERPRISE' => 'Enterprise'];
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Administracion Membora CRM</h2>
    <p>Control de empresas cliente, estado del CRM, pagos y facturacion mensual.</p>
  </div>
  <button class="primary-action" type="button" data-open-modal="empresa-create-modal">Nueva empresa</button>
</div>

<section class="dashboard-metrics">
  <article class="dashboard-metric dashboard-metric--primary">
    <span>Empresas activas</span>
    <strong><?= (int) $metrics['active'] ?></strong>
    <small>Clientes con CRM operativo</small>
  </article>
  <article class="dashboard-metric dashboard-metric--green">
    <span>En prueba</span>
    <strong><?= (int) $metrics['trial'] ?></strong>
    <small>Seguimiento comercial</small>
  </article>
  <article class="dashboard-metric dashboard-metric--orange">
    <span>Pagos pendientes</span>
    <strong><?= (int) $metrics['payments_pending'] ?></strong>
    <small>Pendientes o vencidos</small>
  </article>
  <article class="dashboard-metric dashboard-metric--danger">
    <span>MRR estimado</span>
    <strong><?= e(money_amount($metrics['mrr'])) ?></strong>
    <small>Ingresos recurrentes mensuales</small>
  </article>
</section>

<form class="lead-toolbar platform-toolbar" method="get" action="index.php" data-auto-filter-form>
  <input type="hidden" name="route" value="platform-dashboard">
  <label class="field platform-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Empresa, contacto, plan o notas" data-auto-submit-input>
  </label>
  <label class="field platform-filter-field">
    <span>Estado CRM</span>
    <select name="status" data-auto-submit-input>
      <?php foreach ($statusOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field platform-filter-field">
    <span>Pago</span>
    <select name="payment_status" data-auto-submit-input>
      <?php foreach ($paymentOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['payment_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="primary-action" type="submit">Filtrar</button>
</form>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Empresas cliente</h3>
      <span><?= count($empresas) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table platform-table">
      <thead>
        <tr>
          <th>Empresa</th>
          <th>Contacto</th>
          <th>Plan</th>
          <th>Estado CRM</th>
          <th>Pago</th>
          <th>Precio mensual</th>
          <th>Proximo pago</th>
          <th>Notas</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($empresas as $empresa): ?>
          <?php
            $statusClass = strtolower((string) $empresa['status']);
            $paymentClass = strtolower((string) $empresa['payment_status']);
          ?>
          <tr class="lead-data-row clickable-row" data-open-modal="empresa-edit-<?= e($empresa['id']) ?>">
            <td>
              <strong><?= e($empresa['name']) ?></strong>
              <span class="table-subtext"><?= $empresa['tenant_id'] ? 'Tenant conectado' : 'Alta manual' ?></span>
            </td>
            <td><?= e($empresa['contact_email'] ?: 'Sin contacto') ?></td>
            <td><span class="source-badge"><?= e($planOptions[$empresa['plan']] ?? $empresa['plan']) ?></span></td>
            <td><span class="status-badge status-badge--<?= e($statusClass) ?>"><?= e(empresa_status_label($empresa['status'])) ?></span></td>
            <td><span class="status-badge status-badge--<?= e($paymentClass) ?>"><?= e(empresa_payment_status_label($empresa['payment_status'])) ?></span></td>
            <td><?= e(money_amount($empresa['monthly_price'])) ?></td>
            <td><?= e(format_date_short($empresa['next_payment_at'])) ?></td>
            <td><?= e($empresa['notes'] ? substr($empresa['notes'], 0, 60) . (strlen($empresa['notes']) > 60 ? '...' : '') : 'Sin notas') ?></td>
            <td>
              <button class="icon-action" type="button" data-open-modal="empresa-edit-<?= e($empresa['id']) ?>" aria-label="Editar <?= e($empresa['name']) ?>">
                <svg viewBox="0 0 24 24"><path d="M4 17.3V20h2.7L17.9 8.8l-2.7-2.7L4 17.3Zm15.8-10.6a1 1 0 0 0 0-1.4l-1.1-1.1a1 1 0 0 0-1.4 0l-.9.9 2.7 2.7.7-.8Z"/></svg>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$empresas): ?>
          <tr><td colspan="9" class="empty-state">No hay empresas que coincidan con los filtros actuales.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<dialog class="modal-card empresa-modal" id="empresa-create-modal">
  <header>
    <div>
      <h2>Nueva empresa</h2>
      <p>Alta manual de cliente CRM para seguimiento comercial y pagos.</p>
    </div>
    <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
  </header>
  <?php require __DIR__ . '/partials/empresa-form.php'; ?>
</dialog>

<?php foreach ($empresas as $empresa): ?>
  <dialog class="modal-card empresa-modal" id="empresa-edit-<?= e($empresa['id']) ?>">
    <header>
      <div>
        <h2><?= e($empresa['name']) ?></h2>
        <p>Gestiona plan, estado y pagos de esta empresa cliente.</p>
      </div>
      <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
    </header>
    <?php require __DIR__ . '/partials/empresa-form.php'; ?>
  </dialog>
<?php endforeach; ?>
