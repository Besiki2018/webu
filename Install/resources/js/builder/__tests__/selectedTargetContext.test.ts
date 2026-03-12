import { describe, expect, it } from 'vitest';
import type { BuilderEditableTarget } from '@/builder/editingState';
import { buildEditableTargetFromMention, buildEditableTargetFromMessagePayload } from '@/builder/editingState';
import { buildSelectedTargetContext, selectedTargetIsMappable } from '@/builder/selectedTargetContext';

describe('selectedTargetContext', () => {
    it('builds AI-selected target context from the canonical editable target model', () => {
        const target = buildEditableTargetFromMessagePayload({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            parameterPath: 'ctaLink',
            elementId: 'HeroSection.ctaLink',
            props: {
                ctaLink: {
                    label: 'Start',
                    url: '/start',
                },
            },
            responsiveContext: {
                currentBreakpoint: 'desktop',
                currentInteractionState: 'normal',
                availableBreakpoints: ['desktop', 'tablet', 'mobile'],
                availableInteractionStates: ['normal', 'hover'],
                supportsVisibility: true,
                supportsResponsiveOverrides: true,
                visibleFieldPaths: ['ctaLink.label', 'ctaLink.url'],
                responsiveFieldPaths: ['responsive.mobile.padding_top'],
                stateFieldPaths: ['states.hover.background_color'],
            },
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });

        const context = buildSelectedTargetContext(target, {
            currentBreakpoint: 'mobile',
            currentInteractionState: 'hover',
        });

        expect(context).toMatchObject({
            page_id: null,
            section_id: 'hero-1',
            section_key: 'webu_general_hero_01',
            component_type: 'webu_general_hero_01',
            component_name: 'Hero',
            parameter_path: 'ctaLink',
            component_path: 'ctaLink',
            element_id: 'HeroSection.ctaLink',
            current_breakpoint: 'mobile',
            current_interaction_state: 'hover',
        });
        expect(context?.responsive_context?.currentBreakpoint).toBe('mobile');
        expect(context?.responsive_context?.currentInteractionState).toBe('hover');
    });

    it('treats unmapped targets as not chat-safe and mapped component targets as chat-safe', () => {
        const mapped = buildEditableTargetFromMessagePayload({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            parameterPath: 'headline',
            elementId: 'HeroSection.headline',
            props: { headline: 'Hello' },
        });

        const unmapped = {
            path: null,
            editableFields: [],
            elementId: null,
            allowedUpdates: null,
        } as unknown as BuilderEditableTarget;

        expect(selectedTargetIsMappable(mapped)).toBe(true);
        expect(selectedTargetIsMappable(unmapped)).toBe(false);
    });

    it('preserves exact parameter_path for child target (items.0.title) in AI context', () => {
        const target = buildEditableTargetFromMention(
            {
                id: 'CardsSection.items.0.title',
                tagName: 'h3',
                selector: '[data-webu-field="items.0.title"]',
                textPreview: 'Starter',
                sectionKey: 'webu_general_cards_01',
                sectionLocalId: 'cards-1',
                parameterName: 'items.0.title',
                componentPath: 'items.0',
                elementId: 'CardsSection.items.0.title',
            },
            {
                items: [{ title: 'Starter', description: 'Short copy' }],
            },
        );

        const context = buildSelectedTargetContext(target!);

        expect(context?.parameter_path).toBe('items.0.title');
        expect(context?.component_path).toBe('items.0');
        expect(context?.element_id).toBe('CardsSection.items.0.title');
        expect(context?.allowed_updates?.fieldPaths).toEqual(
            expect.arrayContaining(['items.0.title', 'items.0.description']),
        );
    });
});
