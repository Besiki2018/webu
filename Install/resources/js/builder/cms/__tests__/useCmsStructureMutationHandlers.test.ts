import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { DragEndEvent } from '@dnd-kit/core';
import { toast } from 'sonner';

import { useCmsStructureMutationHandlers } from '@/builder/cms/useCmsStructureMutationHandlers';
import type { CmsNestedSelection } from '@/builder/cms/useCmsSelectionStateSync';
import type { BuilderEditableTarget } from '@/builder/editingState';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}));

function makeSection(localId: string, type = 'webu_general_hero_01', props: Record<string, unknown> = { headline: 'Hero' }): SectionDraft {
    return {
        localId,
        type,
        propsText: JSON.stringify(props, null, 2),
        propsError: null,
        bindingMeta: null,
    };
}

function createStateUpdater<T>(initialValue: T) {
    let currentValue = initialValue;
    const setter = vi.fn((value: T | ((current: T) => T)) => {
        currentValue = typeof value === 'function'
            ? (value as (current: T) => T)(currentValue)
            : value;
    });

    return {
        setter,
        getCurrent: () => currentValue,
    };
}

function buildHarness(overrides: {
    sectionsDraft?: SectionDraft[];
    selectedSectionLocalId?: string | null;
    selectedNestedSectionParentLocalId?: string | null;
    selectedBuilderTarget?: BuilderEditableTarget | null;
} = {}) {
    let nextGeneratedId = 1;
    let sectionsDraft = overrides.sectionsDraft ?? [];
    const sectionsDraftRef = { current: sectionsDraft };
    const selectedSectionState = createStateUpdater<string | null>(overrides.selectedSectionLocalId ?? null);
    const fixedSectionState = createStateUpdater<string | null>(null);
    const nestedSelectionState = createStateUpdater<CmsNestedSelection | null>(null);
    const selectedBuilderTargetState = createStateUpdater<BuilderEditableTarget | null>(overrides.selectedBuilderTarget ?? null);

    const setSectionsDraft = vi.fn((value: SectionDraft[] | ((current: SectionDraft[]) => SectionDraft[])) => {
        sectionsDraft = typeof value === 'function'
            ? (value as (current: SectionDraft[]) => SectionDraft[])(sectionsDraft)
            : value;
        sectionsDraftRef.current = sectionsDraft;
    });

    const scheduleStructuralDraftPersist = vi.fn();
    const applyMutationState = vi.fn((next: {
        sectionsDraft: SectionDraft[];
        selectedSectionLocalId: string | null;
        selectedBuilderTarget: BuilderEditableTarget | null;
    }) => {
        sectionsDraft = next.sectionsDraft;
        sectionsDraftRef.current = next.sectionsDraft;
        selectedSectionState.setter(next.selectedSectionLocalId);
        selectedBuilderTargetState.setter(next.selectedBuilderTarget);
    });
    const syncPreviewVisibleSections = vi.fn();
    const options = {
        sectionsDraftRef,
        scheduleStructuralDraftPersistRef: { current: scheduleStructuralDraftPersist },
        syncPreviewVisibleSections,
        nextSectionLocalId: vi.fn(() => `generated-${nextGeneratedId++}`),
        selectedSectionLocalId: overrides.selectedSectionLocalId ?? null,
        selectedBuilderTarget: overrides.selectedBuilderTarget ?? null,
        selectedNestedSectionParentLocalId: overrides.selectedNestedSectionParentLocalId ?? null,
        isEmbeddedSidebarMode: false,
        canvasDropId: 'canvas-drop',
        visualDropId: 'visual-drop',
        sectionSchemaByKey: new Map<string, unknown>([
            ['webu_general_hero_01', { properties: { headline: { type: 'string' } } }],
            ['webu_general_text_01', { properties: { content: { type: 'string' } } }],
        ]),
        templateSectionPreviewByKey: new Map([
            ['webu_general_text_01', { defaultProps: { content: 'Text' } }],
        ]),
        sectionDisplayLabelByKey: new Map([
            ['webu_general_hero_01', 'Hero'],
            ['webu_general_text_01', 'Text'],
        ]),
        hydrateSectionDefaultsFromCms: vi.fn((_sectionType: string, props: Record<string, unknown>) => props),
        normalizeSectionTypeKey: (key: string) => key.trim().toLowerCase(),
        isFixedLayoutSectionKey: vi.fn(() => false),
        buildPropsFromSchema: vi.fn((schema: unknown) => {
            if (schema && typeof schema === 'object' && 'properties' in (schema as Record<string, unknown>)) {
                const properties = (schema as { properties?: Record<string, unknown> }).properties ?? {};
                if ('content' in properties) {
                    return { content: 'Text' };
                }
            }
            return { headline: 'Hero' };
        }),
        cloneRecord: (input: Record<string, unknown>) => JSON.parse(JSON.stringify(input)) as Record<string, unknown>,
        formatPropsText: (value: unknown) => JSON.stringify(value, null, 2),
        ensurePreviewSectionVisibility: vi.fn(),
        extractLibrarySectionKey: (dragId: string) => dragId.startsWith('library:') ? dragId.slice('library:'.length) : null,
        setSectionsDraft,
        applyMutationState,
        setSelectedFixedSectionKey: fixedSectionState.setter,
        setSelectedNestedSection: nestedSelectionState.setter,
        setBuilderSidebarMode: vi.fn(),
        setActiveDragId: vi.fn(),
        setBuilderCurrentDropTarget: vi.fn(),
        t: (key: string) => key,
    };

    return {
        options,
        getSectionsDraft: () => sectionsDraft,
        getSelectedSectionLocalId: () => selectedSectionState.getCurrent(),
        getSelectedFixedSectionKey: () => fixedSectionState.getCurrent(),
        getSelectedNestedSection: () => nestedSelectionState.getCurrent(),
        getSelectedBuilderTarget: () => selectedBuilderTargetState.getCurrent(),
        applyMutationState,
        syncPreviewVisibleSections,
        scheduleStructuralDraftPersist,
    };
}

describe('useCmsStructureMutationHandlers', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('adds a new section, selects it, and opens the settings sidebar', () => {
        const harness = buildHarness();
        const { result } = renderHook(() => useCmsStructureMutationHandlers(harness.options));

        act(() => {
            result.current.addSectionByKey('webu_general_hero_01', 'library');
        });

        expect(harness.getSectionsDraft()).toHaveLength(1);
        expect(harness.getSectionsDraft()[0]).toMatchObject({
            localId: 'generated-1',
            type: 'webu_general_hero_01',
        });
        expect(harness.getSelectedSectionLocalId()).toBe('generated-1');
        expect(harness.getSelectedFixedSectionKey()).toBeNull();
        expect(harness.getSelectedNestedSection()).toBeNull();
        expect(harness.getSelectedBuilderTarget()).toEqual(expect.objectContaining({
            sectionLocalId: 'generated-1',
            sectionKey: 'webu_general_hero_01',
        }));
        expect(harness.options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
        expect(harness.applyMutationState).toHaveBeenCalledTimes(1);
        expect(harness.scheduleStructuralDraftPersist).toHaveBeenCalledTimes(1);
        expect(toast.success).toHaveBeenCalledWith('Component added to page canvas');
    });

    it('removes the selected section and resets the sidebar back to elements', () => {
        const harness = buildHarness({
            sectionsDraft: [makeSection('hero-1'), makeSection('hero-2')],
            selectedSectionLocalId: 'hero-1',
        });
        const { result } = renderHook(() => useCmsStructureMutationHandlers(harness.options));

        act(() => {
            result.current.handleRemoveSection('hero-1');
        });

        expect(harness.getSectionsDraft().map((section) => section.localId)).toEqual(['hero-2']);
        expect(harness.getSelectedSectionLocalId()).toBeNull();
        expect(harness.getSelectedNestedSection()).toBeNull();
        expect(harness.options.setBuilderSidebarMode).toHaveBeenCalledWith('elements');
        expect(harness.syncPreviewVisibleSections).toHaveBeenCalledTimes(1);
        expect(harness.scheduleStructuralDraftPersist).toHaveBeenCalledTimes(1);
    });

    it('preserves the current selection when deleting a different section', () => {
        const selectedTarget: BuilderEditableTarget = {
            targetId: 'hero-2::section',
            sectionLocalId: 'hero-2',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            path: null,
            elementId: null,
            selector: '[data-webu-section-local-id="hero-2"]',
            textPreview: 'Hero 2',
            props: { headline: 'Hero 2' },
        };
        const harness = buildHarness({
            sectionsDraft: [makeSection('hero-1'), makeSection('hero-2')],
            selectedSectionLocalId: 'hero-2',
            selectedBuilderTarget: selectedTarget,
        });
        const { result } = renderHook(() => useCmsStructureMutationHandlers(harness.options));

        act(() => {
            result.current.handleRemoveSection('hero-1');
        });

        expect(harness.getSectionsDraft().map((section) => section.localId)).toEqual(['hero-2']);
        expect(harness.getSelectedSectionLocalId()).toBe('hero-2');
        expect(harness.getSelectedBuilderTarget()).toEqual(expect.objectContaining({
            sectionLocalId: 'hero-2',
        }));
    });

    it('adds a nested section inside a layout section', () => {
        const harness = buildHarness({
            sectionsDraft: [makeSection('layout-1', 'container', { sections: [] })],
        });
        const { result } = renderHook(() => useCmsStructureMutationHandlers(harness.options));

        act(() => {
            result.current.handleAddSectionInside('layout-1', 'webu_general_text_01');
        });

        const updatedProps = JSON.parse(harness.getSectionsDraft()[0].propsText) as { sections?: Array<Record<string, unknown>> };
        expect(updatedProps.sections).toEqual([
            {
                type: 'webu_general_text_01',
                props: { content: 'Text' },
            },
        ]);
        expect(harness.scheduleStructuralDraftPersist).toHaveBeenCalledTimes(1);
        expect(toast.success).toHaveBeenCalledWith('Section added inside');
    });

    it('inserts a dragged library component after the selected section when dropped on the canvas', () => {
        const harness = buildHarness({
            sectionsDraft: [makeSection('hero-1'), makeSection('hero-2')],
            selectedSectionLocalId: 'hero-1',
        });
        const { result } = renderHook(() => useCmsStructureMutationHandlers(harness.options));

        act(() => {
            result.current.handleBuilderDragEnd({
                active: { id: 'library:webu_general_text_01' },
                over: { id: 'canvas-drop' },
            } as unknown as DragEndEvent);
        });

        expect(harness.getSectionsDraft().map((section) => section.localId)).toEqual([
            'hero-1',
            'generated-1',
            'hero-2',
        ]);
        expect(harness.getSelectedSectionLocalId()).toBe('generated-1');
        expect(harness.getSelectedBuilderTarget()).toEqual(expect.objectContaining({
            sectionLocalId: 'generated-1',
            sectionKey: 'webu_general_text_01',
        }));
        expect(harness.options.setActiveDragId).toHaveBeenCalledWith(null);
        expect(harness.options.setBuilderCurrentDropTarget).toHaveBeenCalledWith(null);
    });
});
