import { test, expect } from '@playwright/test';

/**
 * Golden Path E2E stub: Create page and generate-website entry.
 * Full flow (Generate Website → CMS Edit → Builder → Publish) can be extended here.
 * Requires app running: npm run start (or php artisan serve).
 */

test.describe('Golden path', () => {
  test('Create page loads and shows prompt / quick generate option', async ({ page }) => {
    await page.goto('/create');
    await expect(page).toHaveURL(/\/create/);
    // Prompt area or greeting
    const promptOrGreeting = page.getByRole('textbox', { name: /build|want|project/i }).or(
      page.locator('textarea')
    );
    await expect(promptOrGreeting.first()).toBeVisible({ timeout: 10000 });
  });

  test('Generate website (CMS-first) link appears when prompt is entered', async ({ page }) => {
    await page.goto('/create');
    const textarea = page.locator('textarea').first();
    await textarea.fill('Small coffee shop website');
    await expect(textarea).toHaveValue('Small coffee shop website');
    // Link "Or generate website with AI (instant, CMS-first)"
    const quickGenerate = page.getByText(/generate website with AI|instant.*CMS-first/i);
    await expect(quickGenerate).toBeVisible({ timeout: 5000 });
  });
});

test.describe('Ultra Cheap Mode', () => {
  test('Profile AI settings shows Ultra Cheap Mode toggle', async ({ page }) => {
    await page.goto('/profile');
    await expect(page).toHaveURL(/\/(login|profile)/);
    if (page.url().includes('login')) {
      test.skip();
      return;
    }
    const ultraCheap = page.getByText(/Ultra Cheap Mode|Website generation/i);
    await expect(ultraCheap.first()).toBeVisible({ timeout: 10000 });
  });
});
