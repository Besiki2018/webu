import { getComponentSchema, resolveComponentProps } from '@/builder/componentRegistry';
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
    collectFallbackSchemaFields: (
        properties: Record<string, unknown>,
        currentValues?: unknown,
    ) => SelectedSectionInspectorField<TControlMeta>[];
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
    usesEcommerceProductsBinding: boolean;
    usesEcommerceProductDetailBinding: boolean;
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
            usesEcommerceProductsBinding: false,
            usesEcommerceProductDetailBinding: false,
        };
    }

    const typeToUse = options.selectedSectionEffectiveType || options.selectedSectionDraft.type;
    const normalizedType = options.normalizeSectionTypeKey(typeToUse);
    const componentSchema = getComponentSchema(typeToUse)
        ?? getComponentSchema(options.selectedSectionDraft.type)
        ?? getComponentSchema(normalizedType)
        ?? getComponentSchema(options.normalizeSectionTypeKey(options.selectedSectionDraft.type));

    const resolvedProps = resolveComponentProps(typeToUse, options.selectedSectionEffectiveParsedProps ?? {});
    const schemaFields = componentSchema
        ? collectBuilderSchemaPrimitiveFieldDescriptors(componentSchema, {
            values: resolvedProps ?? options.selectedSectionEffectiveParsedProps,
        }).map((field) => ({
            ...field,
            control_meta: options.buildControlMeta(field.path, field.type, field.label, field.definition),
        }))
        : options.selectedSectionSchemaProperties
            ? options.collectFallbackSchemaFields(options.selectedSectionSchemaProperties, options.selectedSectionEffectiveParsedProps)
            : [];

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

    const inspectorTarget = options.selectedBuilderTarget?.path
        && options.selectedBuilderTarget.sectionLocalId === options.selectedSectionDraft.localId
        ? options.selectedBuilderTarget
        : null;
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
        usesEcommerceProductsBinding,
        usesEcommerceProductDetailBinding,
    };
}
