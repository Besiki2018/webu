import { getComponentSchema, type BuilderFieldDefinition, type BuilderFieldType } from '@/builder/componentRegistry';
import {
    buildEditableTargetFromMessagePayload,
    editableTargetToMessagePayload,
    type BuilderEditableTarget,
} from '@/builder/editingState';
import {
    isRecord,
    normalizePath,
    parseSectionProps,
    setValueAtPath,
    stringifySectionProps,
} from '@/builder/state/sectionProps';
import type { BuilderSection } from '@/builder/visual/treeUtils';

export interface BuilderUpdateStateSnapshot {
    sectionsDraft: BuilderSection[];
    selectedSectionLocalId: string | null;
    selectedBuilderTarget: BuilderEditableTarget | null;
}

export type BuilderUpdateSource = 'sidebar' | 'chat' | 'toolbar' | 'drag-drop';

export interface BuilderSetFieldOperation {
    kind: 'set-field';
    source: BuilderUpdateSource;
    sectionLocalId: string;
    path: string | string[];
    value: unknown;
    nestedSectionPath?: number[];
}

export interface BuilderUnsetFieldOperation {
    kind: 'unset-field';
    source: BuilderUpdateSource;
    sectionLocalId: string;
    path: string | string[];
    nestedSectionPath?: number[];
}

export interface BuilderMergePropsOperation {
    kind: 'merge-props';
    source: BuilderUpdateSource;
    sectionLocalId: string;
    patch: Record<string, unknown>;
    nestedSectionPath?: number[];
}

export interface BuilderInsertSectionOperation {
    kind: 'insert-section';
    source: BuilderUpdateSource;
    sectionType: string;
    afterSectionId?: string | null;
    insertIndex?: number | null;
    props?: Record<string, unknown>;
    localId?: string | null;
}

export interface BuilderDeleteSectionOperation {
    kind: 'delete-section';
    source: BuilderUpdateSource;
    sectionLocalId: string;
}

export interface BuilderReorderSectionOperation {
    kind: 'reorder-section';
    source: BuilderUpdateSource;
    sectionLocalId: string;
    toIndex: number;
}

export type BuilderUpdateOperation =
    | BuilderSetFieldOperation
    | BuilderUnsetFieldOperation
    | BuilderMergePropsOperation
    | BuilderInsertSectionOperation
    | BuilderDeleteSectionOperation
    | BuilderReorderSectionOperation;

export interface BuilderCreateSectionInput {
    sectionType: string;
    localId?: string | null;
    props?: Record<string, unknown>;
}

export type BuilderCreateSectionFactory = (input: BuilderCreateSectionInput) => BuilderSection | null;

export interface BuilderUpdateError {
    code:
        | 'section_not_found'
        | 'schema_not_found'
        | 'field_not_found'
        | 'invalid_value_type'
        | 'target_mismatch'
        | 'invalid_patch'
        | 'insert_factory_missing'
        | 'insert_failed';
    message: string;
    operation: BuilderUpdateOperation;
}

export interface BuilderUpdatePipelineResult {
    ok: boolean;
    changed: boolean;
    structuralChange: boolean;
    immediatePreviewRefresh: boolean;
    state: BuilderUpdateStateSnapshot;
    appliedOperations: BuilderUpdateOperation[];
    errors: BuilderUpdateError[];
}

interface ResolvedSectionTarget {
    componentType: string | null;
    props: Record<string, unknown>;
    writeProps: (sections: BuilderSection[], nextProps: Record<string, unknown>) => BuilderSection[];
}

interface ResolvedSchemaField {
    field: BuilderFieldDefinition;
    relativePath: string[];
}

const COMPOUND_FIELD_TYPES = new Set<BuilderFieldType>([
    'link',
    'menu',
    'repeater',
    'button-group',
    'image',
    'video',
    'icon',
    'typography',
]);

const IMMEDIATE_PREVIEW_PATHS = new Set(['variant', 'layout_variant', 'style_variant', 'layoutVariant', 'styleVariant']);

function buildError(code: BuilderUpdateError['code'], message: string, operation: BuilderUpdateOperation): BuilderUpdateError {
    return { code, message, operation };
}

function normalizeSectionTypeKey(value: string | null | undefined): string {
    return typeof value === 'string' ? value.trim().toLowerCase() : '';
}

function cloneSections(sections: BuilderSection[]): BuilderSection[] {
    return sections.map((section) => ({ ...section }));
}

function getSectionProps(section: BuilderSection): Record<string, unknown> {
    if (isRecord(section.props)) {
        return section.props;
    }

    return parseSectionProps(section.propsText) ?? {};
}

function getNestedSectionAtPath(
    props: Record<string, unknown>,
    path: number[]
): { type: string | null; props: Record<string, unknown> } | null {
    if (path.length === 0) {
        return null;
    }

    let currentProps = props;
    let currentType: string | null = null;

    for (let index = 0; index < path.length; index += 1) {
        const sections = Array.isArray(currentProps.sections) ? currentProps.sections : [];
        const sectionIndex = path[index] ?? -1;
        const section = sectionIndex >= 0 && sectionIndex < sections.length ? sections[sectionIndex] : null;
        if (!isRecord(section)) {
            return null;
        }

        currentType = typeof section.type === 'string'
            ? section.type.trim()
            : (typeof section.key === 'string' ? section.key.trim() : null);
        currentProps = isRecord(section.props) ? { ...section.props } : {};
    }

    return {
        type: currentType,
        props: currentProps,
    };
}

function updateNestedSectionPropsAtPath(
    props: Record<string, unknown>,
    path: number[],
    nextProps: Record<string, unknown>
): Record<string, unknown> {
    if (path.length === 0) {
        return props;
    }

    const sections = Array.isArray(props.sections) ? [...props.sections] : [];
    const sectionIndex = path[0] ?? -1;
    if (sectionIndex < 0 || sectionIndex >= sections.length) {
        return props;
    }

    const section = isRecord(sections[sectionIndex]) ? { ...sections[sectionIndex] } : {};
    const currentSectionProps = isRecord(section.props) ? section.props : {};

    if (path.length === 1) {
        section.props = nextProps;
        sections[sectionIndex] = section;
        return {
            ...props,
            sections,
        };
    }

    section.props = updateNestedSectionPropsAtPath(currentSectionProps, path.slice(1), nextProps);
    sections[sectionIndex] = section;

    return {
        ...props,
        sections,
    };
}

function resolveSectionTarget(
    sections: BuilderSection[],
    sectionLocalId: string,
    nestedSectionPath: number[] | undefined
): ResolvedSectionTarget | null {
    const sectionIndex = sections.findIndex((section) => section.localId === sectionLocalId);
    if (sectionIndex < 0) {
        return null;
    }

    const section = sections[sectionIndex];
    const sectionProps = getSectionProps(section);
    const nestedPath = Array.isArray(nestedSectionPath) ? nestedSectionPath.filter((segment) => Number.isInteger(segment) && segment >= 0) : [];

    if (nestedPath.length === 0) {
        return {
            componentType: section.type ?? null,
            props: sectionProps,
            writeProps: (nextSections, nextProps) => nextSections.map((entry) => (
                entry.localId === sectionLocalId
                    ? { ...entry, props: nextProps, propsText: stringifySectionProps(nextProps), propsError: null }
                    : entry
            )),
        };
    }

    const nestedSection = getNestedSectionAtPath(sectionProps, nestedPath);
    if (!nestedSection) {
        return null;
    }

    return {
        componentType: nestedSection.type,
        props: nestedSection.props,
        writeProps: (nextSections, nextProps) => nextSections.map((entry) => {
            if (entry.localId !== sectionLocalId) {
                return entry;
            }

            const currentRootProps = getSectionProps(entry);
            const updatedRootProps = updateNestedSectionPropsAtPath(currentRootProps, nestedPath, nextProps);

            return {
                ...entry,
                props: updatedRootProps,
                propsText: stringifySectionProps(updatedRootProps),
                propsError: null,
            };
        }),
    };
}

function unsetValueAtPathNode(source: unknown, path: string[]): unknown {
    if (path.length === 0) {
        return source;
    }

    const [segment, ...rest] = path;
    if (/^\d+$/.test(segment)) {
        const index = Number(segment);
        const currentArray = Array.isArray(source) ? [...source] : [];
        if (index < 0 || index >= currentArray.length) {
            return source;
        }
        if (rest.length === 0) {
            currentArray.splice(index, 1);
            return currentArray;
        }
        currentArray[index] = unsetValueAtPathNode(currentArray[index], rest);
        return currentArray;
    }

    if (!isRecord(source)) {
        return source;
    }

    const nextRecord: Record<string, unknown> = { ...source };
    if (!(segment in nextRecord)) {
        return source;
    }

    if (rest.length === 0) {
        delete nextRecord[segment];
        return nextRecord;
    }

    nextRecord[segment] = unsetValueAtPathNode(nextRecord[segment], rest);
    return nextRecord;
}

function unsetValueAtPath(source: Record<string, unknown>, path: string | string[]): Record<string, unknown> {
    const normalizedPath = normalizePath(path);
    if (normalizedPath.length === 0) {
        return source;
    }

    return unsetValueAtPathNode(source, normalizedPath) as Record<string, unknown>;
}

function resolveSchemaField(componentType: string | null, path: string[]): ResolvedSchemaField | null {
    const normalizedComponentType = normalizeSectionTypeKey(componentType);
    if (normalizedComponentType === '' || path.length === 0) {
        return null;
    }

    const schema = getComponentSchema(normalizedComponentType);
    if (!schema) {
        return null;
    }

    const candidatePath = path.join('.');
    const exactField = schema.fields.find((field) => field.path === candidatePath) ?? null;
    if (exactField) {
        return {
            field: exactField,
            relativePath: [],
        };
    }

    const compoundField = schema.fields
        .filter((field) => candidatePath.startsWith(`${field.path}.`))
        .sort((left, right) => right.path.length - left.path.length)[0] ?? null;
    if (!compoundField || !COMPOUND_FIELD_TYPES.has(compoundField.type)) {
        return null;
    }

    return {
        field: compoundField,
        relativePath: candidatePath.slice(compoundField.path.length + 1).split('.').filter(Boolean),
    };
}

function isPrimitiveValue(value: unknown): value is string | number | boolean {
    return typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean';
}

function validateValueAgainstField(field: BuilderFieldDefinition, relativePath: string[], value: unknown): boolean {
    if (relativePath.length > 0) {
        return value === null || isPrimitiveValue(value) || Array.isArray(value) || isRecord(value);
    }

    switch (field.type) {
        case 'boolean':
        case 'visibility':
            return typeof value === 'boolean';
        case 'number':
            return typeof value === 'number' && Number.isFinite(value);
        case 'spacing':
        case 'width':
        case 'height':
            return typeof value === 'string' || (typeof value === 'number' && Number.isFinite(value));
        case 'typography':
            return value === null || isRecord(value);
        case 'menu':
        case 'repeater':
        case 'button-group':
            return value === null || Array.isArray(value) || isRecord(value);
        case 'link':
        case 'image':
        case 'video':
        case 'icon':
            return value === null || typeof value === 'string' || isRecord(value);
        case 'text':
        case 'richtext':
        case 'color':
        case 'alignment':
        case 'radius':
        case 'shadow':
        case 'overlay':
        case 'select':
        case 'layout-variant':
        case 'style-variant':
            return value === null || typeof value === 'string';
        default:
            return value === null || isPrimitiveValue(value) || Array.isArray(value) || isRecord(value);
    }
}

function validateTargetSelection(
    operation: BuilderUpdateOperation,
    target: BuilderEditableTarget | null,
    sectionLocalId: string,
    componentType: string | null,
    path: string[] | null
): BuilderUpdateError | null {
    if (!target || !target.sectionLocalId) {
        return null;
    }

    if (target.sectionLocalId !== sectionLocalId) {
        return buildError(
            'target_mismatch',
            'The update does not match the currently selected section target.',
            operation
        );
    }

    const selectedComponentType = normalizeSectionTypeKey(target.componentType ?? target.sectionKey ?? null);
    const nextComponentType = normalizeSectionTypeKey(componentType);
    if (selectedComponentType !== '' && nextComponentType !== '' && selectedComponentType !== nextComponentType) {
        return buildError(
            'target_mismatch',
            'The update does not match the currently selected component type.',
            operation
        );
    }

    if (
        operation.source !== 'sidebar'
        && path
        && target.path
        && Array.isArray(target.allowedUpdates?.fieldPaths)
        && target.allowedUpdates.fieldPaths.length > 0
    ) {
        const nextPath = path.join('.');
        const allowed = target.allowedUpdates.fieldPaths.some((candidate) => (
            candidate === nextPath
            || candidate.startsWith(`${nextPath}.`)
            || nextPath.startsWith(`${candidate}.`)
        ));
        if (!allowed) {
            return buildError(
                'target_mismatch',
                'The update is outside the selected element scope.',
                operation
            );
        }
    }

    return null;
}

function flattenPatchEntries(
    patch: Record<string, unknown>,
    prefix: string[] = []
): Array<{ path: string[]; value: unknown }> {
    const entries: Array<{ path: string[]; value: unknown }> = [];

    Object.entries(patch).forEach(([key, value]) => {
        const nextPath = [...prefix, key];
        if (Array.isArray(value) || !isRecord(value)) {
            entries.push({ path: nextPath, value });
            return;
        }

        const nestedEntries = flattenPatchEntries(value, nextPath);
        if (nestedEntries.length === 0) {
            entries.push({ path: nextPath, value });
            return;
        }

        entries.push(...nestedEntries);
    });

    return entries;
}

function refreshSelectedTarget(
    target: BuilderEditableTarget | null,
    sectionsDraft: BuilderSection[]
): BuilderEditableTarget | null {
    if (!target?.sectionLocalId) {
        return target;
    }

    const section = sectionsDraft.find((entry) => entry.localId === target.sectionLocalId);
    if (!section) {
        return null;
    }

    const payload = editableTargetToMessagePayload(target);
    if (!payload) {
        return null;
    }

    return buildEditableTargetFromMessagePayload({
        ...payload,
        sectionLocalId: section.localId,
        sectionKey: payload.sectionKey ?? section.type,
        componentType: payload.componentType ?? section.type,
        props: getSectionProps(section),
    });
}

function applySetLikeOperation(
    currentState: BuilderUpdateStateSnapshot,
    operation: BuilderSetFieldOperation | BuilderUnsetFieldOperation
): BuilderUpdatePipelineResult {
    const target = resolveSectionTarget(currentState.sectionsDraft, operation.sectionLocalId, operation.nestedSectionPath);
    if (!target) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [buildError('section_not_found', 'The target section could not be found.', operation)],
        };
    }

    const path = normalizePath(operation.path);
    if (path.length === 0) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [buildError('field_not_found', 'The target field path is empty.', operation)],
        };
    }

    const targetError = validateTargetSelection(operation, currentState.selectedBuilderTarget, operation.sectionLocalId, target.componentType, path);
    if (targetError) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [targetError],
        };
    }

    const resolvedField = resolveSchemaField(target.componentType, path);
    if (!resolvedField) {
        const hasSchema = Boolean(getComponentSchema(normalizeSectionTypeKey(target.componentType)));
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [buildError(
                hasSchema ? 'field_not_found' : 'schema_not_found',
                hasSchema
                    ? `Field "${path.join('.')}" is not declared in the component schema.`
                    : 'The component schema could not be resolved for this update.',
                operation
            )],
        };
    }

    if (operation.kind === 'set-field' && !validateValueAgainstField(resolvedField.field, resolvedField.relativePath, operation.value)) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [buildError(
                'invalid_value_type',
                `Value for "${path.join('.')}" is incompatible with schema field "${resolvedField.field.type}".`,
                operation
            )],
        };
    }

    const nextProps = operation.kind === 'set-field'
        ? setValueAtPath(target.props, path, operation.value)
        : unsetValueAtPath(target.props, path);
    const nextSections = target.writeProps(cloneSections(currentState.sectionsDraft), nextProps);
    const nextState: BuilderUpdateStateSnapshot = {
        sectionsDraft: nextSections,
        selectedSectionLocalId: currentState.selectedSectionLocalId,
        selectedBuilderTarget: refreshSelectedTarget(currentState.selectedBuilderTarget, nextSections),
    };

    return {
        ok: true,
        changed: true,
        structuralChange: false,
        immediatePreviewRefresh: IMMEDIATE_PREVIEW_PATHS.has(path[0] ?? ''),
        state: nextState,
        appliedOperations: [operation],
        errors: [],
    };
}

function applyMergePropsOperation(
    currentState: BuilderUpdateStateSnapshot,
    operation: BuilderMergePropsOperation
): BuilderUpdatePipelineResult {
    if (!isRecord(operation.patch) || Object.keys(operation.patch).length === 0) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [buildError('invalid_patch', 'Patch payload is empty.', operation)],
        };
    }

    const target = resolveSectionTarget(currentState.sectionsDraft, operation.sectionLocalId, operation.nestedSectionPath);
    if (!target) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [buildError('section_not_found', 'The target section could not be found.', operation)],
        };
    }

    const patchEntries = flattenPatchEntries(operation.patch);
    const targetError = validateTargetSelection(operation, currentState.selectedBuilderTarget, operation.sectionLocalId, target.componentType, null);
    if (targetError) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [targetError],
        };
    }

    const validationErrors: BuilderUpdateError[] = [];
    patchEntries.forEach((entry) => {
        const resolvedField = resolveSchemaField(target.componentType, entry.path);
        if (!resolvedField) {
            const hasSchema = Boolean(getComponentSchema(normalizeSectionTypeKey(target.componentType)));
            validationErrors.push(buildError(
                hasSchema ? 'field_not_found' : 'schema_not_found',
                hasSchema
                    ? `Field "${entry.path.join('.')}" is not declared in the component schema.`
                    : 'The component schema could not be resolved for this patch.',
                operation
            ));
            return;
        }

        if (!validateValueAgainstField(resolvedField.field, resolvedField.relativePath, entry.value)) {
            validationErrors.push(buildError(
                'invalid_value_type',
                `Value for "${entry.path.join('.')}" is incompatible with schema field "${resolvedField.field.type}".`,
                operation
            ));
        }
    });

    if (validationErrors.length > 0) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: validationErrors,
        };
    }

    const nextProps = patchEntries.reduce<Record<string, unknown>>((props, entry) => (
        setValueAtPath(props, entry.path, entry.value)
    ), target.props);
    const nextSections = target.writeProps(cloneSections(currentState.sectionsDraft), nextProps);
    const nextState: BuilderUpdateStateSnapshot = {
        sectionsDraft: nextSections,
        selectedSectionLocalId: currentState.selectedSectionLocalId,
        selectedBuilderTarget: refreshSelectedTarget(currentState.selectedBuilderTarget, nextSections),
    };

    return {
        ok: true,
        changed: true,
        structuralChange: false,
        immediatePreviewRefresh: patchEntries.some((entry) => IMMEDIATE_PREVIEW_PATHS.has(entry.path[0] ?? '')),
        state: nextState,
        appliedOperations: [operation],
        errors: [],
    };
}

function applyInsertSectionOperation(
    currentState: BuilderUpdateStateSnapshot,
    operation: BuilderInsertSectionOperation,
    createSection: BuilderCreateSectionFactory | undefined
): BuilderUpdatePipelineResult {
    if (!createSection) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [buildError('insert_factory_missing', 'No section factory is registered for insert operations.', operation)],
        };
    }

    const nextSection = createSection({
        sectionType: operation.sectionType,
        localId: operation.localId ?? null,
        props: operation.props,
    });
    if (!nextSection) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [buildError('insert_failed', `Could not create a section for "${operation.sectionType}".`, operation)],
        };
    }

    const nextSections = cloneSections(currentState.sectionsDraft);
    const afterSectionId = typeof operation.afterSectionId === 'string' && operation.afterSectionId.trim() !== ''
        ? operation.afterSectionId.trim()
        : null;
    const requestedInsertIndex = Number.isInteger(operation.insertIndex)
        ? Number(operation.insertIndex)
        : null;
    const insertIndex = afterSectionId
        ? Math.max(0, nextSections.findIndex((section) => section.localId === afterSectionId) + 1)
        : requestedInsertIndex !== null
            ? Math.max(0, Math.min(requestedInsertIndex, nextSections.length))
            : nextSections.length;
    nextSections.splice(insertIndex, 0, nextSection);

    return {
        ok: true,
        changed: true,
        structuralChange: true,
        immediatePreviewRefresh: false,
        state: {
            sectionsDraft: nextSections,
            selectedSectionLocalId: currentState.selectedSectionLocalId,
            selectedBuilderTarget: refreshSelectedTarget(currentState.selectedBuilderTarget, nextSections),
        },
        appliedOperations: [operation],
        errors: [],
    };
}

function applyDeleteSectionOperation(
    currentState: BuilderUpdateStateSnapshot,
    operation: BuilderDeleteSectionOperation
): BuilderUpdatePipelineResult {
    const sectionExists = currentState.sectionsDraft.some((section) => section.localId === operation.sectionLocalId);
    if (!sectionExists) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [buildError('section_not_found', 'The section to delete could not be found.', operation)],
        };
    }

    const nextSections = currentState.sectionsDraft.filter((section) => section.localId !== operation.sectionLocalId);
    const selectedBuilderTarget = currentState.selectedBuilderTarget?.sectionLocalId === operation.sectionLocalId
        ? null
        : refreshSelectedTarget(currentState.selectedBuilderTarget, nextSections);
    const selectedSectionLocalId = currentState.selectedSectionLocalId === operation.sectionLocalId
        ? null
        : currentState.selectedSectionLocalId;

    return {
        ok: true,
        changed: true,
        structuralChange: true,
        immediatePreviewRefresh: false,
        state: {
            sectionsDraft: nextSections,
            selectedSectionLocalId,
            selectedBuilderTarget,
        },
        appliedOperations: [operation],
        errors: [],
    };
}

function applyReorderSectionOperation(
    currentState: BuilderUpdateStateSnapshot,
    operation: BuilderReorderSectionOperation
): BuilderUpdatePipelineResult {
    const currentIndex = currentState.sectionsDraft.findIndex((section) => section.localId === operation.sectionLocalId);
    if (currentIndex < 0) {
        return {
            ok: false,
            changed: false,
            structuralChange: false,
            immediatePreviewRefresh: false,
            state: currentState,
            appliedOperations: [],
            errors: [buildError('section_not_found', 'The section to reorder could not be found.', operation)],
        };
    }

    const nextSections = cloneSections(currentState.sectionsDraft);
    const [movedSection] = nextSections.splice(currentIndex, 1);
    const boundedIndex = Math.max(0, Math.min(operation.toIndex, nextSections.length));
    nextSections.splice(boundedIndex, 0, movedSection);

    return {
        ok: true,
        changed: true,
        structuralChange: true,
        immediatePreviewRefresh: false,
        state: {
            sectionsDraft: nextSections,
            selectedSectionLocalId: currentState.selectedSectionLocalId,
            selectedBuilderTarget: refreshSelectedTarget(currentState.selectedBuilderTarget, nextSections),
        },
        appliedOperations: [operation],
        errors: [],
    };
}

function applySingleOperation(
    currentState: BuilderUpdateStateSnapshot,
    operation: BuilderUpdateOperation,
    createSection: BuilderCreateSectionFactory | undefined
): BuilderUpdatePipelineResult {
    switch (operation.kind) {
        case 'set-field':
        case 'unset-field':
            return applySetLikeOperation(currentState, operation);
        case 'merge-props':
            return applyMergePropsOperation(currentState, operation);
        case 'insert-section':
            return applyInsertSectionOperation(currentState, operation, createSection);
        case 'delete-section':
            return applyDeleteSectionOperation(currentState, operation);
        case 'reorder-section':
            return applyReorderSectionOperation(currentState, operation);
        default:
            return {
                ok: false,
                changed: false,
                structuralChange: false,
                immediatePreviewRefresh: false,
                state: currentState,
                appliedOperations: [],
                errors: [buildError('invalid_patch', 'Unsupported builder update operation.', operation)],
            };
    }
}

export function applyBuilderUpdatePipeline(
    initialState: BuilderUpdateStateSnapshot,
    operations: BuilderUpdateOperation[],
    options: { createSection?: BuilderCreateSectionFactory } = {}
): BuilderUpdatePipelineResult {
    if (!Array.isArray(operations) || operations.length === 0) {
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

    for (const operation of operations) {
        const result = applySingleOperation(workingState, operation, options.createSection);
        if (!result.ok) {
            return {
                ok: false,
                changed: appliedOperations.length > 0,
                structuralChange,
                immediatePreviewRefresh,
                state: initialState,
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

function normalizeChatPatch(payload: unknown): Record<string, unknown> {
    if (!isRecord(payload)) {
        return {};
    }

    return payload;
}

/**
 * Build a merge-props patch for replaceImage that uses schema prop names.
 * Maps url/alt/imageKey to: image, backgroundImage, imageAlt (builder/schema convention).
 */
function buildReplaceImagePatch(operation: Record<string, unknown>): Record<string, unknown> {
    const url = typeof operation.url === 'string' ? operation.url.trim() : (typeof operation.image_url === 'string' ? operation.image_url.trim() : '');
    const alt = typeof operation.alt === 'string' ? operation.alt.trim() : undefined;
    const imageKey = typeof operation.imageKey === 'string' ? operation.imageKey.trim().toLowerCase() : 'image';

    if (!url) return {};

    const isBackground = imageKey === 'backgroundimage' || imageKey === 'background';
    const patch: Record<string, unknown> = isBackground
        ? { backgroundImage: url }
        : { image: url };
    if (alt !== undefined) patch.imageAlt = alt;
    return patch;
}

/**
 * Converts AI/chat ChangeSet operations into builder pipeline operations.
 * Schema props are validated by the pipeline via getComponentSchema; chat should use
 * editableFields/chatTargets prop names (e.g. title, backgroundColor, menu_items, padding).
 *
 * Supported ops: updateText, setField, replaceImage, updateSection, updateButton,
 * insertSection, deleteSection, reorderSection.
 */
export function buildBuilderUpdateOperationsFromChangeSet(changeSet: {
    operations?: Array<Record<string, unknown>>;
}): BuilderUpdateOperation[] {
    const rawOperations = Array.isArray(changeSet.operations) ? changeSet.operations : [];

    return rawOperations.flatMap<BuilderUpdateOperation>((operation) => {
        const opType = typeof operation.op === 'string' ? operation.op.trim() : '';
        const sectionLocalId = typeof operation.sectionId === 'string'
            ? operation.sectionId.trim()
            : (typeof operation.section_id === 'string' ? operation.section_id.trim() : '');

        switch (opType) {
            case 'updateText': {
                const pathValue = Array.isArray(operation.path)
                    ? operation.path.map((segment) => String(segment).trim()).filter(Boolean)
                    : String(operation.path ?? operation.parameter_path ?? '').trim();
                const defaultPath = pathValue === '' ? 'title' : pathValue;
                const normalizedPath = Array.isArray(pathValue) && pathValue.length > 0
                    ? pathValue
                    : normalizePath(defaultPath);

                return sectionLocalId === ''
                    ? []
                    : [{
                        kind: 'set-field',
                        source: 'chat',
                        sectionLocalId,
                        path: normalizedPath,
                        value: typeof operation.value === 'string' ? operation.value : String(operation.value ?? ''),
                    }];
            }
            case 'setField': {
                const pathValue = Array.isArray(operation.path)
                    ? operation.path.map((segment) => String(segment).trim()).filter(Boolean)
                    : String(operation.path ?? operation.parameter_path ?? '').trim();
                const normalizedPath = pathValue === '' ? [] : normalizePath(pathValue);
                if (normalizedPath.length === 0 || sectionLocalId === '') return [];
                return [{
                    kind: 'set-field',
                    source: 'chat',
                    sectionLocalId,
                    path: normalizedPath,
                    value: operation.value,
                }];
            }
            case 'replaceImage': {
                const patch = normalizeChatPatch(operation.patch);
                if (Object.keys(patch).length > 0) {
                    return sectionLocalId === ''
                        ? []
                        : [{
                            kind: 'merge-props',
                            source: 'chat',
                            sectionLocalId,
                            patch,
                        }];
                }

                const replacePatch = buildReplaceImagePatch(operation);
                if (Object.keys(replacePatch).length === 0) return [];
                return sectionLocalId === ''
                    ? []
                    : [{
                        kind: 'merge-props',
                        source: 'chat',
                        sectionLocalId,
                        patch: replacePatch,
                    }];
            }
            case 'updateSection':
            case 'updateButton': {
                const patch = normalizeChatPatch(operation.patch);
                if (Object.keys(patch).length > 0) {
                    return sectionLocalId === ''
                        ? []
                        : [{
                            kind: 'merge-props',
                            source: 'chat',
                            sectionLocalId,
                            patch,
                        }];
                }
                if (opType === 'updateButton') {
                    const buttonPatch: Record<string, unknown> = {};
                    if (typeof operation.label === 'string') buttonPatch.buttonText = operation.label;
                    if (typeof operation.href === 'string') buttonPatch.buttonLink = operation.href;
                    if (typeof operation.variant === 'string') buttonPatch.buttonVariant = operation.variant;
                    if (Object.keys(buttonPatch).length > 0) {
                        return sectionLocalId === '' ? [] : [{ kind: 'merge-props', source: 'chat', sectionLocalId, patch: buttonPatch }];
                    }
                }
                return [];
            }
            case 'insertSection':
                return typeof operation.sectionType === 'string' && operation.sectionType.trim() !== ''
                    ? [{
                        kind: 'insert-section',
                        source: 'chat',
                        sectionType: operation.sectionType.trim(),
                        afterSectionId: typeof operation.afterSectionId === 'string' ? operation.afterSectionId.trim() : null,
                        props: normalizeChatPatch(operation.props),
                    }]
                    : [];
            case 'deleteSection':
                return sectionLocalId === ''
                    ? []
                    : [{
                        kind: 'delete-section',
                        source: 'chat',
                        sectionLocalId,
                    }];
            case 'reorderSection':
                return sectionLocalId === '' || !Number.isInteger(operation.toIndex)
                    ? []
                    : [{
                        kind: 'reorder-section',
                        source: 'chat',
                        sectionLocalId,
                        toIndex: Number(operation.toIndex),
                    }];
            default:
                return [];
        }
    });
}

/**
 * Applies a chat/AI ChangeSet to builder state via the unified update pipeline.
 * All schema prop edits from chat flow through this; paths are validated against
 * component schema (getComponentSchema). Use schema prop names from
 * editableFields/chatTargets (e.g. title, backgroundColor, menu_items, padding).
 */
export function applyBuilderChangeSetPipeline(
    initialState: BuilderUpdateStateSnapshot,
    changeSet: { operations?: Array<Record<string, unknown>> },
    options: { createSection?: BuilderCreateSectionFactory } = {}
): BuilderUpdatePipelineResult {
    return applyBuilderUpdatePipeline(
        initialState,
        buildBuilderUpdateOperationsFromChangeSet(changeSet),
        options
    );
}

/**
 * Single entry point for updating one component's props (validate → patch → return new state).
 * Use this or applyBuilderUpdatePipeline so all callers (Sidebar, Chat, etc.) go through the same validation and patch path.
 *
 * - Validates component existence (section by localId)
 * - Validates field key against schema (resolveSchemaField)
 * - Validates value type (validateValueAgainstField)
 * - Patches props and returns new state; caller must apply state and trigger rerender/sync.
 */
export function updateComponentProps(
    initialState: BuilderUpdateStateSnapshot,
    componentId: string,
    payload: { path: string | string[]; value: unknown } | { patch: Record<string, unknown> },
    source: BuilderUpdateSource = 'sidebar'
): BuilderUpdatePipelineResult {
    const pathPayload = 'path' in payload && payload.path !== undefined;
    const operations: BuilderUpdateOperation[] = pathPayload
        ? [{ kind: 'set-field', source, sectionLocalId: componentId, path: payload.path, value: (payload as { path: string | string[]; value: unknown }).value }]
        : [{ kind: 'merge-props', source, sectionLocalId: componentId, patch: (payload as { patch: Record<string, unknown> }).patch }];
    return applyBuilderUpdatePipeline(initialState, operations, {});
}
