<?php
$isEditingEmpresa = isset($empresa) && is_array($empresa);
$selectedClient = $selectedClient ?? null;
$empresaValues = $isEditingEmpresa ? $empresa : [
    'id' => '',
    'client_id' => $selectedClient['id'] ?? '',
    'name' => $selectedClient['company_name'] ?? '',
    'contact_email' => $selectedClient['email'] ?? '',
    'plan' => 'BASIC',
    'status' => 'TRIAL',
    'payment_status' => 'TRIAL',
    'monthly_price' => '0.00',
    'next_payment_at' => '',
    'notes' => '',
];
?>

<form class="empresa-form" method="post">
  <input type="hidden" name="action" value="<?= $isEditingEmpresa ? 'update_empresa' : 'create_empresa' ?>">
  <?php if ($isEditingEmpresa): ?>
    <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">
  <?php endif; ?>

  <?php if (!empty($clients)): ?>
    <label class="field form-full">
      <span>Cliente origen</span>
      <select name="client_id">
        <option value="">Sin cliente vinculado</option>
        <?php foreach ($clients as $clientOption): ?>
          <option value="<?= e($clientOption['id']) ?>" <?= ($empresaValues['client_id'] ?? '') === $clientOption['id'] ? 'selected' : '' ?>>
            <?= e($clientOption['company_name']) ?><?= $clientOption['email'] ? ' - ' . e($clientOption['email']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>

  <label class="field">
    <span>Empresa</span>
    <input name="name" required value="<?= e($empresaValues['name']) ?>" placeholder="NexoFit Studio">
  </label>
  <label class="field">
    <span>Email de contacto</span>
    <input name="contact_email" type="email" value="<?= e($empresaValues['contact_email']) ?>" placeholder="admin@empresa.com">
  </label>
  <label class="field">
    <span>Plan</span>
    <select name="plan">
      <?php foreach ($planOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $empresaValues['plan'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
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
  <label class="field">
    <span>Precio mensual</span>
    <input name="monthly_price" inputmode="decimal" value="<?= e((string) $empresaValues['monthly_price']) ?>" placeholder="49.00">
  </label>
  <label class="field">
    <span>Proximo pago</span>
    <input name="next_payment_at" type="date" value="<?= e($empresaValues['next_payment_at'] ? date('Y-m-d', strtotime($empresaValues['next_payment_at'])) : '') ?>">
  </label>
  <label class="field form-full">
    <span>Notas internas</span>
    <textarea name="notes" rows="4" placeholder="Contrato, incidencias de pago, contacto decisor..."><?= e($empresaValues['notes']) ?></textarea>
  </label>

  <?php if (!$isEditingEmpresa): ?>
    <div class="form-full platform-form-divider">
      <strong>Acceso al CRM de la empresa</strong>
      <span>Marca esta opcion para crear el tenant y el usuario administrador de este gimnasio.</span>
    </div>
    <label class="settings-check form-full">
      <input name="create_tenant" type="checkbox" value="1" checked>
      <span>Crear CRM y usuario administrador para esta empresa</span>
    </label>
    <label class="field">
      <span>Nombre administrador</span>
      <input name="admin_name" value="<?= e($selectedClient['contact_name'] ?? 'Administrador') ?>" placeholder="Laura Martin">
    </label>
    <label class="field">
      <span>Email administrador</span>
      <input name="admin_email" type="email" value="<?= e($selectedClient['email'] ?? '') ?>" placeholder="admin@empresa.com">
    </label>
    <label class="field">
      <span>Contrasena inicial</span>
      <input name="admin_password" type="text" value="MemboraDemo2026!" placeholder="Minimo 8 caracteres">
    </label>
  <?php endif; ?>

  <div class="form-actions form-full">
    <button class="secondary-action" type="button" data-close-modal>Cancelar</button>
    <button class="primary-action" type="submit"><?= $isEditingEmpresa ? 'Guardar empresa' : 'Crear empresa' ?></button>
  </div>
</form>
