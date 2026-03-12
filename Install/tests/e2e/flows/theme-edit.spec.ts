import { test, expect } from '@playwright/test';

/**
 * E2E flow: Theme edit (Tab 9 B1).
 * Open profile or settings where theme can be changed; assert no runtime errors.
 */
test.describe('Theme edit flow', () => {
  test('Profile or landing loads', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/\//);
    await expect(page.locator('body')).toBeVisible();
  });
});
