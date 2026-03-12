import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const d2DocPath = path.join(ROOT, 'docs/qa/CMS_STATE_CONTROLS_D2_BASELINE.md');

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
        expect(cms).toContain("t('Reset State Override')");
    });

    it('documents D2 state control baseline and normalized typography state override behavior', () => {
        const doc = read(d2DocPath);

        expect(doc).toContain('# CMS State Controls D2 Baseline');
        expect(doc).toContain('P3-D2-02');
        expect(doc).toContain('states.hover');
        expect(doc).toContain('states.focus');
        expect(doc).toContain('states.active');
        expect(doc).toContain('Normal / Hover / Focus / Active');
        expect(doc).toContain('State overrides are viewport-agnostic');
        expect(doc).toContain('selected page section editor controls');
        expect(doc).toContain('fixed header/footer editor controls');
    });
});
