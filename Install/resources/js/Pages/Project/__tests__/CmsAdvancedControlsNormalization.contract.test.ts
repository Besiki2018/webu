import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const templateImportServicePath = path.join(ROOT, 'app/Services/TemplateImportService.php');
const d3DocPath = path.join(ROOT, 'docs/qa/CMS_ADVANCED_CONTROLS_D3_BASELINE.md');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS advanced controls normalization contracts (D3 baseline)', () => {
    it('defines canonical advanced control schema for general foundation components', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("custom_css: { type: 'string', title: 'Advanced: Custom CSS (Scoped to Element)', default: '' }");
        expect(cms).toContain('visibility: {');
        expect(cms).toContain("desktop: { type: 'boolean', title: 'Advanced: Visible on Desktop', default: true }");
        expect(cms).toContain("tablet: { type: 'boolean', title: 'Advanced: Visible on Tablet', default: true }");
        expect(cms).toContain("mobile: { type: 'boolean', title: 'Advanced: Visible on Mobile', default: true }");
        expect(cms).toContain('positioning: {');
        expect(cms).toContain("position_mode: { type: 'string', title: 'Advanced: Position Mode (static|relative|absolute|sticky)', default: 'static' }");
        expect(cms).toContain("z_index: { type: 'integer', title: 'Advanced: z-index', default: null }");
        expect(cms).toContain('attributes: {');
        expect(cms).toContain("aria_label: { type: 'string', title: 'Advanced: aria-label', default: '' }");
        expect(cms).toContain("data_testid: { type: 'string', title: 'Advanced: data-testid', default: '' }");
    });

    it('applies normalized advanced controls in builder preview for visibility, positioning, attributes and custom-css markers', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const advancedVisibilityProps = isRecord(advancedProps.visibility) ? advancedProps.visibility : {};');
        expect(cms).toContain('const advancedPositioningProps = isRecord(advancedProps.positioning) ? advancedProps.positioning : {};');
        expect(cms).toContain('const advancedAttributesProps = isRecord(advancedProps.attributes) ? advancedProps.attributes : {};');
        expect(cms).toContain('const customCss = typeof advancedProps.custom_css === \'string\' ? advancedProps.custom_css.trim() : \'\';');
        expect(cms).toContain('const advancedVisibilityDimmed = (builderPreviewMode === \'mobile\' && !advancedVisibleOnMobile)');
        expect(cms).toContain("'data-webu-builder-visibility-source',");
        expect(cms).toContain("container.setAttribute('data-webu-builder-positioning-mode', positionMode);");
        expect(cms).toContain("container.setAttribute('role', roleAttr);");
        expect(cms).toContain("container.setAttribute('aria-label', ariaLabelAttr);");
        expect(cms).toContain("container.setAttribute('data-testid', dataTestIdAttr);");
        expect(cms).toContain("container.setAttribute('data-webu-builder-custom-css-present', '1');");
        expect(cms).toContain("container.setAttribute('data-webu-builder-custom-css-bytes', String(customCss.length));");
    });

    it('keeps runtime script parity for advanced controls and documents D3 baseline scope', () => {
        const service = read(templateImportServicePath);
        const doc = read(d3DocPath);

        expect(service).toContain('var advancedVisibilityProps = advancedProps.visibility');
        expect(service).toContain('var advancedPositioningProps = advancedProps.positioning');
        expect(service).toContain('var advancedAttributesProps = advancedProps.attributes');
        expect(service).toContain('var hiddenByAdvancedVisibilityRule = (viewportMode === \'mobile\' && !advancedVisibleOnMobile)');
        expect(service).toContain("container.setAttribute('data-webu-runtime-hidden-by-advanced-visibility', '1');");
        expect(service).toContain("container.setAttribute('data-webu-runtime-positioning-mode', positionMode);");
        expect(service).toContain("container.setAttribute('role', roleAttr);");
        expect(service).toContain("container.setAttribute('aria-label', ariaLabelAttr);");
        expect(service).toContain("container.setAttribute('data-testid', dataTestIdAttr);");
        expect(service).toContain("container.setAttribute('data-webu-runtime-custom-css-present', '1');");
        expect(service).toContain("container.setAttribute('data-webu-runtime-html-id', htmlId);");
        expect(service).toContain("container.setAttribute('data-webu-runtime-css-class', cssClass);");

        expect(doc).toContain('# CMS Advanced Controls D3 Baseline');
        expect(doc).toContain('P3-D3-01');
        expect(doc).toContain('custom CSS (safe scoped execution + runtime/builder style injection)');
        expect(doc).toContain('attributes (role / aria-* / data-* subset)');
        expect(doc).toContain('visibility (advanced per-device visibility flags)');
        expect(doc).toContain('positioning (position mode + offsets + z-index)');
        expect(doc).toContain('P3-D3-03');
    });
});
