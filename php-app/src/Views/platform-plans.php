<?php
$filters = $filters ?? ['q' => '', 'status' => ''];
$plans = $plans ?? [];
$metrics = $metrics ?? ['active' => 0, 'average_price' => 0, 'enterprise' => 0];
$statusOptions = [
    '' => 'Todos',
    'ACTIVE' => 'Activo',
    'INACTIVE' => 'Inactivo',
    'ARCHIVED' => 'Archivado',
];
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Planes CRM</h2>
    <p>Define los paquetes comerciales de Membora CRM: precios, limites, setup y prestaciones.</p>
  </div>
  <button class="primary-action" type="button" data-open-modal="plan-create-modal">Nuevo plan</button>
</div>

<section class="dashboard-metrics">
  <article class="dashboard-metric dashboard-metric--primary">
    <span>Planes activos</span>
    <strong><?= (int) $metrics['active'] ?></strong>
    <small>Disponibles para venta</small>
  </article>
  <article class="dashboard-metric dashboard-metric--green">
    <span>Precio medio</span>
    <strong><?= e(money_amount($metrics['average_price'])) ?></strong>
    <small>Planes activos</small>
  </article>
  <article class="dashboard-metric dashboard-metric--orange">
    <span>Enterprise</span>
    <strong><?= (int) $metrics['enterprise'] ?></strong>
    <small>Oferta personalizada</small>
  </article>
  <article class="dashboard-metric dashboard-metric--danger">
    <span>Catalogo</span>
    <strong><?= count($plans) ?></strong>
    <small>Planes filtrados</small>
  </article>
</section>

<form class="lead-toolbar platform-toolbar platform-toolbar--payments" method="get" action="index.php" data-auto-filter-form>
  <input type="hidden" name="route" value="platform-plans">
  <label class="field platform-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Nombre, codigo o funciones" data-auto-submit-input>
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

<section class="platform-plan-cards">
  <?php foreach ($plans as $plan): ?>
    <?php
      $monthlyPrice = (float) ($plan['monthly_price'] ?? 0);
      $discountPrice = (float) ($plan['discount_price'] ?? 0);
      $hasDiscount = $discountPrice > 0 && $discountPrice < $monthlyPrice;
    ?>
    <article class="platform-plan-card">
      <header>
        <div>
          <span><?= e($plan['code']) ?></span>
          <h3><?= e($plan['name']) ?></h3>
        </div>
        <span class="status-badge status-badge--<?= e(strtolower((string) $plan['status'])) ?>"><?= e(platform_plan_status_label($plan['status'])) ?></span>
      </header>
      <strong>
        <?php if ($hasDiscount): ?>
          <span class="plan-original-price"><?= e(money_amount($monthlyPrice)) ?></span>
          <?= e(money_amount($discountPrice)) ?><small>/mes</small>
        <?php else: ?>
          <?= e(money_amount($monthlyPrice)) ?><small>/mes</small>
        <?php endif; ?>
      </strong>
      <?php if ($hasDiscount): ?>
        <span class="plan-discount-badge"><?= e($plan['discount_label'] ?: 'Oferta activa') ?></span>
      <?php endif; ?>
      <dl>
        <div><dt>Alta</dt><dd><?= e(money_amount($plan['setup_price'])) ?></dd></div>
        <div><dt>Usuarios</dt><dd><?= $plan['max_users'] !== null ? (int) $plan['max_users'] : 'Sin limite' ?></dd></div>
        <div><dt>Socios</dt><dd><?= $plan['max_members'] !== null ? (int) $plan['max_members'] : 'Sin limite' ?></dd></div>
      </dl>
      <p><?= e($plan['features'] ?: 'Sin descripcion de funciones.') ?></p>
      <button class="support-edit-action" type="button" data-open-modal="plan-edit-<?= e($plan['id']) ?>">
        <svg viewBox="0 0 24 24"><path d="M4 17.3V20h2.7L17.9 8.8l-2.7-2.7L4 17.3Zm15.8-10.6a1 1 0 0 0 0-1.4l-1.1-1.1a1 1 0 0 0-1.4 0l-.9.9 2.7 2.7.7-.8Z"/></svg>
        <span>Editar plan</span>
      </button>
    </article>
  <?php endforeach; ?>
  <?php if (!$plans): ?>
    <p class="platform-empty">No hay planes que coincidan con los filtros actuales.</p>
  <?php endif; ?>
</section>

<?php unset($plan); ?>
<dialog class="modal-card empresa-modal" id="plan-create-modal">
  <header>
    <div>
      <h2>Nuevo plan</h2>
      <p>Crea un paquete comercial para las empresas cliente.</p>
    </div>
    <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
  </header>
  <?php require __DIR__ . '/partials/platform-plan-form.php'; ?>
</dialog>

<?php foreach ($plans as $plan): ?>
  <dialog class="modal-card empresa-modal" id="plan-edit-<?= e($plan['id']) ?>">
    <header>
      <div>
        <h2><?= e($plan['name']) ?></h2>
        <p><?= e($plan['code']) ?> - <?= e(platform_plan_status_label($plan['status'])) ?></p>
      </div>
      <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
    </header>
    <?php require __DIR__ . '/partials/platform-plan-form.php'; ?>
  </dialog>
<?php endforeach; ?>
