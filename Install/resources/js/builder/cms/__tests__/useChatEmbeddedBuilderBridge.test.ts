import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { toast } from 'sonner';

import { useChatEmbeddedBuilderBridge } from '@/builder/cms/useChatEmbeddedBuilderBridge';
import type { PendingBuilderStructureMutation } from '@/builder/cms/chatBuilderStructureMutations';
import type { BuilderBridgeMessage } from '@/lib/builderBridge';

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

    it('maps section-level select messages into canonical builder selection state', async () => {
        const options = buildOptions({
            isBuilderPreviewReady: true,
            isBuilderSidebarReady: true,
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                type: 'BUILDER_SELECT_TARGET',
                source: 'sidebar',
                projectId: 'project-1',
                requestId: 'req-select-1',
                timestamp: Date.now(),
                version: 1,
                pageId: 11,
                pageSlug: 'home',
                pageTitle: 'Home',
                payload: {
                    target: {
                        sectionLocalId: 'hero-1',
                        sectionKey: 'webu_general_hero_01',
                        componentType: 'webu_general_hero_01',
                        componentName: 'Hero',
                        textPreview: 'Hello',
                        props: { headline: 'Hello' },
                        currentBreakpoint: 'desktop',
                        currentInteractionState: 'normal',
                    },
                },
            },
        }));

        await waitFor(() => {
            expect(options.selectBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                path: null,
                componentPath: null,
            }));
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
            data: {
                type: 'BUILDER_ACK',
                source: 'sidebar',
                projectId: 'project-1',
                requestId: 'req-remove-1',
                timestamp: Date.now(),
                version: 1,
                pageId: 11,
                pageSlug: 'home',
                pageTitle: 'Home',
                payload: {
                    ackType: 'BUILDER_DELETE_NODE',
                    success: false,
                    changed: false,
                    error: 'Builder update failed',
                },
            },
        }));

        await waitFor(() => {
            expect(options.setPendingBuilderStructureMutation).toHaveBeenCalledWith(null);
            expect(options.selectBuilderTarget).toHaveBeenCalledWith(selectionSnapshot.target);
            expect(toast.error).toHaveBeenCalled();
        });
    });
});
