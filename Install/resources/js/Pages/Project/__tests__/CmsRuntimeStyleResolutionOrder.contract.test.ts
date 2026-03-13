import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import { readCurrentBuilderDocs } from './builderContractTestUtils';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const templateImportServicePath = path.join(ROOT, 'app/Services/TemplateImportService.php');
function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS runtime style resolution order contracts (D2)', () => {
    it('keeps builder preview general-foundation style resolution helper with canonical merge precedence', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('interface GeneralFoundationRuntimeStyleResolution {');
        expect(cms).toContain('function readGeneralFoundationStyleOverrides(value: unknown): GeneralFoundationStyleOverrides {');
        expect(cms).toContain('function resolveGeneralFoundationRuntimeStyleResolution(options: {');
        expect(cms).toContain('viewport: BuilderPreviewViewport;');
        expect(cms).toContain('interactionState: BuilderInteractionPreviewState;');
        expect(cms).toContain('const effectiveStyleProps = {');
        expect(cms).toContain('...options.styleProps,');
        expect(cms).toContain('...activeResponsiveStyleOverrides,');
        expect(cms).toContain('...normalStateStyleOverrides,');
        expect(cms).toContain('...interactionStateStyleOverrides,');
        expect(cms).toContain('effective_style: effectiveStyleProps,');
        expect(cms).toContain('const runtimeStyleResolution = resolveGeneralFoundationRuntimeStyleResolution({');
        expect(cms).toContain('const effectiveStyleProps = runtimeStyleResolution.effective_style;');
        expect(cms).toContain("container.setAttribute('data-webu-builder-style-order', 'base>responsive>state');");
    });

    it('keeps published/runtime script merge order aligned and exposes runtime style-order marker', () => {
        const service = read(templateImportServicePath);

        expect(service).toContain("var activeResponsiveStyleOverrides = viewportMode === 'mobile'");
        expect(service).toContain("var interactionStateStyleOverrides = runtimeInteractionState === 'hover'");
        expect(service).toContain('var effectiveStyleProps = Object.assign(');
        expect(service).toContain('styleProps,');
        expect(service).toContain('activeResponsiveStyleOverrides,');
        expect(service).toContain('normalStateStyleOverrides,');
        expect(service).toContain('interactionStateStyleOverrides');
        expect(service).toContain("container.setAttribute('data-webu-runtime-style-order', 'base>responsive>state');");
    });

    it('documents D2 runtime style resolution order and builder/runtime parity markers', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('Props updates');
        expect(doc).toContain('RUNTIME_VERIFICATION.md');
    });
});
