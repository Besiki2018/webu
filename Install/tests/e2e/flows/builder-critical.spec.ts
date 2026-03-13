import { test, expect, type Page, type FrameLocator } from '@playwright/test';
import { ensureAuthenticatedPage } from '../helpers/auth';

const PROJECT_ID = process.env.TEST_PROJECT_ID;

function inspectUrl(projectId: string): string {
  return `/project/${projectId}?tab=inspect`;
}

function previewFrame(page: Page): FrameLocator {
  return page.frameLocator('iframe[src*="/themes/"][src*="builder=1"]').first();
}

test.describe('Builder-critical inspect flow', () => {
  test('loads inspect, keeps preview stable, and stays free of bridge spam', async ({ page }) => {
    if (!PROJECT_ID) test.skip();

    await ensureAuthenticatedPage(page, inspectUrl(PROJECT_ID));

    const bridgeLogs: string[] = [];
    page.on('console', (message) => {
      const text = message.text();
      if (text.includes('BuilderBridge')) {
        bridgeLogs.push(text);
      }
    });

    await test.step('loads the active inspect route with preview and sidebar frames', async () => {
      await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}\\?tab=inspect`), { timeout: 15000 });
      await expect(previewFrame(page).locator('body')).toBeVisible({ timeout: 15000 });
      await expect(page.getByRole('button', { name: /save|შენახვა/i }).first()).toBeVisible({ timeout: 15000 });
    });

    await test.step('renders real preview sections without stuck placeholders', async () => {
      const preview = previewFrame(page);
      await expect(preview.locator('[data-webu-section-local-id]').first()).toBeVisible({ timeout: 15000 });
      expect(await preview.locator('[data-webu-section-local-id]').count()).toBeGreaterThan(0);
      await expect(preview.locator('[data-webu-chat-placeholder="true"]')).toHaveCount(0);
    });

    await test.step('keeps the visible sidebar library surface available', async () => {
      await expect(page.getByRole('button', { name: /hero|header|footer|banner|product/i }).first()).toBeVisible({
        timeout: 15000,
      });
    });

    await test.step('preview click keeps the inspect runtime stable', async () => {
      const preview = previewFrame(page);
      await preview.locator('[data-webu-field], [data-webu-field-scope], [data-webu-field-url], [data-webu-section-local-id]').first().click();
      await expect(preview.locator('[data-webu-section-local-id]').first()).toBeVisible({ timeout: 15000 });
      await expect(page.getByRole('button', { name: /save|შენახვა/i }).first()).toBeVisible({ timeout: 15000 });
    });

    await test.step('does not spam BuilderBridge logs while inspect idles', async () => {
      await page.waitForTimeout(2500);
      expect(bridgeLogs.length).toBeLessThanOrEqual(2);
    });
  });
});
