import { test, expect } from '@playwright/test';

/**
 * E2E flow: Template select (Tab 9 B1).
 * Open app, reach template selection, assert critical UI exists.
 */
test.describe('Template select flow', () => {
  test('Create page loads and shows template or prompt area', async ({ page }) => {
    await page.goto('/create');
    await expect(page).toHaveURL(/\/create/);
    await expect(page.locator('textarea').or(page.getByRole('textbox')).first()).toBeVisible({ timeout: 10000 });
  });
});
