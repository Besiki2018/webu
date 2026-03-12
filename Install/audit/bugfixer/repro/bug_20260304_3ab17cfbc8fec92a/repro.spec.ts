import { test, expect } from '@playwright/test';
/** Repro: bug_20260304_3ab17cfbc8fec92a - reuses app flow, targets route */
test.describe('Bug bug_20260304_3ab17cfbc8fec92a', () => {
  test('reproduce error on route', async ({ page }) => {
    const route = "/project/019cbabc-c8c2-70ee-96a6-4606aa0e768f/cms?tab=cms-entries";
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(route.replace(/\//g, '\\/')));
    // Add steps that trigger the error (see instructions.md)
  });
});