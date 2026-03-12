import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const metadataDocPath = path.join(ROOT, 'docs/architecture/CMS_CANONICAL_CONTROL_METADATA_V1.md');
const controlGroupStandardsDocPath = path.join(ROOT, 'docs/qa/CMS_CONTROL_GROUP_STANDARDS_D3.md');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS canonical control metadata contracts', () => {
    it('defines canonical control metadata fields and group taxonomy in Cms builder', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('type CanonicalControlGroup =');
        [
            "'content'",
            "'style'",
            "'advanced'",
            "'responsive'",
            "'states'",
            "'data'",
            "'bindings'",
            "'meta'",
        ].forEach((token) => expect(cms).toContain(token));

        expect(cms).toContain('interface CanonicalControlMetadata');
        expect(cms).toContain('group: CanonicalControlGroup;');
        expect(cms).toContain('responsive: boolean;');
        expect(cms).toContain('stateful: boolean;');
        expect(cms).toContain('dynamic_capable: boolean;');
        expect(cms).toContain('control_meta: CanonicalControlMetadata;');
    });

    it('builds canonical control metadata during schema field collection and manual header/footer field normalization', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('function buildCanonicalControlMetadataForSchemaField(');
        expect(cms).toContain('function inferCanonicalControlGroupForSchemaField(');
        expect(cms).toContain('function inferCanonicalDynamicCapabilityForSchemaField(');
        expect(cms).toContain('control_meta: buildCanonicalControlMetadataForSchemaField(nextPath, fieldType, label, definition)');
        expect(cms).toContain('function fixedSectionManualFields(kind: \'header\' | \'footer\')');
        expect(cms).toContain('control_meta: buildCanonicalControlMetadataForSchemaField(');
        expect(cms).toContain('field.control_meta.dynamic_capable === false');
    });

    it('documents canonical control metadata v1 for future component additions', () => {
        const doc = read(metadataDocPath);

        expect(doc).toContain('# CMS Canonical Control Metadata V1');
        expect(doc).toContain('Every primitive editable control should expose metadata');
        expect(doc).toContain('`type`');
        expect(doc).toContain('`label`');
        expect(doc).toContain('`group`');
        expect(doc).toContain('`responsive`');
        expect(doc).toContain('`stateful`');
        expect(doc).toContain('`dynamic_capable`');
        expect(doc).toContain('Inference Rules');
    });

    it('documents D3 control-group standards for future component additions without new panel paradigms', () => {
        const doc = read(controlGroupStandardsDocPath);

        expect(doc).toContain('# CMS Control Group Standards D3');
        expect(doc).toContain('P3-D3-04');
        expect(doc).toContain('without inventing new panel paradigms');
        expect(doc).toContain('CMS_CANONICAL_CONTROL_METADATA_V1');
        expect(doc).toContain('content');
        expect(doc).toContain('style');
        expect(doc).toContain('advanced');
        expect(doc).toContain('responsive');
        expect(doc).toContain('states');
        expect(doc).toContain('data');
        expect(doc).toContain('bindings');
        expect(doc).toContain('meta');
        expect(doc).toContain('base -> responsive -> state');
        expect(doc).toContain('custom_css');
        expect(doc).toContain('renderSchemaFieldEditorControl');
        expect(doc).toContain('renderCanonicalControlGroupFieldSets');
        expect(doc).toContain('buildCanonicalControlMetadataForSchemaField');
        expect(doc).toContain('advanced.component_presets');
        expect(doc).toContain('upsertBuilderScopedCustomCss');
        expect(doc).toContain('upsertRuntimeScopedCustomCss');
        expect(doc).toContain('applyGeneralFoundationComponentStylePresetsPreview');
        expect(doc).toContain('applyGeneralComponentStylePresetsRuntime');
        expect(doc).toContain('D3-03');
    });
});
