import { test, expect } from '@playwright/test';
/** Repro: bug_20260306_d76d87bfba219f9e - reuses app flow, targets route */
test.describe('Bug bug_20260306_d76d87bfba219f9e', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/project/019cbce1-c7f2-700b-a288-39c1397479bc?tab=inspect";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});