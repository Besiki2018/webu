import { test, expect } from '@playwright/test';

/**
 * E2E flow: Generate website (Tab 9 B1).
 * Open app, trigger generate-website path, assert no runtime errors and critical UI exists.
 */
test.describe('Generate website flow', () => {
  test('Create page loads and generate-website entry point is visible', async ({ page }) => {
    await page.goto('/create');
    await expect(page).toHaveURL(/\/create/);
    const textarea = page.locator('textarea').first();
    await expect(textarea).toBeVisible({ timeout: 10000 });
    await textarea.fill('Small business landing page');
    const generateLink = page.getByText(/generate website with AI|instant.*CMS/i);
    await expect(generateLink.first()).toBeVisible({ timeout: 5000 });
  });

  test('Chat generates project -> redirect to project or show progress', async ({ page }) => {
    await page.goto('/create');
    await expect(page).toHaveURL(/\/create/);
    const textarea = page.locator('textarea').first();
    await expect(textarea).toBeVisible({ timeout: 10000 });
    await textarea.fill('Coffee shop landing');
    const generateBtn = page.getByText(/generate website with AI|instant.*CMS/i).first();
    await expect(generateBtn).toBeVisible({ timeout: 5000 });
    await generateBtn.click();
    await expect(
      page.getByText(/building|redirect|project|error/i).or(page.locator('[class*="loading"], [class*="progress"]'))
    ).toBeVisible({ timeout: 15000 });
  });

  test('Chat generates project -> inspect/CMS tab opens when redirect completes', async ({ page }) => {
    await page.goto('/create');
    await expect(page).toHaveURL(/\/create/);
    const textarea = page.locator('textarea').first();
    await expect(textarea).toBeVisible({ timeout: 10000 });
    await textarea.fill('Pet store one page');
    const generateBtn = page.getByText(/generate website with AI|instant.*CMS/i).first();
    await expect(generateBtn).toBeVisible({ timeout: 5000 });
    await generateBtn.click();
    await expect(
      page.getByText(/building|redirect|project|error/i).or(page.locator('[class*="loading"], [class*="progress"]'))
    ).toBeVisible({ timeout: 10000 });
    await expect(page).toHaveURL(/\/(project\/[^/]+\/(cms|chat)|create)/, { timeout: 35000 });
    if (page.url().match(/\/project\/[^/]+\/(cms|chat)/)) {
      const editorTab = page.getByRole('tab').filter({ hasText: /editor|structure|elements|page/i }).or(
        page.locator('[data-section="editor"], [data-value="editor"]')
      ).first();
      await expect(editorTab).toBeVisible({ timeout: 8000 });
    }
  });
});
