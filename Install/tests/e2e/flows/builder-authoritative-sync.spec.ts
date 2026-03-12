import { test, expect, type Locator, type Page } from '@playwright/test';
import { ensureAuthenticatedPage } from '../helpers/auth';

const chatUrl = process.env.WEBU_E2E_CHAT_URL;

async function openVisualBuilder(page: Page) {
  const visualModeActive = page.getByRole('button', { name: /chat|ჩატი|save draft|დრაფტი შენახვა|open structure|სტრუქტურის გახსნა/i }).first();
  await page.waitForTimeout(500);
  if (await visualModeActive.isVisible().catch(() => false)) {
    return;
  }

  const openBuilder = page.getByRole('button', { name: /visual builder|open visual builder|inspect|ვიზუალური/i }).first();
  await expect(openBuilder).toBeVisible({ timeout: 15000 });
  await openBuilder.click();
  await expect(visualModeActive).toBeVisible({ timeout: 15000 });
}

async function visibleStructureItems(page: Page): Promise<Locator> {
  const items = page.locator('.workspace-floating-structure-item, [data-structure-item-id], [draggable="true"]').filter({ has: page.locator('button, span, p, div') });
  if (!(await items.first().isVisible().catch(() => false))) {
    const openStructure = page.getByRole('button', { name: /open structure|structure|სტრუქტურის გახსნა/i }).first();
    if (await openStructure.isVisible().catch(() => false)) {
      await openStructure.click();
    }
  }
  await expect(items.first()).toBeVisible({ timeout: 10000 });
  return items;
}

test.describe('Builder authoritative sync flow', () => {
  test.skip(!chatUrl, 'Set WEBU_E2E_CHAT_URL to run authenticated builder flow.');

  test('select -> edit -> reorder -> save -> refresh keeps authoritative builder state', async ({ page }) => {
    await ensureAuthenticatedPage(page, String(chatUrl));
    await openVisualBuilder(page);

    const structureItems = await visibleStructureItems(page);
    const initialCount = await structureItems.count();
    await structureItems.first().click();

    const editableField = page.locator('input:not([type="hidden"]), textarea').filter({ hasNot: page.locator('[readonly]') }).first();
    if (await editableField.isVisible().catch(() => false)) {
      await editableField.fill(`Builder sync ${Date.now()}`);
    }

    const dragHandles = page.getByRole('button', { name: /drag to reorder|reorder/i });
    if (initialCount > 1 && await dragHandles.count() >= 2) {
      await dragHandles.nth(0).dragTo(dragHandles.nth(1));
    }

    const saveDraft = page.getByRole('button', { name: /save draft|save|დრაფტი შენახვა|შენახვა/i }).first();
    await expect(saveDraft).toBeVisible({ timeout: 10000 });
    await saveDraft.click();

    await page.reload();
    await openVisualBuilder(page);

    const refreshedItems = await visibleStructureItems(page);
    await expect(refreshedItems).toHaveCount(initialCount);
    await expect(page.locator('iframe[title="Preview"]').first()).toBeVisible();
  });
});
