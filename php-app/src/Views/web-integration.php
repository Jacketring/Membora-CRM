<div class="page-heading">
  <div>
    <h2>Captacion web</h2>
    <p>Conecta tu pagina web o landing para recibir nuevos leads automaticamente en Membora CRM.</p>
  </div>
</div>

<section class="webhook-grid">
  <article class="webhook-card webhook-card--wide">
    <header class="webhook-card-header">
      <div>
        <h3>Configuracion del webhook</h3>
        <p>Usa esta URL y token secreto en formularios externos del gimnasio.</p>
      </div>
      <span class="status-badge <?= (int) $settings['is_active'] === 1 ? 'status-badge--active' : 'status-badge--cancelled' ?>">
        <?= (int) $settings['is_active'] === 1 ? 'Activa' : 'Inactiva' ?>
      </span>
    </header>

    <div class="webhook-copy-list">
      <label class="field">
        <span>URL publica</span>
        <div class="copy-field">
          <input readonly value="<?= e($webhookUrl) ?>" data-copy-source="webhook-url">
          <button class="secondary-action" type="button" data-copy-target="webhook-url">Copiar URL</button>
        </div>
      </label>
      <label class="field">
        <span>Token secreto</span>
        <div class="copy-field">
          <input readonly value="<?= e($token ?: ($settings['token_preview'] ?? 'Token no disponible')) ?>" data-copy-source="webhook-token">
          <button class="secondary-action" type="button" data-copy-target="webhook-token">Copiar token</button>
        </div>
        <small>Vista previa guardada: <?= e($settings['token_preview'] ?? 'Sin token') ?>. Regenera el token si necesitas rotarlo.</small>
      </label>
    </div>

    <div class="webhook-actions">
      <form method="post">
        <input type="hidden" name="action" value="update_webhook_settings">
        <input type="hidden" name="is_active" value="<?= (int) $settings['is_active'] === 1 ? '0' : '1' ?>">
        <button class="secondary-action" type="submit"><?= (int) $settings['is_active'] === 1 ? 'Desactivar integracion' : 'Activar integracion' ?></button>
      </form>
      <form method="post" data-confirm-message="Regenerar el token? Los formularios antiguos dejaran de funcionar.">
        <input type="hidden" name="action" value="regenerate_webhook_token">
        <button class="primary-action primary-action--compact" type="submit">Regenerar token</button>
      </form>
    </div>
  </article>

  <article class="webhook-card">
    <header class="webhook-card-header">
      <div>
        <h3>Codigo HTML basico</h3>
        <p>Formulario listo para pegar en una pagina externa.</p>
      </div>
      <button class="secondary-action" type="button" data-copy-target="webhook-html">Copiar HTML</button>
    </header>
    <pre class="code-sample"><code data-copy-source="webhook-html"><?= e($htmlExample) ?></code></pre>
  </article>

  <article class="webhook-card">
    <header class="webhook-card-header">
      <div>
        <h3>Ejemplo JavaScript fetch</h3>
        <p>Envio JSON desde landing, WordPress o formulario propio.</p>
      </div>
      <button class="secondary-action" type="button" data-copy-target="webhook-js">Copiar JS</button>
    </header>
    <pre class="code-sample"><code data-copy-source="webhook-js"><?= e($jsExample) ?></code></pre>
  </article>
</section>

<section class="webhook-grid webhook-grid--bottom">
  <article class="webhook-card">
    <header class="webhook-card-header">
      <div>
        <h3>Probar integracion</h3>
        <p>Crea un lead real de prueba usando el mismo flujo del webhook.</p>
      </div>
    </header>
    <form class="form-grid" method="post">
      <input type="hidden" name="action" value="test_webhook_lead">
      <label class="field">
        <span>Nombre</span>
        <input name="nombre" required value="Lead web prueba">
      </label>
      <label class="field">
        <span>Apellidos</span>
        <input name="apellidos" value="Membora">
      </label>
      <label class="field">
        <span>Email</span>
        <input name="email" type="email" value="lead.web.prueba@example.com">
      </label>
      <label class="field">
        <span>Telefono</span>
        <input name="telefono" inputmode="tel" value="+34600000000">
      </label>
      <label class="field field--wide">
        <span>Mensaje</span>
        <textarea name="mensaje" rows="3">Quiero informacion desde el formulario web.</textarea>
      </label>
      <button class="primary-action primary-action--compact" type="submit">Enviar prueba</button>
    </form>
  </article>

  <article class="webhook-card">
    <header class="webhook-card-header">
      <div>
        <h3>Ultimos envios recibidos</h3>
        <p>Registro tecnico de captaciones, duplicados y errores.</p>
      </div>
    </header>
    <div class="webhook-log-list">
      <?php foreach ($logs as $log): ?>
        <?php
          $leadName = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));
          $statusClass = match ($log['status']) {
            'success' => 'status-badge--active',
            'duplicate' => 'status-badge--pending',
            'blocked', 'error' => 'status-badge--cancelled',
            default => 'status-badge--scheduled',
          };
        ?>
        <article class="webhook-log-item">
          <div>
            <strong><?= e($leadName ?: ($log['email'] ?? 'Envio externo')) ?></strong>
            <span><?= e(format_date($log['created_at'])) ?> &middot; <?= e($log['ip_address'] ?: 'Sin IP') ?></span>
            <?php if (!empty($log['error_message'])): ?>
              <small><?= e($log['error_message']) ?></small>
            <?php endif; ?>
          </div>
          <span class="status-badge <?= e($statusClass) ?>"><?= e($log['status']) ?></span>
        </article>
      <?php endforeach; ?>
      <?php if (!$logs): ?>
        <p class="empty-note">Todavia no hay envios recibidos por webhook.</p>
      <?php endif; ?>
    </div>
  </article>
</section>
