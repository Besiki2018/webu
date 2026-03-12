import { describe, expect, it, vi } from 'vitest';
import { renderHook } from '@testing-library/react';

import { useCmsCanvasInteractionHandlers } from '@/builder/cms/useCmsCanvasInteractionHandlers';
import { buildEditableTargetFromMessagePayload } from '@/builder/editingState';

function makeSection(localId: string, type = 'webu_general_hero_01') {
    return {
        localId,
        type,
        props: { headline: 'Hello' },
        propsText: '{"headline":"Hello"}',
        propsError: null,
        bindingMeta: null,
    };
}

function buildOptions(overrides: Partial<Parameters<typeof useCmsCanvasInteractionHandlers>[0]> = {}) {
    return {
        sectionsDraft: [],
        setSelectedSectionLocalId: vi.fn(),
        setSelectedFixedSectionKey: vi.fn(),
        setSelectedNestedSection: vi.fn(),
        setSelectedBuilderTarget: vi.fn(),
        setHoveredBuilderTarget: vi.fn(),
        setBuilderHoveredElementId: vi.fn(),
        setBuilderSidebarMode: vi.fn(),
        setSelectedSidebarTab: vi.fn(),
        ...overrides,
    };
}

describe('useCmsCanvasInteractionHandlers', () => {
    it('selects a canvas section by local id and opens the settings sidebar', () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('hero-1')],
        });

        const { result } = renderHook(() => useCmsCanvasInteractionHandlers(options));

        result.current.handleCanvasSelect('hero-1');

        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith('hero-1');
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
        }));
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
    });

    it('updates hovered section identity from the canvas', () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('hero-1')],
        });

        const { result } = renderHook(() => useCmsCanvasInteractionHandlers(options));

        result.current.handleCanvasHover('hero-1');
        result.current.handleCanvasHover(null);

        expect(options.setBuilderHoveredElementId).toHaveBeenNthCalledWith(1, 'hero-1');
        expect(options.setHoveredBuilderTarget).toHaveBeenNthCalledWith(1, expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
        }));
        expect(options.setBuilderHoveredElementId).toHaveBeenNthCalledWith(2, null);
        expect(options.setHoveredBuilderTarget).toHaveBeenNthCalledWith(2, null);
    });

    it('normalizes target clicks back to component selection and sidebar tab state', () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('hero-1')],
        });
        const target = buildEditableTargetFromMessagePayload({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            fieldGroup: 'style',
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });

        const { result } = renderHook(() => useCmsCanvasInteractionHandlers(options));

        result.current.handleCanvasSelectTarget(target!);

        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith('hero-1');
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
        }));
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
        expect(options.setSelectedSidebarTab).toHaveBeenCalledWith('style');
    });

    it('clears selection state when the canvas is deselected', () => {
        const options = buildOptions();

        const { result } = renderHook(() => useCmsCanvasInteractionHandlers(options));

        result.current.handleCanvasDeselect();

        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith(null);
        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(null);
        expect(options.setHoveredBuilderTarget).toHaveBeenCalledWith(null);
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('elements');
    });

    it('focuses the selected section when editing from the canvas overlay', () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('hero-1')],
        });

        const { result } = renderHook(() => useCmsCanvasInteractionHandlers(options));

        result.current.handleCanvasEditSection('hero-1');

        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith('hero-1');
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
        }));
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
    });
});
