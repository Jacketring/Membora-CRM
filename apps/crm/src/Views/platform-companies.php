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
    'PAID' => 'Al día',
    'PENDING' => 'Pendiente',
    'OVERDUE' => 'Vencido',
    'TRIAL' => 'Prueba',
];
$planOptions = $planOptions ?? PlatformPlanRepository::options();
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Empresas cliente</h2>
    <p>Gestiona clientes del CRM, estado comercial, pagos, plan contratado y acceso de soporte.</p>
  </div>
  <button class="primary-action" type="button" data-open-modal="empresa-create-modal">Nueva empresa</button>
</div>

<section class="dashboard-metrics">
  <article class="dashboard-metric dashboard-metric--primary">
    <span>Activas</span>
    <strong><?= (int) $metrics['active'] ?></strong>
    <small>CRM operativo</small>
  </article>
  <article class="dashboard-metric dashboard-metric--green">
    <span>En prueba</span>
    <strong><?= (int) $metrics['trial'] ?></strong>
    <small>Potenciales clientes</small>
  </article>
  <article class="dashboard-metric dashboard-metric--orange">
    <span>Pago pendiente</span>
    <strong><?= (int) $metrics['payments_pending'] ?></strong>
    <small>Pendiente o vencido</small>
  </article>
  <article class="dashboard-metric dashboard-metric--danger">
    <span>MRR cartera</span>
    <strong><?= e(money_amount($metrics['mrr'])) ?></strong>
    <small>Ingresos recurrentes</small>
  </article>
</section>

<form class="lead-toolbar platform-toolbar" method="get" action="index.php" data-auto-filter-form>
  <input type="hidden" name="route" value="platform-companies">
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
      <h3>Listado de empresas</h3>
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
          <th>Suscripción</th>
          <th>Acceso</th>
          <th>Notas</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($empresas as $empresa): ?>
          <?php
            $statusClass = strtolower((string) $empresa['status']);
            $paymentClass = strtolower((string) $empresa['payment_status']);
            $isTrialPlan = strtoupper((string) $empresa['plan']) === 'TRIAL';
            $nextPaymentTime = !empty($empresa['next_payment_at']) ? strtotime((string) $empresa['next_payment_at']) : false;
            $renewalStatus = (string) ($empresa['renewal_status'] ?? 'ACTIVE');
            $canRenew = !$isTrialPlan
                && $nextPaymentTime !== false
                && $nextPaymentTime <= strtotime(date('Y-m-d'))
                && in_array((string) $empresa['status'], ['ACTIVE', 'TRIAL'], true)
                && (float) $empresa['monthly_price'] > 0;
            $canCancel = !$isTrialPlan && $renewalStatus === 'ACTIVE' && (string) $empresa['status'] !== 'CANCELLED';
            $canResume = in_array($renewalStatus, ['CANCEL_AT_PERIOD_END', 'CANCELLED'], true);
          ?>
          <tr class="lead-data-row clickable-row" tabindex="0" data-open-modal="empresa-edit-<?= e($empresa['id']) ?>">
            <td>
              <strong><?= e($empresa['name']) ?></strong>
              <span class="table-subtext"><?= $empresa['tenant_id'] ? 'Tenant conectado' : 'Alta manual' ?></span>
            </td>
            <td><?= e($empresa['contact_email'] ?: 'Sin contacto') ?></td>
            <td><span class="source-badge"><?= e($planOptions[$empresa['plan']] ?? $empresa['plan']) ?></span></td>
            <td><span class="status-badge status-badge--<?= e($statusClass) ?>"><?= e(empresa_status_label($empresa['status'])) ?></span></td>
            <td><span class="status-badge status-badge--<?= e($paymentClass) ?>"><?= e(empresa_payment_status_label($empresa['payment_status'])) ?></span></td>
            <td><?= e(money_amount($empresa['monthly_price'])) ?></td>
            <td>
              <strong><?= e(empresa_renewal_period_label($empresa['renewal_period'] ?? 'MONTHLY')) ?></strong>
              <span class="table-subtext"><?= e($isTrialPlan ? ('Prueba: ' . (int) ($empresa['trial_days'] ?? 30) . ' días') : empresa_renewal_status_label($renewalStatus)) ?></span>
            </td>
            <td>
              <strong><?= e($isTrialPlan ? 'Demo' : format_date_short($empresa['access_until'] ?: $empresa['next_payment_at'])) ?></strong>
              <span class="table-subtext"><?= e($isTrialPlan ? ('Inicio ' . format_date_short($empresa['subscription_started_at'] ?: $empresa['created_at'])) : ('Próximo pago ' . format_date_short($empresa['next_payment_at']))) ?></span>
            </td>
            <td><?= e($empresa['notes'] ? substr($empresa['notes'], 0, 60) . (strlen($empresa['notes']) > 60 ? '...' : '') : 'Sin notas') ?></td>
            <td>
              <div class="platform-row-actions">
                <?php if ($empresa['tenant_id']): ?>
                  <form method="post">
                    <input type="hidden" name="action" value="enter_empresa_crm">
                    <input type="hidden" name="id" value="<?= e($empresa['id']) ?>">
                    <button class="support-enter-action" type="submit" aria-label="Entrar al CRM de <?= e($empresa['name']) ?>">
                      <svg viewBox="0 0 24 24"><path d="M14 3h7v7h-2V6.4l-8.3 8.3-1.4-1.4L17.6 5H14V3ZM5 5h6v2H7v10h10v-4h2v6H5V5Z"/></svg>
                      <span>Entrar</span>
                    </button>
                  </form>
                <?php endif; ?>
                <button class="support-edit-action" type="button" data-open-modal="empresa-edit-<?= e($empresa['id']) ?>" aria-label="Editar <?= e($empresa['name']) ?>">
                  <svg viewBox="0 0 24 24"><path d="M4 17.3V20h2.7L17.9 8.8l-2.7-2.7L4 17.3Zm15.8-10.6a1 1 0 0 0 0-1.4l-1.1-1.1a1 1 0 0 0-1.4 0l-.9.9 2.7 2.7.7-.8Z"/></svg>
                  <span>Editar</span>
                </button>
                <?php if ($canRenew): ?>
                  <form method="post" data-confirm-message="Se creara un pago pagado y se movera el próximo pago al mes siguiente." data-confirm-action-label="Confirmar">
                    <input type="hidden" name="action" value="renew_empresa_subscription">
                    <input type="hidden" name="id" value="<?= e($empresa['id']) ?>">
                    <button class="support-renew-action" type="submit" aria-label="Renovar suscripción de <?= e($empresa['name']) ?>">
                      <svg viewBox="0 0 24 24"><path d="M17.7 6.3A8 8 0 1 0 20 12h-2a6 6 0 1 1-1.8-4.2L13 11h8V3l-3.3 3.3ZM11 7h2v5.6l4 2.4-1 1.7-5-3V7Z"/></svg>
                      <span>Renovación</span>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($canCancel): ?>
                  <form method="post" data-confirm-message="La empresa mantendra acceso hasta la fecha de fin del periodo, pero no renovara automaticamente." data-confirm-action-label="Cancelar renovación">
                    <input type="hidden" name="action" value="cancel_empresa_subscription">
                    <input type="hidden" name="id" value="<?= e($empresa['id']) ?>">
                    <button class="note-delete-button" type="submit" aria-label="Cancelar suscripción de <?= e($empresa['name']) ?>">
                      <svg viewBox="0 0 24 24"><path d="M6 6h12v2H6V6Zm2 4h8l-1 10H9L8 10Zm3-7h2l1 2h-4l1-2Z"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($canResume): ?>
                  <form method="post">
                    <input type="hidden" name="action" value="resume_empresa_subscription">
                    <input type="hidden" name="id" value="<?= e($empresa['id']) ?>">
                    <button class="support-renew-action" type="submit" aria-label="Reactivar suscripción de <?= e($empresa['name']) ?>">
                      <svg viewBox="0 0 24 24"><path d="M12 5v14m7-7H5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                      <span>Reactivar</span>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$empresas): ?>
          <tr><td colspan="10" class="empty-state">No hay empresas que coincidan con los filtros actuales.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php unset($empresa); ?>
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
