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

describe('CMS state overrides parity contracts', () => {
    it('defines canonical normal/hover/focus/active state override schema for general foundation controls', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("type BuilderInteractionPreviewState = 'normal' | 'hover' | 'focus' | 'active';");
        expect(cms).toContain("function buildGeneralFoundationStateStyleOverrideSchema(stateLabel: 'Normal' | 'Hover' | 'Focus' | 'Active')");
        expect(cms).toContain("normal: buildGeneralFoundationStateStyleOverrideSchema('Normal')");
        expect(cms).toContain("hover: buildGeneralFoundationStateStyleOverrideSchema('Hover')");
        expect(cms).toContain("focus: buildGeneralFoundationStateStyleOverrideSchema('Focus')");
        expect(cms).toContain("active: buildGeneralFoundationStateStyleOverrideSchema('Active')");
        expect(cms).toContain('title: `State: ${stateLabel} Background Override`');
    });

    it('keeps builder preview state toggle UI wired to normal/hover/focus/active interactions', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const builderPreviewInteractionState = currentInteractionState as BuilderInteractionPreviewState;');
        expect(cms).toContain('const setBuilderPreviewInteractionState = setCurrentInteractionState as (next: BuilderInteractionPreviewState) => void;');
        expect(cms).toContain('const renderBuilderInteractionStatePreviewControls = (compact: boolean): ReactNode => {');
        expect(cms).toContain("const states: BuilderInteractionPreviewState[] = ['normal', 'hover', 'focus', 'active'];");
        expect(cms).toContain("key={`builder-style-state-${state}`}");
        expect(cms).toContain("variant={builderPreviewInteractionState === state ? 'default' : 'outline'}");
        expect(cms).toContain('onClick={() => setBuilderPreviewInteractionState(state)}');
        expect(cms).toContain('{supportsStates ? renderBuilderInteractionStatePreviewControls(compact) : null}');
    });

    it('applies state style overrides in builder preview on top of base and responsive styles', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const normalStateStyleOverrides = readGeneralFoundationStyleOverrides(options.stateProps.normal);');
        expect(cms).toContain('const hoverStateStyleOverrides = readGeneralFoundationStyleOverrides(options.stateProps.hover);');
        expect(cms).toContain('const focusStateStyleOverrides = readGeneralFoundationStyleOverrides(options.stateProps.focus);');
        expect(cms).toContain('const activeStateStyleOverrides = readGeneralFoundationStyleOverrides(options.stateProps.active);');
        expect(cms).toContain("const interactionStateStyleOverrides = options.interactionState === 'hover'");
        expect(cms).toContain('...options.styleProps,');
        expect(cms).toContain('...activeResponsiveStyleOverrides,');
        expect(cms).toContain('...normalStateStyleOverrides,');
        expect(cms).toContain('...interactionStateStyleOverrides,');
        expect(cms).toContain("container.setAttribute('data-webu-builder-style-order', 'base>responsive>state');");
        expect(cms).toContain("container.setAttribute('data-webu-builder-interaction-state-preview', builderPreviewInteractionState);");
    });
});
