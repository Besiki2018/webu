import { useBuilderStore } from '@/builder/state/builderStore';
import { useShallow } from 'zustand/shallow';
import { LayersTree } from './LayersTree';

export function LayersPanel() {
    const {
        builderDocument,
        activePageId,
        selectedNodeId,
        collapsedLayerNodeIds,
        selectNode,
        toggleLayerCollapse,
    } = useBuilderStore(useShallow((state) => ({
        builderDocument: state.builderDocument,
        activePageId: state.activePageId,
        selectedNodeId: state.selectedNodeId,
        collapsedLayerNodeIds: state.collapsedLayerNodeIds,
        selectNode: state.selectNode,
        toggleLayerCollapse: state.toggleLayerCollapse,
    })));

    const activePage = builderDocument.pages[activePageId] ?? builderDocument.pages[builderDocument.rootPageId];

    if (! activePage) {
        return null;
    }

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-slate-200 px-4 py-3">
                <div className="text-sm font-semibold text-slate-900">Layers</div>
                <div className="text-xs text-slate-500">Structural tree for the active page.</div>
            </div>
            <div className="flex-1 overflow-y-auto p-3">
                <LayersTree
                    document={builderDocument}
                    rootNodeId={activePage.rootNodeId}
                    selectedNodeId={selectedNodeId}
                    collapsedNodeIds={collapsedLayerNodeIds}
                    onToggleCollapse={toggleLayerCollapse}
                    onSelect={selectNode}
                />
            </div>
        </div>
    );
}
