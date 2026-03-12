import { test, expect } from '@playwright/test';
/** Repro: bug_20260305_12b34e55ec9671fa - reuses app flow, targets route */
test.describe('Bug bug_20260305_12b34e55ec9671fa', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});