import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import { readCurrentBuilderDocs } from './builderContractTestUtils';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS universal binding namespace compatibility contracts (P5-F5-03)', () => {
    it('keeps canonical control groups and binding metadata surface for universal builder components', () => {
        const cms = read(cmsPagePath);
        const resolver = read(path.join(ROOT, 'resources/js/builder/inspector/InspectorFieldResolver.ts'));

        expect(resolver).toContain("export type CanonicalControlGroup = 'content' | 'layout' | 'style' | 'advanced' | 'responsive' | 'states' | 'data' | 'bindings' | 'meta';");
        expect(cms).toContain('binding_namespaces?: string[];');
        expect(cms).toContain('buildCanonicalControlMetadataForSchemaField');
        expect(cms).toContain('control_meta.group');
        expect(cms).toContain("['style', 'responsive', 'states', 'advanced', 'meta']");
    });

    it('keeps canonical route-param binding hints for universal vertical detail and reservation components', () => {
        const cms = read(cmsPagePath);

        [
            "order_id: { type: 'string', title: 'Order ID (Binding Hint)', default: '{{route.params.id}}' }",
            "service_id: { type: 'string', title: 'Service ID (Binding Hint)', default: '{{route.params.service_id}}' }",
            "project_slug: { type: 'string', title: 'Project Slug (Binding Hint)', default: '{{route.params.slug}}' }",
            "property_slug: { type: 'string', title: 'Property Slug (Binding Hint)', default: '{{route.params.slug}}' }",
            "room_slug: { type: 'string', title: 'Room Slug (Binding Hint)', default: '{{route.params.slug}}' }",
        ].forEach((bindingHint) => expect(cms).toContain(bindingHint));

        expect(cms).not.toContain('{{properties.');
        expect(cms).not.toContain('{{rooms.');
    });

    it('documents current canonical registry and mutation pipeline ownership instead of the removed namespace compatibility note', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('schema-driven builder');
        expect(doc).toContain('Sidebar generates controls from schema');
    });
});
