import type { BuilderEditableTarget } from '@/builder/editingState';
import type { WorkspaceBuilderStructureItem as BuilderStructureItem } from '@/builder/cms/workspaceBuilderSync';

export interface PendingBuilderSelectionSnapshot {
    sectionLocalId: string | null;
    sectionKey: string | null;
    target: BuilderEditableTarget | null;
}

export interface PendingBuilderStructureMutation {
    requestId: string;
    mutation: 'add-section' | 'remove-section' | 'move-section';
    baseItems: BuilderStructureItem[];
    previewItems: BuilderStructureItem[] | null;
    selectionSnapshot: PendingBuilderSelectionSnapshot | null;
}

export function createPendingBuilderSelectionSnapshot(input: {
    sectionLocalId: string | null;
    sectionKey: string | null;
    target: BuilderEditableTarget | null;
}): PendingBuilderSelectionSnapshot {
    return {
        sectionLocalId: input.sectionLocalId,
        sectionKey: input.sectionKey,
        target: input.target,
    };
}

export function buildOptimisticRemovedStructureItems(
    items: BuilderStructureItem[],
    sectionLocalId: string,
): BuilderStructureItem[] {
    return items.filter((item) => item.localId !== sectionLocalId);
}

export function buildOptimisticInsertedStructureItems(
    items: BuilderStructureItem[],
    nextItem: BuilderStructureItem,
    options?: {
        afterSectionLocalId?: string | null;
        placement?: 'before' | 'after' | 'inside' | null;
    },
): BuilderStructureItem[] {
    const nextItems = [...items];
    const existingIndex = nextItems.findIndex((item) => item.localId === nextItem.localId);
    if (existingIndex >= 0) {
        nextItems.splice(existingIndex, 1);
    }

    const placement = options?.placement ?? null;
    const anchorLocalId = options?.afterSectionLocalId?.trim() ?? '';
    if (placement === 'inside') {
        return nextItems;
    }

    if (anchorLocalId === '') {
        nextItems.push(nextItem);
        return nextItems;
    }

    const anchorIndex = nextItems.findIndex((item) => item.localId === anchorLocalId);
    if (anchorIndex === -1) {
        nextItems.push(nextItem);
        return nextItems;
    }

    const insertIndex = placement === 'before' ? anchorIndex : anchorIndex + 1;
    nextItems.splice(insertIndex, 0, nextItem);
    return nextItems;
}
