import type { SchemaPrimitiveScalarType } from '@/lib/schemaPrimitiveFields';

export interface InspectorFieldControlMeta {
    type: SchemaPrimitiveScalarType;
    label: string;
    group: string;
    responsive: boolean;
    stateful: boolean;
    dynamic_capable: boolean;
}

export interface InspectorSchemaField {
    path: string[];
    type: SchemaPrimitiveScalarType;
    label: string;
    definition: Record<string, unknown>;
    control_meta: InspectorFieldControlMeta;
}

interface FilterInspectorSchemaFieldOptions {
    previewMode: 'desktop' | 'tablet' | 'mobile';
    interactionState: 'normal' | 'hover' | 'focus' | 'active';
    targetPath?: string | null;
    targetComponentPath?: string | null;
    targetEditableFields?: string[];
    elementorLike?: boolean;
}

export function normalizeInspectorFieldPath(path: string[] | string): string {
    if (Array.isArray(path)) {
        return path.map((segment) => String(segment).trim()).filter(Boolean).join('.');
    }

    return String(path)
        .split('.')
        .map((segment) => segment.trim())
        .filter(Boolean)
        .join('.');
}

function shouldDisplayAdvancedField(fieldPath: string[]): boolean {
    const last = fieldPath[fieldPath.length - 1] as string;
    if (last === 'custom_class' || last === 'custom_css' || last === 'z_index') {
        return true;
    }

    return /^(padding|margin)_(top|right|bottom|left)$/.test(last);
}

function deriveFieldFamily(path: string): string {
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

export function filterInspectorSchemaFields<T extends InspectorSchemaField>(
    fields: T[],
    options: FilterInspectorSchemaFieldOptions,
): T[] {
    if (fields.length === 0) {
        return [];
    }

    const previewFiltered = fields.filter((field) => {
        if (options.elementorLike) {
            const group = field.control_meta.group;
            if (group === 'meta') {
                return false;
            }
            if (group === 'responsive') {
                return field.path[1] === 'desktop';
            }
            if (field.path[0] === 'states') {
                const activeState = options.interactionState;
                if (activeState === 'normal') {
                    return false;
                }
                return field.path[1] === activeState;
            }
            if (group === 'advanced') {
                return shouldDisplayAdvancedField(field.path);
            }
            return true;
        }

        const group = field.control_meta.group;
        if (group === 'advanced') {
            return shouldDisplayAdvancedField(field.path);
        }
        if (field.path[0] !== 'responsive') {
            if (field.path[0] !== 'states') {
                return true;
            }
            const activeState = options.interactionState;
            if (activeState === 'normal') {
                return false;
            }
            const interactionState = field.path[1];
            if (interactionState !== 'hover' && interactionState !== 'focus' && interactionState !== 'active') {
                return true;
            }
            return interactionState === activeState;
        }

        const breakpoint = field.path[1];
        if (breakpoint !== 'desktop' && breakpoint !== 'tablet' && breakpoint !== 'mobile') {
            return true;
        }
        return breakpoint === options.previewMode;
    });

    const targetPath = normalizeInspectorFieldPath(options.targetPath ?? '');
    const targetComponentPath = normalizeInspectorFieldPath(options.targetComponentPath ?? '');
    if (targetPath === '' && targetComponentPath === '') {
        return previewFiltered;
    }

    const explicitTargetPaths = new Set(
        (options.targetEditableFields ?? [])
            .map((path) => normalizeInspectorFieldPath(path))
            .filter(Boolean),
    );

    if (targetPath !== '') {
        explicitTargetPaths.add(targetPath);
    }

    const scopePath = targetComponentPath || (
        targetPath.includes('.')
            ? targetPath.split('.').slice(0, -1).join('.')
            : targetPath
    );
    const typographyCompanion = targetPath && !targetPath.includes('.')
        ? `${targetPath}_typography`
        : '';
    const relatedFamilies = new Set(
        [targetPath, targetComponentPath, ...explicitTargetPaths]
            .map((value) => deriveFieldFamily(value))
            .filter(Boolean),
    );
    const hasIndexedScope = /\.\d+(?:\.|$)/.test(scopePath) || Array.from(explicitTargetPaths).some((path) => /\.\d+(?:\.|$)/.test(path));
    const strictMatches = previewFiltered.filter((field) => {
        const fieldPath = normalizeInspectorFieldPath(field.path);
        if (explicitTargetPaths.has(fieldPath)) {
            return true;
        }
        if (scopePath !== '' && (fieldPath === scopePath || fieldPath.startsWith(`${scopePath}.`))) {
            return true;
        }
        if (typographyCompanion !== '' && fieldPath === typographyCompanion) {
            return true;
        }
        if (fieldPath === 'layoutVariant' || fieldPath === 'layout_variant' || fieldPath === 'styleVariant' || fieldPath === 'style_variant') {
            return explicitTargetPaths.has(fieldPath) || relatedFamilies.size > 0;
        }

        if (hasIndexedScope) {
            return false;
        }

        return false;
    });

    if (strictMatches.length > 0) {
        return strictMatches;
    }

    if (hasIndexedScope || explicitTargetPaths.size > 0 || targetPath !== '' || targetComponentPath !== '') {
        return [];
    }

    if (relatedFamilies.size === 0) {
        return [];
    }

    return previewFiltered.filter((field) => {
        const fieldFamily = deriveFieldFamily(normalizeInspectorFieldPath(field.path));
        return fieldFamily !== '' && relatedFamilies.has(fieldFamily);
    });
}
