import type { BuilderSection } from '@/builder/visual/treeUtils';

export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export function normalizePath(path: string | string[]): string[] {
    if (Array.isArray(path)) {
        return path.map((segment) => String(segment).trim()).filter(Boolean);
    }

    return String(path)
        .split('.')
        .map((segment) => segment.trim())
        .filter(Boolean);
}

export function parseSectionProps(raw: string | null | undefined): Record<string, unknown> | null {
    if (typeof raw !== 'string' || raw.trim() === '') {
        return {};
    }

    try {
        const parsed = JSON.parse(raw);
        return isRecord(parsed) ? parsed : {};
    } catch {
        return null;
    }
}

export function stringifySectionProps(props: Record<string, unknown>): string {
    return JSON.stringify(props, null, 2) ?? '{}';
}

export function getValueAtPath(source: Record<string, unknown>, path: string | string[]): unknown {
    const normalizedPath = normalizePath(path);
    let cursor: unknown = source;

    for (const key of normalizedPath) {
        if (Array.isArray(cursor)) {
            const index = Number(key);
            if (!Number.isInteger(index) || index < 0 || index >= cursor.length) {
                return null;
            }
            cursor = cursor[index];
            continue;
        }

        if (!isRecord(cursor) || !(key in cursor)) {
            return null;
        }

        cursor = cursor[key];
    }

    return cursor;
}

function setValueAtPathNode(source: unknown, path: string[], value: unknown): unknown {
    if (path.length === 0) {
        return value;
    }

    const [segment, ...rest] = path;
    const nextIsIndex = rest.length > 0 && /^\d+$/.test(rest[0] ?? '');

    if (/^\d+$/.test(segment)) {
        const index = Number(segment);
        const currentArray = Array.isArray(source) ? [...source] : [];
        currentArray[index] = setValueAtPathNode(currentArray[index], rest, value);
        return currentArray;
    }

    const currentRecord = isRecord(source) ? { ...source } : {};
    const existingValue = currentRecord[segment];
    currentRecord[segment] = rest.length === 0
        ? value
        : setValueAtPathNode(existingValue ?? (nextIsIndex ? [] : {}), rest, value);

    return currentRecord;
}

export function setValueAtPath(
    source: Record<string, unknown>,
    path: string | string[],
    value: unknown
): Record<string, unknown> {
    const normalizedPath = normalizePath(path);
    if (normalizedPath.length === 0) {
        return source;
    }

    return setValueAtPathNode(source, normalizedPath, value) as Record<string, unknown>;
}

export function updateSectionDraftByLocalId(
    sections: BuilderSection[],
    localId: string,
    updater: (props: Record<string, unknown>) => Record<string, unknown>
): BuilderSection[] {
    return sections.map((section) => {
        if (section.localId !== localId) {
            return section;
        }

        const currentProps = parseSectionProps(section.propsText) ?? {};
        const nextProps = updater({ ...currentProps });

        return {
            ...section,
            props: nextProps,
            propsText: stringifySectionProps(nextProps),
            propsError: null,
        };
    });
}

export function buildSectionPreviewText(props: Record<string, unknown>, fallback = ''): string {
    const previewPaths = [
        'headline',
        'title',
        'subtitle',
        'description',
        'body',
        'label',
        'buttonText',
        'primary_cta.label',
    ];

    for (const path of previewPaths) {
        const value = getValueAtPath(props, path);
        if (typeof value === 'string' && value.trim() !== '') {
            return value.trim();
        }
    }

    return fallback.trim();
}
