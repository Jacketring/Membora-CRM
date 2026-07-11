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
  'period_start_at' => '',
  'period_end_at' => '',
  'reference' => '',
  'paid_notes' => '',
  'notes' => '',
];
$paymentStatusOptions = [
  'DRAFT' => 'Borrador',
  'PENDING' => 'Pendiente',
  'PAID' => 'Pagado',
  'OVERDUE' => 'Vencido',
  'CANCELLED' => 'Anulado',
];
$paymentMethodOptions = [
  'CASH' => 'Efectivo',
  'TRANSFER' => 'Transferencia',
  'TPV' => 'TPV',
  'CARD' => 'Tarjeta',
  'DIRECT_DEBIT' => 'Domiciliacion',
  'OTHER' => 'Otro',
];
?>

<div class="form-grid">
  <?php if ($isEditingPayment): ?>
    <input type="hidden" name="id" value="<?= e($paymentValues['id']) ?>">
  <?php endif; ?>

  <div class="field">
    <span>Socio</span>
    <div class="custom-select custom-select--field" data-custom-select data-payment-member-select>
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
        <input class="custom-select-search" type="search" placeholder="Buscar socio..." data-custom-select-search>
        <?php foreach ($members as $memberOption): ?>
          <?php $memberLabel = trim($memberOption['first_name'] . ' ' . ($memberOption['last_name'] ?? '')); ?>
          <button class="custom-select-option <?= $paymentValues['member_id'] === $memberOption['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($memberOption['id']) ?>" data-search="<?= e(strtolower($memberLabel . ' ' . ($memberOption['email'] ?? ''))) ?>">
            <?= e($memberLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="field">
    <span>Membresía asociada</span>
    <div class="custom-select custom-select--field" data-custom-select data-payment-subscription-select>
      <input type="hidden" name="subscription_id" value="<?= e($paymentValues['subscription_id'] ?? '') ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <?php
          $selectedSubscriptionLabel = 'Sin membresía asociada';
          foreach ($subscriptions as $subscriptionOption) {
              if ($subscriptionOption['id'] === ($paymentValues['subscription_id'] ?? '')) {
                  $selectedSubscriptionLabel = $subscriptionOption['member_name'] . ' · ' . $subscriptionOption['plan_name'] . ' · ' . format_date_short($subscriptionOption['ends_at']);
              }
          }
        ?>
        <span data-custom-select-label><?= e($selectedSubscriptionLabel) ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <input class="custom-select-search" type="search" placeholder="Buscar membresía..." data-custom-select-search>
        <button class="custom-select-option <?= empty($paymentValues['subscription_id']) ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="" data-member-id="" data-search="sin membresía asociada">Sin membresía asociada</button>
        <?php foreach ($subscriptions as $subscriptionOption): ?>
          <button class="custom-select-option <?= ($paymentValues['subscription_id'] ?? '') === $subscriptionOption['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($subscriptionOption['id']) ?>" data-member-id="<?= e($subscriptionOption['member_id']) ?>" data-search="<?= e(strtolower($subscriptionOption['member_name'] . ' ' . $subscriptionOption['plan_name'] . ' ' . format_date_short($subscriptionOption['ends_at']))) ?>">
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
    <span>Método</span>
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
    <span>Periodo desde</span>
    <input name="period_start_at" type="date" value="<?= e($paymentValues['period_start_at'] ? date('Y-m-d', strtotime($paymentValues['period_start_at'])) : '') ?>">
  </label>

  <label class="field">
    <span>Periodo hasta</span>
    <input name="period_end_at" type="date" value="<?= e($paymentValues['period_end_at'] ? date('Y-m-d', strtotime($paymentValues['period_end_at'])) : '') ?>">
  </label>

  <label class="field">
    <span>Fecha de pago</span>
    <input name="paid_at" type="date" value="<?= e($paymentValues['paid_at'] ? date('Y-m-d', strtotime($paymentValues['paid_at'])) : '') ?>">
  </label>

  <label class="field">
    <span>Referencia</span>
    <input name="reference" value="<?= e((string) ($paymentValues['reference'] ?? '')) ?>" placeholder="Operación, recibo o justificante">
  </label>

  <label class="field field--wide">
    <span>Notas de cobro</span>
    <textarea name="paid_notes" rows="2" placeholder="Forma de pago, observaciones internas o incidencia"><?= e((string) ($paymentValues['paid_notes'] ?? '')) ?></textarea>
  </label>

  <label class="field field--wide">
    <span>Notas</span>
    <textarea name="notes" rows="3" placeholder="Referencia, incidencia o detalle del cobro"><?= e($paymentValues['notes']) ?></textarea>
  </label>
</div>
