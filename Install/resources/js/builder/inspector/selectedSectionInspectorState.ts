import {
    getComponentRuntimeEntry,
    getComponentSchema,
    resolveComponentProps,
    type BuilderComponentSchema,
} from '@/builder/componentRegistry';
import type {
    BuilderBreakpoint,
    BuilderEditableTarget,
    BuilderInteractionState,
} from '@/builder/editingState';
import { collectBuilderSchemaPrimitiveFieldDescriptors, type SchemaPrimitiveScalarType } from '@/lib/schemaPrimitiveFields';
import { filterInspectorSchemaFields, type InspectorFieldControlMeta } from './filterInspectorSchemaFields';

export interface SelectedSectionInspectorDraft {
    localId: string;
    type: string;
}

export interface SelectedSectionInspectorField<TControlMeta extends InspectorFieldControlMeta> {
    path: string[];
    type: SchemaPrimitiveScalarType;
    label: string;
    definition: Record<string, unknown>;
    control_meta: TControlMeta;
}

interface BuildSelectedSectionInspectorStateOptions<TControlMeta extends InspectorFieldControlMeta> {
    selectedSectionDraft: SelectedSectionInspectorDraft | null;
    selectedSectionEffectiveType: string | null;
    selectedSectionEffectiveParsedProps: Record<string, unknown> | null;
    selectedSectionSchemaProperties: Record<string, unknown> | null;
    selectedSectionSchemaHtmlTemplate?: string;
    selectedBuilderTarget: BuilderEditableTarget | null;
    previewMode: BuilderBreakpoint;
    interactionState: BuilderInteractionState;
    elementorLike?: boolean;
    normalizeSectionTypeKey: (value: string) => string;
    buildControlMeta: (
        path: string[],
        type: SchemaPrimitiveScalarType,
        label: string,
        definition: Record<string, unknown>,
    ) => TControlMeta;
}

export interface SelectedSectionInspectorState<TControlMeta extends InspectorFieldControlMeta> {
    resolvedProps: Record<string, unknown> | null;
    schemaFields: SelectedSectionInspectorField<TControlMeta>[];
    editableSchemaFields: SelectedSectionInspectorField<TControlMeta>[];
    editableSchemaFieldsForDisplay: SelectedSectionInspectorField<TControlMeta>[];
    inspectorTarget: BuilderEditableTarget | null;
    schema: BuilderComponentSchema | null;
    schemaKey: string | null;
    schemaFieldPaths: string[];
    inspectorPropPaths: string[];
    defaultProps: Record<string, unknown> | null;
    nodeId: string | null;
    usesSafeFallbackInspector: boolean;
    usesEcommerceProductsBinding: boolean;
    usesEcommerceProductDetailBinding: boolean;
}

function normalizeInspectorPath(path: string[] | string | null | undefined): string {
    if (Array.isArray(path)) {
        return path.map((segment) => String(segment).trim()).filter(Boolean).join('.');
    }

    return String(path ?? '')
        .split('.')
        .map((segment) => segment.trim())
        .filter(Boolean)
        .join('.');
}

function isPathSchemaRelated(candidate: string, schemaPath: string): boolean {
    return candidate === schemaPath
        || candidate.startsWith(`${schemaPath}.`)
        || schemaPath.startsWith(`${candidate}.`);
}

function collectInspectorPropPaths(target: BuilderEditableTarget | null): string[] {
    if (!target) {
        return [];
    }

    const scopedFieldPaths = target.path
        ? (target.allowedUpdates?.fieldPaths ?? [])
        : [
            ...(target.allowedUpdates?.sectionFieldPaths ?? []),
            ...(target.editableFields ?? []),
        ];

    return Array.from(new Set([
        target.path,
        target.componentPath ?? null,
        ...scopedFieldPaths,
    ]
        .map((value) => normalizeInspectorPath(value))
        .filter(Boolean)));
}

function resolveSchemaBackedInspectorTarget(
    target: BuilderEditableTarget | null,
    sectionLocalId: string,
    schemaFieldPaths: string[],
): {
    inspectorTarget: BuilderEditableTarget | null;
    inspectorPropPaths: string[];
} {
    if (!target?.path || target.sectionLocalId !== sectionLocalId) {
        return {
            inspectorTarget: null,
            inspectorPropPaths: [],
        };
    }

    if (schemaFieldPaths.length === 0) {
        return {
            inspectorTarget: null,
            inspectorPropPaths: [],
        };
    }

    const inspectorPropPaths = collectInspectorPropPaths(target);
    const hasSchemaMatch = inspectorPropPaths.some((path) => (
        schemaFieldPaths.some((schemaPath) => isPathSchemaRelated(path, schemaPath))
    ));

    return {
        inspectorTarget: hasSchemaMatch ? target : null,
        inspectorPropPaths: hasSchemaMatch ? inspectorPropPaths : [],
    };
}

function filterEcommerceBoundSchemaFields<TControlMeta extends InspectorFieldControlMeta>(
    fields: SelectedSectionInspectorField<TControlMeta>[],
    options: {
        usesProductsBinding: boolean;
        usesProductDetailBinding: boolean;
    },
): SelectedSectionInspectorField<TControlMeta>[] {
    if (fields.length === 0) {
        return fields;
    }

    if (!options.usesProductsBinding && !options.usesProductDetailBinding) {
        return fields;
    }

    return fields.filter((field) => {
        const rootKey = String(field.path[0] ?? '').trim().toLowerCase();
        if (rootKey === '') {
            return false;
        }

        if (/^(link|heading|paragraph|image|button)_\d+$/.test(rootKey)) {
            return false;
        }

        if (options.usesProductsBinding) {
            return !['name', 'price', 'compare_at_price', 'sku', 'short_description', 'description'].includes(rootKey);
        }

        if (options.usesProductDetailBinding) {
            return ['product_id', 'product_slug', 'variant_id', 'collection', 'title', 'headline', 'subtitle'].includes(rootKey);
        }

        return true;
    });
}

export function buildSelectedSectionInspectorState<TControlMeta extends InspectorFieldControlMeta>(
    options: BuildSelectedSectionInspectorStateOptions<TControlMeta>,
): SelectedSectionInspectorState<TControlMeta> {
    if (!options.selectedSectionDraft) {
        return {
            resolvedProps: null,
            schemaFields: [],
            editableSchemaFields: [],
            editableSchemaFieldsForDisplay: [],
            inspectorTarget: null,
            schema: null,
            schemaKey: null,
            schemaFieldPaths: [],
            inspectorPropPaths: [],
            defaultProps: null,
            nodeId: null,
            usesSafeFallbackInspector: false,
            usesEcommerceProductsBinding: false,
            usesEcommerceProductDetailBinding: false,
        };
    }

    const typeToUse = options.selectedSectionEffectiveType || options.selectedSectionDraft.type;
    const normalizedType = options.normalizeSectionTypeKey(typeToUse);
    const runtimeEntry = getComponentRuntimeEntry(typeToUse)
        ?? getComponentRuntimeEntry(options.selectedSectionDraft.type)
        ?? getComponentRuntimeEntry(normalizedType)
        ?? getComponentRuntimeEntry(options.normalizeSectionTypeKey(options.selectedSectionDraft.type));
    const componentSchema = runtimeEntry?.schema
        ?? getComponentSchema(typeToUse)
        ?? getComponentSchema(options.selectedSectionDraft.type)
        ?? getComponentSchema(normalizedType)
        ?? getComponentSchema(options.normalizeSectionTypeKey(options.selectedSectionDraft.type));
    const schemaKey = runtimeEntry?.componentKey
        ?? componentSchema?.componentKey
        ?? null;
    const resolvedProps = componentSchema
        ? resolveComponentProps(schemaKey ?? typeToUse, options.selectedSectionEffectiveParsedProps ?? {})
        : (options.selectedSectionEffectiveParsedProps ?? null);
    const schemaFields = componentSchema
        ? collectBuilderSchemaPrimitiveFieldDescriptors(componentSchema, {
            values: resolvedProps ?? options.selectedSectionEffectiveParsedProps,
        }).map((field) => ({
            ...field,
            control_meta: options.buildControlMeta(field.path, field.type, field.label, field.definition),
        }))
        : [];
    const schemaFieldPaths = schemaFields
        .map((field) => normalizeInspectorPath(field.path))
        .filter(Boolean);

    const selectedSectionKey = options.normalizeSectionTypeKey(options.selectedSectionDraft.type);
    const selectedSectionSchemaHtmlTemplate = options.selectedSectionSchemaHtmlTemplate ?? '';
    const usesEcommerceProductsBinding = selectedSectionKey === 'webu_product_grid_01'
        || selectedSectionKey === 'webu_ecom_product_carousel_01'
        || selectedSectionSchemaHtmlTemplate.includes('data-webby-ecommerce-products')
        || selectedSectionSchemaHtmlTemplate.includes('data-webby-ecommerce-product-carousel');
    const usesEcommerceProductDetailBinding = selectedSectionKey === 'webu_product_card_01';

    const editableSchemaFields = filterEcommerceBoundSchemaFields(schemaFields, {
        usesProductsBinding: usesEcommerceProductsBinding,
        usesProductDetailBinding: usesEcommerceProductDetailBinding,
    });

    const { inspectorTarget, inspectorPropPaths } = resolveSchemaBackedInspectorTarget(
        options.selectedBuilderTarget,
        options.selectedSectionDraft.localId,
        schemaFieldPaths,
    );
    const editableSchemaFieldsForDisplay = filterInspectorSchemaFields(editableSchemaFields, {
        previewMode: options.previewMode,
        interactionState: options.interactionState,
        targetPath: inspectorTarget?.path ?? null,
        targetComponentPath: inspectorTarget?.componentPath ?? null,
        targetEditableFields: inspectorTarget?.allowedUpdates?.fieldPaths ?? inspectorTarget?.editableFields ?? [],
        elementorLike: options.elementorLike,
    });

    return {
        resolvedProps,
        schemaFields,
        editableSchemaFields,
        editableSchemaFieldsForDisplay,
        inspectorTarget,
        schema: componentSchema,
        schemaKey,
        schemaFieldPaths,
        inspectorPropPaths,
        defaultProps: componentSchema ? { ...componentSchema.defaultProps } : null,
        nodeId: inspectorTarget?.targetId ?? options.selectedSectionDraft.localId,
        usesSafeFallbackInspector: !componentSchema && Boolean(options.selectedSectionSchemaProperties),
        usesEcommerceProductsBinding,
        usesEcommerceProductDetailBinding,
    };
}
