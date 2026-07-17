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
  <meta name="theme-color" content="#004bf2">
  <title><?= e($title) ?> - Membora CRM</title>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <link rel="stylesheet" href="assets/app.css?v=<?= e($cssVersion) ?>">
</head>
<?php
$tenantPrimaryColor = hex_color_or_default($user['tenant_primary_color'] ?? '#004bf2');
$isPlatformAdmin = is_platform_admin($user);
$route = $_GET['route'] ?? 'dashboard';
$demoRemainingSeconds = Auth::demoRemainingSeconds();
$subscriptionAccessState = (!$isPlatformAdmin && !is_platform_support_context())
    ? EmpresaRepository::accessStateForTenant((string) ($user['tenant_id'] ?? ''))
    : null;
$subscriptionBlocked = !empty($subscriptionAccessState['blocked']) && $route !== 'upgrade-plan';
$publicPlans = $subscriptionBlocked ? PlatformPlanRepository::publicPlans() : [];
?>
<body data-tenant-accent="<?= e($tenantPrimaryColor) ?>" <?= $demoRemainingSeconds > 0 ? 'data-demo-expires-in="' . e((string) $demoRemainingSeconds) . '"' : '' ?> <?= $subscriptionBlocked ? 'data-subscription-blocked="true"' : '' ?>>
  <main class="app-shell">
    <aside class="sidebar">
      <div class="brand-lockup brand-lockup--sidebar">
        <img class="brand-logo brand-logo--sidebar" src="assets/membora-logo.svg" alt="Membora CRM">
        <div>
          <h1>Membora CRM</h1>
          <p><?= e($user['tenant_name'] ?? 'NexoFit Studio') ?></p>
        </div>
      </div>

      <nav class="sidebar-nav">
        <?php if ($isPlatformAdmin): ?>
          <a class="<?= $route === 'platform-dashboard' ? 'active' : '' ?>" href="index.php?route=platform-dashboard">Resumen</a>
          <a class="<?= in_array($route, ['platform-contacts', 'platform-leads', 'platform-clients'], true) ? 'active' : '' ?>" href="index.php?route=platform-contacts">Contactos</a>
          <a class="<?= $route === 'platform-companies' ? 'active' : '' ?>" href="index.php?route=platform-companies">Empresas</a>
          <a class="<?= $route === 'platform-users' ? 'active' : '' ?>" href="index.php?route=platform-users">Usuarios admin</a>
          <details class="sidebar-nav-group" <?= in_array($route, ['platform-payments', 'platform-invoices', 'platform-invoice'], true) ? 'open' : '' ?>>
            <summary class="<?= in_array($route, ['platform-payments', 'platform-invoices', 'platform-invoice'], true) ? 'active' : '' ?>">Facturación</summary>
            <div class="sidebar-nav-submenu">
              <a class="<?= $route === 'platform-payments' ? 'active' : '' ?>" href="index.php?route=platform-payments">Pagos</a>
              <a class="<?= in_array($route, ['platform-invoices', 'platform-invoice'], true) ? 'active' : '' ?>" href="index.php?route=platform-invoices">Facturas</a>
            </div>
          </details>
          <a class="<?= $route === 'platform-plans' ? 'active' : '' ?>" href="index.php?route=platform-plans">Planes</a>
        <?php else: ?>
          <?php foreach ([
            'dashboard' => 'Panel',
            'leads' => 'Leads',
            'users' => 'Usuarios',
            'members' => 'Socios',
            'memberships' => 'Membresías',
            'checkins' => 'Check-ins',
            'classes' => 'Clases',
            'tasks' => 'Tareas',
            'alerts' => 'Alertas',
            'audit' => 'Auditoría',
          ] as $navRoute => $navLabel): ?>
            <?php if ($navRoute === 'checkins' && (can_access_route('payments', $user) || can_access_route('billing', $user))): ?>
              <?php $clientBillingRoutes = ['payments', 'payment-invoice', 'billing', 'client-invoice']; ?>
              <details class="sidebar-nav-group" <?= in_array($route, $clientBillingRoutes, true) ? 'open' : '' ?>>
                <summary class="<?= in_array($route, $clientBillingRoutes, true) ? 'active' : '' ?>">Facturación</summary>
                <div class="sidebar-nav-submenu">
                  <?php if (can_access_route('payments', $user)): ?>
                    <a class="<?= in_array($route, ['payments', 'payment-invoice'], true) ? 'active' : '' ?>" href="index.php?route=payments">Pagos</a>
                  <?php endif; ?>
                  <?php if (can_access_route('billing', $user)): ?>
                    <a class="<?= in_array($route, ['billing', 'client-invoice'], true) ? 'active' : '' ?>" href="index.php?route=billing">Facturas</a>
                  <?php endif; ?>
                </div>
              </details>
            <?php endif; ?>
            <?php if (can_access_route($navRoute, $user)): ?>
              <a class="<?= $route === $navRoute ? 'active' : '' ?>" href="index.php?route=<?= e($navRoute) ?>"><?= e($navLabel) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </nav>

      <form method="post">
        <input type="hidden" name="action" value="logout">
        <button class="logout-button" type="submit">Cerrar sesión</button>
      </form>
    </aside>

    <section class="workspace">
      <?php if (!empty($subscriptionAccessState['is_trial']) && !is_platform_support_context()): ?>
        <div class="trial-upgrade-banner" role="region" aria-label="Estado de la prueba gratuita">
          <div>
            <?php $trialRemainingDays = (int) ($subscriptionAccessState['remaining_days'] ?? 0); ?>
            <strong><?= $trialRemainingDays > 0 ? ($trialRemainingDays === 1 ? 'Te queda 1 día de prueba' : 'Te quedan ' . $trialRemainingDays . ' días de prueba') : 'Tu prueba gratuita ha finalizado' ?></strong>
            <span><?= !empty($subscriptionAccessState['expires_at']) ? 'Acceso de prueba hasta el ' . e(format_date_short($subscriptionAccessState['expires_at'])) . '.' : 'Elige un plan para mantener el acceso.' ?></span>
          </div>
          <a class="trial-upgrade-action" href="index.php?route=upgrade-plan">Mejorar el plan</a>
        </div>
      <?php endif; ?>
      <header class="topbar">
        <form class="search-box global-search-box" method="get" action="index.php" data-global-search-form>
          <input name="q" value="" placeholder="<?= $isPlatformAdmin ? 'Buscar empresas, pagos o planes...' : 'Buscar tareas, socios, leads, clases o membresías...' ?>" autocomplete="off" data-global-search-input>
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
              <small>Foto, datos y contraseña</small>
            </a>
            <a href="index.php?route=settings" role="menuitem">
              <strong>Configuración</strong>
              <small>Apariencia y comodidad</small>
            </a>
            <a href="index.php?route=novedades" role="menuitem">
              <strong>Novedades</strong>
              <small>Versiones y cambios del CRM</small>
            </a>
            <?php if ($isPlatformAdmin): ?>
              <a href="index.php?route=platform-dashboard" role="menuitem">
                <strong>Admin CRM</strong>
                <small>Empresas, planes y pagos</small>
              </a>
              <a href="index.php?route=platform-audit" role="menuitem">
                <strong>Logs CRM</strong>
                <small>Actividad de empresas cliente</small>
              </a>
            <?php endif; ?>
            <form method="post" role="none">
              <input type="hidden" name="action" value="logout">
              <button class="danger-menu-action" type="submit" role="menuitem">
                <strong>Cerrar sesión</strong>
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
        <?php if ($demoRemainingSeconds > 0): ?>
          <div class="demo-session-banner" role="region" aria-label="Sesión demo temporal">
            <div>
              <strong>Demo temporal</strong>
              <span>Esta sesión de prueba se cerrará automáticamente.</span>
            </div>
            <div class="demo-session-actions">
              <time data-demo-countdown datetime="PT<?= (int) $demoRemainingSeconds ?>S">20:00</time>
              <a class="demo-exit-action" href="index.php?route=demo-expired">Salir de la demo</a>
            </div>
          </div>
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
  <?php if ($subscriptionBlocked): ?>
    <div class="subscription-blocker" role="dialog" aria-modal="true" aria-labelledby="subscription-blocker-title">
      <section class="subscription-blocker-card">
        <header>
          <span class="confirm-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 2 1.8 20h20.4L12 2Zm1 15h-2v-2h2v2Zm0-4h-2V8h2v5Z"/></svg>
          </span>
          <div>
            <h2 id="subscription-blocker-title"><?= e($subscriptionAccessState['title'] ?? 'Suscripción requerida') ?></h2>
            <p><?= e($subscriptionAccessState['message'] ?? 'Elige un plan para continuar usando Membora.') ?></p>
          </div>
        </header>
        <div class="subscription-plan-grid">
          <?php foreach (array_slice($publicPlans, 0, 4) as $plan): ?>
            <article>
              <h3><?= e($plan['name']) ?></h3>
              <strong><?= e(money_amount($plan['monthly_price'])) ?><small>/mes</small></strong>
              <?php if (!empty($plan['features'][0])): ?>
                <p><?= e($plan['features'][0]) ?></p>
              <?php endif; ?>
              <a class="primary-action" href="mailto:contacto@josehurtado.dev?subject=Contratar%20<?= rawurlencode((string) $plan['name']) ?>%20Membora">Elegir plan</a>
            </article>
          <?php endforeach; ?>
        </div>
        <p class="subscription-blocker-note">Para contratar se solicitaran los datos fiscales, direccion, teléfono, plan y forma de pago.</p>
      </section>
    </div>
  <?php endif; ?>
  <?php if ($demoRemainingSeconds > 0): ?>
    <form method="post" hidden data-demo-cleanup-form>
      <input type="hidden" name="action" value="schedule_demo_cleanup">
    </form>
    <form method="post" hidden data-demo-keepalive-form>
      <input type="hidden" name="action" value="keep_demo_session">
    </form>
  <?php endif; ?>
  <dialog id="confirm-dialog" class="confirm-dialog">
    <form method="dialog">
      <header>
        <span class="confirm-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 2 1.8 20h20.4L12 2Zm1 15h-2v-2h2v2Zm0-4h-2V8h2v5Z"/></svg>
        </span>
        <div>
          <h2>Confirmar acción</h2>
          <p data-confirm-text>Esta acción no se puede deshacer.</p>
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
