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

describe('CMS responsive overrides parity contracts', () => {
    it('defines canonical responsive desktop/tablet/mobile override schema for general foundation controls', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("function buildGeneralFoundationResponsiveStyleOverrideSchema(breakpointLabel: 'Desktop' | 'Tablet' | 'Mobile')");
        expect(cms).toContain("hide_on_tablet: { type: 'boolean', title: 'Responsive: Hide on Tablet', default: false }");
        expect(cms).toContain("desktop: buildGeneralFoundationResponsiveStyleOverrideSchema('Desktop')");
        expect(cms).toContain("tablet: buildGeneralFoundationResponsiveStyleOverrideSchema('Tablet')");
        expect(cms).toContain("mobile: buildGeneralFoundationResponsiveStyleOverrideSchema('Mobile')");
        expect(cms).toContain('title: `Responsive: ${breakpointLabel} Background Override`');
        expect(cms).toContain('title: `Responsive: ${breakpointLabel} Vertical Margin (px)`');
    });

    it('applies responsive style overrides in builder preview and handles tablet visibility separately', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('function readGeneralFoundationStyleOverrides(value: unknown): GeneralFoundationStyleOverrides {');
        expect(cms).toContain('function resolveGeneralFoundationRuntimeStyleResolution(options: {');
        expect(cms).toContain('viewport: BuilderPreviewViewport;');
        expect(cms).toContain('interactionState: BuilderInteractionPreviewState;');
        expect(cms).toContain('const responsiveDesktopStyleOverrides = readGeneralFoundationStyleOverrides(options.responsiveProps.desktop);');
        expect(cms).toContain('const responsiveTabletStyleOverrides = readGeneralFoundationStyleOverrides(options.responsiveProps.tablet);');
        expect(cms).toContain('const responsiveMobileStyleOverrides = readGeneralFoundationStyleOverrides(options.responsiveProps.mobile);');
        expect(cms).toContain("const activeResponsiveStyleOverrides = options.viewport === 'mobile'");
        expect(cms).toContain('const runtimeStyleResolution = resolveGeneralFoundationRuntimeStyleResolution({');
        expect(cms).toContain('const effectiveStyleProps = runtimeStyleResolution.effective_style;');
        expect(cms).toContain('const hideOnTablet = parseBooleanProp(responsiveProps.hide_on_tablet, false);');
        expect(cms).toContain("(builderPreviewMode === 'tablet' && hideOnTablet)");
        expect(cms).toContain("t('Hidden on Tablet')");
    });

    it('implements unified responsive overrides UI: filter responsive fields by current breakpoint and show single breakpoint selector in Style tab', () => {
        const cms = read(cmsPagePath);
        const filterInspectorSchemaFields = read(path.join(ROOT, 'resources/js/builder/inspector/filterInspectorSchemaFields.ts'));

        expect(cms).toContain('selectedSectionEditableSchemaFieldsForDisplay');
        expect(filterInspectorSchemaFields).toContain('return breakpoint === options.previewMode;');
        expect(cms).toContain('interactionState: builderPreviewInteractionState');
        expect(cms).toContain('data-webu-role="builder-style-tab-responsive-selector"');
        expect(cms).toContain('styleTabExtraContent');
        expect(cms).toContain("t('Responsive style')");
        expect(cms).toContain('const renderBuilderInteractionStatePreviewControls = (compact: boolean): ReactNode => {');
        expect(cms).toContain("t('Interaction state')");
        expect(cms).toContain("key={`builder-style-state-${state}`}");
    });
});

describe('CMS variant selector in builder', () => {
    it('uses componentVariants to expose layout_variant and style_variant dropdowns in inspector', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('componentVariants');
        expect(cms).toContain('propComponentVariants');
        expect(cms).toContain("title: 'Design variant'");
        expect(cms).toContain("title: 'Style variant'");
        expect(cms).toContain('variants.layout_variants');
        expect(cms).toContain('variants.style_variants');
    });
});
