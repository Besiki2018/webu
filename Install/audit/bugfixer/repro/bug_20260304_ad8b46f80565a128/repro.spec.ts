import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_ad8b46f80565a128 - reuses app flow, targets route */
test.describe('Bug bug_20260304_ad8b46f80565a128', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/admin/plans";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});