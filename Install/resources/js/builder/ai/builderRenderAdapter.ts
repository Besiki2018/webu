import { getComponentSchema, isValidComponent, resolveComponentRegistryKey } from '../componentRegistry';
import { buildEditableTargetFromSection, type BuilderEditableTarget } from '../editingState';
import {
    applyBuilderUpdatePipeline,
    type BuilderCreateSectionFactory,
    type BuilderUpdateOperation,
    type BuilderUpdatePipelineResult,
    type BuilderUpdateStateSnapshot,
} from '../state/updatePipeline';
import { normalizePath, setValueAtPath } from '../state/sectionProps';
import type { BuilderSection } from '../visual/treeUtils';
import type { AiSitePlan, AiSitePlanSection } from './sitePlanner';

export type AiBuilderMutation =
    | {
        kind: 'insert-section';
        sectionType: string;
        insertIndex?: number;
        props?: Record<string, unknown>;
        localId?: string;
        selectInserted?: boolean;
    }
    | {
        kind: 'replace-section';
        targetSectionLocalId: string;
        sectionType: string;
        props?: Record<string, unknown>;
        newLocalId?: string;
        selectInserted?: boolean;
    }
    | {
        kind: 'update-props';
        targetSectionLocalId: string;
        patch: Record<string, unknown>;
    }
    | {
        kind: 'remove-section';
        targetSectionLocalId: string;
    }
    | {
        kind: 'reorder-section';
        targetSectionLocalId: string;
        toIndex: number;
    };

export interface AiBuilderRenderOptions {
    createSection: BuilderCreateSectionFactory;
    makeLocalId: () => string;
}

function sanitizePatch(componentKey: string, patch: Record<string, unknown> | null | undefined): Record<string, unknown> {
    if (!patch) {
        return {};
    }

    const schema = getComponentSchema(componentKey);
    if (!schema) {
        return {};
    }

    const sanitized = Object.entries(patch).reduce<Record<string, unknown>>((accumulator, [rawPath, value]) => {
        const normalized = normalizePath(rawPath);
        if (normalized.length === 0) {
            return accumulator;
        }

        const pathValue = normalized.join('.');
        const hasField = schema.fields.some((field) => (
            field.path === pathValue
            || pathValue.startsWith(`${field.path}.`)
        ));
        if (!hasField) {
            return accumulator;
        }

        return setValueAtPath(accumulator, normalized, value);
    }, {});

    return sanitized;
}

function resolveTargetSection(state: BuilderUpdateStateSnapshot, sectionLocalId: string): BuilderSection | null {
    return state.sectionsDraft.find((section) => section.localId === sectionLocalId) ?? null;
}

function replaceSelectionTarget(
    state: BuilderUpdateStateSnapshot,
    selectedSectionLocalId: string | null,
    selectedBuilderTarget: BuilderEditableTarget | null,
): BuilderUpdateStateSnapshot {
    return {
        ...state,
        selectedSectionLocalId,
        selectedBuilderTarget,
    };
}

function buildInsertOperations(
    mutation: Extract<AiBuilderMutation, { kind: 'insert-section' }>,
): BuilderUpdateOperation[] {
    const canonicalType = resolveComponentRegistryKey(mutation.sectionType);
    if (!canonicalType || !isValidComponent(canonicalType)) {
        return [];
    }

    const localId = mutation.localId?.trim() || undefined;
    const propsPatch = sanitizePatch(canonicalType, mutation.props);
    const operations: BuilderUpdateOperation[] = [{
        kind: 'insert-section',
        source: 'chat',
        sectionType: canonicalType,
        insertIndex: mutation.insertIndex ?? null,
        localId,
        selectInserted: mutation.selectInserted ?? false,
    }];

    if (localId && Object.keys(propsPatch).length > 0) {
        operations.push({
            kind: 'merge-props',
            source: 'chat',
            sectionLocalId: localId,
            patch: propsPatch,
        });
    }

    return operations;
}

function buildReplaceOperations(
    state: BuilderUpdateStateSnapshot,
    mutation: Extract<AiBuilderMutation, { kind: 'replace-section' }>,
    options: AiBuilderRenderOptions,
): BuilderUpdateOperation[] {
    const target = resolveTargetSection(state, mutation.targetSectionLocalId);
    const canonicalType = resolveComponentRegistryKey(mutation.sectionType);
    if (!target || !canonicalType || !isValidComponent(canonicalType)) {
        return [];
    }

    const patch = sanitizePatch(canonicalType, mutation.props);
    if (resolveComponentRegistryKey(target.type) === canonicalType) {
        return Object.keys(patch).length > 0
            ? [{
                kind: 'merge-props',
                source: 'chat',
                sectionLocalId: target.localId,
                patch,
            }]
            : [];
    }

    const insertIndex = state.sectionsDraft.findIndex((section) => section.localId === target.localId);
    const newLocalId = mutation.newLocalId?.trim() || options.makeLocalId();
    const operations: BuilderUpdateOperation[] = [{
        kind: 'delete-section',
        source: 'chat',
        sectionLocalId: target.localId,
    }, {
        kind: 'insert-section',
        source: 'chat',
        sectionType: canonicalType,
        insertIndex,
        localId: newLocalId,
        selectInserted: mutation.selectInserted ?? true,
    }];

    if (Object.keys(patch).length > 0) {
        operations.push({
            kind: 'merge-props',
            source: 'chat',
            sectionLocalId: newLocalId,
            patch,
        });
    }

    return operations;
}

export function buildBuilderOperationsFromAiMutation(
    state: BuilderUpdateStateSnapshot,
    mutation: AiBuilderMutation,
    options: AiBuilderRenderOptions,
): BuilderUpdateOperation[] {
    switch (mutation.kind) {
        case 'insert-section':
            return buildInsertOperations(mutation);
        case 'replace-section':
            return buildReplaceOperations(state, mutation, options);
        case 'update-props': {
            const target = resolveTargetSection(state, mutation.targetSectionLocalId);
            if (!target) {
                return [];
            }

            const patch = sanitizePatch(target.type, mutation.patch);
            return Object.keys(patch).length > 0
                ? [{
                    kind: 'merge-props',
                    source: 'chat',
                    sectionLocalId: mutation.targetSectionLocalId,
                    patch,
                }]
                : [];
        }
        case 'remove-section':
            return [{
                kind: 'delete-section',
                source: 'chat',
                sectionLocalId: mutation.targetSectionLocalId,
            }];
        case 'reorder-section':
            return [{
                kind: 'reorder-section',
                source: 'chat',
                sectionLocalId: mutation.targetSectionLocalId,
                toIndex: mutation.toIndex,
            }];
        default:
            return [];
    }
}

function prepareStateForMutation(
    state: BuilderUpdateStateSnapshot,
    mutation: AiBuilderMutation,
): BuilderUpdateStateSnapshot {
    if (mutation.kind !== 'update-props' && mutation.kind !== 'replace-section') {
        return state;
    }

    const targetLocalId = mutation.targetSectionLocalId;
    const targetSection = resolveTargetSection(state, targetLocalId);
    if (!targetSection) {
        return state;
    }

    return replaceSelectionTarget(
        state,
        targetLocalId,
        buildEditableTargetFromSection(targetSection),
    );
}

export function applyAiBuilderMutations(
    initialState: BuilderUpdateStateSnapshot,
    mutations: AiBuilderMutation[],
    options: AiBuilderRenderOptions,
): BuilderUpdatePipelineResult {
    if (mutations.length === 0) {
        return {
            ok: true,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: initialState,
            appliedOperations: [],
            errors: [],
        };
    }

    let workingState = initialState;
    const appliedOperations: BuilderUpdateOperation[] = [];
    let structuralChange = false;
    let immediatePreviewRefresh = false;

    for (const mutation of mutations) {
        const preparedState = prepareStateForMutation(workingState, mutation);
        const operations = buildBuilderOperationsFromAiMutation(preparedState, mutation, options);
        if (operations.length === 0) {
            continue;
        }

        const result = applyBuilderUpdatePipeline(preparedState, operations, {
            createSection: options.createSection,
        });
        if (!result.ok) {
            return {
                ok: false,
                changed: appliedOperations.length > 0,
                structuralChange,
                immediatePreviewRefresh,
                state: workingState,
                appliedOperations,
                errors: result.errors,
            };
        }

        workingState = result.state;
        appliedOperations.push(...result.appliedOperations);
        structuralChange = structuralChange || result.structuralChange;
        immediatePreviewRefresh = immediatePreviewRefresh || result.immediatePreviewRefresh;
    }

    return {
        ok: true,
        changed: appliedOperations.length > 0,
        structuralChange,
        immediatePreviewRefresh,
        state: workingState,
        appliedOperations,
        errors: [],
    };
}

function buildInsertMutationFromPlannedSection(
    section: AiSitePlanSection,
    index: number,
    options: AiBuilderRenderOptions,
): Extract<AiBuilderMutation, { kind: 'insert-section' }> {
    return {
        kind: 'insert-section',
        sectionType: section.componentKey,
        insertIndex: index,
        props: {
            ...(section.variant ? { variant: section.variant } : {}),
            ...(section.props ?? {}),
        },
        localId: options.makeLocalId(),
    };
}

export function buildSitePlanMutations(plan: AiSitePlan, options: AiBuilderRenderOptions): AiBuilderMutation[] {
    const homePage = plan.pages[0];
    if (!homePage) {
        return [];
    }

    return homePage.sections.map((section, index) => buildInsertMutationFromPlannedSection(section, index, options));
}

export function applyAiSitePlan(
    initialState: BuilderUpdateStateSnapshot,
    plan: AiSitePlan,
    options: AiBuilderRenderOptions,
): BuilderUpdatePipelineResult {
    const clearOperations: AiBuilderMutation[] = [...initialState.sectionsDraft]
        .reverse()
        .map((section) => ({
            kind: 'remove-section' as const,
            targetSectionLocalId: section.localId,
        }));
    const insertOperations = buildSitePlanMutations(plan, options);

    return applyAiBuilderMutations(
        replaceSelectionTarget(initialState, null, null),
        [...clearOperations, ...insertOperations],
        options,
    );
}
