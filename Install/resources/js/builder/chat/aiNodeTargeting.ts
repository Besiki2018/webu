import { buildAiNodeId, extractAiNodeIds } from '@/builder/runtime/elementHover';
import {
    buildEditableTargetFromMention,
    buildTargetId,
    type BuilderBreakpoint,
    type BuilderEditableTarget,
    type BuilderInteractionState,
} from '@/builder/editingState';
import type { SelectedTargetContext } from '@/builder/selectedTargetContext';
import { buildSelectedTargetContext } from '@/builder/selectedTargetContext';
import type { WorkspaceBuilderStructureItem } from '@/builder/cms/workspaceBuilderSync';
import { getValueAtPath } from '@/builder/state/sectionProps';
import { buildSectionPreviewText } from '@/builder/state/sectionProps';
import type { ElementMention } from '@/types/inspector';

export interface ResolvedAiNodeTarget {
    aiNodeId: string;
    mention: ElementMention;
    target: BuilderEditableTarget;
}

function normalizeOptionalString(value: string | null | undefined): string | null {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function uniqueStrings(values: Array<string | null | undefined>): string[] {
    return Array.from(new Set(values.map((value) => normalizeOptionalString(value)).filter(Boolean) as string[]));
}

function resolveMentionCurrentValue(
    props: Record<string, unknown> | null,
    parameterPath: string | null,
    previewText: string,
    sectionKey: string,
): string {
    if (props && parameterPath) {
        const value = getValueAtPath(props, parameterPath);
        if (typeof value === 'string' && value.trim() !== '') {
            return value.trim();
        }
        if (typeof value === 'number' || typeof value === 'boolean') {
            return String(value);
        }
    }

    return buildSectionPreviewText(props ?? {}, previewText, sectionKey);
}

function matchStructureItemByAiNodeId(
    aiNodeId: string,
    builderStructureItems: WorkspaceBuilderStructureItem[],
): { item: WorkspaceBuilderStructureItem; parameterPath: string | null } | null {
    const candidates = builderStructureItems
        .map((item) => ({
            item,
            scopeId: normalizeOptionalString(item.localId),
            sectionKey: normalizeOptionalString(item.sectionKey),
        }))
        .sort((left, right) => (right.scopeId?.length ?? 0) - (left.scopeId?.length ?? 0));

    for (const candidate of candidates) {
        const scopeId = candidate.scopeId;
        if (scopeId && aiNodeId === scopeId) {
            return { item: candidate.item, parameterPath: null };
        }
        if (scopeId && aiNodeId.startsWith(`${scopeId}.`)) {
            return {
                item: candidate.item,
                parameterPath: aiNodeId.slice(scopeId.length + 1) || null,
            };
        }

        const sectionKey = candidate.sectionKey;
        if (sectionKey && aiNodeId === sectionKey) {
            return { item: candidate.item, parameterPath: null };
        }
        if (sectionKey && aiNodeId.startsWith(`${sectionKey}.`)) {
            return {
                item: candidate.item,
                parameterPath: aiNodeId.slice(sectionKey.length + 1) || null,
            };
        }
    }

    return null;
}

export function resolveAiNodeMention(input: {
    aiNodeId: string;
    builderStructureItems: WorkspaceBuilderStructureItem[];
}): ElementMention | null {
    const matched = matchStructureItemByAiNodeId(input.aiNodeId, input.builderStructureItems);
    if (!matched) {
        return null;
    }

    const { item, parameterPath } = matched;
    const currentValue = resolveMentionCurrentValue(
        item.props ?? null,
        parameterPath,
        item.previewText,
        item.sectionKey,
    );

    return {
        id: buildTargetId(item.localId, item.sectionKey, parameterPath),
        targetId: buildTargetId(item.localId, item.sectionKey, parameterPath),
        aiNodeId: buildAiNodeId(item.localId, parameterPath, item.sectionKey),
        tagName: parameterPath ? 'div' : 'section',
        selector: parameterPath
            ? `[data-webu-section-local-id="${item.localId.replace(/"/g, '\\"')}"]`
            : `[data-webu-section-local-id="${item.localId.replace(/"/g, '\\"')}"]`,
        textPreview: currentValue,
        currentValue,
        sectionKey: item.sectionKey,
        sectionLocalId: item.localId,
        parameterName: parameterPath,
        propName: parameterPath,
        componentPath: parameterPath,
        elementId: parameterPath ? `${item.sectionKey}.${parameterPath}` : item.sectionKey,
        componentKey: item.sectionKey,
    };
}

export function resolveAiNodeTargets(input: {
    message: string;
    selectedMentions?: ElementMention[];
    builderStructureItems: WorkspaceBuilderStructureItem[];
    currentBreakpoint?: BuilderBreakpoint | null;
    currentInteractionState?: BuilderInteractionState | null;
}): {
    resolvedTargets: ResolvedAiNodeTarget[];
    unresolvedNodeIds: string[];
} {
    const selectedMentions = Array.isArray(input.selectedMentions) ? input.selectedMentions : [];
    const requestedNodeIds = uniqueStrings([
        ...extractAiNodeIds(input.message),
        ...selectedMentions.map((mention) => mention.aiNodeId ?? null),
    ]);

    const resolvedTargets: ResolvedAiNodeTarget[] = [];
    const unresolvedNodeIds: string[] = [];

    requestedNodeIds.forEach((aiNodeId) => {
        const selectedMention = selectedMentions.find((candidate) => candidate.aiNodeId === aiNodeId) ?? null;
        const mention = selectedMention ?? resolveAiNodeMention({
            aiNodeId,
            builderStructureItems: input.builderStructureItems,
        });

        if (!mention) {
            unresolvedNodeIds.push(aiNodeId);
            return;
        }

        const matchedItem = input.builderStructureItems.find((item) => item.localId === mention.sectionLocalId) ?? null;
        const target = buildEditableTargetFromMention(mention, matchedItem?.props ?? null, {
            currentBreakpoint: input.currentBreakpoint ?? null,
            currentInteractionState: input.currentInteractionState ?? null,
        });

        if (!target) {
            unresolvedNodeIds.push(aiNodeId);
            return;
        }

        resolvedTargets.push({
            aiNodeId,
            mention,
            target,
        });
    });

    return {
        resolvedTargets,
        unresolvedNodeIds,
    };
}

function mergeAllowedUpdates(targets: BuilderEditableTarget[]) {
    const scopes = uniqueStrings(targets.map((target) => target.allowedUpdates?.scope ?? null));
    return {
        scope: (scopes[0] === 'section' ? 'section' : 'element') as 'section' | 'element',
        operationTypes: uniqueStrings(targets.flatMap((target) => target.allowedUpdates?.operationTypes ?? [])),
        fieldPaths: uniqueStrings(targets.flatMap((target) => target.allowedUpdates?.fieldPaths ?? [])),
        sectionOperationTypes: uniqueStrings(targets.flatMap((target) => target.allowedUpdates?.sectionOperationTypes ?? [])),
        sectionFieldPaths: uniqueStrings(targets.flatMap((target) => target.allowedUpdates?.sectionFieldPaths ?? [])),
    };
}

export function buildScopedSelectedTargetForAi(targets: BuilderEditableTarget[]): SelectedTargetContext | null {
    if (targets.length === 0) {
        return null;
    }

    if (targets.length === 1) {
        return buildSelectedTargetContext(targets[0] ?? null);
    }

    const first = targets[0] ?? null;
    if (!first) {
        return null;
    }

    const sameSection = targets.every((target) => target.sectionLocalId === first.sectionLocalId);
    if (!sameSection) {
        return null;
    }

    const uniquePaths = uniqueStrings(targets.map((target) => target.path ?? null));
    const uniqueComponentPaths = uniqueStrings(targets.map((target) => target.componentPath ?? null));

    return buildSelectedTargetContext({
        ...first,
        targetId: uniqueStrings(targets.map((target) => target.targetId)).join('|'),
        path: uniquePaths.length === 1 ? uniquePaths[0] ?? null : null,
        componentPath: uniqueComponentPaths.length === 1 ? uniqueComponentPaths[0] ?? null : null,
        elementId: uniqueStrings(targets.map((target) => target.elementId ?? null)).join('|') || first.elementId,
        textPreview: uniqueStrings(targets.map((target) => target.textPreview ?? null)).join(' | ') || first.textPreview,
        editableFields: uniqueStrings(targets.flatMap((target) => target.editableFields ?? [])),
        allowedUpdates: mergeAllowedUpdates(targets),
    });
}

export function buildAiTargetPromptContext(targets: ResolvedAiNodeTarget[]): string | null {
    if (targets.length === 0) {
        return null;
    }

    const lines = targets.map(({ aiNodeId, mention, target }) => {
        const currentValue = normalizeOptionalString(mention.currentValue ?? mention.textPreview);
        const sectionId = normalizeOptionalString(target.sectionLocalId) ?? 'unknown-section';
        const parameterPath = normalizeOptionalString(target.path ?? target.componentPath) ?? 'section';

        return `- ${aiNodeId} -> sectionId=${sectionId}, path=${parameterPath}${currentValue ? `, current="${currentValue}"` : ''}`;
    });

    return `[Target Nodes]\nOnly update these referenced nodes unless the user explicitly asks for a broader same-section change.\n${lines.join('\n')}`;
}
