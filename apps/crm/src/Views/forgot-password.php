<?php $flash = flash(); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#004bf2"><title>Membora - Recuperar contraseña</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg"><link rel="stylesheet" href="assets/app.css">
</head>
<body>
  <main class="login-screen"><div class="login-overlay"></div><section class="login-panel">
    <div class="brand-lockup brand-lockup--login"><img class="brand-logo brand-logo--login" src="assets/membora-logo.svg" alt="Membora"><div><p>Portal de gestión fitness</p></div></div>
    <form class="login-card" method="post">
      <input type="hidden" name="action" value="request_password_reset">
      <header><h2>Recupera tu contraseña</h2><p>Indica el email de tu cuenta y te enviaremos un enlace temporal.</p></header>
      <?php if ($flash): ?><div class="notice <?= $flash['type'] === 'error' ? 'notice-error' : 'notice-success' ?>"><?= e($flash['message']) ?></div><?php endif; ?>
      <label class="field"><span>Email</span><div class="input-shell"><input name="email" type="email" required autocomplete="email" autofocus></div></label>
      <button class="primary-action" type="submit">Enviar enlace</button>
      <a class="login-back-link" href="index.php?route=login">Volver al inicio de sesión</a>
    </form>
  </section></main>
</body>
</html>
