import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

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

        expect(presetsParity).toContain('--webu-token-radius-*');
        expect(presetsParity).toContain('--webu-token-shadow-*');
        expect(presetsParity).toContain('data-webu-builder-component-presets');
        expect(presetsParity).toContain('data-webu-runtime-component-presets');
    });

    it('documents the wrapper-level closure mapping and references separate wrapper evidence locks', () => {
        const doc = read('docs/qa/CMS_PHASE3_WRAPPER_CONTROL_GROUP_PARITY_SUMMARY.md');

        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:158');
        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:159');
        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:162');
        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:165');
        expect(doc).toContain('Closure Semantics (Wrapper Level)');
        expect(doc).toContain('base -> responsive -> state');
        expect(doc).toContain('data-webu-builder-background-overlay');
        expect(doc).toContain('data-webu-runtime-background-overlay');
        expect(doc).toContain('Related Wrapper Evidence Locks (Tracked Separately)');
        expect(doc).toContain('CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md');
        expect(doc).toContain('CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md');
        expect(doc).toContain('CMS_ELEMENT_PANEL_UX_CLEANUP_PHASE3_WRAPPER_SUMMARY.md');
        expect(doc).not.toContain('overlay-specific parity not yet explicitly locked');
    });
});
