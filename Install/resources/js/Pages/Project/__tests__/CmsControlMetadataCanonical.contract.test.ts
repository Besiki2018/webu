import { describe, expect, it } from 'vitest';

import { read, readCurrentBuilderDocs } from './builderContractTestUtils';

describe('CMS canonical control metadata contracts', () => {
    it('defines canonical control metadata fields and group taxonomy in the extracted inspector field resolver', () => {
        const resolver = read('resources/js/builder/inspector/InspectorFieldResolver.ts');

        expect(resolver).toContain("export type CanonicalControlGroup = 'content' | 'layout' | 'style' | 'advanced' | 'responsive' | 'states' | 'data' | 'bindings' | 'meta';");
        expect(resolver).toContain('export interface CanonicalControlMetadata {');
        expect(resolver).toContain('group: CanonicalControlGroup;');
        expect(resolver).toContain('responsive: boolean;');
        expect(resolver).toContain('stateful: boolean;');
        expect(resolver).toContain('dynamic_capable: boolean;');
    });

    it('builds canonical control metadata through the resolver and preserves Cms fixed-section/manual field wiring', () => {
        const resolver = read('resources/js/builder/inspector/InspectorFieldResolver.ts');
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(resolver).toContain('function inferCanonicalControlGroupForSchemaField(');
        expect(resolver).toContain('function inferCanonicalDynamicCapabilityForSchemaField(');
        expect(resolver).toContain('export function buildCanonicalControlMetadataForSchemaField(');
        expect(resolver).toContain('control_meta: buildCanonicalControlMetadataForSchemaField(field.path, field.type, field.label, field.definition),');

        expect(cms).toContain("function fixedSectionManualFields(kind: 'header' | 'footer'): SchemaPrimitiveField[] {");
        expect(cms).toContain('control_meta: buildCanonicalControlMetadataForSchemaField(');
        expect(cms).toContain('field.control_meta.dynamic_capable === false');
    });

    it('documents the current canonical builder architecture instead of removed Cms inline metadata notes', () => {
        const docs = readCurrentBuilderDocs();

        expect(docs).toContain('componentRegistry.ts');
        expect(docs).toContain('updatePipeline.ts');
        expect(docs).toContain('Sidebar generates controls from schema');
        expect(docs).toContain('schema-driven builder');
    });
});
