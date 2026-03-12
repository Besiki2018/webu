import { useBuilderStore } from './builderStore';
import { useShallow } from 'zustand/shallow';

export function useAiStore() {
    return useBuilderStore(useShallow((state) => ({
        conversation: state.conversation,
        selectedNodeContext: state.selectedNodeContext,
        suggestedMutations: state.suggestedMutations,
        applyStatus: state.applyStatus,
        appendConversation: state.appendConversation,
        setConversation: state.setConversation,
        setSelectedNodeContext: state.setSelectedNodeContext,
        setSuggestedMutations: state.setSuggestedMutations,
        setApplyStatus: state.setApplyStatus,
        applyMutations: state.applyMutations,
    })));
}
