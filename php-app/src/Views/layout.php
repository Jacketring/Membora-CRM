<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> - Membora CRM</title>
  <link rel="stylesheet" href="assets/app.css">
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
        <a href="#">Socios</a>
        <a href="#">Membresias</a>
        <a href="#">Clases</a>
        <a class="<?= $route === 'tasks' ? 'active' : '' ?>" href="index.php?route=tasks">Tareas</a>
      </nav>

      <form method="post">
        <input type="hidden" name="action" value="logout">
        <button class="logout-button" type="submit">Cerrar sesion</button>
      </form>
    </aside>

    <section class="workspace">
      <header class="topbar">
        <div class="search-box">
          <span>⌕</span>
          <input placeholder="Buscar socios, leads o tareas..." disabled>
        </div>
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
          <div class="notice <?= $flash['type'] === 'error' ? 'notice-error' : 'notice-success' ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?php require __DIR__ . '/' . $contentView . '.php'; ?>
      </div>
    </section>
  </main>
  <script src="assets/app.js"></script>
</body>
</html>
