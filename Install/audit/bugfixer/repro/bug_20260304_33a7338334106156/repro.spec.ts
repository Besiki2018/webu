import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_33a7338334106156 - reuses app flow, targets route */
test.describe('Bug bug_20260304_33a7338334106156', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/projects";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});