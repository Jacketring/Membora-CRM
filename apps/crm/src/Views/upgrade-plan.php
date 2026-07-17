<?php
$isTrial = strtoupper((string) ($empresa['plan'] ?? '')) === 'TRIAL' || (string) ($empresa['status'] ?? '') === 'TRIAL';
?>
<section class="page-header upgrade-plan-header">
  <div>
    <span class="eyebrow">PLANES DE PAGO</span>
    <h2>Mejora tu plan de Membora</h2>
    <p><?= $simulatedCheckout
      ? 'Elige un plan y prueba el flujo completo con una tarjeta ficticia. No se realiza ningún cargo bancario.'
      : 'Elige el plan y la periodicidad. Los datos bancarios se introducen de forma segura en Stripe y Membora no almacena los datos de tu tarjeta.' ?></p>
  </div>
  <?php if (!empty($accessState['remaining_days'])): ?>
    <div class="upgrade-plan-days">
      <strong><?= (int) $accessState['remaining_days'] ?></strong>
      <span><?= (int) $accessState['remaining_days'] === 1 ? 'día de prueba restante' : 'días de prueba restantes' ?></span>
    </div>
  <?php endif; ?>
</section>

<?php if (!$isTrial && !empty($accessState['blocked'])): ?>
  <div class="notice notice-error">Tu suscripción no tiene acceso activo. Contacta con el equipo de Membora para revisar la reactivación del plan <?= e((string) ($empresa['plan'] ?? 'actual')) ?>.</div>
<?php elseif (!$isTrial): ?>
  <div class="notice notice-success">Tu empresa ya tiene el plan <?= e((string) ($empresa['plan'] ?? 'activo')) ?>. Los cambios posteriores se gestionan con el equipo de Membora.</div>
<?php elseif (!$canPurchase): ?>
  <div class="notice notice-error">Solo el administrador del gimnasio puede contratar un plan. Puedes consultar las opciones y pedirle que complete el pago.</div>
<?php elseif (!$simulatedCheckout && !$stripeReady): ?>
  <div class="notice notice-error">El pago todavía no está disponible. El administrador de Membora debe completar el modo Stripe Test, el secreto del webhook y los Price ID de los planes.</div>
<?php endif; ?>

<div class="upgrade-plan-grid">
  <?php foreach ($plans as $plan): ?>
    <?php
      $monthlyAvailable = $simulatedCheckout || ($stripeReady && !empty($plan['stripe_monthly_available']));
      $annualAvailable = $simulatedCheckout || ($stripeReady && !empty($plan['stripe_annual_available']));
      $checkoutAction = $simulatedCheckout ? 'open_tenant_simulated_checkout' : 'create_tenant_stripe_checkout';
      $features = array_slice($plan['features'] ?? [], 0, 4);
    ?>
    <article class="upgrade-plan-card">
      <header>
        <div>
          <span class="upgrade-plan-code"><?= e((string) $plan['code']) ?></span>
          <h3><?= e((string) $plan['name']) ?></h3>
        </div>
        <?php if (!empty($plan['discount_label'])): ?>
          <span class="upgrade-plan-offer"><?= e((string) $plan['discount_label']) ?></span>
        <?php endif; ?>
      </header>
      <div class="upgrade-plan-price">
        <?php if (!empty($plan['original_monthly_price'])): ?>
          <del><?= e(money_amount($plan['original_monthly_price'])) ?></del>
        <?php endif; ?>
        <strong><?= e(money_amount($plan['monthly_price'])) ?></strong>
        <span>al mes</span>
      </div>
      <ul>
        <?php foreach ($features as $feature): ?>
          <li><?= e((string) $feature) ?></li>
        <?php endforeach; ?>
        <?php if ($plan['max_users'] !== null): ?><li>Hasta <?= (int) $plan['max_users'] ?> usuarios</li><?php endif; ?>
        <?php if ($plan['max_members'] !== null): ?><li>Hasta <?= (int) $plan['max_members'] ?> socios</li><?php endif; ?>
      </ul>
      <div class="upgrade-plan-actions">
        <form method="post" action="index.php?route=upgrade-plan">
          <input type="hidden" name="action" value="<?= e($checkoutAction) ?>">
          <input type="hidden" name="plan_code" value="<?= e((string) $plan['code']) ?>">
          <input type="hidden" name="renewal_period" value="MONTHLY">
          <button class="primary-action" type="submit" <?= !$monthlyAvailable || !$isTrial || !$canPurchase ? 'disabled' : '' ?>><?= $simulatedCheckout ? 'Probar pago mensual' : 'Pagar mensualmente' ?></button>
        </form>
        <?php if ($simulatedCheckout || !empty($plan['stripe_annual_available'])): ?>
          <form method="post" action="index.php?route=upgrade-plan">
            <input type="hidden" name="action" value="<?= e($checkoutAction) ?>">
            <input type="hidden" name="plan_code" value="<?= e((string) $plan['code']) ?>">
            <input type="hidden" name="renewal_period" value="ANNUAL">
            <button class="secondary-action" type="submit" <?= !$annualAvailable || !$isTrial || !$canPurchase ? 'disabled' : '' ?>><?= $simulatedCheckout ? 'Probar pago anual' : 'Pagar anualmente' ?></button>
          </form>
        <?php endif; ?>
      </div>
      <?php if (!$simulatedCheckout && !$monthlyAvailable && !$annualAvailable): ?>
        <small class="upgrade-plan-unavailable">Plan pendiente de configuración en Stripe.</small>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>

<p class="upgrade-plan-security"><?= $simulatedCheckout
  ? 'Modo de demostración: la tarjeta es ficticia, no se contacta con bancos y no se almacena ningún dato de tarjeta. El resultado se registra como pago simulado en administración.'
  : 'El plan no cambia al volver del Checkout. Solo se activa cuando el webhook firmado de Stripe confirma el pago; entonces se crean el pago y la factura en la administración de Membora.' ?></p>
