const { test, expect } = require('@playwright/test');
test('rechaza credenciales incorrectas', async ({ page }) => {
  await page.goto('/?route=login');
  await page.locator('input[name=email]').fill('nadie@example.test');
  await page.locator('input[name=password]').fill('incorrecta');
  await page.getByRole('button', { name: 'Iniciar sesion', exact: true }).click();
  await expect(page).toHaveURL(/route=login/);
});
test('inicia sesión con credenciales de prueba', async ({ page }) => {
  test.skip(!process.env.E2E_EMAIL || !process.env.E2E_PASSWORD, 'Configura E2E_EMAIL y E2E_PASSWORD');
  await page.goto('/?route=login');
  await page.getByRole('button', { name: 'Demo cliente', exact: true }).click();
  await expect(page).not.toHaveURL(/route=login/);
});
