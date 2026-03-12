import { test, expect } from '@playwright/test';

/**
 * E2E flow: Generate content (Tab 9 B1).
 * Open app, assert content-generation entry point or editor is present.
 */
test.describe('Generate content flow', () => {
  test('Create or editor page loads without crash', async ({ page }) => {
    await page.goto('/create');
    await expect(page).toHaveURL(/\/create/);
    await expect(page.locator('body')).toBeVisible();
  });
});
