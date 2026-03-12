import type { BuilderDocument } from '@/builder/types/builderDocument';
import { serializeHistoryDocument } from './historySerializer';

export interface BuilderHistoryEntry {
    id: string;
    label: string;
    groupKey?: string;
    beforeDocument: BuilderDocument;
    afterDocument: BuilderDocument;
    beforeSelection: string | null;
    afterSelection: string | null;
    timestamp: number;
}

interface HistoryState {
    undoStack: BuilderHistoryEntry[];
    redoStack: BuilderHistoryEntry[];
}

interface PushHistoryInput {
    undoStack: BuilderHistoryEntry[];
    redoStack: BuilderHistoryEntry[];
    label: string;
    groupKey?: string;
    beforeDocument: BuilderDocument;
    afterDocument: BuilderDocument;
    beforeSelection: string | null;
    afterSelection: string | null;
    mutationId: string;
}

const HISTORY_GROUP_WINDOW_MS = 800;

export function pushHistoryEntry(input: PushHistoryInput): HistoryState {
    const timestamp = Date.now();
    const entry: BuilderHistoryEntry = {
        id: input.mutationId,
        label: input.label,
        groupKey: input.groupKey,
        beforeDocument: serializeHistoryDocument(input.beforeDocument),
        afterDocument: serializeHistoryDocument(input.afterDocument),
        beforeSelection: input.beforeSelection,
        afterSelection: input.afterSelection,
        timestamp,
    };

    const previous = input.undoStack[input.undoStack.length - 1];
    if (
        previous &&
        previous.groupKey &&
        input.groupKey &&
        previous.groupKey === input.groupKey &&
        timestamp - previous.timestamp <= HISTORY_GROUP_WINDOW_MS
    ) {
        const mergedEntry: BuilderHistoryEntry = {
            ...previous,
            afterDocument: entry.afterDocument,
            afterSelection: entry.afterSelection,
            timestamp,
            id: entry.id,
        };

        return {
            undoStack: [...input.undoStack.slice(0, -1), mergedEntry],
            redoStack: [],
        };
    }

    return {
        undoStack: [...input.undoStack, entry],
        redoStack: [],
    };
}

export function applyUndo(input: HistoryState) {
    const entry = input.undoStack[input.undoStack.length - 1];
    if (! entry) {
        return null;
    }

    return {
        document: serializeHistoryDocument(entry.beforeDocument),
        selection: entry.beforeSelection,
        undoStack: input.undoStack.slice(0, -1),
        redoStack: [...input.redoStack, entry],
    };
}

export function applyRedo(input: HistoryState) {
    const entry = input.redoStack[input.redoStack.length - 1];
    if (! entry) {
        return null;
    }

    return {
        document: serializeHistoryDocument(entry.afterDocument),
        selection: entry.afterSelection,
        undoStack: [...input.undoStack, entry],
        redoStack: input.redoStack.slice(0, -1),
    };
}
