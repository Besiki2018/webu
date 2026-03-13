import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { toast } from 'sonner';

import { useChatEmbeddedBuilderBridge } from '@/builder/cms/useChatEmbeddedBuilderBridge';
import type { PendingBuilderStructureMutation } from '@/builder/cms/chatBuilderStructureMutations';
import {
    buildBuilderAckMessage,
    buildBuilderSelectTargetMessage,
    buildBuilderSyncStateMessage,
    type BuilderBridgeMessage,
} from '@/lib/builderBridge';

vi.mock('sonner', () => ({
    toast: {
        error: vi.fn(),
        success: vi.fn(),
    },
}));

function buildOptions(overrides: Partial<Parameters<typeof useChatEmbeddedBuilderBridge>[0]> = {}) {
    return {
        projectId: 'project-1',
        viewMode: 'inspect' as const,
        isVisualBuilderOpen: true,
        isBuilderSidebarReady: false,
        isBuilderPreviewReady: false,
        isBuilderStructureOpen: true,
        builderPaneMode: 'settings' as const,
        previewViewport: 'desktop' as const,
        previewInteractionState: 'normal' as const,
        selectedBuilderTarget: null,
        selectedBuilderSectionLocalId: null,
        selectedPreviewSectionKey: null,
        activeBuilderPageIdentity: { pageId: 11, pageSlug: 'home', pageTitle: 'Home' },
        builderStructureItems: [{
            localId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            label: 'Hero',
            previewText: 'Hero',
            props: { headline: 'Hello' },
        }],
        pendingBuilderStructureMutation: null as PendingBuilderStructureMutation | null,
        builderSidebarFrameRef: { current: { contentWindow: null } as unknown as HTMLIFrameElement },
        builderSidebarCommandQueueRef: { current: [] as BuilderBridgeMessage[] },
        pendingBuilderChangeSetRequestIdRef: { current: null as string | null },
        lastBuilderReadySignatureRef: { current: null as string | null },
        lastBuilderSnapshotSignatureRef: { current: null as string | null },
        latestBuilderStateCursorRef: { current: null as { pageId: number | null; pageSlug: string | null; stateVersion: number | null; revisionVersion: number | null } | null },
        structureSnapshotPageRef: { current: { pageId: null, pageSlug: null, pageTitle: null } },
        setStructureSnapshotPageIdentity: vi.fn(),
        preferPersistedStructureStateRef: { current: false },
        justPlacedSectionRef: { current: false },
        setPreviewViewport: vi.fn(),
        setPreviewInteractionState: vi.fn(),
        setIsBuilderStructureOpen: vi.fn(),
        selectBuilderTarget: vi.fn(),
        clearBuilderSelection: vi.fn(),
        setBuilderPaneMode: vi.fn(),
        setActiveLibraryItem: vi.fn(),
        setIsSavingBuilderDraft: vi.fn(),
        setPreviewRefreshTrigger: vi.fn(),
        setBuilderLibraryItems: vi.fn(),
        setBuilderStructureItems: vi.fn(),
        setBuilderCodePages: vi.fn(),
        setPendingBuilderStructureMutation: vi.fn(),
        markBuilderSidebarReady: vi.fn(),
        t: (key: string) => key,
        ...overrides,
    };
}

function buildSidebarInput(requestId: string) {
    return {
        source: 'sidebar' as const,
        projectId: 'project-1',
        page: {
            pageId: 11,
            pageSlug: 'home',
            pageTitle: 'Home',
        },
        requestId,
        timestamp: 12345,
    };
}

describe('useChatEmbeddedBuilderBridge', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('queues outgoing bridge commands until preview and sidebar are both ready', () => {
        const options = buildOptions();
        const { result } = renderHook(() => useChatEmbeddedBuilderBridge(options));

        result.current.postBuilderCommand({
            type: 'builder:set-sidebar-mode',
            mode: 'settings',
        });

        expect(options.builderSidebarCommandQueueRef.current.length).toBeGreaterThan(0);
        expect(options.builderSidebarCommandQueueRef.current).toContainEqual(expect.objectContaining({
            type: 'BUILDER_SYNC_STATE',
            source: 'chat',
            projectId: 'project-1',
            payload: expect.objectContaining({
                sidebarMode: 'settings',
            }),
        }));
    });

    it('re-requests sidebar state when commands are queued before sidebar readiness is acknowledged', () => {
        const postMessage = vi.fn();
        const options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: false,
            builderSidebarFrameRef: {
                current: {
                    contentWindow: { postMessage },
                } as unknown as HTMLIFrameElement,
            },
        });
        const { result } = renderHook(() => useChatEmbeddedBuilderBridge(options));

        result.current.postBuilderCommand({
            type: 'builder:set-selected-target',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
        });

        expect(options.builderSidebarCommandQueueRef.current).toContainEqual(expect.objectContaining({
            type: 'BUILDER_SELECT_TARGET',
            source: 'chat',
        }));
        expect(postMessage).toHaveBeenCalledWith(expect.objectContaining({
            type: 'BUILDER_REQUEST_STATE',
            source: 'chat',
            projectId: 'project-1',
            payload: expect.objectContaining({
                reason: 'queued-command',
            }),
        }), window.location.origin);
    });

    it('maps section-level select messages into canonical builder selection state', async () => {
        const options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        const message = buildBuilderSelectTargetMessage({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            textPreview: 'Hello',
            props: { headline: 'Hello' },
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        }, buildSidebarInput('req-select-1'));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: message,
        }));
        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: message,
        }));

        await waitFor(() => {
            expect(options.selectBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                path: null,
                componentPath: null,
            }));
            expect(options.selectBuilderTarget).toHaveBeenCalledTimes(1);
            expect(options.setBuilderPaneMode).toHaveBeenCalledWith('settings');
        });
    });

    it('restores the previous selection when a pending mutation fails', async () => {
        const selectionSnapshot = {
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            target: {
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
        };
        const options = buildOptions({
            pendingBuilderStructureMutation: {
                requestId: 'req-remove-1',
                mutation: 'remove-section',
                previewItems: null,
                selectionSnapshot,
            },
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: buildBuilderAckMessage({
                ackType: 'BUILDER_DELETE_NODE',
                success: false,
                changed: false,
                error: 'Builder update failed',
                stateVersion: null,
                structureHash: null,
                revisionId: null,
                revisionVersion: null,
                mutation: null,
            }, buildSidebarInput('req-remove-1')),
        }));

        await waitFor(() => {
            expect(options.setPendingBuilderStructureMutation).toHaveBeenCalledWith(null);
            expect(options.selectBuilderTarget).toHaveBeenCalledWith(selectionSnapshot.target);
            expect(toast.error).toHaveBeenCalled();
        });
    });

    it('ignores duplicate structure snapshots and refreshes selected target preview data from the first snapshot only once', async () => {
        const options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
            selectedBuilderTarget: {
                targetId: 'hero-1::section',
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Old Hero',
                path: null,
                elementId: null,
                selector: '[data-webu-section-local-id="hero-1"]',
                textPreview: 'Old hero',
                props: { headline: 'Old hero' },
            },
            selectedBuilderSectionLocalId: 'hero-1',
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        const snapshotEvent = {
            origin: window.location.origin,
            data: buildBuilderSyncStateMessage({
                stateVersion: 9,
                revisionVersion: 4,
                structureHash: 'hash-9',
                structureSections: [{
                    localId: 'hero-1',
                    sectionKey: 'webu_general_hero_01',
                    type: 'webu_general_hero_01',
                    label: 'Hero',
                    previewText: 'Fresh preview',
                    propsText: JSON.stringify({ title: 'Fresh preview' }),
                    props: { title: 'Fresh preview' },
                }],
            }, buildSidebarInput('req-sync-1')),
        };

        window.dispatchEvent(new MessageEvent('message', snapshotEvent));
        window.dispatchEvent(new MessageEvent('message', snapshotEvent));

        await waitFor(() => {
            expect(options.setBuilderStructureItems).toHaveBeenCalledTimes(1);
            expect(options.selectBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-1',
                componentName: 'Hero',
                textPreview: 'Fresh preview',
                props: { title: 'Fresh preview' },
            }));
        });
    });

    it('applies changed structure snapshots even when state metadata stays the same', async () => {
        const options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
            selectedBuilderTarget: {
                targetId: 'hero-1::section',
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Old Hero',
                path: null,
                elementId: null,
                selector: '[data-webu-section-local-id="hero-1"]',
                textPreview: 'Old hero',
                props: { title: 'Old hero' },
            },
            selectedBuilderSectionLocalId: 'hero-1',
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        const baseInput = buildSidebarInput('req-sync-meta-1');
        const firstSnapshot = buildBuilderSyncStateMessage({
            stateVersion: 9,
            revisionVersion: 4,
            structureHash: 'hash-9',
            structureSections: [{
                localId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                type: 'webu_general_hero_01',
                label: 'Hero',
                previewText: 'Fresh preview',
                propsText: JSON.stringify({ title: 'Fresh preview' }),
                props: { title: 'Fresh preview' },
            }],
        }, baseInput);
        const secondSnapshot = buildBuilderSyncStateMessage({
            stateVersion: 9,
            revisionVersion: 4,
            structureHash: 'hash-9',
            structureSections: [{
                localId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                type: 'webu_general_hero_01',
                label: 'Hero',
                previewText: 'Fresh preview 2',
                propsText: JSON.stringify({ title: 'Fresh preview 2' }),
                props: { title: 'Fresh preview 2' },
            }],
        }, buildSidebarInput('req-sync-meta-2'));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: firstSnapshot,
        }));
        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: secondSnapshot,
        }));

        await waitFor(() => {
            expect(options.setBuilderStructureItems).toHaveBeenCalledTimes(2);
            expect(options.selectBuilderTarget).toHaveBeenLastCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-1',
                componentName: 'Hero',
                textPreview: 'Fresh preview 2',
                props: { title: 'Fresh preview 2' },
            }));
        });
    });

    it('adopts sidebar fallback selection from structure snapshots when the previous section no longer exists', async () => {
        const options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
            selectedBuilderTarget: {
                targetId: 'hero-1::title',
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                path: 'title',
                elementId: 'hero-1.title',
                selector: '[data-webu-section-local-id="hero-1"] h1',
                textPreview: 'Old hero',
                props: { title: 'Old hero' },
            },
            selectedBuilderSectionLocalId: 'hero-1',
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: buildBuilderSyncStateMessage({
                stateVersion: 10,
                revisionVersion: 5,
                structureHash: 'hash-10',
                selectedTarget: {
                    sectionLocalId: 'hero-2',
                    sectionKey: 'webu_general_hero_01',
                    componentType: 'webu_general_hero_01',
                    componentName: 'Hero',
                    textPreview: 'Duplicated hero',
                    currentBreakpoint: 'desktop',
                    currentInteractionState: 'normal',
                },
                structureSections: [{
                    localId: 'hero-2',
                    sectionKey: 'webu_general_hero_01',
                    type: 'webu_general_hero_01',
                    label: 'Hero',
                    previewText: 'Duplicated hero',
                    propsText: JSON.stringify({ title: 'Duplicated hero' }),
                    props: { title: 'Duplicated hero' },
                }],
            }, buildSidebarInput('req-sync-fallback-1')),
        }));

        await waitFor(() => {
            expect(options.selectBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-2',
                componentName: 'Hero',
                textPreview: 'Duplicated hero',
                props: { title: 'Duplicated hero' },
            }));
            expect(options.clearBuilderSelection).not.toHaveBeenCalled();
            expect(options.setBuilderPaneMode).toHaveBeenCalledWith('settings');
        });
    });

    it('falls back to the nearest remaining section when a delete snapshot omits selectedTarget', async () => {
        const options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
            builderStructureItems: [{
                localId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                label: 'Hero',
                previewText: 'Hero 1',
                props: { title: 'Hero 1' },
            }, {
                localId: 'hero-2',
                sectionKey: 'webu_general_hero_01',
                label: 'Hero',
                previewText: 'Hero 2',
                props: { title: 'Hero 2' },
            }],
            selectedBuilderTarget: {
                targetId: 'hero-2::section',
                sectionLocalId: 'hero-2',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                path: null,
                elementId: null,
                selector: '[data-webu-section-local-id="hero-2"]',
                textPreview: 'Hero 2',
                props: { title: 'Hero 2' },
            },
            selectedBuilderSectionLocalId: 'hero-2',
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: buildBuilderSyncStateMessage({
                stateVersion: 11,
                revisionVersion: 6,
                structureHash: 'hash-11',
                structureSections: [{
                    localId: 'hero-1',
                    sectionKey: 'webu_general_hero_01',
                    type: 'webu_general_hero_01',
                    label: 'Hero',
                    previewText: 'Hero 1',
                    propsText: JSON.stringify({ title: 'Hero 1' }),
                    props: { title: 'Hero 1' },
                }],
            }, buildSidebarInput('req-sync-nearest-1')),
        }));

        await waitFor(() => {
            expect(options.selectBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-1',
                componentName: 'Hero',
                textPreview: 'Hero 1',
                props: { title: 'Hero 1' },
            }));
            expect(options.clearBuilderSelection).not.toHaveBeenCalled();
            expect(options.setBuilderPaneMode).toHaveBeenCalledWith('settings');
        });
    });

    it('falls back to the nearest remaining section when the live selection is already cleared but the last sidebar selection existed', async () => {
        const options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
            builderStructureItems: [{
                localId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                label: 'Hero',
                previewText: 'Hero 1',
                props: { title: 'Hero 1' },
            }, {
                localId: 'hero-2',
                sectionKey: 'webu_general_hero_01',
                label: 'Hero',
                previewText: 'Hero 2',
                props: { title: 'Hero 2' },
            }],
            selectedBuilderTarget: null,
            selectedBuilderSectionLocalId: null,
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: buildBuilderSelectTargetMessage({
                sectionLocalId: 'hero-2',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                textPreview: 'Hero 2',
                props: { title: 'Hero 2' },
                currentBreakpoint: 'desktop',
                currentInteractionState: 'normal',
            }, buildSidebarInput('req-select-before-delete-1')),
        }));

        await waitFor(() => {
            expect(options.selectBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-2',
                componentName: 'Hero',
                textPreview: 'Hero 2',
                props: { title: 'Hero 2' },
            }));
        });

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: buildBuilderSyncStateMessage({
                stateVersion: 12,
                revisionVersion: 7,
                structureHash: 'hash-12',
                structureSections: [{
                    localId: 'hero-1',
                    sectionKey: 'webu_general_hero_01',
                    type: 'webu_general_hero_01',
                    label: 'Hero',
                    previewText: 'Hero 1',
                    propsText: JSON.stringify({ title: 'Hero 1' }),
                    props: { title: 'Hero 1' },
                }],
            }, buildSidebarInput('req-sync-nearest-2')),
        }));

        await waitFor(() => {
            expect(options.selectBuilderTarget).toHaveBeenLastCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-1',
                componentName: 'Hero',
                textPreview: 'Hero 1',
                props: { title: 'Hero 1' },
            }));
            expect(options.clearBuilderSelection).not.toHaveBeenCalled();
            expect(options.setBuilderPaneMode).toHaveBeenCalledWith('settings');
        });
    });

    it('adopts sidebar visual state without echoing the same sync state back to the sidebar iframe', async () => {
        const postMessage = vi.fn();
        let options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
            isBuilderStructureOpen: true,
            builderPaneMode: 'settings',
            builderSidebarFrameRef: {
                current: {
                    contentWindow: { postMessage },
                } as unknown as HTMLIFrameElement,
            },
        });

        const { rerender } = renderHook(() => useChatEmbeddedBuilderBridge(options));
        postMessage.mockClear();

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: buildBuilderSyncStateMessage({
                viewport: 'mobile',
                interactionState: 'hover',
                sidebarMode: 'elements',
                structureOpen: false,
            }, buildSidebarInput('req-sync-loop-1')),
        }));

        await waitFor(() => {
            expect(options.setPreviewViewport).toHaveBeenCalledWith('mobile');
            expect(options.setPreviewInteractionState).toHaveBeenCalledWith('hover');
            expect(options.setIsBuilderStructureOpen).toHaveBeenCalledWith(false);
        });

        options = {
            ...options,
            previewViewport: 'mobile',
            previewInteractionState: 'hover',
            builderPaneMode: 'elements',
            isBuilderStructureOpen: false,
        };
        rerender();

        expect(postMessage).not.toHaveBeenCalled();
    });

    it('does not echo sidebar selection back as a new select-target bridge message', async () => {
        const postMessage = vi.fn();
        const target = {
            targetId: 'hero-1::section',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            path: null,
            elementId: null,
            selector: '[data-webu-section-local-id="hero-1"]',
            textPreview: 'Hello',
            props: { headline: 'Hello' },
        };
        let options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
            builderSidebarFrameRef: {
                current: {
                    contentWindow: { postMessage },
                } as unknown as HTMLIFrameElement,
            },
        });

        const { rerender } = renderHook(() => useChatEmbeddedBuilderBridge(options));
        postMessage.mockClear();

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: buildBuilderSelectTargetMessage({
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                textPreview: 'Hello',
                props: { headline: 'Hello' },
                currentBreakpoint: 'desktop',
                currentInteractionState: 'normal',
            }, buildSidebarInput('req-select-loop-1')),
        }));

        await waitFor(() => {
            expect(options.selectBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
            }));
        });

        options = {
            ...options,
            selectedBuilderTarget: target,
            selectedBuilderSectionLocalId: 'hero-1',
        };
        rerender();

        expect(postMessage.mock.calls.some(([message]) => message?.type === 'BUILDER_SELECT_TARGET')).toBe(false);
        expect(postMessage.mock.calls.some(([message]) => message?.type === 'BUILDER_CLEAR_SELECTION')).toBe(false);
    });

    it('does not resend unchanged visual sync envelopes on equivalent rerenders', async () => {
        const postMessage = vi.fn();
        let options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
            builderSidebarFrameRef: {
                current: {
                    contentWindow: { postMessage },
                } as unknown as HTMLIFrameElement,
            },
        });

        const { rerender } = renderHook(() => useChatEmbeddedBuilderBridge(options));

        await waitFor(() => {
            expect(postMessage.mock.calls.some(([message]) => (
                message?.type === 'BUILDER_SYNC_STATE'
                && message?.payload?.viewport === 'desktop'
                && message?.payload?.interactionState === 'normal'
                && message?.payload?.structureOpen === true
                && message?.payload?.sidebarMode === 'settings'
            ))).toBe(true);
            expect(postMessage.mock.calls.some(([message]) => message?.type === 'BUILDER_CLEAR_SELECTION')).toBe(true);
        });

        postMessage.mockClear();

        options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
            builderSidebarFrameRef: {
                current: {
                    contentWindow: { postMessage },
                } as unknown as HTMLIFrameElement,
            },
        });
        rerender();

        await waitFor(() => {
            expect(postMessage).not.toHaveBeenCalled();
        });
    });
});
