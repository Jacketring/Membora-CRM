<div class="page-heading">
  <div>
    <h2>Leads</h2>
    <p>Gestiona oportunidades comerciales desde el primer contacto hasta el alta como socio.</p>
  </div>
  <button class="primary-action primary-action--compact" data-open-modal="lead-modal" type="button">Nuevo lead</button>
</div>

<section class="lead-metrics">
  <article class="lead-metric lead-metric--blue"><span>Abiertos</span><strong><?= $metrics['open'] ?></strong></article>
  <article class="lead-metric lead-metric--green"><span>Convertidos</span><strong><?= $metrics['converted'] ?></strong></article>
  <article class="lead-metric lead-metric--orange"><span>Perdidos</span><strong><?= $metrics['lost'] ?></strong></article>
  <article class="lead-metric lead-metric--dark"><span>Conversion</span><strong><?= $metrics['conversion'] ?>%</strong></article>
</section>

<form class="lead-toolbar" method="get">
  <input type="hidden" name="route" value="leads">
  <div class="lead-search"><span>⌕</span><input name="q" value="<?= e($filters['q']) ?>" placeholder="Buscar por nombre, email, telefono o interes"></div>
  <div class="lead-filter-group">
    <select name="stage">
      <option value="">Todas las etapas</option>
      <?php foreach ($stages as $stage): ?>
        <option value="<?= e($stage['id']) ?>" <?= $filters['stage'] === $stage['id'] ? 'selected' : '' ?>><?= e($stage['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">Todos los estados</option>
      <option value="OPEN" <?= $filters['status'] === 'OPEN' ? 'selected' : '' ?>>Abiertos</option>
      <option value="CONVERTED" <?= $filters['status'] === 'CONVERTED' ? 'selected' : '' ?>>Convertidos</option>
      <option value="LOST" <?= $filters['status'] === 'LOST' ? 'selected' : '' ?>>Perdidos</option>
    </select>
  </div>
  <button class="primary-action primary-action--compact" type="submit">Filtrar</button>
</form>

<section class="leads-table-card">
  <header><div><h3>Listado de leads</h3><span><?= count($leads) ?> resultados</span></div></header>
  <div class="leads-table-wrap">
    <table class="leads-table">
      <thead><tr><th>Nombre</th><th>Telefono</th><th>Email</th><th>Origen</th><th>Interes</th><th>Etapa</th><th>Estado</th><th>Creacion</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($leads as $lead): ?>
        <tr class="lead-data-row">
          <td><?= e($lead['first_name'] . ' ' . ($lead['last_name'] ?? '')) ?></td>
          <td><?= e($lead['phone'] ?? 'Sin telefono') ?></td>
          <td><?= e($lead['email'] ?? 'Sin email') ?></td>
          <td><span class="source-badge"><?= e($lead['source']) ?></span></td>
          <td><?= e($lead['interest'] ?? 'Sin interes') ?></td>
          <td>
            <form method="post" class="inline-form">
              <input type="hidden" name="action" value="update_lead_stage">
              <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
              <select class="stage-select stage-select--table" name="pipeline_stage_id" onchange="this.form.submit()">
                <?php foreach ($stages as $stage): ?>
                  <option value="<?= e($stage['id']) ?>" <?= $lead['pipeline_stage_id'] === $stage['id'] ? 'selected' : '' ?>><?= e($stage['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><span class="status-badge status-badge--<?= strtolower($lead['status']) ?>"><?= e($lead['status']) ?></span></td>
          <td><?= e(format_date($lead['created_at'])) ?></td>
          <td>
            <div class="row-actions">
              <form method="post"><input type="hidden" name="id" value="<?= e($lead['id']) ?>"><button name="action" value="convert_lead">Convertir</button></form>
              <form method="post"><input type="hidden" name="id" value="<?= e($lead['id']) ?>"><button name="action" value="mark_lead_lost">Perdido</button></form>
              <form method="post" onsubmit="return confirm('Eliminar lead?')"><input type="hidden" name="id" value="<?= e($lead['id']) ?>"><button class="danger-action" name="action" value="delete_lead">Eliminar</button></form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$leads): ?><tr><td class="leads-empty-cell" colspan="9">No hay leads.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<dialog id="lead-modal" class="modal-card">
  <form method="post">
    <input type="hidden" name="action" value="create_lead">
    <header><h2>Nuevo lead</h2><button data-close-modal type="button">Cerrar</button></header>
    <div class="form-grid">
      <label class="field"><span>Nombre</span><input name="first_name" required></label>
      <label class="field"><span>Apellidos</span><input name="last_name"></label>
      <label class="field"><span>Email</span><input name="email" type="email"></label>
      <label class="field"><span>Telefono</span><input name="phone"></label>
      <label class="field"><span>Origen</span><select name="source"><option value="WALK_IN">Visita presencial</option><option value="WEBSITE">Web</option><option value="PHONE">Telefono</option><option value="SOCIAL_MEDIA">Redes sociales</option><option value="REFERRAL">Recomendacion</option><option value="OTHER">Otro</option></select></label>
      <label class="field"><span>Etapa</span><select name="pipeline_stage_id"><?php foreach ($stages as $stage): ?><option value="<?= e($stage['id']) ?>"><?= e($stage['name']) ?></option><?php endforeach; ?></select></label>
      <label class="field field--wide"><span>Interes</span><input name="interest"></label>
    </div>
    <button class="primary-action" type="submit">Crear lead</button>
  </form>
</dialog>
