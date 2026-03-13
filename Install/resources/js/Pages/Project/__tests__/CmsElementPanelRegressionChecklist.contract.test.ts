import { describe, expect, it } from 'vitest';

import { read } from './builderContractTestUtils';

describe('CMS element panel regression checklist contract', () => {
    it('keeps selected section fallback states in the extracted inspector panel shell', () => {
        const panel = read('resources/js/builder/inspector/SelectedSectionEditableFields.tsx');
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(panel).toContain("Selected section data is invalid.");
        expect(panel).toContain("Data comes from backend automatically.");
        expect(panel).toContain("No editable fields");
        expect(panel).toContain("No controls available for the selected element.");
        expect(cms).toContain('sectionDisplayLabelByKey.get(normalizeSectionTypeKey(selectedSectionDraft.type)) ?? selectedSectionDraft.type');
    });

    it('keeps common field renderer branches for section settings panel', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');
        const mediaFieldControl = read('resources/js/builder/cms/CmsMediaFieldControl.tsx');

        expect(cms).toContain('const renderSchemaFieldEditorControl = (options: {');
        expect(cms).toContain("if (field.type === 'boolean') {");
        expect(cms).toContain("if (field.type === 'number' || field.type === 'integer') {");
        expect(cms).toContain('if (isColorField) {');
        expect(cms).toContain("if (field.type === 'string' && (isImageField || isVideoField)) {");
        expect(cms).toContain('const linkValue = isEditableLinkObjectValue(effectiveValue)');
        expect(cms).toContain('<CmsMediaFieldControl');
        expect(cms).toContain('uploadMediaFile={uploadMediaFile}');
        expect(cms).toContain('renderSidebarLinkObjectFieldControls({');
        expect(cms).toContain('renderDynamicBindingHint(field, effectiveValue, { compact, bindingMeta: options.bindingMeta })');
        expect(cms).toContain('renderDynamicBindingActions(field, effectiveValue, {');
        expect(cms).toContain('renderFieldBindingWarnings(fieldBindingWarnings, compact)');
        expect(cms).toContain('renderTextTypographyControls({');
        expect(mediaFieldControl).toContain("hasValue ? t('Replace Image') : t('Upload Image')");
        expect(mediaFieldControl).toContain("hasValue ? t('Replace Video') : t('Upload Video')");
    });

    it('keeps fixed header/footer editor parity for link/media controls and fieldset rendering', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain("<Label className=\"text-xs\">{t('Header Menu Source')}</Label>");
        expect(cms).toContain('selectedFixedSectionParsedProps && selectedFixedSectionEditableFieldsForDisplay.length > 0');
        expect(cms).toContain('renderCanonicalControlGroupFieldSets(selectedFixedSectionEditableFieldsForDisplay, {');
        expect(cms).toContain('renderField: (field) => renderSchemaFieldEditorControl({');
        expect(cms).toContain('itemKeyPrefix: selectedFixedSectionKey');
        expect(cms).toContain('bindingMeta: null');
        expect(cms).toContain('bindingWarnings: []');
        expect(cms).toContain('onChangePath: (path, value) => updateFixedSectionPathProp(selectedFixedSectionKey, path, value)');
        expect(cms).toContain(": t('No editable options')}");
    });

    it('keeps the visual builder settings card wired to the selected section editor renderer', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain("<CardTitle className=\"text-sm\">{t('Settings')}</CardTitle>");
        expect(cms).toContain("<CardDescription>{t('Select section and edit')}</CardDescription>");
        expect(cms).toContain('{renderSelectedSectionEditableFields(false)}');
        expect(cms).toContain('bindingMeta: selectedSectionDraft.bindingMeta ?? null');
        expect(cms).toContain('bindingWarnings: isNested ? [] : selectedSectionBindingWarnings');
        expect(cms).toContain("{t('Select section from structure')}");
    });
});
