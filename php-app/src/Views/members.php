<div class="page-heading">
  <div>
    <h2>Socios</h2>
    <p>Gestiona personas activas del centro, datos de contacto y estado de alta.</p>
  </div>
  <button class="primary-action primary-action--compact" data-open-modal="member-modal" type="button">Nuevo socio</button>
</div>

<section class="lead-metrics" aria-label="Resumen de socios">
  <article class="lead-metric lead-metric--green" aria-label="Socios activos: <?= (int) $metrics['active'] ?>">
    <span>Activos</span>
    <strong><?= (int) $metrics['active'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--orange" aria-label="Socios inactivos: <?= (int) $metrics['inactive'] ?>">
    <span>Inactivos</span>
    <strong><?= (int) $metrics['inactive'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--blue" aria-label="Altas este mes: <?= (int) $metrics['new_month'] ?>">
    <span>Altas este mes</span>
    <strong><?= (int) $metrics['new_month'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark" aria-label="Total socios: <?= (int) $metrics['total'] ?>">
    <span>Total</span>
    <strong><?= (int) $metrics['total'] ?></strong>
  </article>
</section>

<?php
$memberStatusOptions = [
  '' => 'Todos',
  'ACTIVE' => 'Activos',
  'INACTIVE' => 'Inactivos',
];
?>

<form class="lead-toolbar member-toolbar" method="get" aria-label="Filtros de socios" data-auto-filter-form data-live-search-form data-live-search-target="members-table">
  <input type="hidden" name="route" value="members">
  <label class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Nombre, telefono o email" aria-label="Buscar socios por nombre, telefono o email" data-auto-filter-input>
  </label>
  <div class="lead-filter-group">
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="status" value="<?= e($filters['status']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Estado</small>
        <span data-custom-select-label><?= e($memberStatusOptions[$filters['status']] ?? 'Todos') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($memberStatusOptions as $statusValue => $statusLabel): ?>
          <button class="custom-select-option <?= $filters['status'] === $statusValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($statusValue) ?>">
            <?= e($statusLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <label class="filter-control filter-control--date date-filter-control">
      <span>Desde</span>
      <input name="date_from" type="date" value="<?= e($filters['date_from']) ?>" aria-label="Fecha de alta desde" data-auto-filter-input>
    </label>
    <label class="filter-control filter-control--date date-filter-control">
      <span>Hasta</span>
      <input name="date_to" type="date" value="<?= e($filters['date_to']) ?>" aria-label="Fecha de alta hasta" data-auto-filter-input>
    </label>
  </div>
  <button class="primary-action primary-action--compact" type="submit">Filtrar</button>
</form>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Listado de socios</h3>
      <span data-live-search-count><?= count($members) ?> resultados</span>
    </div>
  </header>

  <div class="leads-table-wrap">
    <table class="leads-table" id="members-table">
      <caption class="sr-only">Listado de socios con contacto, estado, fecha de alta y acciones disponibles</caption>
      <thead>
        <tr>
          <th scope="col">Nombre</th>
          <th scope="col">Telefono</th>
          <th scope="col">Email</th>
          <th scope="col">Estado</th>
          <th scope="col">Membresia</th>
          <th scope="col">Caduca</th>
          <th scope="col">Alta</th>
          <th scope="col">Actualizacion</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $member): ?>
          <?php
            $memberName = trim($member['first_name'] . ' ' . ($member['last_name'] ?? ''));
            $membershipEndTime = !empty($member['membership_ends_at']) ? strtotime((string) $member['membership_ends_at']) : false;
            $canRenewMembership = $membershipEndTime !== false
                && $membershipEndTime <= strtotime('+7 days', strtotime(date('Y-m-d')))
                && !empty($member['subscription_id'])
                && !empty($member['membership_name']);
          ?>
          <tr class="lead-data-row clickable-row" data-open-modal="member-detail-<?= e($member['id']) ?>" data-live-search-row>
            <td>
              <div class="member-identity">
                <?php if (!empty($member['photo_path'])): ?>
                  <img class="member-avatar" src="<?= e($member['photo_path']) ?>" alt="Foto de <?= e($memberName) ?>">
                <?php else: ?>
                  <span class="member-avatar member-avatar--initials" aria-hidden="true"><?= e(initials($member['first_name'], $member['last_name'])) ?></span>
                <?php endif; ?>
                <strong><?= e($memberName) ?></strong>
              </div>
            </td>
            <td><?= e($member['phone'] ?: 'Sin telefono') ?></td>
            <td><?= e($member['email'] ?: 'Sin email') ?></td>
            <td>
              <span class="status-badge status-badge--<?= e(strtolower($member['status'])) ?>">
                <?= e(status_label($member['status'])) ?>
              </span>
            </td>
            <td>
              <?php if (!empty($member['membership_name'])): ?>
                <strong><?= e($member['membership_name']) ?></strong>
                <small class="table-subtext"><?= e(money_amount($member['membership_price'])) ?> · <?= e(membership_period_label($member['membership_period'])) ?></small>
              <?php else: ?>
                <span class="muted-cell">Sin membresia</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($member['membership_ends_at'])): ?>
                <span class="membership-expiry <?= strtotime($member['membership_ends_at']) < strtotime(date('Y-m-d')) ? 'membership-expiry--expired' : '' ?>">
                  <?= e(format_date_short($member['membership_ends_at'])) ?>
                </span>
              <?php else: ?>
                <span class="muted-cell">Sin fecha</span>
              <?php endif; ?>
            </td>
            <td><?= e(format_date($member['joined_at'])) ?></td>
            <td><?= e(format_date($member['updated_at'])) ?></td>
            <td>
              <div class="row-actions">
                <button class="icon-action" data-open-modal="member-detail-<?= e($member['id']) ?>" type="button" title="Editar socio" aria-label="Editar socio <?= e($memberName) ?>">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4.8L19.4 9.4a2.1 2.1 0 0 0 0-3L17.6 4.6a2.1 2.1 0 0 0-3 0L4 15.2V20Zm2-2v-1.95l7.25-7.25 1.95 1.95L7.95 18H6Zm10.6-8.65L14.65 7.4 16 6.05 17.95 8l-1.35 1.35Z"/></svg>
                </button>
                <?php if ($canRenewMembership): ?>
                  <form method="post" data-confirm-message="Se renovara la membresia y se registrara el pago asociado.">
                    <input type="hidden" name="action" value="renew_member_subscription">
                    <input type="hidden" name="id" value="<?= e($member['id']) ?>">
                    <button class="icon-action success-action" type="submit" title="Renovar membresia" aria-label="Renovar membresia de <?= e($memberName) ?>">
                      <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M17.7 6.3A8 8 0 1 0 20 12h-2a6 6 0 1 1-1.8-4.2L13 11h8V3l-3.3 3.3ZM11 7h2v5.6l4 2.4-1 1.7-5-3V7Z"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
                <form method="post" data-confirm-message="Eliminar este socio? Esta accion no se puede deshacer.">
                  <input type="hidden" name="action" value="delete_member">
                  <input type="hidden" name="id" value="<?= e($member['id']) ?>">
                  <button class="icon-action danger-action" type="submit" title="Eliminar socio" aria-label="Eliminar socio <?= e($memberName) ?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 21a2 2 0 0 1-2-2V8h14v11a2 2 0 0 1-2 2H7ZM9 6V4h6v2h5v2H4V6h5Zm0 5v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$members): ?>
          <tr data-live-search-empty>
            <td class="leads-empty-cell" colspan="9">No hay socios que coincidan con los filtros actuales.</td>
          </tr>
        <?php else: ?>
          <tr data-live-search-empty hidden>
            <td class="leads-empty-cell" colspan="9">No hay socios que coincidan con la busqueda actual.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($members as $member): ?>
  <?php $memberName = trim($member['first_name'] . ' ' . ($member['last_name'] ?? '')); ?>
  <?php $phoneCountry = phone_country_entry($member['phone']); ?>
  <dialog id="member-detail-<?= e($member['id']) ?>" class="modal-card lead-detail-modal" aria-labelledby="member-detail-title-<?= e($member['id']) ?>">
    <form method="post" enctype="multipart/form-data" aria-label="Editar datos del socio <?= e($memberName) ?>">
      <input type="hidden" name="action" value="update_member">
      <input type="hidden" name="id" value="<?= e($member['id']) ?>">
      <header>
        <div>
          <div class="member-modal-heading">
            <?php if (!empty($member['photo_path'])): ?>
              <img class="member-avatar member-avatar--large" src="<?= e($member['photo_path']) ?>" alt="Foto de <?= e($memberName) ?>">
            <?php else: ?>
              <span class="member-avatar member-avatar--large member-avatar--initials" aria-hidden="true"><?= e(initials($member['first_name'], $member['last_name'])) ?></span>
            <?php endif; ?>
            <div>
              <h2 id="member-detail-title-<?= e($member['id']) ?>"><?= e($memberName) ?></h2>
              <p><?= e($member['phone'] ?: 'Sin telefono') ?> &middot; <?= e($member['email'] ?: 'Sin email') ?></p>
            </div>
          </div>
        </div>
        <button data-close-modal type="button" aria-label="Cerrar detalles de <?= e($memberName) ?>">Cerrar</button>
      </header>

      <div class="form-grid">
        <label class="field">
          <span>Nombre</span>
          <input name="first_name" required value="<?= e($member['first_name']) ?>">
        </label>
        <label class="field">
          <span>Apellidos</span>
          <input name="last_name" value="<?= e($member['last_name']) ?>">
        </label>
        <label class="field">
          <span>Telefono</span>
          <div class="phone-combo">
            <div class="phone-prefix-picker" data-phone-picker>
              <input type="hidden" name="phone_country" value="<?= e($phoneCountry['code']) ?>" data-phone-country-value>
              <button class="phone-prefix-trigger" type="button" data-phone-country-trigger aria-label="Seleccionar prefijo telefonico" aria-expanded="false">
                <img src="https://flagcdn.com/w40/<?= e($phoneCountry['iso']) ?>.png" alt="" data-phone-country-flag>
                <span data-phone-country-code><?= e($phoneCountry['code']) ?></span>
              </button>
              <div class="phone-prefix-menu" data-phone-country-menu hidden>
                <input class="phone-prefix-search" type="search" placeholder="Buscar pais" data-phone-country-search>
                <div class="phone-prefix-options">
                  <?php foreach (country_dial_options() as $option): ?>
                    <button type="button" data-phone-country-option data-code="<?= e($option['code']) ?>" data-iso="<?= e($option['iso']) ?>" data-search="<?= e(strtolower($option['country'] . ' ' . $option['code'] . ' ' . $option['iso'])) ?>">
                      <img src="https://flagcdn.com/w40/<?= e($option['iso']) ?>.png" alt="">
                      <span><?= e($option['code']) ?></span>
                      <small><?= e($option['country']) ?></small>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <input class="phone-number-input" name="phone_number" value="<?= e(phone_local_value($member['phone'])) ?>" inputmode="tel" placeholder="Numero" aria-label="Numero de telefono">
          </div>
        </label>
        <label class="field">
          <span>Email</span>
          <input name="email" type="email" value="<?= e($member['email']) ?>">
        </label>
        <div class="field">
          <span>Estado</span>
          <div class="custom-select custom-select--field" data-custom-select>
            <input type="hidden" name="status" value="<?= e($member['status']) ?>" data-custom-select-value>
            <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
              <span data-custom-select-label><?= e(status_label($member['status'])) ?></span>
            </button>
            <div class="custom-select-menu" data-custom-select-menu hidden>
              <?php foreach (['ACTIVE', 'INACTIVE'] as $status): ?>
                <button class="custom-select-option <?= $member['status'] === $status ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($status) ?>"><?= e(status_label($status)) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <label class="field">
          <span>Fecha de alta</span>
          <input name="joined_at" type="date" value="<?= $member['joined_at'] ? e(date('Y-m-d', strtotime($member['joined_at']))) : '' ?>">
        </label>
        <div class="field">
          <span>Membresia</span>
          <div class="custom-select custom-select--field" data-custom-select>
            <input type="hidden" name="membership_plan_id" value="<?= e($member['membership_plan_id'] ?? '') ?>" data-custom-select-value>
            <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
              <span data-custom-select-label><?= e($member['membership_name'] ?? 'Sin membresia') ?></span>
            </button>
            <div class="custom-select-menu" data-custom-select-menu hidden>
              <button class="custom-select-option <?= empty($member['membership_plan_id']) ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="" data-duration-days="">Sin membresia</button>
              <?php foreach ($membershipPlans as $planOption): ?>
                <button class="custom-select-option <?= ($member['membership_plan_id'] ?? '') === $planOption['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($planOption['id']) ?>" data-duration-days="<?= (int) $planOption['duration_days'] ?>">
                  <?= e($planOption['name']) ?> · <?= e(money_amount($planOption['price'])) ?> · <?= e(membership_period_label($planOption['billing_period'])) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <label class="field">
          <span>Inicio membresia</span>
          <input name="membership_starts_at" type="date" value="<?= !empty($member['membership_starts_at']) ? e(date('Y-m-d', strtotime($member['membership_starts_at']))) : e(date('Y-m-d')) ?>" data-membership-start-date>
        </label>
        <label class="field">
          <span>Caducidad</span>
          <input name="membership_ends_at" type="date" value="<?= !empty($member['membership_ends_at']) ? e(date('Y-m-d', strtotime($member['membership_ends_at']))) : '' ?>" data-membership-end-date>
        </label>
        <label class="field field--wide member-photo-field">
          <span>Foto del socio</span>
          <input name="photo" type="file" accept="image/jpeg,image/png,image/webp">
          <small>JPG, PNG o WEBP. Maximo 2 MB.</small>
        </label>
        <?php if (!empty($member['photo_path'])): ?>
          <div class="field field--wide member-photo-actions">
            <button class="photo-remove-button" name="remove_photo" type="submit" value="1">
              <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 21a2 2 0 0 1-2-2V8h14v11a2 2 0 0 1-2 2H7ZM9 6V4h6v2h5v2H4V6h5Zm0 5v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>
              Quitar foto actual
            </button>
          </div>
        <?php endif; ?>
      </div>

      <?php $memberReservations = $reservationHistory[$member['id']] ?? []; ?>
      <section class="reservations-panel reservations-panel--member" aria-labelledby="member-reservations-title-<?= e($member['id']) ?>">
        <div class="reservations-heading">
          <div>
            <h3 id="member-reservations-title-<?= e($member['id']) ?>">Historial de reservas</h3>
            <p>Clases reservadas, asistidas, canceladas o marcadas como no-show.</p>
          </div>
        </div>
        <div class="reservation-list">
          <?php foreach ($memberReservations as $reservation): ?>
            <article class="reservation-item reservation-item--<?= e($reservation['status']) ?>">
              <div>
                <strong><?= e($reservation['class_name']) ?></strong>
                <span>
                  <?= e(format_date_short($reservation['starts_at'])) ?>
                  &middot;
                  <?= e(format_time($reservation['starts_at'])) ?> - <?= e(format_time($reservation['ends_at'])) ?>
                  &middot;
                  <?= e($reservation['instructor_name'] ?: 'Sin instructor') ?>
                </span>
              </div>
              <span class="status-badge status-badge--<?= e(str_replace('_', '-', $reservation['status'])) ?>"><?= e(status_label($reservation['status'])) ?></span>
            </article>
          <?php endforeach; ?>
          <?php if (!$memberReservations): ?>
            <p class="empty-note">Este socio todavia no tiene reservas.</p>
          <?php endif; ?>
        </div>
      </section>

      <button class="primary-action" type="submit">Guardar cambios</button>
    </form>
  </dialog>
<?php endforeach; ?>

<?php $defaultPhoneCountry = phone_country_entry(null); ?>
<dialog id="member-modal" class="modal-card" aria-labelledby="member-modal-title">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="create_member">
    <header>
      <h2 id="member-modal-title">Nuevo socio</h2>
      <button data-close-modal type="button" aria-label="Cerrar formulario de nuevo socio">Cerrar</button>
    </header>
    <div class="form-grid">
      <label class="field">
        <span>Nombre</span>
        <input name="first_name" required>
      </label>
      <label class="field">
        <span>Apellidos</span>
        <input name="last_name">
      </label>
      <label class="field">
        <span>Telefono</span>
        <div class="phone-combo">
          <div class="phone-prefix-picker" data-phone-picker>
            <input type="hidden" name="phone_country" value="<?= e($defaultPhoneCountry['code']) ?>" data-phone-country-value>
            <button class="phone-prefix-trigger" type="button" data-phone-country-trigger aria-label="Seleccionar prefijo telefonico" aria-expanded="false">
              <img src="https://flagcdn.com/w40/<?= e($defaultPhoneCountry['iso']) ?>.png" alt="" data-phone-country-flag>
              <span data-phone-country-code><?= e($defaultPhoneCountry['code']) ?></span>
            </button>
            <div class="phone-prefix-menu" data-phone-country-menu hidden>
              <input class="phone-prefix-search" type="search" placeholder="Buscar pais" data-phone-country-search>
              <div class="phone-prefix-options">
                <?php foreach (country_dial_options() as $option): ?>
                  <button type="button" data-phone-country-option data-code="<?= e($option['code']) ?>" data-iso="<?= e($option['iso']) ?>" data-search="<?= e(strtolower($option['country'] . ' ' . $option['code'] . ' ' . $option['iso'])) ?>">
                    <img src="https://flagcdn.com/w40/<?= e($option['iso']) ?>.png" alt="">
                    <span><?= e($option['code']) ?></span>
                    <small><?= e($option['country']) ?></small>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <input class="phone-number-input" name="phone_number" inputmode="tel" placeholder="Numero" aria-label="Numero de telefono">
        </div>
      </label>
      <label class="field">
        <span>Email</span>
        <input name="email" type="email">
      </label>
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
      <label class="field">
        <span>Fecha de alta</span>
        <input name="joined_at" type="date" value="<?= e(date('Y-m-d')) ?>">
      </label>
      <div class="field">
        <span>Membresia</span>
        <div class="custom-select custom-select--field" data-custom-select>
          <input type="hidden" name="membership_plan_id" value="" data-custom-select-value>
          <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
            <span data-custom-select-label>Sin membresia</span>
          </button>
          <div class="custom-select-menu" data-custom-select-menu hidden>
            <button class="custom-select-option selected" type="button" data-custom-select-option data-value="" data-duration-days="">Sin membresia</button>
            <?php foreach ($membershipPlans as $planOption): ?>
              <button class="custom-select-option" type="button" data-custom-select-option data-value="<?= e($planOption['id']) ?>" data-duration-days="<?= (int) $planOption['duration_days'] ?>">
                <?= e($planOption['name']) ?> · <?= e(money_amount($planOption['price'])) ?> · <?= e(membership_period_label($planOption['billing_period'])) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <label class="field">
        <span>Inicio membresia</span>
        <input name="membership_starts_at" type="date" value="<?= e(date('Y-m-d')) ?>" data-membership-start-date>
      </label>
      <label class="field">
        <span>Caducidad</span>
        <input name="membership_ends_at" type="date" data-membership-end-date>
      </label>
      <label class="field field--wide member-photo-field">
        <span>Foto del socio</span>
        <input name="photo" type="file" accept="image/jpeg,image/png,image/webp">
        <small>JPG, PNG o WEBP. Maximo 2 MB.</small>
      </label>
    </div>
    <button class="primary-action" type="submit">Crear socio</button>
  </form>
</dialog>
