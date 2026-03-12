import { ReactNode, useCallback, useEffect } from 'react';
import { useDroppable } from '@dnd-kit/core';
import { GripVertical, Pencil, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import { getVisualDropId } from './types';
import { isContainerSection } from './treeUtils';
import type { BuilderSection } from './treeUtils';

export interface EditableNodeWrapperProps {
    node: BuilderSection;
    index: number;
    isSelected: boolean;
    isHovered: boolean;
    isDraggingFromLibrary: boolean;
    displayLabel: string;
    children: ReactNode;
    onClick: (localId: string) => void;
    onMouseEnter: (localId: string) => void;
    onMouseLeave: () => void;
    /** When this section is the current drop target, show insertion preview (handled by parent). */
    dropPosition?: 'before' | 'after' | 'inside' | null;
    /** Called when a drop zone gets isOver (with drop id) or loses it (null). */
    onDropTargetActive?: (dropId: string | null) => void;
    /** Phase 2: when selected, show edit icon; called when user clicks edit (e.g. focus sidebar). */
    onEdit?: (localId: string) => void;
    /** Phase 2: when selected, show delete icon; called when user clicks delete (remove section). */
    onDelete?: (localId: string) => void;
    /** When true, show overlay toolbar with drag handle, edit, delete when selected. Default true if onEdit or onDelete provided. */
    showOverlayChrome?: boolean;
}

/**
 * Wraps every builder node rendered in the canvas.
 * Handles: hover, click selection, and drop zones (before / after / inside if container).
 */
export function EditableNodeWrapper({
    node,
    index,
    isSelected,
    isHovered,
    isDraggingFromLibrary,
    displayLabel,
    children,
    onClick,
    onMouseEnter,
    onMouseLeave,
    dropPosition,
    onDropTargetActive,
    onEdit,
    onDelete,
    showOverlayChrome = !!(onEdit ?? onDelete),
}: EditableNodeWrapperProps) {
    const handleClick = useCallback(
        (e: React.MouseEvent) => {
            e.stopPropagation();
            onClick(node.localId);
        },
        [node.localId, onClick]
    );

    const handleEdit = useCallback(
        (e: React.MouseEvent) => {
            e.stopPropagation();
            onEdit?.(node.localId);
        },
        [node.localId, onEdit]
    );

    const handleDelete = useCallback(
        (e: React.MouseEvent) => {
            e.stopPropagation();
            onDelete?.(node.localId);
        },
        [node.localId, onDelete]
    );

    const canAcceptInside = isContainerSection(node);

    const beforeId = getVisualDropId('before', node.localId);
    const afterId = getVisualDropId('after', node.localId);
    const insideId = canAcceptInside ? getVisualDropId('inside', node.localId) : null;

    const showToolbar = isSelected && (showOverlayChrome || onEdit || onDelete);

    return (
        <div
            className="relative"
            data-builder-node-id={node.localId}
            onMouseEnter={() => onMouseEnter(node.localId)}
            onMouseLeave={onMouseLeave}
        >
            {/* Drop zone: before this section */}
            <DropZoneLine
                id={beforeId}
                isActive={dropPosition === 'before'}
                isDraggingFromLibrary={isDraggingFromLibrary}
                onDropTargetActive={onDropTargetActive}
            />

            <div
                role="button"
                tabIndex={0}
                onClick={handleClick}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        onClick(node.localId);
                    }
                }}
                className={cn(
                    'relative rounded-lg transition-all min-h-[32px] cursor-pointer select-none',
                    isSelected && 'ring-2 ring-primary ring-offset-2 ring-offset-background shadow-sm',
                    isHovered && !isSelected && 'ring-1 ring-primary/40 ring-offset-1 bg-primary/[0.02]',
                    isDraggingFromLibrary && 'opacity-90'
                )}
                data-selected={isSelected ? 'true' : undefined}
                data-hovered={isHovered ? 'true' : undefined}
            >
                {/* Phase 2: overlay toolbar when selected — border, drag handle, edit icon, delete icon */}
                {showToolbar ? (
                    <div
                        className="absolute -top-6 left-0 z-10 flex items-center rounded px-1.5 py-0.5 gap-1 shadow-sm bg-primary text-primary-foreground pointer-events-auto"
                        data-builder-chrome="true"
                    >
                        <span className="text-[10px] font-medium">{displayLabel}</span>
                        <span className="text-primary-foreground/70" aria-hidden>
                            <GripVertical className="h-3 w-3" />
                        </span>
                        {onEdit && (
                            <button
                                type="button"
                                onClick={handleEdit}
                                className="p-0.5 rounded hover:bg-primary-foreground/20"
                                title="Edit"
                                aria-label="Edit"
                            >
                                <Pencil className="h-3 w-3" />
                            </button>
                        )}
                        {onDelete && (
                            <button
                                type="button"
                                onClick={handleDelete}
                                className="p-0.5 rounded hover:bg-destructive/30"
                                title="Delete"
                                aria-label="Delete"
                            >
                                <Trash2 className="h-3 w-3" />
                            </button>
                        )}
                    </div>
                ) : (isSelected || isHovered) ? (
                    <div className="absolute -top-6 left-0 z-10 flex items-center gap-1.5 pointer-events-none">
                        <span
                            data-builder-chrome="true"
                            className={cn(
                                'rounded px-1.5 py-0.5 text-[10px] font-medium shadow-sm',
                                isSelected ? 'bg-primary text-primary-foreground' : 'bg-muted/90 text-muted-foreground'
                            )}
                        >
                            {displayLabel}
                        </span>
                    </div>
                ) : null}
                {/* Drop zone inside container (at top of content) */}
                {canAcceptInside && insideId ? (
                    <DropZoneInside
                        id={insideId}
                        isActive={dropPosition === 'inside'}
                        isDraggingFromLibrary={isDraggingFromLibrary}
                        onDropTargetActive={onDropTargetActive}
                    />
                ) : null}
                {children}
            </div>

            {/* Drop zone: after this section */}
            <DropZoneLine
                id={afterId}
                isActive={dropPosition === 'after'}
                isDraggingFromLibrary={isDraggingFromLibrary}
                position="bottom"
                onDropTargetActive={onDropTargetActive}
            />
        </div>
    );
}

interface DropZoneLineProps {
    id: string;
    isActive: boolean;
    isDraggingFromLibrary: boolean;
    position?: 'top' | 'bottom';
    onDropTargetActive?: (dropId: string | null) => void;
}

function DropZoneLine({ id, isActive, isDraggingFromLibrary, position = 'top', onDropTargetActive }: DropZoneLineProps) {
    const { setNodeRef, isOver } = useDroppable({ id });
    const active = isOver && isDraggingFromLibrary;
    useEffect(() => {
        if (onDropTargetActive) {
            onDropTargetActive(isOver ? id : null);
        }
    }, [isOver, id, onDropTargetActive]);

    return (
        <div
            ref={setNodeRef}
            className={cn(
                'h-1 -mb-px transition-all flex-shrink-0',
                position === 'bottom' && 'mt-0',
                active || isActive
                    ? 'min-h-[14px] rounded-sm border-2 border-primary bg-primary/15 shadow-sm'
                    : isDraggingFromLibrary
                      ? 'min-h-[14px] hover:min-h-[16px] hover:bg-primary/20 rounded hover:border-2 hover:border-primary/50 hover:border-dashed border-transparent'
                      : 'min-h-[2px]'
            )}
            data-drop-zone={id}
            data-builder-chrome="true"
        />
    );
}

interface DropZoneInsideProps {
    id: string;
    isActive: boolean;
    isDraggingFromLibrary: boolean;
    onDropTargetActive?: (dropId: string | null) => void;
}

function DropZoneInside({ id, isActive, isDraggingFromLibrary, onDropTargetActive }: DropZoneInsideProps) {
    const { setNodeRef, isOver } = useDroppable({ id });
    const active = isOver && isDraggingFromLibrary;
    useEffect(() => {
        if (onDropTargetActive) {
            onDropTargetActive(isOver ? id : null);
        }
    }, [isOver, id, onDropTargetActive]);

    return (
        <div
            ref={setNodeRef}
            className={cn(
                'min-h-[28px] rounded-md border-2 transition-all mx-2 my-1 flex items-center justify-center',
                active || isActive
                    ? 'border-primary bg-primary/15 text-primary border-solid shadow-sm'
                    : isDraggingFromLibrary
                      ? 'border-dashed border-muted-foreground/30 hover:border-primary/50 hover:bg-primary/5'
                      : 'border-transparent border-dashed'
            )}
            data-drop-zone={id}
            data-builder-chrome="true"
        >
            {(active || isActive) && (
                <span className="text-[10px] font-semibold uppercase tracking-wide">Drop here</span>
            )}
        </div>
    );
}
