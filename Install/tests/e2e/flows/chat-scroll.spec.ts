import { test, expect } from '@playwright/test';

/**
 * Chat scroll behavior regression E2E.
 * Scenario 1: After full page refresh, chat must reopen at latest message (bottom).
 * Scenario 2: When user is at bottom, new assistant reply must keep viewport pinned to bottom.
 * Scenario 3: When user has scrolled upward manually, new messages must NOT force-scroll to bottom.
 *
 * Requires: TEST_PROJECT_ID, optionally TEST_USER_EMAIL/TEST_USER_PASSWORD
 * Project should have conversation history with multiple messages.
 */
const PROJECT_ID = process.env.TEST_PROJECT_ID;
const USER_EMAIL = process.env.TEST_USER_EMAIL;
const USER_PASSWORD = process.env.TEST_USER_PASSWORD;

test.describe('Chat scroll behavior', () => {
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

  test('1. Full page refresh reopens chat at latest message (bottom)', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const chatViewport = page.locator('[data-slot="scroll-area-viewport"]').first();
    await expect(chatViewport).toBeVisible({ timeout: 10000 });
    const scrollEnd = page.locator('.workspace-message-row').last();
    await expect(scrollEnd.first()).toBeVisible({ timeout: 8000 });
    await page.waitForTimeout(800);
    const scrollTop = await chatViewport.evaluate((el) => el.scrollTop);
    const scrollHeight = await chatViewport.evaluate((el) => el.scrollHeight);
    const clientHeight = await chatViewport.evaluate((el) => el.clientHeight);
    const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
    expect(distanceFromBottom).toBeLessThan(100);
  });

  test('2. User at bottom - new assistant reply keeps viewport pinned to bottom', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const input = page.locator('textarea').or(page.getByRole('textbox')).first();
    await expect(input).toBeVisible({ timeout: 10000 });
    const initialMessageCount = await page.locator('.workspace-message-row').count();
    await input.fill('Say hello in one word');
    await page.getByRole('button', { name: /send|submit/i }).first().click();
    await page.waitForTimeout(2000);
    const chatViewport = page.locator('[data-slot="scroll-area-viewport"]').first();
    await expect(chatViewport).toBeVisible({ timeout: 5000 });
    await page.waitForTimeout(1500);
    const scrollTop = await chatViewport.evaluate((el) => el.scrollTop);
    const scrollHeight = await chatViewport.evaluate((el) => el.scrollHeight);
    const clientHeight = await chatViewport.evaluate((el) => el.clientHeight);
    const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
    expect(distanceFromBottom).toBeLessThan(100);
  });

  test('3. User scrolled up - new messages do not force-scroll to bottom', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const chatViewport = page.locator('[data-slot="scroll-area-viewport"]').first();
    await expect(chatViewport).toBeVisible({ timeout: 10000 });
    await chatViewport.evaluate((el) => el.scrollTo({ top: 0, behavior: 'auto' }));
    await page.waitForTimeout(300);
    const scrollTopBefore = await chatViewport.evaluate((el) => el.scrollTop);
    expect(scrollTopBefore).toBeLessThan(50);
    const input = page.locator('textarea').or(page.getByRole('textbox')).first();
    await input.fill('Reply with just: ok');
    await page.getByRole('button', { name: /send|submit/i }).first().click();
    await page.waitForTimeout(2500);
    const scrollTopAfter = await chatViewport.evaluate((el) => el.scrollTop);
    expect(scrollTopAfter).toBeLessThan(100);
  });
});
