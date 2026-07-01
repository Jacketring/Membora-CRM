<?php
$cssPath = __DIR__ . '/../../public/assets/app.css';
$jsPath = __DIR__ . '/../../public/assets/app.js';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '1';
$jsVersion = is_file($jsPath) ? (string) filemtime($jsPath) : '1';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0754d6">
  <title><?= e($title) ?> - Membora CRM</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link rel="stylesheet" href="assets/app.css?v=<?= e($cssVersion) ?>">
</head>
<?php
$tenantPrimaryColor = hex_color_or_default($user['tenant_primary_color'] ?? '#0754d6');
$isPlatformAdmin = is_platform_admin($user);
?>
<body data-tenant-accent="<?= e($tenantPrimaryColor) ?>">
  <main class="app-shell">
    <aside class="sidebar">
      <div class="brand-lockup brand-lockup--sidebar">
        <div class="brand-icon">M</div>
        <div>
          <h1>Membora CRM</h1>
          <p><?= e($user['tenant_name'] ?? 'NexoFit Studio') ?></p>
        </div>
      </div>

      <?php $route = $_GET['route'] ?? 'dashboard'; ?>
      <nav class="sidebar-nav">
        <?php if ($isPlatformAdmin): ?>
          <a class="<?= $route === 'platform-dashboard' ? 'active' : '' ?>" href="index.php?route=platform-dashboard">Resumen</a>
          <a class="<?= $route === 'platform-leads' ? 'active' : '' ?>" href="index.php?route=platform-leads">Leads</a>
          <a class="<?= $route === 'platform-clients' ? 'active' : '' ?>" href="index.php?route=platform-clients">Clientes</a>
          <a class="<?= $route === 'platform-companies' ? 'active' : '' ?>" href="index.php?route=platform-companies">Empresas</a>
          <a class="<?= $route === 'platform-payments' ? 'active' : '' ?>" href="index.php?route=platform-payments">Pagos</a>
          <a class="<?= $route === 'platform-plans' ? 'active' : '' ?>" href="index.php?route=platform-plans">Planes</a>
          <a class="<?= $route === 'platform-web' ? 'active' : '' ?>" href="index.php?route=platform-web">Web</a>
        <?php else: ?>
          <a class="<?= $route === 'dashboard' ? 'active' : '' ?>" href="index.php?route=dashboard">Panel</a>
          <a class="<?= $route === 'leads' ? 'active' : '' ?>" href="index.php?route=leads">Leads</a>
          <a class="<?= $route === 'users' ? 'active' : '' ?>" href="index.php?route=users">Usuarios</a>
          <a class="<?= $route === 'members' ? 'active' : '' ?>" href="index.php?route=members">Socios</a>
          <a class="<?= $route === 'memberships' ? 'active' : '' ?>" href="index.php?route=memberships">Membresias</a>
          <a class="<?= $route === 'payments' ? 'active' : '' ?>" href="index.php?route=payments">Pagos</a>
          <a class="<?= $route === 'checkins' ? 'active' : '' ?>" href="index.php?route=checkins">Check-ins</a>
          <a class="<?= $route === 'classes' ? 'active' : '' ?>" href="index.php?route=classes">Clases</a>
          <a class="<?= $route === 'tasks' ? 'active' : '' ?>" href="index.php?route=tasks">Tareas</a>
          <a class="<?= $route === 'alerts' ? 'active' : '' ?>" href="index.php?route=alerts">Alertas</a>
          <a class="<?= $route === 'audit' ? 'active' : '' ?>" href="index.php?route=audit">Auditoria</a>
        <?php endif; ?>
      </nav>

      <form method="post">
        <input type="hidden" name="action" value="logout">
        <button class="logout-button" type="submit">Cerrar sesion</button>
      </form>
    </aside>

    <section class="workspace">
      <header class="topbar">
        <form class="search-box global-search-box" method="get" action="index.php" data-global-search-form>
          <input name="q" value="" placeholder="<?= $isPlatformAdmin ? 'Buscar empresas, pagos o planes...' : 'Buscar tareas, socios, leads, clases o membresias...' ?>" autocomplete="off" data-global-search-input>
          <button class="global-search-submit" type="submit" aria-label="Buscar">Buscar</button>
          <div class="global-search-dropdown" data-global-search-results hidden></div>
        </form>
        <div class="user-menu" data-user-menu>
          <button class="user-chip user-chip--button" type="button" data-user-menu-trigger aria-haspopup="menu" aria-expanded="false">
            <?php if (!empty($user['avatar_path'])): ?>
              <img class="user-chip-avatar" src="<?= e($user['avatar_path']) ?>" alt="Foto de <?= e($user['name']) ?>">
            <?php else: ?>
              <span><?= e(substr($user['name'], 0, 1)) ?></span>
            <?php endif; ?>
            <div>
              <strong><?= e($user['name']) ?></strong>
              <small><?= e(role_label($user['role'])) ?></small>
            </div>
          </button>
          <div class="user-menu-dropdown" data-user-menu-dropdown hidden role="menu">
            <a href="index.php?route=profile" role="menuitem">
              <strong>Ver perfil</strong>
              <small>Foto, datos y contrasena</small>
            </a>
            <a href="index.php?route=settings" role="menuitem">
              <strong>Configuracion</strong>
              <small>Apariencia y comodidad</small>
            </a>
            <?php if ($isPlatformAdmin): ?>
              <a href="index.php?route=platform-dashboard" role="menuitem">
                <strong>Admin CRM</strong>
                <small>Empresas, planes y pagos</small>
              </a>
            <?php endif; ?>
            <form method="post" role="none">
              <input type="hidden" name="action" value="logout">
              <button class="danger-menu-action" type="submit" role="menuitem">
                <strong>Cerrar sesion</strong>
                <small>Salir de Membora CRM</small>
              </button>
            </form>
          </div>
        </div>
      </header>

      <div class="content">
        <?php if ($flash): ?>
          <div class="notice <?= $flash['type'] === 'error' ? 'notice-error' : 'notice-success' ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?php if (is_platform_support_context()): ?>
          <div class="support-context-banner" role="status">
            <div>
              <strong>Modo soporte</strong>
              <span>Estas viendo el CRM de <?= e($user['support_company_name'] ?? $user['tenant_name']) ?>.</span>
            </div>
            <form method="post">
              <input type="hidden" name="action" value="exit_empresa_crm">
              <button class="secondary-action" type="submit">Volver a Admin CRM</button>
            </form>
          </div>
        <?php endif; ?>
        <?php require __DIR__ . '/' . $contentView . '.php'; ?>
      </div>
    </section>
  </main>
  <dialog id="confirm-dialog" class="confirm-dialog">
    <form method="dialog">
      <header>
        <span class="confirm-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 2 1.8 20h20.4L12 2Zm1 15h-2v-2h2v2Zm0-4h-2V8h2v5Z"/></svg>
        </span>
        <div>
          <h2>Confirmar accion</h2>
          <p data-confirm-text>Esta accion no se puede deshacer.</p>
        </div>
      </header>
      <div class="confirm-actions">
        <button class="secondary-action" value="cancel" type="button" data-confirm-cancel>Cancelar</button>
        <button class="danger-confirm-action" value="confirm" type="button" data-confirm-accept>Eliminar</button>
      </div>
    </form>
  </dialog>
  <script src="assets/app.js?v=<?= e($jsVersion) ?>"></script>
</body>
</html>
