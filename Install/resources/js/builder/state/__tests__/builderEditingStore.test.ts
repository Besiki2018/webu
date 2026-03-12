import { beforeEach, describe, expect, it } from 'vitest';

import { resetBuilderEditingStore, useBuilderEditingStore } from '../builderEditingStore';

describe('builderEditingStore', () => {
    beforeEach(() => {
        resetBuilderEditingStore();
    });

    it('keeps derived selection fields in sync with the selected builder target', () => {
        const store = useBuilderEditingStore.getState();

        store.setSelectedBuilderTarget({
            targetId: 'hero-1::title::0',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'HeroSection',
            path: 'title',
            elementId: 'HeroSection.title',
            selector: '[data-builder-id="hero-1::title::0"]',
            textPreview: 'Welcome',
            props: { title: 'Welcome' },
            builderId: 'hero-1::title::0',
            parentId: 'hero-1',
            editableFields: ['title', 'subtitle'],
            sectionId: 'hero-1',
            instanceId: 'hero-1::title::0',
        });

        const next = useBuilderEditingStore.getState();
        expect(next.selectedSectionLocalId).toBe('hero-1');
        expect(next.selectedElementId).toBe('HeroSection.title');
        expect(next.selectedComponentType).toBe('webu_general_hero_01');
        expect(next.selectedComponentName).toBe('HeroSection');
        expect(next.selectedPath).toBe('title');
        expect(next.selectedComponentProps).toEqual({ title: 'Welcome' });
    });

    it('uses builderMode as the single source for sidebar mode aliases', () => {
        const store = useBuilderEditingStore.getState();
        store.setBuilderMode('settings');

        let next = useBuilderEditingStore.getState();
        expect(next.builderMode).toBe('settings');
        expect(next.builderSidebarMode).toBe('settings');

        next.setBuilderSidebarMode('elements');
        next = useBuilderEditingStore.getState();
        expect(next.builderMode).toBe('elements');
        expect(next.builderSidebarMode).toBe('elements');
    });

    it('treats identical bridge selection updates as no-ops', () => {
        const store = useBuilderEditingStore.getState();
        const sharedProps = { headline: 'Welcome' };
        const initialTarget = {
            targetId: 'hero-1::headline',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'HeroSection',
            path: 'headline',
            elementId: 'HeroSection.headline',
            selector: '[data-webu-field="headline"]',
            textPreview: 'Welcome',
            props: sharedProps,
            builderId: 'hero-1::headline',
            parentId: 'hero-1',
            editableFields: ['headline'],
            sectionId: 'hero-1',
            instanceId: 'hero-1::headline',
        } as const;

        store.setSelectedSectionLocalId('hero-1');
        store.setSelectedBuilderTarget(initialTarget);

        const beforeNoop = useBuilderEditingStore.getState();

        beforeNoop.setSelectedSectionLocalId('hero-1');
        beforeNoop.setSelectedBuilderTarget({
            ...initialTarget,
            props: sharedProps,
        });

        const afterNoop = useBuilderEditingStore.getState();
        expect(afterNoop.selectedSectionLocalId).toBe('hero-1');
        expect(afterNoop.selectedBuilderTarget).toBe(beforeNoop.selectedBuilderTarget);
    });

    it('refreshes selected target props when the same selection receives updated draft props', () => {
        const store = useBuilderEditingStore.getState();
        const initialTarget = {
            targetId: 'hero-1::headline',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'HeroSection',
            path: null,
            elementId: null,
            selector: '[data-builder-section-id=\"hero-1\"]',
            textPreview: 'Welcome',
            props: { headline: 'Welcome' },
            builderId: 'hero-1',
            parentId: null,
            editableFields: ['headline'],
            sectionId: 'hero-1',
            instanceId: 'hero-1',
        } as const;

        store.setSelectedSectionLocalId('hero-1');
        store.setSelectedBuilderTarget(initialTarget);

        const beforeRefresh = useBuilderEditingStore.getState();

        beforeRefresh.setSelectedBuilderTarget({
            ...initialTarget,
            props: { headline: 'Welcome again' },
            textPreview: 'Welcome again',
        });

        const afterRefresh = useBuilderEditingStore.getState();
        expect(afterRefresh.selectedBuilderTarget).not.toBe(beforeRefresh.selectedBuilderTarget);
        expect(afterRefresh.selectedBuilderTarget?.props).toEqual({ headline: 'Welcome again' });
        expect(afterRefresh.selectedComponentProps).toEqual({ headline: 'Welcome again' });
    });
});
