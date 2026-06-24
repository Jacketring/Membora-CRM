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
        <a class="<?= $route === 'users' ? 'active' : '' ?>" href="index.php?route=users">Usuarios</a>
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
        <div class="user-menu" data-user-menu>
          <button class="user-chip user-chip--button" type="button" data-user-menu-trigger aria-haspopup="menu" aria-expanded="false">
            <span><?= e(substr($user['name'], 0, 1)) ?></span>
            <div>
              <strong><?= e($user['name']) ?></strong>
              <small><?= e(role_label($user['role'])) ?></small>
            </div>
          </button>
          <div class="user-menu-dropdown" data-user-menu-dropdown hidden role="menu">
            <button type="button" data-open-modal="profile-modal" role="menuitem">
              <strong>Ver perfil</strong>
              <small>Editar nombre, email y contrasena</small>
            </button>
            <button type="button" data-open-modal="settings-modal" role="menuitem">
              <strong>Configuracion</strong>
              <small>Modo oscuro, color y comodidad</small>
            </button>
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
  <dialog id="profile-modal" class="modal-card" aria-labelledby="profile-modal-title">
    <form method="post" action="index.php?return=<?= e($route) ?>">
      <input type="hidden" name="action" value="update_profile">
      <header>
        <div>
          <h2 id="profile-modal-title">Mi perfil</h2>
          <p>Personaliza tus datos de acceso al CRM.</p>
        </div>
        <button data-close-modal type="button">Cerrar</button>
      </header>
      <div class="form-grid">
        <label class="field">
          <span>Nombre</span>
          <input name="name" required value="<?= e($user['name']) ?>" autocomplete="name">
        </label>
        <label class="field">
          <span>Email</span>
          <input name="email" required type="email" value="<?= e($user['email']) ?>" autocomplete="email">
        </label>
        <label class="field field--wide">
          <span>Nueva contrasena</span>
          <input name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Dejalo vacio para mantener la actual">
        </label>
      </div>
      <button class="primary-action" type="submit">Guardar perfil</button>
    </form>
  </dialog>

  <dialog id="settings-modal" class="modal-card" aria-labelledby="settings-modal-title">
    <form method="dialog" data-crm-settings-form>
      <header>
        <div>
          <h2 id="settings-modal-title">Configuracion</h2>
          <p>Ajustes visuales guardados solo para tu navegador.</p>
        </div>
        <button data-close-modal type="button">Cerrar</button>
      </header>
      <div class="settings-grid">
        <fieldset class="settings-panel">
          <legend>Modo</legend>
          <label><input type="radio" name="theme" value="system" data-setting-theme> Sistema</label>
          <label><input type="radio" name="theme" value="light" data-setting-theme> Claro</label>
          <label><input type="radio" name="theme" value="dark" data-setting-theme> Oscuro</label>
        </fieldset>
        <label class="settings-panel">
          <span>Color principal</span>
          <input class="color-setting" type="color" name="accent" value="#0754d6" data-setting-accent>
        </label>
        <label class="settings-panel settings-toggle">
          <span>Interfaz compacta</span>
          <input type="checkbox" name="compact" value="1" data-setting-compact>
        </label>
      </div>
      <div class="settings-actions">
        <button class="secondary-action" type="button" data-settings-reset>Restablecer</button>
        <button class="primary-action primary-action--compact" value="default" type="submit">Guardar</button>
      </div>
    </form>
  </dialog>
  <script src="assets/app.js?v=<?= e($jsVersion) ?>"></script>
</body>
</html>
