<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
  <title>Acceso a Membora</title>
  <link rel="stylesheet" href="assets/app.css?v=20260716-trial-credentials">
</head>
<body>
  <main class="login-screen"><div class="login-overlay"></div>
    <section class="login-panel">
      <div class="brand-lockup brand-lockup--login"><img class="brand-logo brand-logo--login" src="assets/membora-logo.svg" alt="Membora"><div><p>Portal de gestión fitness</p></div></div>

      <?php if (is_array($credentials ?? null)): ?>
        <article class="login-card">
          <header><h2>Guarda tu contraseña ahora</h2><p>Acceso preparado para <?= e($credentials['company']) ?>.</p></header>
          <p>Estos datos pertenecen a <strong><?= e($credentials['company']) ?></strong>. Al cerrar o recargar esta página la contraseña no volverá a mostrarse.</p>
          <div class="trial-credential-box">
            <span>Usuario</span>
            <strong><?= e($credentials['email']) ?></strong>
            <span>Contraseña temporal</span>
            <code><?= e($credentials['password']) ?></code>
          </div>
          <a class="primary-action login-primary-link" href="index.php?route=login">Ir a iniciar sesión</a>
        </article>
      <?php elseif (!empty($tokenValid)): ?>
        <form class="login-card" method="post" action="index.php?route=trial-credentials" autocomplete="off">
          <input type="hidden" name="action" value="reveal_trial_credentials">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <header><h2>Tu contraseña está lista</h2><p>Visualización única y protegida.</p></header>
          <p class="trial-credential-intro">Pulsa el botón únicamente cuando puedas guardarla. Por seguridad solo se mostrará una vez.</p>
          <button class="primary-action" type="submit">Mostrar mi contraseña</button>
        </form>
      <?php else: ?>
        <article class="login-card">
          <header><h2>Enlace no disponible</h2><p>La contraseña ya se mostró o el enlace ha caducado.</p></header>
          <p>Si no la guardaste, utiliza la recuperación de contraseña para definir una nueva.</p>
          <a class="primary-action login-primary-link" href="index.php?route=forgot-password">Recuperar contraseña</a>
        </article>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
