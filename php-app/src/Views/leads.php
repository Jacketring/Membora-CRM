<div class="page-heading">
  <div>
    <h2>Leads</h2>
    <p>Gestiona oportunidades comerciales desde el primer contacto hasta convertirlas en clientes.</p>
  </div>
  <button class="primary-action primary-action--compact" data-open-modal="lead-modal" type="button">Nuevo lead</button>
</div>

<section class="lead-metrics" aria-label="Resumen de leads">
  <article class="lead-metric lead-metric--blue" aria-label="Leads abiertos: <?= (int) $metrics['open'] ?>">
    <span>Abiertos</span>
    <strong><?= (int) $metrics['open'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--green" aria-label="Clientes conseguidos: <?= (int) $metrics['converted'] ?>">
    <span>Clientes</span>
    <strong><?= (int) $metrics['converted'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--orange" aria-label="Leads perdidos: <?= (int) $metrics['lost'] ?>">
    <span>Perdidos</span>
    <strong><?= (int) $metrics['lost'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark" aria-label="Conversion: <?= (int) $metrics['conversion'] ?>%">
    <span>Conversion</span>
    <strong><?= (int) $metrics['conversion'] ?>%</strong>
  </article>
</section>

<form class="lead-toolbar" method="get" aria-label="Filtros de leads" data-auto-filter-form data-live-search-form data-live-search-target="leads-table">
  <input type="hidden" name="route" value="leads">
  <label class="lead-search">
    <span>Buscar</span>
    <input name="q" value="<?= e($filters['q']) ?>" placeholder="Nombre, telefono, email o interes" aria-label="Buscar leads por nombre, telefono, email o interes" data-auto-filter-input>
  </label>
  <div class="lead-filter-group">
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <input type="hidden" name="stage" value="<?= e($filters['stage']) ?>" data-custom-select-value>
      <?php
        $selectedStageLabel = 'Todas';
        foreach ($stages as $stage) {
          if ($filters['stage'] === $stage['id']) {
            $selectedStageLabel = $stage['name'];
            break;
          }
        }
      ?>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Etapa</small>
        <span data-custom-select-label><?= e($selectedStageLabel) ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <button class="custom-select-option <?= $filters['stage'] === '' ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="">Todas</button>
        <?php foreach ($stages as $stage): ?>
          <button class="custom-select-option <?= $filters['stage'] === $stage['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($stage['id']) ?>">
            <?= e($stage['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <?php
        $statusOptions = [
          '' => 'Todos',
          'OPEN' => 'Abiertos',
          'CONVERTED' => 'Clientes',
          'LOST' => 'Perdidos',
        ];
      ?>
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
    <div class="filter-control filter-control--select custom-select custom-select--filter" data-custom-select>
      <?php
        $sourceOptions = [
          '' => 'Todos',
          'WALK_IN' => 'Visita',
          'WEBSITE' => 'Web',
          'WEB' => 'Web externa',
          'LANDING' => 'Landing',
          'FORMULARIO_WEB' => 'Formulario web',
          'PHONE' => 'Telefono',
          'SOCIAL_MEDIA' => 'Redes',
          'REFERRAL' => 'Recomendacion',
          'OTHER' => 'Otro',
        ];
      ?>
      <input type="hidden" name="source" value="<?= e($filters['source']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <small>Origen</small>
        <span data-custom-select-label><?= e($sourceOptions[$filters['source']] ?? 'Todos') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($sourceOptions as $sourceValue => $sourceLabel): ?>
          <button class="custom-select-option <?= $filters['source'] === $sourceValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($sourceValue) ?>">
            <?= e($sourceLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <label class="filter-control filter-control--date date-filter-control">
      <span>Desde</span>
      <input name="date_from" type="date" value="<?= e($filters['date_from']) ?>" aria-label="Fecha desde" data-auto-filter-input>
    </label>
    <label class="filter-control filter-control--date date-filter-control">
      <span>Hasta</span>
      <input name="date_to" type="date" value="<?= e($filters['date_to']) ?>" aria-label="Fecha hasta" data-auto-filter-input>
    </label>
  </div>
  <button class="primary-action primary-action--compact" type="submit">Filtrar</button>
</form>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Listado de leads</h3>
      <span data-live-search-count><?= count($leads) ?> resultados</span>
    </div>
  </header>

  <div class="leads-table-wrap">
    <table class="leads-table" id="leads-table">
      <caption class="sr-only">Listado de leads con contacto, etapa, estado, responsable, fecha de creacion y acciones disponibles</caption>
      <thead>
        <tr>
          <th scope="col">Nombre</th>
          <th scope="col">Telefono</th>
          <th scope="col">Email</th>
          <th scope="col">Origen</th>
          <th scope="col">Interes</th>
          <th scope="col">Etapa</th>
          <th scope="col">Estado</th>
          <th scope="col">Responsable</th>
          <th scope="col">Creacion</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($leads as $lead): ?>
          <tr class="lead-data-row clickable-row" data-open-modal="lead-detail-<?= e($lead['id']) ?>" data-live-search-row>
            <td>
              <strong><?= e(trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''))) ?></strong>
            </td>
            <td><?= e($lead['phone'] ?: 'Sin telefono') ?></td>
            <td><?= e($lead['email'] ?: 'Sin email') ?></td>
            <td><span class="source-badge"><?= e(source_label($lead['source'])) ?></span></td>
            <td><?= e($lead['interest'] ?: 'Sin interes') ?></td>
            <td>
              <button class="stage-badge-button stage-badge--<?= e(stage_color_class($lead['stage_key'] ?: $lead['stage_name'])) ?>" data-open-modal="lead-detail-<?= e($lead['id']) ?>" type="button" title="Cambiar etapa" aria-label="Cambiar etapa de <?= e(trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''))) ?>. Etapa actual: <?= e($lead['stage_name']) ?>">
                <span class="stage-dot" aria-hidden="true"></span>
                <?= e($lead['stage_name']) ?>
              </button>
            </td>
            <td>
              <span class="status-badge status-badge--<?= e(strtolower($lead['status'])) ?>">
                <?= e(status_label($lead['status'])) ?>
              </span>
            </td>
            <td><?= e($lead['assigned_name'] ?: 'Sin asignar') ?></td>
            <td><?= e(format_date($lead['created_at'])) ?></td>
            <td>
              <div class="row-actions">
                <button class="icon-action" data-open-modal="lead-detail-<?= e($lead['id']) ?>" type="button" title="Editar y ver detalles" aria-label="Editar y ver detalles de <?= e(trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''))) ?>">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4.8L19.4 9.4a2.1 2.1 0 0 0 0-3L17.6 4.6a2.1 2.1 0 0 0-3 0L4 15.2V20Zm2-2v-1.95l7.25-7.25 1.95 1.95L7.95 18H6Zm10.6-8.65L14.65 7.4 16 6.05 17.95 8l-1.35 1.35Z"/></svg>
                </button>
                <?php if ($lead['status'] !== 'CONVERTED'): ?>
                  <form method="post">
                    <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
                    <button class="icon-action success-action" name="action" value="convert_lead" type="submit" title="Convertir en cliente" aria-label="Convertir en cliente a <?= e(trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''))) ?>">
                      <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L21 7.6l-1.4-1.4L9.2 16.6Z"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
                <form method="post" data-confirm-message="Eliminar este lead? Esta accion no se puede deshacer.">
                  <input type="hidden" name="action" value="delete_lead">
                  <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
                  <button class="icon-action danger-action" type="submit" title="Eliminar lead" aria-label="Eliminar lead <?= e(trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''))) ?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 21a2 2 0 0 1-2-2V8h14v11a2 2 0 0 1-2 2H7ZM9 6V4h6v2h5v2H4V6h5Zm0 5v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$leads): ?>
          <tr data-live-search-empty>
            <td class="leads-empty-cell" colspan="10">No hay leads que coincidan con los filtros actuales.</td>
          </tr>
        <?php else: ?>
          <tr data-live-search-empty hidden>
            <td class="leads-empty-cell" colspan="10">No hay leads que coincidan con la busqueda actual.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($leads as $lead): ?>
  <?php $notes = $leadNotes[$lead['id']] ?? []; ?>
  <dialog id="lead-detail-<?= e($lead['id']) ?>" class="modal-card lead-detail-modal" aria-labelledby="lead-detail-title-<?= e($lead['id']) ?>">
    <header>
      <div>
        <h2 id="lead-detail-title-<?= e($lead['id']) ?>"><?= e(trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''))) ?></h2>
        <p><?= e($lead['phone'] ?: 'Sin telefono') ?> &middot; <?= e($lead['email'] ?: 'Sin email') ?></p>
      </div>
      <button data-close-modal type="button" aria-label="Cerrar detalles de <?= e(trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''))) ?>">Cerrar</button>
    </header>

    <form method="post" aria-label="Editar datos del lead <?= e(trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''))) ?>">
      <input type="hidden" name="action" value="update_lead">
      <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
      <div class="form-grid">
        <label class="field">
          <span>Nombre</span>
          <input name="first_name" required value="<?= e($lead['first_name']) ?>">
        </label>
        <label class="field">
          <span>Apellidos</span>
          <input name="last_name" value="<?= e($lead['last_name']) ?>">
        </label>
        <?php $phoneCountry = phone_country_entry($lead['phone']); ?>
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
          <input class="phone-number-input" name="phone_number" value="<?= e(phone_local_value($lead['phone'])) ?>" inputmode="tel" placeholder="Numero" aria-label="Numero de telefono">
          </div>
        </label>
        <label class="field">
          <span>Email</span>
          <input name="email" type="email" value="<?= e($lead['email']) ?>">
        </label>
        <label class="field">
          <span>Origen</span>
          <select name="source">
            <?php foreach (['WALK_IN', 'WEBSITE', 'WEB', 'LANDING', 'FORMULARIO_WEB', 'PHONE', 'SOCIAL_MEDIA', 'REFERRAL', 'OTHER'] as $source): ?>
              <option value="<?= e($source) ?>" <?= $lead['source'] === $source ? 'selected' : '' ?>><?= e(source_label($source)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="field">
          <span id="lead-stage-label-<?= e($lead['id']) ?>">Etapa</span>
          <div class="custom-select custom-select--field" data-custom-select>
            <input type="hidden" name="pipeline_stage_id" value="<?= e($lead['pipeline_stage_id']) ?>" data-custom-select-value>
            <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false" aria-labelledby="lead-stage-label-<?= e($lead['id']) ?>">
              <span data-custom-select-label><?= e($lead['stage_name']) ?></span>
            </button>
            <div class="custom-select-menu" data-custom-select-menu hidden>
            <?php foreach ($stages as $stage): ?>
              <button class="custom-select-option <?= $lead['pipeline_stage_id'] === $stage['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($stage['id']) ?>"><?= e($stage['name']) ?></button>
            <?php endforeach; ?>
            </div>
          </div>
        </div>
        <label class="field">
          <span>Proxima accion</span>
          <input name="next_action_at" type="datetime-local" value="<?= $lead['next_action_at'] ? e(date('Y-m-d\TH:i', strtotime($lead['next_action_at']))) : '' ?>">
        </label>
        <label class="field field--wide">
          <span>Interes principal</span>
          <input name="interest" value="<?= e($lead['interest']) ?>">
        </label>
      </div>
      <button class="primary-action" type="submit">Guardar cambios</button>
    </form>

    <section class="notes-panel">
      <h3>Notas internas</h3>
      <form method="post" class="note-form" aria-label="Anadir nota interna">
        <input type="hidden" name="action" value="add_lead_note">
        <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
        <label class="field">
          <span>Nueva nota</span>
          <textarea name="note" rows="3" placeholder="Escribe una nota sobre la llamada, visita o seguimiento..." required aria-label="Texto de la nueva nota"></textarea>
        </label>
        <button class="primary-action primary-action--compact" type="submit">Anadir nota</button>
      </form>

      <div class="notes-list">
        <?php foreach ($notes as $note): ?>
          <article class="note-item">
            <form method="post" class="note-edit-form">
              <input type="hidden" name="action" value="update_lead_note">
              <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
              <input type="hidden" name="note_id" value="<?= e($note['id']) ?>">
              <textarea name="note" rows="3" required aria-label="Editar nota interna"><?= e($note['note']) ?></textarea>
              <div class="note-meta-row">
                <span><?= e($note['user_name'] ?: 'Usuario') ?> &middot; <?= e(format_date($note['created_at'])) ?></span>
                <button class="note-save-button" type="submit" title="Guardar nota" aria-label="Guardar cambios de esta nota">
                  <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9.2 16.6 4.9 12.3l-1.4 1.4 5.7 5.7L21 7.6l-1.4-1.4L9.2 16.6Z"/></svg>
                  Guardar
                </button>
              </div>
            </form>
            <form method="post" class="note-delete-form" data-confirm-message="Eliminar esta nota? Esta accion no se puede deshacer.">
              <input type="hidden" name="action" value="delete_lead_note">
              <input type="hidden" name="id" value="<?= e($lead['id']) ?>">
              <input type="hidden" name="note_id" value="<?= e($note['id']) ?>">
              <button class="note-delete-button" type="submit" title="Eliminar nota" aria-label="Eliminar esta nota interna">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 21a2 2 0 0 1-2-2V8h14v11a2 2 0 0 1-2 2H7ZM9 6V4h6v2h5v2H4V6h5Zm0 5v7h2v-7H9Zm4 0v7h2v-7h-2Z"/></svg>
              </button>
            </form>
          </article>
        <?php endforeach; ?>
        <?php if (!$notes): ?>
          <p class="empty-note">Todavia no hay notas para este lead.</p>
        <?php endif; ?>
      </div>
    </section>
  </dialog>
<?php endforeach; ?>

<dialog id="lead-modal" class="modal-card" aria-labelledby="lead-modal-title">
  <form method="post">
    <input type="hidden" name="action" value="create_lead">
    <header>
      <h2 id="lead-modal-title">Nuevo lead</h2>
      <button data-close-modal type="button" aria-label="Cerrar formulario de nuevo lead">Cerrar</button>
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
      <?php $defaultPhoneCountry = phone_country_entry(null); ?>
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
      <label class="field">
        <span>Origen</span>
        <select name="source">
          <option value="WALK_IN">Visita presencial</option>
          <option value="WEBSITE">Web</option>
          <option value="WEB">Web externa</option>
          <option value="LANDING">Landing</option>
          <option value="FORMULARIO_WEB">Formulario web</option>
          <option value="PHONE">Telefono</option>
          <option value="SOCIAL_MEDIA">Redes sociales</option>
          <option value="REFERRAL">Recomendacion</option>
          <option value="OTHER">Otro</option>
        </select>
      </label>
      <div class="field">
        <span id="new-lead-stage-label">Etapa inicial</span>
        <div class="custom-select custom-select--field" data-custom-select>
          <input type="hidden" name="pipeline_stage_id" value="<?= e($stages[0]['id'] ?? '') ?>" data-custom-select-value>
          <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false" aria-labelledby="new-lead-stage-label">
            <span data-custom-select-label><?= e($stages[0]['name'] ?? 'Seleccionar etapa') ?></span>
          </button>
          <div class="custom-select-menu" data-custom-select-menu hidden>
          <?php foreach ($stages as $stage): ?>
            <button class="custom-select-option <?= ($stages[0]['id'] ?? '') === $stage['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($stage['id']) ?>"><?= e($stage['name']) ?></button>
          <?php endforeach; ?>
          </div>
        </div>
      </div>
      <label class="field field--wide">
        <span>Interes principal</span>
        <input name="interest" placeholder="Ej. Prueba de HIIT, plan premium, bono mensual">
      </label>
    </div>
    <button class="primary-action" type="submit">Crear lead</button>
  </form>
</dialog>

