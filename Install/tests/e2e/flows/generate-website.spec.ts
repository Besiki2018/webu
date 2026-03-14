import { test, expect, type Page } from '@playwright/test';
import { ensureAuthenticatedPage } from '../helpers/auth';

const HAS_AUTH = Boolean(
  process.env.PLAYWRIGHT_AUTH_STORAGE_STATE
  || (process.env.TEST_USER_EMAIL && process.env.TEST_USER_PASSWORD)
);

async function openCreatePage(page: Page): Promise<void> {
  await page.goto('/create');

  if (!page.url().includes('/login')) {
    return;
  }

  test.skip(!HAS_AUTH, 'Set TEST_USER_EMAIL/TEST_USER_PASSWORD or PLAYWRIGHT_AUTH_STORAGE_STATE to run create-flow E2E.');
  await ensureAuthenticatedPage(page, '/create');
}

async function fillCreatePrompt(page: Page, prompt: string): Promise<void> {
  const promptEditor = page.getByRole('textbox').first();
  await expect(promptEditor).toBeVisible({ timeout: 10000 });
  await promptEditor.fill(prompt);
}

async function clickGenerate(page: Page): Promise<void> {
  const submit = page.getByRole('button', { name: /send message|გაგზავნა/i }).first();
  await expect(submit).toBeVisible({ timeout: 10000 });
  await submit.click();
}

test.describe('Generate website flow', () => {
  test('Create page loads for an authenticated user', async ({ page }) => {
    await openCreatePage(page);
    await expect(page).toHaveURL(/\/create/, { timeout: 10000 });
    await expect(page.getByRole('textbox').first()).toBeVisible({ timeout: 10000 });
  });

  test('Create redirects to the chat workspace before inspect mode is unlocked', async ({ page }) => {
    await openCreatePage(page);
    await fillCreatePrompt(page, `Coffee shop landing ${Date.now()}`);
    await clickGenerate(page);

    await expect(page).toHaveURL(/\/project\/[^/?]+(?:\?.*)?$/, { timeout: 35000 });
    expect(page.url()).not.toContain('tab=inspect');
  });

  test('Preview stays unmounted while generation overlay is visible', async ({ page }) => {
    await openCreatePage(page);
    await fillCreatePrompt(page, `Pet store landing ${Date.now()}`);
    await clickGenerate(page);

    await expect(page).toHaveURL(/\/project\/[^/?]+(?:\?.*)?$/, { timeout: 35000 });
    expect(page.url()).not.toContain('tab=inspect');

    const generationOverlay = page.locator('[aria-label="Generating your website..."]').first();
    const previewFrame = page.locator('iframe[src*="/themes/"][src*="draft=1"]').first();

    if (await generationOverlay.isVisible().catch(() => false)) {
      await expect(generationOverlay).toBeVisible({ timeout: 10000 });
      await expect(previewFrame).toHaveCount(0);
      return;
    }

    await expect(
      previewFrame.or(page.getByText(/Website generation failed/i)).or(page.getByRole('button', { name: /save|publish|undo/i }).first())
    ).toBeVisible({ timeout: 15000 });
  });
});
