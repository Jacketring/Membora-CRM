const { defineConfig, devices } = require('@playwright/test');
if (!process.env.E2E_BASE_URL) {
  throw new Error('E2E_BASE_URL is required. Use an isolated local or staging environment, never production.');
}
module.exports = defineConfig({
  testDir: './tests', timeout: 30_000, retries: process.env.CI ? 2 : 0, workers: 1,
  use: { baseURL: process.env.E2E_BASE_URL, trace: 'on-first-retry' },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
