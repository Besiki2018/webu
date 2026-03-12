import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_57c8d300dadd69ff - reuses app flow, targets route */
test.describe('Bug bug_20260304_57c8d300dadd69ff', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/create";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});