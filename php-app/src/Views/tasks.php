<div class="page-heading">
  <div>
    <h2>Tareas</h2>
    <p>Organiza seguimientos comerciales, pagos pendientes y acciones operativas del centro.</p>
  </div>
  <button class="primary-action primary-action--compact" data-open-modal="task-modal" type="button">Nueva tarea</button>
</div>

<section class="lead-metrics">
  <article class="lead-metric lead-metric--blue">
    <span>Pendientes</span>
    <strong><?= (int) $metrics['pending'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--green">
    <span>Para hoy</span>
    <strong><?= (int) $metrics['today'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark">
    <span>Completadas</span>
    <strong><?= (int) $metrics['completed'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--orange">
    <span>Vencidas</span>
    <strong><?= (int) $metrics['overdue'] ?></strong>
  </article>
</section>

<form class="lead-toolbar" method="get">
  <input type="hidden" name="route" value="tasks">
  <div class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Titulo, descripcion, socio, lead o responsable">
  </div>
  <div class="lead-filter-group">
    <label class="filter-control filter-control--select">
      <span>Tipo</span>
      <select name="type">
        <option value="">Todos</option>
        <?php foreach (['SALES', 'RETENTION', 'PAYMENT', 'OPERATIONAL', 'OTHER'] as $type): ?>
          <option value="<?= e($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>><?= e(task_type_label($type)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="filter-control filter-control--select">
      <span>Estado</span>
      <select name="status">
        <option value="">Todos</option>
        <option value="PENDING" <?= $filters['status'] === 'PENDING' ? 'selected' : '' ?>>Pendientes</option>
        <option value="COMPLETED" <?= $filters['status'] === 'COMPLETED' ? 'selected' : '' ?>>Completadas</option>
        <option value="CANCELLED" <?= $filters['status'] === 'CANCELLED' ? 'selected' : '' ?>>Canceladas</option>
      </select>
    </label>
    <label class="filter-control filter-control--select">
      <span>Responsable</span>
      <select name="assigned_user_id">
        <option value="">Todos</option>
        <?php foreach ($staff as $staffUser): ?>
          <option value="<?= e($staffUser['id']) ?>" <?= $filters['assigned_user_id'] === $staffUser['id'] ? 'selected' : '' ?>><?= e($staffUser['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="filter-control filter-control--date">
      <span>Desde</span>
      <input name="date_from" type="date" value="<?= e($filters['date_from']) ?>">
    </label>
    <label class="filter-control filter-control--date">
      <span>Hasta</span>
      <input name="date_to" type="date" value="<?= e($filters['date_to']) ?>">
    </label>
  </div>
  <button class="primary-action primary-action--compact" type="submit">Filtrar</button>
</form>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Listado de tareas</h3>
      <span><?= count($tasks) ?> resultados</span>
    </div>
  </header>

  <div class="leads-table-wrap">
    <table class="leads-table tasks-table">
      <thead>
        <tr>
          <th>Tarea</th>
          <th>Tipo</th>
          <th>Vinculado a</th>
          <th>Responsable</th>
          <th>Vencimiento</th>
          <th>Estado</th>
          <th>Creacion</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tasks as $task): ?>
          <?php
            $leadName = trim(($task['lead_first_name'] ?? '') . ' ' . ($task['lead_last_name'] ?? ''));
            $memberName = trim(($task['member_first_name'] ?? '') . ' ' . ($task['member_last_name'] ?? ''));
            $linkedMembers = array_values(array_filter(array_map('trim', explode('||', (string) ($task['linked_member_names'] ?? '')))));
            if (!$linkedMembers && $memberName !== '') {
                $linkedMembers = [$memberName];
            }
          ?>
          <tr class="lead-data-row">
            <td>
              <strong><?= e($task['title']) ?></strong>
              <small class="task-description"><?= e($task['description'] ?: 'Sin descripcion') ?></small>
            </td>
            <td><span class="source-badge"><?= e(task_type_label($task['type'])) ?></span></td>
            <td>
              <?php if ($linkedMembers): ?>
                <details class="linked-members">
                  <summary>
                    <span><?= e($linkedMembers[0]) ?></span>
                    <?php if (count($linkedMembers) > 1): ?>
                      <strong>+<?= count($linkedMembers) - 1 ?></strong>
                    <?php endif; ?>
                  </summary>
                  <?php if (count($linkedMembers) > 1): ?>
                    <div>
                      <?php foreach ($linkedMembers as $linkedMember): ?>
                        <span><?= e($linkedMember) ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </details>
              <?php elseif ($leadName !== ''): ?>
                <?= e($leadName) ?>
              <?php else: ?>
                <span class="muted-text">Sin vincular</span>
              <?php endif; ?>
            </td>
            <td><?= e($task['assigned_name'] ?: 'Sin asignar') ?></td>
            <td><?= e(format_date($task['due_at'])) ?></td>
            <td>
              <span class="status-badge status-badge--<?= e(strtolower($task['status'])) ?>">
                <?= e(status_label($task['status'])) ?>
              </span>
            </td>
            <td><?= e(format_date($task['created_at'])) ?></td>
            <td>
              <div class="row-actions">
                <?php if ($task['status'] !== 'COMPLETED'): ?>
                  <form method="post">
                    <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                    <input type="hidden" name="status" value="COMPLETED">
                    <button name="action" value="update_task_status">Completar</button>
                  </form>
                <?php endif; ?>
                <?php if ($task['status'] !== 'PENDING'): ?>
                  <form method="post">
                    <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                    <input type="hidden" name="status" value="PENDING">
                    <button name="action" value="update_task_status">Reabrir</button>
                  </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Eliminar esta tarea?')">
                  <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                  <button class="danger-action" name="action" value="delete_task">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$tasks): ?>
          <tr>
            <td class="leads-empty-cell" colspan="8">No hay tareas que coincidan con los filtros actuales.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<dialog id="task-modal" class="modal-card">
  <form method="post" data-prevent-double-submit>
    <input type="hidden" name="action" value="create_task">
    <input type="hidden" name="form_token" value="<?= e(form_token('create_task')) ?>">
    <header>
      <h2>Nueva tarea</h2>
      <button data-close-modal type="button">Cerrar</button>
    </header>
    <div class="form-grid">
      <label class="field field--wide">
        <span>Titulo</span>
        <input name="title" required placeholder="Ej. Llamar para confirmar renovacion">
      </label>
      <label class="field">
        <span>Tipo</span>
        <select name="type">
          <option value="SALES">Comercial</option>
          <option value="RETENTION">Retencion</option>
          <option value="PAYMENT">Pago</option>
          <option value="OPERATIONAL">Operativa</option>
          <option value="OTHER">Otra</option>
        </select>
      </label>
      <label class="field">
        <span>Vencimiento</span>
        <input name="due_at" type="datetime-local">
      </label>
      <label class="field field--wide">
        <span>Responsable</span>
        <select name="assigned_user_id">
          <option value="">Sin responsable</option>
          <?php foreach ($staff as $staffUser): ?>
            <option value="<?= e($staffUser['id']) ?>"><?= e($staffUser['name']) ?> - <?= e($staffUser['role_key']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="field field--wide">
        <span>Socios vinculados</span>
        <div class="member-picker">
          <?php foreach ($members as $member): ?>
            <?php $memberName = trim($member['first_name'] . ' ' . ($member['last_name'] ?? '')); ?>
            <label>
              <input type="checkbox" name="member_ids[]" value="<?= e($member['id']) ?>">
              <span>
                <strong><?= e($memberName) ?></strong>
                <small><?= e($member['email'] ?: ($member['phone'] ?: 'Sin contacto')) ?></small>
              </span>
            </label>
          <?php endforeach; ?>
          <?php if (!$members): ?>
            <p>No hay socios disponibles para vincular.</p>
          <?php endif; ?>
        </div>
      </div>
      <label class="field field--wide">
        <span>Descripcion</span>
        <input name="description" placeholder="Notas internas de la tarea">
      </label>
    </div>
    <button class="primary-action" type="submit">Crear tarea</button>
  </form>
</dialog>
