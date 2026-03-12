import { getComponentShortName } from '@/builder/componentParameterMetadata';
import { getComponentSchema } from '@/builder/componentRegistry';
import {
    collectSchemaFieldPaths,
    expandSchemaAwareAliasPaths,
    isSchemaFieldPathRelated,
    normalizeComparableSchemaFieldPath,
    resolveSchemaMatchedField,
    splitSchemaFieldPath,
} from '@/builder/schema/schemaBindingResolver';
import { getEntry } from './registry/componentRegistry';
import { isRecord, parseSectionProps } from '@/builder/state/sectionProps';
import type { BuilderSection } from '@/builder/visual/treeUtils';
import type { ElementMention } from '@/types/inspector';
import type {
    BuilderComponentSchema,
    BuilderFieldDefinition,
    BuilderFieldGroup,
    BuilderFieldType,
} from '@/builder/componentRegistry';

export type BuilderSidebarMode = 'elements' | 'settings' | 'design-system';
export type BuilderSidebarTab = 'content' | 'layout' | 'style' | 'advanced';
export type BuilderBreakpoint = 'desktop' | 'tablet' | 'mobile';
export type BuilderInteractionState = 'normal' | 'hover' | 'focus' | 'active';

export interface BuilderTargetVariantState {
    kind: 'layout' | 'style';
    path: string | null;
    active: string | null;
    options: string[];
}

export interface BuilderTargetVariants {
    layout?: BuilderTargetVariantState | null;
    style?: BuilderTargetVariantState | null;
}

export interface BuilderTargetAllowedUpdates {
    scope: 'element' | 'section';
    operationTypes: string[];
    fieldPaths: string[];
    sectionOperationTypes: string[];
    sectionFieldPaths: string[];
}

export interface BuilderTargetResponsiveContext {
    currentBreakpoint: BuilderBreakpoint;
    currentInteractionState: BuilderInteractionState;
    availableBreakpoints: BuilderBreakpoint[];
    availableInteractionStates: BuilderInteractionState[];
    supportsVisibility: boolean;
    supportsResponsiveOverrides: boolean;
    visibleFieldPaths: string[];
    responsiveFieldPaths: string[];
    stateFieldPaths: string[];
}

export interface BuilderEditableTarget {
    targetId: string;
    pageId?: number | null;
    pageSlug?: string | null;
    pageTitle?: string | null;
    sectionLocalId: string | null;
    sectionKey: string | null;
    componentType: string | null;
    componentName: string | null;
    path: string | null;
    componentPath?: string | null;
    elementId: string | null;
    selector: string | null;
    textPreview: string | null;
    props: Record<string, unknown> | null;
    fieldLabel?: string | null;
    fieldGroup?: BuilderFieldGroup | null;
    builderId?: string | null;
    parentId?: string | null;
    editableFields?: string[];
    sectionId?: string | null;
    instanceId?: string | null;
    variants?: BuilderTargetVariants | null;
    allowedUpdates?: BuilderTargetAllowedUpdates | null;
    responsiveContext?: BuilderTargetResponsiveContext | null;
}

export interface BuilderSelectionMessagePayload {
    pageId?: number | null;
    pageSlug?: string | null;
    pageTitle?: string | null;
    sectionLocalId?: string | null;
    sectionKey?: string | null;
    componentType?: string | null;
    componentName?: string | null;
    parameterPath?: string | null;
    componentPath?: string | null;
    elementId?: string | null;
    selector?: string | null;
    textPreview?: string | null;
    props?: Record<string, unknown> | null;
    fieldLabel?: string | null;
    fieldGroup?: BuilderFieldGroup | null;
    builderId?: string | null;
    parentId?: string | null;
    editableFields?: string[];
    sectionId?: string | null;
    instanceId?: string | null;
    variants?: BuilderTargetVariants | null;
    allowedUpdates?: BuilderTargetAllowedUpdates | null;
    currentBreakpoint?: BuilderBreakpoint | null;
    currentInteractionState?: BuilderInteractionState | null;
    responsiveContext?: BuilderTargetResponsiveContext | null;
}

export function buildTargetId(sectionLocalId: string | null, sectionKey: string | null, path: string | null): string {
    return [sectionLocalId || sectionKey || 'builder-target', path || 'section'].join('::');
}

export function getBuilderTargetStableNodeId(target: BuilderEditableTarget | null): string | null {
    return target?.targetId
        ?? target?.builderId
        ?? target?.instanceId
        ?? target?.elementId
        ?? target?.sectionLocalId
        ?? null;
}

export function getBuilderTargetSchemaKey(target: BuilderEditableTarget | null): string | null {
    return target?.componentType
        ?? target?.sectionKey
        ?? null;
}

export function getBuilderTargetPropPaths(target: BuilderEditableTarget | null): string[] {
    if (!target) {
        return [];
    }

    return uniqueStringList([
        target.path,
        target.componentPath ?? null,
        ...(target.allowedUpdates?.fieldPaths ?? []),
        ...(target.allowedUpdates?.sectionFieldPaths ?? []),
        ...(target.editableFields ?? []),
    ]);
}

/**
 * Resolve componentPath (scope) for a given parameter path.
 * For leaf paths like items.0.title, returns the parent scope (items.0).
 * Never broadens the actual path — path stays exact; componentPath is scope only.
 */
function resolvePreferredComponentPath(
    exactPath: string | null,
    candidatePaths: Array<string | null | undefined>
): string | null {
    const normalizedExactPath = typeof exactPath === 'string' && exactPath.trim() !== '' ? exactPath.trim() : null;
    const normalizedCandidates = uniqueStringList(candidatePaths);
    if (normalizedCandidates.length === 0) {
        return normalizedExactPath;
    }

    const ranked = normalizedCandidates.sort((left, right) => right.length - left.length);
    if (!normalizedExactPath) {
        return ranked[0] ?? null;
    }

    const ancestorCandidates = ranked.filter((candidate) => (
        candidate === normalizedExactPath
        || normalizedExactPath.startsWith(`${candidate}.`)
    ));

    if (ancestorCandidates.length === 0) {
        return ranked[0] ?? normalizedExactPath;
    }

    const broaderCandidates = ancestorCandidates.filter((candidate) => candidate !== normalizedExactPath);
    return broaderCandidates[0] ?? ancestorCandidates[0] ?? normalizedExactPath;
}

function isNumericPathSegment(value: string | undefined): boolean {
    return Boolean(value && /^\d+$/.test(value));
}

function deriveFieldStem(path: string): string {
    const normalized = path.trim();
    if (normalized === '') {
        return '';
    }

    const lastSegment = normalized.split('.').filter(Boolean).pop() ?? normalized;
    const tokenized = lastSegment
        .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
        .split(/[_\s-]+/)
        .map((token) => token.trim().toLowerCase())
        .filter(Boolean);

    if (tokenized.length <= 1) {
        return tokenized[0] ?? normalized.toLowerCase();
    }

    const suffixes = new Set([
        'text',
        'label',
        'link',
        'url',
        'href',
        'image',
        'img',
        'icon',
        'title',
        'subtitle',
        'description',
        'body',
        'content',
        'style',
        'variant',
        'size',
        'color',
        'typography',
        'alignment',
        'align',
    ]);
    let end = tokenized.length;
    while (end > 1 && suffixes.has(tokenized[end - 1] ?? '')) {
        end -= 1;
    }

    return tokenized.slice(0, end).join('_') || tokenized.join('_');
}


function resolveComponentScopePath(
    path: string | null,
    scopeField: BuilderFieldDefinition | null,
): string | null {
    if (!path) {
        return null;
    }

    const normalizedPath = path.trim();
    if (normalizedPath === '') {
        return null;
    }

    const segments = splitSchemaFieldPath(normalizedPath);
    const isIndexedCompoundField = scopeField && ['menu', 'repeater', 'button-group'].includes(scopeField.type);
    if (scopeField?.itemFields && normalizedPath.startsWith(`${scopeField.path}.`)) {
        const scopeSegments = splitSchemaFieldPath(scopeField.path);
        const nextSegment = segments[scopeSegments.length];
        if (isNumericPathSegment(nextSegment)) {
            return [...scopeSegments, nextSegment as string].join('.');
        }

        return scopeField.path;
    }

    if (isIndexedCompoundField && normalizedPath.startsWith(`${scopeField.path}.`)) {
        const scopeSegments = splitSchemaFieldPath(scopeField.path);
        const nextSegment = segments[scopeSegments.length];
        if (isNumericPathSegment(nextSegment)) {
            return [...scopeSegments, nextSegment as string].join('.');
        }

        return scopeField.path;
    }

    if (segments.length > 1) {
        return segments.slice(0, -1).join('.');
    }

    return normalizedPath;
}

function resolveFieldMeta(sectionKey: string | null, path: string | null): {
    fieldLabel: string | null;
    fieldGroup: BuilderFieldGroup | null;
    editableFields: string[];
    matchedField: BuilderFieldDefinition | null;
    scopeField: BuilderFieldDefinition | null;
    componentPath: string | null;
    schema: BuilderComponentSchema | null;
} {
    if (!sectionKey) {
        return {
            fieldLabel: null,
            fieldGroup: null,
            editableFields: [],
            matchedField: null,
            scopeField: null,
            componentPath: null,
            schema: null,
        };
    }

    let schema = getComponentSchema(sectionKey) as unknown as BuilderComponentSchema | null;
    if (!schema && sectionKey) {
        const entry = getEntry(sectionKey);
        if (entry?.schema && typeof entry.schema === 'object') {
            schema = entry.schema as unknown as BuilderComponentSchema;
        }
    }
    if (schema && Array.isArray((schema as unknown as Record<string, unknown>).editableFields) && !schema.fields) {
        const editableFields = (schema as unknown as Record<string, unknown>).editableFields as Array<{ key: string; type?: string }>;
        schema = {
            ...schema,
            fields: editableFields.map((f) => ({ path: f.key, type: (f.type ?? 'text') as BuilderFieldType, label: f.key })),
        } as BuilderComponentSchema;
    }
    const matchedMeta = resolveSchemaMatchedField(schema, path);
    const rawEditable = schema?.editableFields ?? schema?.fields?.map((field) => field.path) ?? [];
    const editableFields: string[] = Array.isArray(rawEditable)
        ? rawEditable.map((f) => (typeof f === 'string' ? f : (f as { key?: string }).key ?? '')).filter(Boolean)
        : [];

    return {
        fieldLabel: matchedMeta.matchedField?.label ?? null,
        fieldGroup: matchedMeta.matchedField?.group ?? null,
        editableFields,
        matchedField: matchedMeta.matchedField,
        scopeField: matchedMeta.scopeField,
        componentPath: resolveComponentScopePath(path, matchedMeta.scopeField),
        schema,
    };
}

function readValueAtPath(input: Record<string, unknown> | null | undefined, path: string | null): unknown {
    if (!input || !path) {
        return null;
    }

    const segments = splitSchemaFieldPath(path);
    if (segments.length === 0) {
        return null;
    }

    let cursor: unknown = input;
    for (const segment of segments) {
        if (Array.isArray(cursor)) {
            const index = Number.parseInt(segment, 10);
            if (!Number.isFinite(index)) {
                return null;
            }
            cursor = cursor[index];
            continue;
        }

        if (!cursor || typeof cursor !== 'object') {
            return null;
        }
        cursor = (cursor as Record<string, unknown>)[segment];
    }

    return cursor ?? null;
}

function collectRuntimeNestedFieldPaths(value: unknown, prefix: string): string[] {
    if (prefix.trim() === '' || value === null || value === undefined) {
        return [];
    }

    if (Array.isArray(value)) {
        return value.flatMap((entry, index) => collectRuntimeNestedFieldPaths(entry, `${prefix}.${index}`));
    }

    if (typeof value === 'object') {
        return Object.entries(value as Record<string, unknown>).flatMap(([key, entry]) => collectRuntimeNestedFieldPaths(
            entry,
            `${prefix}.${key}`,
        ));
    }

    return [prefix];
}

function uniqueStringList(values: Array<string | null | undefined>): string[] {
    return Array.from(new Set(
        values
            .map((value) => (typeof value === 'string' ? value.trim() : ''))
            .filter(Boolean)
    ));
}

function normalizeBreakpoint(value: string | null | undefined): BuilderBreakpoint {
    return value === 'tablet' || value === 'mobile' ? value : 'desktop';
}

function normalizeInteractionState(value: string | null | undefined): BuilderInteractionState {
    return value === 'hover' || value === 'focus' || value === 'active' ? value : 'normal';
}

function resolveVariantState(
    schema: BuilderComponentSchema | null,
    props: Record<string, unknown> | null,
    kind: 'layout' | 'style'
): BuilderTargetVariantState | null {
    if (!schema) {
        return null;
    }

    const definition = schema.variantDefinitions?.find((entry) => entry.kind === kind) ?? null;
    if (!definition) {
        return null;
    }

    const candidatePaths = uniqueStringList([
        ...schema.fields
            .filter((field) => field.type === (kind === 'layout' ? 'layout-variant' : 'style-variant'))
            .map((field) => field.path),
        kind === 'layout' ? 'layoutVariant' : 'styleVariant',
        kind === 'layout' ? 'layout_variant' : 'style_variant',
    ]);
    const activePath = candidatePaths.find((path) => {
        const value = readValueAtPath(props, path);
        return typeof value === 'string' && value.trim() !== '';
    }) ?? candidatePaths[0] ?? null;
    const activeValue = activePath ? readValueAtPath(props, activePath) : null;

    return {
        kind,
        path: activePath,
        active: typeof activeValue === 'string' && activeValue.trim() !== ''
            ? activeValue.trim()
            : (definition.default ?? null),
        options: definition.options.map((option) => option.value),
    };
}

function resolveAllowedFieldPaths(
    schema: BuilderComponentSchema | null,
    matchedField: BuilderFieldDefinition | null,
    scopeField: BuilderFieldDefinition | null,
    componentPath: string | null,
    path: string | null,
    props: Record<string, unknown> | null,
): string[] {
    if (!schema) {
        return [];
    }

    if (!path) {
        return uniqueStringList(schema.editableFields ?? schema.fields.map((field) => field.path));
    }

    const normalizedPath = path.trim();
    if (normalizedPath === '') {
        return [];
    }

    const schemaFieldPaths = collectSchemaFieldPaths(schema);
    const aliasPaths = expandSchemaAwareAliasPaths(normalizedPath, schemaFieldPaths);
    const comparableAliasPaths = aliasPaths.map((candidate) => normalizeComparableSchemaFieldPath(candidate));
    const roots = new Set(aliasPaths.map((candidate) => splitSchemaFieldPath(candidate)[0] ?? '').filter(Boolean));
    const matchedType = matchedField?.type ?? scopeField?.type ?? null;
    const targetStems = new Set(aliasPaths.map((candidate) => deriveFieldStem(candidate)).filter(Boolean));
    const related = new Set<string>(aliasPaths);
    const normalizedComponentPath = componentPath?.trim() ?? '';

    if (normalizedComponentPath !== '' && scopeField?.itemFields && normalizedComponentPath.startsWith(`${scopeField.path}.`)) {
        scopeField.itemFields.forEach((field) => {
            const candidate = `${normalizedComponentPath}.${field.path}`.trim();
            if (candidate !== '') {
                related.add(candidate);
            }
        });
    }

    if (normalizedComponentPath !== '') {
        collectRuntimeNestedFieldPaths(readValueAtPath(props, normalizedComponentPath), normalizedComponentPath).forEach((candidate) => {
            if (candidate.trim() !== '') {
                related.add(candidate.trim());
            }
        });
    }

    if (
        normalizedComponentPath !== ''
        && scopeField?.itemFields
        && normalizedPath.startsWith(`${normalizedComponentPath}.`)
    ) {
        return Array.from(related);
    }

    if (
        normalizedComponentPath !== ''
        && ['menu', 'button-group', 'repeater'].includes(matchedType ?? '')
        && (normalizedPath === normalizedComponentPath || normalizedPath.startsWith(`${normalizedComponentPath}.`))
    ) {
        return Array.from(related);
    }

    schema.fields.forEach((field) => {
        const candidate = field.path.trim();
        if (candidate === '') {
            return;
        }

        const candidateAliasPaths = expandSchemaAwareAliasPaths(candidate, schemaFieldPaths);
        const comparableCandidatePaths = candidateAliasPaths.map((value) => normalizeComparableSchemaFieldPath(value));

        const isAliasRelated = aliasPaths.some((aliasPath) => candidateAliasPaths.some((candidateAlias) => isSchemaFieldPathRelated(candidateAlias, aliasPath)))
            || comparableAliasPaths.some((aliasPath) => comparableCandidatePaths.some((candidateComparablePath) => (
                aliasPath === candidateComparablePath
                || aliasPath.startsWith(`${candidateComparablePath}.`)
                || candidateComparablePath.startsWith(`${aliasPath}.`)
            )));
        if (isAliasRelated) {
            related.add(candidate);
            return;
        }

        if (isSchemaFieldPathRelated(candidate, normalizedPath)) {
            related.add(candidate);
            return;
        }

        if (normalizedComponentPath !== '' && (candidate === normalizedComponentPath || candidate.startsWith(`${normalizedComponentPath}.`))) {
            related.add(candidate);
            return;
        }

        if (
            normalizedComponentPath !== ''
            && normalizeComparableSchemaFieldPath(candidate) === normalizeComparableSchemaFieldPath(normalizedComponentPath)
        ) {
            related.add(candidate);
            return;
        }

        if (
            roots.size > 0
            && (
                ['menu', 'button-group', 'repeater', 'link', 'image', 'video', 'icon'].includes(matchedType ?? '')
                || Array.from(roots).some((root) => /(button|cta|link|menu|image|logo|icon)/i.test(root))
            )
            && roots.has(candidate.split('.')[0] ?? '')
        ) {
            related.add(candidate);
        }

        if (
            targetStems.size > 0
            && ['text', 'link', 'richtext'].includes(matchedType ?? '')
            && targetStems.has(deriveFieldStem(candidate))
        ) {
            related.add(candidate);
        }

        if (field.type === 'layout-variant' || field.type === 'style-variant') {
            related.add(candidate);
        }
    });

    if (!normalizedPath.includes('.')) {
        const typographyCompanion = `${normalizedPath}_typography`;
        if (schema.fields.some((field) => field.path === typographyCompanion)) {
            related.add(typographyCompanion);
        }
    }

    return Array.from(related);
}

function resolveResponsiveContext(
    schema: BuilderComponentSchema | null,
    breakpoint: BuilderBreakpoint,
    interactionState: BuilderInteractionState
): BuilderTargetResponsiveContext | null {
    if (!schema) {
        return null;
    }

    const responsiveSupport = schema.responsiveSupport ?? null;
    const availableBreakpoints = (responsiveSupport?.breakpoints?.length
        ? responsiveSupport.breakpoints
        : ['desktop', 'tablet', 'mobile']) as BuilderBreakpoint[];
    const availableInteractionStates = (responsiveSupport?.interactionStates?.length
        ? responsiveSupport.interactionStates
        : ['normal']) as BuilderInteractionState[];

    const visibleFieldPaths = schema.fields
        .filter((field) => {
            if (field.path.startsWith('responsive.desktop.') || field.path.startsWith('responsive.tablet.') || field.path.startsWith('responsive.mobile.')) {
                return field.path.startsWith(`responsive.${breakpoint}.`);
            }
            if (field.path.startsWith('states.hover.') || field.path.startsWith('states.focus.') || field.path.startsWith('states.active.')) {
                return interactionState !== 'normal' && field.path.startsWith(`states.${interactionState}.`);
            }

            return true;
        })
        .map((field) => field.path);

    const responsiveFieldPaths = schema.fields
        .filter((field) => (
            field.path.startsWith(`responsive.${breakpoint}.`)
            || field.path.startsWith('responsive.hide_on_')
        ))
        .map((field) => field.path);

    const stateFieldPaths = interactionState === 'normal'
        ? []
        : schema.fields
            .filter((field) => field.path.startsWith(`states.${interactionState}.`))
            .map((field) => field.path);

    return {
        currentBreakpoint: breakpoint,
        currentInteractionState: interactionState,
        availableBreakpoints,
        availableInteractionStates,
        supportsVisibility: Boolean(responsiveSupport?.supportsVisibility),
        supportsResponsiveOverrides: Boolean(responsiveSupport?.supportsResponsiveOverrides),
        visibleFieldPaths,
        responsiveFieldPaths,
        stateFieldPaths,
    };
}

function resolveAllowedOperationTypes(fieldType: BuilderFieldType | null, path: string | null): string[] {
    if (!path) {
        return ['updateSection', 'updateText', 'replaceImage', 'updateButton'];
    }

    switch (fieldType) {
        case 'image':
        case 'video':
            return ['replaceImage', 'updateSection'];
        case 'link':
        case 'menu':
        case 'button-group':
        case 'repeater':
            return ['updateButton', 'updateSection', 'updateText'];
        case 'color':
        case 'number':
        case 'boolean':
        case 'typography':
        case 'width':
        case 'height':
        case 'alignment':
        case 'spacing':
        case 'radius':
        case 'shadow':
        case 'overlay':
        case 'visibility':
        case 'select':
        case 'layout-variant':
        case 'style-variant':
            return ['updateSection'];
        case 'icon':
        case 'richtext':
        case 'text':
        default:
            return ['updateText', 'updateSection'];
    }
}

function resolveTargetScopeMeta(
    sectionKey: string | null,
    path: string | null,
    props: Record<string, unknown> | null,
    breakpoint: BuilderBreakpoint,
    interactionState: BuilderInteractionState,
): Pick<BuilderEditableTarget, 'componentPath' | 'variants' | 'allowedUpdates' | 'responsiveContext'> {
    const fieldMeta = resolveFieldMeta(sectionKey, path);
    const schema = fieldMeta.schema;
    const exactFieldPaths = resolveAllowedFieldPaths(
        schema,
        fieldMeta.matchedField,
        fieldMeta.scopeField,
        fieldMeta.componentPath,
        path,
        props,
    );
    const sectionFieldPaths = uniqueStringList(fieldMeta.editableFields);
    const exactOps = uniqueStringList(resolveAllowedOperationTypes(fieldMeta.matchedField?.type ?? null, path));
    const sectionOps = uniqueStringList([
        ...resolveAllowedOperationTypes(null, null),
        ...exactOps,
    ]);

    return {
        componentPath: fieldMeta.componentPath,
        variants: schema
            ? {
                layout: resolveVariantState(schema, props, 'layout'),
                style: resolveVariantState(schema, props, 'style'),
            }
            : null,
        responsiveContext: resolveResponsiveContext(schema, breakpoint, interactionState),
        allowedUpdates: {
            scope: path ? 'element' : 'section',
            operationTypes: exactOps,
            fieldPaths: exactFieldPaths,
            sectionOperationTypes: sectionOps,
            sectionFieldPaths,
        },
    };
}

export function builderFieldGroupToSidebarTab(group: BuilderFieldGroup | null | undefined): BuilderSidebarTab {
    switch (group) {
        case 'layout':
            return 'layout';
        case 'style':
        case 'responsive':
        case 'state':
        case 'states':
            return 'style';
        case 'advanced':
        case 'meta':
            return 'advanced';
        case 'content':
        case 'data':
        case 'bindings':
        default:
            return 'content';
    }
}

export function buildEditableTargetFromSection(section: BuilderSection | null): BuilderEditableTarget | null {
    if (!section) {
        return null;
    }

    const componentType = section.type?.trim() || null;
    const componentName = componentType ? getComponentShortName(componentType) : null;
    const props = isRecord(section.props) ? section.props : parseSectionProps(section.propsText);
    const fieldMeta = resolveFieldMeta(componentType, null);
    const scopeMeta = resolveTargetScopeMeta(componentType, null, props, 'desktop', 'normal');

    return {
        targetId: buildTargetId(section.localId, componentType, null),
        pageId: null,
        pageSlug: null,
        pageTitle: null,
        sectionLocalId: section.localId,
        sectionKey: componentType,
        componentType,
        componentName,
        path: null,
        componentPath: null,
        elementId: null,
        selector: section.localId ? `[data-webu-section-local-id="${section.localId.replace(/"/g, '\\"')}"]` : null,
        textPreview: null,
        props,
        fieldLabel: null,
        fieldGroup: null,
        builderId: section.localId,
        parentId: null,
        editableFields: fieldMeta.editableFields,
        sectionId: section.localId,
        instanceId: section.localId,
        variants: scopeMeta.variants,
        allowedUpdates: scopeMeta.allowedUpdates,
    };
}

export function buildSectionScopedEditableTarget(
    payload: BuilderSelectionMessagePayload | null | undefined
): BuilderEditableTarget | null {
    if (!payload) {
        return null;
    }

    const sectionLocalId = typeof payload.sectionLocalId === 'string' && payload.sectionLocalId.trim() !== ''
        ? payload.sectionLocalId.trim()
        : null;
    const sectionKey = typeof payload.sectionKey === 'string' && payload.sectionKey.trim() !== ''
        ? payload.sectionKey.trim()
        : null;
    const componentType = typeof payload.componentType === 'string' && payload.componentType.trim() !== ''
        ? payload.componentType.trim()
        : sectionKey;

    if (!sectionLocalId && !sectionKey && !componentType) {
        return null;
    }

    return buildEditableTargetFromMessagePayload({
        pageId: payload.pageId ?? null,
        pageSlug: payload.pageSlug ?? null,
        pageTitle: payload.pageTitle ?? null,
        sectionLocalId,
        sectionKey,
        componentType,
        componentName: payload.componentName ?? null,
        selector: sectionLocalId
            ? `[data-webu-section-local-id="${sectionLocalId.replace(/"/g, '\\"')}"]`
            : sectionKey
                ? `[data-webu-section="${sectionKey.replace(/"/g, '\\"')}"]`
                : null,
        textPreview: payload.textPreview ?? null,
        props: payload.props ?? null,
        builderId: sectionLocalId ?? null,
        parentId: null,
        sectionId: sectionLocalId ?? null,
        instanceId: sectionLocalId ?? null,
        currentBreakpoint: payload.currentBreakpoint ?? null,
        currentInteractionState: payload.currentInteractionState ?? null,
    });
}

export function buildEditableTargetFromMention(
    mention: ElementMention | null,
    sectionProps: Record<string, unknown> | null = null,
    options?: {
        currentBreakpoint?: BuilderBreakpoint | null;
        currentInteractionState?: BuilderInteractionState | null;
    },
): BuilderEditableTarget | null {
    if (!mention) {
        return null;
    }

    const componentType = typeof mention.sectionKey === 'string' && mention.sectionKey.trim() !== ''
        ? mention.sectionKey.trim()
        : null;
    const componentName = componentType ? getComponentShortName(componentType) : null;
    const path = typeof mention.parameterName === 'string' && mention.parameterName.trim() !== ''
        ? mention.parameterName.trim()
        : null;
    const fieldMeta = resolveFieldMeta(componentType, path);
    const scopeMeta = resolveTargetScopeMeta(
        componentType,
        path,
        sectionProps,
        normalizeBreakpoint(options?.currentBreakpoint ?? null),
        normalizeInteractionState(options?.currentInteractionState ?? null),
    );

    return {
        targetId: buildTargetId(mention.sectionLocalId ?? null, componentType, path),
        pageId: null,
        pageSlug: null,
        pageTitle: null,
        sectionLocalId: mention.sectionLocalId ?? null,
        sectionKey: componentType,
        componentType,
        componentName,
        path,
        componentPath: resolvePreferredComponentPath(path, [mention.componentPath, scopeMeta.componentPath, path]),
        elementId: mention.elementId ?? null,
        selector: mention.selector ?? null,
        textPreview: mention.textPreview ?? null,
        props: sectionProps,
        fieldLabel: fieldMeta.fieldLabel,
        fieldGroup: fieldMeta.fieldGroup,
        builderId: mention.id ?? null,
        parentId: mention.sectionLocalId ?? null,
        editableFields: fieldMeta.editableFields,
        sectionId: mention.sectionLocalId ?? null,
        instanceId: mention.id ?? null,
        variants: scopeMeta.variants,
        allowedUpdates: scopeMeta.allowedUpdates,
        responsiveContext: scopeMeta.responsiveContext,
    };
}

export function buildEditableTargetFromMessagePayload(
    payload: BuilderSelectionMessagePayload | null | undefined
): BuilderEditableTarget | null {
    if (!payload) {
        return null;
    }

    const sectionLocalId = typeof payload.sectionLocalId === 'string' && payload.sectionLocalId.trim() !== ''
        ? payload.sectionLocalId.trim()
        : null;
    const pageId = typeof payload.pageId === 'number' && Number.isFinite(payload.pageId)
        ? payload.pageId
        : null;
    const pageSlug = typeof payload.pageSlug === 'string' && payload.pageSlug.trim() !== ''
        ? payload.pageSlug.trim()
        : null;
    const pageTitle = typeof payload.pageTitle === 'string' && payload.pageTitle.trim() !== ''
        ? payload.pageTitle.trim()
        : null;
    const sectionKey = typeof payload.sectionKey === 'string' && payload.sectionKey.trim() !== ''
        ? payload.sectionKey.trim()
        : null;
    const componentType = typeof payload.componentType === 'string' && payload.componentType.trim() !== ''
        ? payload.componentType.trim()
        : sectionKey;
    const path = typeof payload.parameterPath === 'string' && payload.parameterPath.trim() !== ''
        ? payload.parameterPath.trim()
        : null;
    const componentName = typeof payload.componentName === 'string' && payload.componentName.trim() !== ''
        ? payload.componentName.trim()
        : (componentType ? getComponentShortName(componentType) : null);
    const props = payload.props ?? null;
    const fieldMeta = resolveFieldMeta(sectionKey ?? componentType, path);
    const scopeMeta = resolveTargetScopeMeta(
        sectionKey ?? componentType,
        path,
        props,
        normalizeBreakpoint(payload.currentBreakpoint ?? null),
        normalizeInteractionState(payload.currentInteractionState ?? null),
    );

    if (!sectionLocalId && !sectionKey && !componentType) {
        return null;
    }

    return {
        targetId: buildTargetId(sectionLocalId, sectionKey ?? componentType, path),
        pageId,
        pageSlug,
        pageTitle,
        sectionLocalId,
        sectionKey: sectionKey ?? componentType,
        componentType,
        componentName,
        path,
        componentPath: resolvePreferredComponentPath(path, [
            typeof payload.componentPath === 'string' && payload.componentPath.trim() !== ''
                ? payload.componentPath.trim()
                : null,
            scopeMeta.componentPath,
            path,
        ]),
        elementId: typeof payload.elementId === 'string' && payload.elementId.trim() !== '' ? payload.elementId.trim() : null,
        selector: typeof payload.selector === 'string' && payload.selector.trim() !== '' ? payload.selector.trim() : null,
        textPreview: typeof payload.textPreview === 'string' && payload.textPreview.trim() !== '' ? payload.textPreview.trim() : null,
        props,
        fieldLabel: typeof payload.fieldLabel === 'string' && payload.fieldLabel.trim() !== ''
            ? payload.fieldLabel.trim()
            : fieldMeta.fieldLabel,
        fieldGroup: payload.fieldGroup ?? fieldMeta.fieldGroup,
        builderId: typeof payload.builderId === 'string' && payload.builderId.trim() !== '' ? payload.builderId.trim() : null,
        parentId: typeof payload.parentId === 'string' && payload.parentId.trim() !== '' ? payload.parentId.trim() : null,
        editableFields: Array.isArray(payload.editableFields)
            ? payload.editableFields.map((field) => String(field).trim()).filter(Boolean)
            : fieldMeta.editableFields,
        sectionId: typeof payload.sectionId === 'string' && payload.sectionId.trim() !== '' ? payload.sectionId.trim() : sectionLocalId,
        instanceId: typeof payload.instanceId === 'string' && payload.instanceId.trim() !== '' ? payload.instanceId.trim() : sectionLocalId,
        variants: payload.variants ?? scopeMeta.variants,
        allowedUpdates: payload.allowedUpdates ?? scopeMeta.allowedUpdates,
        responsiveContext: payload.responsiveContext ?? scopeMeta.responsiveContext,
    };
}

export function editableTargetToMention(target: BuilderEditableTarget | null): ElementMention | null {
    if (!target) {
        return null;
    }

    const selector = target.selector
        ?? (target.sectionLocalId
            ? `[data-webu-section-local-id="${target.sectionLocalId.replace(/"/g, '\\"')}"]`
            : target.sectionKey
                ? `[data-webu-section="${target.sectionKey.replace(/"/g, '\\"')}"]`
                : '');

    if (!selector) {
        return null;
    }

    return {
        id: target.targetId,
        tagName: target.path ? 'div' : 'section',
        selector,
        textPreview: target.textPreview ?? '',
        sectionKey: target.sectionKey,
        sectionLocalId: target.sectionLocalId,
        parameterName: target.path,
        componentPath: target.componentPath ?? null,
        elementId: target.elementId,
    };
}

export function editableTargetToMessagePayload(target: BuilderEditableTarget | null): BuilderSelectionMessagePayload | null {
    if (!target) {
        return null;
    }

    return {
        pageId: target.pageId ?? null,
        pageSlug: target.pageSlug ?? null,
        pageTitle: target.pageTitle ?? null,
        sectionLocalId: target.sectionLocalId,
        sectionKey: target.sectionKey,
        componentType: target.componentType,
        componentName: target.componentName,
        parameterPath: target.path,
        componentPath: target.componentPath ?? target.path,
        elementId: target.elementId,
        selector: target.selector,
        textPreview: target.textPreview,
        props: target.props,
        fieldLabel: target.fieldLabel ?? null,
        fieldGroup: target.fieldGroup ?? null,
        builderId: target.builderId ?? null,
        parentId: target.parentId ?? null,
        editableFields: target.editableFields,
        sectionId: target.sectionId ?? null,
        instanceId: target.instanceId ?? null,
        variants: target.variants ?? null,
        allowedUpdates: target.allowedUpdates ?? null,
        currentBreakpoint: target.responsiveContext?.currentBreakpoint ?? null,
        currentInteractionState: target.responsiveContext?.currentInteractionState ?? null,
        responsiveContext: target.responsiveContext ?? null,
    };
}

export function buildBuilderSelectionMessageSignature(
    payload: BuilderSelectionMessagePayload | null | undefined
): string {
    if (!payload) {
        return 'null';
    }

    return JSON.stringify({
        pageId: payload.pageId ?? null,
        pageSlug: payload.pageSlug ?? null,
        pageTitle: payload.pageTitle ?? null,
        sectionLocalId: payload.sectionLocalId ?? null,
        sectionKey: payload.sectionKey ?? null,
        componentType: payload.componentType ?? null,
        componentName: payload.componentName ?? null,
        parameterPath: payload.parameterPath ?? null,
        componentPath: payload.componentPath ?? null,
        elementId: payload.elementId ?? null,
        selector: payload.selector ?? null,
        fieldLabel: payload.fieldLabel ?? null,
        fieldGroup: payload.fieldGroup ?? null,
        builderId: payload.builderId ?? null,
        parentId: payload.parentId ?? null,
        editableFields: payload.editableFields ?? null,
        sectionId: payload.sectionId ?? null,
        instanceId: payload.instanceId ?? null,
        variants: payload.variants ?? null,
        allowedUpdates: payload.allowedUpdates ?? null,
        currentBreakpoint: payload.currentBreakpoint ?? null,
        currentInteractionState: payload.currentInteractionState ?? null,
        responsiveContext: payload.responsiveContext ?? null,
    });
}

export function areBuilderEditableTargetsEqual(
    left: BuilderEditableTarget | null,
    right: BuilderEditableTarget | null
): boolean {
    if (left === right) {
        return true;
    }

    return buildBuilderEditableTargetSelectionSignature(left) === buildBuilderEditableTargetSelectionSignature(right);
}

export function buildBuilderEditableTargetSelectionSignature(target: BuilderEditableTarget | null): string {
    return buildBuilderSelectionMessageSignature(editableTargetToMessagePayload(target));
}
