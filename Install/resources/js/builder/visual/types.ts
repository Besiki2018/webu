/**
 * Visual builder interaction and tree types.
 * Keeps builder state and drop target logic separate from UI.
 */

export type DropPosition = 'before' | 'after' | 'inside';

export interface DropTarget {
    /** Section to relate to (null = root / page level). */
    sectionLocalId: string | null;
    /** Index in parent (for before/after). */
    sectionIndex: number;
    position: DropPosition;
}

export interface BuilderInteractionState {
    selectedElementId: string | null;
    hoveredElementId: string | null;
    /** When dragging from library: the section key being dragged. */
    draggingComponentType: string | null;
    currentDropTarget: DropTarget | null;
}

/** Droppable id for the structure panel / layers list drop zone. */
export const CMS_BUILDER_CANVAS_DROP_ID = 'cms-builder-canvas-drop-zone';

export const VISUAL_DROP_PREFIX = 'cms-builder-visual-drop:';

export function getVisualDropId(position: DropPosition, sectionLocalId: string | null): string {
    if (sectionLocalId === null) {
        return `${VISUAL_DROP_PREFIX}${position}:root`;
    }
    return `${VISUAL_DROP_PREFIX}${position}:${sectionLocalId}`;
}

export function parseVisualDropId(id: string): { position: DropPosition; sectionLocalId: string | null } | null {
    if (!id.startsWith(VISUAL_DROP_PREFIX)) {
        return null;
    }
    const rest = id.slice(VISUAL_DROP_PREFIX.length);
    const colon = rest.indexOf(':');
    if (colon === -1) return null;
    const position = rest.slice(0, colon) as DropPosition;
    const sectionLocalId = rest.slice(colon + 1);
    if (position !== 'before' && position !== 'after' && position !== 'inside') {
        return null;
    }
    return {
        position,
        sectionLocalId: sectionLocalId === 'root' ? null : sectionLocalId,
    };
}
