import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const templateImportServicePath = path.join(ROOT, 'app/Services/TemplateImportService.php');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS responsive/state preview-runtime parity contracts', () => {
    it('keeps responsive and state style merge order aligned between builder preview and runtime script generation', () => {
        const cms = read(cmsPagePath);
        const service = read(templateImportServicePath);

        expect(cms).toContain('function resolveGeneralFoundationRuntimeStyleResolution(options: {');
        expect(cms).toContain("const activeResponsiveStyleOverrides = options.viewport === 'mobile'");
        expect(cms).toContain("const interactionStateStyleOverrides = options.interactionState === 'hover'");
        expect(cms).toContain('const effectiveStyleProps = {');
        expect(cms).toContain('...options.styleProps,');
        expect(cms).toContain('...activeResponsiveStyleOverrides,');
        expect(cms).toContain('...normalStateStyleOverrides,');
        expect(cms).toContain('...interactionStateStyleOverrides,');
        expect(cms).toContain('const runtimeStyleResolution = resolveGeneralFoundationRuntimeStyleResolution({');
        expect(cms).toContain('const effectiveStyleProps = runtimeStyleResolution.effective_style;');

        expect(service).toContain("var activeResponsiveStyleOverrides = viewportMode === 'mobile'");
        expect(service).toContain("var interactionStateStyleOverrides = runtimeInteractionState === 'hover'");
        expect(service).toContain('var effectiveStyleProps = Object.assign(');
        expect(service).toContain('styleProps,');
        expect(service).toContain('activeResponsiveStyleOverrides,');
        expect(service).toContain('normalStateStyleOverrides,');
        expect(service).toContain('interactionStateStyleOverrides');
        expect(service).toContain("container.setAttribute('data-webu-runtime-style-order', 'base>responsive>state');");
    });

    it('keeps tablet responsive visibility branch and interaction state hooks present on both sides', () => {
        const cms = read(cmsPagePath);
        const service = read(templateImportServicePath);

        expect(cms).toContain('const hideOnTablet = parseBooleanProp(responsiveProps.hide_on_tablet, false);');
        expect(cms).toContain("(builderPreviewMode === 'tablet' && hideOnTablet)");
        expect(cms).toContain("container.setAttribute('data-webu-builder-style-order', 'base>responsive>state');");
        expect(cms).toContain("container.setAttribute('data-webu-builder-interaction-state-preview', builderPreviewInteractionState);");
        expect(cms).toContain('const renderBuilderInteractionStatePreviewControls = (compact: boolean): ReactNode => {');

        expect(service).toContain('var hideOnTablet = parseBooleanProp(responsiveProps.hide_on_tablet, false);');
        expect(service).toContain("(viewportMode === 'tablet' && hideOnTablet)");
        expect(service).toContain("container.setAttribute('data-webu-runtime-interaction-state-preview', runtimeInteractionState);");
        expect(service).toContain("query.get('ui_state')");
        expect(service).toContain("query.get('interaction_state')");
    });

    it('keeps runtime general section style application wired into applySectionProps before field binding updates', () => {
        const service = read(templateImportServicePath);

        expect(service).toContain('function applyGeneralSectionStyleRuntime(container, props) {');
        expect(service).toContain('if (isGeneralSectionType(type) && container instanceof HTMLElement) {');
        expect(service).toContain('applyGeneralSectionStyleRuntime(container, effectiveProps);');
        expect(service).toContain('Object.keys(effectiveProps).forEach(function (key) {');
        expect(service).toContain('applyFieldByKey(container, key, effectiveProps[key]);');
    });
});
