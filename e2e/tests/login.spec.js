const { test, expect } = require('@playwright/test');

async function login(page) {
  await page.goto('/?route=login');
  await page.locator('input[name=email]').fill(process.env.E2E_EMAIL);
  await page.locator('input[name=password]').fill(process.env.E2E_PASSWORD);
  await page.getByRole('button', { name: /iniciar sesi[oó]n/i }).click();
}

test('carga la pantalla de login y rechaza credenciales incorrectas', async ({ page }) => {
  await page.goto('/?route=login');
  await expect(page.locator('input[name=email]')).toBeVisible();
  await page.locator('input[name=email]').fill('nadie@example.test');
  await page.locator('input[name=password]').fill('incorrecta');
  await page.getByRole('button', { name: /iniciar sesi[oó]n/i }).click();
  await expect(page).toHaveURL(/route=login/);
});

test('inicia sesión y navega por el menú principal', async ({ page }) => {
  test.skip(!process.env.E2E_EMAIL || !process.env.E2E_PASSWORD, 'Configura las credenciales E2E');
  await login(page);
  await expect(page).not.toHaveURL(/route=login/);
  await page.goto('/?route=members');
  await expect(page).toHaveURL(/route=members/);
});

test('un usuario de gimnasio no accede a rutas de plataforma', async ({ page }) => {
  test.skip(!process.env.E2E_EMAIL || !process.env.E2E_PASSWORD, 'Requiere un GYM_ADMIN de pruebas');
  await login(page);
  await page.goto('/?route=platform-dashboard');
  await expect(page).not.toHaveURL(/route=platform-dashboard/);
});

test('visualiza una factura y activa la impresión sin errores JavaScript', async ({ page }) => {
  test.skip(!process.env.E2E_INVOICE_URL, 'Configura E2E_INVOICE_URL con una factura de pruebas existente');
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.addInitScript(() => {
    window.__printCalled = false;
    window.print = () => { window.__printCalled = true; };
  });
  await login(page);
  await page.goto(process.env.E2E_INVOICE_URL);
  const printButton = page.locator('.js-print-invoice');
  await expect(printButton).toBeVisible();
  await printButton.click();
  await expect.poll(() => page.evaluate(() => window.__printCalled)).toBe(true);
  expect(errors).toEqual([]);
});
