import { buildSectionPreviewText } from '@/builder/state/sectionProps';
import {
    buildSectionScopedEditableTarget,
    buildEditableTargetFromMessagePayload,
    type BuilderBreakpoint,
    type BuilderEditableTarget,
    type BuilderInteractionState,
} from '@/builder/editingState';
import type { WorkspaceBuilderStructureItem as BuilderStructureItem } from '@/builder/cms/workspaceBuilderSync';
import type { ElementMention } from '@/types/inspector';

export interface ResolvedMentionBuilderTarget {
    matchedItem: BuilderStructureItem | null;
    resolvedLocalId: string | null;
    resolvedSectionKey: string | null;
    resolvedProps: Record<string, unknown> | null;
    target: BuilderEditableTarget | null;
}

export interface StructureItemSelectionResult {
    payload: {
        sectionLocalId: string;
        sectionKey: string;
        componentType: string;
        componentName: string;
        props: Record<string, unknown> | null;
        textPreview: string;
    };
    target: BuilderEditableTarget;
}

function normalizeOptionalString(value: string | null | undefined): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

export function resolveMentionBuilderTarget(input: {
    element: ElementMention;
    builderStructureItems: BuilderStructureItem[];
    currentBreakpoint?: BuilderBreakpoint | null;
    currentInteractionState?: BuilderInteractionState | null;
}): ResolvedMentionBuilderTarget {
    const nextSectionLocalId = normalizeOptionalString(input.element.sectionLocalId);
    const nextSectionKey = normalizeOptionalString(input.element.sectionKey);
    let matchedItem: BuilderStructureItem | null = null;
    if (nextSectionLocalId) {
        matchedItem = input.builderStructureItems.find((item) => item.localId === nextSectionLocalId) ?? null;
    }
    if (!matchedItem && nextSectionKey) {
        matchedItem = input.builderStructureItems.find((item) => item.sectionKey === nextSectionKey) ?? null;
    }
    const resolvedLocalId = matchedItem?.localId ?? nextSectionLocalId;
    const resolvedProps = matchedItem?.props ?? null;
    const target = buildSectionScopedEditableTarget({
        sectionLocalId: resolvedLocalId,
        sectionKey: matchedItem?.sectionKey ?? nextSectionKey,
        componentType: matchedItem?.sectionKey ?? nextSectionKey,
        componentName: matchedItem?.label ?? null,
        props: resolvedProps,
        textPreview: matchedItem
            ? buildSectionPreviewText(matchedItem.props, matchedItem.previewText, matchedItem.sectionKey)
            : (input.element.textPreview ?? null),
        currentBreakpoint: input.currentBreakpoint ?? null,
        currentInteractionState: input.currentInteractionState ?? null,
    });

    return {
        matchedItem,
        resolvedLocalId,
        resolvedSectionKey: matchedItem?.sectionKey ?? nextSectionKey,
        resolvedProps,
        target,
    };
}

export function buildStructureItemSelection(item: BuilderStructureItem): StructureItemSelectionResult {
    const textPreview = buildSectionPreviewText(item.props, item.previewText, item.sectionKey);
    const payload = {
        sectionLocalId: item.localId,
        sectionKey: item.sectionKey,
        componentType: item.sectionKey,
        componentName: item.label,
        props: item.props,
        textPreview,
    };

    return {
        payload,
        target: buildEditableTargetFromMessagePayload(payload)!,
    };
}
