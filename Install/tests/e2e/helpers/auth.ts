import { expect, type Page } from '@playwright/test';

const USER_EMAIL = process.env.TEST_USER_EMAIL;
const USER_PASSWORD = process.env.TEST_USER_PASSWORD;

export async function ensureAuthenticatedPage(page: Page, targetUrl: string): Promise<void> {
  await page.goto(targetUrl);
  if (!page.url().includes('/login')) {
    return;
  }

  if (!USER_EMAIL || !USER_PASSWORD) {
    throw new Error('Login required for E2E builder flow. Set TEST_USER_EMAIL and TEST_USER_PASSWORD, or provide PLAYWRIGHT_AUTH_STORAGE_STATE.');
  }

  const dismissCookieBanner = page.getByRole('button', { name: /accept all|essential only/i }).first();
  if (await dismissCookieBanner.isVisible().catch(() => false)) {
    await dismissCookieBanner.click().catch(() => {});
  }

  await page.locator('input[name="email"], input[type="email"]').first().fill(USER_EMAIL);
  await page.locator('input[name="password"], input[type="password"]').first().fill(USER_PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 }),
    page.getByRole('button', { name: /log in|sign in|შესვლა/i }).click(),
  ]);

  await page.goto(targetUrl);
  await expect(page).not.toHaveURL(/\/login/, { timeout: 10000 });
}
