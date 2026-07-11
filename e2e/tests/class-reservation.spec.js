const { test, expect } = require('@playwright/test');

test('programa una clase y reserva una plaza', async ({ page }) => {
  test.skip(!process.env.E2E_EMAIL || !process.env.E2E_PASSWORD, 'Requiere usuario y BD de prueba');
  await page.goto('/?route=login');
  await page.getByRole('button', { name: 'Demo cliente', exact: true }).click();
  await page.goto('/?route=classes');

  await page.getByRole('button', { name: 'Nueva clase', exact: true }).click();
  const createForm = page.locator('#class-session-modal form');
  const minute = String(Date.now() % 50).padStart(2, '0');
  const endMinute = String(Number(minute) + 5).padStart(2, '0');
  const startTime = `20:${minute}`;
  await createForm.locator('input[name=class_date]').fill(new Date().toISOString().slice(0, 10));
  await createForm.locator('input[name=class_start_time]').fill(startTime);
  await createForm.locator('input[name=class_end_time]').fill(`20:${endMinute}`);
  await createForm.getByRole('button', { name: 'Programar clase', exact: true }).click();
  await expect(page.getByText('Clase programada correctamente.', { exact: true })).toBeVisible();

  const createdRow = page.locator('#classes-table tbody tr').filter({ hasText: startTime });
  await expect(createdRow).toBeVisible();
  await createdRow.getByRole('button', { name: /Editar/ }).click();

  const detailDialog = page.locator('dialog[open]').filter({ has: page.getByRole('heading', { name: /.+/ }) });
  const reservationForm = detailDialog.locator('form.reservation-create-form');
  await reservationForm.locator('input[name=member_id]').first().check();
  await reservationForm.getByRole('button', { name: 'Crear reserva', exact: true }).click();
  await expect(page.getByText('Reserva creada correctamente.', { exact: true })).toBeVisible();
  await expect(page.locator('dialog[open] .reservation-item')).toHaveCount(1);
  await page.locator('dialog[open] .reservation-item').scrollIntoViewIfNeeded();
  await page.screenshot({ path: 'evidence/clase-reserva.png', fullPage: true });
});
