import { RefObject, useLayoutEffect, useState } from 'react';

export interface CanvasNodeRect {
    top: number;
    left: number;
    width: number;
    height: number;
}

function resolveNodeRect(viewport: HTMLElement, nodeId: string): CanvasNodeRect | null {
    const target = viewport.querySelector<HTMLElement>(`[data-builder-node-id="${CSS.escape(nodeId)}"]`);
    if (! target) {
        return null;
    }

    const viewportRect = viewport.getBoundingClientRect();
    const targetRect = target.getBoundingClientRect();

    return {
        top: targetRect.top - viewportRect.top + viewport.scrollTop,
        left: targetRect.left - viewportRect.left + viewport.scrollLeft,
        width: targetRect.width,
        height: targetRect.height,
    };
}

export function useCanvasNodeRect(viewportRef: RefObject<HTMLElement>, nodeId: string | null) {
    const [rect, setRect] = useState<CanvasNodeRect | null>(null);

    useLayoutEffect(() => {
        const viewport = viewportRef.current;
        if (! viewport || ! nodeId) {
            const resetFrameId = window.requestAnimationFrame(() => setRect(null));
            return () => window.cancelAnimationFrame(resetFrameId);
        }

        const update = () => {
            window.requestAnimationFrame(() => setRect(resolveNodeRect(viewport, nodeId)));
        };
        update();

        const resizeObserver = new ResizeObserver(update);
        resizeObserver.observe(viewport);
        const target = viewport.querySelector<HTMLElement>(`[data-builder-node-id="${CSS.escape(nodeId)}"]`);
        if (target) {
            resizeObserver.observe(target);
        }

        viewport.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        return () => {
            resizeObserver.disconnect();
            viewport.removeEventListener('scroll', update);
            window.removeEventListener('resize', update);
        };
    }, [nodeId, viewportRef]);

    return rect;
}
