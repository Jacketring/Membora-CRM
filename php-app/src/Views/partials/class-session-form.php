<?php
$sessionTypeId = $editingSession['class_type_id'] ?? ($activeClassTypes[0]['id'] ?? '');
$sessionTypeLabel = 'Seleccionar clase';
foreach ($activeClassTypes as $type) {
  if ($type['id'] === $sessionTypeId) {
    $sessionTypeLabel = $type['name'];
    break;
  }
}
$sessionInstructorId = $editingSession['instructor_user_id'] ?? '';
$sessionInstructorLabel = 'Sin instructor';
foreach ($staff as $staffMember) {
  if ($staffMember['id'] === $sessionInstructorId) {
    $sessionInstructorLabel = $staffMember['name'];
    break;
  }
}
$sessionStatus = $editingSession['status'] ?? 'SCHEDULED';
$sessionDate = !empty($editingSession['starts_at']) ? date('Y-m-d', strtotime($editingSession['starts_at'])) : date('Y-m-d');
$sessionStartTime = !empty($editingSession['starts_at']) ? date('H:i', strtotime($editingSession['starts_at'])) : date('H:00', strtotime('+1 hour'));
$sessionEndTime = !empty($editingSession['ends_at']) ? date('H:i', strtotime($editingSession['ends_at'])) : date('H:00', strtotime('+2 hours'));
$sessionCapacity = $editingSession['capacity'] ?? ($activeClassTypes[0]['capacity'] ?? 12);
?>

<div class="form-grid">
  <div class="field">
    <span>Clase</span>
    <div class="custom-select custom-select--field" data-custom-select>
      <input type="hidden" name="class_type_id" value="<?= e($sessionTypeId) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <span data-custom-select-label><?= e($sessionTypeLabel) ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($activeClassTypes as $type): ?>
          <button class="custom-select-option <?= $sessionTypeId === $type['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($type['id']) ?>">
            <?= e($type['name']) ?> - <?= (int) $type['duration_minutes'] ?> min
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="field">
    <span>Instructor</span>
    <div class="custom-select custom-select--field" data-custom-select>
      <input type="hidden" name="instructor_user_id" value="<?= e($sessionInstructorId) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <span data-custom-select-label><?= e($sessionInstructorLabel) ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <button class="custom-select-option <?= $sessionInstructorId === '' ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="">Sin instructor</button>
        <?php foreach ($staff as $staffMember): ?>
          <button class="custom-select-option <?= $sessionInstructorId === $staffMember['id'] ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($staffMember['id']) ?>">
            <?= e($staffMember['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <label class="field">
    <span>Fecha</span>
    <input name="class_date" type="date" required value="<?= e($sessionDate) ?>">
  </label>
  <label class="field">
    <span>Hora inicio</span>
    <input name="class_start_time" type="time" required value="<?= e($sessionStartTime) ?>">
  </label>
  <label class="field">
    <span>Hora finalizacion</span>
    <input name="class_end_time" type="time" required value="<?= e($sessionEndTime) ?>">
  </label>
  <label class="field">
    <span>Aforo</span>
    <input name="capacity" type="number" min="1" required value="<?= (int) $sessionCapacity ?>">
  </label>
  <?php if ($editingSession): ?>
    <div class="field">
      <span>Estado</span>
      <div class="custom-select custom-select--field" data-custom-select>
        <input type="hidden" name="status" value="<?= e($sessionStatus) ?>" data-custom-select-value>
        <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
          <span data-custom-select-label><?= e(status_label($sessionStatus)) ?></span>
        </button>
        <div class="custom-select-menu" data-custom-select-menu hidden>
          <?php foreach (['SCHEDULED', 'COMPLETED', 'CANCELLED'] as $status): ?>
            <button class="custom-select-option <?= $sessionStatus === $status ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($status) ?>">
              <?= e(status_label($status)) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
