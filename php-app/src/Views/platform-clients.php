<?php
$filters = $filters ?? ['q' => '', 'status' => ''];
$clients = $clients ?? [];
$metrics = $metrics ?? ['lead' => 0, 'qualified' => 0, 'customer' => 0, 'lost' => 0];
$statusOptions = [
    '' => 'Todos',
    'LEAD' => 'Lead',
    'QUALIFIED' => 'Cualificado',
    'CUSTOMER' => 'Cliente',
    'LOST' => 'Perdido',
];
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Clientes</h2>
    <p>Contactos comerciales de Membora CRM antes de crear su empresa y su acceso al CRM.</p>
  </div>
  <button class="primary-action" type="button" data-open-modal="client-create-modal">Nuevo cliente</button>
</div>

<section class="dashboard-metrics">
  <article class="dashboard-metric dashboard-metric--primary">
    <span>Leads</span>
    <strong><?= (int) $metrics['lead'] ?></strong>
    <small>Primer contacto</small>
  </article>
  <article class="dashboard-metric dashboard-metric--green">
    <span>Cualificados</span>
    <strong><?= (int) $metrics['qualified'] ?></strong>
    <small>Listos para propuesta</small>
  </article>
  <article class="dashboard-metric dashboard-metric--orange">
    <span>Clientes</span>
    <strong><?= (int) $metrics['customer'] ?></strong>
    <small>Convertidos a empresa</small>
  </article>
  <article class="dashboard-metric dashboard-metric--danger">
    <span>Perdidos</span>
    <strong><?= (int) $metrics['lost'] ?></strong>
    <small>No continuan</small>
  </article>
</section>

<form class="lead-toolbar platform-toolbar platform-toolbar--payments" method="get" action="index.php" data-auto-filter-form>
  <input type="hidden" name="route" value="platform-clients">
  <label class="field platform-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Empresa, contacto, email, telefono o notas" data-auto-submit-input>
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
      <h3>Listado de clientes</h3>
      <span><?= count($clients) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table platform-table platform-table--payments">
      <thead>
        <tr>
          <th>Empresa</th>
          <th>Contacto</th>
          <th>Email</th>
          <th>Telefono</th>
          <th>Estado</th>
          <th>Notas</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $client): ?>
          <?php $statusClass = strtolower((string) $client['status']); ?>
          <tr class="lead-data-row clickable-row" tabindex="0" data-open-modal="client-edit-<?= e($client['id']) ?>">
            <td><strong><?= e($client['company_name']) ?></strong></td>
            <td><?= e($client['contact_name'] ?: 'Sin contacto') ?></td>
            <td><?= e($client['email'] ?: 'Sin email') ?></td>
            <td><?= e($client['phone'] ?: 'Sin telefono') ?></td>
            <td><span class="status-badge status-badge--<?= e($statusClass) ?>"><?= e(platform_client_status_label($client['status'])) ?></span></td>
            <td><?= e($client['notes'] ? substr($client['notes'], 0, 70) . (strlen($client['notes']) > 70 ? '...' : '') : 'Sin notas') ?></td>
            <td>
              <div class="platform-row-actions">
                <a class="support-enter-action" href="index.php?route=platform-companies&client_id=<?= urlencode($client['id']) ?>&modal=empresa-create-modal">
                  <svg viewBox="0 0 24 24"><path d="M12 5v14m7-7H5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                  <span>Crear empresa</span>
                </a>
                <button class="support-edit-action" type="button" data-open-modal="client-edit-<?= e($client['id']) ?>" aria-label="Editar cliente <?= e($client['company_name']) ?>">
                  <svg viewBox="0 0 24 24"><path d="M4 17.3V20h2.7L17.9 8.8l-2.7-2.7L4 17.3Zm15.8-10.6a1 1 0 0 0 0-1.4l-1.1-1.1a1 1 0 0 0-1.4 0l-.9.9 2.7 2.7.7-.8Z"/></svg>
                  <span>Editar</span>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$clients): ?>
          <tr><td colspan="7" class="empty-state">No hay clientes que coincidan con los filtros actuales.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<dialog class="modal-card empresa-modal" id="client-create-modal">
  <header>
    <div>
      <h2>Nuevo cliente</h2>
      <p>Registra un contacto comercial antes de crear su empresa CRM.</p>
    </div>
    <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
  </header>
  <?php require __DIR__ . '/partials/platform-client-form.php'; ?>
</dialog>

<?php foreach ($clients as $client): ?>
  <dialog class="modal-card empresa-modal" id="client-edit-<?= e($client['id']) ?>">
    <header>
      <div>
        <h2><?= e($client['company_name']) ?></h2>
        <p><?= e($client['email'] ?: 'Sin email') ?> · <?= e(platform_client_status_label($client['status'])) ?></p>
      </div>
      <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
    </header>
    <?php require __DIR__ . '/partials/platform-client-form.php'; ?>
  </dialog>
<?php endforeach; ?>
