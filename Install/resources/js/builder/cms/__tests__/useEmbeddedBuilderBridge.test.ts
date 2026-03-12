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

    it('applies chat visual sync once without echoing the same sync state back to chat', () => {
        const postMessage = vi.fn();
        Object.defineProperty(window, 'parent', {
            configurable: true,
            value: { postMessage },
        });

        let options = buildOptions({
            isEmbeddedVisualBuilder: true,
            builderViewport: 'desktop',
            builderInteractionState: 'normal',
            isStructurePanelCollapsed: false,
        });

        const { rerender } = renderHook(() => useEmbeddedBuilderBridge(options));
        postMessage.mockClear();

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                type: 'BUILDER_SYNC_STATE',
                source: 'chat',
                projectId: 'project-1',
                requestId: 'req-chat-sync-1',
                timestamp: Date.now(),
                version: 1,
                payload: {
                    viewport: 'mobile',
                    interactionState: 'hover',
                    structureOpen: false,
                },
            },
        }));

        expect(options.onSetViewport).toHaveBeenCalledWith('mobile');
        expect(options.onSetInteractionState).toHaveBeenCalledWith('hover');
        expect(options.onSetStructureOpen).toHaveBeenCalledWith(false);

        options = {
            ...options,
            builderViewport: 'mobile',
            builderInteractionState: 'hover',
            isStructurePanelCollapsed: true,
        };
        rerender();

        expect(postMessage.mock.calls.some(([message]) => (
            message?.type === 'BUILDER_SYNC_STATE'
            && (
                message?.payload?.viewport === 'mobile'
                || message?.payload?.interactionState === 'hover'
                || message?.payload?.structureOpen === false
            )
        ))).toBe(false);
    });

    it('does not echo chat selection back after local state updates from the same inbound envelope', () => {
        const postMessage = vi.fn();
        Object.defineProperty(window, 'parent', {
            configurable: true,
            value: { postMessage },
        });

        const target = {
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
        };
        let options = buildOptions({
            isEmbeddedVisualBuilder: true,
        });

        const { rerender } = renderHook(() => useEmbeddedBuilderBridge(options));
        postMessage.mockClear();

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                type: 'BUILDER_SELECT_TARGET',
                source: 'chat',
                projectId: 'project-1',
                requestId: 'req-chat-select-1',
                timestamp: Date.now(),
                version: 1,
                payload: {
                    target: {
                        sectionLocalId: 'hero-1',
                        sectionKey: 'webu_general_hero_01',
                        componentType: 'webu_general_hero_01',
                        componentName: 'Hero',
                        textPreview: 'Hero',
                        props: { headline: 'Hello' },
                        currentBreakpoint: 'desktop',
                        currentInteractionState: 'normal',
                    },
                },
            },
        }));

        expect(options.onSetSelectedTarget).toHaveBeenCalledWith(expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
        }));

        options = {
            ...options,
            selectedSectionLocalId: 'hero-1',
            selectedBuilderTarget: target,
        };
        rerender();

        expect(postMessage.mock.calls.some(([message]) => message?.type === 'BUILDER_SELECT_TARGET')).toBe(false);
    });
});
