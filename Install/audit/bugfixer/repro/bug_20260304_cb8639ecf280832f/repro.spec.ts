import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_cb8639ecf280832f - reuses app flow, targets route */
test.describe('Bug bug_20260304_cb8639ecf280832f', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/profile";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});