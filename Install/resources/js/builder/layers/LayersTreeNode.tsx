import { ChevronDown, ChevronRight, EyeOff, Lock } from 'lucide-react';
import type { BuilderDocument } from '@/builder/types/builderDocument';
import type { BuilderNode } from '@/builder/types/builderNode';
import { cn } from '@/lib/utils';
import { resolveBuilderNodeLabel } from '@/builder/utils/document';

interface LayersTreeNodeProps {
    document: BuilderDocument;
    node: BuilderNode;
    depth?: number;
    selectedNodeId: string | null;
    collapsedIds: Set<string>;
    onToggleCollapse: (nodeId: string) => void;
    onSelect: (nodeId: string) => void;
}

export function LayersTreeNode({
    document,
    node,
    depth = 0,
    selectedNodeId,
    collapsedIds,
    onToggleCollapse,
    onSelect,
}: LayersTreeNodeProps) {
    const isCollapsed = collapsedIds.has(node.id);
    const hasChildren = node.children.length > 0;

    return (
        <div>
            <button
                type="button"
                onClick={() => onSelect(node.id)}
                className={cn(
                    'flex w-full items-center gap-2 rounded-xl px-2 py-2 text-left text-sm transition',
                    selectedNodeId === node.id ? 'bg-slate-900 text-white' : 'hover:bg-slate-100',
                )}
                style={{ paddingLeft: `${depth * 16 + 8}px` }}
            >
                <span
                    className="inline-flex h-5 w-5 items-center justify-center"
                    onClick={(event) => {
                        event.stopPropagation();
                        if (hasChildren) {
                            onToggleCollapse(node.id);
                        }
                    }}
                >
                    {hasChildren ? (
                        isCollapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />
                    ) : null}
                </span>
                <span className="min-w-0 flex-1 truncate">{resolveBuilderNodeLabel(node)}</span>
                {node.meta?.hidden ? <EyeOff className="h-4 w-4 opacity-70" /> : null}
                {node.meta?.locked ? <Lock className="h-4 w-4 opacity-70" /> : null}
            </button>

            {! isCollapsed && hasChildren ? (
                <div className="mt-1 space-y-1">
                    {node.children.map((childId) => {
                        const childNode = document.nodes[childId];
                        if (! childNode) {
                            return null;
                        }

                        return (
                            <LayersTreeNode
                                key={childId}
                                document={document}
                                node={childNode}
                                depth={depth + 1}
                                selectedNodeId={selectedNodeId}
                                collapsedIds={collapsedIds}
                                onToggleCollapse={onToggleCollapse}
                                onSelect={onSelect}
                            />
                        );
                    })}
                </div>
            ) : null}
        </div>
    );
}
