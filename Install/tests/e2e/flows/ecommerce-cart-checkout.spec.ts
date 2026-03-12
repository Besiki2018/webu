import { test, expect } from '@playwright/test';

/**
 * E2E flow: E-commerce cart/checkout (Tab 9 B1).
 * When project has e-commerce, open storefront and assert cart/checkout UI exists or page loads.
 */
test.describe('E-commerce cart checkout flow', () => {
  test('App root loads', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible();
  });
});
