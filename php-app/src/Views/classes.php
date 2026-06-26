<div class="page-heading">
  <div>
    <h2>Clases</h2>
    <p>Programa sesiones por fecha y revisa el calendario del centro.</p>
  </div>
  <div class="page-actions">
    <button class="secondary-action" data-open-modal="classes-calendar-modal" type="button">Abrir calendario</button>
    <button class="primary-action primary-action--compact" data-open-modal="class-session-modal" type="button">Nueva clase</button>
  </div>
</div>

<section class="lead-metrics" aria-label="Resumen de clases">
  <article class="lead-metric lead-metric--blue">
    <span>Hoy</span>
    <strong><?= (int) $metrics['today'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--green">
    <span>Proximos 7 dias</span>
    <strong><?= (int) $metrics['week'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--orange">
    <span>Programadas</span>
    <strong><?= (int) $metrics['scheduled'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark">
    <span>Tipos activos</span>
    <strong><?= (int) $metrics['types'] ?></strong>
  </article>
</section>

<?php
$classTypeOptions = ['' => 'Todas'];
foreach ($classTypes as $type) {
  $classTypeOptions[$type['id']] = $type['name'];
}
?>

<form class="lead-toolbar classes-toolbar" method="get" aria-label="Filtros de clases" data-auto-filter-form data-live-search-form data-live-search-target="classes-table">
  <input type="hidden" name="route" value="classes">
  <input type="hidden" name="month" value="<?= e($filters['month']) ?>">
  <label class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Clase o instructor" aria-label="Buscar clases o instructores" data-auto-filter-input>
  </label>
  <div class="lead-filter-group">
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="type" value="<?= e($filters['type']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Clase</small>
        <span data-custom-select-label><?= e($classTypeOptions[$filters['type']] ?? 'Todas') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($classTypeOptions as $typeValue => $typeLabel): ?>
          <button class="custom-select-option <?= $filters['type'] === $typeValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($typeValue) ?>">
            <?= e($typeLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
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

<?php if (!$activeClassTypes): ?>
  <section class="empty-setup-card">
    <div>
      <h3>Primero crea un tipo de clase</h3>
      <p>Necesitas al menos un tipo, por ejemplo Yoga, HIIT o Pilates, para programar sesiones con fecha.</p>
    </div>
    <button class="primary-action primary-action--compact" data-open-modal="class-type-modal" type="button">Crear tipo</button>
  </section>
<?php endif; ?>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Clases por fecha</h3>
      <span data-live-search-count><?= count($sessions) ?> resultados</span>
    </div>
    <button class="secondary-action" data-open-modal="class-type-modal" type="button">Nuevo tipo</button>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table" id="classes-table">
      <caption class="sr-only">Listado de clases por fecha, hora, instructor y aforo</caption>
      <thead>
        <tr>
          <th scope="col">Clase</th>
          <th scope="col">Fecha</th>
          <th scope="col">Horario</th>
          <th scope="col">Instructor</th>
          <th scope="col">Aforo</th>
          <th scope="col">Estado</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $session): ?>
          <tr class="lead-data-row clickable-row" data-open-modal="class-detail-<?= e($session['id']) ?>" data-live-search-row>
            <td>
              <strong><?= e($session['class_name']) ?></strong>
              <small class="table-subtext"><?= e($session['class_description'] ?: 'Sin descripcion') ?></small>
            </td>
            <td><?= e(format_date_short($session['starts_at'])) ?></td>
            <td><?= e(format_time($session['starts_at'])) ?> - <?= e(format_time($session['ends_at'])) ?></td>
            <td><?= e($session['instructor_name'] ?: 'Sin instructor') ?></td>
            <?php
              $sessionReservations = $reservationsBySession[$session['id']] ?? [];
              $activeReservationCount = count(array_filter($sessionReservations, fn ($reservation) => in_array($reservation['status'], ['reserved', 'attended', 'no_show'], true)));
            ?>
            <td>
              <strong><?= $activeReservationCount ?>/<?= (int) $session['capacity'] ?></strong>
              <small class="table-subtext">reservas activas</small>
            </td>
            <td><span class="status-badge status-badge--<?= e(strtolower($session['status'])) ?>"><?= e(status_label($session['status'])) ?></span></td>
            <td>
              <div class="row-actions">
                <button class="icon-action" data-open-modal="class-detail-<?= e($session['id']) ?>" type="button" title="Editar clase" aria-label="Editar clase <?= e($session['class_name']) ?>">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4.8L19.4 9.4a2.1 2.1 0 0 0 0-3L17.6 4.6a2.1 2.1 0 0 0-3 0L4 15.2V20Zm2-2v-1.95l7.25-7.25 1.95 1.95L7.95 18H6Zm10.6-8.65L14.65 7.4 16 6.05 17.95 8l-1.35 1.35Z"/></svg>
                </button>
                <form method="post" data-confirm-message="Eliminar esta clase programada?">
                  <input type="hidden" name="action" value="delete_class_session">
                  <input type="hidden" name="id" value="<?= e($session['id']) ?>">
                  <button class="icon-action danger-action" type="submit" title="Eliminar clase" aria-label="Eliminar clase <?= e($session['class_name']) ?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 21a2 2 0 0 1-2-2V8h14v11a2 2 0 0 1-2 2H7ZM9 6V4h6v2h5v2H4V6h5Zm0 5v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$sessions): ?>
          <tr data-live-search-empty>
            <td class="leads-empty-cell" colspan="7">No hay clases en el rango seleccionado.</td>
          </tr>
        <?php else: ?>
          <tr data-live-search-empty hidden>
            <td class="leads-empty-cell" colspan="7">No hay clases que coincidan con la busqueda actual.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($sessions as $session): ?>
  <?php
    $sessionReservations = $reservationsBySession[$session['id']] ?? [];
    $activeReservationCount = count(array_filter($sessionReservations, fn ($reservation) => in_array($reservation['status'], ['reserved', 'attended', 'no_show'], true)));
    $availableSeats = max(0, (int) $session['capacity'] - $activeReservationCount);
  ?>
  <dialog id="class-detail-<?= e($session['id']) ?>" class="modal-card" aria-labelledby="class-title-<?= e($session['id']) ?>">
    <form method="post">
      <input type="hidden" name="action" value="update_class_session">
      <input type="hidden" name="id" value="<?= e($session['id']) ?>">
      <input type="hidden" name="return_to_calendar" value="0" data-class-return-to-calendar>
      <input type="hidden" name="calendar_month" value="<?= e($filters['month']) ?>" data-class-calendar-month>
      <header>
        <div>
          <h2 id="class-title-<?= e($session['id']) ?>"><?= e($session['class_name']) ?></h2>
          <p><?= e(format_date_short($session['starts_at'])) ?> · <?= e(format_time($session['starts_at'])) ?> - <?= e(format_time($session['ends_at'])) ?></p>
        </div>
        <button data-close-modal type="button">Cerrar</button>
      </header>
      <?php $editingSession = $session; require __DIR__ . '/partials/class-session-form.php'; ?>
      <button class="primary-action" type="submit">Guardar cambios</button>
    </form>

    <section class="reservations-panel" aria-labelledby="reservations-title-<?= e($session['id']) ?>">
      <div class="reservations-heading">
        <div>
          <h3 id="reservations-title-<?= e($session['id']) ?>">Reservas</h3>
          <p><?= $activeReservationCount ?> de <?= (int) $session['capacity'] ?> plazas ocupadas. <?= $availableSeats ?> libres.</p>
        </div>
        <span class="status-badge <?= $availableSeats > 0 ? 'status-badge--active' : 'status-badge--lost' ?>">
          <?= $availableSeats > 0 ? 'Aforo disponible' : 'Clase llena' ?>
        </span>
      </div>

      <form class="reservation-create-form" method="post">
        <input type="hidden" name="action" value="create_reservation">
        <input type="hidden" name="class_session_id" value="<?= e($session['id']) ?>">
        <div class="field field--wide">
          <span>Anadir socio</span>
          <div class="member-picker-shell" data-member-picker>
            <input class="member-picker-search" type="search" placeholder="Buscar socio por nombre, email o telefono" data-member-search <?= $availableSeats <= 0 ? 'disabled' : '' ?>>
            <div class="member-picker member-picker--radio">
              <?php foreach ($members as $member): ?>
                <?php
                  $memberName = trim($member['first_name'] . ' ' . ($member['last_name'] ?? ''));
                  $searchText = strtolower($memberName . ' ' . ($member['email'] ?? '') . ' ' . ($member['phone'] ?? ''));
                ?>
                <label data-member-option data-search="<?= e($searchText) ?>">
                  <input type="radio" name="member_id" value="<?= e($member['id']) ?>" <?= $availableSeats <= 0 ? 'disabled' : '' ?>>
                  <span>
                    <strong><?= e($memberName) ?></strong>
                    <small><?= e($member['email'] ?: ($member['phone'] ?: 'Sin contacto')) ?></small>
                  </span>
                </label>
              <?php endforeach; ?>
              <?php if (!$members): ?>
                <p>No hay socios activos disponibles.</p>
              <?php endif; ?>
              <p class="member-picker-empty" data-member-empty hidden>No hay socios que coincidan con la busqueda.</p>
            </div>
          </div>
        </div>
        <button class="primary-action primary-action--compact" type="submit" <?= $availableSeats <= 0 || !$members ? 'disabled' : '' ?>>Crear reserva</button>
      </form>

      <div class="reservation-list">
        <?php foreach ($sessionReservations as $reservation): ?>
          <?php $reservationMemberName = trim($reservation['first_name'] . ' ' . ($reservation['last_name'] ?? '')); ?>
          <article class="reservation-item reservation-item--<?= e($reservation['status']) ?>">
            <div>
              <strong><?= e($reservationMemberName) ?></strong>
              <span><?= e($reservation['email'] ?: ($reservation['phone'] ?: 'Sin contacto')) ?></span>
            </div>
            <span class="status-badge status-badge--<?= e(str_replace('_', '-', $reservation['status'])) ?>"><?= e(status_label($reservation['status'])) ?></span>
            <div class="row-actions reservation-actions">
              <?php if ($reservation['status'] !== 'cancelled' && $reservation['status'] !== 'attended'): ?>
                <form method="post">
                  <input type="hidden" name="action" value="update_reservation_status">
                  <input type="hidden" name="reservation_id" value="<?= e($reservation['id']) ?>">
                  <input type="hidden" name="class_session_id" value="<?= e($session['id']) ?>">
                  <input type="hidden" name="status" value="attended">
                  <button class="icon-action" type="submit" title="Marcar asistencia" aria-label="Marcar asistencia de <?= e($reservationMemberName) ?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9.55 17.6-5.3-5.3 1.4-1.4 3.9 3.9 8.8-8.8 1.4 1.4-10.2 10.2Z"/></svg>
                  </button>
                </form>
              <?php endif; ?>
              <?php if ($reservation['status'] !== 'cancelled' && $reservation['status'] !== 'no_show'): ?>
                <form method="post">
                  <input type="hidden" name="action" value="update_reservation_status">
                  <input type="hidden" name="reservation_id" value="<?= e($reservation['id']) ?>">
                  <input type="hidden" name="class_session_id" value="<?= e($session['id']) ?>">
                  <input type="hidden" name="status" value="no_show">
                  <button class="icon-action" type="submit" title="Marcar no-show" aria-label="Marcar no-show de <?= e($reservationMemberName) ?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M11 13H5v-2h6V5h2v6h6v2h-6v6h-2v-6Z"/></svg>
                  </button>
                </form>
              <?php endif; ?>
              <?php if ($reservation['status'] !== 'cancelled'): ?>
                <form method="post" data-confirm-message="Cancelar esta reserva?">
                  <input type="hidden" name="action" value="update_reservation_status">
                  <input type="hidden" name="reservation_id" value="<?= e($reservation['id']) ?>">
                  <input type="hidden" name="class_session_id" value="<?= e($session['id']) ?>">
                  <input type="hidden" name="status" value="cancelled">
                  <button class="icon-action danger-action" type="submit" title="Cancelar reserva" aria-label="Cancelar reserva de <?= e($reservationMemberName) ?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6.4 19-1.4-1.4 5.6-5.6L5 6.4 6.4 5l5.6 5.6L17.6 5 19 6.4 13.4 12l5.6 5.6-1.4 1.4-5.6-5.6L6.4 19Z"/></svg>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (!$sessionReservations): ?>
          <p class="empty-note">Todavia no hay socios reservados en esta clase.</p>
        <?php endif; ?>
      </div>
    </section>
  </dialog>
<?php endforeach; ?>

<dialog id="class-session-modal" class="modal-card" aria-labelledby="class-session-modal-title">
  <form method="post">
    <input type="hidden" name="action" value="create_class_session">
    <input type="hidden" name="return_to_calendar" value="0" data-class-return-to-calendar>
    <input type="hidden" name="calendar_month" value="<?= e($filters['month']) ?>" data-class-calendar-month>
    <header>
      <h2 id="class-session-modal-title">Nueva clase programada</h2>
      <button data-close-modal type="button">Cerrar</button>
    </header>
    <?php $editingSession = null; require __DIR__ . '/partials/class-session-form.php'; ?>
    <button class="primary-action" type="submit">Programar clase</button>
  </form>
</dialog>

<dialog id="class-type-modal" class="modal-card" aria-labelledby="class-type-modal-title">
  <form method="post">
    <input type="hidden" name="action" value="create_class_type">
    <header>
      <h2 id="class-type-modal-title">Nuevo tipo de clase</h2>
      <button data-close-modal type="button">Cerrar</button>
    </header>
    <div class="form-grid">
      <label class="field">
        <span>Nombre</span>
        <input name="name" required placeholder="Ej. HIIT, Yoga, Pilates">
      </label>
      <label class="field">
        <span>Aforo por defecto</span>
        <input name="capacity" type="number" min="1" value="12">
      </label>
      <label class="field">
        <span>Duracion</span>
        <input name="duration_minutes" type="number" min="15" step="5" value="60">
      </label>
      <label class="field field--wide">
        <span>Descripcion</span>
        <textarea name="description" rows="3" placeholder="Resumen breve de la clase"></textarea>
      </label>
    </div>
    <button class="primary-action" type="submit">Crear tipo</button>
  </form>
</dialog>

<dialog id="classes-calendar-modal" class="modal-card calendar-modal" aria-labelledby="classes-calendar-title">
  <?php
    $calendarDate = DateTimeImmutable::createFromFormat('Y-m-d', $calendar['month'] . '-01') ?: new DateTimeImmutable('first day of this month');
    $previousMonth = $calendarDate->modify('-1 month')->format('Y-m');
    $nextMonth = $calendarDate->modify('+1 month')->format('Y-m');
    $calendarQueryBase = [
      'route' => 'classes',
      'q' => $filters['q'],
      'type' => $filters['type'],
      'date_from' => $filters['date_from'],
      'date_to' => $filters['date_to'],
      'modal' => 'classes-calendar-modal',
    ];
    $previousMonthUrl = 'index.php?' . http_build_query($calendarQueryBase + ['month' => $previousMonth]);
    $nextMonthUrl = 'index.php?' . http_build_query($calendarQueryBase + ['month' => $nextMonth]);
  ?>
  <header>
    <div>
      <h2 id="classes-calendar-title">Calendario de clases</h2>
      <p><?= e($calendar['title']) ?></p>
    </div>
    <div class="calendar-header-actions">
      <a class="secondary-action" href="<?= e($previousMonthUrl) ?>">Mes anterior</a>
      <a class="secondary-action" href="<?= e($nextMonthUrl) ?>">Mes siguiente</a>
      <button data-close-modal type="button">Cerrar</button>
    </div>
  </header>
  <div class="calendar-grid" role="table" aria-label="Calendario mensual de clases">
    <?php foreach (['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'] as $dayName): ?>
      <div class="calendar-weekday" role="columnheader"><?= e($dayName) ?></div>
    <?php endforeach; ?>
    <?php for ($blank = 1; $blank < (int) $calendar['first_weekday']; $blank++): ?>
      <div class="calendar-day calendar-day--empty" aria-hidden="true"></div>
    <?php endfor; ?>
    <?php for ($day = 1; $day <= (int) $calendar['days_in_month']; $day++): ?>
      <?php $dateKey = $calendar['month'] . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT); ?>
      <?php $daySessions = $calendar['sessions_by_day'][$dateKey] ?? []; ?>
      <div class="calendar-day <?= $dateKey === date('Y-m-d') ? 'calendar-day--today' : '' ?>" role="cell" tabindex="0" data-class-create-date="<?= e($dateKey) ?>" aria-label="Crear clase el <?= e(format_date_short($dateKey)) ?>">
        <div class="calendar-day-head">
          <strong><?= $day ?></strong>
          <button class="calendar-add-button" type="button" data-class-create-date="<?= e($dateKey) ?>" aria-label="Anadir clase el <?= e(format_date_short($dateKey)) ?>">+</button>
        </div>
        <div class="calendar-events">
          <?php foreach ($daySessions as $daySession): ?>
            <div class="calendar-event-row">
              <button class="calendar-event" type="button" data-open-modal="class-detail-<?= e($daySession['id']) ?>" data-class-calendar-trigger data-class-date="<?= e($dateKey) ?>" aria-label="Editar <?= e($daySession['class_name']) ?> del <?= e(format_date_short($daySession['starts_at'])) ?>">
                <?= e(format_time($daySession['starts_at'])) ?> <?= e($daySession['class_name']) ?>
              </button>
              <form method="post" data-confirm-message="Eliminar esta clase programada?">
                <input type="hidden" name="action" value="delete_class_session">
                <input type="hidden" name="id" value="<?= e($daySession['id']) ?>">
                <input type="hidden" name="return_to_calendar" value="1">
                <input type="hidden" name="calendar_month" value="<?= e($calendar['month']) ?>">
                <button class="calendar-delete-button" type="submit" aria-label="Eliminar <?= e($daySession['class_name']) ?>">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 21a2 2 0 0 1-2-2V8h14v11a2 2 0 0 1-2 2H7ZM9 6V4h6v2h5v2H4V6h5Zm0 5v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>
                </button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endfor; ?>
  </div>
</dialog>
