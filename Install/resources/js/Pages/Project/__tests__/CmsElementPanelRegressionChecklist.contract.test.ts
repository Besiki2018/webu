import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS element panel regression checklist contract', () => {
    it('keeps selected section panel semantic labels and fallback states', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const renderSelectedSectionEditableFields = (compact: boolean): ReactNode => {');
        expect(cms).toContain("Selected section data is invalid.");
        expect(cms).toContain("Data comes from backend automatically.");
        expect(cms).toContain("No editable fields");
        expect(cms).toContain("sectionDisplayLabelByKey.get(normalizeSectionTypeKey(selectedSectionDraft.type)) ?? sectionDisplayLabelByKey.get(selectedSectionDraft.type) ?? selectedSectionDraft.type");
    });

    it('keeps common field renderer branches for section settings panel', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const renderSchemaFieldEditorControl = (options: {');
        expect(cms).toContain("if (field.type === 'boolean') {");
        expect(cms).toContain("if (field.type === 'number' || field.type === 'integer') {");
        expect(cms).toContain('if (isColorField) {');
        expect(cms).toContain("if (field.type === 'string' && (isImageField || isVideoField)) {");
        expect(cms).toContain("if (field.type === 'string' && isEditableLinkObjectValue(effectiveValue)) {");
        expect(cms).toContain('renderSidebarMediaFieldControls({');
        expect(cms).toContain('renderSidebarLinkObjectFieldControls({');
        expect(cms).toContain('renderDynamicBindingHint(field, effectiveValue, { compact, bindingMeta: options.bindingMeta })');
        expect(cms).toContain('renderDynamicBindingActions(field, effectiveValue, {');
        expect(cms).toContain('renderFieldBindingWarnings(fieldBindingWarnings, compact)');
        expect(cms).toContain('renderTextTypographyControls({');
    });

    it('keeps fixed header/footer editor renderer parity for link/media object controls', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("{t('Edit Header')}");
        expect(cms).toContain("{t('Edit Footer')}");
        expect(cms).toContain("{t('Header Menu Source')}");
        expect(cms).toContain('selectedFixedSectionParsedProps && selectedFixedSectionEditableFields.length > 0');
        expect(cms).toContain('renderCanonicalControlGroupFieldSets(selectedFixedSectionEditableFields, {');
        expect(cms).toContain('renderField: (field) => renderSchemaFieldEditorControl({');
        expect(cms).toContain('itemKeyPrefix: selectedFixedSectionKey');
        expect(cms).toContain('bindingMeta: null');
        expect(cms).toContain('bindingWarnings: []');
        expect(cms).toContain('onChangePath: (path, value) => updateFixedSectionPathProp(selectedFixedSectionKey, path, value)');
        expect(cms).toContain("{t('No editable options')}");
    });

    it('keeps visual builder settings card wired to selected section editor renderer', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("<CardTitle className=\"text-sm\">{t('Settings')}</CardTitle>");
        expect(cms).toContain("<CardDescription>{t('Select section and edit')}</CardDescription>");
        expect(cms).toContain('{renderSelectedSectionEditableFields(false)}');
        expect(cms).toContain('bindingMeta: selectedSectionDraft.bindingMeta ?? null');
        expect(cms).toContain('bindingWarnings: isNested ? [] : selectedSectionBindingWarnings');
        expect(cms).toContain("{t('Select section from structure')}");
    });
});
