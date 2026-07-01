<?php
$isEditingPayment = isset($payment) && is_array($payment);
$paymentValues = $isEditingPayment ? $payment : [
  'id' => '',
  'member_id' => '',
  'subscription_id' => '',
  'amount' => '0.00',
  'payment_method' => 'CARD',
  'status' => 'PENDING',
  'due_at' => date('Y-m-d'),
  'paid_at' => '',
  'notes' => '',
];
$paymentStatusOptions = [
  'PENDING' => 'Pendiente',
  'PAID' => 'Pagado',
  'OVERDUE' => 'Vencido',
  'CANCELLED' => 'Cancelado',
];
$paymentMethodOptions = [
  'CARD' => 'Tarjeta',
  'CASH' => 'Efectivo',
  'TRANSFER' => 'Transferencia',
  'BIZUM' => 'Bizum',
  'OTHER' => 'Otro',
];
?>

<div class="form-grid">
  <input type="hidden" name="action" value="<?= $isEditingPayment ? 'update_payment' : 'create_payment' ?>">
  <?php if ($isEditingPayment): ?>
    <input type="hidden" name="id" value="<?= e($paymentValues['id']) ?>">
  <?php endif; ?>

  <div class="field">
    <span>Socio</span>
    <div class="custom-select custom-select--field" data-custom-select>
      <input type="hidden" name="member_id" value="<?= e($paymentValues['member_id']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <?php
          $selectedMemberLabel = 'Selecciona socio';
          foreach ($members as $memberOption) {
              if ($memberOption['id'] === $paymentValues['member_id']) {
                  $selectedMemberLabel = trim($memberOption['first_name'] . ' ' . ($memberOption['last_name'] ?? ''));
              }
          }
        ?>
        <span data-custom-select-label><?= e($selectedMemberLabel) ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($members as $memberOption): ?>
          <?php $memberLabel = trim($memberOption['first_name'] . ' ' . ($memberOption['last_name'] ?? '')); ?>
          <button class="custom-select-option <?= $paymentValues['member_id'] === $memberOption['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($memberOption['id']) ?>">
            <?= e($memberLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="field">
    <span>Membresia asociada</span>
    <div class="custom-select custom-select--field" data-custom-select>
      <input type="hidden" name="subscription_id" value="<?= e($paymentValues['subscription_id'] ?? '') ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <?php
          $selectedSubscriptionLabel = 'Sin membresia asociada';
          foreach ($subscriptions as $subscriptionOption) {
              if ($subscriptionOption['id'] === ($paymentValues['subscription_id'] ?? '')) {
                  $selectedSubscriptionLabel = $subscriptionOption['member_name'] . ' · ' . $subscriptionOption['plan_name'] . ' · ' . format_date_short($subscriptionOption['ends_at']);
              }
          }
        ?>
        <span data-custom-select-label><?= e($selectedSubscriptionLabel) ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <button class="custom-select-option <?= empty($paymentValues['subscription_id']) ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="">Sin membresia asociada</button>
        <?php foreach ($subscriptions as $subscriptionOption): ?>
          <button class="custom-select-option <?= ($paymentValues['subscription_id'] ?? '') === $subscriptionOption['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($subscriptionOption['id']) ?>">
            <?= e($subscriptionOption['member_name']) ?> · <?= e($subscriptionOption['plan_name']) ?> · <?= e(money_amount($subscriptionOption['price'])) ?> · vence <?= e(format_date_short($subscriptionOption['ends_at'])) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <label class="field">
    <span>Importe</span>
    <input name="amount" type="number" min="0" step="0.01" required value="<?= e((string) $paymentValues['amount']) ?>">
  </label>

  <div class="field">
    <span>Metodo</span>
    <div class="custom-select custom-select--field" data-custom-select>
      <input type="hidden" name="payment_method" value="<?= e($paymentValues['payment_method']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <span data-custom-select-label><?= e($paymentMethodOptions[$paymentValues['payment_method']] ?? 'Otro') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($paymentMethodOptions as $methodValue => $methodLabel): ?>
          <button class="custom-select-option <?= $paymentValues['payment_method'] === $methodValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($methodValue) ?>">
            <?= e($methodLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="field">
    <span>Estado</span>
    <div class="custom-select custom-select--field" data-custom-select>
      <input type="hidden" name="status" value="<?= e($paymentValues['status']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <span data-custom-select-label><?= e($paymentStatusOptions[$paymentValues['status']] ?? 'Pendiente') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($paymentStatusOptions as $statusValue => $statusLabel): ?>
          <button class="custom-select-option <?= $paymentValues['status'] === $statusValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($statusValue) ?>">
            <?= e($statusLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <label class="field">
    <span>Vencimiento</span>
    <input name="due_at" type="date" value="<?= e($paymentValues['due_at'] ? date('Y-m-d', strtotime($paymentValues['due_at'])) : '') ?>">
  </label>

  <label class="field">
    <span>Fecha de pago</span>
    <input name="paid_at" type="date" value="<?= e($paymentValues['paid_at'] ? date('Y-m-d', strtotime($paymentValues['paid_at'])) : '') ?>">
  </label>

  <label class="field field--wide">
    <span>Notas</span>
    <textarea name="notes" rows="3" placeholder="Referencia, incidencia o detalle del cobro"><?= e($paymentValues['notes']) ?></textarea>
  </label>
</div>
