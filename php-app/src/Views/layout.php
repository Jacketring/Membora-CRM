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
<body>
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
        <a class="<?= $route === 'dashboard' ? 'active' : '' ?>" href="index.php?route=dashboard">Panel</a>
        <a class="<?= $route === 'leads' ? 'active' : '' ?>" href="index.php?route=leads">Leads</a>
        <a class="<?= $route === 'members' ? 'active' : '' ?>" href="index.php?route=members">Socios</a>
        <a class="<?= $route === 'memberships' ? 'active' : '' ?>" href="index.php?route=memberships">Membresias</a>
        <a class="<?= $route === 'classes' ? 'active' : '' ?>" href="index.php?route=classes">Clases</a>
        <a class="<?= $route === 'tasks' ? 'active' : '' ?>" href="index.php?route=tasks">Tareas</a>
      </nav>

      <form method="post">
        <input type="hidden" name="action" value="logout">
        <button class="logout-button" type="submit">Cerrar sesion</button>
      </form>
    </aside>

    <section class="workspace">
      <header class="topbar">
        <form class="search-box global-search-box" method="get" action="index.php" data-global-search-form>
          <input name="q" value="" placeholder="Buscar tareas, socios, leads, clases o membresias..." autocomplete="off" data-global-search-input>
          <button class="global-search-submit" type="submit" aria-label="Buscar">Buscar</button>
          <div class="global-search-dropdown" data-global-search-results hidden></div>
        </form>
        <div class="user-chip">
          <span><?= e(substr($user['name'], 0, 1)) ?></span>
          <div>
            <strong><?= e($user['name']) ?></strong>
            <small><?= e($user['role']) ?></small>
          </div>
        </div>
      </header>

      <div class="content">
        <?php if ($flash): ?>
          <div class="notice <?= $flash['type'] === 'error' ? 'notice-error' : 'notice-success' ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($flash['message']) ?></div>
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
