<?php $flash = flash(); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#004bf2"><title>Membora CRM - Nueva contraseña</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg"><link rel="stylesheet" href="assets/app.css">
</head>
<body>
  <main class="login-screen"><div class="login-overlay"></div><section class="login-panel">
    <div class="brand-lockup brand-lockup--login"><img class="brand-logo brand-logo--login" src="assets/membora-logo.svg" alt="Membora CRM"><div><p>Portal de gestión fitness</p></div></div>
    <div class="login-card">
      <header><h2>Nueva contraseña</h2><p><?= $tokenValid ? 'Introduce una contraseña de al menos 8 caracteres.' : 'Este enlace ya no se puede utilizar.' ?></p></header>
      <?php if ($flash): ?><div class="notice <?= $flash['type'] === 'error' ? 'notice-error' : 'notice-success' ?>"><?= e($flash['message']) ?></div><?php endif; ?>
      <?php if ($tokenValid): ?>
        <form method="post">
          <input type="hidden" name="action" value="reset_password"><input type="hidden" name="token" value="<?= e($token) ?>">
          <label class="field"><span>Nueva contraseña</span><div class="input-shell"><input name="password" type="password" required minlength="8" autocomplete="new-password" autofocus></div></label>
          <label class="field"><span>Repite la contraseña</span><div class="input-shell"><input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"></div></label>
          <button class="primary-action" type="submit">Cambiar contraseña</button>
        </form>
      <?php else: ?>
        <div class="notice notice-error">El enlace no es válido o ha caducado.</div>
        <a class="primary-action login-primary-link" href="index.php?route=forgot-password">Solicitar otro enlace</a>
      <?php endif; ?>
      <a class="login-back-link" href="index.php?route=login">Volver al inicio de sesión</a>
    </div>
  </section></main>
</body>
</html>
