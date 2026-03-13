import { buildInspectorMutationHandlers, type NestedInspectorSectionTarget } from '@/builder/mutations/applyInspectorMutation';

export interface CmsInspectorMutationDispatcherOptions<TTypographyStyle> {
    selectedSectionLocalId: string;
    selectedNestedSection: NestedInspectorSectionTarget | null;
    updateSectionPathProp: (localId: string, path: string[], value: unknown) => void;
    updateNestedSectionPathProp: (parentLocalId: string, path: number[], propPath: string[], value: unknown) => void;
    updateSectionTextTypographyProp: (
        localId: string,
        companionKey: string,
        updater: (current: TTypographyStyle | null) => TTypographyStyle | null,
    ) => void;
    updateNestedSectionTextTypographyProp: (
        parentLocalId: string,
        path: number[],
        companionKey: string,
        updater: (current: TTypographyStyle | null) => TTypographyStyle | null,
    ) => void;
}

export function buildCmsInspectorMutationDispatcher<TTypographyStyle>(
    options: CmsInspectorMutationDispatcherOptions<TTypographyStyle>,
) {
    return buildInspectorMutationHandlers(options);
}
