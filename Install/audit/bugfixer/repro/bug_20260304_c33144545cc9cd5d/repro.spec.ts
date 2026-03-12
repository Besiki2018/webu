import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_c33144545cc9cd5d - reuses app flow, targets route */
test.describe('Bug bug_20260304_c33144545cc9cd5d', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});