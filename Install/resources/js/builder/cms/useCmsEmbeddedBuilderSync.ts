import { useCmsEmbeddedBuilderMutationHandlers } from '@/builder/cms/useCmsEmbeddedBuilderMutationHandlers';
import { useCmsEmbeddedBuilderSelectionHandlers } from '@/builder/cms/useCmsEmbeddedBuilderSelectionHandlers';
import { useEmbeddedBuilderBridge } from '@/builder/cms/useEmbeddedBuilderBridge';

type EmbeddedBuilderBridgeBaseOptions = Omit<
    Parameters<typeof useEmbeddedBuilderBridge>[0],
    | 'onClearSelectedSection'
    | 'onSetInitialSections'
    | 'onApplyChangeSet'
    | 'onSetSelectedTarget'
    | 'onSetSelectedSection'
    | 'onSetSelectedSectionKey'
    | 'onSaveDraft'
    | 'onAddSectionByKey'
    | 'onRemoveSection'
    | 'onMoveSection'
>;

interface UseCmsEmbeddedBuilderSyncOptions {
    selection: Parameters<typeof useCmsEmbeddedBuilderSelectionHandlers>[0];
    mutation: Parameters<typeof useCmsEmbeddedBuilderMutationHandlers>[0];
    bridge: EmbeddedBuilderBridgeBaseOptions;
}

export function useCmsEmbeddedBuilderSync({
    selection,
    mutation,
    bridge,
}: UseCmsEmbeddedBuilderSyncOptions): void {
    const {
        handleEmbeddedBuilderClearSelection,
        handleEmbeddedBuilderInitialSections,
        handleEmbeddedBuilderSelectedTarget,
        handleEmbeddedBuilderSelectedSection,
        handleEmbeddedBuilderSelectedSectionKey,
    } = useCmsEmbeddedBuilderSelectionHandlers(selection);

    const {
        handleEmbeddedBuilderChangeSet,
        handleEmbeddedBuilderSaveDraft,
        handleEmbeddedBuilderAddSection,
        handleEmbeddedBuilderRemoveSection,
        handleEmbeddedBuilderMoveSection,
    } = useCmsEmbeddedBuilderMutationHandlers(mutation);

    useEmbeddedBuilderBridge({
        ...bridge,
        onClearSelectedSection: handleEmbeddedBuilderClearSelection,
        onSetInitialSections: handleEmbeddedBuilderInitialSections,
        onApplyChangeSet: handleEmbeddedBuilderChangeSet,
        onSetSelectedTarget: handleEmbeddedBuilderSelectedTarget,
        onSetSelectedSection: handleEmbeddedBuilderSelectedSection,
        onSetSelectedSectionKey: handleEmbeddedBuilderSelectedSectionKey,
        onSaveDraft: handleEmbeddedBuilderSaveDraft,
        onAddSectionByKey: handleEmbeddedBuilderAddSection,
        onRemoveSection: handleEmbeddedBuilderRemoveSection,
        onMoveSection: handleEmbeddedBuilderMoveSection,
    });
}
