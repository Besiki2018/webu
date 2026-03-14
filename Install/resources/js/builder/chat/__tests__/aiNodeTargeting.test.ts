import { describe, expect, it } from 'vitest';

import {
    buildAiTargetPromptContext,
    buildScopedSelectedTargetForAi,
    resolveAiNodeTargets,
} from '@/builder/chat/aiNodeTargeting';

const structureItems = [{
    localId: 'hero-1',
    sectionKey: 'webu_general_hero_01',
    label: 'Hero',
    previewText: 'Trusted veterinary care',
    props: {
        title: 'Trusted veterinary care',
        subtitle: 'Compassionate treatment for pets in Tbilisi',
        buttonText: 'Book appointment',
    },
}];

describe('aiNodeTargeting', () => {
    it('resolves @node references to exact builder targets', () => {
        const result = resolveAiNodeTargets({
            message: '@node(hero-1.title)\nMake this title more premium',
            builderStructureItems: structureItems,
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });

        expect(result.unresolvedNodeIds).toEqual([]);
        expect(result.resolvedTargets).toHaveLength(1);
        expect(result.resolvedTargets[0]?.mention.aiNodeId).toBe('hero-1.title');
        expect(result.resolvedTargets[0]?.target.sectionLocalId).toBe('hero-1');
        expect(result.resolvedTargets[0]?.target.path).toBe('title');
    });

    it('builds a union selected target for multiple fields in the same section', () => {
        const result = resolveAiNodeTargets({
            message: '@node(hero-1.title)\n@node(hero-1.subtitle)\nRewrite these texts',
            builderStructureItems: structureItems,
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });

        const selectedTarget = buildScopedSelectedTargetForAi(
            result.resolvedTargets.map((entry) => ({
                ...entry.target,
                pageId: 1,
                pageSlug: 'home',
                pageTitle: 'Home',
            })),
        );

        expect(selectedTarget?.section_id).toBe('hero-1');
        expect(selectedTarget?.parameter_path).toBeNull();
        expect(selectedTarget?.allowed_updates?.fieldPaths).toEqual(expect.arrayContaining(['title', 'subtitle']));
    });

    it('creates explicit prompt context for multiple target nodes', () => {
        const result = resolveAiNodeTargets({
            message: '@node(hero-1.title)\n@node(hero-1.subtitle)\nRewrite these texts',
            builderStructureItems: structureItems,
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });

        const context = buildAiTargetPromptContext(result.resolvedTargets);

        expect(context).toContain('[Target Nodes]');
        expect(context).toContain('hero-1.title');
        expect(context).toContain('path=title');
        expect(context).toContain('hero-1.subtitle');
        expect(context).toContain('path=subtitle');
    });
});
