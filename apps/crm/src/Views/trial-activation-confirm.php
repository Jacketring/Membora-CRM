<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#004bf2">
  <title>Membora - Activar prueba</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link rel="stylesheet" href="assets/app.css?v=20260716-trial-confirm">
</head>
<body>
  <main class="login-screen">
    <div class="login-overlay"></div>
    <section class="login-panel">
      <div class="brand-lockup brand-lockup--login">
        <img class="brand-logo brand-logo--login" src="assets/membora-logo.svg" alt="Membora">
        <div><p>Portal de gestión fitness</p></div>
      </div>
      <form class="login-card" method="post" action="index.php?route=activate-trial">
        <input type="hidden" name="action" value="confirm_trial_activation">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <header>
          <h2>Activa tu prueba gratuita</h2>
          <p>Confirma para crear tu empresa, tu espacio en Membora y el acceso durante 14 días.</p>
        </header>
        <div class="notice notice-success">El enlace es válido y está listo para utilizarse.</div>
        <button class="primary-action" type="submit">Activar prueba de 14 días</button>
        <a class="login-back-link" href="https://membora.es/">Volver a Membora</a>
      </form>
    </section>
  </main>
</body>
</html>
