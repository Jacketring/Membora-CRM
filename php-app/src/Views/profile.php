<div class="page-heading">
  <div>
    <h2>Mi perfil</h2>
    <p>Actualiza tus datos personales, foto y credenciales de acceso.</p>
  </div>
</div>

<?php require __DIR__ . '/partials/settings-nav.php'; ?>

<section class="settings-page-card">
  <form method="post" action="index.php?return=profile" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_profile">
    <div class="profile-settings-head">
      <?php if (!empty($user['avatar_path'])): ?>
        <img class="profile-settings-avatar" src="<?= e($user['avatar_path']) ?>" alt="Foto de <?= e($user['name']) ?>">
      <?php else: ?>
        <span class="profile-settings-avatar profile-settings-avatar--initials" aria-hidden="true"><?= e(substr($user['name'], 0, 1)) ?></span>
      <?php endif; ?>
      <div>
        <strong><?= e($user['name']) ?></strong>
        <span><?= e(role_label($user['role'])) ?></span>
      </div>
    </div>

    <div class="form-grid">
      <label class="field">
        <span>Nombre</span>
        <input name="name" required value="<?= e($user['name']) ?>" autocomplete="name">
      </label>
      <label class="field">
        <span>Email</span>
        <input name="email" required type="email" value="<?= e($user['email']) ?>" autocomplete="email">
      </label>
      <label class="field">
        <span>Foto de perfil</span>
        <input name="avatar" type="file" accept="image/png,image/jpeg,image/webp">
      </label>
      <div class="field settings-checkbox-field">
        <span>Imagen actual</span>
        <label><input type="checkbox" name="remove_avatar" value="1" <?= empty($user['avatar_path']) ? 'disabled' : '' ?>> Quitar foto actual</label>
      </div>
      <label class="field field--wide">
        <span>Nueva contrasena</span>
        <input name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Dejalo vacio para mantener la actual">
      </label>
    </div>

    <button class="primary-action" type="submit">Guardar perfil</button>
  </form>
</section>
