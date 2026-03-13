import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import { readCurrentBuilderDocs } from './builderContractTestUtils';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');

function read(relativePath: string): string {
    return fs.readFileSync(path.join(ROOT, relativePath), 'utf8');
}

describe('CMS Phase 3 wrapper control-group parity summary contracts', () => {
    it('keeps canonical style/responsive/state schema primitives that back typography/spacing/border/layout parity lines', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain('function buildGeneralFoundationResponsiveStyleOverrideSchema');
        expect(cms).toContain('function buildGeneralFoundationStateStyleOverrideSchema');

        expect(cms).toContain('Background Override');
        expect(cms).toContain('Overlay Color Override');
        expect(cms).toContain('Overlay Opacity (%)');
        expect(cms).toContain('Text Color Override');
        expect(cms).toContain('Border Color Override');
        expect(cms).toContain('Border Radius (px)');
        expect(cms).toContain('Vertical Padding (px)');
        expect(cms).toContain('Horizontal Padding (px)');
        expect(cms).toContain('Vertical Margin (px)');

        expect(cms).toContain('hide_on_mobile');
        expect(cms).toContain('hide_on_tablet');
        expect(cms).toContain('hide_on_desktop');
        expect(cms).toContain('normal: buildGeneralFoundationStateStyleOverrideSchema(');
        expect(cms).toContain('hover: buildGeneralFoundationStateStyleOverrideSchema(');
        expect(cms).toContain('focus: buildGeneralFoundationStateStyleOverrideSchema(');
        expect(cms).toContain('active: buildGeneralFoundationStateStyleOverrideSchema(');
    });

    it('keeps background overlay parity markers plus advanced visibility/positioning and token-backed preset foundations', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');
        const runtimeService = read('app/Services/TemplateImportService.php');
        const advancedParity = read('resources/js/Pages/Project/__tests__/CmsAdvancedControlsParity.contract.test.ts');
        const presetsParity = read('resources/js/Pages/Project/__tests__/CmsReusableStylePresetsParity.contract.test.ts');

        expect(cms).toContain('resolveGeneralFoundationOverlayCssColor');
        expect(cms).toContain('applyGeneralFoundationBackgroundOverlayPreview');
        expect(cms).toContain("data-webu-builder-background-overlay");
        expect(cms).toContain("data-webu-builder-background-overlay-source");
        expect(cms).toContain('color-mix(in srgb,');

        expect(runtimeService).toContain('resolveGeneralOverlayCssColor');
        expect(runtimeService).toContain('applyGeneralBackgroundOverlayRuntime');
        expect(runtimeService).toContain("data-webu-runtime-background-overlay");
        expect(runtimeService).toContain("data-webu-runtime-background-overlay-source");
        expect(runtimeService).toContain('overlay_opacity_percent');

        expect(cms).toContain('visibility: {');
        expect(cms).toContain('positioning: {');
        expect(cms).toContain('component_presets: {');
        expect(cms).toContain('GENERAL_FOUNDATION_COMPONENT_PRESET_OPTIONS');

        expect(advancedParity).toContain('visibility');
        expect(advancedParity).toContain('positioning');
        expect(advancedParity).toContain('data-webu-builder-positioning-mode');
        expect(advancedParity).toContain('data-webu-runtime-positioning-mode');

        expect(presetsParity).toContain('var(--webu-token-radius-button, var(--webu-token-radius-base, 8px))');
        expect(presetsParity).toContain('var(--webu-token-radius-card, var(--webu-token-radius-base, 12px))');
        expect(presetsParity).toContain('data-webu-builder-component-presets');
        expect(presetsParity).toContain('data-webu-runtime-component-presets');
    });

    it('documents the wrapper-level closure mapping and references separate wrapper evidence locks', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('schema-driven builder');
        expect(doc).toContain('BuilderCanvas');
    });
});
