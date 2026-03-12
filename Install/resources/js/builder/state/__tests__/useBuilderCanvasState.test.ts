import { beforeEach, describe, it, expect } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useBuilderCanvasState } from '../useBuilderCanvasState';
import { resetBuilderEditingStore } from '../builderEditingStore';

describe('useBuilderCanvasState', () => {
    beforeEach(() => {
        resetBuilderEditingStore();
    });

    it('returns initial canvas state', () => {
        const { result } = renderHook(() => useBuilderCanvasState());

        expect(result.current.sectionsDraft).toEqual([]);
        expect(result.current.selectedSectionLocalId).toBeNull();
        expect(result.current.selectedBuilderTarget).toBeNull();
        expect(result.current.hoveredBuilderTarget).toBeNull();
        expect(result.current.selectedElementId).toBeNull();
        expect(result.current.selectedComponentType).toBeNull();
        expect(result.current.selectedComponentProps).toBeNull();
        expect(result.current.selectedPath).toBeNull();
        expect(result.current.activeDragId).toBeNull();
        expect(result.current.builderHoveredElementId).toBeNull();
        expect(result.current.hoveredElementId).toBeNull();
        expect(result.current.builderCurrentDropTarget).toBeNull();
        expect(result.current.builderMode).toBe('elements');
        expect(result.current.currentBreakpoint).toBe('desktop');
        expect(result.current.currentInteractionState).toBe('normal');
        expect(result.current.selectedSidebarTab).toBe('content');
        expect(result.current.builderSidebarMode).toBe('elements');
        expect(result.current.isStructurePanelCollapsed).toBe(false);
        expect(result.current.structurePanelPosition).toEqual({ x: 24, y: 72 });
    });

    it('updates selection and sections', () => {
        const { result } = renderHook(() => useBuilderCanvasState());

        act(() => {
            result.current.setSelectedSectionLocalId('section-1');
            result.current.setSelectedBuilderTarget({
                targetId: 'section-1::title',
                sectionLocalId: 'section-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'HeroSection',
                path: 'title',
                elementId: 'HeroSection.title',
                selector: '[data-webu-field=\"title\"]',
                textPreview: 'Welcome',
                props: { title: 'Welcome' },
            });
        });
        expect(result.current.selectedSectionLocalId).toBe('section-1');
        expect(result.current.selectedComponentType).toBe('webu_general_hero_01');
        expect(result.current.selectedPath).toBe('title');
        expect(result.current.selectedElementId).toBe('HeroSection.title');
        expect(result.current.selectedComponentProps).toEqual({ title: 'Welcome' });

        act(() => {
            result.current.setSectionsDraft([
                { localId: 's1', type: 'hero', propsText: '{}', propsError: null },
            ]);
        });
        expect(result.current.sectionsDraft).toHaveLength(1);
        expect(result.current.sectionsDraft[0].localId).toBe('s1');
    });
});
