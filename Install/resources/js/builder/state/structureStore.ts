import { useBuilderStore } from './builderStore';
import { useShallow } from 'zustand/shallow';

export function useStructureStore() {
    return useBuilderStore(useShallow((state) => ({
        builderDocument: state.builderDocument,
        activePageId: state.activePageId,
        dirty: state.dirty,
        lastSavedVersion: state.lastSavedVersion,
        insertNode: state.insertNode,
        deleteNode: state.deleteNode,
        moveNode: state.moveNode,
        duplicateNode: state.duplicateNode,
        setBuilderDocument: state.setBuilderDocument,
    })));
}
