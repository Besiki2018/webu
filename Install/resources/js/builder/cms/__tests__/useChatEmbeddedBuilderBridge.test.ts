import { describe, expect, it, vi, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { toast } from 'sonner';
import { useChatEmbeddedBuilderBridge } from '@/builder/cms/useChatEmbeddedBuilderBridge';
import { buildEditableTargetFromMessagePayload, buildSectionScopedEditableTarget, editableTargetToMessagePayload } from '@/builder/editingState';
import type { PendingBuilderStructureMutation } from '@/builder/cms/chatBuilderStructureMutations';

vi.mock('sonner', () => ({
    toast: {
        error: vi.fn(),
        success: vi.fn(),
    },
}));

function buildOptions(overrides: Partial<Parameters<typeof useChatEmbeddedBuilderBridge>[0]> = {}) {
    return {
        viewMode: 'inspect' as const,
        isVisualBuilderOpen: true,
        isBuilderSidebarReady: false,
        isBuilderStructureOpen: true,
        builderPaneMode: 'settings' as const,
        previewViewport: 'desktop' as const,
        previewInteractionState: 'normal' as const,
        selectedBuilderTarget: null,
        selectedBuilderSectionLocalId: null,
        selectedPreviewSectionKey: null,
        selectedElement: null,
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
        builderSidebarCommandQueueRef: { current: [] as Array<Record<string, unknown>> },
        pendingBuilderChangeSetRequestIdRef: { current: null as string | null },
        lastBuilderReadySignatureRef: { current: null as string | null },
        lastBuilderSnapshotSignatureRef: { current: null as string | null },
        latestBuilderStateCursorRef: { current: null as { pageId: number | null; pageSlug: string | null; stateVersion: number | null; revisionVersion: number | null } | null },
        structureSnapshotPageRef: { current: { pageId: null, pageSlug: null, pageTitle: null } },
        preferPersistedStructureStateRef: { current: false },
        justPlacedSectionRef: { current: false },
        setPreviewViewport: vi.fn(),
        setPreviewInteractionState: vi.fn(),
        setIsBuilderStructureOpen: vi.fn(),
        setSelectedBuilderSectionLocalId: vi.fn(),
        setSelectedPreviewSectionKey: vi.fn(),
        setSelectedBuilderTarget: vi.fn(),
        setBuilderPaneMode: vi.fn(),
        setActiveLibraryItem: vi.fn(),
        setIsSavingBuilderDraft: vi.fn(),
        setPreviewRefreshTrigger: vi.fn(),
        setBuilderLibraryItems: vi.fn(),
        setBuilderStructureItems: vi.fn(),
        setBuilderCodePages: vi.fn(),
        setPendingBuilderStructureMutation: vi.fn(),
        setIsBuilderSidebarReady: vi.fn(),
        t: (key: string) => key,
        ...overrides,
    };
}

describe('useChatEmbeddedBuilderBridge', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('ignores stale selected-section bridge events', async () => {
        const options = buildOptions();

        renderHook(() => useChatEmbeddedBuilderBridge(options));
        options.latestBuilderStateCursorRef.current = {
            pageId: 11,
            pageSlug: 'home',
            stateVersion: 8,
            revisionVersion: 12,
        };

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                source: 'webu-cms-builder',
                type: 'builder:selected-section',
                pageId: 11,
                pageSlug: 'home',
                stateVersion: 7,
                revisionVersion: 12,
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
            },
        }));

        await waitFor(() => {
            expect(options.setSelectedBuilderSectionLocalId).not.toHaveBeenCalled();
            expect(options.setSelectedPreviewSectionKey).not.toHaveBeenCalled();
        });
    });

    it('ignores stale selected-target bridge events', async () => {
        const options = buildOptions();

        renderHook(() => useChatEmbeddedBuilderBridge(options));
        options.latestBuilderStateCursorRef.current = {
            pageId: 11,
            pageSlug: 'home',
            stateVersion: 5,
            revisionVersion: 7,
        };

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                source: 'webu-cms-builder',
                type: 'builder:selected-target',
                pageId: 11,
                pageSlug: 'home',
                stateVersion: 4,
                revisionVersion: 7,
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                parameterPath: 'headline',
                elementId: 'HeroSection.headline',
                selector: '[data-webu-field="headline"]',
                textPreview: 'Hello',
                props: { headline: 'Hello' },
                viewport: 'desktop',
                interactionState: 'normal',
            },
        }));

        await waitFor(() => {
            expect(options.setSelectedBuilderTarget).not.toHaveBeenCalled();
            expect(options.setBuilderPaneMode).not.toHaveBeenCalled();
        });
    });

    it('normalizes mirrored same-section selections back to section scope', async () => {
        const selectedTarget = buildEditableTargetFromMessagePayload({
            pageId: 11,
            pageSlug: 'home',
            pageTitle: 'Home',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            parameterPath: 'headline',
            componentPath: 'headline',
            elementId: 'HeroSection.headline',
            builderId: 'hero-1::headline',
            props: { headline: 'Hello' },
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });
        const options = buildOptions({
            selectedBuilderTarget: selectedTarget,
            selectedBuilderSectionLocalId: 'hero-1',
            selectedPreviewSectionKey: 'webu_general_hero_01',
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                source: 'webu-cms-builder',
                type: 'builder:selected-section',
                pageId: 11,
                pageSlug: 'home',
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
            },
        }));

        await waitFor(() => {
            expect(options.setSelectedBuilderSectionLocalId).not.toHaveBeenCalled();
            expect(options.setSelectedPreviewSectionKey).not.toHaveBeenCalled();
            expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                path: null,
                componentPath: null,
                elementId: null,
            }));
        });
    });

    it('ignores identical selected-target bridge events to avoid selection feedback loops', async () => {
        const selectedTarget = buildSectionScopedEditableTarget({
            pageId: 11,
            pageSlug: 'home',
            pageTitle: 'Home',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            textPreview: 'Hello',
            props: { headline: 'Hello' },
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });
        const mirroredPayload = editableTargetToMessagePayload(selectedTarget);
        const options = buildOptions({
            selectedBuilderTarget: selectedTarget,
            selectedBuilderSectionLocalId: 'hero-1',
            selectedPreviewSectionKey: 'webu_general_hero_01',
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                source: 'webu-cms-builder',
                type: 'builder:selected-target',
                ...mirroredPayload,
                viewport: 'desktop',
                interactionState: 'normal',
            },
        }));

        await waitFor(() => {
            expect(options.setSelectedBuilderSectionLocalId).not.toHaveBeenCalled();
            expect(options.setSelectedPreviewSectionKey).not.toHaveBeenCalled();
            expect(options.setSelectedBuilderTarget).not.toHaveBeenCalled();
        });
    });

    it('does not resend selected-target commands when only selected props change while typing', async () => {
        const postMessage = vi.fn();
        const initialTarget = buildEditableTargetFromMessagePayload({
            pageId: 11,
            pageSlug: 'home',
            pageTitle: 'Home',
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            parameterPath: 'headline',
            componentPath: 'headline',
            elementId: 'HeroSection.headline',
            selector: '[data-webu-field="headline"]',
            textPreview: 'Hello',
            builderId: 'hero-1::headline',
            props: { headline: 'Hello' },
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });
        const initialOptions = buildOptions({
            isBuilderSidebarReady: true,
            selectedBuilderTarget: initialTarget,
            selectedBuilderSectionLocalId: 'hero-1',
            selectedPreviewSectionKey: 'webu_general_hero_01',
            builderSidebarFrameRef: {
                current: {
                    contentWindow: { postMessage },
                } as unknown as HTMLIFrameElement,
            },
        });

        const { rerender } = renderHook((props: ReturnType<typeof buildOptions>) => useChatEmbeddedBuilderBridge(props), {
            initialProps: initialOptions,
        });

        await waitFor(() => {
            const selectionCalls = postMessage.mock.calls.filter(([payload]) => payload?.type === 'builder:set-selected-target');
            expect(selectionCalls).toHaveLength(1);
        });

        const updatedTarget = buildEditableTargetFromMessagePayload({
            ...editableTargetToMessagePayload(initialTarget),
            props: { headline: 'Hello again' },
            textPreview: 'Hello again',
        });

        rerender({
            ...initialOptions,
            selectedBuilderTarget: updatedTarget,
        });

        await waitFor(() => {
            const selectionCalls = postMessage.mock.calls.filter(([payload]) => payload?.type === 'builder:set-selected-target');
            expect(selectionCalls).toHaveLength(1);
        });
    });

    it('restores selection when a remove mutation fails', async () => {
        const target = buildEditableTargetFromMessagePayload({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            parameterPath: 'headline',
            elementId: 'HeroSection.headline',
            props: { headline: 'Hello' },
        });
        const pendingMutation: PendingBuilderStructureMutation = {
            requestId: 'req-remove-1',
            mutation: 'remove-section',
            previewItems: [],
            selectionSnapshot: {
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                target,
            },
        };
        const options = buildOptions({
            pendingBuilderStructureMutation: pendingMutation,
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                source: 'webu-cms-builder',
                type: 'builder:mutation-result',
                pageId: 11,
                pageSlug: 'home',
                requestId: 'req-remove-1',
                mutation: 'remove-section',
                success: false,
                changed: false,
                error: 'Remove failed',
            },
        }));

        await waitFor(() => {
            expect(options.setPendingBuilderStructureMutation).toHaveBeenCalledWith(null);
            expect(options.setSelectedBuilderSectionLocalId).toHaveBeenCalledWith('hero-1');
            expect(options.setSelectedPreviewSectionKey).toHaveBeenCalledWith('webu_general_hero_01');
            expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(target);
            expect(toast.error).toHaveBeenCalledWith('Remove failed');
        });
    });

    it('keeps structure panel state controlled by chat when builder reports a different state', async () => {
        const postMessage = vi.fn();
        const options = buildOptions({
            isBuilderSidebarReady: true,
            isBuilderStructureOpen: false,
            builderSidebarFrameRef: {
                current: {
                    contentWindow: { postMessage },
                } as unknown as HTMLIFrameElement,
            },
        });

        renderHook(() => useChatEmbeddedBuilderBridge(options));

        window.dispatchEvent(new MessageEvent('message', {
            origin: window.location.origin,
            data: {
                source: 'webu-cms-builder',
                type: 'builder:state',
                pageId: 11,
                pageSlug: 'home',
                viewport: 'desktop',
                interactionState: 'normal',
                structureOpen: true,
            },
        }));

        await waitFor(() => {
            expect(options.setIsBuilderStructureOpen).not.toHaveBeenCalled();
            expect(postMessage).toHaveBeenCalledWith(expect.objectContaining({
                source: 'webu-chat-builder',
                type: 'builder:set-structure-open',
                open: false,
                pageId: 11,
                pageSlug: 'home',
                pageTitle: 'Home',
            }), window.location.origin);
        });
    });
});
