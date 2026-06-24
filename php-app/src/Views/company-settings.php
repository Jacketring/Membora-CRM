<div class="page-heading">
  <div>
    <h2>Empresa</h2>
    <p>Configura la marca visible del centro que usa este CRM.</p>
  </div>
</div>

<?php require __DIR__ . '/partials/settings-nav.php'; ?>

<section class="settings-page-card">
  <form method="post" action="index.php?return=company-settings">
    <input type="hidden" name="action" value="update_company_settings">
    <div class="form-grid">
      <label class="field">
        <span>Nombre de empresa</span>
        <input name="tenant_name" required value="<?= e($user['tenant_name'] ?? 'Membora CRM') ?>">
      </label>
      <label class="field">
        <span>Color por defecto</span>
        <input class="color-setting" name="tenant_primary_color" type="color" value="<?= e(hex_color_or_default($user['tenant_primary_color'] ?? '#0754d6')) ?>">
      </label>
      <div class="settings-info-card field--wide">
        <strong>Configuracion comercial</strong>
        <p>Este nombre aparece en el menu lateral y en el panel. El color por defecto se usa para nuevos usuarios o navegadores sin preferencias personales.</p>
      </div>
    </div>
    <button class="primary-action" type="submit">Guardar empresa</button>
  </form>
</section>
