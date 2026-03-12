import { describe, expect, it } from 'vitest';

import {
    areBuilderEditableTargetsEqual,
    buildEditableTargetFromMention,
    buildEditableTargetFromMessagePayload,
    buildSectionScopedEditableTarget,
} from '../editingState';

describe('editingState selected target metadata', () => {
    it('derives variants and exact allowed updates for field targets', () => {
        const target = buildEditableTargetFromMessagePayload({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero Section',
            parameterPath: 'buttonText',
            elementId: 'HeroSection.buttonText',
            props: {
                buttonText: 'Buy now',
                buttonLink: '/shop',
                layoutVariant: 'centered',
                styleVariant: 'soft',
            },
            currentBreakpoint: 'mobile',
            currentInteractionState: 'hover',
        });

        expect(target?.componentPath).toBe('buttonText');
        expect(target?.allowedUpdates?.scope).toBe('element');
        expect(target?.allowedUpdates?.operationTypes).toContain('updateText');
        expect(target?.allowedUpdates?.fieldPaths).toContain('buttonText');
        expect(target?.allowedUpdates?.fieldPaths).toContain('buttonLink');
        expect(target?.variants?.layout?.active).toBe('centered');
        expect(target?.variants?.layout?.options).toContain('split');
        expect(target?.variants?.style?.active).toBe('soft');
        expect(target?.responsiveContext?.currentBreakpoint).toBe('mobile');
        expect(target?.responsiveContext?.currentInteractionState).toBe('hover');
        expect(target?.responsiveContext?.availableBreakpoints).toEqual(['desktop', 'tablet', 'mobile']);
        expect(target?.responsiveContext?.availableInteractionStates).toContain('hover');
        expect(target?.responsiveContext?.stateFieldPaths).toContain('states.hover.background_color');
        expect(target?.responsiveContext?.responsiveFieldPaths).toContain('responsive.hide_on_mobile');
    });

    it('keeps section-wide editable fields available when whole section is selected', () => {
        const target = buildEditableTargetFromMessagePayload({
            sectionLocalId: 'header-1',
            sectionKey: 'webu_header_01',
            componentType: 'webu_header_01',
            componentName: 'Header',
            props: {
                logoText: 'Webu',
                menu_items: '[]',
                ctaText: 'Get started',
            },
        });

        expect(target?.allowedUpdates?.scope).toBe('section');
        expect(target?.allowedUpdates?.sectionOperationTypes).toContain('updateSection');
        expect(target?.allowedUpdates?.sectionFieldPaths).toContain('menu_items');
        expect(target?.variants?.layout?.options).toContain('default');
    });

    it('collapses field-level targets into section-level scope when the builder wants full component editing', () => {
        const target = buildSectionScopedEditableTarget({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero Section',
            parameterPath: 'title',
            componentPath: 'title',
            elementId: 'HeroSection.title',
            props: {
                title: 'Launch faster',
                buttonText: 'Get started',
            },
            currentBreakpoint: 'tablet',
            currentInteractionState: 'focus',
        });

        expect(target?.path).toBeNull();
        expect(target?.componentPath).toBeNull();
        expect(target?.elementId).toBeNull();
        expect(target?.builderId).toBe('hero-1');
        expect(target?.allowedUpdates?.scope).toBe('section');
        expect(target?.allowedUpdates?.sectionFieldPaths).toContain('title');
        expect(target?.allowedUpdates?.sectionFieldPaths).toContain('buttonText');
        expect(target?.responsiveContext?.currentBreakpoint).toBe('tablet');
        expect(target?.responsiveContext?.currentInteractionState).toBe('focus');
    });

    it('uses component scope for compound link targets built from preview mentions', () => {
        const target = buildEditableTargetFromMention({
            id: 'HeaderSection.ctaLink',
            tagName: 'a',
            selector: '[data-webu-field-scope="ctaLink"]',
            textPreview: 'Get started',
            sectionKey: 'webu_header_01',
            sectionLocalId: 'header-1',
            parameterName: 'ctaLink',
            elementId: 'HeaderSection.ctaLink',
        }, {
            ctaLink: {
                label: 'Get started',
                url: '/start',
            },
        });

        expect(target?.path).toBe('ctaLink');
        expect(target?.componentPath).toBe('ctaLink');
        expect(target?.allowedUpdates?.scope).toBe('element');
        expect(target?.allowedUpdates?.fieldPaths).toEqual(expect.arrayContaining([
            'ctaLink',
            'ctaLink.label',
            'ctaLink.url',
        ]));
        expect(target?.responsiveContext?.currentBreakpoint).toBe('desktop');
    });

    it('keeps repeated menu item targets scoped to the clicked item instead of the full section', () => {
        const target = buildEditableTargetFromMention({
            id: 'HeaderSection.menu_items.0',
            tagName: 'a',
            selector: '[data-webu-field-scope="menu_items.0"]',
            textPreview: 'Shop',
            sectionKey: 'webu_header_01',
            sectionLocalId: 'header-1',
            parameterName: 'menu_items.0',
            elementId: 'HeaderSection.menu_items.0',
        }, {
            menu_items: [
                {
                    label: 'Shop',
                    url: '/shop',
                },
            ],
        });

        expect(target?.componentPath).toBe('menu_items.0');
        expect(target?.allowedUpdates?.fieldPaths).toEqual(expect.arrayContaining([
            'menu_items.0',
            'menu_items.0.label',
            'menu_items.0.url',
        ]));
        expect(target?.allowedUpdates?.fieldPaths).not.toContain('menu_items.1.label');
    });

    it('preserves exact field path separately from repeated item scope', () => {
        const target = buildEditableTargetFromMention({
            id: 'HeaderSection.menu_items.0.label',
            tagName: 'span',
            selector: '[data-webu-field="menu_items.0.label"]',
            textPreview: 'Shop',
            sectionKey: 'webu_header_01',
            sectionLocalId: 'header-1',
            parameterName: 'menu_items.0.label',
            componentPath: 'menu_items.0',
            elementId: 'HeaderSection.menu_items.0.label',
        }, {
            menu_items: [
                {
                    label: 'Shop',
                    url: '/shop',
                },
            ],
        });

        expect(target?.path).toBe('menu_items.0.label');
        expect(target?.componentPath).toBe('menu_items.0');
        expect(target?.allowedUpdates?.fieldPaths).toEqual(expect.arrayContaining([
            'menu_items.0.label',
            'menu_items.0.url',
        ]));
    });

    it('treats prop-only changes as the same selection identity', () => {
        const initialTarget = buildSectionScopedEditableTarget({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero Section',
            textPreview: 'Launch faster',
            props: {
                title: 'Launch faster',
            },
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });
        const updatedTarget = buildSectionScopedEditableTarget({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero Section',
            textPreview: 'Launch even faster',
            props: {
                title: 'Launch even faster',
            },
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });

        expect(areBuilderEditableTargetsEqual(initialTarget, updatedTarget)).toBe(true);
    });
});
