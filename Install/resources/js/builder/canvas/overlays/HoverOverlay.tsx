import type { RefObject } from 'react';
import { useBuilderStore } from '@/builder/state/builderStore';
import { useCanvasNodeRect } from '@/builder/canvas/hooks/useCanvasNodeRect';

interface HoverOverlayProps {
    viewportRef: RefObject<HTMLDivElement>;
}

export function HoverOverlay({ viewportRef }: HoverOverlayProps) {
    const hoveredNodeId = useBuilderStore((state) => state.hoveredNodeId);
    const selectedNodeId = useBuilderStore((state) => state.selectedNodeId);
    const rect = useCanvasNodeRect(viewportRef, hoveredNodeId && hoveredNodeId !== selectedNodeId ? hoveredNodeId : null);

    if (! rect || ! hoveredNodeId || hoveredNodeId === selectedNodeId) {
        return null;
    }

    return (
        <div
            className="pointer-events-none absolute rounded-[24px] border border-amber-500"
            style={{
                top: rect.top - 2,
                left: rect.left - 2,
                width: rect.width + 4,
                height: rect.height + 4,
            }}
        />
    );
}
