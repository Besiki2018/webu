import { test, expect, type FrameLocator, type Page } from '@playwright/test';
import { ensureAuthenticatedPage } from '../helpers/auth';

/**
 * Component-level inspect/sidebar click flow.
 * Verifies that clicking nested repeater/menu/footer items in the builder iframe
 * keeps selection on the parent component and the sidebar still shows the full
 * component editing surface.
 *
 * Requires: TEST_PROJECT_ID, optionally TEST_USER_EMAIL/TEST_USER_PASSWORD
 * Project must have cards, header, footer sections with nested items.
 */
const PROJECT_ID = process.env.TEST_PROJECT_ID;
const EDITABLE_NODE_SELECTOR = '[data-webu-field], [data-webu-field-scope], [data-webu-field-url]';
const SECTION_SELECTOR = '[data-webu-section-local-id]';

function editableNodesInSectionSelector(sectionLocalId: string): string {
  return [
    `[data-webu-section-local-id="${sectionLocalId}"] [data-webu-field]`,
    `[data-webu-section-local-id="${sectionLocalId}"] [data-webu-field-scope]`,
    `[data-webu-section-local-id="${sectionLocalId}"] [data-webu-field-url]`,
  ].join(', ');
}

async function openInspectTab(page: Page): Promise<FrameLocator> {
  const previewFrame = page.frameLocator('iframe[title="Preview"], iframe').first();
  if (await previewFrame.locator('body').isVisible().catch(() => false)) {
    return previewFrame;
  }

  const inspectTab = page.getByRole('tab', { name: /inspect|preview|პრევიუ/i }).or(
    page.getByRole('button', { name: /inspect|preview|პრევიუ/i })
  ).first();
  await expect(inspectTab).toBeVisible({ timeout: 10000 });
  await inspectTab.click();
  await expect(previewFrame.locator('body')).toBeVisible({ timeout: 8000 });
  return previewFrame;
}

async function expectComponentEditingSurface(page: Page): Promise<void> {
  await expect(page.getByText(/Unified editing context/i).first()).toBeVisible({ timeout: 5000 });
  await expect(page.getByRole('tab', { name: /^Content$|^კონტენტი$/i }).or(
    page.getByRole('button', { name: /^Content$|^კონტენტი$/i })
  ).first()).toBeVisible({ timeout: 5000 });
  await expect(page.getByRole('tab', { name: /^Layout$|^განლაგება$/i }).or(
    page.getByRole('button', { name: /^Layout$|^განლაგება$/i })
  ).first()).toBeVisible({ timeout: 5000 });
  await expect(page.getByRole('tab', { name: /^Style$|^სტილი$/i }).or(
    page.getByRole('button', { name: /^Style$|^სტილი$/i })
  ).first()).toBeVisible({ timeout: 5000 });
  await expect(page.getByRole('tab', { name: /^Advanced$|^დამატებითი$/i }).or(
    page.getByRole('button', { name: /^Advanced$|^დამატებითი$/i })
  ).first()).toBeVisible({ timeout: 5000 });
}

async function getSectionIdsWithEditableNodes(previewFrame: FrameLocator): Promise<string[]> {
  return previewFrame.locator(SECTION_SELECTOR).evaluateAll((nodes, editableSelector) => {
    const seen = new Set<string>();

    nodes.forEach((node) => {
      if (!(node instanceof HTMLElement)) {
        return;
      }

      const localId = (node.getAttribute('data-webu-section-local-id') ?? '').trim();
      if (localId === '' || seen.has(localId)) {
        return;
      }

      if (node.querySelector(editableSelector as string)) {
        seen.add(localId);
      }
    });

    return Array.from(seen);
  }, EDITABLE_NODE_SELECTOR);
}

async function getSectionIds(previewFrame: FrameLocator): Promise<string[]> {
  return previewFrame.locator(SECTION_SELECTOR).evaluateAll((nodes) => {
    const seen = new Set<string>();

    nodes.forEach((node) => {
      if (!(node instanceof HTMLElement)) {
        return;
      }

      const localId = (node.getAttribute('data-webu-section-local-id') ?? '').trim();
      if (localId !== '') {
        seen.add(localId);
      }
    });

    return Array.from(seen);
  });
}

test.describe('Component-level inspect/sidebar click flow', () => {
  test('opens inspect preview and keeps sidebar component-level across available selections', async ({ page }) => {
    if (!PROJECT_ID) test.skip();

    await ensureAuthenticatedPage(page, `/project/${PROJECT_ID}?tab=inspect`);
    await expect(page).toHaveURL(new RegExp(`/project/${PROJECT_ID}`), { timeout: 15000 });
    if (page.url().includes('/login')) {
      test.skip();
      return;
    }

    const previewFrame = await openInspectTab(page);
    const editableNodes = previewFrame.locator(EDITABLE_NODE_SELECTOR);
    const sectionNodes = previewFrame.locator(SECTION_SELECTOR);

    if (await editableNodes.count() > 0) {
      await expect(editableNodes.first()).toBeVisible({ timeout: 12000 });
      await editableNodes.first().click();
      await expectComponentEditingSurface(page);
    } else if (await sectionNodes.count() > 0) {
      await expect(sectionNodes.first()).toBeVisible({ timeout: 12000 });
      await sectionNodes.first().click();
      await expectComponentEditingSurface(page);
    } else {
      test.skip();
      return;
    }

    await expect(page.locator('.workspace-floating-structure-item--active, [class*="inspector"]').first()).toBeVisible({ timeout: 5000 });

    const sectionIdsWithEditableNodes = await getSectionIdsWithEditableNodes(previewFrame);
    const primarySectionId = sectionIdsWithEditableNodes[0];
    if (primarySectionId) {
      const sameSectionNodes = previewFrame.locator(editableNodesInSectionSelector(primarySectionId));
      if (await sameSectionNodes.count() >= 2) {
        await sameSectionNodes.nth(1).click();
        await expectComponentEditingSurface(page);
      }
    }

    const sectionIds = await getSectionIds(previewFrame);
    if (sectionIds.length >= 2) {
      const secondSection = previewFrame.locator(`${SECTION_SELECTOR}[data-webu-section-local-id="${sectionIds[1]!}"]`).first();
      await expect(secondSection).toBeVisible({ timeout: 12000 });
      await secondSection.click();
      await expectComponentEditingSurface(page);
    }
  });
});
