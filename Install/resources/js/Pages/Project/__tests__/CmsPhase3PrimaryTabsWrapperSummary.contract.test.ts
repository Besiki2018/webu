import { describe, expect, it } from 'vitest';

import { read, readCurrentBuilderDocs } from './builderContractTestUtils';

describe('CMS Phase 3 primary tabs wrapper summary contracts', () => {
    it('keeps shared canonical primary-tab bucket mapping in InspectorRenderer and tab renderer markers in Cms', () => {
        const fieldResolver = read('resources/js/builder/inspector/InspectorFieldResolver.ts');
        const renderer = read('resources/js/builder/inspector/InspectorRenderer.ts');
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(fieldResolver).toContain("export type CanonicalPrimaryPanelTab = 'content' | 'layout' | 'style' | 'advanced';");
        expect(renderer).toContain('function mapCanonicalControlGroupToPrimaryPanelTab(group: CanonicalControlGroup): CanonicalPrimaryPanelTab');
        expect(renderer).toContain('export function buildCanonicalPrimaryPanelTabFieldSetBuckets(');
        expect(cms).toContain('const renderCanonicalControlGroupFieldSets = (');
        expect(cms).toContain('<Tabs');
        expect(cms).toContain('<TabsList');
        expect(cms).toContain('<TabsTrigger');
        expect(cms).toContain('<TabsContent');
        expect(cms).toContain("data-webu-role={compact ? 'builder-control-panel-primary-tabs-compact' : 'builder-control-panel-primary-tabs'}");
        expect(cms).toContain('data-webu-role="builder-control-panel-primary-tab-trigger"');
        expect(cms).toContain('data-webu-role="builder-control-panel-primary-tab-content"');
    });

    it('reuses the primary-tab renderer for selected and fixed section settings panels', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain('renderCanonicalControlGroupFieldSets(selectedSectionEditableSchemaFieldsForDisplay, {');
        expect(cms).toContain('renderCanonicalControlGroupFieldSets(selectedFixedSectionEditableFieldsForDisplay, {');
        expect(cms).toContain('panelKey: `selected-section-${selectedSectionDraft.localId}');
        expect(cms).toContain('panelKey: `fixed-section-${selectedFixedSectionKey}`');
    });

    it('documents current canonical registry and mutation pipeline ownership instead of removed wrapper summary notes', () => {
        const docs = readCurrentBuilderDocs();

        expect(docs).toContain('componentRegistry.ts');
        expect(docs).toContain('updatePipeline.ts');
        expect(docs).toContain('BuilderCanvas');
        expect(docs).toContain('schema-driven builder');
    });
});
