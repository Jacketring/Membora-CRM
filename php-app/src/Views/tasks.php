<div class="page-heading">
  <div>
    <h2>Tareas</h2>
    <p>Organiza seguimientos comerciales, pagos pendientes y acciones operativas del centro.</p>
  </div>
  <button class="primary-action primary-action--compact" data-open-modal="task-modal" type="button">Nueva tarea</button>
</div>

<section class="lead-metrics">
  <article class="lead-metric lead-metric--blue"><span>Pendientes</span><strong><?= $metrics['pending'] ?></strong></article>
  <article class="lead-metric lead-metric--green"><span>Para hoy</span><strong><?= $metrics['today'] ?></strong></article>
  <article class="lead-metric lead-metric--dark"><span>Completadas</span><strong><?= $metrics['completed'] ?></strong></article>
  <article class="lead-metric lead-metric--orange"><span>Vencidas</span><strong><?= $metrics['overdue'] ?></strong></article>
</section>

<form class="lead-toolbar" method="get">
  <input type="hidden" name="route" value="tasks">
  <div class="lead-search"><span>⌕</span><input name="q" value="<?= e($filters['q']) ?>" placeholder="Buscar por titulo, descripcion o responsable"></div>
  <div class="lead-filter-group">
    <select name="status">
      <option value="">Todos</option>
      <option value="PENDING" <?= $filters['status'] === 'PENDING' ? 'selected' : '' ?>>Pendientes</option>
      <option value="COMPLETED" <?= $filters['status'] === 'COMPLETED' ? 'selected' : '' ?>>Completadas</option>
      <option value="CANCELLED" <?= $filters['status'] === 'CANCELLED' ? 'selected' : '' ?>>Canceladas</option>
    </select>
  </div>
  <button class="primary-action primary-action--compact" type="submit">Filtrar</button>
</form>

<section class="leads-table-card">
  <header><div><h3>Listado de tareas</h3><span><?= count($tasks) ?> resultados</span></div></header>
  <div class="leads-table-wrap">
    <table class="leads-table tasks-table">
      <thead><tr><th>Tarea</th><th>Tipo</th><th>Vinculado a</th><th>Responsable</th><th>Vencimiento</th><th>Estado</th><th>Creacion</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($tasks as $task): ?>
        <?php $linked = trim(($task['lead_first_name'] ?? '') . ' ' . ($task['lead_last_name'] ?? '')) ?: trim(($task['member_first_name'] ?? '') . ' ' . ($task['member_last_name'] ?? '')) ?: 'Sin vincular'; ?>
        <tr class="lead-data-row">
          <td><strong><?= e($task['title']) ?></strong><small class="task-description"><?= e($task['description'] ?? 'Sin descripcion') ?></small></td>
          <td><span class="source-badge"><?= e($task['type']) ?></span></td>
          <td><?= e($linked) ?></td>
          <td><?= e($task['assigned_name'] ?? 'Sin asignar') ?></td>
          <td><?= e(format_date($task['due_at'])) ?></td>
          <td><span class="status-badge status-badge--<?= strtolower($task['status']) ?>"><?= e($task['status']) ?></span></td>
          <td><?= e(format_date($task['created_at'])) ?></td>
          <td>
            <div class="row-actions">
              <form method="post"><input type="hidden" name="id" value="<?= e($task['id']) ?>"><input type="hidden" name="status" value="COMPLETED"><button name="action" value="update_task_status">Completar</button></form>
              <form method="post"><input type="hidden" name="id" value="<?= e($task['id']) ?>"><input type="hidden" name="status" value="PENDING"><button name="action" value="update_task_status">Reabrir</button></form>
              <form method="post" onsubmit="return confirm('Eliminar tarea?')"><input type="hidden" name="id" value="<?= e($task['id']) ?>"><button class="danger-action" name="action" value="delete_task">Eliminar</button></form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tasks): ?><tr><td class="leads-empty-cell" colspan="8">No hay tareas.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<dialog id="task-modal" class="modal-card">
  <form method="post">
    <input type="hidden" name="action" value="create_task">
    <header><h2>Nueva tarea</h2><button data-close-modal type="button">Cerrar</button></header>
    <div class="form-grid">
      <label class="field field--wide"><span>Titulo</span><input name="title" required></label>
      <label class="field"><span>Tipo</span><select name="type"><option value="SALES">Comercial</option><option value="RETENTION">Retencion</option><option value="PAYMENT">Pago</option><option value="OPERATIONAL">Operativa</option><option value="OTHER">Otra</option></select></label>
      <label class="field"><span>Vencimiento</span><input name="due_at" type="datetime-local"></label>
      <label class="field field--wide"><span>Responsable</span><select name="assigned_user_id"><option value="">Sin responsable</option><?php foreach ($staff as $staffUser): ?><option value="<?= e($staffUser['id']) ?>"><?= e($staffUser['name']) ?></option><?php endforeach; ?></select></label>
      <label class="field field--wide"><span>Descripcion</span><input name="description"></label>
    </div>
    <button class="primary-action" type="submit">Crear tarea</button>
  </form>
</dialog>
