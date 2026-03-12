import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_65ef8f2ac2aecb5d - reuses app flow, targets route */
test.describe('Bug bug_20260304_65ef8f2ac2aecb5d', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/admin/users";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});