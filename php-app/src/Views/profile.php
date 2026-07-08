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
        <span class="settings-kicker">Cuenta</span>
        <h2>Perfil</h2>
        <p>Datos personales, foto e inicio de sesion.</p>
      </div>
    </div>

    <form class="profile-editor-grid" method="post" action="index.php?return=profile" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_profile">

      <section class="settings-card settings-card--main">
        <header class="settings-card-header">
          <div>
            <span>Identidad</span>
            <h3>Informacion principal</h3>
          </div>
        </header>

        <div class="form-grid">
          <label class="field">
            <span>Nombre</span>
            <input name="name" required value="<?= e($user['name']) ?>" autocomplete="name">
          </label>
          <label class="field">
            <span>Email</span>
            <input name="email" required type="email" value="<?= e($user['email']) ?>" autocomplete="email">
          </label>
          <label class="field field--wide">
            <span>Nueva contrasena</span>
            <input name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Mantener contrasena actual">
          </label>
        </div>
      </section>

      <section class="settings-card">
        <header class="settings-card-header">
          <div>
            <span>Imagen</span>
            <h3>Foto de perfil</h3>
          </div>
        </header>

        <div class="profile-photo-panel">
          <?php if ($avatarPath !== ''): ?>
            <img class="profile-photo-preview" src="<?= e($avatarPath) ?>" alt="Foto actual de <?= e($user['name']) ?>">
          <?php else: ?>
            <span class="profile-photo-preview profile-photo-preview--initials" aria-hidden="true"><?= e($initial) ?></span>
          <?php endif; ?>
          <div class="profile-photo-controls">
            <label class="file-drop-field">
              <span>Subir imagen</span>
              <input name="avatar" type="file" accept="image/png,image/jpeg,image/webp">
            </label>
            <label class="settings-check">
              <input type="checkbox" name="remove_avatar" value="1" <?= $avatarPath === '' ? 'disabled' : '' ?>>
              <span>Quitar foto actual</span>
            </label>
          </div>
        </div>
      </section>

      <div class="settings-save-bar">
        <a class="secondary-action" href="index.php?route=<?= is_platform_admin($user) ? 'platform-dashboard' : 'dashboard' ?>">Volver</a>
        <button class="primary-action" type="submit">Guardar perfil</button>
      </div>
    </form>
  </section>
</div>
