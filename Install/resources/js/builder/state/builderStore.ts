import { create } from 'zustand';
import type { BuilderDocument } from '@/builder/types/builderDocument';
import type { BuilderNode } from '@/builder/types/builderNode';
import type { BuilderHistoryEntry } from '@/builder/history/historyActions';
import { dispatchBuilderMutation, type BuilderMutation } from '@/builder/mutations/dispatchBuilderMutation';
import { getActivePage, getPageRootNode } from '@/builder/utils/document';

export type BuilderPanelTab = 'layers' | 'library' | 'assets' | 'ai';
export type BuilderInspectorTab = 'content' | 'style' | 'advanced';
export type BuilderDevicePreset = 'desktop' | 'tablet' | 'mobile';
export type BuilderViewportMode = 'fit' | 'actual';
export type BuilderApplyStatus = 'idle' | 'running' | 'error' | 'success';

export interface BuilderConversationEntry {
    id: string;
    role: 'user' | 'assistant' | 'system';
    content: string;
}

export interface BuilderSuggestedMutationSet {
    id: string;
    title: string;
    summary: string;
    mutations: BuilderMutation[];
}

export interface BuilderStoreState {
    builderDocument: BuilderDocument;
    publishedDocument: BuilderDocument | null;
    activePageId: string;
    dirty: boolean;
    lastSavedVersion: number;

    selectedNodeId: string | null;
    hoveredNodeId: string | null;
    multiSelectIds: string[];
    focusedField: string | null;

    activeInspectorTab: BuilderInspectorTab;
    resolvedSchemaKey: string | null;
    validationErrors: Record<string, string>;

    zoom: number;
    devicePreset: BuilderDevicePreset;
    guidesVisible: boolean;
    viewportMode: BuilderViewportMode;

    undoStack: BuilderHistoryEntry[];
    redoStack: BuilderHistoryEntry[];
    lastMutationId: string | null;

    leftPanelTab: BuilderPanelTab;
    rightPanelTab: 'inspector';
    assetsOpen: boolean;
    aiPanelOpen: boolean;
    collapsedLayerNodeIds: string[];

    conversation: BuilderConversationEntry[];
    selectedNodeContext: string | null;
    suggestedMutations: BuilderSuggestedMutationSet[];
    applyStatus: BuilderApplyStatus;

    initialize: (document: BuilderDocument, publishedDocument?: BuilderDocument | null) => void;
    setBuilderDocument: (document: BuilderDocument, publishedDocument?: BuilderDocument | null) => void;
    setPublishedDocument: (document: BuilderDocument | null) => void;
    setActivePageId: (pageId: string) => void;
    selectNode: (nodeId: string | null) => void;
    hoverNode: (nodeId: string | null) => void;
    clearSelection: () => void;
    patchNodeProps: (nodeId: string, patch: Record<string, unknown>, groupKey?: string) => void;
    patchNodeStyles: (nodeId: string, patch: Record<string, unknown>, groupKey?: string) => void;
    insertNode: (node: BuilderNode, parentId: string, index?: number) => void;
    deleteNode: (nodeId: string) => void;
    moveNode: (nodeId: string, targetParentId: string, index?: number) => void;
    duplicateNode: (nodeId: string, targetParentId?: string, index?: number) => void;
    wrapNode: (nodeId: string) => void;
    unwrapNode: (nodeId: string) => void;
    applyMutations: (mutations: BuilderMutation[]) => void;
    setDevicePreset: (devicePreset: BuilderDevicePreset) => void;
    setZoom: (zoom: number) => void;
    setGuidesVisible: (visible: boolean) => void;
    setViewportMode: (mode: BuilderViewportMode) => void;
    undo: () => void;
    redo: () => void;
    markDirty: (dirty?: boolean) => void;
    markSaved: (document: BuilderDocument) => void;

    setFocusedField: (field: string | null) => void;
    setActiveInspectorTab: (tab: BuilderInspectorTab) => void;
    setResolvedSchemaKey: (key: string | null) => void;
    setLeftPanelTab: (tab: BuilderPanelTab) => void;
    setAssetsOpen: (open: boolean) => void;
    setAiPanelOpen: (open: boolean) => void;
    toggleLayerCollapse: (nodeId: string) => void;

    appendConversation: (entry: BuilderConversationEntry) => void;
    setConversation: (conversation: BuilderConversationEntry[]) => void;
    setSelectedNodeContext: (context: string | null) => void;
    setSuggestedMutations: (suggestions: BuilderSuggestedMutationSet[]) => void;
    setApplyStatus: (status: BuilderApplyStatus) => void;
}

function createEmptyDocument(): BuilderDocument {
    return {
        projectId: 'unknown',
        pages: {
            'page-empty': {
                id: 'page-empty',
                title: 'Untitled',
                slug: 'untitled',
                rootNodeId: 'page-empty-root',
                status: 'draft',
            },
        },
        nodes: {
            'page-empty-root': {
                id: 'page-empty-root',
                type: 'page',
                parentId: null,
                children: [],
                props: { title: 'Untitled', slug: 'untitled' },
                styles: {},
                bindings: {},
                meta: { label: 'Untitled' },
            },
        },
        rootPageId: 'page-empty',
        version: 1,
    };
}

const emptyDocument = createEmptyDocument();

function applyMutation(mutation: BuilderMutation, state: BuilderStoreState) {
    return dispatchBuilderMutation({
        builderDocument: state.builderDocument,
        activePageId: state.activePageId,
        selectedNodeId: state.selectedNodeId,
        hoveredNodeId: state.hoveredNodeId,
        devicePreset: state.devicePreset,
        undoStack: state.undoStack,
        redoStack: state.redoStack,
        dirty: state.dirty,
        lastSavedVersion: state.lastSavedVersion,
        validationErrors: state.validationErrors,
        lastMutationId: state.lastMutationId,
    }, mutation);
}

export const useBuilderStore = create<BuilderStoreState>((set) => ({
    builderDocument: emptyDocument,
    publishedDocument: null,
    activePageId: emptyDocument.rootPageId,
    dirty: false,
    lastSavedVersion: emptyDocument.version,

    selectedNodeId: getPageRootNode(emptyDocument, emptyDocument.rootPageId)?.id ?? null,
    hoveredNodeId: null,
    multiSelectIds: [],
    focusedField: null,

    activeInspectorTab: 'content',
    resolvedSchemaKey: null,
    validationErrors: {},

    zoom: 1,
    devicePreset: 'desktop',
    guidesVisible: true,
    viewportMode: 'fit',

    undoStack: [],
    redoStack: [],
    lastMutationId: null,

    leftPanelTab: 'layers',
    rightPanelTab: 'inspector',
    assetsOpen: false,
    aiPanelOpen: false,
    collapsedLayerNodeIds: [],

    conversation: [],
    selectedNodeContext: null,
    suggestedMutations: [],
    applyStatus: 'idle',

    initialize: (document, publishedDocument = null) => {
        const activePage = getActivePage(document, document.rootPageId);
        set({
            builderDocument: document,
            publishedDocument,
            activePageId: activePage?.id ?? document.rootPageId,
            lastSavedVersion: document.version,
            dirty: false,
            selectedNodeId: activePage ? document.pages[activePage.id]?.rootNodeId ?? null : null,
            hoveredNodeId: null,
            undoStack: [],
            redoStack: [],
            validationErrors: {},
            lastMutationId: null,
            collapsedLayerNodeIds: [],
        });
    },

    setBuilderDocument: (document, publishedDocument = null) => {
        const activePage = getActivePage(document, document.rootPageId);
        set({
            builderDocument: document,
            publishedDocument,
            activePageId: activePage?.id ?? document.rootPageId,
            lastSavedVersion: document.version,
            dirty: false,
            selectedNodeId: activePage ? document.pages[activePage.id]?.rootNodeId ?? null : null,
            hoveredNodeId: null,
            undoStack: [],
            redoStack: [],
            validationErrors: {},
            lastMutationId: null,
            collapsedLayerNodeIds: [],
        });
    },

    setPublishedDocument: (document) => set({ publishedDocument: document }),
    setActivePageId: (pageId) => {
        set((state) => {
            const page = state.builderDocument.pages[pageId];
            return {
                activePageId: page?.id ?? state.activePageId,
                selectedNodeId: page?.rootNodeId ?? state.selectedNodeId,
            };
        });
    },
    selectNode: (nodeId) => set((state) => applyMutation({ type: 'SELECT_NODE', payload: { nodeId } }, state)),
    hoverNode: (nodeId) => set((state) => applyMutation({ type: 'HOVER_NODE', payload: { nodeId } }, state)),
    clearSelection: () => set((state) => applyMutation({ type: 'SELECT_NODE', payload: { nodeId: null } }, state)),
    patchNodeProps: (nodeId, patch, groupKey) => set((state) => applyMutation({
        type: 'PATCH_NODE_PROPS',
        payload: { nodeId, patch },
        meta: { groupKey, label: 'Edit content' },
    }, state)),
    patchNodeStyles: (nodeId, patch, groupKey) => set((state) => applyMutation({
        type: 'PATCH_NODE_STYLES',
        payload: { nodeId, patch },
        meta: { groupKey, label: 'Edit styles' },
    }, state)),
    insertNode: (node, parentId, index) => set((state) => applyMutation({
        type: 'INSERT_NODE',
        payload: { node, parentId, index },
        meta: { label: 'Insert block' },
    }, state)),
    deleteNode: (nodeId) => set((state) => applyMutation({
        type: 'DELETE_NODE',
        payload: { nodeId },
        meta: { label: 'Delete block' },
    }, state)),
    moveNode: (nodeId, targetParentId, index) => set((state) => applyMutation({
        type: 'MOVE_NODE',
        payload: { nodeId, targetParentId, index },
        meta: { label: 'Move block' },
    }, state)),
    duplicateNode: (nodeId, targetParentId, index) => set((state) => applyMutation({
        type: 'DUPLICATE_NODE',
        payload: { nodeId, targetParentId, index },
        meta: { label: 'Duplicate block' },
    }, state)),
    wrapNode: (nodeId) => set((state) => applyMutation({
        type: 'WRAP_NODE',
        payload: { nodeId },
        meta: { label: 'Group block' },
    }, state)),
    unwrapNode: (nodeId) => set((state) => applyMutation({
        type: 'UNWRAP_NODE',
        payload: { nodeId },
        meta: { label: 'Ungroup block' },
    }, state)),
    applyMutations: (mutations) => set((state) => {
        let nextState: BuilderStoreState = state;
        for (const mutation of mutations) {
            nextState = {
                ...nextState,
                ...applyMutation(mutation, nextState),
            };
        }

        return nextState;
    }),
    setDevicePreset: (devicePreset) => set((state) => applyMutation({
        type: 'CHANGE_DEVICE_PRESET',
        payload: { devicePreset },
    }, state)),
    setZoom: (zoom) => set({ zoom }),
    setGuidesVisible: (guidesVisible) => set({ guidesVisible }),
    setViewportMode: (viewportMode) => set({ viewportMode }),
    undo: () => set((state) => applyMutation({ type: 'UNDO', payload: {} }, state)),
    redo: () => set((state) => applyMutation({ type: 'REDO', payload: {} }, state)),
    markDirty: (dirty = true) => set({ dirty }),
    markSaved: (document) => set({
        builderDocument: document,
        lastSavedVersion: document.version,
        dirty: false,
    }),

    setFocusedField: (focusedField) => set({ focusedField }),
    setActiveInspectorTab: (activeInspectorTab) => set({ activeInspectorTab }),
    setResolvedSchemaKey: (resolvedSchemaKey) => set({ resolvedSchemaKey }),
    setLeftPanelTab: (leftPanelTab) => set({ leftPanelTab }),
    setAssetsOpen: (assetsOpen) => set({ assetsOpen }),
    setAiPanelOpen: (aiPanelOpen) => set({ aiPanelOpen }),
    toggleLayerCollapse: (nodeId) => set((state) => ({
        collapsedLayerNodeIds: state.collapsedLayerNodeIds.includes(nodeId)
            ? state.collapsedLayerNodeIds.filter((currentId) => currentId !== nodeId)
            : [...state.collapsedLayerNodeIds, nodeId],
    })),

    appendConversation: (entry) => set((state) => ({ conversation: [...state.conversation, entry] })),
    setConversation: (conversation) => set({ conversation }),
    setSelectedNodeContext: (selectedNodeContext) => set({ selectedNodeContext }),
    setSuggestedMutations: (suggestedMutations) => set({ suggestedMutations }),
    setApplyStatus: (applyStatus) => set({ applyStatus }),
}));
