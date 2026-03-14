import { normalizePath, setValueAtPath } from '@/builder/state/sectionProps';

import type { CmsFieldBinding } from './types';

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function cloneRecord<T extends Record<string, unknown>>(value: T): T {
    try {
        return JSON.parse(JSON.stringify(value)) as T;
    } catch {
        return { ...value };
    }
}

export function getCmsFieldValue(
    source: Record<string, unknown>,
    path: string,
): unknown {
    return normalizePath(path).reduce<unknown>((current, key) => {
        if (!isRecord(current)) {
            return undefined;
        }

        return current[key];
    }, source);
}

export function setCmsFieldValue(
    source: Record<string, unknown>,
    path: string,
    value: unknown,
): Record<string, unknown> {
    return setValueAtPath(cloneRecord(source), normalizePath(path), value);
}

export function unsetCmsFieldValue(
    source: Record<string, unknown>,
    path: string,
): Record<string, unknown> {
    const normalizedPath = normalizePath(path);
    if (normalizedPath.length === 0) {
        return cloneRecord(source);
    }

    const next = cloneRecord(source);
    let current: Record<string, unknown> = next;

    normalizedPath.forEach((segment, index) => {
        if (index === normalizedPath.length - 1) {
            delete current[segment];
            return;
        }

        const nested = current[segment];
        if (!isRecord(nested)) {
            current[segment] = {};
        }
        current = current[segment] as Record<string, unknown>;
    });

    return next;
}

export function findCmsFieldBinding(
    bindings: CmsFieldBinding[],
    path: string,
): CmsFieldBinding | null {
    const normalized = normalizePath(path).join('.');
    return bindings.find((binding) => normalizePath(binding.propPath).join('.') === normalized) ?? null;
}
