<div class="page-heading">
  <div>
    <h2>Alertas</h2>
    <p>Prioriza pagos vencidos, tareas atrasadas, membresias caducadas y socios sin actividad.</p>
  </div>
  <a class="secondary-action" href="index.php?route=alerts">Regenerar alertas</a>
</div>

<section class="lead-metrics" aria-label="Resumen de alertas">
  <article class="lead-metric lead-metric--orange">
    <span>Abiertas</span>
    <strong><?= (int) $metrics['open'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark">
    <span>Alta prioridad</span>
    <strong><?= (int) $metrics['high'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--blue">
    <span>Media</span>
    <strong><?= (int) $metrics['medium'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--green">
    <span>Resueltas</span>
    <strong><?= (int) $metrics['resolved'] ?></strong>
  </article>
</section>

<?php
$statusOptions = [
  '' => 'Todas',
  'OPEN' => 'Abiertas',
  'RESOLVED' => 'Resueltas',
  'DISMISSED' => 'Descartadas',
];
?>

<form class="lead-toolbar member-toolbar" method="get" aria-label="Filtros de alertas" data-auto-filter-form data-live-search-form data-live-search-target="alerts-table">
  <input type="hidden" name="route" value="alerts">
  <label class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Mensaje, socio, lead o tarea" aria-label="Buscar alertas" data-auto-filter-input>
  </label>
  <div class="lead-filter-group">
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="status" value="<?= e($filters['status']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Estado</small>
        <span data-custom-select-label><?= e($statusOptions[$filters['status']] ?? 'Abiertas') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
          <button class="custom-select-option <?= $filters['status'] === $statusValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($statusValue) ?>">
            <?= e($statusLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="type" value="<?= e($filters['type']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Tipo</small>
        <span data-custom-select-label><?= e($typeOptions[$filters['type']] ?? 'Todas') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($typeOptions as $typeValue => $typeLabel): ?>
          <button class="custom-select-option <?= $filters['type'] === $typeValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($typeValue) ?>">
            <?= e($typeLabel) ?>
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
      <h3>Listado de alertas</h3>
      <span data-live-search-count><?= count($alerts) ?> resultados</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table" id="alerts-table">
      <caption class="sr-only">Listado de alertas de riesgo</caption>
      <thead>
        <tr>
          <th scope="col">Alerta</th>
          <th scope="col">Tipo</th>
          <th scope="col">Prioridad</th>
          <th scope="col">Relacionado</th>
          <th scope="col">Detectada</th>
          <th scope="col">Estado</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($alerts as $alert): ?>
          <?php
            $related = 'Sin relacion';
            if (!empty($alert['member_first_name'])) {
                $related = trim($alert['member_first_name'] . ' ' . ($alert['member_last_name'] ?? ''));
            } elseif (!empty($alert['lead_first_name'])) {
                $related = 'Lead: ' . trim($alert['lead_first_name'] . ' ' . ($alert['lead_last_name'] ?? ''));
            } elseif (!empty($alert['task_title'])) {
                $related = 'Tarea: ' . $alert['task_title'];
            } elseif (!empty($alert['class_name'])) {
                $related = 'Clase: ' . $alert['class_name'];
            }
          ?>
          <tr class="lead-data-row" data-live-search-row>
            <td><strong><?= e($alert['message']) ?></strong></td>
            <td><?= e(risk_alert_type_label($alert['type'])) ?></td>
            <td><span class="status-badge status-badge--<?= e(strtolower($alert['severity'])) ?>"><?= e(risk_alert_severity_label($alert['severity'])) ?></span></td>
            <td><?= e($related) ?></td>
            <td><?= e(format_date($alert['detected_at'])) ?></td>
            <td><span class="status-badge status-badge--<?= e(strtolower($alert['status'])) ?>"><?= e(status_label($alert['status'])) ?></span></td>
            <td>
              <div class="row-actions">
                <?php if ($alert['status'] !== 'RESOLVED'): ?>
                  <form method="post">
                    <input type="hidden" name="action" value="update_risk_alert_status">
                    <input type="hidden" name="id" value="<?= e($alert['id']) ?>">
                    <input type="hidden" name="status" value="RESOLVED">
                    <button class="icon-action" type="submit" title="Resolver alerta" aria-label="Resolver alerta">
                      <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9.55 17.6-5.3-5.3 1.4-1.4 3.9 3.9 8.8-8.8 1.4 1.4-10.2 10.2Z"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($alert['status'] !== 'DISMISSED'): ?>
                  <form method="post">
                    <input type="hidden" name="action" value="update_risk_alert_status">
                    <input type="hidden" name="id" value="<?= e($alert['id']) ?>">
                    <input type="hidden" name="status" value="DISMISSED">
                    <button class="icon-action danger-action" type="submit" title="Descartar alerta" aria-label="Descartar alerta">
                      <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6.4 19-1.4-1.4 5.6-5.6L5 6.4 6.4 5l5.6 5.6L17.6 5 19 6.4 13.4 12l5.6 5.6-1.4 1.4-5.6-5.6L6.4 19Z"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$alerts): ?>
          <tr data-live-search-empty>
            <td class="leads-empty-cell" colspan="7">No hay alertas que coincidan con los filtros actuales.</td>
          </tr>
        <?php else: ?>
          <tr data-live-search-empty hidden>
            <td class="leads-empty-cell" colspan="7">No hay alertas que coincidan con la busqueda actual.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
