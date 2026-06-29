<?php
$filters = $filters ?? ['q' => '', 'status' => ''];
$leads = $leads ?? [];
$metrics = $metrics ?? ['new' => 0, 'contacted' => 0, 'qualified' => 0, 'converted' => 0, 'lost' => 0];
$statusOptions = [
    '' => 'Todos',
    'NEW' => 'Nuevo',
    'CONTACTED' => 'Contactado',
    'QUALIFIED' => 'Cualificado',
    'CONVERTED' => 'Convertido',
    'LOST' => 'Perdido',
];
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Leads comerciales</h2>
    <p>Solicitudes recibidas desde la web publica de Membora CRM antes de convertirlas en clientes.</p>
  </div>
  <a class="secondary-action" href="index.php?route=platform-web">Configuracion web</a>
</div>

<section class="dashboard-metrics">
  <article class="dashboard-metric dashboard-metric--primary">
    <span>Nuevos</span>
    <strong><?= (int) $metrics['new'] ?></strong>
    <small>Sin gestionar</small>
  </article>
  <article class="dashboard-metric dashboard-metric--green">
    <span>Contactados</span>
    <strong><?= (int) $metrics['contacted'] ?></strong>
    <small>Primer seguimiento</small>
  </article>
  <article class="dashboard-metric dashboard-metric--orange">
    <span>Cualificados</span>
    <strong><?= (int) $metrics['qualified'] ?></strong>
    <small>Listos para propuesta</small>
  </article>
  <article class="dashboard-metric dashboard-metric--danger">
    <span>Convertidos</span>
    <strong><?= (int) $metrics['converted'] ?></strong>
    <small>Pasados a clientes</small>
  </article>
</section>

<form class="lead-toolbar platform-toolbar platform-toolbar--payments" method="get" action="index.php" data-auto-filter-form>
  <input type="hidden" name="route" value="platform-leads">
  <label class="field platform-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Empresa, contacto, email, telefono o mensaje" data-auto-submit-input>
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
      <h3>Solicitudes web</h3>
      <span><?= count($leads) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table platform-table platform-table--payments">
      <thead>
        <tr>
          <th>Contacto</th>
          <th>Gimnasio</th>
          <th>Email</th>
          <th>Telefono</th>
          <th>Estado</th>
          <th>Fecha</th>
          <th>Mensaje</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leads as $lead): ?>
          <?php $statusClass = strtolower(str_replace('_', '-', (string) $lead['status'])); ?>
          <tr class="lead-data-row clickable-row" tabindex="0" data-open-modal="platform-lead-<?= e($lead['id']) ?>">
            <td><strong><?= e($lead['contact_name']) ?></strong></td>
            <td><?= e($lead['company_name'] ?: 'Sin gimnasio') ?></td>
            <td><?= e($lead['email'] ?: 'Sin email') ?></td>
            <td><?= e($lead['phone'] ?: 'Sin telefono') ?></td>
            <td><span class="status-badge status-badge--<?= e($statusClass) ?>"><?= e(platform_lead_status_label($lead['status'])) ?></span></td>
            <td><?= e(format_date($lead['created_at'])) ?></td>
            <td><?= e($lead['message'] ? substr($lead['message'], 0, 70) . (strlen($lead['message']) > 70 ? '...' : '') : 'Sin mensaje') ?></td>
            <td>
              <div class="platform-row-actions">
                <?php if ($lead['status'] !== 'CONVERTED'): ?>
                  <form method="post">
                    <input type="hidden" name="action" value="convert_platform_lead">
                    <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
                    <button class="support-enter-action" type="submit" aria-label="Convertir <?= e($lead['contact_name']) ?> en cliente">
                      <svg viewBox="0 0 24 24"><path d="M12 5v14m7-7H5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                      <span>Cliente</span>
                    </button>
                  </form>
                <?php endif; ?>
                <button class="support-edit-action" type="button" data-open-modal="platform-lead-<?= e($lead['id']) ?>" aria-label="Editar lead <?= e($lead['contact_name']) ?>">
                  <svg viewBox="0 0 24 24"><path d="M4 17.3V20h2.7L17.9 8.8l-2.7-2.7L4 17.3Zm15.8-10.6a1 1 0 0 0 0-1.4l-1.1-1.1a1 1 0 0 0-1.4 0l-.9.9 2.7 2.7.7-.8Z"/></svg>
                  <span>Editar</span>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$leads): ?>
          <tr><td colspan="8" class="empty-state">Todavia no hay solicitudes web con estos filtros.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($leads as $lead): ?>
  <dialog class="modal-card empresa-modal" id="platform-lead-<?= e($lead['id']) ?>">
    <header>
      <div>
        <h2><?= e($lead['contact_name']) ?></h2>
        <p><?= e($lead['company_name'] ?: 'Solicitud web') ?> - <?= e(platform_lead_status_label($lead['status'])) ?></p>
      </div>
      <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
    </header>
    <form class="empresa-form" method="post">
      <input type="hidden" name="action" value="update_platform_lead">
      <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
      <label class="field">
        <span>Contacto</span>
        <input name="contact_name" required value="<?= e($lead['contact_name']) ?>">
      </label>
      <label class="field">
        <span>Gimnasio / centro</span>
        <input name="company_name" value="<?= e($lead['company_name']) ?>">
      </label>
      <label class="field">
        <span>Email</span>
        <input name="email" type="email" value="<?= e($lead['email']) ?>">
      </label>
      <label class="field">
        <span>Telefono</span>
        <input name="phone" value="<?= e($lead['phone']) ?>">
      </label>
      <label class="field">
        <span>Estado</span>
        <select name="status">
          <?php foreach (array_filter($statusOptions, static fn ($label, $value): bool => $value !== '', ARRAY_FILTER_USE_BOTH) as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $lead['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field form-full">
        <span>Mensaje / notas</span>
        <textarea name="message" rows="6"><?= e($lead['message']) ?></textarea>
      </label>
      <?php if (!empty($lead['source_url'])): ?>
        <div class="form-full platform-form-divider">
          <strong>Origen</strong>
          <span><?= e($lead['source_url']) ?></span>
        </div>
      <?php endif; ?>
      <div class="form-actions form-full">
        <button class="secondary-action" type="button" data-close-modal>Cancelar</button>
        <button class="primary-action" type="submit">Guardar lead</button>
      </div>
    </form>
  </dialog>
<?php endforeach; ?>
