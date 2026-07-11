<div class="page-heading">
  <div>
    <h2>Membresías</h2>
    <p>Gestiona planes, precios, duración y vencimientos de los socios.</p>
  </div>
  <button class="primary-action primary-action--compact" data-open-modal="membership-modal" type="button">Nueva membresía</button>
</div>

<section class="lead-metrics" aria-label="Resumen de membresías">
  <article class="lead-metric lead-metric--blue">
    <span>Planes activos</span>
    <strong><?= (int) $metrics['plans'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--green">
    <span>Socios asignados</span>
    <strong><?= (int) $metrics['assigned'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--orange">
    <span>Caducan pronto</span>
    <strong><?= (int) $metrics['expiring'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--blue">
    <span>Próxima facturación</span>
    <strong><?= (int) $metrics['next_billing'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark">
    <span>Caducadas</span>
    <strong><?= (int) $metrics['expired'] ?></strong>
  </article>
</section>

<?php
$membershipStatusOptions = [
  '' => 'Todos',
  'ACTIVE' => 'Activas',
  'INACTIVE' => 'Inactivas',
];
?>

<form class="lead-toolbar membership-toolbar" method="get" aria-label="Filtros de membresías" data-auto-filter-form data-live-search-form data-live-search-target="memberships-table">
  <input type="hidden" name="route" value="memberships">
  <label class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Nombre de membresía o socio" aria-label="Buscar membresías o socios" data-auto-filter-input>
  </label>
  <div class="lead-filter-group">
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="status" value="<?= e($filters['status']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Estado</small>
        <span data-custom-select-label><?= e($membershipStatusOptions[$filters['status']] ?? 'Todos') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($membershipStatusOptions as $statusValue => $statusLabel): ?>
          <button class="custom-select-option <?= $filters['status'] === $statusValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($statusValue) ?>">
            <?= e($statusLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <button class="primary-action primary-action--compact" type="submit">Filtrar</button>
</form>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Planes de membresía</h3>
      <span data-live-search-count><?= count($plans) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table" id="memberships-table">
      <caption class="sr-only">Listado de planes de membresía con precio y duración</caption>
      <thead>
        <tr>
          <th scope="col">Membresía</th>
          <th scope="col">Precio</th>
          <th scope="col">Duración</th>
          <th scope="col">Estado</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plans as $plan): ?>
          <tr class="lead-data-row clickable-row" data-open-modal="membership-detail-<?= e($plan['id']) ?>" data-live-search-row>
            <td>
              <strong><?= e($plan['name']) ?></strong>
              <small class="table-subtext"><?= e($plan['description'] ?: 'Sin descripción') ?></small>
            </td>
            <td><?= e(money_amount($plan['price'])) ?></td>
            <td><?= e(membership_period_label($plan['billing_period'])) ?></td>
            <td>
              <span class="status-badge status-badge--<?= e(strtolower($plan['status'])) ?>"><?= e(status_label($plan['status'])) ?></span>
            </td>
            <td>
              <div class="row-actions">
                <button class="icon-action" data-open-modal="membership-detail-<?= e($plan['id']) ?>" type="button" title="Editar membresía" aria-label="Editar membresía <?= e($plan['name']) ?>">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4.8L19.4 9.4a2.1 2.1 0 0 0 0-3L17.6 4.6a2.1 2.1 0 0 0-3 0L4 15.2V20Zm2-2v-1.95l7.25-7.25 1.95 1.95L7.95 18H6Zm10.6-8.65L14.65 7.4 16 6.05 17.95 8l-1.35 1.35Z"/></svg>
                </button>
                <form method="post" data-confirm-message="Eliminar esta membresía? Solo se permite si no esta asignada a socios.">
                  <input type="hidden" name="action" value="delete_membership_plan">
                  <input type="hidden" name="id" value="<?= e($plan['id']) ?>">
                  <button class="icon-action danger-action" type="submit" title="Eliminar membresía" aria-label="Eliminar membresía <?= e($plan['name']) ?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 21a2 2 0 0 1-2-2V8h14v11a2 2 0 0 1-2 2H7ZM9 6V4h6v2h5v2H4V6h5Zm0 5v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$plans): ?>
          <tr data-live-search-empty>
            <td class="leads-empty-cell" colspan="5">No hay membresías que coincidan con los filtros actuales.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="leads-table-card membership-subscriptions-card">
  <header>
    <div>
      <h3>Socios con membresía</h3>
      <span><?= count($subscriptions) ?> asignaciones activas</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table">
      <caption class="sr-only">Socios con membresía asignada y fecha de caducidad</caption>
      <thead>
        <tr>
          <th scope="col">Socio</th>
          <th scope="col">Membresía</th>
          <th scope="col">Precio</th>
          <th scope="col">Inicio</th>
          <th scope="col">Próximo cobro</th>
          <th scope="col">Caduca</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subscriptions as $subscription): ?>
          <?php $memberName = trim($subscription['first_name'] . ' ' . ($subscription['last_name'] ?? '')); ?>
          <tr>
            <td><strong><?= e($memberName) ?></strong></td>
            <td><?= e($subscription['plan_name']) ?> <small class="table-subtext"><?= e(membership_period_label($subscription['billing_period'])) ?></small></td>
            <td><?= e(money_amount($subscription['price'])) ?></td>
            <td><?= e(format_date_short($subscription['starts_at'])) ?></td>
            <td><?= e(format_date_short($subscription['next_billing_at'] ?: $subscription['starts_at'])) ?></td>
            <td>
              <span class="membership-expiry <?= strtotime($subscription['ends_at']) < strtotime(date('Y-m-d')) ? 'membership-expiry--expired' : '' ?>">
                <?= e(format_date_short($subscription['ends_at'])) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$subscriptions): ?>
          <tr>
            <td class="leads-empty-cell" colspan="6">Todavía no hay socios con membresía asignada.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($plans as $plan): ?>
  <dialog id="membership-detail-<?= e($plan['id']) ?>" class="modal-card" aria-labelledby="membership-title-<?= e($plan['id']) ?>">
    <form method="post" aria-label="Editar membresía <?= e($plan['name']) ?>">
      <input type="hidden" name="action" value="update_membership_plan">
      <input type="hidden" name="id" value="<?= e($plan['id']) ?>">
      <header>
        <div>
          <h2 id="membership-title-<?= e($plan['id']) ?>"><?= e($plan['name']) ?></h2>
          <p><?= e(money_amount($plan['price'])) ?> · <?= e(membership_period_label($plan['billing_period'])) ?></p>
        </div>
        <button data-close-modal type="button">Cerrar</button>
      </header>
      <?php require __DIR__ . '/partials/membership-plan-form.php'; ?>
      <button class="primary-action" type="submit">Guardar cambios</button>
    </form>
  </dialog>
<?php endforeach; ?>

<?php $plan = ['name' => '', 'description' => '', 'price' => '0.00', 'billing_period' => 'MONTHLY', 'status' => 'ACTIVE']; ?>
<dialog id="membership-modal" class="modal-card" aria-labelledby="membership-modal-title">
  <form method="post">
    <input type="hidden" name="action" value="create_membership_plan">
    <header>
      <h2 id="membership-modal-title">Nueva membresía</h2>
      <button data-close-modal type="button">Cerrar</button>
    </header>
    <?php require __DIR__ . '/partials/membership-plan-form.php'; ?>
    <button class="primary-action" type="submit">Crear membresía</button>
  </form>
</dialog>
