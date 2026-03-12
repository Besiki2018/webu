import type { BuilderComponentSchema, BuilderFieldDefinition, BuilderFieldType } from '@/builder/componentRegistry';

export type SchemaPrimitiveScalarType = 'string' | 'number' | 'integer' | 'boolean';

export interface SchemaPrimitiveFieldDescriptor {
    path: string[];
    type: SchemaPrimitiveScalarType;
    label: string;
    definition: Record<string, unknown>;
}

interface CollectSchemaPrimitiveFieldOptions {
    values?: unknown;
}

interface CollectBuilderSchemaPrimitiveFieldOptions {
    values?: unknown;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function humanizePropertyKey(value: string): string {
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (match) => match.toUpperCase());
}

function schemaType(value: unknown): string | null {
    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        return normalized === '' ? null : normalized;
    }

    if (Array.isArray(value)) {
        const normalized = value
            .map((entry) => (typeof entry === 'string' ? entry.trim().toLowerCase() : ''))
            .find(Boolean);
        return normalized || null;
    }

    return null;
}

function normalizeLabel(raw: string | null | undefined, fallback: string): string {
    const trimmed = typeof raw === 'string' ? raw.trim() : '';
    return trimmed === '' ? fallback : trimmed;
}

function formatFieldLabel(baseLabel: string, contextSegments: string[]): string {
    const normalizedContext = contextSegments
        .map((segment) => segment.trim())
        .filter(Boolean);

    return normalizedContext.length === 0
        ? baseLabel
        : `${normalizedContext.join(' / ')} / ${baseLabel}`;
}

function isPrimitiveSchemaType(value: string | null): value is SchemaPrimitiveScalarType {
    return value === 'string' || value === 'number' || value === 'integer' || value === 'boolean';
}

function buildPrototypeValueFromDefinition(definition: Record<string, unknown>): unknown {
    const fieldType = schemaType(definition.type);
    const nestedProperties = isRecord(definition.properties) ? definition.properties : null;
    const itemsDefinition = isRecord(definition.items) ? definition.items : null;

    if ((fieldType === 'object' || (!fieldType && nestedProperties)) && nestedProperties) {
        return Object.fromEntries(
            Object.entries(nestedProperties).map(([key, rawDefinition]) => {
                if (!isRecord(rawDefinition)) {
                    return [key, undefined];
                }

                return [key, buildPrototypeValueFromDefinition(rawDefinition)];
            }),
        );
    }

    if (fieldType === 'array' && itemsDefinition) {
        const itemPrototype = buildPrototypeValueFromDefinition(itemsDefinition);
        return itemPrototype === undefined ? [] : [itemPrototype];
    }

    if (!isPrimitiveSchemaType(fieldType)) {
        return definition.default;
    }

    if (definition.default !== undefined) {
        return definition.default;
    }

    switch (fieldType) {
        case 'number':
        case 'integer':
            return 0;
        case 'boolean':
            return false;
        case 'string':
        default:
            return '';
    }
}

function cloneValue<T>(value: T): T {
    if (Array.isArray(value)) {
        return value.map((entry) => cloneValue(entry)) as T;
    }

    if (isRecord(value)) {
        return Object.fromEntries(
            Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]),
        ) as T;
    }

    return value;
}

function splitPath(path: string): string[] {
    return path
        .split('.')
        .map((segment) => segment.trim())
        .filter(Boolean);
}

function getValueAtRelativePath(input: unknown, path: string[]): unknown {
    if (path.length === 0) {
        return input;
    }

    let cursor: unknown = input;
    for (const segment of path) {
        if (Array.isArray(cursor)) {
            const index = Number.parseInt(segment, 10);
            if (!Number.isFinite(index)) {
                return undefined;
            }
            cursor = cursor[index];
            continue;
        }

        if (!isRecord(cursor)) {
            return undefined;
        }

        cursor = cursor[segment];
    }

    return cursor;
}

function parseStructuredValue(raw: unknown): unknown {
    if (typeof raw !== 'string') {
        return raw;
    }

    const trimmed = raw.trim();
    if (
        !((trimmed.startsWith('{') && trimmed.endsWith('}'))
            || (trimmed.startsWith('[') && trimmed.endsWith(']')))
    ) {
        return raw;
    }

    try {
        return JSON.parse(trimmed);
    } catch {
        return raw;
    }
}

function scalarTypeForBuilderField(type: BuilderFieldType): SchemaPrimitiveScalarType {
    switch (type) {
        case 'number':
            return 'number';
        case 'boolean':
        case 'visibility':
            return 'boolean';
        default:
            return 'string';
    }
}

function inferBuilderFieldTypeFromRuntimeLeaf(path: string[], value: unknown): BuilderFieldType {
    if (typeof value === 'number') {
        return 'number';
    }

    if (typeof value === 'boolean') {
        return 'boolean';
    }

    const probe = path.join('.').toLowerCase();
    if (/(image|img|photo|picture|logo|banner|thumbnail|avatar|cover)/.test(probe)) {
        return 'image';
    }

    if (/(icon|glyph|symbol)/.test(probe)) {
        return 'icon';
    }

    return 'text';
}

function serializeBuilderFieldDefinition(definition: BuilderFieldDefinition): Record<string, unknown> {
    return {
        type: scalarTypeForBuilderField(definition.type),
        title: definition.label,
        default: definition.default,
        enum: definition.options?.map((option) => option.value),
        description: definition.description,
        control_group: definition.group,
        group: definition.group,
        builder_field_type: definition.type,
        dynamic_capable: definition.bindingCompatible !== false,
        binding_compatible: definition.bindingCompatible !== false,
        responsive: definition.responsive === true,
        min: definition.min,
        max: definition.max,
        step: definition.step,
        units: definition.units,
        accepts: definition.accepts,
        project_types: definition.projectTypes,
        interaction_states: definition.states,
        item_fields: definition.itemFields?.map((fieldDefinition) => serializeBuilderFieldDefinition(fieldDefinition)),
    };
}

function buildDefaultPrimitiveValue(type: BuilderFieldType): unknown {
    switch (type) {
        case 'number':
            return 0;
        case 'boolean':
        case 'visibility':
            return false;
        default:
            return '';
    }
}

function buildMenuPrototypeItem(): Record<string, unknown> {
    return {
        label: '',
        url: '',
    };
}

function buildButtonGroupPrototypeItem(): Record<string, unknown> {
    return {
        label: '',
        url: '',
        variant: '',
    };
}

function buildPrototypeObjectFromFieldDefinitions(fieldDefinitions: BuilderFieldDefinition[]): Record<string, unknown> {
    const next: Record<string, unknown> = {};

    fieldDefinitions.forEach((fieldDefinition) => {
        const path = splitPath(fieldDefinition.path);
        if (path.length === 0) {
            return;
        }

        let cursor = next;
        path.forEach((segment, index) => {
            const isLeaf = index === path.length - 1;
            if (isLeaf) {
                cursor[segment] = buildPrototypeValueFromBuilderField(fieldDefinition);
                return;
            }

            const existing = cursor[segment];
            if (!isRecord(existing)) {
                cursor[segment] = {};
            }
            cursor = cursor[segment] as Record<string, unknown>;
        });
    });

    return next;
}

function buildPrototypeValueFromBuilderField(fieldDefinition: BuilderFieldDefinition): unknown {
    const normalizedDefault = parseStructuredValue(cloneValue(fieldDefinition.default));
    if (fieldDefinition.type === 'link') {
        if (isRecord(normalizedDefault)) {
            return normalizedDefault;
        }

        return {
            label: '',
            url: typeof normalizedDefault === 'string' ? normalizedDefault : '',
        };
    }

    if (fieldDefinition.type === 'menu') {
        if (Array.isArray(normalizedDefault) && normalizedDefault.length > 0) {
            return normalizedDefault;
        }
        return [buildMenuPrototypeItem()];
    }

    if (fieldDefinition.type === 'button-group') {
        if (Array.isArray(normalizedDefault) && normalizedDefault.length > 0) {
            return normalizedDefault;
        }
        return [buildButtonGroupPrototypeItem()];
    }

    if (fieldDefinition.type === 'repeater') {
        if (Array.isArray(normalizedDefault) && normalizedDefault.length > 0) {
            return normalizedDefault;
        }

        if (Array.isArray(fieldDefinition.itemFields) && fieldDefinition.itemFields.length > 0) {
            return [buildPrototypeObjectFromFieldDefinitions(fieldDefinition.itemFields)];
        }

        return [];
    }

    if (normalizedDefault !== undefined) {
        return normalizedDefault;
    }

    return buildDefaultPrimitiveValue(fieldDefinition.type);
}

function collectRuntimeObjectPrimitiveFieldDescriptors(
    value: unknown,
    path: string[],
    label: string,
    contextSegments: string[],
    inheritedDefinition: Record<string, unknown>,
): SchemaPrimitiveFieldDescriptor[] {
    if (Array.isArray(value)) {
        return value.flatMap((entry, index) => collectRuntimeObjectPrimitiveFieldDescriptors(
            entry,
            [...path, String(index)],
            `${label} ${index + 1}`.trim(),
            contextSegments,
            inheritedDefinition,
        ));
    }

    if (isRecord(value)) {
        return Object.entries(value).flatMap(([key, entry]) => collectRuntimeObjectPrimitiveFieldDescriptors(
            entry,
            [...path, key],
            humanizePropertyKey(key),
            label.trim() === ''
                ? [...contextSegments]
                : [...contextSegments, label],
            inheritedDefinition,
        ));
    }

    if (value === null || value === undefined || path.length === 0) {
        return [];
    }

    const scalarType: SchemaPrimitiveScalarType = typeof value === 'number'
        ? 'number'
        : typeof value === 'boolean'
            ? 'boolean'
            : 'string';
    const runtimeLeafType = inferBuilderFieldTypeFromRuntimeLeaf(path, value);

    return [{
        path,
        type: scalarType,
        label: formatFieldLabel(label, contextSegments),
        definition: {
            ...inheritedDefinition,
            builder_field_type: runtimeLeafType,
            item_fields: undefined,
        },
    }];
}

function collectBuilderFieldDescriptorsFromDefinition(
    definition: BuilderFieldDefinition,
    currentValues: unknown,
    parentPath: string[],
    contextSegments: string[],
): SchemaPrimitiveFieldDescriptor[] {
    const relativePath = splitPath(definition.path);
    if (relativePath.length === 0) {
        return [];
    }

    const path = [...parentPath, ...relativePath];
    const currentValue = parseStructuredValue(getValueAtRelativePath(currentValues, relativePath));
    const prototypeValue = buildPrototypeValueFromBuilderField(definition);
    const effectiveValue = currentValue === undefined ? prototypeValue : currentValue;
    const serializedDefinition = serializeBuilderFieldDefinition(definition);

    if (definition.type === 'link') {
        return [{
            path,
            type: 'string',
            label: formatFieldLabel(definition.label, contextSegments),
            definition: {
                ...serializedDefinition,
                default: prototypeValue,
            },
        }];
    }

    if (definition.type === 'menu' || definition.type === 'repeater' || definition.type === 'button-group') {
        const itemValues = Array.isArray(effectiveValue) ? effectiveValue : [];
        const itemFields = Array.isArray(definition.itemFields) && definition.itemFields.length > 0
            ? definition.itemFields
            : [];
        const prototypeItems = Array.isArray(prototypeValue) ? prototypeValue : [];
        const itemsToDescribe = itemValues.length > 0
            ? itemValues
            : prototypeItems;

        if (itemFields.length > 0) {
            return itemsToDescribe.flatMap((entry, index) => collectBuilderFieldDescriptors(
                itemFields,
                entry,
                [...path, String(index)],
                [...contextSegments, `${definition.label} ${index + 1}`],
            ));
        }

        const fallbackItems = itemsToDescribe.length > 0
            ? itemsToDescribe
            : (definition.type === 'menu'
                ? [buildMenuPrototypeItem()]
                : definition.type === 'button-group'
                    ? [buildButtonGroupPrototypeItem()]
                    : []);

        return fallbackItems.flatMap((entry, index) => collectRuntimeObjectPrimitiveFieldDescriptors(
            entry,
            [...path, String(index)],
            `${definition.label} ${index + 1}`,
            contextSegments,
            serializedDefinition,
        ));
    }

    return [{
        path,
        type: scalarTypeForBuilderField(definition.type),
        label: formatFieldLabel(definition.label, contextSegments),
        definition: {
            ...serializedDefinition,
            default: prototypeValue,
        },
    }];
}

function collectBuilderFieldDescriptors(
    fieldDefinitions: BuilderFieldDefinition[],
    currentValues: unknown,
    parentPath: string[] = [],
    contextSegments: string[] = [],
): SchemaPrimitiveFieldDescriptor[] {
    return fieldDefinitions.flatMap((fieldDefinition) => collectBuilderFieldDescriptorsFromDefinition(
        fieldDefinition,
        currentValues,
        parentPath,
        contextSegments,
    ));
}

function collectSchemaPrimitiveFieldsFromDefinition(
    definition: Record<string, unknown>,
    path: string[],
    currentValue: unknown,
    label: string,
    contextSegments: string[],
): SchemaPrimitiveFieldDescriptor[] {
    const fieldType = schemaType(definition.type);
    const nestedProperties = isRecord(definition.properties) ? definition.properties : null;
    const additionalProperties = isRecord(definition.additionalProperties) ? definition.additionalProperties : null;
    const itemsDefinition = isRecord(definition.items) ? definition.items : null;

    if ((fieldType === 'object' || (!fieldType && nestedProperties)) && nestedProperties) {
        const nestedValue = isRecord(currentValue)
            ? currentValue
            : (isRecord(definition.default) ? definition.default : null);

        return collectSchemaPrimitiveFieldDescriptorsInternal(nestedProperties, nestedValue ?? undefined, path, contextSegments);
    }

    if ((fieldType === 'object' || (!fieldType && additionalProperties)) && additionalProperties) {
        const sourceValue = isRecord(currentValue)
            ? currentValue
            : (isRecord(definition.default) ? definition.default : null);

        if (!sourceValue) {
            return [];
        }

        return Object.entries(sourceValue).flatMap(([dynamicKey, dynamicValue]) => {
            const dynamicLabel = humanizePropertyKey(dynamicKey);
            const nextContext = label.trim() === ''
                ? [...contextSegments]
                : [...contextSegments, label];

            return collectSchemaPrimitiveFieldsFromDefinition(
                additionalProperties,
                [...path, dynamicKey],
                dynamicValue,
                dynamicLabel,
                nextContext,
            );
        });
    }

    if (fieldType === 'array' && itemsDefinition) {
        const items = Array.isArray(currentValue)
            ? currentValue
            : (Array.isArray(definition.default) ? definition.default : []);
        const fallbackPrototype = buildPrototypeValueFromDefinition(itemsDefinition);
        const itemsToDescribe = items.length === 0 && fallbackPrototype !== undefined
            ? [fallbackPrototype]
            : items;

        if (itemsToDescribe.length === 0) {
            return [];
        }

        return itemsToDescribe.flatMap((entry, index) => {
            const entryPath = [...path, String(index)];
            const entryContext = label.trim() === ''
                ? [...contextSegments, String(index + 1)]
                : [...contextSegments, `${label} ${index + 1}`];
            const itemType = schemaType(itemsDefinition.type);
            const itemProperties = isRecord(itemsDefinition.properties) ? itemsDefinition.properties : null;

            if ((itemType === 'object' || (!itemType && itemProperties)) && itemProperties) {
                return collectSchemaPrimitiveFieldDescriptorsInternal(itemProperties, entry, entryPath, entryContext);
            }

            if (!isPrimitiveSchemaType(itemType)) {
                return collectSchemaPrimitiveFieldsFromDefinition(
                    itemsDefinition,
                    entryPath,
                    entry,
                    label,
                    entryContext,
                );
            }

            return [{
                path: entryPath,
                type: itemType,
                label: entryContext.join(' / '),
                definition: itemsDefinition,
            }];
        });
    }

    if (!isPrimitiveSchemaType(fieldType)) {
        return [];
    }

    return [{
        path,
        type: fieldType,
        label: formatFieldLabel(label, contextSegments),
        definition,
    }];
}

function collectSchemaPrimitiveFieldDescriptorsInternal(
    properties: Record<string, unknown>,
    currentValues: unknown,
    parentPath: string[],
    contextSegments: string[],
): SchemaPrimitiveFieldDescriptor[] {
    const fields: SchemaPrimitiveFieldDescriptor[] = [];

    Object.entries(properties).forEach(([key, rawDefinition]) => {
        if (!isRecord(rawDefinition)) {
            return;
        }

        const definition = rawDefinition;
        const path = [...parentPath, key];
        const fallbackLabel = humanizePropertyKey(key);
        const label = normalizeLabel(
            typeof definition.title === 'string' ? definition.title : null,
            fallbackLabel,
        );
        const currentValue = isRecord(currentValues) ? currentValues[key] : undefined;

        fields.push(
            ...collectSchemaPrimitiveFieldsFromDefinition(
                definition,
                path,
                currentValue,
                label,
                contextSegments,
            )
        );
    });

    return fields;
}

export function collectSchemaPrimitiveFieldDescriptors(
    properties: Record<string, unknown>,
    options: CollectSchemaPrimitiveFieldOptions = {},
): SchemaPrimitiveFieldDescriptor[] {
    return collectSchemaPrimitiveFieldDescriptorsInternal(properties, options.values, [], []);
}

export function collectBuilderSchemaPrimitiveFieldDescriptors(
    schema: BuilderComponentSchema | null | undefined,
    options: CollectBuilderSchemaPrimitiveFieldOptions = {},
): SchemaPrimitiveFieldDescriptor[] {
    if (!schema || !Array.isArray(schema.fields) || schema.fields.length === 0) {
        return [];
    }

    return collectBuilderFieldDescriptors(schema.fields, options.values);
}
