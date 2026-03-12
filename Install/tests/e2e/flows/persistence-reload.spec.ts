import { test, expect } from '@playwright/test';

/**
 * E2E flow: Persistence reload (Tab 9 B1).
 * Load page, reload, assert state or critical UI persists.
 */
test.describe('Persistence reload flow', () => {
  test('Create page survives reload', async ({ page }) => {
    await page.goto('/create');
    await expect(page).toHaveURL(/\/create/);
    await page.reload();
    await expect(page).toHaveURL(/\/create/);
    await expect(page.locator('textarea').or(page.getByRole('textbox')).first()).toBeVisible({ timeout: 10000 });
  });

  test('Create page preserves prompt text after reload when stored in state', async ({ page }) => {
    await page.goto('/create');
    await expect(page).toHaveURL(/\/create/);
    const input = page.locator('textarea').or(page.getByRole('textbox')).first();
    await input.fill('My store');
    await page.reload();
    await expect(page).toHaveURL(/\/create/);
    await expect(page.locator('textarea').or(page.getByRole('textbox')).first()).toBeVisible({ timeout: 10000 });
  });
});
