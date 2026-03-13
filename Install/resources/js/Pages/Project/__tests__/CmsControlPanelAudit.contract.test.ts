import { describe, expect, it } from 'vitest';

import { read, readCurrentBuilderDocs } from './builderContractTestUtils';

describe('CMS builder control panel audit contracts', () => {
    it('keeps canonical control-group audit builders in InspectorRenderer and the summary renderer in Cms', () => {
        const renderer = read('resources/js/builder/inspector/InspectorRenderer.ts');
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(renderer).toContain('export interface CanonicalControlGroupAuditRow {');
        expect(renderer).toContain('export function buildCanonicalControlGroupAuditRows(fields: SchemaPrimitiveField[]): CanonicalControlGroupAuditRow[] {');
        expect(cms).toContain('const renderControlGroupAuditSummary = (rows: CanonicalControlGroupAuditRow[], compact: boolean): ReactNode => {');
        expect(cms).toContain("data-webu-role={compact ? 'builder-control-group-audit-compact' : 'builder-control-group-audit'}");
        expect(cms).toContain("{t('Control Groups')}");
        expect(cms).toContain("{t('Panel audit')}");
    });

    it('renders control-group audit summary for selected section and fixed header/footer controls', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');
        const resolver = read('resources/js/builder/cms/CmsSchemaResolver.ts');

        expect(resolver).toContain('controlGroupAuditRows: buildCanonicalControlGroupAuditRows(inspectorState.editableSchemaFieldsForDisplay),');
        expect(cms).toContain('controlGroupAuditRows: selectedSectionControlGroupAuditRows,');
        expect(cms).toContain('const selectedFixedSectionControlGroupAuditRows = useMemo(');
        expect(cms).toContain('renderControlGroupAuditSummary(selectedSectionControlGroupAuditRows, compact)');
        expect(cms).toContain('renderControlGroupAuditSummary(selectedFixedSectionControlGroupAuditRows, true)');
    });

    it('reuses canonical fieldset and primary-tab builders from InspectorRenderer for selected and fixed section panels', () => {
        const renderer = read('resources/js/builder/inspector/InspectorRenderer.ts');
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(renderer).toContain('export interface CanonicalControlGroupFieldSet {');
        expect(renderer).toContain('export function buildCanonicalControlGroupFieldSets(fields: SchemaPrimitiveField[]): CanonicalControlGroupFieldSet[] {');
        expect(renderer).toContain('export function buildCanonicalPrimaryPanelTabFieldSetBuckets(');
        expect(cms).toContain('const renderCanonicalControlGroupFieldSets = (');
        expect(cms).toContain("data-webu-role={compact ? 'builder-control-panel-primary-tabs-compact' : 'builder-control-panel-primary-tabs'}");
        expect(cms).toContain('data-webu-role="builder-control-panel-primary-tab-trigger"');
        expect(cms).toContain('data-webu-role="builder-control-panel-primary-tab-content"');
        expect(cms).toContain("data-webu-role={compact ? 'builder-control-group-fieldsets-compact' : 'builder-control-group-fieldsets'}");
        expect(cms).toContain('renderCanonicalControlGroupFieldSets(selectedSectionEditableSchemaFieldsForDisplay, {');
        expect(cms).toContain('renderCanonicalControlGroupFieldSets(selectedFixedSectionEditableFieldsForDisplay, {');
    });

    it('documents current canonical registry and mutation pipeline ownership in active builder docs', () => {
        const docs = readCurrentBuilderDocs();

        expect(docs).toContain('componentRegistry.ts');
        expect(docs).toContain('updatePipeline.ts');
        expect(docs).toContain('Sidebar generates controls from schema');
        expect(docs).toContain('BuilderCanvas');
    });
});
