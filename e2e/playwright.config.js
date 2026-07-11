const { defineConfig, devices } = require('@playwright/test');
module.exports = defineConfig({
  testDir: './tests', timeout: 30_000, retries: process.env.CI ? 2 : 0, workers: 1,
  use: { baseURL: process.env.E2E_BASE_URL || 'http://localhost:8000', trace: 'on-first-retry' },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
