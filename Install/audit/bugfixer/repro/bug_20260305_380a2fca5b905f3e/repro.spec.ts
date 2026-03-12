import { test, expect } from '@playwright/test';
/** Repro: bug_20260305_380a2fca5b905f3e - reuses app flow, targets route */
test.describe('Bug bug_20260305_380a2fca5b905f3e', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/project/019cbb52-19ff-7345-b9d7-c28bf5061311";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});