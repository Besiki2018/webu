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

describe('CMS Phase 3 responsive/state wrapper summary contracts', () => {
    it('keeps canonical responsive and state builder target UI markers in Cms.tsx', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain('data-webu-role="builder-typography-responsive-target"');
        expect(cms).toContain('data-webu-role="builder-typography-state-target"');
        expect(cms).toContain("const activeTypographyViewport: BuilderPreviewViewport = builderPreviewMode;");
        expect(cms).toContain("const activeTypographyInteractionState: BuilderInteractionPreviewState = builderPreviewInteractionState;");
        expect(cms).toContain("t('Desktop')");
        expect(cms).toContain("t('Tablet')");
        expect(cms).toContain("t('Mobile')");
        expect(cms).toContain("t('Hover')");
        expect(cms).toContain("t('Focus')");
        expect(cms).toContain("t('Active')");
    });

    it('documents wrapper-level responsive/state closure semantics and related wrapper evidence locks', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('schema-driven builder');
        expect(doc).toContain('BuilderCanvas');
    });

    it('keeps D2/D3 evidence references and parity contract links in wrapper summary doc', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('RUNTIME_VERIFICATION.md');
        expect(doc).toContain('Schema-Driven Builder Architecture — Verification Summary');
        expect(doc).toContain('Phase 10 — Migration Report');
    });
});
