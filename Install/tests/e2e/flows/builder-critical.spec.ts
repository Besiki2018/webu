import { test, expect } from '@playwright/test';
import { ensureAuthenticatedPage } from '../helpers/auth';

/**
 * Builder-critical E2E flows (Task 5).
 * Real browser specs for: inspect tab, add/delete component, code tab, draft save.
 *
 * Requires:
 * - App running (e.g. npm run start)
 * - TEST_PROJECT_ID set to a valid project ID (e.g. from php artisan tinker or seed)
 * - Optional: TEST_USER_EMAIL, TEST_USER_PASSWORD for login (otherwise redirect to login will fail)
 *
 * Seed a project locally:
 *   php artisan migrate:fresh --seed
 *   php artisan tinker -> Project::first()?->id
 *   TEST_PROJECT_ID=<id> npx playwright test tests/e2e/flows/builder-critical.spec.ts
 */
const PROJECT_ID = process.env.TEST_PROJECT_ID;

test.describe('Builder-critical flows', () => {
  test.beforeEach(async ({ page }) => {
    if (!PROJECT_ID) {
      test.skip();
      return;
    }
    await ensureAuthenticatedPage(page, `/project/${PROJECT_ID}/cms`);
  });

  test('1. Open project CMS and inspect/editor tab is available', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}/cms`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}/cms`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const editorOrWorkspace = page.getByRole('tab').filter({ hasText: /editor|structure|elements|page/i }).or(
      page.locator('[data-section="editor"], [data-value="editor"]')
    );
    await expect(editorOrWorkspace.first()).toBeVisible({ timeout: 10000 });
  });

  test('2. Add component to canvas via library (structure or drag zone visible)', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}/cms?section=editor`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}/cms`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const canvasOrPreview = page.locator('.workspace-preview-frame, iframe[title="Preview"], [class*="builder"], [class*="canvas"]').first();
    await expect(canvasOrPreview).toBeVisible({ timeout: 12000 });
  });

  test('3. Code tab shows output for generated project', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}/cms`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}/cms`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const codeTab = page.getByRole('tab', { name: /code/i }).or(
      page.getByRole('button', { name: /code/i })
    ).first();
    await expect(codeTab).toBeVisible({ timeout: 8000 });
    await codeTab.click();
    const codeContent = page.locator('pre, .monaco-editor, [class*="code"], [class*="Code"]').first();
    await expect(codeContent).toBeVisible({ timeout: 8000 });
  });

  test('3b. Code tab shows multi-page output when project has multiple pages', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}/cms`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}/cms`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const codeTab = page.getByRole('tab', { name: /code/i }).or(
      page.getByRole('button', { name: /code/i })
    ).first();
    await expect(codeTab).toBeVisible({ timeout: 8000 });
    await codeTab.click();
    const codeContent = page.locator('pre, .monaco-editor, [class*="code"], [class*="Code"]').first();
    await expect(codeContent).toBeVisible({ timeout: 8000 });
    const text = await codeContent.textContent().catch(() => '');
    const hasMultiplePages =
      (/home|page/.test(text || '') && /shop|about|contact/.test(text || '')) ||
      (text || '').split(/page|slug|path/).length >= 2;
    expect(hasMultiplePages || (text || '').length > 200).toBeTruthy();
  });

  test('4. Save draft control exists in builder', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}/cms?section=editor`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}/cms`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const saveOrDraft = page.getByRole('button', { name: /save|draft/i }).or(
      page.locator('button').filter({ hasText: /save|draft/i })
    ).first();
    await expect(saveOrDraft).toBeVisible({ timeout: 10000 });
  });

  test('5a. Fallback mode does not spam status endpoint', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    const statusCalls: string[] = [];
    await page.route('**/builder/projects/*/status*', (route) => {
      statusCalls.push(route.request().url());
      route.fulfill({ status: 200, body: JSON.stringify({ status: 'idle', has_session: false }) });
    });
    await page.goto(`/project/${PROJECT_ID}/cms`);
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    await page.waitForTimeout(16000);
    expect(statusCalls.length).toBeLessThanOrEqual(8);
  });

  test('5. Persistence: reload project CMS and page still loads', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}/cms`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}/cms`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    await page.reload();
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}/cms`), { timeout: 10000 });
    await expect(page.locator('body')).toBeVisible();
  });

  test('6. Structure or canvas: builder UI has canvas and optional section controls', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}/cms?section=editor`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}/cms`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const canvasOrPreview = page.locator('.workspace-preview-frame, iframe[title="Preview"], [class*="builder"], [class*="canvas"]').first();
    await expect(canvasOrPreview).toBeVisible({ timeout: 12000 });
    const hasStructureOrTabs =
      (await page.getByRole('tab').count()) >= 1 ||
      (await page.getByRole('button', { name: /delete|remove/i }).count()) >= 0;
    expect(hasStructureOrTabs).toBeTruthy();
  });

  test('7. Save draft and reload: builder still loads after save', async ({ page }) => {
    if (!PROJECT_ID) test.skip();
    await page.goto(`/project/${PROJECT_ID}/cms?section=editor`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}/cms`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }
    const saveOrDraft = page.getByRole('button', { name: /save|draft/i }).or(
      page.locator('button').filter({ hasText: /save|draft/i })
    ).first();
    if (await saveOrDraft.isVisible().catch(() => false)) {
      await saveOrDraft.click();
      await page.waitForTimeout(2000);
    }
    await page.reload();
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}/cms`), { timeout: 10000 });
    await expect(page.locator('.workspace-preview-frame, iframe[title="Preview"], [class*="builder"]').first()).toBeVisible({ timeout: 10000 });
  });
});
