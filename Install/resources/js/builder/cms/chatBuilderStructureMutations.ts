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
