<?php
$avatarPath = (string) (($user['avatar_path'] ?? '') ?: '');
$initial = strtoupper(substr((string) ($user['name'] ?? 'U'), 0, 1));
?>

<div class="settings-shell">
  <aside class="settings-rail">
    <div class="settings-identity-card">
      <?php if ($avatarPath !== ''): ?>
        <img class="settings-identity-avatar" src="<?= e($avatarPath) ?>" alt="Foto de <?= e($user['name']) ?>">
      <?php else: ?>
        <span class="settings-identity-avatar settings-identity-avatar--initials" aria-hidden="true"><?= e($initial) ?></span>
      <?php endif; ?>
      <strong><?= e($user['name']) ?></strong>
      <span><?= e(role_label($user['role'])) ?></span>
      <small><?= e($user['email']) ?></small>
    </div>

    <?php require __DIR__ . '/partials/settings-nav.php'; ?>
  </aside>

  <section class="settings-workspace">
    <div class="page-heading settings-heading">
      <div>
        <span class="settings-kicker">Preferencias</span>
        <h2>Configuracion</h2>
        <p>Apariencia, color y densidad de trabajo.</p>
      </div>
    </div>

    <form class="settings-editor" data-crm-settings-form>
      <section class="settings-card settings-card--main">
        <header class="settings-card-header">
          <div>
            <span>Tema</span>
            <h3>Modo visual</h3>
          </div>
        </header>

        <div class="theme-choice-grid">
          <label class="theme-choice">
            <input type="radio" name="theme" value="light" data-setting-theme>
            <span class="theme-choice-preview theme-choice-preview--light"></span>
            <strong>Claro</strong>
          </label>
          <label class="theme-choice">
            <input type="radio" name="theme" value="dark" data-setting-theme>
            <span class="theme-choice-preview theme-choice-preview--dark"></span>
            <strong>Oscuro</strong>
          </label>
        </div>
      </section>

      <section class="settings-card settings-preference-grid">
        <label class="preference-card preference-card--color">
          <span>Color personal</span>
          <strong>Color de acento</strong>
          <input class="color-setting" type="color" name="accent" value="<?= e(hex_color_or_default($user['tenant_primary_color'] ?? '#004bf2')) ?>" data-setting-accent>
        </label>

        <label class="preference-card preference-card--toggle">
          <span>Densidad</span>
          <strong>Interfaz compacta</strong>
          <input type="checkbox" name="compact" value="1" data-setting-compact>
          <i aria-hidden="true"></i>
        </label>
      </section>

      <div class="settings-save-bar">
        <button class="secondary-action" type="button" data-settings-reset>Restablecer</button>
        <button class="primary-action primary-action--compact" type="submit">Guardar configuracion</button>
      </div>
    </form>
  </section>
</div>
