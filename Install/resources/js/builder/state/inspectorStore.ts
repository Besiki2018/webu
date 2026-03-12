import { useBuilderStore } from './builderStore';
import { useShallow } from 'zustand/shallow';

export function useInspectorStore() {
    return useBuilderStore(useShallow((state) => ({
        activeInspectorTab: state.activeInspectorTab,
        resolvedSchemaKey: state.resolvedSchemaKey,
        validationErrors: state.validationErrors,
        patchNodeProps: state.patchNodeProps,
        patchNodeStyles: state.patchNodeStyles,
        setActiveInspectorTab: state.setActiveInspectorTab,
        setResolvedSchemaKey: state.setResolvedSchemaKey,
    })));
}
