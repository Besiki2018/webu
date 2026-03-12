import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_22030795c69d65af - reuses app flow, targets route */
test.describe('Bug bug_20260304_22030795c69d65af', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/projects";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});