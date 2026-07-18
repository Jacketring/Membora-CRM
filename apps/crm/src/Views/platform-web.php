<?php
$logs = $logs ?? [];
$mailDiagnostics = $mailDiagnostics ?? [];
$webhookUrl = $webhookUrl ?? app_base_url() . '/webhook/lead';
$webUrl = $webUrl ?? 'https://membora.es';
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Web comercial</h2>
    <p>Estado de conexion entre la web pública de Membora y los leads comerciales del administrador.</p>
  </div>
  <div class="platform-heading-actions">
    <a class="secondary-action" href="index.php?route=platform-contacts&type=lead">Ver contactos</a>
    <a class="primary-action" href="<?= e(rtrim((string) $webUrl, '/')) ?>" target="_blank" rel="noreferrer">Abrir web</a>
  </div>
</div>

<section class="platform-admin-grid platform-admin-grid--web">
  <article class="platform-panel platform-panel--wide">
    <header>
      <div>
        <h3>Flujo actual</h3>
        <p>El formulario publico crea solicitudes comerciales en `Administración Membora > Contactos`.</p>
      </div>
      <span>Sin token</span>
    </header>
    <div class="web-info-list">
      <div>
        <span>1. Visitante envia el formulario</span>
        <strong><?= e(rtrim((string) $webUrl, '/')) ?></strong>
      </div>
      <div>
        <span>2. La plataforma recibe la solicitud</span>
        <strong><?= e($webhookUrl) ?></strong>
      </div>
      <div>
        <span>3. El administrador la gestiona</span>
        <strong>Administración Membora > Contactos > Convertir en cliente</strong>
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
        <strong>Origen permitido, honeypot y límite de envíos</strong>
      </div>
    </div>
  </article>

  <article class="platform-panel platform-panel--wide">
    <header>
      <div>
        <h3>Prueba de correo</h3>
        <p>Envia un email tecnico para comprobar SMTP sin rellenar el formulario publico.</p>
      </div>
      <span>Email</span>
    </header>
    <form class="lead-toolbar platform-toolbar" method="post">
      <input type="hidden" name="action" value="send_platform_test_email">
      <label class="field platform-search">
        <span>Destino</span>
        <input name="email" type="email" value="josehur2003@gmail.com" placeholder="correo@ejemplo.com" required>
      </label>
      <button class="primary-action" type="submit">Enviar prueba</button>
    </form>
  </article>

  <article class="platform-panel platform-panel--wide">
    <header>
      <div>
        <h3>Reiniciar alta de prueba</h3>
        <p>Invalida solicitudes pendientes o fallidas para poder enviar un enlace de activación nuevo.</p>
      </div>
      <span>14 días</span>
    </header>
    <form class="lead-toolbar platform-toolbar" method="post">
      <input type="hidden" name="action" value="reset_platform_trial_attempts">
      <label class="field platform-search">
        <span>Email de la solicitud</span>
        <input name="email" type="email" value="josehurtadodev@gmail.com" placeholder="correo@ejemplo.com" required>
      </label>
      <button class="secondary-action" type="submit">Reiniciar intentos</button>
    </form>
  </article>

  <article class="platform-panel">
    <header>
      <div>
        <h3>Configuración detectada</h3>
        <p>Valores actuales leidos desde `apps/crm/.env`.</p>
      </div>
      <span><?= e($mailDiagnostics['transport'] ?? 'Sin dato') ?></span>
    </header>
    <div class="web-info-list">
      <?php foreach ($mailDiagnostics as $label => $value): ?>
        <div>
          <span><?= e(str_replace('_', ' ', ucfirst($label))) ?></span>
          <strong><?= e((string) $value) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>

<section class="leads-table-card">
  <header>
    <div>
      <h3>Ultimos envíos tecnicos</h3>
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
          <?php $statusClass = in_array($status, ['success', 'duplicate', 'email_test', 'trial_email'], true) ? 'active' : ($status === 'email_error' ? 'pending' : 'cancelled'); ?>
          <tr>
            <td><?= e(format_date($log['created_at'])) ?></td>
            <td><span class="status-badge status-badge--<?= e($statusClass) ?>"><?= e(webhook_status_label($status)) ?></span></td>
            <td><?= e($log['source_url'] ?: 'Web pública') ?></td>
            <td><?= e($log['error_message'] ?: 'Solicitud procesada') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?>
          <tr><td colspan="4" class="empty-state">Todavía no se han recibido formularios desde la web.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
