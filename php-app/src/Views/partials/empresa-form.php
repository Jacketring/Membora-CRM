<?php
$isEditingEmpresa = isset($empresa) && is_array($empresa);
$selectedClient = $selectedClient ?? null;
$planPrices = $planPrices ?? PlatformPlanRepository::priceMap();
$empresaValues = $isEditingEmpresa ? $empresa : [
    'id' => '',
    'client_id' => $selectedClient['id'] ?? '',
    'name' => $selectedClient['company_name'] ?? '',
    'contact_email' => $selectedClient['email'] ?? '',
    'plan' => 'TRIAL',
    'status' => 'TRIAL',
    'payment_status' => 'TRIAL',
    'monthly_price' => '0.00',
    'next_payment_at' => '',
    'trial_days' => '30',
    'notes' => '',
];
$selectedClientId = (string) ($empresaValues['client_id'] ?? '');
$selectedClientLabel = 'Sin cliente vinculado';
foreach (($clients ?? []) as $clientOption) {
    if ((string) $clientOption['id'] === $selectedClientId) {
        $selectedClientLabel = trim((string) $clientOption['company_name']);
        if (!empty($clientOption['email'])) {
            $selectedClientLabel .= ' - ' . $clientOption['email'];
        }
        break;
    }
}
?>

<form class="empresa-form" method="post" data-empresa-form data-plan-prices='<?= e(json_encode($planPrices, JSON_UNESCAPED_UNICODE)) ?>'>
  <input type="hidden" name="action" value="<?= $isEditingEmpresa ? 'update_empresa' : 'create_empresa' ?>">
  <?php if ($isEditingEmpresa): ?>
    <input type="hidden" name="id" value="<?= e($empresaValues['id']) ?>">
  <?php endif; ?>

  <?php if (!empty($clients)): ?>
    <label class="field form-full">
      <span>Cliente vinculado</span>
      <div class="custom-select custom-select--field custom-select--searchable" data-custom-select>
        <input type="hidden" name="client_id" value="<?= e($selectedClientId) ?>" data-custom-select-value>
        <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
          <span data-custom-select-label><?= e($selectedClientLabel) ?></span>
        </button>
        <div class="custom-select-menu" data-custom-select-menu hidden>
          <input class="custom-select-search" type="search" placeholder="Buscar cliente por empresa, contacto o email" data-custom-select-search>
          <button
            class="custom-select-option <?= $selectedClientId === '' ? 'selected' : '' ?>"
            type="button"
            data-custom-select-option
            data-value=""
            data-search="sin cliente vinculado"
          >Sin cliente vinculado</button>
        <?php foreach ($clients as $clientOption): ?>
          <?php
            $clientLabel = trim((string) $clientOption['company_name']);
            if (!empty($clientOption['email'])) {
                $clientLabel .= ' - ' . $clientOption['email'];
            }
            $clientSearch = implode(' ', array_filter([
                $clientOption['company_name'] ?? '',
                $clientOption['contact_name'] ?? '',
                $clientOption['email'] ?? '',
                $clientOption['phone'] ?? '',
            ]));
          ?>
          <button
            class="custom-select-option <?= $selectedClientId === (string) $clientOption['id'] ? 'selected' : '' ?>"
            type="button"
            data-custom-select-option
            data-value="<?= e($clientOption['id']) ?>"
            data-search="<?= e($clientSearch) ?>"
          ><?= e($clientLabel) ?></button>
        <?php endforeach; ?>
          <p class="custom-select-empty" data-custom-select-empty hidden>No hay clientes que coincidan con la busqueda.</p>
        </div>
      </div>
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
    <select name="plan" data-plan-price-select>
      <?php foreach ($planOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" data-monthly-price="<?= e($planPrices[$value] ?? '') ?>" <?= $empresaValues['plan'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
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
    <input name="monthly_price" inputmode="decimal" value="<?= e((string) $empresaValues['monthly_price']) ?>" placeholder="49.00" data-plan-price-input>
  </label>
  <label class="field" data-next-payment-field>
    <span>Proximo pago</span>
    <input name="next_payment_at" type="date" value="<?= e($empresaValues['next_payment_at'] ? date('Y-m-d', strtotime($empresaValues['next_payment_at'])) : '') ?>" data-next-payment-input>
  </label>
  <label class="field" data-trial-plan-field hidden>
    <span>Dias de prueba</span>
    <input name="trial_days" type="number" min="1" max="365" step="1" value="<?= e((string) ($empresaValues['trial_days'] ?? 30)) ?>" placeholder="30">
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
