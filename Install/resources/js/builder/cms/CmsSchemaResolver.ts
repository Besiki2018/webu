import type {
    BuilderBreakpoint,
    BuilderEditableTarget,
    BuilderInteractionState,
} from '@/builder/editingState';
import {
    buildCanonicalControlMetadataForSchemaField,
    type CanonicalControlMetadata,
} from '@/builder/inspector/InspectorFieldResolver';
import {
    buildCanonicalControlGroupAuditRows,
    type CanonicalControlGroupAuditRow,
} from '@/builder/inspector/InspectorRenderer';
import {
    buildSelectedSectionInspectorState,
    type SelectedSectionInspectorDraft,
    type SelectedSectionInspectorState,
} from '@/builder/inspector/selectedSectionInspectorState';

interface ResolveCmsSelectedSectionSchemaStateOptions {
    selectedSectionDraft: SelectedSectionInspectorDraft | null;
    selectedSectionEffectiveType: string | null;
    selectedSectionEffectiveParsedProps: Record<string, unknown> | null;
    selectedSectionSchemaProperties: Record<string, unknown> | null;
    selectedSectionSchemaHtmlTemplate: string;
    selectedBuilderTarget: BuilderEditableTarget | null;
    previewMode: BuilderBreakpoint;
    interactionState: BuilderInteractionState;
    elementorLike: boolean;
    normalizeSectionTypeKey: (value: string) => string;
}

export function resolveCmsSelectedSectionSchemaState(
    options: ResolveCmsSelectedSectionSchemaStateOptions,
): SelectedSectionInspectorState<CanonicalControlMetadata> & {
    controlGroupAuditRows: CanonicalControlGroupAuditRow[];
} {
    const inspectorState: SelectedSectionInspectorState<CanonicalControlMetadata> = buildSelectedSectionInspectorState({
        ...options,
        buildControlMeta: buildCanonicalControlMetadataForSchemaField,
    });

    return {
        ...inspectorState,
        controlGroupAuditRows: buildCanonicalControlGroupAuditRows(inspectorState.editableSchemaFieldsForDisplay),
    };
}
