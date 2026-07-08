<?php
$empresas = $empresas ?? [];
$allEmpresas = $allEmpresas ?? $empresas;
$metrics = $metrics ?? ['active' => 0, 'trial' => 0, 'payments_pending' => 0, 'mrr' => 0];
$payments = $payments ?? [];
$paymentMetrics = $paymentMetrics ?? ['paid_month' => 0, 'pending_amount' => 0, 'overdue' => 0, 'due_week' => 0];
$planOptions = PlatformPlanRepository::options();

$today = new DateTimeImmutable('today');
$nextWeek = $today->modify('+7 days');
$activeCompanies = array_values(array_filter($allEmpresas, static fn (array $empresa): bool => in_array($empresa['status'], ['ACTIVE', 'TRIAL'], true)));
$riskCompanies = array_values(array_filter($allEmpresas, static function (array $empresa) use ($today, $nextWeek): bool {
    if (in_array($empresa['payment_status'], ['PENDING', 'OVERDUE'], true) || in_array($empresa['status'], ['SUSPENDED', 'CANCELLED'], true)) {
        return true;
    }

    if (empty($empresa['next_payment_at'])) {
        return false;
    }

    $timestamp = strtotime((string) $empresa['next_payment_at']);
    if (!$timestamp) {
        return false;
    }

    $date = new DateTimeImmutable(date('Y-m-d', $timestamp));
    return $date <= $nextWeek;
}));
usort($riskCompanies, static fn (array $a, array $b): int => strcmp((string) ($a['next_payment_at'] ?? '9999-12-31'), (string) ($b['next_payment_at'] ?? '9999-12-31')));

$upcomingPayments = array_values(array_filter($payments, static function (array $payment): bool {
    return in_array($payment['status'], ['PENDING', 'OVERDUE'], true);
}));
if (!$upcomingPayments) {
    $upcomingPayments = $payments;
}

$statusCounts = [];
$paymentCounts = [];
$planCounts = [];
foreach ($allEmpresas as $empresa) {
    $status = (string) ($empresa['status'] ?? 'ACTIVE');
    $paymentStatus = (string) ($empresa['payment_status'] ?? 'PENDING');
    $plan = strtoupper((string) ($empresa['plan'] ?? 'BASIC'));
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    $paymentCounts[$paymentStatus] = ($paymentCounts[$paymentStatus] ?? 0) + 1;
    $planCounts[$plan] = ($planCounts[$plan] ?? 0) + 1;
}

$companyTotal = max(1, count($allEmpresas));
$mrr = (float) ($metrics['mrr'] ?? 0);
$arr = $mrr * 12;
$arpa = count($activeCompanies) > 0 ? $mrr / count($activeCompanies) : 0;
$pendingAmount = (float) ($paymentMetrics['pending_amount'] ?? 0);
$paidMonth = (float) ($paymentMetrics['paid_month'] ?? 0);
$paidRate = count($allEmpresas) > 0 ? (int) round((($paymentCounts['PAID'] ?? 0) / count($allEmpresas)) * 100) : 0;
?>

<div class="page-heading leads-heading platform-heading">
  <div>
    <h2>Administracion Membora CRM</h2>
    <p>Panel ejecutivo para controlar cartera, cobros y empresas que necesitan seguimiento.</p>
  </div>
  <div class="platform-heading-actions">
    <a class="secondary-action" href="index.php?route=platform-payments">Ver pagos</a>
    <a class="primary-action" href="index.php?route=platform-companies">Gestionar empresas</a>
  </div>
</div>

<section class="admin-dashboard-hero" aria-label="Resumen ejecutivo">
  <article class="admin-dashboard-panel admin-dashboard-panel--main">
    <header class="admin-dashboard-panel-header">
      <div>
        <span>Salud financiera</span>
        <h3><?= e(money_amount($mrr)) ?> de MRR</h3>
        <p><?= e(money_amount($arr)) ?> ARR estimado con <?= count($activeCompanies) ?> empresas activas o en prueba.</p>
      </div>
      <a href="index.php?route=platform-payments" class="admin-dashboard-link">Cobros</a>
    </header>
    <div class="admin-dashboard-kpis">
      <div>
        <span>Cobrado este mes</span>
        <strong><?= e(money_amount($paidMonth)) ?></strong>
      </div>
      <div>
        <span>Pendiente</span>
        <strong><?= e(money_amount($pendingAmount)) ?></strong>
      </div>
      <div>
        <span>ARPA</span>
        <strong><?= e(money_amount($arpa)) ?></strong>
      </div>
      <div>
        <span>Vencidos</span>
        <strong><?= (int) ($paymentMetrics['overdue'] ?? 0) ?></strong>
      </div>
    </div>
  </article>

  <article class="admin-dashboard-panel">
    <header class="admin-dashboard-panel-header">
      <div>
        <span>Cartera</span>
        <h3><?= count($allEmpresas) ?> empresas</h3>
        <p><?= $paidRate ?>% al dia en pagos.</p>
      </div>
    </header>
    <div class="admin-dashboard-status">
      <?php foreach (['ACTIVE' => 'Activas', 'TRIAL' => 'En prueba', 'SUSPENDED' => 'Suspendidas', 'CANCELLED' => 'Canceladas'] as $status => $label): ?>
        <?php $count = $statusCounts[$status] ?? 0; $width = (int) round(($count / $companyTotal) * 100); ?>
        <div>
          <span><?= e($label) ?></span>
          <strong><?= $count ?></strong>
          <i style="--bar-width: <?= $width ?>%"></i>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>

<section class="admin-dashboard-actions" aria-label="Accesos de administracion">
  <a href="index.php?route=platform-contacts">
    <span>Contactos</span>
    <strong>Leads y clientes</strong>
  </a>
  <a href="index.php?route=platform-companies">
    <span>Empresas</span>
    <strong>Alta y soporte</strong>
  </a>
  <a href="index.php?route=platform-payments">
    <span>Pagos</span>
    <strong>Cobros y facturas</strong>
  </a>
  <a href="index.php?route=platform-plans">
    <span>Planes</span>
    <strong>Packaging</strong>
  </a>
</section>

<section class="admin-dashboard-tables" aria-label="Seguimiento operativo">
  <article class="admin-dashboard-panel">
    <header class="admin-dashboard-panel-header">
      <div>
        <span>Prioridad</span>
        <h3>Empresas que requieren accion</h3>
        <p>Pagos pendientes, vencidos, estados bloqueados o renovaciones cercanas.</p>
      </div>
      <strong class="admin-dashboard-count"><?= count($riskCompanies) ?></strong>
    </header>
    <div class="admin-dashboard-table-wrap">
      <table class="admin-dashboard-table">
        <thead>
          <tr>
            <th>Empresa</th>
            <th>Estado</th>
            <th>Pago</th>
            <th>Proximo pago</th>
            <th>Importe</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($riskCompanies, 0, 6) as $empresa): ?>
            <tr>
              <td><a href="index.php?route=platform-companies&q=<?= urlencode($empresa['name']) ?>"><?= e($empresa['name']) ?></a></td>
              <td><?= e(empresa_status_label($empresa['status'])) ?></td>
              <td><?= e(empresa_payment_status_label($empresa['payment_status'])) ?></td>
              <td><?= e(format_date_short($empresa['next_payment_at'] ?? null)) ?></td>
              <td><?= e(money_amount($empresa['monthly_price'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$riskCompanies): ?>
            <tr><td colspan="5">No hay empresas con incidencias ahora mismo.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>

  <article class="admin-dashboard-panel">
    <header class="admin-dashboard-panel-header">
      <div>
        <span>Cobros</span>
        <h3>Proximos movimientos</h3>
        <p>Pagos pendientes o ultimos pagos registrados para seguimiento rapido.</p>
      </div>
      <strong class="admin-dashboard-count"><?= (int) ($paymentMetrics['due_week'] ?? 0) ?></strong>
    </header>
    <div class="admin-dashboard-table-wrap">
      <table class="admin-dashboard-table">
        <thead>
          <tr>
            <th>Empresa</th>
            <th>Concepto</th>
            <th>Vence</th>
            <th>Estado</th>
            <th>Importe</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($upcomingPayments, 0, 6) as $payment): ?>
            <tr>
              <td><?= e($payment['empresa_name'] ?? 'Empresa') ?></td>
              <td><?= e($payment['concept'] ?? 'Pago SaaS') ?></td>
              <td><?= e(format_date_short($payment['due_at'] ?? null)) ?></td>
              <td><?= e(platform_payment_status_label($payment['status'] ?? 'PENDING')) ?></td>
              <td><?= e(money_amount($payment['amount'] ?? 0)) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$upcomingPayments): ?>
            <tr><td colspan="5">Todavia no hay pagos registrados.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>
</section>

<section class="admin-dashboard-bottom" aria-label="Estadisticas de cartera">
  <article class="admin-dashboard-panel">
    <header class="admin-dashboard-panel-header">
      <div>
        <span>Planes</span>
        <h3>Distribucion comercial</h3>
      </div>
    </header>
    <div class="admin-dashboard-status admin-dashboard-status--plans">
      <?php foreach ($planOptions as $plan => $label): ?>
        <?php $count = $planCounts[$plan] ?? 0; $width = (int) round(($count / $companyTotal) * 100); ?>
        <div>
          <span><?= e($label) ?></span>
          <strong><?= $count ?></strong>
          <i style="--bar-width: <?= $width ?>%"></i>
        </div>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="admin-dashboard-panel">
    <header class="admin-dashboard-panel-header">
      <div>
        <span>Ultimas empresas</span>
        <h3>Cartera reciente</h3>
      </div>
      <a href="index.php?route=platform-companies" class="admin-dashboard-link">Ver todas</a>
    </header>
    <div class="admin-dashboard-company-list">
      <?php foreach (array_slice($empresas, 0, 5) as $empresa): ?>
        <a href="index.php?route=platform-companies&q=<?= urlencode($empresa['name']) ?>">
          <span>
            <strong><?= e($empresa['name']) ?></strong>
            <small><?= e(empresa_status_label($empresa['status'])) ?> | <?= e($planOptions[$empresa['plan']] ?? $empresa['plan']) ?></small>
          </span>
          <b><?= e(money_amount($empresa['monthly_price'] ?? 0)) ?></b>
        </a>
      <?php endforeach; ?>
      <?php if (!$empresas): ?>
        <p class="platform-empty">Todavia no hay empresas registradas.</p>
      <?php endif; ?>
    </div>
  </article>
</section>
