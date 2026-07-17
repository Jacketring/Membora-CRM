<?php
$isTrial = strtoupper((string) ($empresa['plan'] ?? '')) === 'TRIAL' || (string) ($empresa['status'] ?? '') === 'TRIAL';
$remainingDays = max(0, (int) ($accessState['remaining_days'] ?? 0));
?>
<div class="upgrade-plan-page">
  <section class="upgrade-plan-hero">
    <div class="upgrade-plan-hero-copy">
      <span class="upgrade-plan-kicker"><i></i><?= $simulatedCheckout ? 'MODO DE DEMOSTRACIÓN' : 'PLANES MEMBORA' ?></span>
      <h2>Haz crecer tu centro<br>con el plan adecuado</h2>
      <p><?= $simulatedCheckout
        ? 'Prueba el recorrido de contratación completo con datos ficticios. Sin cargos, sin bancos y sin guardar datos de tarjeta.'
        : 'Elige la modalidad que mejor encaja con tu centro. El pago se procesa de forma segura mediante Stripe.' ?></p>
      <div class="upgrade-plan-trust">
        <span>✓ Activación inmediata</span>
        <span>✓ Sin permanencia</span>
        <span>✓ Datos protegidos</span>
      </div>
    </div>

    <?php if ($isTrial): ?>
      <div class="upgrade-plan-trial-card">
        <span>Tu periodo de prueba</span>
        <div class="upgrade-plan-trial-number"><?= $remainingDays ?></div>
        <strong><?= $remainingDays === 1 ? 'día disponible' : 'días disponibles' ?></strong>
        <small>Mejora el plan cuando quieras</small>
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
    <div class="notice notice-error">El pago todavía no está disponible. El administrador de Membora debe completar la configuración de Stripe.</div>
  <?php endif; ?>

  <section class="upgrade-plan-pricing">
    <header class="upgrade-plan-pricing-header">
      <div>
        <span>PLANES DE PAGO</span>
        <h3>Elige cómo quieres continuar</h3>
      </div>
      <?php if ($simulatedCheckout): ?>
        <div class="upgrade-plan-demo-pill"><i></i> Pago totalmente simulado</div>
      <?php endif; ?>
    </header>

    <div class="upgrade-plan-grid">
      <?php foreach ($plans as $plan): ?>
        <?php
          $planCode = strtoupper((string) $plan['code']);
          $isFeatured = $planCode === 'PRO';
          $monthlyAvailable = $simulatedCheckout || ($stripeReady && !empty($plan['stripe_monthly_available']));
          $annualAvailable = $simulatedCheckout || ($stripeReady && !empty($plan['stripe_annual_available']));
          $checkoutAction = $simulatedCheckout ? 'open_tenant_simulated_checkout' : 'create_tenant_stripe_checkout';
          $features = array_slice($plan['features'] ?? [], 0, 4);
          $annualAmount = (float) $plan['monthly_price'] * 12;
          $planClass = strtolower(preg_replace('/[^A-Z0-9_-]/', '', $planCode) ?: 'plan');
        ?>
        <article class="upgrade-plan-card upgrade-plan-card--<?= e($planClass) ?><?= $isFeatured ? ' upgrade-plan-card--featured' : '' ?>">
          <?php if ($isFeatured): ?><div class="upgrade-plan-popular">MÁS ELEGIDO</div><?php endif; ?>

          <header class="upgrade-plan-card-header">
            <div class="upgrade-plan-card-icon"><?= e(substr($planCode, 0, 1)) ?></div>
            <div>
              <span><?= e($planCode) ?></span>
              <h3><?= e((string) $plan['name']) ?></h3>
            </div>
          </header>

          <div class="upgrade-plan-price">
            <?php if (!empty($plan['original_monthly_price'])): ?>
              <del><?= e(money_amount($plan['original_monthly_price'])) ?></del>
            <?php endif; ?>
            <div><strong><?= e(money_amount($plan['monthly_price'])) ?></strong><span>/mes</span></div>
            <small>Facturación mensual · anual <?= e(money_amount($annualAmount)) ?> · Precios sin IVA.</small>
          </div>

          <?php if (!empty($plan['discount_label'])): ?>
            <div class="upgrade-plan-offer"><?= e((string) $plan['discount_label']) ?></div>
          <?php endif; ?>

          <div class="upgrade-plan-divider"></div>
          <strong class="upgrade-plan-includes">Todo lo que necesitas:</strong>
          <ul class="upgrade-plan-features">
            <?php foreach ($features as $feature): ?>
              <li><?= e((string) $feature) ?></li>
            <?php endforeach; ?>
            <?php if ($plan['max_users'] !== null): ?><li>Hasta <?= (int) $plan['max_users'] ?> usuarios</li><?php endif; ?>
            <?php if ($plan['max_members'] !== null): ?><li>Hasta <?= (int) $plan['max_members'] ?> socios</li><?php endif; ?>
            <?php if ($plan['max_users'] === null || $plan['max_members'] === null): ?><li>Capacidad personalizada</li><?php endif; ?>
          </ul>

          <div class="upgrade-plan-actions">
            <form method="post" action="index.php?route=upgrade-plan">
              <input type="hidden" name="action" value="<?= e($checkoutAction) ?>">
              <input type="hidden" name="plan_code" value="<?= e($planCode) ?>">
              <input type="hidden" name="renewal_period" value="MONTHLY">
              <button class="upgrade-plan-button upgrade-plan-button--primary" type="submit" <?= !$monthlyAvailable || !$isTrial || !$canPurchase ? 'disabled' : '' ?>>
                <?= $simulatedCheckout ? 'Probar pago mensual' : 'Elegir pago mensual' ?><span>→</span>
              </button>
            </form>
            <?php if ($simulatedCheckout || !empty($plan['stripe_annual_available'])): ?>
              <form method="post" action="index.php?route=upgrade-plan">
                <input type="hidden" name="action" value="<?= e($checkoutAction) ?>">
                <input type="hidden" name="plan_code" value="<?= e($planCode) ?>">
                <input type="hidden" name="renewal_period" value="ANNUAL">
                <button class="upgrade-plan-button upgrade-plan-button--secondary" type="submit" <?= !$annualAvailable || !$isTrial || !$canPurchase ? 'disabled' : '' ?>>Probar pago anual</button>
              </form>
            <?php endif; ?>
          </div>

          <?php if (!$simulatedCheckout && !$monthlyAvailable && !$annualAvailable): ?>
            <small class="upgrade-plan-unavailable">Pendiente de configuración en Stripe</small>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <footer class="upgrade-plan-security">
    <span class="upgrade-plan-security-icon">✓</span>
    <div>
      <strong><?= $simulatedCheckout ? 'Entorno seguro de demostración' : 'Pago protegido' ?></strong>
      <p><?= $simulatedCheckout
        ? 'No se realiza ningún cargo ni se contacta con entidades financieras. El resultado se registra claramente como simulado.'
        : 'Membora no almacena datos bancarios. Stripe confirma el pago antes de activar el plan.' ?></p>
    </div>
  </footer>
</div>
