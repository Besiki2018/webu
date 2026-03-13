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

describe('CMS typography state override contracts (D2 baseline)', () => {
    it('keeps normalized typography state override schema and parser/sanitizer wiring', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("type TextTypographyInteractionStateKey = Exclude<BuilderInteractionPreviewState, 'normal'>;");
        expect(cms).toContain('type TextTypographyStateOverrides = Partial<Record<TextTypographyInteractionStateKey, TextTypographyScalarStyle>>;');
        expect(cms).toContain('states?: TextTypographyStateOverrides;');
        expect(cms).toContain('const statesRaw = isRecord(value.states) ? value.states : null;');
        expect(cms).toContain("(['hover', 'focus', 'active'] as const).forEach((interactionState) => {");
        expect(cms).toContain('const states: TextTypographyStateOverrides = {};');
        expect(cms).toContain('input.states?.[interactionState]');
    });

    it('resolves typography preview state and exposes state-target editing UI in builder controls', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('function resolveTextTypographyStyleForViewportAndInteractionState(');
        expect(cms).toContain('interactionState: BuilderInteractionPreviewState,');
        expect(cms).toContain("const stateResolvedStyle = interactionState === 'normal'");
        expect(cms).toContain("style?.states?.[interactionState] ?? null");
        expect(cms).toContain('resolveTextTypographyStyleForViewportAndInteractionState(');
        expect(cms).toContain('builderPreviewInteractionState');
        expect(cms).toContain('const activeTypographyInteractionState: BuilderInteractionPreviewState = builderPreviewInteractionState;');
        expect(cms).toContain('data-webu-role="builder-typography-state-target"');
        expect(cms).toContain("{t('State Target')}");
        expect(cms).toContain("t('State overrides apply after responsive/base resolution.')");
        expect(cms).toContain("{t('Reset')}");
    });

    it('documents the current canonical registry and mutation pipeline instead of the removed D2 state baseline note', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('Sidebar generates controls from schema');
        expect(doc).toContain('Props updates');
    });
});
