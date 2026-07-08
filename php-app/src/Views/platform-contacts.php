<?php
$filters = $filters ?? ['q' => '', 'status' => '', 'type' => ''];
$contacts = $contacts ?? [];
$metrics = $metrics ?? ['new' => 0, 'qualified' => 0, 'customers' => 0, 'lost' => 0];
$statusOptions = [
    '' => 'Todos',
    'NEW' => 'Nuevo',
    'CONTACTED' => 'Contactado',
    'LEAD' => 'Lead',
    'QUALIFIED' => 'Cualificado',
    'CONVERTED' => 'Convertido',
    'CUSTOMER' => 'Cliente',
    'LOST' => 'Perdido',
];
$typeOptions = [
    '' => 'Todos',
    'lead' => 'Lead web',
    'client' => 'Cliente CRM',
];
$leadStatusOptions = [
    'NEW' => 'Nuevo',
    'CONTACTED' => 'Contactado',
    'QUALIFIED' => 'Cualificado',
    'CONVERTED' => 'Convertido',
    'LOST' => 'Perdido',
];
$clientStatusOptions = [
    'LEAD' => 'Lead',
    'QUALIFIED' => 'Cualificado',
    'CUSTOMER' => 'Cliente',
    'LOST' => 'Perdido',
];
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Contactos</h2>
    <p>Gestiona en una sola tabla los leads web y los clientes comerciales antes de crear su empresa CRM.</p>
  </div>
  <button class="primary-action" type="button" data-open-modal="client-create-modal">Nuevo contacto</button>
</div>

<section class="dashboard-metrics">
  <article class="dashboard-metric dashboard-metric--primary">
    <span>Nuevos</span>
    <strong><?= (int) $metrics['new'] ?></strong>
    <small>Primer contacto</small>
  </article>
  <article class="dashboard-metric dashboard-metric--green">
    <span>Cualificados</span>
    <strong><?= (int) $metrics['qualified'] ?></strong>
    <small>Listos para propuesta</small>
  </article>
  <article class="dashboard-metric dashboard-metric--orange">
    <span>Clientes</span>
    <strong><?= (int) $metrics['customers'] ?></strong>
    <small>Convertidos</small>
  </article>
  <article class="dashboard-metric dashboard-metric--danger">
    <span>Perdidos</span>
    <strong><?= (int) $metrics['lost'] ?></strong>
    <small>No continuan</small>
  </article>
</section>

<form class="lead-toolbar platform-toolbar" method="get" action="index.php" data-auto-filter-form data-live-search-form data-live-search-target="platform-contacts-table">
  <input type="hidden" name="route" value="platform-contacts">
  <label class="field platform-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Empresa, contacto, email, telefono o notas" data-auto-filter-input>
  </label>
  <label class="field platform-filter-field">
    <span>Tipo</span>
    <select name="type" data-auto-filter-input>
      <?php foreach ($typeOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field platform-filter-field">
    <span>Estado</span>
    <select name="status" data-auto-filter-input>
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
      <h3>Listado de contactos</h3>
      <span data-live-search-count><?= count($contacts) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table platform-table platform-table--payments" id="platform-contacts-table">
      <thead>
        <tr>
          <th>Empresa</th>
          <th>Contacto</th>
          <th>Email</th>
          <th>Telefono</th>
          <th>Tipo</th>
          <th>Estado</th>
          <th>Notas</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contacts as $contact): ?>
          <?php
            $modalId = $contact['type'] === 'lead' ? 'platform-lead-' . $contact['id'] : 'client-edit-' . $contact['id'];
            $notes = (string) ($contact['notes'] ?? '');
          ?>
          <tr class="lead-data-row clickable-row" tabindex="0" data-open-modal="<?= e($modalId) ?>" data-live-search-row>
            <td><strong><?= e($contact['company_name'] ?: 'Sin empresa') ?></strong></td>
            <td><?= e($contact['contact_name'] ?: 'Sin contacto') ?></td>
            <td><?= e($contact['email'] ?: 'Sin email') ?></td>
            <td><?= e($contact['phone'] ?: 'Sin telefono') ?></td>
            <td><span class="source-badge"><?= e($contact['source_label']) ?></span></td>
            <td><span class="status-badge status-badge--<?= e($contact['status_class']) ?>"><?= e($contact['status_label']) ?></span></td>
            <td><?= e($notes !== '' ? substr($notes, 0, 70) . (strlen($notes) > 70 ? '...' : '') : 'Sin notas') ?></td>
            <td>
              <div class="platform-row-actions">
                <?php if ($contact['type'] === 'lead' && $contact['status'] !== 'CONVERTED'): ?>
                  <form method="post">
                    <input type="hidden" name="action" value="convert_platform_lead">
                    <input type="hidden" name="id" value="<?= e($contact['id']) ?>">
                    <button class="support-enter-action" type="submit" aria-label="Convertir <?= e($contact['contact_name']) ?> en cliente">
                      <svg viewBox="0 0 24 24"><path d="M12 5v14m7-7H5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                      <span>Cliente</span>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($contact['type'] === 'client'): ?>
                  <a class="support-enter-action" href="index.php?route=platform-companies&client_id=<?= urlencode($contact['id']) ?>&modal=empresa-create-modal">
                    <svg viewBox="0 0 24 24"><path d="M12 5v14m7-7H5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span>Empresa</span>
                  </a>
                <?php endif; ?>
                <button class="support-edit-action" type="button" data-open-modal="<?= e($modalId) ?>" aria-label="Editar <?= e($contact['company_name'] ?: $contact['contact_name']) ?>">
                  <svg viewBox="0 0 24 24"><path d="M4 17.3V20h2.7L17.9 8.8l-2.7-2.7L4 17.3Zm15.8-10.6a1 1 0 0 0 0-1.4l-1.1-1.1a1 1 0 0 0-1.4 0l-.9.9 2.7 2.7.7-.8Z"/></svg>
                  <span>Editar</span>
                </button>
                <?php if ($contact['type'] === 'lead'): ?>
                  <form method="post" data-confirm-message="Eliminar este lead comercial? Esta accion no se puede deshacer.">
                    <input type="hidden" name="action" value="delete_platform_lead">
                    <input type="hidden" name="id" value="<?= e($contact['id']) ?>">
                    <button class="note-delete-button" type="submit" aria-label="Eliminar lead <?= e($contact['contact_name']) ?>">
                      <svg viewBox="0 0 24 24"><path d="M9 4h6l1 2h4v2H4V6h4l1-2Zm1 6h2v8h-2v-8Zm4 0h2v8h-2v-8ZM7 10h2l1 10h4l1-10h2l-1.2 12H8.2L7 10Z"/></svg>
                    </button>
                  </form>
                <?php else: ?>
                  <form method="post" data-confirm-message="Eliminar este contacto comercial? Si tiene empresas vinculadas, se mantendran pero sin cliente asociado.">
                    <input type="hidden" name="action" value="delete_platform_client">
                    <input type="hidden" name="id" value="<?= e($contact['id']) ?>">
                    <button class="note-delete-button" type="submit" aria-label="Eliminar contacto <?= e($contact['contact_name']) ?>">
                      <svg viewBox="0 0 24 24"><path d="M9 4h6l1 2h4v2H4V6h4l1-2Zm1 6h2v8h-2v-8Zm4 0h2v8h-2v-8ZM7 10h2l1 10h4l1-10h2l-1.2 12H8.2L7 10Z"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr data-live-search-empty <?= $contacts ? 'hidden' : '' ?>><td colspan="8" class="empty-state">No hay contactos que coincidan con los filtros actuales.</td></tr>
      </tbody>
    </table>
  </div>
</section>

<dialog class="modal-card empresa-modal" id="client-create-modal">
  <header>
    <div>
      <h2>Nuevo contacto</h2>
      <p>Registra un contacto comercial antes de crear su empresa CRM.</p>
    </div>
    <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
  </header>
  <?php require __DIR__ . '/partials/platform-client-form.php'; ?>
</dialog>

<?php foreach ($contacts as $contact): ?>
  <?php if ($contact['type'] === 'lead'): ?>
    <?php $lead = $contact['raw']; ?>
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
          <span>Tipo</span>
          <select name="contact_type">
            <option value="lead" selected>Lead</option>
            <option value="client">Cliente CRM</option>
          </select>
        </label>
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
            <?php foreach ($leadStatusOptions as $value => $label): ?>
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
          <button class="primary-action" type="submit">Guardar contacto</button>
        </div>
      </form>
    </dialog>
  <?php else: ?>
    <?php $client = $contact['raw']; ?>
    <dialog class="modal-card empresa-modal" id="client-edit-<?= e($client['id']) ?>">
      <header>
        <div>
          <h2><?= e($client['company_name']) ?></h2>
          <p><?= e($client['email'] ?: 'Sin email') ?> - <?= e(platform_client_status_label($client['status'])) ?></p>
        </div>
        <button class="modal-close-action" type="button" data-close-modal aria-label="Cerrar">Cerrar</button>
      </header>
      <?php require __DIR__ . '/partials/platform-client-form.php'; ?>
    </dialog>
  <?php endif; ?>
<?php endforeach; ?>
