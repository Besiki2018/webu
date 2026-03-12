import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_11cb56da3d3e31ca - reuses app flow, targets route */
test.describe('Bug bug_20260304_11cb56da3d3e31ca', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/create";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});