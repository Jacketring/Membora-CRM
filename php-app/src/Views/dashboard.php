<div class="page-heading">
  <div>
    <h2>Panel de control</h2>
    <p>Resumen operativo de <?= e($user['tenant_name'] ?? 'tu centro') ?>.</p>
  </div>
</div>

<section class="lead-metrics">
  <article class="lead-metric lead-metric--blue"><span>Socios activos</span><strong><?= $summary['activeMembers'] ?></strong></article>
  <article class="lead-metric lead-metric--green"><span>Leads abiertos</span><strong><?= $summary['openLeads'] ?></strong></article>
  <article class="lead-metric lead-metric--orange"><span>Tareas pendientes</span><strong><?= $summary['pendingTasks'] ?></strong></article>
  <article class="lead-metric lead-metric--dark"><span>Alertas abiertas</span><strong><?= $summary['openAlerts'] ?></strong></article>
</section>

<section class="dashboard-grid">
  <article class="panel-card">
    <header><h3>Leads recientes</h3></header>
    <div class="compact-list">
      <?php foreach ($summary['recentLeads'] as $lead): ?>
        <div><strong><?= e($lead['first_name'] . ' ' . ($lead['last_name'] ?? '')) ?></strong><span><?= e($lead['stage_name']) ?></span></div>
      <?php endforeach; ?>
    </div>
  </article>
  <article class="panel-card">
    <header><h3>Proximas tareas</h3></header>
    <div class="compact-list">
      <?php foreach ($summary['tasks'] as $task): ?>
        <div><strong><?= e($task['title']) ?></strong><span><?= e(format_date($task['due_at'])) ?></span></div>
      <?php endforeach; ?>
    </div>
  </article>
</section>
