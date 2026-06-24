<div class="page-heading">
  <div>
    <h2>Configuracion</h2>
    <p>Ajusta la apariencia del CRM solo para tu navegador y tu forma de trabajar.</p>
  </div>
</div>

<?php require __DIR__ . '/partials/settings-nav.php'; ?>

<section class="settings-page-card">
  <form data-crm-settings-form>
    <div class="settings-grid">
      <fieldset class="settings-panel">
        <legend>Modo visual</legend>
        <label><input type="radio" name="theme" value="system" data-setting-theme> Sistema</label>
        <label><input type="radio" name="theme" value="light" data-setting-theme> Claro</label>
        <label><input type="radio" name="theme" value="dark" data-setting-theme> Oscuro</label>
      </fieldset>
      <label class="settings-panel">
        <span>Color personal</span>
        <input class="color-setting" type="color" name="accent" value="<?= e(hex_color_or_default($user['tenant_primary_color'] ?? '#0754d6')) ?>" data-setting-accent>
        <small>Este color solo se aplica en tu navegador.</small>
      </label>
      <label class="settings-panel settings-toggle">
        <span>Interfaz compacta</span>
        <input type="checkbox" name="compact" value="1" data-setting-compact>
        <small>Reduce espacios para tablas y trabajo diario.</small>
      </label>
    </div>
    <div class="settings-actions">
      <button class="secondary-action" type="button" data-settings-reset>Restablecer</button>
      <button class="primary-action primary-action--compact" type="submit">Guardar configuracion</button>
    </div>
  </form>
</section>
