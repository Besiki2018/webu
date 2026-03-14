import { test, expect, type Page } from '@playwright/test';
import { ensureAuthenticatedPage } from '../helpers/auth';

const HAS_AUTH = Boolean(
  process.env.PLAYWRIGHT_AUTH_STORAGE_STATE
  || (process.env.TEST_USER_EMAIL && process.env.TEST_USER_PASSWORD)
);
const RUN_V2_ROLLOUT = process.env.WEBU_E2E_RUN_V2_ROLLOUT === '1';
const GENERATION_TIMEOUT_MS = Number.parseInt(process.env.WEBU_E2E_GENERATION_TIMEOUT_MS ?? '180000', 10);

async function openCreatePage(page: Page): Promise<void> {
  await page.goto('/create');

  if (!page.url().includes('/login')) {
    return;
  }

  test.skip(!HAS_AUTH, 'Set TEST_USER_EMAIL/TEST_USER_PASSWORD or PLAYWRIGHT_AUTH_STORAGE_STATE to run rollout E2E.');
  await ensureAuthenticatedPage(page, '/create');
}

async function waitForGenerationReady(page: Page): Promise<void> {
  const generationOverlay = page.locator('[aria-label="Generating your website..."]').first();
  const saveDraft = page.getByRole('button', { name: /save draft|save|დრაფტი შენახვა|შენახვა/i }).first();
  const previewFrame = page.locator('iframe[title="Preview"], iframe[title="პრევიუ"]').first();

  if (await generationOverlay.isVisible().catch(() => false)) {
    await expect(generationOverlay).toBeVisible({ timeout: 10000 });
    await expect(generationOverlay).toBeHidden({ timeout: GENERATION_TIMEOUT_MS });
  }

  await expect(saveDraft.or(previewFrame)).toBeVisible({ timeout: GENERATION_TIMEOUT_MS });
}

async function openVisualBuilder(page: Page): Promise<void> {
  const openBuilder = page.getByRole('button', { name: /visual builder|open visual builder|inspect|ვიზუალური/i }).first();
  await expect(openBuilder).toBeVisible({ timeout: 15000 });
  await openBuilder.click();

  const saveDraft = page.getByRole('button', { name: /save draft|save|დრაფტი შენახვა|შენახვა/i }).first();
  await expect(saveDraft).toBeVisible({ timeout: 15000 });
}

async function openCodeMode(page: Page): Promise<void> {
  const codeTab = page.getByRole('button', { name: /code|კოდი/i }).first();
  await expect(codeTab).toBeVisible({ timeout: 15000 });
  await codeTab.click();
}

test.describe('Webu v2 rollout flow', () => {
  test.skip(!RUN_V2_ROLLOUT, 'Set WEBU_E2E_RUN_V2_ROLLOUT=1 to run the full rollout flow.');

  test('create -> ready -> inspect edit -> preview refresh -> code mode manifest consistency', async ({ page }) => {
    await openCreatePage(page);

    const promptEditor = page.getByRole('textbox').first();
    await expect(promptEditor).toBeVisible({ timeout: 10000 });
    await promptEditor.fill(`Rollout hero ${Date.now()}`);
    await page.getByRole('button', { name: /send message|გაგზავნა/i }).first().click();

    await expect(page).toHaveURL(/\/project\/[^/?]+(?:\?.*)?$/, { timeout: 35000 });
    expect(page.url()).not.toContain('tab=inspect');
    await waitForGenerationReady(page);
    await openVisualBuilder(page);

    const projectMatch = page.url().match(/\/project\/([^/?]+)/);
    expect(projectMatch?.[1]).toBeTruthy();
    const projectId = projectMatch?.[1] as string;

    const structureItems = page.locator('.workspace-floating-structure-item, [data-structure-item-id]');
    const structureCount = await structureItems.count();
    await expect(structureItems.first()).toBeVisible({ timeout: 15000 });
    await structureItems.nth(structureCount > 1 ? 1 : 0).click();

    const updatedText = `Rollout hero ${Date.now()}`;
    const editableField = page.locator('input:not([type="hidden"]), textarea').filter({ hasNot: page.locator('[readonly]') }).first();
    await expect(editableField).toBeVisible({ timeout: 15000 });
    await editableField.fill(updatedText);

    const saveDraft = page.getByRole('button', { name: /save draft|save|დრაფტი შენახვა|შენახვა/i }).first();
    await saveDraft.click();

    const previewFrame = page.frameLocator('iframe[title="Preview"], iframe[title="პრევიუ"]').first();
    await expect(previewFrame.getByText(updatedText).first()).toBeVisible({ timeout: GENERATION_TIMEOUT_MS });

    await openCodeMode(page);
    await expect(page.getByText(/Editable real project files|AI project-edit uses only these files/i).first()).toBeVisible({ timeout: 15000 });

    const manifestResponse = await page.request.get(
      `/panel/projects/${projectId}/workspace/file?path=${encodeURIComponent('.webu/workspace-manifest.json')}`
    );
    expect(manifestResponse.ok()).toBeTruthy();

    const manifestPayload = await manifestResponse.json();
    const manifest = JSON.parse(String(manifestPayload.content ?? '{}')) as {
      preview?: { ready?: boolean };
      fileOwnership?: Array<{ path?: string; lastEditor?: string | null }>;
      generatedPages?: Array<{ pageId?: string | null; slug?: string | null }>;
    };

    expect(manifest.preview?.ready).toBe(true);
    expect(
      manifest.fileOwnership?.some((entry) => entry.path?.includes('src/') && entry.lastEditor === 'visual_builder')
    ).toBeTruthy();
    expect(Array.isArray(manifest.generatedPages) && manifest.generatedPages.length > 0).toBeTruthy();
  });
});
