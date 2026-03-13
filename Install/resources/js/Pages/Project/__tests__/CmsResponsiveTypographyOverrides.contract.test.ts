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

describe('CMS responsive typography override contracts (D2 baseline)', () => {
    it('keeps normalized typography responsive override schema and parser/sanitizer wiring', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("type BuilderPreviewViewport = 'desktop' | 'tablet' | 'mobile';");
        expect(cms).toContain('type TextTypographyResponsiveOverrides = Partial<Record<BuilderPreviewViewport, TextTypographyScalarStyle>>;');
        expect(cms).toContain('responsive?: TextTypographyResponsiveOverrides;');
        expect(cms).toContain('function parseTextTypographyScalarStyle(value: Record<string, unknown>): TextTypographyScalarStyle | null {');
        expect(cms).toContain('const responsiveRaw = isRecord(value.responsive) ? value.responsive : null;');
        expect(cms).toContain("(['desktop', 'tablet', 'mobile'] as const).forEach((viewport) => {");
        expect(cms).toContain('const sanitizeTypographyScalarStyleForStorage = useCallback(');
        expect(cms).toContain('const sanitizeTypographyStyleForStorage = useCallback((input: TextTypographyStyle | null): TextTypographyStyle | null => {');
        expect(cms).toContain('input.responsive?.[viewport]');
    });

    it('applies responsive typography target editing and preview viewport resolution in builder UI', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const activeTypographyViewport: BuilderPreviewViewport = builderPreviewMode;');
        expect(cms).toContain('const applyTypographyTargetUpdate = (');
        expect(cms).toContain('data-webu-role="builder-typography-responsive-target"');
        expect(cms).toContain("{t('Responsive Target')}");
        expect(cms).toContain("t('Blank values inherit from desktop base style.')");
        expect(cms).toContain("t('Desktop edits define the base typography style.')");
        expect(cms).toContain("{t('Reset')}");
        expect(cms).toContain('resolveTextTypographyStyleForViewportAndInteractionState(');
    });

    it('documents the current canonical registry and mutation pipeline instead of the removed D2 baseline note', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('Sidebar generates controls from schema');
        expect(doc).toContain('Props updates');
    });
});
