import { test, expect, type Page, type FrameLocator } from '@playwright/test';
import { ensureAuthenticatedPage } from '../helpers/auth';

const PROJECT_ID = process.env.TEST_PROJECT_ID;

test.describe.configure({ mode: 'serial' });

function inspectUrl(projectId: string): string {
  return `/project/${projectId}?tab=inspect`;
}

function previewFrame(page: Page): FrameLocator {
  return page.frameLocator('iframe[src*="/themes/"][src*="builder=1"]').first();
}

test.describe('Builder-critical inspect flow', () => {
  test.beforeEach(async ({ page }) => {
    if (!PROJECT_ID) {
      test.skip();
      return;
    }

    await ensureAuthenticatedPage(page, inspectUrl(PROJECT_ID));
  });

  test('loads the active inspect route with preview and sidebar frames', async ({ page }) => {
    if (!PROJECT_ID) test.skip();

    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}\\?tab=inspect`), { timeout: 15000 });
    await expect(previewFrame(page).locator('body')).toBeVisible({ timeout: 15000 });
    await expect(page.getByRole('button', { name: /save|შენახვა/i }).first()).toBeVisible({ timeout: 15000 });
  });

  test('renders real preview sections without stuck placeholders', async ({ page }) => {
    if (!PROJECT_ID) test.skip();

    const preview = previewFrame(page);
    await expect(preview.locator('[data-webu-section-local-id]').first()).toBeVisible({ timeout: 15000 });
    expect(await preview.locator('[data-webu-section-local-id]').count()).toBeGreaterThan(0);
    await expect(preview.locator('[data-webu-chat-placeholder="true"]')).toHaveCount(0);
  });

  test('keeps the visible sidebar library surface available', async ({ page }) => {
    if (!PROJECT_ID) test.skip();

    await expect(page.getByRole('button', { name: /hero|header|footer|banner|product/i }).first()).toBeVisible({
      timeout: 15000,
    });
  });

  test('preview click keeps the inspect runtime stable', async ({ page }) => {
    if (!PROJECT_ID) test.skip();

    const preview = previewFrame(page);
    await preview.locator('[data-webu-field], [data-webu-field-scope], [data-webu-field-url], [data-webu-section-local-id]').first().click();
    await expect(preview.locator('[data-webu-section-local-id]').first()).toBeVisible({ timeout: 15000 });
    await expect(page.getByRole('button', { name: /save|შენახვა/i }).first()).toBeVisible({ timeout: 15000 });
  });

  test('does not spam BuilderBridge logs while inspect idles', async ({ page }) => {
    if (!PROJECT_ID) test.skip();

    const bridgeLogs: string[] = [];
    page.on('console', (message) => {
      const text = message.text();
      if (text.includes('BuilderBridge')) {
        bridgeLogs.push(text);
      }
    });

    await page.goto(inspectUrl(PROJECT_ID));
    await expect(previewFrame(page).locator('body')).toBeVisible({ timeout: 15000 });
    await page.waitForTimeout(2500);

    expect(bridgeLogs.length).toBeLessThanOrEqual(2);
  });
});
