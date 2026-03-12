import { describe, expect, it } from 'vitest';

import { resolveMentionBuilderTarget } from '../chatBuilderSelection';

describe('chatBuilderSelection', () => {
    it('resolves inspect mentions to section-scoped targets so chat can edit the full component', () => {
        const result = resolveMentionBuilderTarget({
            element: {
                id: 'HeroSection.title',
                tagName: 'h1',
                selector: '[data-webu-field="title"]',
                textPreview: 'Launch faster',
                sectionKey: 'webu_general_hero_01',
                sectionLocalId: 'hero-1',
                parameterName: 'title',
                componentPath: 'title',
                elementId: 'HeroSection.title',
            },
            builderStructureItems: [{
                localId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                label: 'Hero',
                previewText: 'Launch faster',
                props: {
                    title: 'Launch faster',
                    subtitle: 'Build in minutes',
                    buttonText: 'Get started',
                },
            }],
            currentBreakpoint: 'mobile',
            currentInteractionState: 'hover',
        });

        expect(result.resolvedLocalId).toBe('hero-1');
        expect(result.resolvedSectionKey).toBe('webu_general_hero_01');
        expect(result.target?.sectionLocalId).toBe('hero-1');
        expect(result.target?.path).toBeNull();
        expect(result.target?.componentPath).toBeNull();
        expect(result.target?.allowedUpdates?.scope).toBe('section');
        expect(result.target?.editableFields).toContain('title');
        expect(result.target?.editableFields).toContain('buttonText');
        expect(result.target?.responsiveContext?.currentBreakpoint).toBe('mobile');
        expect(result.target?.responsiveContext?.currentInteractionState).toBe('hover');
    });
});
