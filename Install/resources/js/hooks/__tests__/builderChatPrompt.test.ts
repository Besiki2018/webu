import { describe, expect, it } from 'vitest';
import { buildBuilderChatPrompt } from '@/hooks/builderChatPrompt';

describe('buildBuilderChatPrompt', () => {
    it('keeps plain prompts unchanged when there is no selected element context', () => {
        expect(buildBuilderChatPrompt('  Update hero text  ')).toBe('Update hero text');
    });

    it('adds scoped component metadata for chat-safe builder edits', () => {
        const prompt = buildBuilderChatPrompt('Make this heading smaller', {
            aiNodeId: 'hero-1.title',
            tagName: 'h1',
            selector: '[data-webu-field="title"]',
            textPreview: 'Storefront hero',
            currentValue: 'Storefront hero',
            componentType: 'webu_general_hero_01',
            componentPath: 'title',
            elementId: 'HeroSection.title',
            editableFields: ['title', 'title_typography'],
            allowedUpdates: {
                scope: 'element',
                operationTypes: ['updateText'],
                fieldPaths: ['title', 'title_typography'],
                sectionOperationTypes: ['updateText', 'updateSection'],
                sectionFieldPaths: ['title', 'title_typography', 'description'],
            },
            currentBreakpoint: 'mobile',
            currentInteractionState: 'hover',
            responsiveContext: {
                currentBreakpoint: 'mobile',
                currentInteractionState: 'hover',
                availableBreakpoints: ['desktop', 'tablet', 'mobile'],
                availableInteractionStates: ['normal', 'hover'],
                supportsVisibility: true,
                supportsResponsiveOverrides: true,
                visibleFieldPaths: ['title', 'title_typography'],
                responsiveFieldPaths: ['responsive.mobile.padding_top'],
                stateFieldPaths: ['states.hover.background_color'],
            },
        });

        expect(prompt).toContain('[Selected Element]');
        expect(prompt).toContain('AI Node ID: hero-1.title');
        expect(prompt).toContain('Current Value: Storefront hero');
        expect(prompt).toContain('Component Type: webu_general_hero_01');
        expect(prompt).toContain('Component Path: title');
        expect(prompt).toContain('Allowed Field Paths: title, title_typography');
        expect(prompt).toContain('Current Breakpoint: mobile');
        expect(prompt).toContain('Current Interaction State: hover');
        expect(prompt).toContain('Responsive Field Paths (mobile): responsive.mobile.padding_top');
        expect(prompt).toContain('State Field Paths (hover): states.hover.background_color');
    });

    it('renders multiple selected node contexts deterministically', () => {
        const prompt = buildBuilderChatPrompt(
            'Rewrite both texts',
            undefined,
            [
                {
                    aiNodeId: 'hero-1.title',
                    tagName: 'h1',
                    selector: '[data-webu-field="title"]',
                    textPreview: 'Trusted veterinary care',
                    currentValue: 'Trusted veterinary care',
                },
                {
                    aiNodeId: 'hero-1.subtitle',
                    tagName: 'p',
                    selector: '[data-webu-field="subtitle"]',
                    textPreview: 'Compassionate treatment for pets',
                    currentValue: 'Compassionate treatment for pets',
                },
            ],
        );

        expect(prompt).toContain('[Selected Element 1]');
        expect(prompt).toContain('[Selected Element 2]');
        expect(prompt).toContain('AI Node ID: hero-1.title');
        expect(prompt).toContain('AI Node ID: hero-1.subtitle');
    });
});
