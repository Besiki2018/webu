import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const docPath = path.join(ROOT, 'docs/architecture/UNIVERSAL_BINDING_NAMESPACE_COMPATIBILITY_P5_F5_03.md');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS universal binding namespace compatibility contracts (P5-F5-03)', () => {
    it('keeps canonical control groups and binding metadata surface for universal builder components', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('type CanonicalControlGroup =');
        expect(cms).toContain("'content'");
        expect(cms).toContain("'bindings' | 'meta'");
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

    it('documents P5-F5-03 canonical namespace compatibility and validator coverage', () => {
        const doc = read(docPath);

        expect(doc).toContain('P5-F5-03');
        expect(doc).toContain('CmsCanonicalBindingResolver');
        expect(doc).toContain('CmsBindingExpressionValidator');
        expect(doc).toContain('CanonicalControlGroup');
        expect(doc).toContain('content.properties');
        expect(doc).toContain('content.rooms');
        expect(doc).toContain('webu_hotel_room_availability_01');
        expect(doc).toContain('webu_realestate_map_01');
        expect(doc).toContain('webu_book_slots_01');
    });
});

