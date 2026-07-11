<div class="page-heading">
  <div>
    <h2>Facturación</h2>
    <p>Configura una integración externa genérica y exporta pagos del gimnasio.</p>
  </div>
  <div class="page-actions">
    <a class="secondary-action" href="index.php?route=billing-export">Exportar CSV</a>
    <form method="post">
      <input type="hidden" name="action" value="sync_billing_integration">
      <button class="primary-action primary-action--compact" type="submit">Sincronizar</button>
    </form>
  </div>
</div>

<section class="lead-metrics" aria-label="Resumen de facturación">
  <article class="lead-metric lead-metric--orange">
    <span>Pendientes</span>
    <strong><?= (int) $metrics['pending'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--blue">
    <span>Exportados</span>
    <strong><?= (int) $metrics['exported'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--green">
    <span>Sincronizados</span>
    <strong><?= (int) $metrics['synced'] ?></strong>
  </article>
  <article class="lead-metric lead-metric--dark">
    <span>Errores</span>
    <strong><?= (int) $metrics['errors'] ?></strong>
  </article>
</section>

<section class="dashboard-layout">
  <article class="panel-card">
    <header>
      <div>
        <h3>Proveedor externo</h3>
        <p>Configuración usada para exportacion o sincronizacion simulada.</p>
      </div>
      <span class="status-badge status-badge--<?= e(strtolower($settings['status'])) ?>"><?= e(billing_sync_status_label($settings['status'])) ?></span>
    </header>

    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="save_billing_integration">
      <label class="field">
        <span>Proveedor</span>
        <input name="provider_name" required value="<?= e($settings['provider_name']) ?>" placeholder="Holded, Quaderno, Stripe...">
      </label>
      <label class="field">
        <span>Endpoint</span>
        <input name="endpoint_url" type="url" value="<?= e($settings['endpoint_url']) ?>" placeholder="https://api.proveedor.test/invoices">
      </label>
      <label class="field">
        <span>API key</span>
        <input name="api_key" type="password" autocomplete="new-password" placeholder="<?= e($settings['api_key_mask'] ?: 'Guardar nueva clave') ?>">
      </label>
      <div class="field">
        <span>Estado</span>
        <div class="custom-select custom-select--field" data-custom-select>
          <input type="hidden" name="status" value="<?= e($settings['status']) ?>" data-custom-select-value>
          <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
            <span data-custom-select-label><?= e(billing_sync_status_label($settings['status'])) ?></span>
          </button>
          <div class="custom-select-menu" data-custom-select-menu hidden>
            <?php foreach (['ACTIVE', 'INACTIVE'] as $status): ?>
              <button class="custom-select-option <?= $settings['status'] === $status ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($status) ?>">
                <?= e(billing_sync_status_label($status)) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="field">
        <span>Formato</span>
        <div class="custom-select custom-select--field" data-custom-select>
          <input type="hidden" name="export_format" value="<?= e($settings['export_format']) ?>" data-custom-select-value>
          <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
            <span data-custom-select-label><?= e($settings['export_format']) ?></span>
          </button>
          <div class="custom-select-menu" data-custom-select-menu hidden>
            <?php foreach (['CSV', 'JSON'] as $format): ?>
              <button class="custom-select-option <?= $settings['export_format'] === $format ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($format) ?>">
                <?= e($format) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <label class="field field--wide">
        <span>Notas</span>
        <textarea name="notes" rows="3" placeholder="Condiciones, proveedor previsto o detalles operativos"><?= e($settings['notes']) ?></textarea>
      </label>
      <button class="primary-action" type="submit">Guardar configuración</button>
    </form>
  </article>

  <article class="panel-card">
    <header>
      <div>
        <h3>Ultimos envíos</h3>
        <p>Registro de exportaciones y sincronizaciones.</p>
      </div>
    </header>
    <div class="activity-list">
      <?php foreach (array_slice($logs, 0, 6) as $log): ?>
        <div class="activity-item">
          <span><?= e(substr(billing_operation_label($log['operation']), 0, 1)) ?></span>
          <div>
            <strong><?= e(billing_operation_label($log['operation'])) ?> · <?= e(billing_sync_status_label($log['status'])) ?></strong>
            <p><?= e($log['message']) ?></p>
            <small><?= e((string) $log['payments_count']) ?> pagos · <?= e(money_amount($log['total_amount'])) ?> · <?= e(format_date($log['created_at'])) ?></small>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$logs): ?>
        <div class="empty-setup-card">
          <h3>Sin envíos todavía</h3>
          <p>Exporta o sincroniza pagos para generar el primer registro.</p>
        </div>
      <?php endif; ?>
    </div>
  </article>
</section>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Pagos preparados para facturación</h3>
      <span><?= count($payments) ?> pagos pagados pendientes de envio o reenvio</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table">
      <caption class="sr-only">Pagos pendientes de integración externa</caption>
      <thead>
        <tr>
          <th scope="col">Socio</th>
          <th scope="col">Membresía</th>
          <th scope="col">Importe</th>
          <th scope="col">Pagado</th>
          <th scope="col">Estado externo</th>
          <th scope="col">Referencia</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $payment): ?>
          <tr>
            <td>
              <strong><?= e(trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? ''))) ?></strong>
              <small class="table-subtext"><?= e($payment['email'] ?: 'Sin email') ?></small>
            </td>
            <td><?= e($payment['plan_name'] ?: 'Sin membresía') ?></td>
            <td><?= e(money_amount($payment['amount'])) ?></td>
            <td><?= e(format_date_short($payment['paid_at'])) ?></td>
            <td><span class="status-badge status-badge--<?= e(strtolower($payment['external_sync_status'])) ?>"><?= e(billing_sync_status_label($payment['external_sync_status'])) ?></span></td>
            <td><?= e($payment['external_reference'] ?: 'Sin referencia') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?>
          <tr>
            <td class="leads-empty-cell" colspan="6">No hay pagos pagados pendientes de enviar.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
