import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';

import { useEmbeddedBuilderBridge } from '@/builder/cms/useEmbeddedBuilderBridge';
import {
    buildBuilderInsertNodeMessage,
    buildBuilderRequestStateMessage,
    buildBuilderSelectTargetMessage,
    buildBuilderSyncStateMessage,
} from '@/lib/builderBridge';

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

function buildChatInput(requestId: string) {
    return {
        source: 'chat' as const,
        projectId: 'project-1',
        page: { pageId: null, pageSlug: null, pageTitle: null },
        requestId,
        timestamp: 12345,
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
            data: buildBuilderRequestStateMessage({
                reason: 'inspect-open',
            }, buildChatInput('req-state-1')),
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
            data: buildBuilderInsertNodeMessage({
                sectionKey: 'webu_general_text_01',
                sectionLocalId: 'builder-section-1',
                afterSectionLocalId: 'hero-1',
                placement: 'after',
            }, buildChatInput('req-insert-1')),
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

        const message = buildBuilderSyncStateMessage({
            viewport: 'mobile',
            interactionState: 'hover',
            structureOpen: false,
        }, buildChatInput('req-chat-sync-1'));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: message,
        }));
        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: message,
        }));

        expect(options.onSetViewport).toHaveBeenCalledWith('mobile');
        expect(options.onSetInteractionState).toHaveBeenCalledWith('hover');
        expect(options.onSetStructureOpen).toHaveBeenCalledWith(false);
        expect(options.onSetViewport).toHaveBeenCalledTimes(1);
        expect(options.onSetInteractionState).toHaveBeenCalledTimes(1);
        expect(options.onSetStructureOpen).toHaveBeenCalledTimes(1);

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
            data: buildBuilderSelectTargetMessage({
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                textPreview: 'Hero',
                props: { headline: 'Hello' },
                currentBreakpoint: 'desktop',
                currentInteractionState: 'normal',
            }, buildChatInput('req-chat-select-1')),
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

    it('does not resend unchanged sidebar snapshots on equivalent rerenders', async () => {
        const postMessage = vi.fn();
        Object.defineProperty(window, 'parent', {
            configurable: true,
            value: { postMessage },
        });

        let options = buildOptions({
            sectionsDraft: [{
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                props: { headline: 'Hello world' },
                propsText: JSON.stringify({ headline: 'Hello world' }),
            }],
            builderSectionLibrary: [{
                key: 'webu_general_hero_01',
                label: 'Hero',
                category: 'hero',
            }],
            sectionDisplayLabelByKey: new Map([
                ['webu_general_hero_01', 'Hero'],
            ]),
            getBuilderSectionExplicitProps: (section) => section.props as Record<string, unknown>,
        });

        const { rerender } = renderHook(() => useEmbeddedBuilderBridge(options));
        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: buildBuilderRequestStateMessage({
                reason: 'inspect-open',
            }, buildChatInput('req-rerender-1')),
        }));

        await waitFor(() => {
            expect(postMessage.mock.calls.some(([message]) => message?.type === 'BUILDER_SYNC_STATE')).toBe(true);
        });

        postMessage.mockClear();

        options = buildOptions({
            sectionsDraft: [{
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                props: { headline: 'Hello world' },
                propsText: JSON.stringify({ headline: 'Hello world' }),
            }],
            builderSectionLibrary: [{
                key: 'webu_general_hero_01',
                label: 'Hero',
                category: 'hero',
            }],
            sectionDisplayLabelByKey: new Map([
                ['webu_general_hero_01', 'Hero'],
            ]),
            getBuilderSectionExplicitProps: (section) => section.props as Record<string, unknown>,
        });
        rerender();

        await waitFor(() => {
            expect(postMessage).not.toHaveBeenCalled();
        });
    });
});
