import { test, expect } from '@playwright/test';

/**
 * E2E flow: Add section by chat (Tab 9 B1).
 * Requires project context; minimal: open create or project and assert chat/editor exists.
 */
test.describe('Add section by chat flow', () => {
  test('Create page has chat or prompt input', async ({ page }) => {
    await page.goto('/create');
    await expect(page).toHaveURL(/\/create/);
    const input = page.locator('textarea').or(page.getByRole('textbox')).first();
    await expect(input).toBeVisible({ timeout: 10000 });
  });

  test('Prompt area accepts text and submit or send control is present', async ({ page }) => {
    await page.goto('/create');
    await expect(page).toHaveURL(/\/create/);
    const input = page.locator('textarea').or(page.getByRole('textbox')).first();
    await input.fill('Add a hero section');
    await expect(input).toHaveValue('Add a hero section');
    const sendOrSubmit = page.getByRole('button', { name: /send|submit|generate/i }).or(
      page.locator('button[type="submit"]')
    );
    await expect(sendOrSubmit.first()).toBeVisible({ timeout: 5000 });
  });
});
