import { useDroppable } from '@dnd-kit/core';
import { cn } from '@/lib/utils';
import { getVisualDropId } from './types';

interface RootDropZoneProps {
    isActive: boolean;
    isDraggingFromLibrary: boolean;
    isEmpty: boolean;
    t: (key: string) => string;
    /** When canvas is empty, clicking this zone can deselect (e.g. switch sidebar to library). */
    onEmptyAreaClick?: () => void;
}

/**
 * Root-level drop zone: at start (before first) or when canvas is empty.
 * Uses same id as "after:root" for empty canvas (insert at 0).
 */
export function RootDropZone({ isActive, isDraggingFromLibrary, isEmpty, t, onEmptyAreaClick }: RootDropZoneProps) {
    const id = getVisualDropId(isEmpty ? 'after' : 'before', null);
    const { setNodeRef, isOver } = useDroppable({ id });
    const active = (isOver && isDraggingFromLibrary) || isActive;

    return (
        <div
            ref={setNodeRef}
            role={isEmpty && onEmptyAreaClick ? 'button' : undefined}
            tabIndex={isEmpty && onEmptyAreaClick ? 0 : undefined}
            onClick={isEmpty && onEmptyAreaClick ? (e) => { e.stopPropagation(); onEmptyAreaClick(); } : undefined}
            onKeyDown={isEmpty && onEmptyAreaClick ? (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onEmptyAreaClick(); } } : undefined}
            className={cn(
                'rounded-lg border-2 border-dashed transition-all flex items-center justify-center',
                isEmpty ? 'min-h-[80px]' : 'min-h-[12px] py-1',
                active
                    ? 'border-primary bg-primary/10 text-primary'
                    : isDraggingFromLibrary
                      ? 'border-muted-foreground/40 hover:border-primary/50 bg-muted/20'
                      : 'border-muted-foreground/25 bg-muted/10'
            )}
            data-drop-zone={id}
        >
            {isEmpty ? (
                <span className="text-sm text-muted-foreground">
                    {isDraggingFromLibrary && active ? t('Drop here') : t('Drag components here')}
                </span>
            ) : (
                active && <span className="text-[10px] font-medium">{t('Drop here')}</span>
            )}
        </div>
    );
}
