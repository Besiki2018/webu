import { useRef } from 'react';
import { useShallow } from 'zustand/shallow';
import { useBuilderStore } from '@/builder/state/builderStore';
import { CanvasViewport } from './CanvasViewport';
import { CanvasRenderer } from './CanvasRenderer';
import { DropIndicators } from './overlays/DropIndicators';
import { HoverOverlay } from './overlays/HoverOverlay';
import { InlineToolbar } from './overlays/InlineToolbar';
import { SelectionOverlay } from './overlays/SelectionOverlay';

export function CanvasWorkspace() {
    const viewportRef = useRef<HTMLDivElement>(null);
    const { builderDocument, activePageId, devicePreset, selectNode, hoverNode } = useBuilderStore(useShallow((state) => ({
        builderDocument: state.builderDocument,
        activePageId: state.activePageId,
        devicePreset: state.devicePreset,
        selectNode: state.selectNode,
        hoverNode: state.hoverNode,
    })));

    const activePage = builderDocument.pages[activePageId] ?? builderDocument.pages[builderDocument.rootPageId];
    const rootNodeId = activePage?.rootNodeId ?? null;

    return (
        <div className="relative flex h-full min-h-0 flex-col overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.08),_transparent_48%),linear-gradient(180deg,_#f8fafc,_#e2e8f0)]">
            <CanvasViewport
                ref={viewportRef}
                devicePreset={devicePreset}
            >
                <div
                    className="relative grid gap-8"
                    onMouseMove={(event) => {
                        const target = event.target as HTMLElement | null;
                        const nodeId = target?.closest<HTMLElement>('[data-builder-node-id]')?.dataset.builderNodeId ?? null;
                        hoverNode(nodeId);
                    }}
                    onMouseLeave={() => hoverNode(null)}
                    onClick={(event) => {
                        const target = event.target as HTMLElement | null;
                        const nodeId = target?.closest<HTMLElement>('[data-builder-node-id]')?.dataset.builderNodeId ?? rootNodeId;
                        selectNode(nodeId);
                    }}
                >
                    <CanvasRenderer />
                </div>
            </CanvasViewport>

            <div className="pointer-events-none absolute inset-0">
                <SelectionOverlay viewportRef={viewportRef} />
                <HoverOverlay viewportRef={viewportRef} />
                <DropIndicators />
                <div className="pointer-events-auto">
                    <InlineToolbar viewportRef={viewportRef} />
                </div>
            </div>
        </div>
    );
}
