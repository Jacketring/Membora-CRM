<?php
$logs = $logs ?? [];
$webhookUrl = $webhookUrl ?? app_base_url() . '/webhook/lead';
$webUrl = $webUrl ?? 'https://app.web.josehurtado.dev';
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Web comercial</h2>
    <p>Estado de conexion entre la web publica de Membora CRM y los leads comerciales del administrador.</p>
  </div>
  <div class="platform-heading-actions">
    <a class="secondary-action" href="index.php?route=platform-leads">Ver leads</a>
    <a class="primary-action" href="<?= e(rtrim((string) $webUrl, '/')) ?>" target="_blank" rel="noreferrer">Abrir web</a>
  </div>
</div>

<section class="platform-admin-grid platform-admin-grid--web">
  <article class="platform-panel platform-panel--wide">
    <header>
      <div>
        <h3>Flujo actual</h3>
        <p>El formulario publico crea solicitudes comerciales en `Admin CRM > Leads`.</p>
      </div>
      <span>Sin token</span>
    </header>
    <div class="web-info-list">
      <div>
        <span>1. Visitante envia el formulario</span>
        <strong><?= e(rtrim((string) $webUrl, '/')) ?></strong>
      </div>
      <div>
        <span>2. El CRM recibe la solicitud</span>
        <strong><?= e($webhookUrl) ?></strong>
      </div>
      <div>
        <span>3. El administrador la gestiona</span>
        <strong>Admin CRM > Leads > Convertir en cliente</strong>
      </div>
    </div>
  </article>

  <article class="platform-panel">
    <header>
      <div>
        <h3>Seguridad</h3>
        <p>No hay que copiar tokens ni tocar JavaScript en Plesk.</p>
      </div>
    </header>
    <div class="web-info-list">
      <div>
        <span>Dominio permitido</span>
        <strong><?= e(rtrim((string) $webUrl, '/')) ?></strong>
      </div>
      <div>
        <span>Protecciones</span>
        <strong>Origen permitido, honeypot y limite de envios</strong>
      </div>
    </div>
  </article>
</section>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Ultimos envios tecnicos</h3>
      <span><?= count($logs) ?> registros</span>
    </div>
  </header>
  <div class="leads-table-wrap">
    <table class="leads-table platform-table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Estado</th>
          <th>Origen</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
          <?php $status = (string) ($log['status'] ?? ''); ?>
          <tr>
            <td><?= e(format_date($log['created_at'])) ?></td>
            <td><span class="status-badge status-badge--<?= e($status === 'success' || $status === 'duplicate' ? 'active' : 'cancelled') ?>"><?= e(webhook_status_label($status)) ?></span></td>
            <td><?= e($log['source_url'] ?: 'Web publica') ?></td>
            <td><?= e($log['error_message'] ?: 'Solicitud procesada') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?>
          <tr><td colspan="4" class="empty-state">Todavia no se han recibido formularios desde la web.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
