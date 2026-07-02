<div class="page-heading">
  <div>
    <h2>Auditoria</h2>
    <p>Consulta las acciones registradas por usuario, fecha, modulo y entidad afectada.</p>
  </div>
</div>

<section class="lead-metrics" aria-label="Resumen de auditoria">
  <article class="lead-metric lead-metric--blue">
    <span>Hoy</span>
    <strong><?= (int) $metrics['today'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--green">
    <span>Ultimos 7 dias</span>
    <strong><?= (int) $metrics['week'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--orange">
    <span>Cambios</span>
    <strong><?= (int) $metrics['writes'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark">
    <span>Eliminaciones</span>
    <strong><?= (int) $metrics['deletes'] ?></strong>
  </article>
</section>

<form class="lead-toolbar member-toolbar" method="get" aria-label="Filtros de auditoria" data-auto-filter-form data-live-search-form data-live-search-target="audit-table">
  <input type="hidden" name="route" value="audit">
  <label class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Accion, entidad, usuario o dato" aria-label="Buscar auditoria" data-auto-filter-input>
  </label>
  <div class="lead-filter-group">
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="action_filter" value="<?= e($filters['action']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Accion</small>
        <span data-custom-select-label><?= e($actionOptions[$filters['action']] ?? 'Todas') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($actionOptions as $actionValue => $actionLabel): ?>
          <button class="custom-select-option <?= $filters['action'] === $actionValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($actionValue) ?>">
            <?= e($actionLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="user_id" value="<?= e($filters['user_id']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Usuario</small>
        <span data-custom-select-label>
          <?php
            $selectedUserLabel = 'Todos';
            foreach ($staff as $staffMember) {
                if ($staffMember['id'] === $filters['user_id']) {
                    $selectedUserLabel = $staffMember['name'];
                    break;
                }
            }
          ?>
          <?= e($selectedUserLabel) ?>
        </span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <button class="custom-select-option <?= $filters['user_id'] === '' ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="">Todos</button>
        <?php foreach ($staff as $staffMember): ?>
          <button class="custom-select-option <?= $filters['user_id'] === $staffMember['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($staffMember['id']) ?>">
            <?= e($staffMember['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <label class="filter-control">
      <span>Desde</span>
      <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" data-auto-filter-input>
    </label>
    <label class="filter-control">
      <span>Hasta</span>
      <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" data-auto-filter-input>
    </label>
  </div>
  <button class="primary-action primary-action--compact" type="submit">Filtrar</button>
</form>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Registro de actividad</h3>
      <span data-live-search-count><?= count($logs) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table" id="audit-table">
      <caption class="sr-only">Registro de auditoria</caption>
      <thead>
        <tr>
          <th scope="col">Fecha</th>
          <th scope="col">Usuario</th>
          <th scope="col">Accion</th>
          <th scope="col">Modulo</th>
          <th scope="col">Area</th>
          <th scope="col">IP</th>
          <th scope="col">Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
          <tr class="lead-data-row" data-live-search-row>
            <td><?= e(format_date($log['created_at'])) ?></td>
            <td>
              <strong><?= e($log['user_name'] ?: 'Sistema') ?></strong>
              <small class="table-subtext"><?= e($log['user_email'] ?: 'Sin usuario autenticado') ?></small>
            </td>
            <td><?= e(audit_action_label($log['action'])) ?></td>
            <td>
              <strong><?= e(audit_entity_label($log['entity_type'])) ?></strong>
            </td>
            <td><?= e(audit_area_label($log['route'])) ?></td>
            <td><?= e($log['ip_address'] ?: 'Sin IP') ?></td>
            <td>
              <details class="audit-details">
                <summary>Ver</summary>
                <p><?= e(audit_metadata_summary($log['metadata'])) ?></p>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?>
          <tr data-live-search-empty>
            <td class="leads-empty-cell" colspan="7">No hay registros de auditoria que coincidan con los filtros actuales.</td>
          </tr>
        <?php else: ?>
          <tr data-live-search-empty hidden>
            <td class="leads-empty-cell" colspan="7">No hay registros de auditoria que coincidan con la busqueda actual.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
