import { useMemo } from 'react';
import type { BuilderDocument } from '@/builder/types/builderDocument';
import { LayersTreeNode } from './LayersTreeNode';

interface LayersTreeProps {
    document: BuilderDocument;
    rootNodeId: string;
    selectedNodeId: string | null;
    collapsedNodeIds: string[];
    onToggleCollapse: (nodeId: string) => void;
    onSelect: (nodeId: string) => void;
}

export function LayersTree({
    document,
    rootNodeId,
    selectedNodeId,
    collapsedNodeIds,
    onToggleCollapse,
    onSelect,
}: LayersTreeProps) {
    const collapsedIds = useMemo(() => new Set(collapsedNodeIds), [collapsedNodeIds]);
    const rootNode = document.nodes[rootNodeId];

    if (! rootNode) {
        return null;
    }

    return (
        <div className="space-y-1">
            <LayersTreeNode
                document={document}
                node={rootNode}
                selectedNodeId={selectedNodeId}
                collapsedIds={collapsedIds}
                onToggleCollapse={onToggleCollapse}
                onSelect={onSelect}
            />
        </div>
    );
}
