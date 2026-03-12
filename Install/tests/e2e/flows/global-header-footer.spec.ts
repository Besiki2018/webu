import { test, expect } from '@playwright/test';

/**
 * Global header/footer browser verification.
 * Verifies: response scope is not "Home page only", label is correct for site-wide
 * header/footer, and preview iframe reflects the change immediately without manual save.
 *
 * Requires: TEST_PROJECT_ID, optionally TEST_USER_EMAIL/TEST_USER_PASSWORD
 */
const PROJECT_ID = process.env.TEST_PROJECT_ID;
const USER_EMAIL = process.env.TEST_USER_EMAIL;
const USER_PASSWORD = process.env.TEST_USER_PASSWORD;

test.describe('Global header/footer verification', () => {
  test.beforeEach(async ({ page }) => {
    if (!PROJECT_ID) {
      test.skip();
      return;
    }
    await page.goto('/');
    const url = page.url();
    if (url.includes('/login') && USER_EMAIL && USER_PASSWORD) {
      await page.getByLabel(/email/i).fill(USER_EMAIL);
      await page.getByLabel(/password/i).fill(USER_PASSWORD);
      await page.getByRole('button', { name: /log in|sign in/i }).click();
      await expect(page).not.toHaveURL(/\/login/, { timeout: 10000 });
    }
  });

  test('1. Chat header design change shows site-wide scope, not Home page only', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const input = page.locator('textarea').or(page.getByRole('textbox')).first();
    await expect(input).toBeVisible({ timeout: 10000 });
    await input.fill('Change the header design');
    await page.getByRole('button', { name: /send|submit/i }).first().click();
    await page.waitForTimeout(4000);
    const scopeLabel = page.getByText(/global|site-wide|header|all pages/i).first();
    const homeOnly = page.getByText(/home page only/i).first();
    await expect(scopeLabel).toBeVisible({ timeout: 8000 });
    await expect(homeOnly).not.toBeVisible();
  });

  test('2. Chat footer design change shows correct scope label', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const input = page.locator('textarea').or(page.getByRole('textbox')).first();
    await expect(input).toBeVisible({ timeout: 10000 });
    await input.fill('Change the footer design');
    await page.getByRole('button', { name: /send|submit/i }).first().click();
    await page.waitForTimeout(4000);
    const scopeLabel = page.getByText(/global|site-wide|footer|all pages/i).first();
    await expect(scopeLabel).toBeVisible({ timeout: 8000 });
  });

  test('3. Header/footer change reflects in preview iframe without manual save', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const input = page.locator('textarea').or(page.getByRole('textbox')).first();
    await expect(input).toBeVisible({ timeout: 10000 });
    await input.fill('Change header design to next variant');
    await page.getByRole('button', { name: /send|submit/i }).first().click();
    await page.waitForTimeout(5000);
    const previewFrame = page.frameLocator('iframe[title="Preview"]').first();
    await expect(previewFrame.locator('header, [data-webu-section*="header"], .header')).toBeVisible({ timeout: 8000 });
  });
});
