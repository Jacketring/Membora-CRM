<?php
$isEditingPlan = isset($plan) && is_array($plan);
$planValues = $isEditingPlan ? $plan : [
    'id' => '',
    'code' => '',
    'name' => '',
    'monthly_price' => '0.00',
    'setup_price' => '0.00',
    'discount_price' => '',
    'discount_label' => '',
    'stripe_monthly_price_id' => '',
    'stripe_annual_price_id' => '',
    'max_users' => '',
    'max_members' => '',
    'status' => 'ACTIVE',
    'features' => '',
];
$planStatusOptions = [
    'ACTIVE' => 'Activo',
    'INACTIVE' => 'Inactivo',
    'ARCHIVED' => 'Archivado',
];
?>

<form class="empresa-form" method="post">
  <input type="hidden" name="action" value="<?= $isEditingPlan ? 'update_platform_plan' : 'create_platform_plan' ?>">
  <?php if ($isEditingPlan): ?>
    <input type="hidden" name="id" value="<?= e($planValues['id']) ?>">
  <?php endif; ?>

  <label class="field">
    <span>Nombre</span>
    <input name="name" required value="<?= e($planValues['name']) ?>" placeholder="Pro">
  </label>
  <label class="field">
    <span>Codigo</span>
    <input name="code" required value="<?= e($planValues['code']) ?>" placeholder="PRO">
  </label>
  <label class="field">
    <span>Precio mensual</span>
    <input name="monthly_price" inputmode="decimal" value="<?= e((string) $planValues['monthly_price']) ?>" placeholder="89.00">
  </label>
  <label class="field">
    <span>Alta / setup</span>
    <input name="setup_price" inputmode="decimal" value="<?= e((string) $planValues['setup_price']) ?>" placeholder="99.00">
  </label>
  <label class="field">
    <span>Precio rebajado</span>
    <input name="discount_price" inputmode="decimal" value="<?= e((string) ($planValues['discount_price'] ?? '')) ?>" placeholder="69.00">
  </label>
  <label class="field">
    <span>Texto de rebaja</span>
    <input name="discount_label" value="<?= e((string) ($planValues['discount_label'] ?? '')) ?>" placeholder="Oferta lanzamiento">
  </label>
  <label class="field">
    <span>Stripe Price o producto mensual</span>
    <input name="stripe_monthly_price_id" value="<?= e((string) ($planValues['stripe_monthly_price_id'] ?? '')) ?>" placeholder="price_... o prod_...">
  </label>
  <label class="field">
    <span>Stripe Price o producto anual</span>
    <input name="stripe_annual_price_id" value="<?= e((string) ($planValues['stripe_annual_price_id'] ?? '')) ?>" placeholder="price_... o prod_...">
  </label>
  <small class="form-full">Puedes pegar directamente un <code>price_...</code>. Si pegas un <code>prod_...</code>, Membora buscara dentro del producto la tarifa recurrente mensual o anual correspondiente.</small>
  <label class="field">
    <span>Usuarios incluidos</span>
    <input name="max_users" inputmode="numeric" value="<?= e((string) $planValues['max_users']) ?>" placeholder="8">
  </label>
  <label class="field">
    <span>Socios incluidos</span>
    <input name="max_members" inputmode="numeric" value="<?= e((string) $planValues['max_members']) ?>" placeholder="1000">
  </label>
  <label class="field">
    <span>Estado</span>
    <select name="status">
      <?php foreach ($planStatusOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $planValues['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field form-full">
    <span>Incluye</span>
    <textarea name="features" rows="4" placeholder="Funciones, soporte, limites comerciales..."><?= e($planValues['features']) ?></textarea>
  </label>

  <div class="form-actions form-full">
    <button class="secondary-action" type="button" data-close-modal>Cancelar</button>
    <button class="primary-action" type="submit"><?= $isEditingPlan ? 'Guardar plan' : 'Crear plan' ?></button>
  </div>
</form>
