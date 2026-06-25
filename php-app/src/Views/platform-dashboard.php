<?php
$filters = $filters ?? ['q' => '', 'status' => '', 'payment_status' => ''];
$empresas = $empresas ?? [];
$allEmpresas = $allEmpresas ?? $empresas;
$metrics = $metrics ?? ['active' => 0, 'trial' => 0, 'payments_pending' => 0, 'mrr' => 0];
$statusOptions = [
    '' => 'Todos',
    'ACTIVE' => 'Activo',
    'TRIAL' => 'Prueba',
    'SUSPENDED' => 'Suspendido',
    'CANCELLED' => 'Cancelado',
];
$paymentOptions = [
    '' => 'Todos',
    'PAID' => 'Al dia',
    'PENDING' => 'Pendiente',
    'OVERDUE' => 'Vencido',
    'TRIAL' => 'Prueba',
];
$planOptions = PlatformPlanRepository::options();
$payments = $payments ?? [];
$paymentMetrics = $paymentMetrics ?? ['paid_month' => 0, 'pending_amount' => 0, 'overdue' => 0, 'due_week' => 0];
$planMetrics = $planMetrics ?? ['active' => 0, 'average_price' => 0, 'enterprise' => 0];
$today = new DateTimeImmutable('today');
$nextWeek = $today->modify('+7 days');
$activeCustomers = array_values(array_filter($allEmpresas, static fn (array $empresa): bool => in_array($empresa['status'], ['ACTIVE', 'TRIAL'], true)));
$cancelledCustomers = array_values(array_filter($allEmpresas, static fn (array $empresa): bool => in_array($empresa['status'], ['SUSPENDED', 'CANCELLED'], true)));
$riskCompanies = array_values(array_filter($allEmpresas, static fn (array $empresa): bool => in_array($empresa['payment_status'], ['PENDING', 'OVERDUE'], true) || in_array($empresa['status'], ['SUSPENDED', 'CANCELLED'], true)));
$billingSoon = array_values(array_filter($allEmpresas, static function (array $empresa) use ($today, $nextWeek): bool {
    if (empty($empresa['next_payment_at'])) {
        return false;
    }

    $timestamp = strtotime((string) $empresa['next_payment_at']);
    if (!$timestamp) {
        return false;
    }

    $date = new DateTimeImmutable(date('Y-m-d', $timestamp));
    return $date >= $today && $date <= $nextWeek;
}));
$overdueAmount = array_reduce($allEmpresas, static function (float $carry, array $empresa): float {
    return $carry + ($empresa['payment_status'] === 'OVERDUE' ? (float) $empresa['monthly_price'] : 0);
}, 0.0);
$planCounts = [];
foreach ($allEmpresas as $empresa) {
    $plan = strtoupper((string) ($empresa['plan'] ?? 'BASIC'));
    $planCounts[$plan] = ($planCounts[$plan] ?? 0) + 1;
}
$totalCompanies = max(1, count($allEmpresas));
$arr = (float) $metrics['mrr'] * 12;
$arpa = count($activeCustomers) > 0 ? (float) $metrics['mrr'] / count($activeCustomers) : 0;
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Administracion Membora CRM</h2>
    <p>Resumen ejecutivo de empresas cliente, cobros, planes y soporte.</p>
  </div>
  <div class="platform-heading-actions">
    <a class="secondary-action" href="index.php?route=platform-payments">Ver pagos</a>
    <a class="primary-action" href="index.php?route=platform-companies">Gestionar empresas</a>
  </div>
</div>

<section class="dashboard-metrics">
  <article class="dashboard-metric dashboard-metric--primary">
    <span>Empresas activas</span>
    <strong><?= (int) $metrics['active'] ?></strong>
    <small>Clientes con CRM operativo</small>
  </article>
  <article class="dashboard-metric dashboard-metric--green">
    <span>En prueba</span>
    <strong><?= (int) $metrics['trial'] ?></strong>
    <small>Seguimiento comercial</small>
  </article>
  <article class="dashboard-metric dashboard-metric--orange">
    <span>Pagos pendientes</span>
    <strong><?= e(money_amount($paymentMetrics['pending_amount'])) ?></strong>
    <small><?= (int) $paymentMetrics['overdue'] ?> vencidos</small>
  </article>
  <article class="dashboard-metric dashboard-metric--danger">
    <span>MRR estimado</span>
    <strong><?= e(money_amount($metrics['mrr'])) ?></strong>
    <small>Ingresos recurrentes mensuales</small>
  </article>
</section>

<section class="platform-module-grid" aria-label="Secciones de administracion">
  <a class="platform-module-card" href="index.php?route=platform-companies">
    <span>Empresas</span>
    <strong>Clientes CRM</strong>
    <small>Alta, estado, soporte y acceso al CRM de cada empresa.</small>
  </a>
  <a class="platform-module-card" href="index.php?route=platform-payments">
    <span>Pagos</span>
    <strong>Facturacion SaaS</strong>
    <small>Control de cobros, vencidos, pagados y proximos pagos.</small>
  </a>
  <a class="platform-module-card" href="index.php?route=platform-plans">
    <span>Planes</span>
    <strong>Packaging</strong>
    <small>Precios, limites, configuracion comercial y planes activos.</small>
  </a>
</section>

<section class="platform-ops-grid" aria-label="Resumen operativo de administracion">
  <article class="platform-insight-card platform-insight-card--revenue">
    <span>ARR estimado</span>
    <strong><?= e(money_amount($arr)) ?></strong>
    <small>MRR x 12 meses</small>
  </article>
  <article class="platform-insight-card">
    <span>ARPA</span>
    <strong><?= e(money_amount($arpa)) ?></strong>
    <small>Ingreso medio por empresa activa</small>
  </article>
  <article class="platform-insight-card platform-insight-card--risk">
    <span>Riesgo / churn</span>
    <strong><?= count($cancelledCustomers) ?></strong>
    <small>Suspendidas o canceladas</small>
  </article>
  <article class="platform-insight-card platform-insight-card--warning">
    <span>Cobrado este mes</span>
    <strong><?= e(money_amount($paymentMetrics['paid_month'])) ?></strong>
    <small><?= (int) $paymentMetrics['due_week'] ?> cobros próximos</small>
  </article>
</section>

<section class="platform-admin-grid">
  <article class="platform-panel">
    <header>
      <div>
        <h3>Prioridades de soporte</h3>
        <p>Empresas que requieren revision por pago o estado del CRM.</p>
      </div>
      <span><?= count($riskCompanies) ?></span>
    </header>
    <div class="platform-list">
      <?php foreach (array_slice($riskCompanies, 0, 5) as $empresa): ?>
        <div class="platform-list-item">
          <div>
            <strong><?= e($empresa['name']) ?></strong>
            <small><?= e(empresa_status_label($empresa['status'])) ?> · <?= e(empresa_payment_status_label($empresa['payment_status'])) ?></small>
          </div>
          <b><?= e(money_amount($empresa['monthly_price'])) ?></b>
        </div>
      <?php endforeach; ?>
      <?php if (!$riskCompanies): ?>
        <p class="platform-empty">No hay empresas en riesgo ahora mismo.</p>
      <?php endif; ?>
    </div>
  </article>

  <article class="platform-panel">
    <header>
      <div>
        <h3>Proximos cobros</h3>
        <p>Cobros previstos durante los proximos 7 dias.</p>
      </div>
      <span><?= count($billingSoon) ?></span>
    </header>
    <div class="platform-list">
      <?php foreach (array_slice($billingSoon, 0, 5) as $empresa): ?>
        <div class="platform-list-item">
          <div>
            <strong><?= e($empresa['name']) ?></strong>
            <small><?= e(format_date_short($empresa['next_payment_at'])) ?></small>
          </div>
          <b><?= e(money_amount($empresa['monthly_price'])) ?></b>
        </div>
      <?php endforeach; ?>
      <?php if (!$billingSoon): ?>
        <p class="platform-empty">No hay cobros previstos esta semana.</p>
      <?php endif; ?>
    </div>
  </article>

  <article class="platform-panel">
    <header>
      <div>
        <h3>Distribucion por plan</h3>
        <p>Vista rapida de packaging y cartera.</p>
      </div>
      <span><?= count($allEmpresas) ?></span>
    </header>
    <div class="platform-plan-list">
      <?php foreach ($planOptions as $plan => $label): ?>
        <?php $count = $planCounts[$plan] ?? 0; $percentage = (int) round(($count / $totalCompanies) * 100); ?>
        <div>
          <span><?= e($label) ?></span>
          <strong><?= $count ?></strong>
          <i style="--plan-width: <?= $percentage ?>%"></i>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>

<section class="platform-dashboard-grid">
  <article class="leads-table-card">
    <header>
      <div>
        <h3>Ultimas empresas</h3>
        <span><?= count($empresas) ?> visibles</span>
      </div>
      <a class="secondary-action" href="index.php?route=platform-companies">Abrir empresas</a>
    </header>
    <div class="platform-list">
      <?php foreach ($empresas as $empresa): ?>
        <a class="platform-list-item platform-list-link" href="index.php?route=platform-companies&q=<?= urlencode($empresa['name']) ?>">
          <div>
            <strong><?= e($empresa['name']) ?></strong>
            <small><?= e(empresa_status_label($empresa['status'])) ?> · <?= e($planOptions[$empresa['plan']] ?? $empresa['plan']) ?></small>
          </div>
          <b><?= e(money_amount($empresa['monthly_price'])) ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="leads-table-card">
    <header>
      <div>
        <h3>Cobros recientes</h3>
        <span><?= count($payments) ?> visibles</span>
      </div>
      <a class="secondary-action" href="index.php?route=platform-payments">Abrir pagos</a>
    </header>
    <div class="platform-list">
      <?php foreach ($payments as $payment): ?>
        <a class="platform-list-item platform-list-link" href="index.php?route=platform-payments&q=<?= urlencode($payment['concept']) ?>">
          <div>
            <strong><?= e($payment['concept']) ?></strong>
            <small><?= e($payment['empresa_name']) ?> · <?= e(platform_payment_status_label($payment['status'])) ?></small>
          </div>
          <b><?= e(money_amount($payment['amount'])) ?></b>
        </a>
      <?php endforeach; ?>
      <?php if (!$payments): ?>
        <p class="platform-empty">Todavia no hay pagos registrados.</p>
      <?php endif; ?>
    </div>
  </article>
</section>
