<?php
$periodOptions = [
  'WEEKLY' => 'Semanal',
  'MONTHLY' => 'Mensual',
  'BIMONTHLY' => 'Bimestral',
  'QUARTERLY' => 'Trimestral',
  'YEARLY' => 'Anual',
];
$statusOptions = [
  'ACTIVE' => 'Activa',
  'INACTIVE' => 'Inactiva',
];
?>

<div class="form-grid">
  <label class="field">
    <span>Nombre</span>
    <input name="name" required value="<?= e($plan['name'] ?? '') ?>" placeholder="Ej. Mensual ilimitada">
  </label>
  <label class="field">
    <span>Precio</span>
    <input name="price" type="number" min="0" step="0.01" required value="<?= e((string) ($plan['price'] ?? '0.00')) ?>" placeholder="49.90">
  </label>
  <div class="field">
    <span>Duración</span>
    <div class="custom-select custom-select--field" data-custom-select>
      <input type="hidden" name="billing_period" value="<?= e($plan['billing_period'] ?? 'MONTHLY') ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <span data-custom-select-label><?= e(membership_period_label($plan['billing_period'] ?? 'MONTHLY')) ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($periodOptions as $periodValue => $periodLabel): ?>
          <button class="custom-select-option <?= ($plan['billing_period'] ?? 'MONTHLY') === $periodValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($periodValue) ?>">
            <?= e($periodLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="field">
    <span>Estado</span>
    <div class="custom-select custom-select--field" data-custom-select>
      <input type="hidden" name="status" value="<?= e($plan['status'] ?? 'ACTIVE') ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <span data-custom-select-label><?= e($statusOptions[$plan['status'] ?? 'ACTIVE'] ?? 'Activa') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
          <button class="custom-select-option <?= ($plan['status'] ?? 'ACTIVE') === $statusValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($statusValue) ?>">
            <?= e($statusLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <label class="field field--wide">
    <span>Descripción</span>
    <textarea name="description" rows="3" placeholder="Incluye lo que cubre esta membresía"><?= e($plan['description'] ?? '') ?></textarea>
  </label>
</div>
