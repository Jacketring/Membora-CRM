<?php
$planPrices = $planPrices ?? PlatformPlanRepository::priceMap();
$subscriptionEmpresa = $subscriptionEmpresa ?? ($empresa ?? null);
?>

<?php if (!$subscriptionEmpresa): ?>
  <div class="empty-state form-full">
    Este cliente todavia no tiene una empresa CRM vinculada.
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
  ?>
  <form class="empresa-form" method="post" data-empresa-form data-plan-prices='<?= e(json_encode($planPrices, JSON_UNESCAPED_UNICODE)) ?>'>
    <input type="hidden" name="action" value="update_empresa_subscription">
    <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">

    <div class="form-full platform-form-divider">
      <strong><?= e($empresaValues['name']) ?></strong>
      <span><?= e($empresaValues['contact_email'] ?: 'Sin email de contacto') ?></span>
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
      <span>Proximo pago</span>
      <input name="next_payment_at" type="date" value="<?= e(!empty($empresaValues['next_payment_at']) ? date('Y-m-d', strtotime($empresaValues['next_payment_at'])) : '') ?>" data-next-payment-input>
    </label>
    <label class="field" data-trial-plan-field hidden>
      <span>Dias de prueba</span>
      <input name="trial_days" type="number" min="1" max="365" step="1" value="<?= e((string) ($empresaValues['trial_days'] ?? 30)) ?>" placeholder="30">
    </label>
    <label class="field">
      <span>Suscrito desde</span>
      <input name="subscription_started_at" type="date" value="<?= e(!empty($empresaValues['subscription_started_at']) ? date('Y-m-d', strtotime($empresaValues['subscription_started_at'])) : date('Y-m-d')) ?>">
    </label>
    <label class="field">
      <span>Paga desde</span>
      <input name="paid_since" type="date" value="<?= e(!empty($empresaValues['paid_since']) ? date('Y-m-d', strtotime($empresaValues['paid_since'])) : '') ?>">
    </label>
    <label class="field">
      <span>Acceso hasta</span>
      <input name="access_until" type="date" value="<?= e(!empty($empresaValues['access_until']) ? date('Y-m-d', strtotime($empresaValues['access_until'])) : '') ?>">
    </label>
    <label class="field">
      <span>Renovacion</span>
      <select name="renewal_period">
        <?php foreach (['MONTHLY' => 'Mensual', 'ANNUAL' => 'Anual'] as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= ($empresaValues['renewal_period'] ?? 'MONTHLY') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>Estado de renovacion</span>
      <select name="renewal_status">
        <?php foreach (['ACTIVE' => 'Renovacion activa', 'CANCEL_AT_PERIOD_END' => 'Cancelar al final del periodo', 'CANCELLED' => 'Cancelada'] as $value => $label): ?>
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
      <button class="primary-action" type="submit">Guardar suscripcion</button>
    </div>
  </form>

  <div class="platform-subscription-actions">
    <?php if ($canRenew): ?>
      <form method="post" data-confirm-message="Se creara un pago pagado y se movera el proximo pago al siguiente periodo." data-confirm-action-label="Confirmar">
        <input type="hidden" name="action" value="renew_empresa_subscription">
        <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">
        <input type="hidden" name="return" value="platform-contacts">
        <button class="support-renew-action" type="submit">Renovar ahora</button>
      </form>
    <?php endif; ?>
    <?php if ($canCancel): ?>
      <form method="post" data-confirm-message="La empresa mantendra acceso hasta la fecha de fin del periodo, pero no renovara automaticamente." data-confirm-action-label="Cancelar renovacion">
        <input type="hidden" name="action" value="cancel_empresa_subscription">
        <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">
        <input type="hidden" name="return" value="platform-contacts">
        <button class="note-delete-button" type="submit">Cancelar renovacion</button>
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
