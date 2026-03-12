import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { renderHook } from '@testing-library/react';

import { useEmbeddedBuilderBridge } from '@/builder/cms/useEmbeddedBuilderBridge';

function buildOptions(overrides: Partial<Parameters<typeof useEmbeddedBuilderBridge>[0]> = {}) {
    return {
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
        onSetSelectedSection: vi.fn(),
        onSetSelectedSectionKey: vi.fn(),
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

    it('responds to builder ping even when the embedded sidebar has no page identity yet', () => {
        const postMessage = vi.fn();
        Object.defineProperty(window, 'parent', {
            configurable: true,
            value: { postMessage },
        });

        renderHook(() => useEmbeddedBuilderBridge(buildOptions()));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                source: 'webu-chat-builder',
                type: 'builder:ping',
            },
        }));

        expect(postMessage).toHaveBeenCalledWith(expect.objectContaining({
            source: 'webu-cms-builder',
            type: 'builder:ready',
            pageId: null,
            pageSlug: null,
            pageTitle: null,
            stateVersion: 3,
            structureHash: 'hash-1',
            revisionId: 17,
            revisionVersion: 23,
        }), window.location.origin);
    });
});
