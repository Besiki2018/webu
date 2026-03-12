import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');

function read(relativePath: string): string {
    return fs.readFileSync(path.join(ROOT, relativePath), 'utf8');
}

describe('CMS element panel UX cleanup wrapper summary contracts (Phase 3 line 166)', () => {
    it('keeps shared semantic field renderer plus dedicated image/link editors in Cms.tsx', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain('const renderSchemaFieldEditorControl = (options: {');
        expect(cms).toContain('{t(field.label)}');

        expect(cms).toContain('const renderSidebarMediaFieldControls = (options: {');
        expect(cms).toContain("t('Choose / Upload Image')");
        expect(cms).toContain("t('Choose / Upload Video')");
        expect(cms).toContain("aria-label={t('Remove image')}");
        expect(cms).toContain('{renderSidebarMediaFieldControls({');

        expect(cms).toContain('const renderSidebarLinkObjectFieldControls = (options: {');
        expect(cms).toContain("{t('Label')}");
        expect(cms).toContain("{t('URL')}");
        expect(cms).toContain('{renderSidebarLinkObjectFieldControls({');
        expect(cms).toContain('isEditableLinkObjectValue(effectiveValue)');
    });

    it('keeps nested object JSON validation fallback path for section props before builder save', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain('const buildContentJsonPayload = useCallback((): Record<string, unknown> | null => {');
        expect(cms).toContain("t('Section props must be a JSON object')");
        expect(cms).toContain("t('Invalid JSON in section props')");
        expect(cms).toContain("t('Fix invalid section JSON before saving')");
        expect(cms).toContain('const parsedExtra = parseJsonRecord(extraContentJsonText);');
        expect(cms).toContain("toast.error(parsedExtra.error ?? t('Invalid extra content JSON'))");
    });

    it('documents wrapper-level UX cleanup closure semantics and keeps broader Phase 3 lines explicitly separate', () => {
        const doc = read('docs/qa/CMS_ELEMENT_PANEL_UX_CLEANUP_PHASE3_WRAPPER_SUMMARY.md');

        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:166');
        expect(doc).toContain('Closure Semantics (Wrapper Level)');
        expect(doc).toContain('semantic labels');
        expect(doc).toContain('media-picker-aware editor');
        expect(doc).toContain('link objects');
        expect(doc).toContain('Section props must be a JSON object');
        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:154');
        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:162');
    });
});
