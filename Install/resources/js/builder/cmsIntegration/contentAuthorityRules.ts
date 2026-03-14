import {
    getComponentSchema,
    resolveComponentRegistryKey,
    type BuilderFieldDefinition,
} from '@/builder/componentRegistry';

import type { CmsFieldBinding, CmsFieldOwner } from './types';

interface CmsFieldOwnershipSnapshotItem {
    propPath: string;
    owner: CmsFieldOwner;
    fieldDefinition: BuilderFieldDefinition | null;
    persistenceLocation: string;
    fieldType: string;
    staticDefaultOnly: boolean;
    visualOnly: boolean;
    codeOwned: boolean;
}

export interface CmsFieldOwnershipSnapshot {
    registryKey: string | null;
    fields: CmsFieldOwnershipSnapshotItem[];
    contentFieldPaths: string[];
    visualFieldPaths: string[];
    codeFieldPaths: string[];
    staticDefaultFieldPaths: string[];
}

const VISUAL_GROUPS = new Set(['layout', 'style', 'responsive', 'state', 'states', 'meta']);
const CONTENT_GROUPS = new Set(['content', 'data', 'bindings']);
const VISUAL_PATH_PATTERNS = [
    /(^|\.)(variant|layout_variant|style_variant)$/i,
    /(^|\.)(alignment|spacing|padding|margin|radius|shadow|overlay|visibility)$/i,
    /(^|\.)(background|backgroundColor|textColor|containerWidth|columns|gap)$/i,
    /(^|\.)(responsive|desktop|tablet|mobile)(\.|$)/i,
];
const CODE_PATH_PATTERNS = [
    /(^|\.)(api|endpoint|query|provider|hook|handler|submitAction|action|service)$/i,
    /(^|\.)(webhook|mutation|resolver|moduleKey|integration|script)$/i,
    /(^|\.)(code|logic|adapter|transformer|workflow)$/i,
];
const CONTENT_PATH_PATTERNS = [
    /(^|\.)(title|headline|subtitle|description|body|text|copy|eyebrow)$/i,
    /(^|\.)(button|buttonText|buttonLabel|buttonUrl|buttonLink|cta|ctaText|ctaLabel|ctaUrl|ctaLink)$/i,
    /(^|\.)(logo|logoText|menu|menu_items|links|items|faq|faqItems|testimonials)$/i,
    /(^|\.)(image|image_url|image_alt|caption|gallery|media)$/i,
    /(^|\.)(placeholder|namePlaceholder|emailPlaceholder|messagePlaceholder|submitLabel)$/i,
    /(^|\.)(seo|seo_title|seo_description|intro|excerpt)$/i,
];

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function normalizeText(value: string | null | undefined): string {
    return typeof value === 'string' ? value.trim() : '';
}

function uniqueStrings(values: Array<string | null | undefined>): string[] {
    return Array.from(new Set(
        values
            .map((value) => normalizeText(value))
            .filter((value) => value !== ''),
    ));
}

function flattenPropPaths(value: unknown, prefix = ''): string[] {
    if (!isRecord(value)) {
        return prefix !== '' ? [prefix] : [];
    }

    const paths: string[] = [];

    Object.entries(value).forEach(([key, nestedValue]) => {
        const nextPath = prefix === '' ? key : `${prefix}.${key}`;
        paths.push(nextPath);

        if (isRecord(nestedValue)) {
            paths.push(...flattenPropPaths(nestedValue, nextPath));
            return;
        }

        if (Array.isArray(nestedValue)) {
            const firstObject = nestedValue.find((item): item is Record<string, unknown> => isRecord(item));
            if (firstObject) {
                paths.push(...flattenPropPaths(firstObject, nextPath));
            }
        }
    });

    return paths;
}

function matchesAny(path: string, patterns: RegExp[]): boolean {
    return patterns.some((pattern) => pattern.test(path));
}

function resolvePersistenceLocation(owner: CmsFieldOwner): string {
    switch (owner) {
        case 'cms':
            return 'page_revisions.content_json.sections[*].props';
        case 'builder_structure':
            return 'page_revisions.content_json.sections[*]';
        case 'code':
            return 'workspace.files';
        case 'mixed':
        default:
            return 'page_revisions.content_json + workspace.files';
    }
}

export function classifyCmsFieldOwner(
    path: string,
    fieldDefinition: BuilderFieldDefinition | null,
): CmsFieldOwner {
    const normalizedPath = normalizeText(path);

    if (matchesAny(normalizedPath, CODE_PATH_PATTERNS)) {
        return fieldDefinition && CONTENT_GROUPS.has(fieldDefinition.group) ? 'mixed' : 'code';
    }

    if (fieldDefinition) {
        if (CONTENT_GROUPS.has(fieldDefinition.group)) {
            return 'cms';
        }

        if (VISUAL_GROUPS.has(fieldDefinition.group)) {
            return 'builder_structure';
        }

        if (fieldDefinition.group === 'advanced') {
            return fieldDefinition.bindingCompatible === true && matchesAny(normalizedPath, CONTENT_PATH_PATTERNS)
                ? 'cms'
                : 'builder_structure';
        }
    }

    if (matchesAny(normalizedPath, VISUAL_PATH_PATTERNS)) {
        return 'builder_structure';
    }

    if (matchesAny(normalizedPath, CONTENT_PATH_PATTERNS)) {
        return 'cms';
    }

    return 'mixed';
}

export function findComponentFieldDefinition(
    registryKey: string,
    path: string,
): BuilderFieldDefinition | null {
    const schema = getComponentSchema(registryKey);
    if (!schema) {
        return null;
    }

    return schema.fields.find((field) => field.path === path) ?? null;
}

export function buildCmsFieldOwnershipSnapshot(
    registryKeyOrType: string,
    props: Record<string, unknown>,
): CmsFieldOwnershipSnapshot {
    const registryKey = resolveComponentRegistryKey(registryKeyOrType) ?? registryKeyOrType;
    const schema = getComponentSchema(registryKey);
    const candidatePaths = uniqueStrings([
        ...(schema?.fields.map((field) => field.path) ?? []),
        ...flattenPropPaths(props),
    ]);

    const fields = candidatePaths.map((propPath) => {
        const fieldDefinition = findComponentFieldDefinition(registryKey, propPath);
        const owner = classifyCmsFieldOwner(propPath, fieldDefinition);

        return {
            propPath,
            owner,
            fieldDefinition,
            persistenceLocation: resolvePersistenceLocation(owner),
            fieldType: fieldDefinition?.type ?? 'unknown',
            staticDefaultOnly: owner !== 'cms' && owner !== 'mixed',
            visualOnly: owner === 'builder_structure',
            codeOwned: owner === 'code',
        } satisfies CmsFieldOwnershipSnapshotItem;
    });

    return {
        registryKey,
        fields,
        contentFieldPaths: fields.filter((field) => field.owner === 'cms' || field.owner === 'mixed').map((field) => field.propPath),
        visualFieldPaths: fields.filter((field) => field.owner === 'builder_structure').map((field) => field.propPath),
        codeFieldPaths: fields.filter((field) => field.owner === 'code' || field.owner === 'mixed').map((field) => field.propPath),
        staticDefaultFieldPaths: fields.filter((field) => field.staticDefaultOnly).map((field) => field.propPath),
    };
}

export function serializeFieldBindingForMetadata(binding: CmsFieldBinding): Record<string, unknown> {
    return {
        key: binding.key,
        content_key: binding.contentKey,
        path: binding.propPath,
        owner: binding.owner,
        persistence_location: binding.persistenceLocation,
        sync_direction: binding.syncDirection,
        conflict_status: binding.conflictStatus,
        field_type: binding.fieldType,
        registry_field_path: binding.registryFieldPath,
        group: binding.group,
        binding_compatible: binding.bindingCompatible,
        static_default_only: binding.staticDefaultOnly,
        visual_only: binding.visualOnly,
        code_owned: binding.codeOwned,
    };
}
