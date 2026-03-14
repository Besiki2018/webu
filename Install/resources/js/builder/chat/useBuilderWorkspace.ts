import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    type Dispatch,
    type MutableRefObject,
    type SetStateAction,
} from 'react';
import { useShallow } from 'zustand/shallow';

import { useChatEmbeddedBuilderBridge } from '@/builder/cms/useChatEmbeddedBuilderBridge';
import {
    getWorkspaceBuilderPageIdentity,
    type WorkspaceBuilderCodePage,
    type WorkspaceBuilderStructureItem,
} from '@/builder/cms/workspaceBuilderSync';
import {
    editableTargetToMention,
    type BuilderSidebarMode,
} from '@/builder/editingState';
import { resetBuilderEditingStore, useBuilderEditingStore } from '@/builder/state/builderEditingStore';
import type { PreviewLayoutOverrides } from '@/lib/agentChangeSet';
import type { BuilderBridgeMessage } from '@/lib/builderBridge';
import {
    normalizeBuilderBridgePageIdentity,
    type BuilderBridgePageIdentity,
} from '@/builder/cms/embeddedBuilderBridgeContract';
import { readPersistedStructurePanelState, type ChatViewMode } from './chatPageUtils';
import type { PendingBuilderStructureMutation } from '@/builder/cms/chatBuilderStructureMutations';

export interface BuilderLibraryItem {
    key: string;
    label: string;
    category: string;
    category_label?: string;
}

export interface BuilderLibraryGroup {
    category: string;
    categoryLabel: string;
    items: BuilderLibraryItem[];
}

interface UseBuilderWorkspaceOptions {
    projectId: string;
    viewMode: ChatViewMode;
    setViewMode: Dispatch<SetStateAction<ChatViewMode>>;
    canOpenInspectMode?: boolean;
    onInspectBlocked?: () => void;
    seededBuilderLibraryItems: BuilderLibraryItem[];
    activeBuilderCodePage: WorkspaceBuilderCodePage | null;
    builderStructureItems: WorkspaceBuilderStructureItem[];
    setBuilderStructureItems: (items: WorkspaceBuilderStructureItem[]) => void;
    setBuilderCodePages: Dispatch<SetStateAction<WorkspaceBuilderCodePage[]>>;
    structureSnapshotPageRef: MutableRefObject<BuilderBridgePageIdentity>;
    effectivePreviewUrl: string | null;
    setPreviewRefreshTrigger: Dispatch<SetStateAction<number>>;
    t: (key: string) => string;
}

export function buildVisualBuilderSidebarUrl(projectId: string): string {
    return `/project/${projectId}/cms?tab=editor&embedded=sidebar`;
}

export function buildBuilderPreviewUrl(
    viewMode: 'preview' | 'inspect' | 'design',
    effectivePreviewUrl: string | null,
    effectivePreviewUrlWithOverrides: string | null,
): string | null {
    if (viewMode === 'inspect') {
        const previewUrl = effectivePreviewUrlWithOverrides ?? effectivePreviewUrl;
        if (!previewUrl) {
            return null;
        }

        return `${previewUrl}${previewUrl.includes('?') ? '&' : '?'}builder=1`;
    }

    return effectivePreviewUrl;
}

export function useBuilderWorkspace({
    projectId,
    viewMode,
    setViewMode,
    canOpenInspectMode = true,
    onInspectBlocked,
    seededBuilderLibraryItems,
    activeBuilderCodePage,
    builderStructureItems,
    setBuilderStructureItems,
    setBuilderCodePages,
    structureSnapshotPageRef,
    effectivePreviewUrl,
    setPreviewRefreshTrigger,
    t,
}: UseBuilderWorkspaceOptions) {
    const {
        previewViewport,
        setPreviewViewport,
        previewInteractionState,
        setPreviewInteractionState,
        selectedBuilderSectionLocalId,
        selectedBuilderTarget,
        selectBuilderTarget,
        clearBuilderSelection,
        isBuilderSidebarReady,
        markBuilderSidebarReady,
        isBuilderPreviewReady,
        markBuilderPreviewReady,
        builderPaneMode,
        setBuilderPaneMode,
    } = useBuilderEditingStore(useShallow((state) => ({
        previewViewport: state.currentBreakpoint,
        setPreviewViewport: state.setCurrentBreakpoint,
        previewInteractionState: state.currentInteractionState,
        setPreviewInteractionState: state.setCurrentInteractionState,
        selectedBuilderSectionLocalId: state.selectedSectionLocalId,
        selectedBuilderTarget: state.selectedBuilderTarget,
        selectBuilderTarget: state.selectTarget,
        clearBuilderSelection: state.clearSelection,
        isBuilderSidebarReady: state.isSidebarReady,
        markBuilderSidebarReady: state.markSidebarReady,
        isBuilderPreviewReady: state.isPreviewReady,
        markBuilderPreviewReady: state.markPreviewReady,
        builderPaneMode: state.builderMode,
        setBuilderPaneMode: state.setBuilderMode as (mode: BuilderSidebarMode) => void,
    })));

    const builderSidebarFrameRef = useRef<HTMLIFrameElement | null>(null);
    const builderSidebarCommandQueueRef = useRef<BuilderBridgeMessage[]>([]);
    const pendingBuilderChangeSetRequestIdRef = useRef<string | null>(null);
    const lastBuilderReadySignatureRef = useRef<string | null>(null);
    const lastBuilderSnapshotSignatureRef = useRef<string | null>(null);
    const latestBuilderStateCursorRef = useRef<{
        pageId: number | null;
        pageSlug: string | null;
        stateVersion: number | null;
        revisionVersion: number | null;
    } | null>(null);
    const preferPersistedStructureStateRef = useRef(true);
    const justPlacedSectionRef = useRef(false);

    const [isSidebarVisible, setIsSidebarVisible] = useState(true);
    const isVisualBuilderOpen = viewMode === 'inspect';
    const [isBuilderStructureOpen, setIsBuilderStructureOpen] = useState(() => (
        readPersistedStructurePanelState(projectId, false).open
    ));
    const [pendingBuilderStructureMutation, setPendingBuilderStructureMutation] = useState<PendingBuilderStructureMutation | null>(null);
    const [builderLibraryItems, setBuilderLibraryItems] = useState<BuilderLibraryItem[]>(() => seededBuilderLibraryItems);
    const [activeLibraryItem, setActiveLibraryItem] = useState<BuilderLibraryItem | null>(null);
    const [isSavingBuilderDraft, setIsSavingBuilderDraft] = useState(false);
    const [previewLayoutOverrides, setPreviewLayoutOverrides] = useState<PreviewLayoutOverrides | null>(null);
    const [structureSnapshotPageIdentity, setStructureSnapshotPageIdentity] = useState<BuilderBridgePageIdentity>({
        pageId: null,
        pageSlug: null,
        pageTitle: null,
    });

    const activeBuilderPageIdentity = useMemo(() => (
        activeBuilderCodePage
            ? getWorkspaceBuilderPageIdentity(activeBuilderCodePage)
            : structureSnapshotPageIdentity
    ), [activeBuilderCodePage, structureSnapshotPageIdentity]);
    const visibleBuilderStructureItems = pendingBuilderStructureMutation?.previewItems ?? builderStructureItems;
    const effectiveSelectedBuilderSectionLocalId = selectedBuilderTarget?.sectionLocalId ?? selectedBuilderSectionLocalId;
    const effectiveSelectedPreviewSectionKey = selectedBuilderTarget?.sectionKey ?? null;
    const selectedElementMention = useMemo(
        () => editableTargetToMention(selectedBuilderTarget),
        [selectedBuilderTarget],
    );

    const effectivePreviewUrlWithOverrides = useMemo(() => {
        const base = effectivePreviewUrl;
        if (!base || !previewLayoutOverrides) {
            return base;
        }

        const header = previewLayoutOverrides.header_variant?.trim();
        const footer = previewLayoutOverrides.footer_variant?.trim();
        if (!header && !footer) {
            return base;
        }

        const separator = base.includes('?') ? '&' : '?';
        const params = new URLSearchParams();
        if (header) {
            params.set('header_variant', header);
        }
        if (footer) {
            params.set('footer_variant', footer);
        }

        return `${base}${separator}${params.toString()}`;
    }, [effectivePreviewUrl, previewLayoutOverrides]);

    const visualBuilderSidebarUrl = useMemo(
        () => buildVisualBuilderSidebarUrl(projectId),
        [projectId],
    );

    const groupedBuilderLibraryItems = useMemo<BuilderLibraryGroup[]>(() => {
        const uniqueItems = new Map<string, BuilderLibraryItem>();

        builderLibraryItems.forEach((item) => {
            const normalizedKey = item.key.trim();
            if (normalizedKey === '' || uniqueItems.has(normalizedKey)) {
                return;
            }

            const category = item.category.trim() || 'general';
            uniqueItems.set(normalizedKey, {
                key: normalizedKey,
                label: item.label.trim() || normalizedKey,
                category,
                category_label: item.category_label?.trim(),
            });
        });

        const categoryOrder = ['general', 'ecommerce', 'business', 'content', 'booking', 'layout'];
        const grouped = new Map<string, BuilderLibraryItem[]>();

        Array.from(uniqueItems.values())
            .sort((left, right) => {
                const leftOrder = categoryOrder.indexOf(left.category);
                const rightOrder = categoryOrder.indexOf(right.category);
                if (leftOrder !== -1 || rightOrder !== -1) {
                    const a = leftOrder === -1 ? 99 : leftOrder;
                    const b = rightOrder === -1 ? 99 : rightOrder;
                    if (a !== b) {
                        return a - b;
                    }
                }

                const categoryCompare = left.category.localeCompare(right.category, undefined, { sensitivity: 'base' });
                if (categoryCompare !== 0) {
                    return categoryCompare;
                }

                return left.label.localeCompare(right.label, undefined, { sensitivity: 'base' });
            })
            .forEach((item) => {
                const bucket = grouped.get(item.category) ?? [];
                bucket.push(item);
                grouped.set(item.category, bucket);
            });

        return Array.from(grouped.entries()).map(([category, items]) => {
            const categoryKey = `builder.category.${category}`;
            const translated = t(categoryKey);
            return {
                category,
                categoryLabel: translated !== categoryKey ? translated : (items[0]?.category_label ?? category),
                items,
            };
        });
    }, [builderLibraryItems, t]);

    useEffect(() => {
        resetBuilderEditingStore();
        setStructureSnapshotPageIdentity(normalizeBuilderBridgePageIdentity(structureSnapshotPageRef.current));

        return () => {
            resetBuilderEditingStore();
        };
    }, [projectId, structureSnapshotPageRef]);

    useEffect(() => {
        if (seededBuilderLibraryItems.length === 0) {
            return;
        }

        setBuilderLibraryItems((current) => {
            if (current.length >= seededBuilderLibraryItems.length) {
                return current;
            }

            const merged = new Map<string, BuilderLibraryItem>();
            seededBuilderLibraryItems.forEach((item) => {
                const key = item.key.trim();
                if (key !== '') {
                    merged.set(key, item);
                }
            });
            current.forEach((item) => {
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
    }, [seededBuilderLibraryItems]);

    useEffect(() => {
        if (viewMode !== 'inspect') {
            markBuilderPreviewReady(false);
        }
    }, [markBuilderPreviewReady, markBuilderSidebarReady, viewMode]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        try {
            window.localStorage.setItem(
                `webu:chat:structure-panel:${projectId}`,
                JSON.stringify({ open: isBuilderStructureOpen }),
            );
        } catch {
            // Ignore storage failures.
        }
    }, [isBuilderStructureOpen, projectId]);

    const applyPreviewLayoutOverrides = useCallback((overrides: PreviewLayoutOverrides | null) => {
        if (!overrides) {
            return;
        }

        setPreviewLayoutOverrides((prev) => {
            const next = {
                header_variant: overrides.header_variant ?? prev?.header_variant,
                footer_variant: overrides.footer_variant ?? prev?.footer_variant,
            };

            return next.header_variant || next.footer_variant ? next : null;
        });
    }, []);

    useEffect(() => {
        if (viewMode !== 'inspect' || typeof window === 'undefined') {
            return;
        }

        const handler = (event: MessageEvent) => {
            const data = event.data;
            if (!data || typeof data !== 'object' || data.type !== 'webu-builder-preview-layout-override') {
                return;
            }

            applyPreviewLayoutOverrides({
                header_variant: typeof data.header_variant === 'string' ? data.header_variant.trim() : undefined,
                footer_variant: typeof data.footer_variant === 'string' ? data.footer_variant.trim() : undefined,
            });
            setPreviewRefreshTrigger(Date.now());
        };

        window.addEventListener('message', handler);
        return () => window.removeEventListener('message', handler);
    }, [applyPreviewLayoutOverrides, setPreviewRefreshTrigger, viewMode]);

    const {
        postBuilderCommand,
        syncBuilderChangeSet,
        setStructurePanelOpen,
        handleBuilderSidebarFrameLoad,
    } = useChatEmbeddedBuilderBridge({
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
        selectedBuilderSectionLocalId: effectiveSelectedBuilderSectionLocalId,
        selectedPreviewSectionKey: effectiveSelectedPreviewSectionKey,
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
    });

    const openVisualBuilder = useCallback(() => {
        if (!canOpenInspectMode) {
            setViewMode('preview');
            onInspectBlocked?.();
            return;
        }

        setIsSidebarVisible(true);
        markBuilderSidebarReady(false);
        markBuilderPreviewReady(false);
        setBuilderPaneMode('elements');
        setActiveLibraryItem(null);
        clearBuilderSelection();
        setViewMode('inspect');
        postBuilderCommand({
            type: 'builder:set-sidebar-mode',
            mode: 'elements',
        });
        postBuilderCommand({
            type: 'builder:clear-selected-section',
        });
    }, [
        clearBuilderSelection,
        canOpenInspectMode,
        markBuilderPreviewReady,
        markBuilderSidebarReady,
        onInspectBlocked,
        postBuilderCommand,
        setActiveLibraryItem,
        setBuilderPaneMode,
        setViewMode,
    ]);

    const handleVisualBuilderToggle = useCallback(() => {
        if (viewMode === 'inspect') {
            setViewMode('preview');
            setBuilderPaneMode('elements');
            setActiveLibraryItem(null);
            clearBuilderSelection();
            postBuilderCommand({
                type: 'builder:set-sidebar-mode',
                mode: 'elements',
            });
            postBuilderCommand({
                type: 'builder:clear-selected-section',
            });
            return;
        }

        openVisualBuilder();
    }, [clearBuilderSelection, openVisualBuilder, postBuilderCommand, setActiveLibraryItem, setBuilderPaneMode, setViewMode, viewMode]);

    const handleWorkspaceModeChange = useCallback((nextMode: ChatViewMode) => {
        if (nextMode === 'inspect' && viewMode === 'inspect') {
            setViewMode('preview');
            setBuilderPaneMode('elements');
            setActiveLibraryItem(null);
            clearBuilderSelection();
            postBuilderCommand({
                type: 'builder:set-sidebar-mode',
                mode: 'elements',
            });
            postBuilderCommand({
                type: 'builder:clear-selected-section',
            });
            return;
        }

        if (nextMode === 'inspect') {
            if (!canOpenInspectMode) {
                setViewMode('preview');
                onInspectBlocked?.();
                return;
            }

            openVisualBuilder();
            return;
        }

        setViewMode(nextMode);
    }, [canOpenInspectMode, clearBuilderSelection, onInspectBlocked, openVisualBuilder, postBuilderCommand, setActiveLibraryItem, setBuilderPaneMode, setViewMode, viewMode]);

    const handleSidebarToggle = useCallback(() => {
        if (viewMode === 'inspect') {
            return;
        }

        setIsSidebarVisible((prev) => !prev);
    }, [viewMode]);

    return {
        previewViewport,
        setPreviewViewport,
        previewInteractionState,
        setPreviewInteractionState,
        selectedBuilderSectionLocalId,
        selectedBuilderTarget,
        selectBuilderTarget,
        clearBuilderSelection,
        selectedPreviewSectionKey: effectiveSelectedPreviewSectionKey,
        isBuilderSidebarReady,
        markBuilderSidebarReady,
        isBuilderPreviewReady,
        markBuilderPreviewReady,
        builderPaneMode,
        setBuilderPaneMode,
        builderSidebarFrameRef,
        builderSidebarCommandQueueRef,
        pendingBuilderChangeSetRequestIdRef,
        lastBuilderReadySignatureRef,
        lastBuilderSnapshotSignatureRef,
        latestBuilderStateCursorRef,
        preferPersistedStructureStateRef,
        justPlacedSectionRef,
        isSidebarVisible,
        setIsSidebarVisible,
        isVisualBuilderOpen,
        isBuilderStructureOpen,
        setIsBuilderStructureOpen,
        pendingBuilderStructureMutation,
        setPendingBuilderStructureMutation,
        builderLibraryItems,
        setBuilderLibraryItems,
        groupedBuilderLibraryItems,
        activeLibraryItem,
        setActiveLibraryItem,
        isSavingBuilderDraft,
        setIsSavingBuilderDraft,
        selectedElementMention,
        activeBuilderPageIdentity,
        visibleBuilderStructureItems,
        effectiveSelectedBuilderSectionLocalId,
        effectiveSelectedPreviewSectionKey,
        previewLayoutOverrides,
        applyPreviewLayoutOverrides,
        effectivePreviewUrlWithOverrides,
        visualBuilderSidebarUrl,
        postBuilderCommand,
        syncBuilderChangeSet,
        setStructurePanelOpen,
        handleBuilderSidebarFrameLoad,
        openVisualBuilder,
        handleVisualBuilderToggle,
        handleWorkspaceModeChange,
        handleSidebarToggle,
    };
}
