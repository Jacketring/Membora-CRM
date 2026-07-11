<div class="page-heading">
  <div>
    <h2>Check-ins</h2>
    <p>Registra entradas de socios, asistencia manual y check-ins asociados a reservas.</p>
  </div>
  <button class="primary-action primary-action--compact" data-open-modal="checkin-modal" type="button">Nuevo check-in</button>
</div>

<section class="lead-metrics" aria-label="Resumen de check-ins">
  <article class="lead-metric lead-metric--green">
    <span>Hoy</span>
    <strong><?= (int) $metrics['today'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--blue">
    <span>Ultimos 7 días</span>
    <strong><?= (int) $metrics['week'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--orange">
    <span>Manuales</span>
    <strong><?= (int) $metrics['manual'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark">
    <span>Con clase</span>
    <strong><?= (int) $metrics['with_class'] ?></strong>
  </article>
</section>

<form class="lead-toolbar member-toolbar" method="get" aria-label="Filtros de check-ins" data-auto-filter-form data-live-search-form data-live-search-target="checkins-table">
  <input type="hidden" name="route" value="checkins">
  <label class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Socio, email, clase o nota" aria-label="Buscar check-ins" data-auto-filter-input>
  </label>
  <div class="lead-filter-group">
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
      <h3>Historial de check-ins</h3>
      <span data-live-search-count><?= count($checkins) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table" id="checkins-table">
      <caption class="sr-only">Listado de check-ins de socios</caption>
      <thead>
        <tr>
          <th scope="col">Socio</th>
          <th scope="col">Fecha y hora</th>
          <th scope="col">Clase</th>
          <th scope="col">Método</th>
          <th scope="col">Registrado por</th>
          <th scope="col">Notas</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($checkins as $checkin): ?>
          <?php $memberName = trim($checkin['first_name'] . ' ' . ($checkin['last_name'] ?? '')); ?>
          <tr class="lead-data-row" data-live-search-row>
            <td>
              <strong><?= e($memberName) ?></strong>
              <small class="table-subtext"><?= e($checkin['email'] ?: ($checkin['phone'] ?: 'Sin contacto')) ?></small>
            </td>
            <td><?= e(format_date($checkin['checked_in_at'])) ?></td>
            <td>
              <?php if (!empty($checkin['class_name'])): ?>
                <strong><?= e($checkin['class_name']) ?></strong>
                <small class="table-subtext"><?= e(format_time($checkin['starts_at'])) ?> - <?= e(format_time($checkin['ends_at'])) ?></small>
              <?php else: ?>
                <span class="muted-cell">Entrada general</span>
              <?php endif; ?>
            </td>
            <td><?= e(checkin_method_label($checkin['method'])) ?></td>
            <td><?= e($checkin['created_by_name'] ?: 'Sistema') ?></td>
            <td><?= e($checkin['notes'] ? substr($checkin['notes'], 0, 70) . (strlen($checkin['notes']) > 70 ? '...' : '') : 'Sin notas') ?></td>
            <td>
              <form method="post" data-confirm-message="Eliminar este check-in?">
                <input type="hidden" name="action" value="delete_checkin">
                <input type="hidden" name="id" value="<?= e($checkin['id']) ?>">
                <button class="icon-action danger-action" type="submit" title="Eliminar check-in" aria-label="Eliminar check-in de <?= e($memberName) ?>">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 21a2 2 0 0 1-2-2V8h14v11a2 2 0 0 1-2 2H7ZM9 6V4h6v2h5v2h5v2H4V6h5Zm0 5v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$checkins): ?>
          <tr data-live-search-empty>
            <td class="leads-empty-cell" colspan="7">No hay check-ins que coincidan con los filtros actuales.</td>
          </tr>
        <?php else: ?>
          <tr data-live-search-empty hidden>
            <td class="leads-empty-cell" colspan="7">No hay check-ins que coincidan con la búsqueda actual.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<dialog id="checkin-modal" class="modal-card" aria-labelledby="checkin-modal-title">
  <form method="post" data-checkin-form>
    <header>
      <h2 id="checkin-modal-title">Nuevo check-in</h2>
      <button data-close-modal type="button">Cerrar</button>
    </header>
    <?php require __DIR__ . '/partials/checkin-form.php'; ?>
    <button class="primary-action" type="submit">Registrar check-in</button>
  </form>
</dialog>
