import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_b71d637d839addd8 - reuses app flow, targets route */
test.describe('Bug bug_20260304_b71d637d839addd8', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/admin/settings?tab=integrations";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});