import type {
    BuilderComponentSchema,
    BuilderFieldDefinition,
} from '@/builder/componentRegistry';
import { expandComponentPropAliasPath } from '@/builder/componentRegistry';

function uniqueStringList(values: Array<string | null | undefined>): string[] {
    return Array.from(new Set(
        values
            .map((value) => (typeof value === 'string' ? value.trim() : ''))
            .filter(Boolean),
    ));
}

export function splitSchemaFieldPath(path: string | null | undefined): string[] {
    return (path ?? '')
        .split('.')
        .map((segment) => segment.trim())
        .filter(Boolean);
}

function isNumericSchemaFieldSegment(value: string | undefined): boolean {
    return Boolean(value && /^\d+$/.test(value));
}

export function normalizeComparableSchemaFieldPath(path: string | null | undefined): string {
    return splitSchemaFieldPath(path)
        .filter((segment) => !isNumericSchemaFieldSegment(segment))
        .join('.');
}

export function isSchemaFieldPathRelated(candidate: string, targetPath: string): boolean {
    return candidate === targetPath
        || candidate.startsWith(`${targetPath}.`)
        || targetPath.startsWith(`${candidate}.`);
}

export function collectSchemaFieldPaths(schema: BuilderComponentSchema | null): string[] {
    if (!schema) {
        return [];
    }

    const paths: string[] = [];

    schema.fields.forEach((field) => {
        const basePath = field.path.trim();
        if (basePath !== '') {
            paths.push(basePath);
        }

        field.itemFields?.forEach((itemField) => {
            const itemPath = itemField.path.trim();
            if (basePath !== '' && itemPath !== '') {
                paths.push(`${basePath}.${itemPath}`);
            }
        });
    });

    return uniqueStringList(paths);
}

export function resolveSchemaPreferredStringProp(
    schema: BuilderComponentSchema | null,
    props: Record<string, unknown>,
    candidateKeys: string[],
): string | null {
    const schemaFieldPathSet = new Set(collectSchemaFieldPaths(schema));
    const schemaBackedKeys = candidateKeys.filter((key) => schemaFieldPathSet.has(key));
    const orderedKeys = schemaBackedKeys.length > 0 ? schemaBackedKeys : candidateKeys;

    for (const key of orderedKeys) {
        const value = props[key];
        if (typeof value === 'string' && value.trim() !== '') {
            return value.trim();
        }
    }

    return null;
}

export function expandSchemaAwareAliasPaths(
    path: string | null | undefined,
    availableFieldPaths: Iterable<string>,
): string[] {
    const normalizedPath = splitSchemaFieldPath(path).join('.');
    if (normalizedPath === '') {
        return [];
    }

    const comparableFieldPaths = Array.from(new Set(
        Array.from(availableFieldPaths)
            .map((candidate) => normalizeComparableSchemaFieldPath(candidate))
            .filter(Boolean),
    ));

    const candidates = uniqueStringList([
        normalizedPath,
        ...expandComponentPropAliasPath(normalizedPath),
    ]);

    if (comparableFieldPaths.length === 0) {
        return [normalizedPath];
    }

    const filtered = candidates.filter((candidate) => {
        const comparableCandidate = normalizeComparableSchemaFieldPath(candidate);
        return comparableFieldPaths.some((fieldPath) => (
            comparableCandidate === fieldPath
            || comparableCandidate.startsWith(`${fieldPath}.`)
            || fieldPath.startsWith(`${comparableCandidate}.`)
        ));
    });

    return filtered.length > 0 ? filtered : [normalizedPath];
}

export function resolveSchemaMatchedField(
    schema: BuilderComponentSchema | null,
    path: string | null,
): {
    matchedField: BuilderFieldDefinition | null;
    scopeField: BuilderFieldDefinition | null;
} {
    if (!schema || !path) {
        return {
            matchedField: null,
            scopeField: null,
        };
    }

    const normalizedPath = splitSchemaFieldPath(path).join('.');
    if (normalizedPath === '') {
        return {
            matchedField: null,
            scopeField: null,
        };
    }

    const schemaFieldPaths = collectSchemaFieldPaths(schema);
    const schemaAwareAliases = expandSchemaAwareAliasPaths(normalizedPath, schemaFieldPaths);
    const comparablePaths = Array.from(new Set(
        schemaAwareAliases
            .map((candidate) => normalizeComparableSchemaFieldPath(candidate))
            .filter(Boolean),
    ));

    const exactField = schema.fields.find((field) => schemaAwareAliases.includes(field.path)) ?? null;
    if (exactField) {
        return {
            matchedField: exactField,
            scopeField: exactField,
        };
    }

    const scopeField = [...schema.fields]
        .filter((field) => {
            const comparableFieldPath = normalizeComparableSchemaFieldPath(field.path);
            return comparablePaths.some((comparablePath) => (
                comparablePath === comparableFieldPath
                || comparablePath.startsWith(`${comparableFieldPath}.`)
            ));
        })
        .sort((left, right) => right.path.length - left.path.length)[0] ?? null;

    if (scopeField?.itemFields && comparablePaths.some((comparablePath) => comparablePath.startsWith(`${normalizeComparableSchemaFieldPath(scopeField.path)}.`))) {
        const scopeSegments = splitSchemaFieldPath(scopeField.path);
        const remainingSegments = splitSchemaFieldPath(normalizedPath).slice(scopeSegments.length);
        const itemPath = (isNumericSchemaFieldSegment(remainingSegments[0]) ? remainingSegments.slice(1) : remainingSegments).join('.');
        const itemAliases = expandSchemaAwareAliasPaths(
            itemPath,
            scopeField.itemFields.map((field) => field.path),
        );
        const comparableItemPaths = Array.from(new Set(
            itemAliases
                .map((candidate) => normalizeComparableSchemaFieldPath(candidate))
                .filter(Boolean),
        ));
        const itemField = itemPath
            ? [...scopeField.itemFields]
                .filter((field) => comparableItemPaths.some((candidate) => (
                    candidate === normalizeComparableSchemaFieldPath(field.path)
                    || candidate.startsWith(`${normalizeComparableSchemaFieldPath(field.path)}.`)
                )))
                .sort((left, right) => right.path.length - left.path.length)[0] ?? null
            : null;

        return {
            matchedField: itemField ?? scopeField,
            scopeField,
        };
    }

    return {
        matchedField: scopeField,
        scopeField,
    };
}
