import { useCallback, useEffect, useRef, type MutableRefObject } from 'react';
import { toast } from 'sonner';
import {
    createBuilderBridgeRequestId,
    normalizeBuilderBridgePageIdentity,
    payloadTargetsBuilderBridgePage,
    type BuilderBridgeInteractionState,
    type BuilderBridgePageIdentity,
    type BuilderBridgeSidebarMode,
} from '@/builder/cms/embeddedBuilderBridgeContract';
import {
    buildBuilderBridgeEventSignature,
    isStaleBuilderBridgeState,
    resolvePendingBuilderStructureMutation,
    type BuilderBridgeStateCursor,
} from '@/builder/cms/chatBuilderMutationFlow';
import type { PendingBuilderStructureMutation } from '@/builder/cms/chatBuilderStructureMutations';
import {
    upsertWorkspaceBuilderCodePages,
    type WorkspaceBuilderCodePage as BuilderCodePage,
    type WorkspaceBuilderCodeSection as BuilderCodeSection,
    type WorkspaceBuilderStructureItem as BuilderStructureItem,
} from '@/builder/cms/workspaceBuilderSync';
import { buildFallbackSectionProps, buildGeneratedPagePath } from '@/builder/cms/chatEmbeddedBuilderUtils';
import {
    areBuilderEditableTargetsEqual,
    buildEditableTargetFromMessagePayload,
    buildSectionScopedEditableTarget,
    editableTargetToMessagePayload,
    type BuilderEditableTarget,
} from '@/builder/editingState';
import { buildSectionPreviewText, parseSectionProps as parseBuilderSectionProps, stringifySectionProps } from '@/builder/state/sectionProps';
import type { ChangeSet } from '@/hooks/useAiSiteEditor';
import type { PreviewViewport } from '@/components/Preview/PreviewViewportMenu';
import { buildCanonicalBridgeSelectedTargetPayload } from '@/builder/cms/canonicalSelectionPayload';
import {
    buildBuilderBridgeSelectionSignature,
    buildBuilderBridgeVisualStateSignature,
    buildBuilderClearSelectionMessage,
    buildBuilderDeleteNodeMessage,
    buildBuilderInsertNodeMessage,
    buildBuilderMoveNodeMessage,
    buildBuilderPatchPropsMessage,
    buildBuilderRequestStateMessage,
    buildBuilderRefreshPreviewMessage,
    buildBuilderSelectTargetMessage,
    buildBuilderSyncStateMessage,
    buildBuilderSaveDraftMessage,
    builderBridgeMessageEchoesActor,
    builderBridgeEnvelopeTargetsProject,
    inspectBuilderBridgeEnvelope,
    logBuilderBridgeDiagnostic,
    postBuilderBridgeEnvelope,
    rememberBuilderBridgeEnvelopeSignature,
    type BuilderBridgeMessage,
} from '@/lib/builderBridge';

type ViewMode = 'preview' | 'inspect' | 'code' | 'design' | 'settings';
type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

interface BuilderLibraryItem {
    key: string;
    label: string;
    category: string;
}

interface UseChatEmbeddedBuilderBridgeOptions {
    projectId: string;
    viewMode: ViewMode;
    isVisualBuilderOpen: boolean;
    isBuilderSidebarReady: boolean;
    isBuilderPreviewReady: boolean;
    isBuilderStructureOpen: boolean;
    builderPaneMode: BuilderBridgeSidebarMode | 'design-system';
    previewViewport: PreviewViewport;
    previewInteractionState: BuilderBridgeInteractionState;
    selectedBuilderTarget: BuilderEditableTarget | null;
    selectedBuilderSectionLocalId: string | null;
    selectedPreviewSectionKey: string | null;
    activeBuilderPageIdentity: BuilderBridgePageIdentity;
    builderStructureItems: BuilderStructureItem[];
    pendingBuilderStructureMutation: PendingBuilderStructureMutation | null;
    builderSidebarFrameRef: MutableRefObject<HTMLIFrameElement | null>;
    builderSidebarCommandQueueRef: MutableRefObject<BuilderBridgeMessage[]>;
    pendingBuilderChangeSetRequestIdRef: MutableRefObject<string | null>;
    lastBuilderReadySignatureRef: MutableRefObject<string | null>;
    lastBuilderSnapshotSignatureRef: MutableRefObject<string | null>;
    latestBuilderStateCursorRef: MutableRefObject<BuilderBridgeStateCursor | null>;
    structureSnapshotPageRef: MutableRefObject<BuilderBridgePageIdentity>;
    setStructureSnapshotPageIdentity: (page: BuilderBridgePageIdentity) => void;
    preferPersistedStructureStateRef: MutableRefObject<boolean>;
    justPlacedSectionRef: MutableRefObject<boolean>;
    setPreviewViewport: (viewport: PreviewViewport) => void;
    setPreviewInteractionState: (state: BuilderBridgeInteractionState) => void;
    setIsBuilderStructureOpen: StateUpdater<boolean>;
    selectBuilderTarget: (target: BuilderEditableTarget | null) => void;
    clearBuilderSelection: () => void;
    setBuilderPaneMode: (mode: BuilderBridgeSidebarMode) => void;
    setActiveLibraryItem: (item: BuilderLibraryItem | null) => void;
    setIsSavingBuilderDraft: (saving: boolean) => void;
    setPreviewRefreshTrigger: StateUpdater<number>;
    setBuilderLibraryItems: StateUpdater<BuilderLibraryItem[]>;
    setBuilderStructureItems: (items: BuilderStructureItem[]) => void;
    setBuilderCodePages: StateUpdater<BuilderCodePage[]>;
    setPendingBuilderStructureMutation: StateUpdater<PendingBuilderStructureMutation | null>;
    markBuilderSidebarReady: (ready?: boolean) => void;
    t: (key: string) => string;
}

interface UseChatEmbeddedBuilderBridgeResult {
    postBuilderCommand: (payload: Record<string, unknown>) => void;
    syncBuilderChangeSet: (changeSet: ChangeSet) => boolean;
    setStructurePanelOpen: (open: boolean) => void;
    handleBuilderSidebarFrameLoad: () => void;
}

export function useChatEmbeddedBuilderBridge({
    projectId,
    viewMode,
    isVisualBuilderOpen,
    isBuilderSidebarReady,
    isBuilderPreviewReady,
    isBuilderStructureOpen,
    builderPaneMode,
    previewViewport,
    previewInteractionState,
    selectedBuilderTarget,
    selectedBuilderSectionLocalId,
    selectedPreviewSectionKey,
    activeBuilderPageIdentity,
    builderStructureItems,
    pendingBuilderStructureMutation,
    builderSidebarFrameRef,
    builderSidebarCommandQueueRef,
    pendingBuilderChangeSetRequestIdRef,
    lastBuilderReadySignatureRef,
    lastBuilderSnapshotSignatureRef,
    latestBuilderStateCursorRef,
    structureSnapshotPageRef,
    setStructureSnapshotPageIdentity,
    preferPersistedStructureStateRef,
    justPlacedSectionRef,
    setPreviewViewport,
    setPreviewInteractionState,
    setIsBuilderStructureOpen,
    selectBuilderTarget,
    clearBuilderSelection,
    setBuilderPaneMode,
    setActiveLibraryItem,
    setIsSavingBuilderDraft,
    setPreviewRefreshTrigger,
    setBuilderLibraryItems,
    setBuilderStructureItems,
    setBuilderCodePages,
    setPendingBuilderStructureMutation,
    markBuilderSidebarReady,
    t,
}: UseChatEmbeddedBuilderBridgeOptions): UseChatEmbeddedBuilderBridgeResult {
    const hasRequestedSidebarStateRef = useRef(false);
    const lastSidebarStateRequestAtRef = useRef<number>(0);
    const sidebarStateRetryTimeoutsRef = useRef<number[]>([]);
    const lastSelectionCommandSignatureRef = useRef<string | null>(null);
    const lastVisualStateCommandSignatureRef = useRef<string | null>(null);
    const lastReceivedSidebarVisualStateSignatureRef = useRef<string | null>(null);
    const lastReceivedSidebarSelectionSignatureRef = useRef<string | null>(null);
    const processedSidebarEnvelopeSignaturesRef = useRef<Set<string>>(new Set());
    const logChatBridge = useCallback((input: {
        phase: 'send' | 'receive' | 'ignore' | 'drop';
        target?: string | null;
        message?: BuilderBridgeMessage | null;
        rawType?: string | null;
        requestId?: string | null;
        reason?: string | null;
    }) => {
        logBuilderBridgeDiagnostic({
            actor: 'chat',
            ...input,
        });
    }, []);

    const clearSidebarStateRetryTimeouts = useCallback(() => {
        if (typeof window === 'undefined') {
            return;
        }

        sidebarStateRetryTimeoutsRef.current.forEach((timeoutId) => {
            window.clearTimeout(timeoutId);
        });
        sidebarStateRetryTimeoutsRef.current = [];
    }, []);

    const requestSidebarState = useCallback((reason: string, force = false) => {
        if (typeof window === 'undefined') {
            return false;
        }

        if (isBuilderSidebarReady || lastBuilderReadySignatureRef.current) {
            return false;
        }

        const frameWindow = builderSidebarFrameRef.current?.contentWindow;
        if (!frameWindow) {
            return false;
        }

        const now = Date.now();
        if (!force && now - lastSidebarStateRequestAtRef.current < 250) {
            return false;
        }

        hasRequestedSidebarStateRef.current = true;
        lastSidebarStateRequestAtRef.current = now;

        const requestStateMessage = buildBuilderRequestStateMessage({
            reason,
        }, {
            source: 'chat',
            projectId,
            page: activeBuilderPageIdentity,
        });
        logChatBridge({
            phase: 'send',
            target: 'sidebar',
            message: requestStateMessage,
            reason,
        });
        postBuilderBridgeEnvelope(frameWindow, window.location.origin, requestStateMessage);
        return true;
    }, [
        activeBuilderPageIdentity,
        builderSidebarFrameRef,
        isBuilderSidebarReady,
        lastBuilderReadySignatureRef,
        logChatBridge,
        projectId,
    ]);

    const scheduleSidebarStateRetries = useCallback((reasonPrefix: string) => {
        if (typeof window === 'undefined') {
            return;
        }

        clearSidebarStateRetryTimeouts();
        [350, 1200].forEach((delay, index) => {
            const timeoutId = window.setTimeout(() => {
                requestSidebarState(`${reasonPrefix}-retry-${index + 1}`);
            }, delay);
            sidebarStateRetryTimeoutsRef.current.push(timeoutId);
        });
    }, [clearSidebarStateRetryTimeouts, requestSidebarState]);

    const createBridgeMessageFromPayload = useCallback((payload: Record<string, unknown>): BuilderBridgeMessage | null => {
        const type = typeof payload.type === 'string' ? payload.type : null;
        if (!type) {
            return null;
        }

        const input = {
            source: 'chat' as const,
            projectId,
            page: activeBuilderPageIdentity,
            requestId: typeof payload.requestId === 'string' ? payload.requestId : null,
        };

        switch (type) {
            case 'builder:ping':
                return buildBuilderRequestStateMessage({ reason: 'sidebar-ping' }, input);
            case 'builder:clear-selected-section':
                return buildBuilderClearSelectionMessage('clear-selection', input);
            case 'builder:refresh-preview':
                return buildBuilderRefreshPreviewMessage({ reason: 'manual-refresh' }, input);
            case 'builder:save-draft':
                return buildBuilderSaveDraftMessage({ reason: 'save-draft' }, input);
            case 'builder:set-viewport':
                return buildBuilderSyncStateMessage({
                    viewport: payload.viewport === 'desktop' || payload.viewport === 'tablet' || payload.viewport === 'mobile'
                        ? payload.viewport
                        : null,
                }, input);
            case 'builder:set-interaction-state':
                return buildBuilderSyncStateMessage({
                    interactionState: payload.interactionState === 'normal'
                        || payload.interactionState === 'hover'
                        || payload.interactionState === 'focus'
                        || payload.interactionState === 'active'
                        ? payload.interactionState
                        : null,
                }, input);
            case 'builder:set-sidebar-mode':
                return buildBuilderSyncStateMessage({
                    sidebarMode: payload.mode === 'elements' || payload.mode === 'settings'
                        ? payload.mode
                        : null,
                }, input);
            case 'builder:set-structure-open':
                return buildBuilderSyncStateMessage({
                    structureOpen: typeof payload.open === 'boolean' ? payload.open : null,
                }, input);
            case 'builder:set-selected-target':
                return buildBuilderSelectTargetMessage({
                    pageId: activeBuilderPageIdentity.pageId,
                    pageSlug: activeBuilderPageIdentity.pageSlug,
                    pageTitle: activeBuilderPageIdentity.pageTitle,
                    sectionLocalId: typeof payload.sectionLocalId === 'string' ? payload.sectionLocalId : null,
                    sectionKey: typeof payload.sectionKey === 'string' ? payload.sectionKey : null,
                    componentType: typeof payload.componentType === 'string' ? payload.componentType : null,
                    componentName: typeof payload.componentName === 'string' ? payload.componentName : null,
                    parameterPath: typeof payload.parameterPath === 'string' ? payload.parameterPath : null,
                    componentPath: typeof payload.componentPath === 'string' ? payload.componentPath : null,
                    elementId: typeof payload.elementId === 'string' ? payload.elementId : null,
                    selector: typeof payload.selector === 'string' ? payload.selector : null,
                    textPreview: typeof payload.textPreview === 'string' ? payload.textPreview : null,
                    props: payload.props && typeof payload.props === 'object' ? payload.props as Record<string, unknown> : null,
                    fieldLabel: typeof payload.fieldLabel === 'string' ? payload.fieldLabel : null,
                    fieldGroup: typeof payload.fieldGroup === 'string'
                        ? payload.fieldGroup as BuilderEditableTarget['fieldGroup']
                        : null,
                    builderId: typeof payload.builderId === 'string' ? payload.builderId : null,
                    parentId: typeof payload.parentId === 'string' ? payload.parentId : null,
                    editableFields: Array.isArray(payload.editableFields) ? payload.editableFields as string[] : [],
                    sectionId: typeof payload.sectionId === 'string' ? payload.sectionId : null,
                    instanceId: typeof payload.instanceId === 'string' ? payload.instanceId : null,
                    variants: payload.variants && typeof payload.variants === 'object'
                        ? payload.variants as BuilderEditableTarget['variants']
                        : null,
                    allowedUpdates: payload.allowedUpdates && typeof payload.allowedUpdates === 'object'
                        ? payload.allowedUpdates as BuilderEditableTarget['allowedUpdates']
                        : null,
                    currentBreakpoint: previewViewport,
                    currentInteractionState: previewInteractionState,
                    responsiveContext: payload.responsiveContext && typeof payload.responsiveContext === 'object'
                        ? payload.responsiveContext as BuilderEditableTarget['responsiveContext']
                        : null,
                }, input);
            case 'builder:set-initial-sections':
                return buildBuilderInsertNodeMessage({
                    sections: Array.isArray(payload.sections)
                        ? payload.sections.filter((entry): entry is Record<string, unknown> => Boolean(entry && typeof entry === 'object'))
                        : [],
                }, input);
            case 'builder:apply-change-set':
                return payload.changeSet && typeof payload.changeSet === 'object'
                    ? buildBuilderPatchPropsMessage({ changeSet: payload.changeSet as Record<string, unknown> }, input)
                    : null;
            case 'builder:add-section-by-key':
                return buildBuilderInsertNodeMessage({
                    sectionKey: typeof payload.sectionKey === 'string' ? payload.sectionKey : null,
                    sectionLocalId: typeof payload.sectionLocalId === 'string' ? payload.sectionLocalId : null,
                    afterSectionLocalId: typeof payload.afterSectionLocalId === 'string' ? payload.afterSectionLocalId : null,
                    targetSectionKey: typeof payload.targetSectionKey === 'string' ? payload.targetSectionKey : null,
                    placement: payload.placement === 'before' || payload.placement === 'after' || payload.placement === 'inside'
                        ? payload.placement
                        : null,
                }, input);
            case 'builder:remove-section':
                return buildBuilderDeleteNodeMessage({
                    sectionLocalId: typeof payload.sectionLocalId === 'string' ? payload.sectionLocalId : null,
                    sectionIndex: typeof payload.sectionIndex === 'number' ? payload.sectionIndex : null,
                    sectionKey: typeof payload.sectionKey === 'string' ? payload.sectionKey : null,
                }, input);
            case 'builder:move-section': {
                const sectionLocalId = typeof payload.sectionLocalId === 'string' ? payload.sectionLocalId : null;
                const targetSectionLocalId = typeof payload.targetSectionLocalId === 'string' ? payload.targetSectionLocalId : null;
                const position = payload.position === 'before' || payload.position === 'after' ? payload.position : null;
                if (!sectionLocalId || !targetSectionLocalId || !position) {
                    return null;
                }

                return buildBuilderMoveNodeMessage({
                    sectionLocalId,
                    targetSectionLocalId,
                    position,
                }, input);
            }
            default:
                return null;
        }
    }, [
        activeBuilderPageIdentity,
        previewInteractionState,
        previewViewport,
        projectId,
    ]);

    const flushBuilderCommandQueue = useCallback(() => {
        if (typeof window === 'undefined') {
            return;
        }

        if (!isBuilderSidebarReady || !isBuilderPreviewReady) {
            return;
        }

        const frameWindow = builderSidebarFrameRef.current?.contentWindow;
        if (!frameWindow) {
            return;
        }

        const queue = builderSidebarCommandQueueRef.current.splice(0);
        queue.forEach((message) => {
            logChatBridge({
                phase: 'send',
                target: 'sidebar',
                message,
                reason: 'flush-queued-command',
            });
            postBuilderBridgeEnvelope(frameWindow, window.location.origin, message);
        });
    }, [builderSidebarCommandQueueRef, builderSidebarFrameRef, isBuilderPreviewReady, isBuilderSidebarReady, logChatBridge]);

    const postBuilderMessage = useCallback((message: BuilderBridgeMessage) => {
        if (typeof window === 'undefined') {
            return;
        }

        const frameWindow = builderSidebarFrameRef.current?.contentWindow;
        if (!frameWindow || !isBuilderSidebarReady || !isBuilderPreviewReady) {
            builderSidebarCommandQueueRef.current.push(message);
            requestSidebarState('queued-command');
            return;
        }

        logChatBridge({
            phase: 'send',
            target: 'sidebar',
            message,
        });
        postBuilderBridgeEnvelope(frameWindow, window.location.origin, message);
    }, [
        builderSidebarCommandQueueRef,
        builderSidebarFrameRef,
        isBuilderPreviewReady,
        isBuilderSidebarReady,
        logChatBridge,
        requestSidebarState,
    ]);

    const postBuilderCommand = useCallback((payload: Record<string, unknown>) => {
        if (typeof window === 'undefined') {
            return;
        }

        const message = createBridgeMessageFromPayload(payload);
        if (!message) {
            logChatBridge({
                phase: 'drop',
                target: 'sidebar',
                rawType: typeof payload.type === 'string' ? payload.type : null,
                requestId: typeof payload.requestId === 'string' ? payload.requestId : null,
                reason: 'unsupported-or-invalid-command-payload',
            });
            return;
        }

        postBuilderMessage(message);
    }, [
        createBridgeMessageFromPayload,
        logChatBridge,
        postBuilderMessage,
    ]);

    const resolveSelectedTargetPayload = useCallback(() => {
        const matchingItem = selectedBuilderSectionLocalId
            ? builderStructureItems.find((item) => item.localId === selectedBuilderSectionLocalId) ?? null
            : (selectedPreviewSectionKey ? builderStructureItems.find((item) => item.sectionKey === selectedPreviewSectionKey) ?? null : null);
        return buildCanonicalBridgeSelectedTargetPayload({
            pageIdentity: activeBuilderPageIdentity,
            target: selectedBuilderTarget,
            fallback: matchingItem || selectedBuilderSectionLocalId || selectedPreviewSectionKey
                ? {
                    sectionLocalId: selectedBuilderSectionLocalId ?? matchingItem?.localId ?? null,
                    sectionKey: selectedPreviewSectionKey ?? matchingItem?.sectionKey ?? null,
                    componentType: selectedPreviewSectionKey ?? matchingItem?.sectionKey ?? null,
                    componentName: matchingItem?.label ?? null,
                    textPreview: matchingItem
                        ? buildSectionPreviewText(matchingItem.props, matchingItem.previewText, matchingItem.sectionKey)
                        : null,
                }
                : null,
            currentBreakpoint: previewViewport,
            currentInteractionState: previewInteractionState,
        });
    }, [
        activeBuilderPageIdentity,
        builderStructureItems,
        previewInteractionState,
        previewViewport,
        selectedBuilderSectionLocalId,
        selectedBuilderTarget,
        selectedPreviewSectionKey,
    ]);

    const syncEmbeddedBuilderControls = useCallback(() => {
        const normalizedSidebarMode = builderPaneMode === 'design-system' ? 'settings' : builderPaneMode;
        const visualStateSignature = buildBuilderBridgeVisualStateSignature({
            pageId: activeBuilderPageIdentity.pageId,
            pageSlug: activeBuilderPageIdentity.pageSlug,
            pageTitle: activeBuilderPageIdentity.pageTitle,
            viewport: previewViewport,
            interactionState: previewInteractionState,
            structureOpen: isBuilderStructureOpen,
            sidebarMode: normalizedSidebarMode,
        });

        if (
            lastReceivedSidebarVisualStateSignatureRef.current !== visualStateSignature
            && lastVisualStateCommandSignatureRef.current !== visualStateSignature
        ) {
            lastVisualStateCommandSignatureRef.current = visualStateSignature;
            postBuilderMessage(buildBuilderSyncStateMessage({
                viewport: previewViewport,
                structureOpen: isBuilderStructureOpen,
                sidebarMode: normalizedSidebarMode,
                interactionState: previewInteractionState,
            }, {
                source: 'chat',
                projectId,
                page: activeBuilderPageIdentity,
            }));
        }

        const selectedTargetPayload = resolveSelectedTargetPayload();
        const selectionPayload = selectedTargetPayload
            ? {
                ...selectedTargetPayload,
                pageId: activeBuilderPageIdentity.pageId,
                pageSlug: activeBuilderPageIdentity.pageSlug,
                pageTitle: activeBuilderPageIdentity.pageTitle,
                currentBreakpoint: previewViewport,
                currentInteractionState: previewInteractionState,
            }
            : null;
        const selectionSignature = buildBuilderBridgeSelectionSignature({
            pageId: activeBuilderPageIdentity.pageId,
            pageSlug: activeBuilderPageIdentity.pageSlug,
            pageTitle: activeBuilderPageIdentity.pageTitle,
            target: selectionPayload,
        });

        if (selectionSignature === lastReceivedSidebarSelectionSignatureRef.current) {
            lastSelectionCommandSignatureRef.current = selectionSignature;
            return;
        }

        if (lastSelectionCommandSignatureRef.current !== selectionSignature) {
            lastSelectionCommandSignatureRef.current = selectionSignature;
            if (selectedTargetPayload) {
                postBuilderCommand({
                    type: 'builder:set-selected-target',
                    ...selectedTargetPayload,
                });
            } else {
                postBuilderCommand({
                    type: 'builder:clear-selected-section',
                });
            }
        }
    }, [
        activeBuilderPageIdentity.pageId,
        activeBuilderPageIdentity.pageSlug,
        activeBuilderPageIdentity.pageTitle,
        lastSelectionCommandSignatureRef,
        builderPaneMode,
        isBuilderStructureOpen,
        postBuilderMessage,
        previewInteractionState,
        previewViewport,
        projectId,
        resolveSelectedTargetPayload,
    ]);

    const syncBuilderChangeSet = useCallback((changeSet: ChangeSet): boolean => {
        if (!isVisualBuilderOpen || !isBuilderSidebarReady || !isBuilderPreviewReady || !Array.isArray(changeSet.operations) || changeSet.operations.length === 0) {
            return false;
        }

        const requestId = createBuilderBridgeRequestId('builder-change-set');
        pendingBuilderChangeSetRequestIdRef.current = requestId;
        postBuilderCommand({
            type: 'builder:apply-change-set',
            requestId,
            changeSet,
        });

        return true;
    }, [isBuilderPreviewReady, isBuilderSidebarReady, isVisualBuilderOpen, pendingBuilderChangeSetRequestIdRef, postBuilderCommand]);

    const setStructurePanelOpen = useCallback((open: boolean) => {
        preferPersistedStructureStateRef.current = false;
        setIsBuilderStructureOpen(open);
        postBuilderCommand({
            type: 'builder:set-structure-open',
            open,
        });
    }, [postBuilderCommand, preferPersistedStructureStateRef, setIsBuilderStructureOpen]);

    const handleBuilderSidebarFrameLoad = useCallback(() => {
        markBuilderSidebarReady(false);
        clearSidebarStateRetryTimeouts();
        preferPersistedStructureStateRef.current = true;
        requestAnimationFrame(() => {
            requestSidebarState('sidebar-frame-load', true);
            scheduleSidebarStateRetries('sidebar-frame-load');
            window.setTimeout(() => {
                preferPersistedStructureStateRef.current = false;
            }, 0);
        });
    }, [
        clearSidebarStateRetryTimeouts,
        markBuilderSidebarReady,
        preferPersistedStructureStateRef,
        requestSidebarState,
        scheduleSidebarStateRetries,
    ]);

    useEffect(() => {
        if (viewMode !== 'inspect' || typeof window === 'undefined') {
            return;
        }

    const handleEmbeddedBuilderMessage = (event: MessageEvent) => {
            if (event.origin !== window.location.origin) {
                logChatBridge({
                    phase: 'ignore',
                    target: 'chat',
                    reason: 'origin-mismatch',
                });
                return;
            }

            const parsedEnvelope = inspectBuilderBridgeEnvelope(event.data);
            const payload = parsedEnvelope.message;
            if (!payload) {
                logChatBridge({
                    phase: 'ignore',
                    target: 'chat',
                    reason: parsedEnvelope.error ?? 'invalid-envelope',
                });
                return;
            }

            if (builderBridgeMessageEchoesActor(payload, 'chat')) {
                logChatBridge({
                    phase: 'ignore',
                    target: 'chat',
                    message: payload,
                    reason: 'self-origin',
                });
                return;
            }

            if (payload.source !== 'sidebar') {
                logChatBridge({
                    phase: 'ignore',
                    target: 'chat',
                    message: payload,
                    reason: 'unexpected-source',
                });
                return;
            }

            if (!builderBridgeEnvelopeTargetsProject(payload, projectId)) {
                logChatBridge({
                    phase: 'ignore',
                    target: 'chat',
                    message: payload,
                    reason: 'project-mismatch',
                });
                return;
            }

            if (!rememberBuilderBridgeEnvelopeSignature(processedSidebarEnvelopeSignaturesRef.current, payload.signature)) {
                logChatBridge({
                    phase: 'ignore',
                    target: 'chat',
                    message: payload,
                    reason: 'duplicate-envelope-signature',
                });
                return;
            }

            const shouldScopeToActivePage = activeBuilderPageIdentity.pageId !== null || activeBuilderPageIdentity.pageSlug !== null;
            if (shouldScopeToActivePage && !payloadTargetsBuilderBridgePage(payload, activeBuilderPageIdentity)) {
                logChatBridge({
                    phase: 'ignore',
                    target: 'chat',
                    message: payload,
                    reason: 'page-mismatch',
                });
                return;
            }

            logChatBridge({
                phase: 'receive',
                target: 'chat',
                message: payload,
            });

            const nextBuilderStateCursor = {
                pageId: payload.pageId ?? null,
                pageSlug: payload.pageSlug ?? null,
                stateVersion: 'stateVersion' in payload.payload ? payload.payload.stateVersion ?? null : null,
                revisionVersion: 'revisionVersion' in payload.payload ? payload.payload.revisionVersion ?? null : null,
            };
            const builderPayloadIsStale = isStaleBuilderBridgeState(
                latestBuilderStateCursorRef.current,
                nextBuilderStateCursor,
            );

            if (payload.type === 'BUILDER_SYNC_STATE') {
                lastReceivedSidebarVisualStateSignatureRef.current = buildBuilderBridgeVisualStateSignature({
                    pageId: payload.pageId ?? activeBuilderPageIdentity.pageId,
                    pageSlug: payload.pageSlug ?? activeBuilderPageIdentity.pageSlug,
                    pageTitle: payload.pageTitle ?? activeBuilderPageIdentity.pageTitle,
                    viewport: payload.payload.viewport ?? previewViewport,
                    interactionState: payload.payload.interactionState ?? previewInteractionState,
                    structureOpen: typeof payload.payload.structureOpen === 'boolean'
                        ? payload.payload.structureOpen
                        : isBuilderStructureOpen,
                    sidebarMode: payload.payload.sidebarMode ?? (builderPaneMode === 'design-system' ? 'settings' : builderPaneMode),
                });
                if (payload.payload.selectedTarget) {
                    lastReceivedSidebarSelectionSignatureRef.current = buildBuilderBridgeSelectionSignature({
                        pageId: payload.pageId ?? activeBuilderPageIdentity.pageId,
                        pageSlug: payload.pageSlug ?? activeBuilderPageIdentity.pageSlug,
                        pageTitle: payload.pageTitle ?? activeBuilderPageIdentity.pageTitle,
                        target: payload.payload.selectedTarget,
                    });
                }
                if (builderPayloadIsStale) {
                    logChatBridge({
                        phase: 'ignore',
                        target: 'chat',
                        message: payload,
                        reason: 'stale-state',
                    });
                    return;
                }

                latestBuilderStateCursorRef.current = nextBuilderStateCursor;
                if (payload.payload.viewport === 'desktop' || payload.payload.viewport === 'tablet' || payload.payload.viewport === 'mobile') {
                    setPreviewViewport(payload.payload.viewport);
                }
                if (
                    payload.payload.interactionState === 'normal'
                    || payload.payload.interactionState === 'hover'
                    || payload.payload.interactionState === 'focus'
                    || payload.payload.interactionState === 'active'
                ) {
                    setPreviewInteractionState(payload.payload.interactionState);
                }

                if (typeof payload.payload.structureOpen === 'boolean' && payload.payload.structureOpen !== isBuilderStructureOpen) {
                    setIsBuilderStructureOpen(payload.payload.structureOpen);
                }

                if (payload.payload.draftSaveState) {
                    const nextIsSaving = payload.payload.draftSaveState.isSaving;
                    setIsSavingBuilderDraft(nextIsSaving);

                    if (!nextIsSaving) {
                        if (payload.payload.draftSaveState.success === true) {
                            toast.success(t('Draft saved'));
                        } else if (typeof payload.payload.draftSaveState.message === 'string' && payload.payload.draftSaveState.message.trim() !== '') {
                            toast.error(payload.payload.draftSaveState.message);
                        }
                    }
                }

                if (Array.isArray(payload.payload.libraryItems)) {
                    const nextItems = payload.payload.libraryItems.flatMap((entry) => {
                        const key = entry.key.trim();
                        const label = entry.label.trim();
                        const category = entry.category.trim();

                        if (key === '') {
                            return [];
                        }

                        return [{
                            key,
                            label: label || key,
                            category,
                        }];
                    });

                    setBuilderLibraryItems((current) => {
                        const merged = new Map<string, BuilderLibraryItem>();

                        current.forEach((item) => {
                            const key = item.key.trim();
                            if (key !== '') {
                                merged.set(key, item);
                            }
                        });

                        nextItems.forEach((item) => {
                            const key = item.key.trim();
                            if (key !== '') {
                                merged.set(key, {
                                    ...merged.get(key),
                                    ...item,
                                });
                            }
                        });

                        return Array.from(merged.values());
                    });
                }

                if (Array.isArray(payload.payload.structureSections)) {
                    const snapshotSignature = buildBuilderBridgeEventSignature({
                        pageId: payload.pageId,
                        pageSlug: payload.pageSlug,
                        stateVersion: payload.payload.stateVersion ?? null,
                        revisionVersion: payload.payload.revisionVersion ?? null,
                        structureHash: payload.payload.structureHash ?? null,
                    });
                    if (lastBuilderSnapshotSignatureRef.current === snapshotSignature) {
                        logChatBridge({
                            phase: 'ignore',
                            target: 'chat',
                            message: payload,
                            reason: 'duplicate-structure-snapshot',
                        });
                        return;
                    }

                    const nextItems: BuilderStructureItem[] = [];
                    const nextCodeSections: BuilderCodeSection[] = [];
                    const snapshotPage = normalizeBuilderBridgePageIdentity({
                        pageId: payload.pageId,
                        pageSlug: payload.pageSlug,
                        pageTitle: payload.pageTitle,
                    });

                    payload.payload.structureSections.forEach((entry, index) => {
                        const localId = entry.localId.trim();
                        const sectionKey = entry.sectionKey.trim();
                        const sectionType = entry.type.trim();
                        const label = entry.label.trim();
                        const previewText = entry.previewText.trim();
                        const propsText = entry.propsText.trim();
                        const props = entry.props ?? (parseBuilderSectionProps(propsText) ?? buildFallbackSectionProps(label, previewText));

                        if (localId === '' || sectionKey === '') {
                            return;
                        }

                        nextItems.push({
                            localId,
                            sectionKey,
                            label: label || sectionKey,
                            previewText,
                            props,
                        });

                        if (sectionType === '') {
                            return;
                        }

                        nextCodeSections.push({
                            localId: localId || `builder-section-${index + 1}`,
                            type: sectionType,
                            props,
                            propsText: propsText !== '' ? propsText : stringifySectionProps(props),
                        });
                    });

                    structureSnapshotPageRef.current = snapshotPage;
                    setStructureSnapshotPageIdentity(snapshotPage);
                    latestBuilderStateCursorRef.current = nextBuilderStateCursor;
                    lastBuilderSnapshotSignatureRef.current = snapshotSignature;
                    setPendingBuilderStructureMutation(null);
                    setBuilderStructureItems(nextItems);
                    const nextSelectedTarget = selectedBuilderTarget?.sectionLocalId
                        ? (() => {
                            const nextItem = nextItems.find((item) => item.localId === selectedBuilderTarget.sectionLocalId) ?? null;
                            if (!nextItem) {
                                return null;
                            }

                            return {
                                ...selectedBuilderTarget,
                                sectionKey: nextItem.sectionKey,
                                componentType: nextItem.sectionKey,
                                componentName: nextItem.label,
                                textPreview: buildSectionPreviewText(nextItem.props, nextItem.previewText, nextItem.sectionKey),
                                props: nextItem.props,
                            };
                        })()
                        : selectedBuilderTarget;
                    selectBuilderTarget(nextSelectedTarget);
                    setBuilderCodePages((current) => upsertWorkspaceBuilderCodePages(current, {
                        page: snapshotPage,
                        sections: nextCodeSections,
                    }, {
                        buildPagePath: (page, index) => buildGeneratedPagePath({
                            page_id: page.pageId,
                            slug: page.pageSlug,
                            title: page.pageTitle,
                        }, index),
                    }));
                }

                if (payload.payload.previewRefresh) {
                    setPreviewRefreshTrigger(Date.now());
                }

                return;
            }

            if (payload.type === 'BUILDER_SELECT_TARGET') {
                lastReceivedSidebarSelectionSignatureRef.current = buildBuilderBridgeSelectionSignature({
                    pageId: payload.pageId ?? activeBuilderPageIdentity.pageId,
                    pageSlug: payload.pageSlug ?? activeBuilderPageIdentity.pageSlug,
                    pageTitle: payload.pageTitle ?? activeBuilderPageIdentity.pageTitle,
                    target: payload.payload.target
                        ? {
                            ...payload.payload.target,
                            pageId: payload.pageId ?? activeBuilderPageIdentity.pageId,
                            pageSlug: payload.pageSlug ?? activeBuilderPageIdentity.pageSlug,
                            pageTitle: payload.pageTitle ?? activeBuilderPageIdentity.pageTitle,
                            currentBreakpoint: payload.payload.target.currentBreakpoint ?? previewViewport,
                            currentInteractionState: payload.payload.target.currentInteractionState ?? previewInteractionState,
                        }
                        : null,
                });
                if (builderPayloadIsStale) {
                    logChatBridge({
                        phase: 'ignore',
                        target: 'chat',
                        message: payload,
                        reason: 'stale-selection',
                    });
                    return;
                }

                latestBuilderStateCursorRef.current = nextBuilderStateCursor;
                const rawNextTarget = buildEditableTargetFromMessagePayload({
                    ...(payload.payload.target ?? {}),
                    pageId: payload.pageId ?? activeBuilderPageIdentity.pageId,
                    pageSlug: payload.pageSlug ?? activeBuilderPageIdentity.pageSlug,
                    pageTitle: payload.pageTitle ?? activeBuilderPageIdentity.pageTitle,
                    currentBreakpoint: payload.payload.target?.currentBreakpoint ?? previewViewport,
                    currentInteractionState: payload.payload.target?.currentInteractionState ?? previewInteractionState,
                });
                const nextTarget = buildSectionScopedEditableTarget(editableTargetToMessagePayload(rawNextTarget)) ?? rawNextTarget;

                if (nextTarget === null) {
                    clearBuilderSelection();
                } else if (!areBuilderEditableTargetsEqual(selectedBuilderTarget, nextTarget)) {
                    selectBuilderTarget(nextTarget);
                }
                setActiveLibraryItem(null);
                if (justPlacedSectionRef.current) {
                    setBuilderPaneMode('elements');
                    justPlacedSectionRef.current = false;
                } else {
                    setBuilderPaneMode(nextTarget ? 'settings' : 'elements');
                }
                return;
            }

            if (payload.type === 'BUILDER_CLEAR_SELECTION') {
                lastReceivedSidebarSelectionSignatureRef.current = buildBuilderBridgeSelectionSignature({
                    pageId: payload.pageId ?? activeBuilderPageIdentity.pageId,
                    pageSlug: payload.pageSlug ?? activeBuilderPageIdentity.pageSlug,
                    pageTitle: payload.pageTitle ?? activeBuilderPageIdentity.pageTitle,
                    target: null,
                });
                clearBuilderSelection();
                setActiveLibraryItem(null);
                setBuilderPaneMode('elements');
                return;
            }

            if (payload.type === 'BUILDER_ACK') {
                if (payload.requestId === pendingBuilderChangeSetRequestIdRef.current) {
                    pendingBuilderChangeSetRequestIdRef.current = null;
                    if (payload.payload.success !== true || payload.payload.changed !== true) {
                        setPreviewRefreshTrigger(Date.now());
                    }
                    return;
                }

                const mutationResolution = resolvePendingBuilderStructureMutation(pendingBuilderStructureMutation, {
                    requestId: payload.requestId,
                    success: payload.payload.success,
                    changed: payload.payload.changed ?? false,
                    error: payload.payload.error,
                });
                if (mutationResolution.status === 'ignore') {
                    return;
                }
                if (mutationResolution.status === 'clear-error') {
                    setPendingBuilderStructureMutation(null);
                    if (pendingBuilderStructureMutation?.selectionSnapshot) {
                        selectBuilderTarget(pendingBuilderStructureMutation.selectionSnapshot.target);
                    }
                    toast.error(mutationResolution.errorMessage ?? t('Builder update failed'));
                }
                return;
            }

            if (payload.type === 'BUILDER_READY') {
                if (builderPayloadIsStale) {
                    logChatBridge({
                        phase: 'ignore',
                        target: 'chat',
                        message: payload,
                        reason: 'stale-ready',
                    });
                    return;
                }

                const readySignature = buildBuilderBridgeEventSignature({
                    pageId: payload.pageId,
                    pageSlug: payload.pageSlug,
                    stateVersion: payload.payload.stateVersion ?? null,
                    revisionVersion: payload.payload.revisionVersion ?? null,
                    structureHash: payload.payload.structureHash ?? null,
                });
                if (lastBuilderReadySignatureRef.current === readySignature) {
                    logChatBridge({
                        phase: 'ignore',
                        target: 'chat',
                        message: payload,
                        reason: 'duplicate-ready',
                    });
                    return;
                }
                latestBuilderStateCursorRef.current = nextBuilderStateCursor;
                lastBuilderReadySignatureRef.current = readySignature;
                hasRequestedSidebarStateRef.current = false;
                lastSidebarStateRequestAtRef.current = 0;
                clearSidebarStateRetryTimeouts();
                markBuilderSidebarReady(true);
                requestAnimationFrame(() => {
                    if (isBuilderPreviewReady) {
                        syncEmbeddedBuilderControls();
                        flushBuilderCommandQueue();
                    }
                });
            }
        };

        window.addEventListener('message', handleEmbeddedBuilderMessage);

        return () => {
            window.removeEventListener('message', handleEmbeddedBuilderMessage);
        };
    }, [
        activeBuilderPageIdentity,
        builderStructureItems,
        flushBuilderCommandQueue,
        isBuilderStructureOpen,
        lastBuilderReadySignatureRef,
        lastBuilderSnapshotSignatureRef,
        latestBuilderStateCursorRef,
        clearSidebarStateRetryTimeouts,
        pendingBuilderChangeSetRequestIdRef,
        pendingBuilderStructureMutation,
        postBuilderCommand,
        preferPersistedStructureStateRef,
        previewInteractionState,
        previewViewport,
        setActiveLibraryItem,
        setBuilderCodePages,
        setBuilderLibraryItems,
        setBuilderPaneMode,
        setBuilderStructureItems,
        setIsBuilderStructureOpen,
        setIsSavingBuilderDraft,
        setPendingBuilderStructureMutation,
        setPreviewInteractionState,
        setPreviewRefreshTrigger,
        setPreviewViewport,
        selectBuilderTarget,
        clearBuilderSelection,
        structureSnapshotPageRef,
        setStructureSnapshotPageIdentity,
        syncEmbeddedBuilderControls,
        t,
        viewMode,
        projectId,
        isBuilderPreviewReady,
        markBuilderSidebarReady,
        justPlacedSectionRef,
    ]);

    useEffect(() => {
        if (viewMode !== 'inspect') {
            return;
        }

        setPendingBuilderStructureMutation(null);
        lastBuilderReadySignatureRef.current = null;
        lastBuilderSnapshotSignatureRef.current = null;
        latestBuilderStateCursorRef.current = null;
        hasRequestedSidebarStateRef.current = false;
        lastSidebarStateRequestAtRef.current = 0;
        lastSelectionCommandSignatureRef.current = null;
        lastVisualStateCommandSignatureRef.current = null;
        lastReceivedSidebarVisualStateSignatureRef.current = null;
        lastReceivedSidebarSelectionSignatureRef.current = null;
        processedSidebarEnvelopeSignaturesRef.current.clear();
        clearSidebarStateRetryTimeouts();
        markBuilderSidebarReady(false);
    }, [
        activeBuilderPageIdentity.pageId,
        activeBuilderPageIdentity.pageSlug,
        clearSidebarStateRetryTimeouts,
        viewMode,
    ]);

    useEffect(() => {
        if (viewMode !== 'inspect' || typeof window === 'undefined') {
            return;
        }
        const timeoutId = window.setTimeout(() => {
            requestSidebarState('inspect-open');
        }, 1500);
        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [
        activeBuilderPageIdentity.pageId,
        activeBuilderPageIdentity.pageSlug,
        activeBuilderPageIdentity.pageTitle,
        isBuilderSidebarReady,
        lastBuilderReadySignatureRef,
        requestSidebarState,
        viewMode,
    ]);

    useEffect(() => {
        return () => {
            clearSidebarStateRetryTimeouts();
        };
    }, [clearSidebarStateRetryTimeouts]);

    useEffect(() => {
        if (viewMode !== 'inspect') {
            return;
        }

        syncEmbeddedBuilderControls();
    }, [syncEmbeddedBuilderControls, viewMode]);

    return {
        postBuilderCommand,
        syncBuilderChangeSet,
        setStructurePanelOpen,
        handleBuilderSidebarFrameLoad,
    };
}
