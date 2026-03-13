import { act, renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { useCmsEmbeddedBuilderMutationHandlers } from '@/builder/cms/useCmsEmbeddedBuilderMutationHandlers';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

function makeSection(localId: string, type = 'webu_general_hero_01'): SectionDraft {
    return {
        localId,
        type,
        propsText: JSON.stringify({
            title: `${localId} title`,
            buttonLink: `/${localId}`,
            image: `/${localId}.jpg`,
        }),
        propsError: null,
        bindingMeta: null,
    };
}

function buildOptions(overrides: Partial<Parameters<typeof useCmsEmbeddedBuilderMutationHandlers>[0]> = {}) {
    return {
        isEmbeddedMode: true,
        pageEditorMode: 'builder',
        selectedPageId: 11,
        selectedSectionLocalId: null,
        selectedBuilderTarget: null,
        sectionsDraftRef: {
            current: [makeSection('hero-1'), makeSection('hero-2')],
        },
        saveDraftRevisionInternalRef: {
            current: vi.fn(async () => 1),
        },
        scheduleStructuralDraftPersistRef: {
            current: vi.fn(),
        },
        setPageEditorMode: vi.fn(),
        setSectionsDraft: vi.fn(),
        setSelectedSectionLocalId: vi.fn(),
        setSelectedNestedSection: vi.fn(),
        setSelectedFixedSectionKey: vi.fn(),
        setBuilderSidebarMode: vi.fn(),
        normalizeSectionTypeKey: (key: string) => key.trim().toLowerCase(),
        isHeaderSectionKey: vi.fn(() => false),
        isFooterSectionKey: vi.fn(() => false),
        createBuilderSectionDraft: vi.fn(),
        applyMutationState: vi.fn(),
        addSectionByKey: vi.fn(),
        handleAddSectionInside: vi.fn(),
        handleRemoveSection: vi.fn(),
        t: (key: string) => key,
        ...overrides,
    };
}

describe('useCmsEmbeddedBuilderMutationHandlers', () => {
    it('forwards stable local ids to addSectionByKey without double-scheduling structural persistence', () => {
        const options = buildOptions();
        const emit = vi.fn();
        const { result } = renderHook(() => useCmsEmbeddedBuilderMutationHandlers(options));

        act(() => {
            result.current.handleEmbeddedBuilderAddSection({
                requestId: 'req-add-1',
                sectionKey: 'webu_general_text_01',
                sectionLocalId: 'builder-section-1',
                afterSectionLocalId: 'hero-1',
                placement: 'after',
            }, emit);
        });

        expect(options.addSectionByKey).toHaveBeenCalledWith('webu_general_text_01', 'library', {
            insertIndex: 1,
            localId: 'builder-section-1',
        });
        expect(options.scheduleStructuralDraftPersistRef.current).not.toHaveBeenCalled();
        expect(emit).toHaveBeenCalledWith(expect.objectContaining({
            type: 'builder:mutation-result',
            requestId: 'req-add-1',
            mutation: 'add-section',
            success: true,
            changed: true,
        }));
    });

    it('applies text, link, and image change sets through one mutation state update', () => {
        const options = buildOptions();
        const emit = vi.fn();
        const { result } = renderHook(() => useCmsEmbeddedBuilderMutationHandlers(options));

        act(() => {
            result.current.handleEmbeddedBuilderChangeSet({
                requestId: 'req-change-set-1',
                changeSet: {
                    operations: [
                        {
                            op: 'updateText',
                            sectionId: 'hero-1',
                            path: 'title',
                            value: 'Updated title',
                        },
                        {
                            op: 'setField',
                            sectionId: 'hero-1',
                            path: 'buttonLink',
                            value: '/updated-link',
                        },
                        {
                            op: 'replaceImage',
                            sectionId: 'hero-1',
                            url: 'https://example.com/updated.jpg',
                            alt: 'Updated image',
                        },
                    ],
                },
            }, emit);
        });

        expect(options.applyMutationState).toHaveBeenCalledTimes(1);
        const nextState = (options.applyMutationState as ReturnType<typeof vi.fn>).mock.calls[0]?.[0] as {
            sectionsDraft: SectionDraft[];
        };
        const nextProps = JSON.parse(nextState.sectionsDraft[0]?.propsText ?? '{}') as Record<string, unknown>;
        expect(nextProps.title).toBe('Updated title');
        expect(nextProps.buttonLink).toBe('/updated-link');
        expect(nextProps.image).toBe('https://example.com/updated.jpg');
        expect(nextProps.imageAlt).toBe('Updated image');
        expect(emit).toHaveBeenCalledWith(expect.objectContaining({
            type: 'builder:mutation-result',
            requestId: 'req-change-set-1',
            mutation: 'apply-change-set',
            success: true,
            changed: true,
        }));
    });

    it('routes move-section through the canonical reorder pipeline and applies one structural state update', () => {
        const options = buildOptions({
            selectedSectionLocalId: 'hero-1',
        });
        const emit = vi.fn();
        const { result } = renderHook(() => useCmsEmbeddedBuilderMutationHandlers(options));

        act(() => {
            result.current.handleEmbeddedBuilderMoveSection({
                requestId: 'req-move-1',
                sectionLocalId: 'hero-1',
                targetSectionLocalId: 'hero-2',
                position: 'after',
            }, emit);
        });

        expect(options.applyMutationState).toHaveBeenCalledTimes(1);
        const nextState = (options.applyMutationState as ReturnType<typeof vi.fn>).mock.calls[0]?.[0] as {
            sectionsDraft: SectionDraft[];
            selectedSectionLocalId: string | null;
        };
        expect(nextState.sectionsDraft.map((section) => section.localId)).toEqual(['hero-2', 'hero-1']);
        expect(nextState.selectedSectionLocalId).toBe('hero-1');
        expect(options.scheduleStructuralDraftPersistRef.current).toHaveBeenCalledTimes(1);
        expect(emit).toHaveBeenCalledWith(expect.objectContaining({
            type: 'builder:mutation-result',
            requestId: 'req-move-1',
            mutation: 'move-section',
            success: true,
            changed: true,
        }));
    });
});
