<div class="page-heading">
  <div>
    <h2>Pagos</h2>
    <p>Registra cobros de socios, vencimientos y pagos pendientes del gimnasio.</p>
  </div>
  <div class="row-actions">
    <form method="post">
      <input type="hidden" name="action" value="generate_recurring_payments">
      <input type="hidden" name="until_date" value="<?= e(date('Y-m-d')) ?>">
      <button class="secondary-action secondary-action--compact" type="submit">Generar borradores</button>
    </form>
    <button class="primary-action primary-action--compact" data-open-modal="payment-modal" type="button">Nuevo pago</button>
  </div>
</div>

<section class="lead-metrics" aria-label="Resumen de pagos">
  <article class="lead-metric lead-metric--green">
    <span>Cobrado este mes</span>
    <strong><?= e(money_amount($metrics['paid_month'])) ?></strong>
  </article>
  <article class="lead-metric lead-metric--orange">
    <span>Pendiente</span>
    <strong><?= e(money_amount($metrics['pending_amount'])) ?></strong>
  </article>
  <article class="lead-metric lead-metric--blue">
    <span>Pagos pendientes</span>
    <strong><?= (int) $metrics['pending_count'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--blue">
    <span>Borradores</span>
    <strong><?= (int) $metrics['draft_count'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark">
    <span>Vencidos</span>
    <strong><?= (int) $metrics['overdue_count'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--green">
    <span>Próximos 7 días</span>
    <strong><?= (int) $metrics['next_due_count'] ?></strong>
  </article>
</section>

<?php
$paymentStatusOptions = [
  '' => 'Todos',
  'DRAFT' => 'Borradores',
  'PENDING' => 'Pendientes',
  'PAID' => 'Pagados',
  'OVERDUE' => 'Vencidos',
  'CANCELLED' => 'Anulados',
];
?>

<form class="lead-toolbar member-toolbar" method="get" aria-label="Filtros de pagos" data-auto-filter-form data-live-search-form data-live-search-target="payments-table">
  <input type="hidden" name="route" value="payments">
  <label class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Socio, email, membresía o nota" aria-label="Buscar pagos" data-auto-filter-input>
  </label>
  <div class="lead-filter-group">
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="status" value="<?= e($filters['status']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Estado</small>
        <span data-custom-select-label><?= e($paymentStatusOptions[$filters['status']] ?? 'Todos') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($paymentStatusOptions as $statusValue => $statusLabel): ?>
          <button class="custom-select-option <?= $filters['status'] === $statusValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($statusValue) ?>">
            <?= e($statusLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <label class="filter-control filter-control--date date-filter-control">
      <span>Desde</span>
      <input name="date_from" type="date" value="<?= e($filters['date_from']) ?>" data-auto-filter-input>
    </label>
    <label class="filter-control filter-control--date date-filter-control">
      <span>Hasta</span>
      <input name="date_to" type="date" value="<?= e($filters['date_to']) ?>" data-auto-filter-input>
    </label>
  </div>
  <button class="primary-action primary-action--compact" type="submit">Filtrar</button>
</form>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Listado de pagos</h3>
      <span data-live-search-count><?= count($payments) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table" id="payments-table">
      <caption class="sr-only">Listado de pagos de socios</caption>
      <thead>
        <tr>
          <th scope="col">Socio</th>
          <th scope="col">Membresía</th>
          <th scope="col">Importe</th>
          <th scope="col">Método</th>
          <th scope="col">Estado</th>
          <th scope="col">Periodo</th>
          <th scope="col">Vence</th>
          <th scope="col">Pagado</th>
          <th scope="col">Referencia</th>
          <th scope="col">Notas</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $payment): ?>
          <?php
            $memberName = trim($payment['first_name'] . ' ' . ($payment['last_name'] ?? ''));
            $statusClass = strtolower((string) $payment['status']);
          ?>
          <tr class="lead-data-row clickable-row" data-open-modal="payment-detail-<?= e($payment['id']) ?>" data-live-search-row>
            <td>
              <strong><?= e($memberName) ?></strong>
              <small class="table-subtext"><?= e($payment['email'] ?: ($payment['phone'] ?: 'Sin contacto')) ?></small>
            </td>
            <td><?= e($payment['plan_name'] ?: 'Sin membresía') ?></td>
            <td><?= e(money_amount($payment['amount'])) ?></td>
            <td><?= e(payment_method_label($payment['payment_method'])) ?></td>
            <td><span class="status-badge status-badge--<?= e($statusClass) ?>"><?= e(platform_payment_status_label($payment['status'])) ?></span></td>
            <td>
              <small class="table-subtext">
                <?= e($payment['period_start_at'] ? format_date_short($payment['period_start_at']) : '-') ?>
                <?= $payment['period_end_at'] ? ' - ' . e(format_date_short($payment['period_end_at'])) : '' ?>
              </small>
            </td>
            <td><?= e(format_date_short($payment['due_at'])) ?></td>
            <td><?= e(format_date_short($payment['paid_at'])) ?></td>
            <td><?= e($payment['reference'] ?: '-') ?></td>
            <td><?= e($payment['notes'] ? substr($payment['notes'], 0, 70) . (strlen($payment['notes']) > 70 ? '...' : '') : 'Sin notas') ?></td>
            <td>
              <div class="row-actions">
                <button class="icon-action" data-open-modal="payment-detail-<?= e($payment['id']) ?>" type="button" title="Editar pago" aria-label="Editar pago de <?= e($memberName) ?>">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4.8L19.4 9.4a2.1 2.1 0 0 0 0-3L17.6 4.6a2.1 2.1 0 0 0-3 0L4 15.2V20Zm2-2v-1.95l7.25-7.25 1.95 1.95L7.95 18H6Zm10.6-8.65L14.65 7.4 16 6.05 17.95 8l-1.35 1.35Z"/></svg>
                </button>
                <?php if (!in_array((string) $payment['status'], ['PAID', 'CANCELLED'], true)): ?>
                  <form method="post" data-confirm-message="Marcar este pago como cobrado?">
                    <input type="hidden" name="action" value="mark_payment_paid">
                    <input type="hidden" name="id" value="<?= e($payment['id']) ?>">
                    <input type="hidden" name="paid_at" value="<?= e(date('Y-m-d')) ?>">
                    <input type="hidden" name="payment_method" value="<?= e($payment['payment_method'] ?: 'OTHER') ?>">
                    <button class="icon-action success-action" type="submit" title="Marcar como cobrada" aria-label="Marcar como cobrada la cuota de <?= e($memberName) ?>">
                      <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9.2 16.2 4.9 11.9 3.5 13.3l5.7 5.7L21 7.2 19.6 5.8 9.2 16.2Z"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
                <a class="icon-action success-action" href="index.php?route=payment-invoice&id=<?= urlencode($payment['id']) ?>" target="_blank" rel="noopener" title="Crear factura PDF" aria-label="Crear factura PDF del pago de <?= e($memberName) ?>">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 2h9l5 5v15H6V2Zm8 1.5V8h4.5L14 3.5ZM8 11h8v2H8v-2Zm0 4h8v2H8v-2Zm0-8h4v2H8V7Z"/></svg>
                </a>
                <form method="post" data-confirm-message="Eliminar este pago? Esta acción no se puede deshacer.">
                  <input type="hidden" name="action" value="delete_payment">
                  <input type="hidden" name="id" value="<?= e($payment['id']) ?>">
                  <button class="icon-action danger-action" type="submit" title="Eliminar pago" aria-label="Eliminar pago de <?= e($memberName) ?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 21a2 2 0 0 1-2-2V8h14v11a2 2 0 0 1-2 2H7ZM9 6V4h6v2h5v2H4V6h5Zm0 5v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?>
          <tr data-live-search-empty>
            <td class="leads-empty-cell" colspan="11">No hay pagos que coincidan con los filtros actuales.</td>
          </tr>
        <?php else: ?>
          <tr data-live-search-empty hidden>
            <td class="leads-empty-cell" colspan="11">No hay pagos que coincidan con la búsqueda actual.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<dialog id="payment-modal" class="modal-card" aria-labelledby="payment-modal-title">
  <form method="post" data-payment-form>
    <header>
      <h2 id="payment-modal-title">Nuevo pago</h2>
      <button data-close-modal type="button">Cerrar</button>
    </header>
    <?php require __DIR__ . '/partials/payment-form.php'; ?>
    <button class="primary-action" type="submit" name="action" value="create_payment">Registrar pago</button>
  </form>
</dialog>

<?php foreach ($payments as $payment): ?>
  <?php $memberName = trim($payment['first_name'] . ' ' . ($payment['last_name'] ?? '')); ?>
  <dialog id="payment-detail-<?= e($payment['id']) ?>" class="modal-card" aria-labelledby="payment-title-<?= e($payment['id']) ?>">
    <form method="post" data-payment-form>
      <header>
        <div>
          <h2 id="payment-title-<?= e($payment['id']) ?>">Pago de <?= e($memberName) ?></h2>
          <p><?= e(money_amount($payment['amount'])) ?> · <?= e(platform_payment_status_label($payment['status'])) ?></p>
        </div>
        <button data-close-modal type="button">Cerrar</button>
      </header>
      <?php require __DIR__ . '/partials/payment-form.php'; ?>
      <div class="row-actions">
        <?php if (!in_array((string) $payment['status'], ['PAID', 'CANCELLED'], true)): ?>
          <button class="secondary-action" type="submit" name="action" value="mark_payment_paid">Marcar como cobrada</button>
        <?php endif; ?>
        <button class="primary-action" type="submit" name="action" value="update_payment">Guardar pago</button>
      </div>
    </form>
  </dialog>
<?php endforeach; ?>
