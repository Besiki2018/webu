import { describe, expect, it } from 'vitest';

import { read, readCurrentBuilderDocs } from './builderContractTestUtils';

describe('CMS element panel UX cleanup wrapper summary contracts (Phase 3 line 166)', () => {
    it('keeps shared semantic field renderer plus direct-upload image/video and link editors in Cms', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');
        const mediaFieldControl = read('resources/js/builder/cms/CmsMediaFieldControl.tsx');

        expect(cms).toContain('const renderSchemaFieldEditorControl = (options: {');
        expect(cms).toContain('{t(field.label)}');

        expect(cms).toContain("import { CmsMediaFieldControl } from '@/builder/cms/CmsMediaFieldControl';");
        expect(cms).toContain('<CmsMediaFieldControl');
        expect(mediaFieldControl).toContain("hasValue ? t('Replace Image') : t('Upload Image')");
        expect(mediaFieldControl).toContain("hasValue ? t('Replace Video') : t('Upload Video')");
        expect(mediaFieldControl).toContain("accept={isVideoField ? 'video/*' : 'image/*'}");
        expect(mediaFieldControl).toContain("aria-label={t('Remove image')}");

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

    it('documents current canonical builder architecture instead of removed wrapper summary notes', () => {
        const docs = readCurrentBuilderDocs();

        expect(docs).toContain('componentRegistry.ts');
        expect(docs).toContain('updatePipeline.ts');
        expect(docs).toContain('Sidebar generates controls from schema');
        expect(docs).toContain('schema-driven builder');
    });
});
