import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_b17a3f70cfb06d5a - reuses app flow, targets route */
test.describe('Bug bug_20260304_b17a3f70cfb06d5a', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/projects";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});