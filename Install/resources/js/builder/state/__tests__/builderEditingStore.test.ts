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
        expect(next.selectedNodeId).toBe('hero-1::title::0');
        expect(next.selectedElementId).toBe('HeroSection.title');
        expect(next.selectedComponentType).toBe('webu_general_hero_01');
        expect(next.selectedComponentName).toBe('HeroSection');
        expect(next.selectedSchemaKey).toBe('webu_general_hero_01');
        expect(next.selectedPath).toBe('title');
        expect(next.selectedPropPaths).toContain('title');
        expect(next.selectedPropPaths).toContain('subtitle');
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
            selector: '[data-builder-section-id="hero-1"]',
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

    it('clears stale hover state when applyMutationState removes the hovered section', () => {
        const store = useBuilderEditingStore.getState();
        store.setBuilderHoveredElementId('hero-1');
        store.setHoveredBuilderTarget({
            targetId: 'hero-1::section',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            path: null,
            elementId: null,
            selector: '[data-webu-section-local-id="hero-1"]',
            textPreview: 'Hero',
            props: { headline: 'Hero' },
        });

        store.applyMutationState({
            sectionsDraft: [{
                localId: 'hero-2',
                type: 'webu_general_text_01',
                propsText: JSON.stringify({ body: 'Hello' }),
                propsError: null,
                bindingMeta: null,
            }],
            selectedSectionLocalId: null,
            selectedBuilderTarget: null,
        });

        const next = useBuilderEditingStore.getState();
        expect(next.hoveredBuilderTarget).toBeNull();
        expect(next.hoveredTargetId).toBeNull();
        expect(next.hoveredElementId).toBeNull();
        expect(next.builderHoveredElementId).toBeNull();
    });

    it('clears both selected and hovered targets when the selection is reset', () => {
        const store = useBuilderEditingStore.getState();
        store.selectTarget({
            targetId: 'hero-1::section',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            path: null,
            elementId: null,
            selector: '[data-webu-section-local-id="hero-1"]',
            textPreview: 'Hero',
            props: { headline: 'Hero' },
        });
        store.hoverTarget({
            targetId: 'hero-1::title',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            path: 'title',
            elementId: 'Hero.title',
            selector: '[data-webu-field="title"]',
            textPreview: 'Hello',
            props: { title: 'Hello' },
        });
        store.setBuilderHoveredElementId('hero-1');

        store.clearSelection();

        const next = useBuilderEditingStore.getState();
        expect(next.selectedBuilderTarget).toBeNull();
        expect(next.selectedSectionLocalId).toBeNull();
        expect(next.hoveredBuilderTarget).toBeNull();
        expect(next.hoveredTargetId).toBeNull();
        expect(next.hoveredElementId).toBeNull();
        expect(next.builderHoveredElementId).toBeNull();
        expect(next.activeDragId).toBeNull();
        expect(next.builderCurrentDropTarget).toBeNull();
    });

    it('honors explicit remote selection clears instead of keeping stale local targets', () => {
        const store = useBuilderEditingStore.getState();
        store.applyMutationState({
            sectionsDraft: [{
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                propsText: JSON.stringify({ headline: 'Hello' }),
                propsError: null,
                bindingMeta: null,
            }],
            selectedSectionLocalId: 'hero-1',
            selectedBuilderTarget: {
                targetId: 'hero-1::section',
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                path: null,
                elementId: null,
                selector: '[data-webu-section-local-id="hero-1"]',
                textPreview: 'Hero',
                props: { headline: 'Hello' },
            },
        });

        store.syncFromRemote({
            selectedSectionLocalId: null,
            selectedBuilderTarget: null,
            hoveredBuilderTarget: null,
        });

        const next = useBuilderEditingStore.getState();
        expect(next.selectedBuilderTarget).toBeNull();
        expect(next.selectedSectionLocalId).toBeNull();
        expect(next.hoveredBuilderTarget).toBeNull();
        expect(next.builderHoveredElementId).toBeNull();
    });

    it('clears raw hovered element ids when hover state is explicitly reset', () => {
        const store = useBuilderEditingStore.getState();
        store.setBuilderHoveredElementId('hero-1');
        store.setHoveredBuilderTarget({
            targetId: 'hero-1::section',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            path: null,
            elementId: null,
            selector: '[data-webu-section-local-id="hero-1"]',
            textPreview: 'Hero',
            props: { headline: 'Hello' },
        });

        store.setHoveredBuilderTarget(null);

        const next = useBuilderEditingStore.getState();
        expect(next.hoveredBuilderTarget).toBeNull();
        expect(next.hoveredTargetId).toBeNull();
        expect(next.hoveredElementId).toBeNull();
        expect(next.builderHoveredElementId).toBeNull();
    });

    it('reconciles stale selection and hover state when sectionsDraft changes directly', () => {
        const store = useBuilderEditingStore.getState();
        store.selectTarget({
            targetId: 'hero-1::section',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            path: null,
            elementId: null,
            selector: '[data-webu-section-local-id="hero-1"]',
            textPreview: 'Hero',
            props: { headline: 'Hero' },
        });
        store.hoverTarget({
            targetId: 'hero-1::title',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            path: 'title',
            elementId: 'Hero.title',
            selector: '[data-webu-field="title"]',
            textPreview: 'Hello',
            props: { title: 'Hello' },
        });
        store.setBuilderHoveredElementId('hero-1');

        store.setSectionsDraft([{
            localId: 'hero-2',
            type: 'webu_general_text_01',
            propsText: JSON.stringify({ body: 'Hello' }),
            propsError: null,
            bindingMeta: null,
        }]);

        const next = useBuilderEditingStore.getState();
        expect(next.selectedBuilderTarget).toBeNull();
        expect(next.selectedSectionLocalId).toBeNull();
        expect(next.hoveredBuilderTarget).toBeNull();
        expect(next.hoveredTargetId).toBeNull();
        expect(next.hoveredElementId).toBeNull();
        expect(next.builderHoveredElementId).toBeNull();
    });

    it('refreshes selected target props from the normalized sections draft without changing the selected node id', () => {
        const store = useBuilderEditingStore.getState();

        store.selectTarget({
            targetId: 'hero-1::headline',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            path: 'headline',
            elementId: 'Hero.headline',
            selector: '[data-webu-field="headline"]',
            textPreview: 'Old headline',
            props: { headline: 'Old headline' },
            builderId: 'hero-1::headline',
            parentId: 'hero-1',
            editableFields: ['headline'],
            sectionId: 'hero-1',
            instanceId: 'hero-1::headline',
        });

        const before = useBuilderEditingStore.getState();
        expect(before.selectedNodeId).toBe('hero-1::headline');

        store.setSectionsDraft([{
            localId: 'hero-1',
            type: 'webu_general_hero_01',
            props: {
                headline: 'Updated headline',
            },
            propsText: JSON.stringify({
                headline: 'Updated headline',
            }),
            propsError: null,
            bindingMeta: null,
        }]);

        const next = useBuilderEditingStore.getState();
        expect(next.selectedNodeId).toBe('hero-1::headline');
        expect(next.selectedBuilderTarget?.props).toMatchObject({
            headline: 'Updated headline',
        });
        expect(next.selectedBuilderTarget?.textPreview).toBe('Updated headline');
        expect(next.selectedComponentProps).toMatchObject({
            headline: 'Updated headline',
        });
    });
});
