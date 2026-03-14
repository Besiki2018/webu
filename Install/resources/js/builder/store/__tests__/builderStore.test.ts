import { beforeEach, describe, expect, it } from 'vitest';

import { useBuilderStore } from '../builderStore';

describe('builderStore', () => {
    beforeEach(() => {
        useBuilderStore.getState().reset();
    });

    it('clears stale selection and hover state when a tree replacement removes the selected node', () => {
        const store = useBuilderStore.getState();

        useBuilderStore.setState({
            componentTree: [{
                id: 'hero-1',
                componentKey: 'webu_general_hero_01',
                props: { title: 'Hero' },
            }],
            selectedComponentId: 'hero-1',
            hoveredComponentId: 'hero-1',
            selectedProps: { title: 'Hero' },
        });

        store.setComponentTree([]);

        const next = useBuilderStore.getState();
        expect(next.componentTree).toEqual([]);
        expect(next.selectedComponentId).toBeNull();
        expect(next.hoveredComponentId).toBeNull();
        expect(next.selectedProps).toBeNull();
    });

    it('keeps the selection stable and refreshes selected props when the same node is updated', () => {
        const store = useBuilderStore.getState();

        useBuilderStore.setState({
            componentTree: [{
                id: 'hero-1',
                componentKey: 'webu_general_hero_01',
                props: { title: 'Old title', variant: 'hero-1' },
            }],
            selectedComponentId: 'hero-1',
            hoveredComponentId: 'hero-1',
            selectedProps: { title: 'Old title', variant: 'hero-1' },
        });

        store.setComponentTree([{
            id: 'hero-1',
            componentKey: 'webu_general_hero_01',
            props: { title: 'Updated title', variant: 'hero-3' },
        }]);

        const next = useBuilderStore.getState();
        expect(next.selectedComponentId).toBe('hero-1');
        expect(next.hoveredComponentId).toBe('hero-1');
        expect(next.selectedProps).toEqual({ title: 'Updated title', variant: 'hero-3' });
    });
});
