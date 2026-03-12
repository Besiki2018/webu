import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const templateImportServicePath = path.join(ROOT, 'app/Services/TemplateImportService.php');
const d3PresetDocPath = path.join(ROOT, 'docs/qa/CMS_REUSABLE_STYLE_PRESETS_D3_BASELINE.md');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS reusable style presets parity contracts', () => {
    it('defines canonical token-backed button/card/input preset schema under advanced component_presets', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const GENERAL_FOUNDATION_COMPONENT_PRESET_OPTIONS = {');
        expect(cms).toContain("button: ['none', 'solid-primary', 'outline-primary', 'soft-accent']");
        expect(cms).toContain("card: ['none', 'surface', 'outline', 'elevated']");
        expect(cms).toContain("input: ['none', 'default', 'filled', 'underline']");
        expect(cms).toContain('component_presets: {');
        expect(cms).toContain("title: 'Advanced: Button Preset (Token-backed)'");
        expect(cms).toContain('enum: [...GENERAL_FOUNDATION_COMPONENT_PRESET_OPTIONS.button]');
        expect(cms).toContain("title: 'Advanced: Card Preset (Token-backed)'");
        expect(cms).toContain('enum: [...GENERAL_FOUNDATION_COMPONENT_PRESET_OPTIONS.card]');
        expect(cms).toContain("title: 'Advanced: Input Preset (Token-backed)'");
        expect(cms).toContain('enum: [...GENERAL_FOUNDATION_COMPONENT_PRESET_OPTIONS.input]');
    });

    it('keeps builder enum select rendering and preview preset application wired to canonical token vars', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const enumStringValues = Array.isArray(field.definition.enum)');
        expect(cms).toContain("if (field.type === 'string' && enumStringValues.length > 0) {");
        expect(cms).toContain('<select');
        expect(cms).toContain('onChange={(event) => options.onChangePath(field.path, event.target.value)}');

        expect(cms).toContain('function readGeneralFoundationComponentPresetSelections(value: unknown): GeneralFoundationComponentPresetSelections {');
        expect(cms).toContain('function applyGeneralFoundationComponentStylePresetsPreview(');
        expect(cms).toContain("const componentPresetSelections = applyGeneralFoundationComponentStylePresetsPreview(container, advancedProps);");
        expect(cms).toContain("'data-webu-builder-component-presets'");
        expect(cms).toContain('`button:${componentPresetSelections.button};card:${componentPresetSelections.card};input:${componentPresetSelections.input}`');
        expect(cms).toContain('var(--webu-token-color-primary, #2563eb)');
        expect(cms).toContain('var(--webu-token-radius-button, var(--webu-token-radius-base, 8px))');
        expect(cms).toContain('var(--webu-token-radius-card, var(--webu-token-radius-base, 12px))');
        expect(cms).toContain("root.style.setProperty(`--webu-token-radius-${cssKey}`, value);");
    });

    it('keeps runtime preset application and token radius var export aligned with preview helpers', () => {
        const service = read(templateImportServicePath);

        expect(service).toContain('function readGeneralComponentPresetSelections(value) {');
        expect(service).toContain('function applyGeneralComponentStylePresetsRuntime(container, advancedProps) {');
        expect(service).toContain('var componentPresetSelections = applyGeneralComponentStylePresetsRuntime(container, advancedProps);');
        expect(service).toContain("'data-webu-runtime-component-presets'");
        expect(service).toContain("'button:' + componentPresetSelections.button + ';card:' + componentPresetSelections.card + ';input:' + componentPresetSelections.input");
        expect(service).toContain("root.style.setProperty('--webu-token-radius-' + cssKey, value);");
        expect(service).toContain("var(--webu-token-color-primary, #2563eb)");
        expect(service).toContain("var(--webu-token-radius-button, var(--webu-token-radius-base, 8px))");
        expect(service).toContain("var(--webu-token-radius-card, var(--webu-token-radius-base, 12px))");
    });

    it('documents D3 preset baseline scope and links to custom-css scoping follow-up', () => {
        const doc = read(d3PresetDocPath);

        expect(doc).toContain('# CMS Reusable Style Presets D3 Baseline');
        expect(doc).toContain('P3-D3-02');
        expect(doc).toContain('advanced.component_presets');
        expect(doc).toContain('data-webu-builder-component-presets');
        expect(doc).toContain('data-webu-runtime-component-presets');
        expect(doc).toContain('--webu-token-radius-*');
        expect(doc).toContain('--webu-token-shadow-*');
        expect(doc).toContain('P3-D3-03');
    });
});
