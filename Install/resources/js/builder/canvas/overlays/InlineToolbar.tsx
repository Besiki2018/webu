import type { RefObject } from 'react';
import { Copy, Trash2 } from 'lucide-react';
import { useShallow } from 'zustand/shallow';
import { Button } from '@/components/ui/button';
import { useBuilderStore } from '@/builder/state/builderStore';
import { useCanvasNodeRect } from '@/builder/canvas/hooks/useCanvasNodeRect';

interface InlineToolbarProps {
    viewportRef: RefObject<HTMLDivElement>;
}

export function InlineToolbar({ viewportRef }: InlineToolbarProps) {
    const { selectedNodeId, duplicateNode, deleteNode } = useBuilderStore(useShallow((state) => ({
        selectedNodeId: state.selectedNodeId,
        duplicateNode: state.duplicateNode,
        deleteNode: state.deleteNode,
    })));
    const rect = useCanvasNodeRect(viewportRef, selectedNodeId);

    if (! rect || ! selectedNodeId) {
        return null;
    }

    return (
        <div
            className="absolute flex items-center gap-1 rounded-full border border-slate-200 bg-white px-1 py-1 shadow-lg"
            style={{
                top: Math.max(8, rect.top - 48),
                left: rect.left,
            }}
        >
            <Button type="button" size="sm" variant="ghost" onClick={() => duplicateNode(selectedNodeId)}>
                <Copy className="h-4 w-4" />
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={() => deleteNode(selectedNodeId)}>
                <Trash2 className="h-4 w-4" />
            </Button>
        </div>
    );
}
