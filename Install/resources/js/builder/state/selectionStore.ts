import { useBuilderStore } from './builderStore';
import { useShallow } from 'zustand/shallow';

export function useSelectionStore() {
    return useBuilderStore(useShallow((state) => ({
        selectedNodeId: state.selectedNodeId,
        hoveredNodeId: state.hoveredNodeId,
        multiSelectIds: state.multiSelectIds,
        focusedField: state.focusedField,
        selectNode: state.selectNode,
        hoverNode: state.hoverNode,
        clearSelection: state.clearSelection,
        setFocusedField: state.setFocusedField,
    })));
}
