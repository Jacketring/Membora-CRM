<?php
$isEditingClient = isset($client) && is_array($client);
$clientValues = $isEditingClient ? $client : [
    'id' => '',
    'company_name' => '',
    'contact_name' => '',
    'email' => '',
    'phone' => '',
    'status' => 'LEAD',
    'notes' => '',
];
$clientStatusOptions = [
    'LEAD' => 'Lead',
    'QUALIFIED' => 'Cualificado',
    'CUSTOMER' => 'Cliente',
    'LOST' => 'Perdido',
];
?>

<form class="empresa-form" method="post">
  <input type="hidden" name="action" value="<?= $isEditingClient ? 'update_platform_client' : 'create_platform_client' ?>">
  <?php if ($isEditingClient): ?>
    <input type="hidden" name="id" value="<?= e($clientValues['id']) ?>">
  <?php endif; ?>

  <label class="field">
    <span>Empresa</span>
    <input name="company_name" required value="<?= e($clientValues['company_name']) ?>" placeholder="NexoFit Studio">
  </label>
  <label class="field">
    <span>Persona de contacto</span>
    <input name="contact_name" value="<?= e($clientValues['contact_name']) ?>" placeholder="Laura Martin">
  </label>
  <label class="field">
    <span>Email</span>
    <input name="email" type="email" value="<?= e($clientValues['email']) ?>" placeholder="admin@empresa.com">
  </label>
  <label class="field">
    <span>Telefono</span>
    <input name="phone" value="<?= e($clientValues['phone']) ?>" placeholder="+34 600 000 000">
  </label>
  <label class="field">
    <span>Estado</span>
    <select name="status">
      <?php foreach ($clientStatusOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $clientValues['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field form-full">
    <span>Notas</span>
    <textarea name="notes" rows="4" placeholder="Necesidad, precio hablado, objeciones, siguiente paso..."><?= e($clientValues['notes']) ?></textarea>
  </label>

  <div class="form-actions form-full">
    <button class="secondary-action" type="button" data-close-modal>Cancelar</button>
    <button class="primary-action" type="submit"><?= $isEditingClient ? 'Guardar contacto' : 'Crear contacto' ?></button>
  </div>
</form>
