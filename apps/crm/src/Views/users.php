<div class="page-heading">
  <div>
    <h2>Usuarios</h2>
    <p>Gestiona el personal interno que puede acceder a la plataforma. Los socios/clientes no aparecen en esta sección.</p>
  </div>
  <button class="primary-action primary-action--compact" data-open-modal="user-modal" type="button">Nuevo usuario</button>
</div>

<section class="lead-metrics" aria-label="Resumen de usuarios internos">
  <article class="lead-metric lead-metric--green">
    <span>Activos</span>
    <strong><?= (int) $metrics['active'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--orange">
    <span>Inactivos</span>
    <strong><?= (int) $metrics['inactive'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--blue">
    <span>Administradores</span>
    <strong><?= (int) $metrics['admins'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark">
    <span>Total</span>
    <strong><?= (int) $metrics['total'] ?></strong>
  </article>
</section>

<?php
$statusOptions = [
  '' => 'Todos',
  'ACTIVE' => 'Activos',
  'INACTIVE' => 'Inactivos',
];

$selectedRoleLabel = 'Todos';
foreach ($roles as $role) {
    if ($filters['role_id'] === $role['id']) {
        $selectedRoleLabel = role_label($role['role_key']);
        break;
    }
}
?>

<form class="lead-toolbar member-toolbar" method="get" aria-label="Filtros de usuarios internos" data-auto-filter-form data-live-search-form data-live-search-target="users-table">
  <input type="hidden" name="route" value="users">
  <label class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Nombre, email o rol" data-auto-filter-input>
  </label>
  <div class="lead-filter-group">
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="role_id" value="<?= e($filters['role_id']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Rol</small>
        <span data-custom-select-label><?= e($selectedRoleLabel) ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <button class="custom-select-option <?= $filters['role_id'] === '' ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="">Todos</button>
        <?php foreach ($roles as $role): ?>
          <button class="custom-select-option <?= $filters['role_id'] === $role['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($role['id']) ?>">
            <?= e(role_label($role['role_key'])) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="status" value="<?= e($filters['status']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Estado</small>
        <span data-custom-select-label><?= e($statusOptions[$filters['status']] ?? 'Todos') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
          <button class="custom-select-option <?= $filters['status'] === $statusValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($statusValue) ?>">
            <?= e($statusLabel) ?>
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
      <h3>Listado de usuarios</h3>
      <span data-live-search-count><?= count($users) ?> resultados</span>
    </div>
  </header>

  <div class="leads-table-wrap">
    <table class="leads-table users-table" id="users-table">
      <caption class="sr-only">Listado de usuarios internos de la plataforma</caption>
      <thead>
        <tr>
          <th scope="col">Nombre</th>
          <th scope="col">Email</th>
          <th scope="col">Rol</th>
          <th scope="col">Estado</th>
          <th scope="col">Ultimo acceso</th>
          <th scope="col">Creación</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $internalUser): ?>
          <tr class="lead-data-row clickable-row" data-open-modal="user-detail-<?= e($internalUser['id']) ?>" tabindex="0" role="button" aria-label="Editar usuario <?= e($internalUser['name']) ?>" data-live-search-row>
            <td>
              <div class="member-identity">
                <span class="member-avatar member-avatar--initials" aria-hidden="true"><?= e(initials($internalUser['name'])) ?></span>
                <strong><?= e($internalUser['name']) ?></strong>
              </div>
            </td>
            <td><?= e($internalUser['email']) ?></td>
            <td><span class="source-badge"><?= e(role_label($internalUser['role_key'])) ?></span></td>
            <td>
              <span class="status-badge status-badge--<?= e(strtolower($internalUser['status'])) ?>">
                <?= e(status_label($internalUser['status'])) ?>
              </span>
            </td>
            <td><?= e(format_date($internalUser['last_login_at'])) ?></td>
            <td><?= e(format_date($internalUser['created_at'])) ?></td>
            <td>
              <div class="row-actions">
                <button class="icon-action" data-open-modal="user-detail-<?= e($internalUser['id']) ?>" type="button" title="Editar usuario" aria-label="Editar usuario <?= e($internalUser['name']) ?>">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4.8L19.4 9.4a2.1 2.1 0 0 0 0-3L17.6 4.6a2.1 2.1 0 0 0-3 0L4 15.2V20Zm2-2v-1.95l7.25-7.25 1.95 1.95L7.95 18H6Zm10.6-8.65L14.65 7.4 16 6.05 17.95 8l-1.35 1.35Z"/></svg>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$users): ?>
          <tr data-live-search-empty>
            <td class="leads-empty-cell" colspan="7">No hay usuarios internos que coincidan con los filtros actuales.</td>
          </tr>
        <?php else: ?>
          <tr data-live-search-empty hidden>
            <td class="leads-empty-cell" colspan="7">No hay usuarios internos que coincidan con la búsqueda actual.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($users as $internalUser): ?>
  <dialog id="user-detail-<?= e($internalUser['id']) ?>" class="modal-card lead-detail-modal" aria-labelledby="user-detail-title-<?= e($internalUser['id']) ?>">
    <form method="post">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="id" value="<?= e($internalUser['id']) ?>">
      <header>
        <div>
          <h2 id="user-detail-title-<?= e($internalUser['id']) ?>"><?= e($internalUser['name']) ?></h2>
          <p><?= e(role_label($internalUser['role_key'])) ?> &middot; <?= e(status_label($internalUser['status'])) ?></p>
        </div>
        <button data-close-modal type="button">Cerrar</button>
      </header>

      <div class="form-grid">
        <label class="field">
          <span>Nombre</span>
          <input name="name" required value="<?= e($internalUser['name']) ?>">
        </label>
        <label class="field">
          <span>Email</span>
          <input name="email" type="email" required value="<?= e($internalUser['email']) ?>">
        </label>
        <div class="field">
          <span>Rol</span>
          <div class="custom-select custom-select--field" data-custom-select>
            <input type="hidden" name="role_id" value="<?= e($internalUser['role_id']) ?>" data-custom-select-value>
            <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
              <span data-custom-select-label><?= e(role_label($internalUser['role_key'])) ?></span>
            </button>
            <div class="custom-select-menu" data-custom-select-menu hidden>
              <?php foreach ($roles as $role): ?>
                <button class="custom-select-option <?= $internalUser['role_id'] === $role['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($role['id']) ?>">
                  <?= e(role_label($role['role_key'])) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="field">
          <span>Estado</span>
          <div class="custom-select custom-select--field" data-custom-select>
            <input type="hidden" name="status" value="<?= e($internalUser['status']) ?>" data-custom-select-value>
            <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
              <span data-custom-select-label><?= e(status_label($internalUser['status'])) ?></span>
            </button>
            <div class="custom-select-menu" data-custom-select-menu hidden>
              <?php foreach (['ACTIVE', 'INACTIVE'] as $status): ?>
                <button class="custom-select-option <?= $internalUser['status'] === $status ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($status) ?>">
                  <?= e(status_label($status)) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <label class="field field--wide">
          <span>Nueva contraseña</span>
          <input name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Dejalo vacio para mantener la actual">
        </label>
      </div>

      <button class="primary-action" type="submit">Guardar cambios</button>
    </form>
  </dialog>
<?php endforeach; ?>

<dialog id="user-modal" class="modal-card" aria-labelledby="user-modal-title">
  <form method="post" data-prevent-double-submit>
    <input type="hidden" name="action" value="create_user">
    <header>
      <div>
        <h2 id="user-modal-title">Nuevo usuario</h2>
        <p>Alta de personal interno con acceso a la plataforma.</p>
      </div>
      <button data-close-modal type="button">Cerrar</button>
    </header>

    <div class="form-grid">
      <label class="field">
        <span>Nombre</span>
        <input name="name" required placeholder="Ej. Carlos Medina">
      </label>
      <label class="field">
        <span>Email</span>
        <input name="email" type="email" required placeholder="usuario@centro.com">
      </label>
      <div class="field">
        <span>Rol</span>
        <div class="custom-select custom-select--field" data-custom-select>
          <input type="hidden" name="role_id" value="<?= e($roles[0]['id'] ?? '') ?>" data-custom-select-value>
          <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
            <span data-custom-select-label><?= e(isset($roles[0]) ? role_label($roles[0]['role_key']) : 'Sin roles') ?></span>
          </button>
          <div class="custom-select-menu" data-custom-select-menu hidden>
            <?php foreach ($roles as $index => $role): ?>
              <button class="custom-select-option <?= $index === 0 ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($role['id']) ?>">
                <?= e(role_label($role['role_key'])) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="field">
        <span>Estado</span>
        <div class="custom-select custom-select--field" data-custom-select>
          <input type="hidden" name="status" value="ACTIVE" data-custom-select-value>
          <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
            <span data-custom-select-label>Activo</span>
          </button>
          <div class="custom-select-menu" data-custom-select-menu hidden>
            <button class="custom-select-option selected" type="button" data-custom-select-option data-value="ACTIVE">Activo</button>
            <button class="custom-select-option" type="button" data-custom-select-option data-value="INACTIVE">Inactivo</button>
          </div>
        </div>
      </div>
      <label class="field field--wide">
        <span>Contraseña inicial</span>
        <input name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
      </label>
    </div>

    <button class="primary-action" type="submit" <?= !$roles ? 'disabled' : '' ?>>Crear usuario</button>
  </form>
</dialog>
