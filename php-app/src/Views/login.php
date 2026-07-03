<?php
$flash = flash();
$platformAdminEmail = EmpresaRepository::PLATFORM_ADMIN_EMAIL;
$platformAdminPassword = EmpresaRepository::PLATFORM_ADMIN_PASSWORD;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Membora CRM - Login</title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
  <main class="login-screen">
    <div class="login-overlay"></div>
    <section class="login-panel">
      <div class="brand-lockup brand-lockup--login">
        <div class="brand-icon">M</div>
        <div>
          <h1>Membora CRM</h1>
          <p>Portal de gestion fitness</p>
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
          <div class="input-shell"><input name="email" type="email" required value="<?= e($platformAdminEmail) ?>" data-login-email></div>
        </label>
        <label class="field">
          <span>Contrasena</span>
          <div class="input-shell"><input name="password" type="password" required value="<?= e($platformAdminPassword) ?>" data-login-password></div>
        </label>
        <button class="primary-action" type="submit">Iniciar sesion</button>
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
            <span>La demo cliente se reinicia automaticamente cada 24 horas.</span>
            <span>La demo administrador abre el panel SaaS.</span>
          </div>
        </div>
      </form>
      <form id="demo-client-login" method="post" hidden>
        <input type="hidden" name="action" value="demo_login">
        <input type="hidden" name="demo_type" value="client">
      </form>
      <form id="demo-admin-login" method="post" hidden>
        <input type="hidden" name="action" value="demo_login">
        <input type="hidden" name="demo_type" value="admin">
      </form>
    </section>
  </main>
</body>
</html>
