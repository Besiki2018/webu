import type { BuilderHistoryEntry } from '@/builder/history/historyActions';
import { applyRedo, applyUndo, pushHistoryEntry } from '@/builder/history/historyActions';
import type { BuilderDocument } from '@/builder/types/builderDocument';
import type { BuilderNode } from '@/builder/types/builderNode';
import { applyBuilderMutation } from './mutationHandlers';
import { normalizeBuilderMutation } from './normalizers';
import { validateBuilderMutation } from './validators';

export interface BuilderMutationMeta {
    groupKey?: string;
    label?: string;
}

interface BuilderMutationBase<TType extends string, TPayload> {
    type: TType;
    payload: TPayload;
    meta?: BuilderMutationMeta;
}

export type PatchNodePropsMutation = BuilderMutationBase<'PATCH_NODE_PROPS', {
    nodeId: string;
    patch: Record<string, unknown>;
}>;

export type PatchNodeStylesMutation = BuilderMutationBase<'PATCH_NODE_STYLES', {
    nodeId: string;
    patch: Record<string, unknown>;
}>;

export type InsertNodeMutation = BuilderMutationBase<'INSERT_NODE', {
    parentId: string;
    node: BuilderNode;
    index?: number;
}>;

export type DeleteNodeMutation = BuilderMutationBase<'DELETE_NODE', {
    nodeId: string;
}>;

export type MoveNodeMutation = BuilderMutationBase<'MOVE_NODE', {
    nodeId: string;
    targetParentId: string;
    index?: number;
}>;

export type DuplicateNodeMutation = BuilderMutationBase<'DUPLICATE_NODE', {
    nodeId: string;
    targetParentId?: string;
    index?: number;
}>;

export type WrapNodeMutation = BuilderMutationBase<'WRAP_NODE', {
    nodeId: string;
    wrapperNodeId?: string;
}>;

export type UnwrapNodeMutation = BuilderMutationBase<'UNWRAP_NODE', {
    nodeId: string;
}>;

export type SelectNodeMutation = BuilderMutationBase<'SELECT_NODE', {
    nodeId: string | null;
}>;

export type HoverNodeMutation = BuilderMutationBase<'HOVER_NODE', {
    nodeId: string | null;
}>;

export type ChangeDevicePresetMutation = BuilderMutationBase<'CHANGE_DEVICE_PRESET', {
    devicePreset: 'desktop' | 'tablet' | 'mobile';
}>;

export type UndoMutation = BuilderMutationBase<'UNDO', Record<string, never>>;
export type RedoMutation = BuilderMutationBase<'REDO', Record<string, never>>;

export type BuilderMutation =
    | PatchNodePropsMutation
    | PatchNodeStylesMutation
    | InsertNodeMutation
    | DeleteNodeMutation
    | MoveNodeMutation
    | DuplicateNodeMutation
    | WrapNodeMutation
    | UnwrapNodeMutation
    | SelectNodeMutation
    | HoverNodeMutation
    | ChangeDevicePresetMutation
    | UndoMutation
    | RedoMutation;

type HistoryTrackedMutation = Exclude<BuilderMutation, SelectNodeMutation | HoverNodeMutation | ChangeDevicePresetMutation | UndoMutation | RedoMutation>;

export interface DispatchBuilderMutationContext {
    builderDocument: BuilderDocument;
    activePageId: string;
    selectedNodeId: string | null;
    hoveredNodeId: string | null;
    devicePreset: 'desktop' | 'tablet' | 'mobile';
    undoStack: BuilderHistoryEntry[];
    redoStack: BuilderHistoryEntry[];
    dirty: boolean;
    validationErrors: Record<string, string>;
    lastSavedVersion?: number;
    lastMutationId?: string | null;
}

export interface DispatchBuilderMutationResult {
    builderDocument: BuilderDocument;
    selectedNodeId: string | null;
    hoveredNodeId: string | null;
    devicePreset: 'desktop' | 'tablet' | 'mobile';
    undoStack: BuilderHistoryEntry[];
    redoStack: BuilderHistoryEntry[];
    dirty: boolean;
    validationErrors: Record<string, string>;
    lastMutationId: string | null;
}

const HISTORY_LABELS: Record<HistoryTrackedMutation['type'], string> = {
    PATCH_NODE_PROPS: 'Edit content',
    PATCH_NODE_STYLES: 'Edit styles',
    INSERT_NODE: 'Insert block',
    DELETE_NODE: 'Delete block',
    MOVE_NODE: 'Move block',
    DUPLICATE_NODE: 'Duplicate block',
    WRAP_NODE: 'Group block',
    UNWRAP_NODE: 'Ungroup block',
};

function createMutationId(type: BuilderMutation['type']): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return `${type.toLowerCase()}-${crypto.randomUUID()}`;
    }

    return `${type.toLowerCase()}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

function buildResult(
    context: DispatchBuilderMutationContext,
    mutationId: string,
    overrides: Partial<DispatchBuilderMutationResult> = {},
): DispatchBuilderMutationResult {
    const result: DispatchBuilderMutationResult = {
        builderDocument: context.builderDocument,
        selectedNodeId: context.selectedNodeId,
        hoveredNodeId: context.hoveredNodeId,
        devicePreset: context.devicePreset,
        undoStack: context.undoStack,
        redoStack: context.redoStack,
        dirty: context.dirty,
        validationErrors: context.validationErrors,
        ...overrides,
        lastMutationId: overrides.lastMutationId ?? mutationId,
    };

    return result;
}

function bumpDocumentVersion(document: BuilderDocument, previousVersion: number): BuilderDocument {
    return {
        ...document,
        version: Math.max(document.version, previousVersion) + 1,
        updatedAt: new Date().toISOString(),
    };
}

function isHistoryTrackedMutation(mutation: BuilderMutation): mutation is HistoryTrackedMutation {
    return mutation.type !== 'SELECT_NODE'
        && mutation.type !== 'HOVER_NODE'
        && mutation.type !== 'CHANGE_DEVICE_PRESET'
        && mutation.type !== 'UNDO'
        && mutation.type !== 'REDO';
}

export function dispatchBuilderMutation(
    context: DispatchBuilderMutationContext,
    incomingMutation: BuilderMutation,
): DispatchBuilderMutationResult {
    const mutationId = createMutationId(incomingMutation.type);
    const lastSavedVersion = context.lastSavedVersion ?? context.builderDocument.version;

    switch (incomingMutation.type) {
        case 'SELECT_NODE':
            return buildResult(context, mutationId, incomingMutation.payload.nodeId === context.selectedNodeId
                ? {}
                : {
                    selectedNodeId: incomingMutation.payload.nodeId,
                    validationErrors: {},
                });
        case 'HOVER_NODE':
            return buildResult(context, mutationId, incomingMutation.payload.nodeId === context.hoveredNodeId
                ? {}
                : {
                    hoveredNodeId: incomingMutation.payload.nodeId,
                    validationErrors: {},
                });
        case 'CHANGE_DEVICE_PRESET':
            return buildResult(context, mutationId, incomingMutation.payload.devicePreset === context.devicePreset
                ? {}
                : {
                    devicePreset: incomingMutation.payload.devicePreset,
                    validationErrors: {},
                });
        case 'UNDO': {
            const restored = applyUndo({
                undoStack: context.undoStack,
                redoStack: context.redoStack,
            });

            if (! restored) {
                return buildResult(context, mutationId);
            }

            return buildResult(context, mutationId, {
                builderDocument: restored.document,
                selectedNodeId: restored.selection,
                hoveredNodeId: null,
                undoStack: restored.undoStack,
                redoStack: restored.redoStack,
                dirty: restored.document.version !== lastSavedVersion,
                validationErrors: {},
            });
        }
        case 'REDO': {
            const restored = applyRedo({
                undoStack: context.undoStack,
                redoStack: context.redoStack,
            });

            if (! restored) {
                return buildResult(context, mutationId);
            }

            return buildResult(context, mutationId, {
                builderDocument: restored.document,
                selectedNodeId: restored.selection,
                hoveredNodeId: null,
                undoStack: restored.undoStack,
                redoStack: restored.redoStack,
                dirty: restored.document.version !== lastSavedVersion,
                validationErrors: {},
            });
        }
        default:
            break;
    }

    const mutation = normalizeBuilderMutation(incomingMutation);
    const errors = validateBuilderMutation(context.builderDocument, mutation);
    if (errors.length > 0) {
        return buildResult(context, mutationId, {
            validationErrors: {
                mutation: errors.join('. '),
            },
        });
    }

    const applied = applyBuilderMutation({
        document: context.builderDocument,
        activePageId: context.activePageId,
        selectedNodeId: context.selectedNodeId,
        hoveredNodeId: context.hoveredNodeId,
    }, mutation);

    if (! isHistoryTrackedMutation(mutation)) {
        return buildResult(context, mutationId, {
            selectedNodeId: applied.selectedNodeId,
            hoveredNodeId: applied.hoveredNodeId,
            validationErrors: {},
        });
    }

    if (applied.document === context.builderDocument) {
        return buildResult(context, mutationId, {
            selectedNodeId: applied.selectedNodeId,
            hoveredNodeId: applied.hoveredNodeId,
            validationErrors: {},
        });
    }

    const nextDocument = bumpDocumentVersion(applied.document, context.builderDocument.version);
    const historyState = pushHistoryEntry({
        undoStack: context.undoStack,
        redoStack: context.redoStack,
        label: mutation.meta?.label ?? HISTORY_LABELS[mutation.type],
        groupKey: mutation.meta?.groupKey,
        beforeDocument: context.builderDocument,
        afterDocument: nextDocument,
        beforeSelection: context.selectedNodeId,
        afterSelection: applied.selectedNodeId,
        mutationId,
    });

    return buildResult(context, mutationId, {
        builderDocument: nextDocument,
        selectedNodeId: applied.selectedNodeId,
        hoveredNodeId: applied.hoveredNodeId,
        undoStack: historyState.undoStack,
        redoStack: historyState.redoStack,
        dirty: nextDocument.version !== lastSavedVersion,
        validationErrors: {},
    });
}
