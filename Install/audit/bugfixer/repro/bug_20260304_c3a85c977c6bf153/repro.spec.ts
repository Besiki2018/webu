import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_c3a85c977c6bf153 - reuses app flow, targets route */
test.describe('Bug bug_20260304_c3a85c977c6bf153', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/create";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});