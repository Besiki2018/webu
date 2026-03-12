import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderHook } from '@testing-library/react';

import { useEmbeddedBuilderBridge } from '@/builder/cms/useEmbeddedBuilderBridge';

function buildOptions(overrides: Partial<Parameters<typeof useEmbeddedBuilderBridge>[0]> = {}) {
    return {
        projectId: 'project-1',
        isEmbeddedMode: true,
        isEmbeddedVisualBuilder: false,
        isEmbeddedSidebarMode: true,
        builderViewport: 'desktop' as const,
        builderInteractionState: 'normal' as const,
        selectedTargetViewport: 'desktop' as const,
        selectedTargetInteractionState: 'normal' as const,
        isStructurePanelCollapsed: false,
        selectedPage: { pageId: null, pageSlug: null, pageTitle: null },
        stateVersion: 3,
        structureHash: 'hash-1',
        revisionId: 17,
        revisionVersion: 23,
        selectedSectionLocalId: null,
        selectedSectionDraft: null,
        selectedFixedSectionKey: null,
        selectedBuilderTarget: null,
        sectionsDraft: [],
        builderSectionLibrary: [],
        sectionDisplayLabelByKey: new Map<string, string>(),
        t: (key: string) => key,
        normalizeSectionTypeKey: (key: string) => key.trim().toLowerCase(),
        buildSectionPreviewText: (_props: Record<string, unknown>, fallback: string) => fallback,
        getBuilderSectionExplicitProps: () => null,
        onSetViewport: vi.fn(),
        onSetInteractionState: vi.fn(),
        onRefreshPreview: vi.fn(),
        onSetSidebarMode: vi.fn(),
        onClearSelectedSection: vi.fn(),
        onSetInitialSections: vi.fn(),
        onApplyChangeSet: vi.fn(),
        onSetSelectedTarget: vi.fn(),
        onSetStructureOpen: vi.fn(),
        onSaveDraft: vi.fn(),
        onAddSectionByKey: vi.fn(),
        onRemoveSection: vi.fn(),
        onMoveSection: vi.fn(),
        ...overrides,
    };
}

describe('useEmbeddedBuilderBridge', () => {
    const originalParent = window.parent;

    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        Object.defineProperty(window, 'parent', {
            configurable: true,
            value: originalParent,
        });
    });

    it('responds to request-state with the canonical ready envelope', () => {
        const postMessage = vi.fn();
        Object.defineProperty(window, 'parent', {
            configurable: true,
            value: { postMessage },
        });

        renderHook(() => useEmbeddedBuilderBridge(buildOptions()));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                type: 'BUILDER_REQUEST_STATE',
                source: 'chat',
                projectId: 'project-1',
                requestId: 'req-state-1',
                timestamp: Date.now(),
                version: 1,
                payload: {
                    reason: 'inspect-open',
                },
            },
        }));

        expect(postMessage).toHaveBeenCalledWith(expect.objectContaining({
            type: 'BUILDER_READY',
            source: 'sidebar',
            projectId: 'project-1',
            requestId: 'req-state-1',
            payload: expect.objectContaining({
                channel: 'sidebar',
                stateVersion: 3,
                structureHash: 'hash-1',
                revisionId: 17,
                revisionVersion: 23,
            }),
        }), window.location.origin);
    });

    it('forwards stable insert local ids to the add-section handler', () => {
        const options = buildOptions();
        renderHook(() => useEmbeddedBuilderBridge(options));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                type: 'BUILDER_INSERT_NODE',
                source: 'chat',
                projectId: 'project-1',
                requestId: 'req-insert-1',
                timestamp: Date.now(),
                version: 1,
                payload: {
                    sectionKey: 'webu_general_text_01',
                    sectionLocalId: 'builder-section-1',
                    afterSectionLocalId: 'hero-1',
                    placement: 'after',
                },
            },
        }));

        expect(options.onAddSectionByKey).toHaveBeenCalledWith(expect.objectContaining({
            requestId: 'req-insert-1',
            sectionKey: 'webu_general_text_01',
            sectionLocalId: 'builder-section-1',
            afterSectionLocalId: 'hero-1',
            placement: 'after',
        }), expect.any(Function));
    });
});
