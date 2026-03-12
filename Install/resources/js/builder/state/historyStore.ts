import { useBuilderStore } from './builderStore';
import { useShallow } from 'zustand/shallow';

export function useHistoryStore() {
    return useBuilderStore(useShallow((state) => ({
        undoStack: state.undoStack,
        redoStack: state.redoStack,
        lastMutationId: state.lastMutationId,
        undo: state.undo,
        redo: state.redo,
    })));
}
