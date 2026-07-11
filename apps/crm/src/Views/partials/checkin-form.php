<?php
$checkinValues = [
  'member_id' => '',
  'reservation_id' => '',
  'method' => 'MANUAL',
  'checkin_date' => date('Y-m-d'),
  'checkin_time' => date('H:i'),
  'notes' => '',
];
$checkinMethodOptions = [
  'MANUAL' => 'Manual',
  'QR' => 'QR',
];
?>

<div class="form-grid">
  <input type="hidden" name="action" value="create_checkin">

  <div class="field">
    <span>Socio</span>
    <div class="custom-select custom-select--field" data-custom-select data-checkin-member-select>
      <input type="hidden" name="member_id" value="<?= e($checkinValues['member_id']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <span data-custom-select-label>Selecciona socio</span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <input class="custom-select-search" type="search" placeholder="Buscar socio..." data-custom-select-search>
        <?php foreach ($members as $memberOption): ?>
          <?php $memberLabel = trim($memberOption['first_name'] . ' ' . ($memberOption['last_name'] ?? '')); ?>
          <button class="custom-select-option" type="button" data-custom-select-option data-value="<?= e($memberOption['id']) ?>" data-search="<?= e(strtolower($memberLabel . ' ' . ($memberOption['email'] ?? ''))) ?>">
            <?= e($memberLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="field">
    <span>Reserva o clase</span>
    <div class="custom-select custom-select--field" data-custom-select data-checkin-reservation-select>
      <input type="hidden" name="reservation_id" value="" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <span data-custom-select-label>Entrada general sin reserva</span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <input class="custom-select-search" type="search" placeholder="Buscar reserva..." data-custom-select-search>
        <button class="custom-select-option selected" type="button" data-custom-select-option data-value="" data-member-id="" data-search="entrada general sin reserva">Entrada general sin reserva</button>
        <?php foreach ($reservations as $reservationOption): ?>
          <button class="custom-select-option" type="button" data-custom-select-option data-value="<?= e($reservationOption['id']) ?>" data-member-id="<?= e($reservationOption['member_id']) ?>" data-search="<?= e(strtolower($reservationOption['member_name'] . ' ' . $reservationOption['class_name'] . ' ' . format_date_short($reservationOption['starts_at']))) ?>">
            <?= e($reservationOption['member_name']) ?> · <?= e($reservationOption['class_name']) ?> · <?= e(format_date_short($reservationOption['starts_at'])) ?> <?= e(format_time($reservationOption['starts_at'])) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="field">
    <span>Método</span>
    <div class="custom-select custom-select--field" data-custom-select>
      <input type="hidden" name="method" value="<?= e($checkinValues['method']) ?>" data-custom-select-value>
      <button class="custom-select-trigger" type="button" data-custom-select-trigger aria-expanded="false">
        <span data-custom-select-label><?= e($checkinMethodOptions[$checkinValues['method']] ?? 'Manual') ?></span>
      </button>
      <div class="custom-select-menu" data-custom-select-menu hidden>
        <?php foreach ($checkinMethodOptions as $methodValue => $methodLabel): ?>
          <button class="custom-select-option <?= $checkinValues['method'] === $methodValue ? 'selected' : '' ?>" type="button" data-custom-select-option data-value="<?= e($methodValue) ?>">
            <?= e($methodLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <label class="field">
    <span>Fecha</span>
    <input name="checkin_date" type="date" value="<?= e($checkinValues['checkin_date']) ?>">
  </label>

  <label class="field">
    <span>Hora</span>
    <input name="checkin_time" type="time" value="<?= e($checkinValues['checkin_time']) ?>">
  </label>

  <label class="field field--wide">
    <span>Notas</span>
    <textarea name="notes" rows="3" placeholder="Observacion opcional del check-in"><?= e($checkinValues['notes']) ?></textarea>
  </label>
</div>
