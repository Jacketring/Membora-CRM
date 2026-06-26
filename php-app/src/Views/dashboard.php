<?php
$totalLeads = max(1, (int) $summary['totalLeads']);
$conversionRate = (int) round(((int) $summary['convertedLeads'] / $totalLeads) * 100);
$openLeadRate = (int) round(((int) $summary['openLeads'] / $totalLeads) * 100);
$lostLeadRate = (int) round(((int) $summary['lostLeads'] / $totalLeads) * 100);
$taskTotal = max(1, (int) $summary['pendingTasks'] + (int) $summary['completedTasks']);
$taskCompletionRate = (int) round(((int) $summary['completedTasks'] / $taskTotal) * 100);
?>

<section class="dashboard-hero">
  <div>
    <span class="eyebrow">Panel operativo</span>
    <h2><?= e($user['tenant_name'] ?? 'Membora CRM') ?></h2>
    <p>Vista rapida de ventas, tareas y riesgos para decidir que atender primero.</p>
  </div>
  <div class="dashboard-hero-actions">
    <a class="primary-action primary-action--compact" href="index.php?route=leads">Nuevo seguimiento</a>
    <a class="secondary-link" href="index.php?route=tasks">Ver tareas</a>
  </div>
</section>

<section class="dashboard-metrics">
  <article class="dashboard-metric dashboard-metric--primary">
    <span>Socios activos</span>
    <strong><?= (int) $summary['activeMembers'] ?></strong>
    <small><?= (int) $summary['totalMembers'] ?> socios registrados</small>
  </article>
  <article class="dashboard-metric dashboard-metric--green">
    <span>Conversion de leads</span>
    <strong><?= $conversionRate ?>%</strong>
    <small><?= (int) $summary['convertedLeads'] ?> convertidos de <?= (int) $summary['totalLeads'] ?></small>
  </article>
  <article class="dashboard-metric dashboard-metric--orange">
    <span>Tareas pendientes</span>
    <strong><?= (int) $summary['pendingTasks'] ?></strong>
    <small><?= (int) $summary['todayTasks'] ?> para hoy</small>
  </article>
  <article class="dashboard-metric dashboard-metric--danger">
    <span>Atencion requerida</span>
    <strong><?= (int) $summary['overdueTasks'] + (int) $summary['openAlerts'] + (int) $summary['pendingPayments'] ?></strong>
    <small>Vencidas, alertas o pagos pendientes</small>
  </article>
</section>

<section class="dashboard-layout">
  <article class="dashboard-card dashboard-card--wide">
    <header class="dashboard-card-header">
      <div>
        <h3>Embudo comercial</h3>
        <p>Estado actual de los leads del centro.</p>
      </div>
      <a href="index.php?route=leads">Abrir leads</a>
    </header>
    <div class="funnel-stack">
      <div class="funnel-row">
        <div class="funnel-row-head">
          <div>
            <strong>Abiertos</strong>
            <span><?= (int) $summary['openLeads'] ?> leads</span>
          </div>
          <b><?= $openLeadRate ?>%</b>
        </div>
        <div class="progress-track"><span style="width: <?= $openLeadRate ?>%"></span></div>
      </div>
      <div class="funnel-row">
        <div class="funnel-row-head">
          <div>
            <strong>Convertidos</strong>
            <span><?= (int) $summary['convertedLeads'] ?> leads</span>
          </div>
          <b><?= $conversionRate ?>%</b>
        </div>
        <div class="progress-track progress-track--green"><span style="width: <?= $conversionRate ?>%"></span></div>
      </div>
      <div class="funnel-row">
        <div class="funnel-row-head">
          <div>
            <strong>Perdidos</strong>
            <span><?= (int) $summary['lostLeads'] ?> leads</span>
          </div>
          <b><?= $lostLeadRate ?>%</b>
        </div>
        <div class="progress-track progress-track--red"><span style="width: <?= $lostLeadRate ?>%"></span></div>
      </div>
    </div>
  </article>

  <article class="dashboard-card">
    <header class="dashboard-card-header">
      <div>
        <h3>Trabajo del dia</h3>
        <p>Seguimientos y tareas activas.</p>
      </div>
    </header>
    <div class="task-health">
      <div class="task-ring" style="--value: <?= $taskCompletionRate ?>%;">
        <strong><?= $taskCompletionRate ?>%</strong>
        <span>completado</span>
      </div>
      <div class="task-health-list">
        <span><b><?= (int) $summary['pendingTasks'] ?></b> pendientes</span>
        <span><b><?= (int) $summary['todayTasks'] ?></b> para hoy</span>
        <span><b><?= (int) $summary['overdueTasks'] ?></b> vencidas</span>
      </div>
    </div>
  </article>

  <article class="dashboard-card">
    <header class="dashboard-card-header">
      <div>
        <h3>Riesgos</h3>
        <p>Elementos que pueden bloquear gestion.</p>
      </div>
    </header>
    <div class="risk-list">
      <div><span>Alertas abiertas</span><strong><?= (int) $summary['openAlerts'] ?></strong></div>
      <div><span>Pagos pendientes</span><strong><?= (int) $summary['pendingPayments'] ?></strong></div>
      <div><span>Tareas vencidas</span><strong><?= (int) $summary['overdueTasks'] ?></strong></div>
    </div>
  </article>

  <article class="dashboard-card">
    <header class="dashboard-card-header">
      <div>
        <h3>Leads recientes</h3>
        <p>Ultimas oportunidades recibidas.</p>
      </div>
    </header>
    <div class="activity-list">
      <?php foreach ($summary['recentLeads'] as $lead): ?>
        <a href="index.php?route=leads" class="activity-item">
          <span><?= e(substr($lead['first_name'], 0, 1)) ?></span>
          <div>
            <strong><?= e(trim($lead['first_name'] . ' ' . ($lead['last_name'] ?? ''))) ?></strong>
            <small><?= e($lead['stage_name']) ?></small>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$summary['recentLeads']): ?>
        <p class="empty-state">No hay leads recientes.</p>
      <?php endif; ?>
    </div>
  </article>

  <article class="dashboard-card">
    <header class="dashboard-card-header">
      <div>
        <h3>Proximas tareas</h3>
        <p>Lo siguiente que conviene resolver.</p>
      </div>
      <a href="index.php?route=tasks">Abrir tareas</a>
    </header>
    <div class="activity-list">
      <?php foreach ($summary['tasks'] as $task): ?>
        <a href="index.php?route=tasks" class="activity-item activity-item--task">
          <span><?= e(substr($task['title'], 0, 1)) ?></span>
          <div>
            <strong><?= e($task['title']) ?></strong>
            <small><?= e(format_date($task['due_at'])) ?></small>
          </div>
          <em class="status-badge status-badge--<?= e(strtolower($task['status'])) ?>"><?= e(status_label($task['status'])) ?></em>
        </a>
      <?php endforeach; ?>
      <?php if (!$summary['tasks']): ?>
        <p class="empty-state">No hay tareas proximas.</p>
      <?php endif; ?>
    </div>
  </article>
</section>
