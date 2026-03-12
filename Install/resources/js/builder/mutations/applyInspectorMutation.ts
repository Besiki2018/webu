export interface NestedInspectorSectionTarget {
    parentLocalId: string;
    path: number[];
}

interface BuildInspectorMutationHandlersOptions<TTypographyStyle> {
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

export function buildInspectorMutationHandlers<TTypographyStyle>(
    options: BuildInspectorMutationHandlersOptions<TTypographyStyle>,
): {
    onChangePath: (path: string[], value: unknown) => void;
    onChangeTextTypography: (fieldKey: string, updater: (current: TTypographyStyle | null) => TTypographyStyle | null) => void;
    onClearTextTypography: (fieldKey: string) => void;
} {
    const nestedTarget = options.selectedNestedSection;

    if (nestedTarget && nestedTarget.path.length > 0) {
        return {
            onChangePath: (path, value) => options.updateNestedSectionPathProp(nestedTarget.parentLocalId, nestedTarget.path, path, value),
            onChangeTextTypography: (fieldKey, updater) => options.updateNestedSectionTextTypographyProp(
                nestedTarget.parentLocalId,
                nestedTarget.path,
                fieldKey,
                updater,
            ),
            onClearTextTypography: (fieldKey) => options.updateNestedSectionTextTypographyProp(
                nestedTarget.parentLocalId,
                nestedTarget.path,
                fieldKey,
                () => null,
            ),
        };
    }

    return {
        onChangePath: (path, value) => options.updateSectionPathProp(options.selectedSectionLocalId, path, value),
        onChangeTextTypography: (fieldKey, updater) => options.updateSectionTextTypographyProp(options.selectedSectionLocalId, fieldKey, updater),
        onClearTextTypography: (fieldKey) => options.updateSectionTextTypographyProp(options.selectedSectionLocalId, fieldKey, () => null),
    };
}
