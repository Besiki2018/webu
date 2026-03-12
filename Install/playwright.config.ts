import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E for Webu — Golden Path: Generate Website, CMS Edit, Builder, Publish.
 * Run with: npx playwright test
 * Start app first: npm run start (or php artisan serve)
 */
export default defineConfig({
  testDir: 'tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  outputDir: process.env.RUN_ID
    ? `audit/ci/${process.env.RUN_ID}/playwright`
    : 'test-results',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8001',
    trace: 'on-first-retry',
    ...(process.env.PLAYWRIGHT_AUTH_STORAGE_STATE
      ? { storageState: process.env.PLAYWRIGHT_AUTH_STORAGE_STATE }
      : {}),
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  /* Optional: start server when not already running */
  // webServer: {
  //   command: 'npm run start',
  //   url: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8001',
  //   reuseExistingServer: !process.env.CI,
  // },
});
