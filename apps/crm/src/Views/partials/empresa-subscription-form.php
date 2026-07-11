<?php
$planPrices = $planPrices ?? PlatformPlanRepository::priceMap();
$subscriptionEmpresa = $subscriptionEmpresa ?? ($empresa ?? null);
?>

<?php if (!$subscriptionEmpresa): ?>
  <div class="empty-state form-full">
    Este cliente todavía no tiene una empresa CRM vinculada.
  </div>
  <div class="form-actions form-full">
    <button class="secondary-action" type="button" data-close-modal>Cerrar</button>
    <a class="primary-action" href="index.php?route=platform-companies&client_id=<?= urlencode((string) ($client['id'] ?? '')) ?>&modal=empresa-create-modal">Crear empresa</a>
  </div>
<?php else: ?>
  <?php
    $empresaValues = $subscriptionEmpresa;
    $isTrialPlan = strtoupper((string) ($empresaValues['plan'] ?? '')) === 'TRIAL';
    $renewalStatus = (string) ($empresaValues['renewal_status'] ?? 'ACTIVE');
    $canCancel = !$isTrialPlan && $renewalStatus === 'ACTIVE' && (string) ($empresaValues['status'] ?? '') !== 'CANCELLED';
    $canResume = in_array($renewalStatus, ['CANCEL_AT_PERIOD_END', 'CANCELLED'], true);
    $nextPaymentTime = !empty($empresaValues['next_payment_at']) ? strtotime((string) $empresaValues['next_payment_at']) : false;
    $canRenew = !$isTrialPlan
        && $nextPaymentTime !== false
        && $nextPaymentTime <= strtotime(date('Y-m-d'))
        && in_array((string) ($empresaValues['status'] ?? ''), ['ACTIVE', 'TRIAL'], true)
        && (float) ($empresaValues['monthly_price'] ?? 0) > 0;
    $stripeEnabled = StripeBillingConfig::enabled();
    $stripeSubscriptionId = trim((string) ($empresaValues['stripe_subscription_id'] ?? ''));
  ?>
  <form class="empresa-form" method="post" data-empresa-form data-plan-prices='<?= e(json_encode($planPrices, JSON_UNESCAPED_UNICODE)) ?>'>
    <input type="hidden" name="action" value="update_empresa_subscription">
    <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">

    <div class="form-full platform-form-divider">
      <strong><?= e($empresaValues['name']) ?></strong>
      <span><?= e($empresaValues['contact_email'] ?: 'Sin email de contacto') ?></span>
    </div>

    <div class="form-full platform-form-divider">
      <strong>Stripe Billing</strong>
      <span>
        <?= $stripeEnabled ? 'Modo test activo' : 'No activo' ?>
        <?php if (!empty($empresaValues['stripe_customer_id'])): ?>
          · Customer <?= e($empresaValues['stripe_customer_id']) ?>
        <?php endif; ?>
        <?php if ($stripeSubscriptionId !== ''): ?>
          · Subscription <?= e($stripeSubscriptionId) ?>
        <?php endif; ?>
      </span>
      <?php if (!empty($empresaValues['stripe_last_error'])): ?>
        <span><?= e($empresaValues['stripe_last_error']) ?></span>
      <?php endif; ?>
    </div>

    <label class="field">
      <span>Plan</span>
      <select name="plan" data-plan-price-select>
        <?php foreach ($planOptions as $value => $label): ?>
          <option value="<?= e($value) ?>" data-monthly-price="<?= e($planPrices[$value] ?? '') ?>" <?= $empresaValues['plan'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>Precio mensual</span>
      <input name="monthly_price" inputmode="decimal" value="<?= e((string) $empresaValues['monthly_price']) ?>" placeholder="49.00" data-plan-price-input>
    </label>
    <label class="field">
      <span>Estado CRM</span>
      <select name="status">
        <?php foreach (array_filter($statusOptions, static fn ($label, $value): bool => $value !== '', ARRAY_FILTER_USE_BOTH) as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $empresaValues['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>Estado de pago</span>
      <select name="payment_status">
        <?php foreach (array_filter($paymentOptions, static fn ($label, $value): bool => $value !== '', ARRAY_FILTER_USE_BOTH) as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $empresaValues['payment_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field" data-next-payment-field>
      <span>Próximo pago</span>
      <input name="next_payment_at" type="date" value="<?= e(!empty($empresaValues['next_payment_at']) ? date('Y-m-d', strtotime($empresaValues['next_payment_at'])) : '') ?>" data-next-payment-input>
    </label>
    <label class="field" data-trial-plan-field hidden>
      <span>Días de prueba</span>
      <input name="trial_days" type="number" min="1" max="365" step="1" value="<?= e((string) ($empresaValues['trial_days'] ?? 30)) ?>" placeholder="30">
    </label>
    <label class="field">
      <span>Suscrito desde</span>
      <input name="subscription_started_at" type="date" value="<?= e(!empty($empresaValues['subscription_started_at']) ? date('Y-m-d', strtotime($empresaValues['subscription_started_at'])) : date('Y-m-d')) ?>">
    </label>
    <label class="field">
      <span>Acceso hasta</span>
      <input name="access_until" type="date" readonly value="<?= e(!empty($empresaValues['access_until']) ? date('Y-m-d', strtotime($empresaValues['access_until'])) : (!empty($empresaValues['next_payment_at']) ? date('Y-m-d', strtotime($empresaValues['next_payment_at'])) : '')) ?>" data-access-until-input>
    </label>
    <label class="field">
      <span>Renovación</span>
      <select name="renewal_period">
        <?php foreach (['MONTHLY' => 'Mensual', 'ANNUAL' => 'Anual'] as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= ($empresaValues['renewal_period'] ?? 'MONTHLY') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>Estado de renovación</span>
      <select name="renewal_status">
        <?php foreach (['ACTIVE' => 'Renovación activa', 'CANCEL_AT_PERIOD_END' => 'Cancelar al final del periodo', 'CANCELLED' => 'Cancelada'] as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= ($empresaValues['renewal_status'] ?? 'ACTIVE') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>Cancelada el</span>
      <input name="cancelled_at" type="date" value="<?= e(!empty($empresaValues['cancelled_at']) ? date('Y-m-d', strtotime($empresaValues['cancelled_at'])) : '') ?>">
    </label>

    <div class="form-actions form-full">
      <button class="secondary-action" type="button" data-close-modal>Cancelar</button>
      <button class="primary-action" type="submit">Guardar suscripción</button>
    </div>
  </form>

  <div class="platform-subscription-actions">
    <?php if ($stripeEnabled && !$isTrialPlan): ?>
      <form method="post" data-confirm-message="Se abrira Stripe Checkout en modo test. El acceso solo se activara cuando llegue el webhook válido de Stripe." data-confirm-action-label="Abrir Stripe">
        <input type="hidden" name="action" value="create_empresa_stripe_checkout">
        <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">
        <input type="hidden" name="return" value="platform-contacts">
        <button class="support-enter-action" type="submit">Checkout Stripe</button>
      </form>
    <?php endif; ?>
    <?php if ($stripeEnabled && $stripeSubscriptionId !== '' && $canCancel): ?>
      <form method="post" data-confirm-message="Stripe marcara la suscripción para cancelar al final del periodo. El acceso se conserva hasta current_period_end." data-confirm-action-label="Cancelar en Stripe">
        <input type="hidden" name="action" value="cancel_empresa_stripe_subscription">
        <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">
        <input type="hidden" name="return" value="platform-contacts">
        <button class="note-delete-button" type="submit">Cancelar en Stripe</button>
      </form>
    <?php endif; ?>
    <?php if ($canRenew): ?>
      <form method="post" data-confirm-message="Se creara un pago pagado y se movera el próximo pago al siguiente periodo." data-confirm-action-label="Confirmar">
        <input type="hidden" name="action" value="renew_empresa_subscription">
        <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">
        <input type="hidden" name="return" value="platform-contacts">
        <button class="support-renew-action" type="submit">Renovar ahora</button>
      </form>
    <?php endif; ?>
    <?php if ($canCancel): ?>
      <form method="post" data-confirm-message="La empresa mantendra acceso hasta la fecha de fin del periodo, pero no renovara automaticamente." data-confirm-action-label="Cancelar renovación">
        <input type="hidden" name="action" value="cancel_empresa_subscription">
        <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">
        <input type="hidden" name="return" value="platform-contacts">
        <button class="note-delete-button" type="submit">Cancelar renovación</button>
      </form>
    <?php endif; ?>
    <?php if ($canResume): ?>
      <form method="post">
        <input type="hidden" name="action" value="resume_empresa_subscription">
        <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">
        <input type="hidden" name="return" value="platform-contacts">
        <button class="support-renew-action" type="submit">Reactivar</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
