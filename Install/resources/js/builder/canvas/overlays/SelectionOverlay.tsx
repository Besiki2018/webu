import type { RefObject } from 'react';
import { useBuilderStore } from '@/builder/state/builderStore';
import { useCanvasNodeRect } from '@/builder/canvas/hooks/useCanvasNodeRect';

interface SelectionOverlayProps {
    viewportRef: RefObject<HTMLDivElement>;
}

export function SelectionOverlay({ viewportRef }: SelectionOverlayProps) {
    const selectedNodeId = useBuilderStore((state) => state.selectedNodeId);
    const rect = useCanvasNodeRect(viewportRef, selectedNodeId);

    if (! rect || ! selectedNodeId) {
        return null;
    }

    return (
        <div
            className="pointer-events-none absolute rounded-[26px] border-2 border-sky-500 shadow-[0_0_0_1px_rgba(14,165,233,0.15)]"
            style={{
                top: rect.top - 4,
                left: rect.left - 4,
                width: rect.width + 8,
                height: rect.height + 8,
            }}
        />
    );
}
