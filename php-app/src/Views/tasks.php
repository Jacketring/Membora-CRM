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

<?php
$taskTypeOptions = [
  '' => 'Todos',
  'SALES' => task_type_label('SALES'),
  'RETENTION' => task_type_label('RETENTION'),
  'PAYMENT' => task_type_label('PAYMENT'),
  'OPERATIONAL' => task_type_label('OPERATIONAL'),
  'OTHER' => task_type_label('OTHER'),
];
$taskStatusOptions = [
  '' => 'Todos',
  'PENDING' => 'Pendientes',
  'COMPLETED' => 'Completadas',
  'CANCELLED' => 'Canceladas',
];
?>

<form class="lead-toolbar task-toolbar" method="get">
  <input type="hidden" name="route" value="tasks">
  <div class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Titulo, descripcion, socio, lead o responsable">
  </div>
  <div class="lead-filter-group">
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="type" value="<?= e($filters['type']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Tipo</small>
        <span data-custom-select-label><?= e($taskTypeOptions[$filters['type']] ?? 'Todos') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($taskTypeOptions as $typeValue => $typeLabel): ?>
          <button class="custom-select-option <?= $filters['type'] === $typeValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($typeValue) ?>">
            <?= e($typeLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="status" value="<?= e($filters['status']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Estado</small>
        <span data-custom-select-label><?= e($taskStatusOptions[$filters['status']] ?? 'Todos') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($taskStatusOptions as $statusValue => $statusLabel): ?>
          <button class="custom-select-option <?= $filters['status'] === $statusValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($statusValue) ?>">
            <?= e($statusLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
      $selectedStaffLabel = 'Todos';
      foreach ($staff as $staffUser) {
        if ($filters['assigned_user_id'] === $staffUser['id']) {
          $selectedStaffLabel = $staffUser['name'];
          break;
        }
      }
    ?>
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="assigned_user_id" value="<?= e($filters['assigned_user_id']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Responsable</small>
        <span data-custom-select-label><?= e($selectedStaffLabel) ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <button class="custom-select-option <?= $filters['assigned_user_id'] === '' ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="">Todos</button>
        <?php foreach ($staff as $staffUser): ?>
          <button class="custom-select-option <?= $filters['assigned_user_id'] === $staffUser['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($staffUser['id']) ?>">
            <?= e($staffUser['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <label class="filter-control filter-control--date date-filter-control">
      <span>Desde</span>
      <input name="date_from" type="date" value="<?= e($filters['date_from']) ?>">
    </label>
    <label class="filter-control filter-control--date date-filter-control">
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
            $linkedMemberIds = array_values(array_filter(array_map('trim', explode('||', (string) ($task['linked_member_ids'] ?? '')))));
            if (!$linkedMembers && $memberName !== '') {
                $linkedMembers = [$memberName];
                if (!empty($task['member_id'])) {
                    $linkedMemberIds = [$task['member_id']];
                }
            }
          ?>
          <tr class="lead-data-row clickable-row" data-open-modal="task-detail-<?= e($task['id']) ?>" tabindex="0" role="button" aria-label="Editar tarea <?= e($task['title']) ?>">
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
                <button class="icon-action" data-open-modal="task-detail-<?= e($task['id']) ?>" type="button" title="Editar tarea" aria-label="Editar tarea">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4.8L19.4 9.4a2.1 2.1 0 0 0 0-3L17.6 4.6a2.1 2.1 0 0 0-3 0L4 15.2V20Zm2-2v-1.95l7.25-7.25 1.95 1.95L7.95 18H6Zm10.6-8.65L14.65 7.4 16 6.05 17.95 8l-1.35 1.35Z"/></svg>
                </button>
                <?php if ($task['status'] !== 'COMPLETED'): ?>
                  <form method="post">
                    <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                    <input type="hidden" name="status" value="COMPLETED">
                    <button class="icon-action success-action" name="action" value="update_task_status" title="Marcar completada" aria-label="Marcar completada">
                      <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L21 7.6l-1.4-1.4L9.2 16.6Z"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($task['status'] !== 'PENDING'): ?>
                  <form method="post">
                    <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                    <input type="hidden" name="status" value="PENDING">
                    <button class="icon-action" name="action" value="update_task_status" title="Reabrir tarea" aria-label="Reabrir tarea">
                      <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5V2L7 7l5 5V9a5 5 0 1 1-4.58 7H5.26A7 7 0 1 0 12 5Z"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
                <form method="post" data-confirm-message="Eliminar esta tarea? Esta accion no se puede deshacer.">
                  <input type="hidden" name="action" value="delete_task">
                  <input type="hidden" name="id" value="<?= e($task['id']) ?>">
                  <button class="icon-action danger-action" type="submit" title="Eliminar tarea" aria-label="Eliminar tarea">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 21a2 2 0 0 1-2-2V8h14v11a2 2 0 0 1-2 2H7ZM9 6V4h6v2h5v2H4V6h5Zm0 5v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>
                  </button>
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

<?php foreach ($tasks as $task): ?>
  <?php
    $selectedMemberIds = array_values(array_filter(array_map('trim', explode('||', (string) ($task['linked_member_ids'] ?? '')))));
    if (!$selectedMemberIds && !empty($task['member_id'])) {
        $selectedMemberIds = [$task['member_id']];
    }
  ?>
  <dialog id="task-detail-<?= e($task['id']) ?>" class="modal-card lead-detail-modal">
    <form method="post">
      <input type="hidden" name="action" value="update_task">
      <input type="hidden" name="id" value="<?= e($task['id']) ?>">
      <header>
        <div>
          <h2><?= e($task['title']) ?></h2>
          <p><?= e(task_type_label($task['type'])) ?> &middot; <?= e(status_label($task['status'])) ?></p>
        </div>
        <button data-close-modal type="button">Cerrar</button>
      </header>

      <div class="form-grid">
        <label class="field field--wide">
          <span>Titulo</span>
          <input name="title" required value="<?= e($task['title']) ?>">
        </label>
        <div class="field">
          <span>Tipo</span>
          <div class="custom-select custom-select--field" data-custom-select>
            <input type="hidden" name="type" value="<?= e($task['type']) ?>" data-custom-select-value>
            <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
              <span data-custom-select-label><?= e(task_type_label($task['type'])) ?></span>
            </button>
            <div class="custom-select-menu" data-custom-select-menu hidden>
            <?php foreach (['SALES', 'RETENTION', 'PAYMENT', 'OPERATIONAL', 'OTHER'] as $type): ?>
              <button class="custom-select-option <?= $task['type'] === $type ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($type) ?>"><?= e(task_type_label($type)) ?></button>
            <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="field">
          <span>Estado</span>
          <div class="custom-select custom-select--field" data-custom-select>
            <input type="hidden" name="status" value="<?= e($task['status']) ?>" data-custom-select-value>
            <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
              <span data-custom-select-label><?= e(status_label($task['status'])) ?></span>
            </button>
            <div class="custom-select-menu" data-custom-select-menu hidden>
            <?php foreach (['PENDING', 'COMPLETED', 'CANCELLED'] as $status): ?>
              <button class="custom-select-option <?= $task['status'] === $status ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($status) ?>"><?= e(status_label($status)) ?></button>
            <?php endforeach; ?>
            </div>
          </div>
        </div>
        <label class="field">
          <span>Vencimiento</span>
          <input name="due_at" type="datetime-local" value="<?= $task['due_at'] ? e(date('Y-m-d\TH:i', strtotime($task['due_at']))) : '' ?>">
        </label>
        <div class="field">
          <span>Responsable</span>
          <div class="custom-select custom-select--field" data-custom-select>
            <input type="hidden" name="assigned_user_id" value="<?= e($task['assigned_user_id'] ?? '') ?>" data-custom-select-value>
            <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
              <span data-custom-select-label><?= e($task['assigned_name'] ?: 'Sin responsable') ?></span>
            </button>
            <div class="custom-select-menu" data-custom-select-menu hidden>
            <button class="custom-select-option <?= empty($task['assigned_user_id']) ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="">Sin responsable</button>
            <?php foreach ($staff as $staffUser): ?>
              <button class="custom-select-option <?= $task['assigned_user_id'] === $staffUser['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($staffUser['id']) ?>"><?= e($staffUser['name']) ?> - <?= e($staffUser['role_key']) ?></button>
            <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="field field--wide">
          <span>Socios vinculados</span>
          <div class="member-picker-shell" data-member-picker>
            <input class="member-picker-search" type="search" placeholder="Buscar socio por nombre, email o telefono" data-member-search>
            <div class="member-picker">
              <?php foreach ($members as $member): ?>
                <?php
                  $memberName = trim($member['first_name'] . ' ' . ($member['last_name'] ?? ''));
                  $searchText = strtolower($memberName . ' ' . ($member['email'] ?? '') . ' ' . ($member['phone'] ?? ''));
                ?>
                <label data-member-option data-search="<?= e($searchText) ?>">
                  <input type="checkbox" name="member_ids[]" value="<?= e($member['id']) ?>" <?= in_array($member['id'], $selectedMemberIds, true) ? 'checked' : '' ?>>
                  <span>
                    <strong><?= e($memberName) ?></strong>
                    <small><?= e($member['email'] ?: ($member['phone'] ?: 'Sin contacto')) ?></small>
                  </span>
                </label>
              <?php endforeach; ?>
              <?php if (!$members): ?>
                <p>No hay socios disponibles para vincular.</p>
              <?php endif; ?>
              <p class="member-picker-empty" data-member-empty hidden>No hay socios que coincidan con la busqueda.</p>
            </div>
          </div>
        </div>
        <label class="field field--wide">
          <span>Descripcion</span>
          <input name="description" value="<?= e($task['description']) ?>" placeholder="Notas internas de la tarea">
        </label>
      </div>

      <button class="primary-action" type="submit">Guardar cambios</button>
    </form>
  </dialog>
<?php endforeach; ?>

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
      <div class="field">
        <span>Tipo</span>
        <div class="custom-select custom-select--field" data-custom-select>
          <input type="hidden" name="type" value="SALES" data-custom-select-value>
          <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
            <span data-custom-select-label><?= e(task_type_label('SALES')) ?></span>
          </button>
          <div class="custom-select-menu" data-custom-select-menu hidden>
            <?php foreach (['SALES', 'RETENTION', 'PAYMENT', 'OPERATIONAL', 'OTHER'] as $type): ?>
              <button class="custom-select-option <?= $type === 'SALES' ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($type) ?>"><?= e(task_type_label($type)) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <label class="field">
        <span>Vencimiento</span>
        <input name="due_at" type="datetime-local">
      </label>
      <div class="field field--wide">
        <span>Responsable</span>
        <div class="custom-select custom-select--field" data-custom-select>
          <input type="hidden" name="assigned_user_id" value="" data-custom-select-value>
          <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
            <span data-custom-select-label>Sin responsable</span>
          </button>
          <div class="custom-select-menu" data-custom-select-menu hidden>
          <button class="custom-select-option selected" type="button" data-custom-select-option data-value="">Sin responsable</button>
          <?php foreach ($staff as $staffUser): ?>
            <button class="custom-select-option" type="button" data-custom-select-option data-value="<?= e($staffUser['id']) ?>"><?= e($staffUser['name']) ?> - <?= e($staffUser['role_key']) ?></button>
          <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="field field--wide">
        <span>Socios vinculados</span>
        <div class="member-picker-shell" data-member-picker>
          <input class="member-picker-search" type="search" placeholder="Buscar socio por nombre, email o telefono" data-member-search>
          <div class="member-picker">
            <?php foreach ($members as $member): ?>
              <?php
                $memberName = trim($member['first_name'] . ' ' . ($member['last_name'] ?? ''));
                $searchText = strtolower($memberName . ' ' . ($member['email'] ?? '') . ' ' . ($member['phone'] ?? ''));
              ?>
              <label data-member-option data-search="<?= e($searchText) ?>">
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
            <p class="member-picker-empty" data-member-empty hidden>No hay socios que coincidan con la busqueda.</p>
          </div>
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
