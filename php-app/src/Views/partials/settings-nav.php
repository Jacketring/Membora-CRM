<nav class="settings-page-tabs" aria-label="Secciones de ajustes">
  <a class="<?= $route === 'profile' ? 'active' : '' ?>" href="index.php?route=profile">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z"/></svg>
    <span>Perfil</span>
  </a>
  <a class="<?= $route === 'settings' ? 'active' : '' ?>" href="index.php?route=settings">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.4 13.5c.1-.5.1-1 .1-1.5s0-1-.1-1.5l2-1.5-2-3.5-2.4 1a8 8 0 0 0-2.6-1.5L14 2h-4l-.4 2.5A8 8 0 0 0 7 6L4.6 5 2.6 8.5l2 1.5A9.2 9.2 0 0 0 4.5 12c0 .5 0 1 .1 1.5l-2 1.5 2 3.5L7 17.5A8 8 0 0 0 9.6 19l.4 3h4l.4-3a8 8 0 0 0 2.6-1.5l2.4 1 2-3.5-2-1.5ZM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7Z"/></svg>
    <span>Configuracion</span>
  </a>
</nav>
