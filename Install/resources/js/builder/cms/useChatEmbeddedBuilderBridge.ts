import { useCallback, useEffect, useRef, type MutableRefObject } from 'react';
import { toast } from 'sonner';
import type { ElementMention } from '@/types/inspector';
import {
    attachBuilderBridgePageIdentity,
    createBuilderBridgeRequestId,
    normalizeBuilderBridgePageIdentity,
    parseBuilderBridgeCmsEvent,
    payloadTargetsBuilderBridgePage,
    postBuilderBridgeMessage,
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
    buildBuilderSelectionMessageSignature,
    buildEditableTargetFromMessagePayload,
    buildSectionScopedEditableTarget,
    editableTargetToMessagePayload,
    type BuilderEditableTarget,
} from '@/builder/editingState';
import { buildSectionPreviewText, parseSectionProps as parseBuilderSectionProps, stringifySectionProps } from '@/builder/state/sectionProps';
import type { ChangeSet } from '@/hooks/useAiSiteEditor';
import type { PreviewViewport } from '@/components/Preview/PreviewViewportMenu';
import { buildCanonicalBridgeSelectedTargetPayload } from '@/builder/cms/canonicalSelectionPayload';

type ViewMode = 'preview' | 'inspect' | 'code' | 'design' | 'settings';
type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

interface BuilderLibraryItem {
    key: string;
    label: string;
    category: string;
}

interface UseChatEmbeddedBuilderBridgeOptions {
    viewMode: ViewMode;
    isVisualBuilderOpen: boolean;
    isBuilderSidebarReady: boolean;
    isBuilderStructureOpen: boolean;
    builderPaneMode: BuilderBridgeSidebarMode | 'design-system';
    previewViewport: PreviewViewport;
    previewInteractionState: BuilderBridgeInteractionState;
    selectedBuilderTarget: BuilderEditableTarget | null;
    selectedBuilderSectionLocalId: string | null;
    selectedPreviewSectionKey: string | null;
    selectedElement: ElementMention | null;
    activeBuilderPageIdentity: BuilderBridgePageIdentity;
    builderStructureItems: BuilderStructureItem[];
    pendingBuilderStructureMutation: PendingBuilderStructureMutation | null;
    builderSidebarFrameRef: MutableRefObject<HTMLIFrameElement | null>;
    builderSidebarCommandQueueRef: MutableRefObject<Array<Record<string, unknown>>>;
    pendingBuilderChangeSetRequestIdRef: MutableRefObject<string | null>;
    lastBuilderReadySignatureRef: MutableRefObject<string | null>;
    lastBuilderSnapshotSignatureRef: MutableRefObject<string | null>;
    latestBuilderStateCursorRef: MutableRefObject<BuilderBridgeStateCursor | null>;
    structureSnapshotPageRef: MutableRefObject<BuilderBridgePageIdentity>;
    preferPersistedStructureStateRef: MutableRefObject<boolean>;
    justPlacedSectionRef: MutableRefObject<boolean>;
    setPreviewViewport: (viewport: PreviewViewport) => void;
    setPreviewInteractionState: (state: BuilderBridgeInteractionState) => void;
    setIsBuilderStructureOpen: StateUpdater<boolean>;
    setSelectedBuilderSectionLocalId: StateUpdater<string | null>;
    setSelectedPreviewSectionKey: StateUpdater<string | null>;
    setSelectedBuilderTarget: StateUpdater<BuilderEditableTarget | null>;
    setBuilderPaneMode: (mode: BuilderBridgeSidebarMode) => void;
    setActiveLibraryItem: (item: BuilderLibraryItem | null) => void;
    setIsSavingBuilderDraft: (saving: boolean) => void;
    setPreviewRefreshTrigger: StateUpdater<number>;
    setBuilderLibraryItems: StateUpdater<BuilderLibraryItem[]>;
    setBuilderStructureItems: (items: BuilderStructureItem[]) => void;
    setBuilderCodePages: StateUpdater<BuilderCodePage[]>;
    setPendingBuilderStructureMutation: StateUpdater<PendingBuilderStructureMutation | null>;
    setIsBuilderSidebarReady: (ready: boolean) => void;
    t: (key: string) => string;
}

interface UseChatEmbeddedBuilderBridgeResult {
    postBuilderCommand: (payload: Record<string, unknown>) => void;
    syncBuilderChangeSet: (changeSet: ChangeSet) => boolean;
    setStructurePanelOpen: (open: boolean) => void;
    handleBuilderSidebarFrameLoad: () => void;
}

export function useChatEmbeddedBuilderBridge({
    viewMode,
    isVisualBuilderOpen,
    isBuilderSidebarReady,
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
    preferPersistedStructureStateRef,
    justPlacedSectionRef,
    setPreviewViewport,
    setPreviewInteractionState,
    setIsBuilderStructureOpen,
    setSelectedBuilderSectionLocalId,
    setSelectedPreviewSectionKey,
    setSelectedBuilderTarget,
    setBuilderPaneMode,
    setActiveLibraryItem,
    setIsSavingBuilderDraft,
    setPreviewRefreshTrigger,
    setBuilderLibraryItems,
    setBuilderStructureItems,
    setBuilderCodePages,
    setPendingBuilderStructureMutation,
    setIsBuilderSidebarReady,
    t,
}: UseChatEmbeddedBuilderBridgeOptions): UseChatEmbeddedBuilderBridgeResult {
    const lastSelectionCommandSignatureRef = useRef<string | null>(null);

    const flushBuilderCommandQueue = useCallback(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const frameWindow = builderSidebarFrameRef.current?.contentWindow;
        if (!frameWindow) {
            return;
        }

        const queue = builderSidebarCommandQueueRef.current.splice(0);
        queue.forEach((payload) => {
            postBuilderBridgeMessage(
                frameWindow,
                window.location.origin,
                'webu-chat-builder',
                payload,
                normalizeBuilderBridgePageIdentity(payload),
            );
        });
    }, [builderSidebarCommandQueueRef, builderSidebarFrameRef]);

    const postBuilderCommand = useCallback((payload: Record<string, unknown>) => {
        if (typeof window === 'undefined') {
            return;
        }

        const frameWindow = builderSidebarFrameRef.current?.contentWindow;
        if (!frameWindow || !isBuilderSidebarReady) {
            builderSidebarCommandQueueRef.current.push(attachBuilderBridgePageIdentity(payload, activeBuilderPageIdentity));
            return;
        }

        postBuilderBridgeMessage(frameWindow, window.location.origin, 'webu-chat-builder', payload, activeBuilderPageIdentity);
    }, [activeBuilderPageIdentity, builderSidebarCommandQueueRef, builderSidebarFrameRef, isBuilderSidebarReady]);

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
                    textPreview: matchingItem ? buildSectionPreviewText(matchingItem.props, matchingItem.previewText) : null,
                }
                : null,
            currentBreakpoint: previewViewport,
            currentInteractionState: previewInteractionState,
        });
    }, [
        activeBuilderPageIdentity.pageId,
        activeBuilderPageIdentity.pageSlug,
        activeBuilderPageIdentity.pageTitle,
        builderStructureItems,
        previewInteractionState,
        previewViewport,
        selectedBuilderSectionLocalId,
        selectedBuilderTarget,
        selectedPreviewSectionKey,
    ]);

    const syncEmbeddedBuilderControls = useCallback(() => {
        postBuilderCommand({
            type: 'builder:set-viewport',
            viewport: previewViewport,
        });
        postBuilderCommand({
            type: 'builder:set-structure-open',
            open: isBuilderStructureOpen,
        });
        postBuilderCommand({
            type: 'builder:set-sidebar-mode',
            mode: builderPaneMode === 'design-system' ? 'settings' : builderPaneMode,
        });
        postBuilderCommand({
            type: 'builder:set-interaction-state',
            interactionState: previewInteractionState,
        });

        const selectedTargetPayload = resolveSelectedTargetPayload();
        let selectionPayload: Record<string, unknown>;
        let selectionSignature: string;
        if (selectedTargetPayload) {
            selectionPayload = {
                type: 'builder:set-selected-target',
                ...selectedTargetPayload,
            };
            selectionSignature = JSON.stringify({
                type: 'builder:set-selected-target',
                pageId: activeBuilderPageIdentity.pageId ?? null,
                pageSlug: activeBuilderPageIdentity.pageSlug ?? null,
                pageTitle: activeBuilderPageIdentity.pageTitle ?? null,
                payload: buildBuilderSelectionMessageSignature({
                    ...selectedTargetPayload,
                    pageId: activeBuilderPageIdentity.pageId,
                    pageSlug: activeBuilderPageIdentity.pageSlug,
                    pageTitle: activeBuilderPageIdentity.pageTitle,
                    currentBreakpoint: previewViewport,
                    currentInteractionState: previewInteractionState,
                }),
            });
        } else {
            selectionPayload = {
                type: 'builder:clear-selected-section',
            };
            selectionSignature = JSON.stringify({
                type: 'builder:clear-selected-section',
                pageId: activeBuilderPageIdentity.pageId ?? null,
                pageSlug: activeBuilderPageIdentity.pageSlug ?? null,
                pageTitle: activeBuilderPageIdentity.pageTitle ?? null,
            });
        }

        if (lastSelectionCommandSignatureRef.current !== selectionSignature) {
            lastSelectionCommandSignatureRef.current = selectionSignature;
            postBuilderCommand(selectionPayload);
        }
    }, [
        activeBuilderPageIdentity.pageId,
        activeBuilderPageIdentity.pageSlug,
        activeBuilderPageIdentity.pageTitle,
        lastSelectionCommandSignatureRef,
        builderPaneMode,
        isBuilderStructureOpen,
        postBuilderCommand,
        previewInteractionState,
        previewViewport,
        resolveSelectedTargetPayload,
    ]);

    const syncBuilderChangeSet = useCallback((changeSet: ChangeSet): boolean => {
        if (!isVisualBuilderOpen || !isBuilderSidebarReady || !Array.isArray(changeSet.operations) || changeSet.operations.length === 0) {
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
    }, [isBuilderSidebarReady, isVisualBuilderOpen, pendingBuilderChangeSetRequestIdRef, postBuilderCommand]);

    const setStructurePanelOpen = useCallback((open: boolean) => {
        preferPersistedStructureStateRef.current = false;
        setIsBuilderStructureOpen(open);
        postBuilderCommand({
            type: 'builder:set-structure-open',
            open,
        });
    }, [postBuilderCommand, preferPersistedStructureStateRef, setIsBuilderStructureOpen]);

    const handleBuilderSidebarFrameLoad = useCallback(() => {
        setIsBuilderSidebarReady(false);
        preferPersistedStructureStateRef.current = true;
        requestAnimationFrame(() => {
            const frameWindow = builderSidebarFrameRef.current?.contentWindow;
            if (typeof window !== 'undefined' && frameWindow) {
                postBuilderBridgeMessage(
                    frameWindow,
                    window.location.origin,
                    'webu-chat-builder',
                    { type: 'builder:ping' },
                    activeBuilderPageIdentity,
                );
            }
            window.setTimeout(() => {
                preferPersistedStructureStateRef.current = false;
            }, 0);
        });
    }, [activeBuilderPageIdentity, builderSidebarFrameRef, preferPersistedStructureStateRef, setIsBuilderSidebarReady]);

    useEffect(() => {
        if (viewMode !== 'inspect' || typeof window === 'undefined') {
            return;
        }

        const handleEmbeddedBuilderMessage = (event: MessageEvent) => {
            if (event.origin !== window.location.origin) {
                return;
            }

            const payload = parseBuilderBridgeCmsEvent(event.data);
            if (!payload) {
                return;
            }

            const shouldScopeToActivePage = activeBuilderPageIdentity.pageId !== null || activeBuilderPageIdentity.pageSlug !== null;
            if (shouldScopeToActivePage && !payloadTargetsBuilderBridgePage(payload, activeBuilderPageIdentity)) {
                return;
            }

            const nextBuilderStateCursor = {
                pageId: payload.pageId ?? null,
                pageSlug: payload.pageSlug ?? null,
                stateVersion: payload.stateVersion ?? null,
                revisionVersion: payload.revisionVersion ?? null,
            };
            const builderPayloadIsStale = isStaleBuilderBridgeState(
                latestBuilderStateCursorRef.current,
                nextBuilderStateCursor,
            );

            if (payload.type === 'builder:state') {
                if (builderPayloadIsStale) {
                    return;
                }

                latestBuilderStateCursorRef.current = nextBuilderStateCursor;
                if (payload.viewport === 'desktop' || payload.viewport === 'tablet' || payload.viewport === 'mobile') {
                    setPreviewViewport(payload.viewport);
                }
                if (payload.interactionState === 'normal' || payload.interactionState === 'hover' || payload.interactionState === 'focus' || payload.interactionState === 'active') {
                    setPreviewInteractionState(payload.interactionState);
                }

                if (typeof payload.structureOpen === 'boolean' && payload.structureOpen !== isBuilderStructureOpen) {
                    postBuilderCommand({
                        type: 'builder:set-structure-open',
                        open: isBuilderStructureOpen,
                    });
                }

                return;
            }

            if (payload.type === 'builder:selected-section') {
                if (builderPayloadIsStale) {
                    return;
                }

                latestBuilderStateCursorRef.current = nextBuilderStateCursor;
                const nextLocalId = typeof payload.sectionLocalId === 'string' && payload.sectionLocalId.trim() !== ''
                    ? payload.sectionLocalId.trim()
                    : null;
                const nextSectionKey = typeof payload.sectionKey === 'string' && payload.sectionKey.trim() !== ''
                    ? payload.sectionKey.trim()
                    : null;

                const matchingItem = nextLocalId
                    ? builderStructureItems.find((item) => item.localId === nextLocalId) ?? null
                    : (nextSectionKey ? builderStructureItems.find((item) => item.sectionKey === nextSectionKey) ?? null : null);
                const nextTarget = nextLocalId || nextSectionKey
                    ? buildSectionScopedEditableTarget({
                        pageId: payload.pageId ?? activeBuilderPageIdentity.pageId,
                        pageSlug: payload.pageSlug ?? activeBuilderPageIdentity.pageSlug,
                        pageTitle: payload.pageTitle ?? activeBuilderPageIdentity.pageTitle,
                        sectionLocalId: nextLocalId,
                        sectionKey: nextSectionKey,
                        componentType: nextSectionKey,
                        componentName: matchingItem?.label ?? null,
                        props: matchingItem?.props ?? null,
                        textPreview: matchingItem ? buildSectionPreviewText(matchingItem.props, matchingItem.previewText) : null,
                        currentBreakpoint: previewViewport,
                        currentInteractionState: previewInteractionState,
                    })
                    : null;
                if (selectedBuilderSectionLocalId !== nextLocalId) {
                    setSelectedBuilderSectionLocalId(nextLocalId);
                }
                if (selectedPreviewSectionKey !== nextSectionKey) {
                    setSelectedPreviewSectionKey(nextSectionKey);
                }
                if (!areBuilderEditableTargetsEqual(selectedBuilderTarget, nextTarget)) {
                    setSelectedBuilderTarget(nextTarget);
                }
                setActiveLibraryItem(null);
                if (justPlacedSectionRef.current) {
                    setBuilderPaneMode('elements');
                    justPlacedSectionRef.current = false;
                } else {
                    setBuilderPaneMode(nextLocalId || nextSectionKey ? 'settings' : 'elements');
                }
                return;
            }

            if (payload.type === 'builder:selected-target') {
                if (builderPayloadIsStale) {
                    return;
                }

                latestBuilderStateCursorRef.current = nextBuilderStateCursor;
                const rawNextTarget = buildEditableTargetFromMessagePayload({
                    pageId: payload.pageId ?? activeBuilderPageIdentity.pageId,
                    pageSlug: payload.pageSlug ?? activeBuilderPageIdentity.pageSlug,
                    pageTitle: payload.pageTitle ?? activeBuilderPageIdentity.pageTitle,
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
                    editableFields: Array.isArray(payload.editableFields) ? payload.editableFields : undefined,
                    sectionId: typeof payload.sectionId === 'string' ? payload.sectionId : null,
                    instanceId: typeof payload.instanceId === 'string' ? payload.instanceId : null,
                    variants: payload.variants && typeof payload.variants === 'object'
                        ? payload.variants as BuilderEditableTarget['variants']
                        : null,
                    allowedUpdates: payload.allowedUpdates && typeof payload.allowedUpdates === 'object'
                        ? payload.allowedUpdates as unknown as BuilderEditableTarget['allowedUpdates']
                        : null,
                    responsiveContext: payload.responsiveContext && typeof payload.responsiveContext === 'object'
                        ? (payload.responsiveContext as unknown as NonNullable<BuilderEditableTarget['responsiveContext']>)
                        : null,
                    currentBreakpoint: payload.viewport === 'desktop' || payload.viewport === 'tablet' || payload.viewport === 'mobile'
                        ? payload.viewport
                        : previewViewport,
                    currentInteractionState: payload.interactionState === 'normal' || payload.interactionState === 'hover' || payload.interactionState === 'focus' || payload.interactionState === 'active'
                        ? payload.interactionState
                        : previewInteractionState,
                });
                const nextTarget = buildSectionScopedEditableTarget(editableTargetToMessagePayload(rawNextTarget)) ?? rawNextTarget;
                const nextLocalId = nextTarget?.sectionLocalId ?? null;
                const nextSectionKey = nextTarget?.sectionKey ?? null;

                if (!areBuilderEditableTargetsEqual(selectedBuilderTarget, nextTarget)) {
                    setSelectedBuilderTarget(nextTarget);
                }
                if (selectedBuilderSectionLocalId !== nextLocalId) {
                    setSelectedBuilderSectionLocalId(nextLocalId);
                }
                if (selectedPreviewSectionKey !== nextSectionKey) {
                    setSelectedPreviewSectionKey(nextSectionKey);
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

            if (payload.type === 'builder:draft-save-state') {
                const nextIsSaving = payload.isSaving;
                setIsSavingBuilderDraft(nextIsSaving);

                if (!nextIsSaving) {
                    if (payload.success === true) {
                        toast.success(t('Draft saved'));
                    } else if (typeof payload.message === 'string' && payload.message.trim() !== '') {
                        toast.error(payload.message);
                    }
                }
                return;
            }

            if (payload.type === 'builder:mutation-result') {
                if (payload.requestId === pendingBuilderChangeSetRequestIdRef.current) {
                    pendingBuilderChangeSetRequestIdRef.current = null;
                    if (payload.success !== true || payload.changed !== true) {
                        setPreviewRefreshTrigger(Date.now());
                        if (typeof payload.error === 'string' && payload.error.trim() !== '') {
                            console.warn('[Builder sync] change set apply failed', payload.error);
                        }
                    }
                    return;
                }

                const mutationResolution = resolvePendingBuilderStructureMutation(pendingBuilderStructureMutation, {
                    requestId: payload.requestId,
                    success: payload.success,
                    changed: payload.changed,
                    error: payload.error,
                });
                if (mutationResolution.status === 'ignore') {
                    return;
                }
                if (mutationResolution.status === 'clear-error') {
                    setPendingBuilderStructureMutation(null);
                    if (pendingBuilderStructureMutation?.selectionSnapshot) {
                        setSelectedBuilderSectionLocalId(pendingBuilderStructureMutation.selectionSnapshot.sectionLocalId);
                        setSelectedPreviewSectionKey(pendingBuilderStructureMutation.selectionSnapshot.sectionKey);
                        setSelectedBuilderTarget(pendingBuilderStructureMutation.selectionSnapshot.target);
                    }
                    toast.error(mutationResolution.errorMessage ?? t('Builder update failed'));
                }
                return;
            }

            if (payload.type === 'builder:library-snapshot' && Array.isArray(payload.items)) {
                const nextItems = payload.items.flatMap((entry) => {
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
                return;
            }

            if (payload.type === 'builder:structure-snapshot' && Array.isArray(payload.sections)) {
                if (builderPayloadIsStale) {
                    return;
                }

                const snapshotSignature = buildBuilderBridgeEventSignature(payload);
                if (lastBuilderSnapshotSignatureRef.current === snapshotSignature) {
                    return;
                }

                const nextItems: BuilderStructureItem[] = [];
                const nextCodeSections: BuilderCodeSection[] = [];
                const snapshotPage = normalizeBuilderBridgePageIdentity({
                    pageId: payload.pageId,
                    pageSlug: payload.pageSlug,
                    pageTitle: payload.pageTitle,
                });

                payload.sections.forEach((entry, index) => {
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
                latestBuilderStateCursorRef.current = nextBuilderStateCursor;
                lastBuilderSnapshotSignatureRef.current = snapshotSignature;
                setPendingBuilderStructureMutation(null);
                setBuilderStructureItems(nextItems);
                setSelectedBuilderSectionLocalId((current) => (
                    current && nextItems.some((item) => item.localId === current) ? current : null
                ));
                setSelectedPreviewSectionKey((current) => (
                    current && nextItems.some((item) => item.sectionKey === current) ? current : null
                ));
                setSelectedBuilderTarget((current) => {
                    if (!current?.sectionLocalId) {
                        return current;
                    }

                    const nextItem = nextItems.find((item) => item.localId === current.sectionLocalId) ?? null;
                    if (!nextItem) {
                        return null;
                    }

                    return {
                        ...current,
                        sectionKey: nextItem.sectionKey,
                        componentType: nextItem.sectionKey,
                        props: nextItem.props,
                    };
                });
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
                return;
            }

            if (payload.type === 'builder:preview-refresh') {
                setPreviewRefreshTrigger(Date.now());
                return;
            }

            if (payload.type === 'builder:ready') {
                if (builderPayloadIsStale) {
                    return;
                }

                const readySignature = buildBuilderBridgeEventSignature(payload);
                if (lastBuilderReadySignatureRef.current === readySignature) {
                    return;
                }
                latestBuilderStateCursorRef.current = nextBuilderStateCursor;
                lastBuilderReadySignatureRef.current = readySignature;
                setIsBuilderSidebarReady(true);
                requestAnimationFrame(() => {
                    syncEmbeddedBuilderControls();
                    flushBuilderCommandQueue();
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
        setIsBuilderSidebarReady,
        setIsBuilderStructureOpen,
        setIsSavingBuilderDraft,
        setPendingBuilderStructureMutation,
        setPreviewInteractionState,
        setPreviewRefreshTrigger,
        setPreviewViewport,
        setSelectedBuilderSectionLocalId,
        setSelectedBuilderTarget,
        setSelectedPreviewSectionKey,
        structureSnapshotPageRef,
        syncEmbeddedBuilderControls,
        t,
        viewMode,
        justPlacedSectionRef,
    ]);

    useEffect(() => {
        if (viewMode !== 'inspect') {
            return;
        }

        setIsBuilderSidebarReady(false);
        setPendingBuilderStructureMutation(null);
        lastBuilderReadySignatureRef.current = null;
        lastBuilderSnapshotSignatureRef.current = null;
        latestBuilderStateCursorRef.current = null;
        lastSelectionCommandSignatureRef.current = null;
    }, [
        activeBuilderPageIdentity.pageId,
        activeBuilderPageIdentity.pageSlug,
        lastBuilderReadySignatureRef,
        lastBuilderSnapshotSignatureRef,
        lastSelectionCommandSignatureRef,
        latestBuilderStateCursorRef,
        setIsBuilderSidebarReady,
        setPendingBuilderStructureMutation,
        viewMode,
    ]);

    useEffect(() => {
        if (viewMode !== 'inspect' || typeof window === 'undefined') {
            return;
        }
        const sendPing = () => {
            const frameWindow = builderSidebarFrameRef.current?.contentWindow;
            if (frameWindow) {
                postBuilderBridgeMessage(
                    frameWindow,
                    window.location.origin,
                    'webu-chat-builder',
                    { type: 'builder:ping' },
                    activeBuilderPageIdentity,
                );
            }
        };
        const t1 = window.setTimeout(sendPing, 200);
        const t2 = window.setTimeout(sendPing, 800);
        return () => {
            window.clearTimeout(t1);
            window.clearTimeout(t2);
        };
    }, [activeBuilderPageIdentity, builderSidebarFrameRef, viewMode]);

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
