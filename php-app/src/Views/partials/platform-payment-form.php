<?php
$isEditingPayment = isset($payment) && is_array($payment);
$paymentValues = $isEditingPayment ? $payment : [
    'id' => '',
    'empresa_id' => $empresas[0]['id'] ?? '',
    'concept' => '',
    'amount' => '0.00',
    'status' => 'PENDING',
    'due_at' => '',
    'paid_at' => '',
    'notes' => '',
];
$paymentStatusOptions = [
    'PENDING' => 'Pendiente',
    'PAID' => 'Pagado',
    'OVERDUE' => 'Vencido',
    'CANCELLED' => 'Cancelado',
];
?>

<form class="empresa-form" method="post">
  <input type="hidden" name="action" value="<?= $isEditingPayment ? 'update_platform_payment' : 'create_platform_payment' ?>">
  <?php if ($isEditingPayment): ?>
    <input type="hidden" name="id" value="<?= e($paymentValues['id']) ?>">
  <?php endif; ?>

  <label class="field">
    <span>Empresa</span>
    <select name="empresa_id" required>
      <?php foreach ($empresas as $empresaOption): ?>
        <option value="<?= e($empresaOption['id']) ?>" <?= $paymentValues['empresa_id'] === $empresaOption['id'] ? 'selected' : '' ?>>
          <?= e($empresaOption['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field">
    <span>Concepto</span>
    <input name="concept" required value="<?= e($paymentValues['concept']) ?>" placeholder="Mensualidad CRM junio">
  </label>
  <label class="field">
    <span>Importe</span>
    <input name="amount" inputmode="decimal" value="<?= e((string) $paymentValues['amount']) ?>" placeholder="89.00">
  </label>
  <label class="field">
    <span>Estado</span>
    <select name="status">
      <?php foreach ($paymentStatusOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $paymentValues['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field">
    <span>Vencimiento</span>
    <input name="due_at" type="date" value="<?= e($paymentValues['due_at'] ? date('Y-m-d', strtotime($paymentValues['due_at'])) : '') ?>">
  </label>
  <label class="field">
    <span>Fecha de pago</span>
    <input name="paid_at" type="date" value="<?= e($paymentValues['paid_at'] ? date('Y-m-d', strtotime($paymentValues['paid_at'])) : '') ?>">
  </label>
  <label class="field form-full">
    <span>Notas internas</span>
    <textarea name="notes" rows="4" placeholder="Factura, incidencia, metodo de pago..."><?= e($paymentValues['notes']) ?></textarea>
  </label>

  <div class="form-actions form-full">
    <button class="secondary-action" type="button" data-close-modal>Cancelar</button>
    <button class="primary-action" type="submit"><?= $isEditingPayment ? 'Guardar pago' : 'Crear pago' ?></button>
  </div>
</form>
