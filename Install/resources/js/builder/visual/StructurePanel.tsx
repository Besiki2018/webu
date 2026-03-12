/**
 * Floating structure panel for the visual builder (Task 6 extraction).
 * Renders a draggable panel with header (Structure, badge, paste, collapse) and scroll area.
 * Position/drag logic is self-contained; parent owns position state and provides scrollRef for virtualizer.
 */

import type { PointerEvent as ReactPointerEvent } from 'react';
import { useCallback, useRef } from 'react';
import { ArrowUp, GripVertical, ClipboardPaste, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

export interface StructurePanelProps {
    /** When true, render nothing (toolbar "Open Structure" stays in parent). */
    collapsed: boolean;
    onCollapse: () => void;
    position: { x: number; y: number };
    onPositionChange: (pos: { x: number; y: number }) => void;
    viewportRef: React.RefObject<HTMLElement | null>;
    /** Ref for the scroll container; parent uses it for useVirtualizer. */
    scrollRef: React.RefObject<HTMLDivElement | null>;
    sectionCount: number;
    onPaste: () => void;
    /** Optional: run layout refiner (spacing, container) and apply to sections */
    onOptimizeLayout?: () => void;
    t: (key: string, params?: Record<string, string>) => string;
    children: React.ReactNode;
}

const PANEL_WIDTH = 350;
const PANEL_HEIGHT = 460;
const MIN_OFFSET = 8;

export function StructurePanel({
    collapsed,
    onCollapse,
    position,
    onPositionChange,
    viewportRef,
    scrollRef,
    sectionCount,
    onPaste,
    onOptimizeLayout,
    t,
    children,
}: StructurePanelProps) {
    const dragRef = useRef({
        pointerId: null as number | null,
        startX: 0,
        startY: 0,
        originX: 0,
        originY: 0,
        dragging: false,
    });

    const clamp = useCallback(
        (x: number, y: number) => {
            const host = viewportRef.current;
            if (!host) {
                return { x: Math.max(MIN_OFFSET, x), y: Math.max(MIN_OFFSET, y) };
            }
            const rect = host.getBoundingClientRect();
            const maxX = Math.max(MIN_OFFSET, rect.width - PANEL_WIDTH - MIN_OFFSET);
            const maxY = Math.max(MIN_OFFSET, rect.height - PANEL_HEIGHT - MIN_OFFSET);
            return {
                x: Math.min(Math.max(MIN_OFFSET, x), maxX),
                y: Math.min(Math.max(MIN_OFFSET, y), maxY),
            };
        },
        [viewportRef]
    );

    const onDragStart = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            const target = event.target as HTMLElement | null;
            if (target?.closest('[data-structure-panel-control="true"]')) return;
            const next = dragRef.current;
            next.pointerId = event.pointerId;
            next.startX = event.clientX;
            next.startY = event.clientY;
            next.originX = position.x;
            next.originY = position.y;
            next.dragging = true;
            event.currentTarget.setPointerCapture(event.pointerId);
        },
        [position.x, position.y]
    );

    const onDragMove = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            const current = dragRef.current;
            if (!current.dragging || current.pointerId !== event.pointerId) return;
            const dx = event.clientX - current.startX;
            const dy = event.clientY - current.startY;
            onPositionChange(clamp(current.originX + dx, current.originY + dy));
        },
        [clamp, onPositionChange]
    );

    const onDragEnd = useCallback((event: ReactPointerEvent<HTMLDivElement>) => {
        const current = dragRef.current;
        if (!current.dragging || current.pointerId !== event.pointerId) return;
        current.dragging = false;
        current.pointerId = null;
        if (event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.releasePointerCapture(event.pointerId);
        }
    }, []);

    if (collapsed) {
        return null;
    }

    return (
        <div
            className="absolute z-20"
            style={{ left: `${position.x}px`, top: `${position.y}px` }}
        >
            <div className="rounded-lg border bg-card shadow-xl overflow-hidden w-[350px]">
                <div
                    className="h-10 px-2 border-b bg-muted/50 flex items-center justify-between gap-2 select-none cursor-move"
                    onPointerDown={onDragStart}
                    onPointerMove={onDragMove}
                    onPointerUp={onDragEnd}
                    onPointerCancel={onDragEnd}
                >
                    <div className="flex items-center gap-2 min-w-0">
                        <GripVertical className="h-4 w-4 text-muted-foreground" />
                        <p className="text-xs font-semibold truncate">{t('Structure')}</p>
                        <Badge variant="outline" className="text-[10px]">
                            {sectionCount}
                        </Badge>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="h-7 w-7"
                            onClick={() => void onPaste()}
                            aria-label={t('Paste section from clipboard')}
                            title={t('Paste section JSON')}
                        >
                            <ClipboardPaste className="h-3.5 w-3.5" />
                        </Button>
                        {onOptimizeLayout ? (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                data-structure-panel-control="true"
                                className="h-7 w-7"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onOptimizeLayout();
                                }}
                                aria-label={t('Optimize layout')}
                                title={t('Optimize layout (spacing, container)')}
                            >
                                <Sparkles className="h-3.5 w-3.5" />
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            data-structure-panel-control="true"
                            className="h-7 w-7"
                            onClick={(e) => {
                                e.stopPropagation();
                                onCollapse();
                            }}
                            aria-label={t('Collapse structure panel')}
                        >
                            <ArrowUp className="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
                <div ref={scrollRef as React.RefObject<HTMLDivElement>} className="p-2 max-h-[56vh] overflow-y-auto">
                    {children}
                </div>
            </div>
        </div>
    );
}
