<?php
$flash = flash();
$platformAdminEmail = EmpresaRepository::PLATFORM_ADMIN_EMAIL;
$demoEnabled = DemoAccessPolicy::isEnabled((string) getenv('APP_ENV'));
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#004bf2">
  <title>Membora CRM - Login</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
  <main class="login-screen">
    <div class="login-overlay"></div>
    <section class="login-panel">
      <div class="brand-lockup brand-lockup--login">
        <img class="brand-logo brand-logo--login" src="assets/membora-logo.svg" alt="Membora CRM">
        <div>
          <h1>Membora CRM</h1>
          <p>Portal de gestión fitness</p>
        </div>
      </div>
      <form class="login-card" method="post">
        <input type="hidden" name="action" value="login">
        <header>
          <h2>Accede a tu CRM</h2>
          <p>Introduce tus credenciales para gestionar tu centro.</p>
        </header>
        <?php if ($flash): ?>
          <div class="notice <?= $flash['type'] === 'error' ? 'notice-error' : 'notice-success' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <label class="field">
          <span>Email</span>
          <div class="input-shell"><input name="email" type="email" required value="" data-login-email autocomplete="username"></div>
        </label>
        <label class="field">
          <span>Contraseña</span>
          <div class="input-shell"><input name="password" type="password" required value="" data-login-password autocomplete="current-password"></div>
        </label>
        <button class="primary-action" type="submit">Iniciar sesión</button>
        <?php if ($demoEnabled): ?>
        <div class="demo-login-actions" aria-label="Accesos demo">
          <button class="demo-login-action demo-login-action--client" type="submit" form="demo-client-login">
            Demo cliente
          </button>
          <button class="demo-login-action demo-login-action--admin" type="submit" form="demo-admin-login">
            Demo administrador
          </button>
        </div>
        <div class="demo-note demo-note--platform">
          <div>
            <strong>Acceso de evaluacion</strong>
            <span>Las demos abren una sesión temporal de 20 minutos.</span>
            <span>Al finalizar, el sistema cierra la sesión y vuelve a la web pública.</span>
          </div>
        </div>
        <?php endif; ?>
      </form>
      <?php if ($demoEnabled): ?>
      <form id="demo-client-login" method="post" hidden>
        <input type="hidden" name="action" value="demo_login">
        <input type="hidden" name="demo_type" value="client">
      </form>
      <?php endif; ?>
      <form id="demo-admin-login" method="post" hidden>
        <input type="hidden" name="action" value="demo_login">
        <input type="hidden" name="demo_type" value="admin">
      </form>
    </section>
  </main>
</body>
</html>
