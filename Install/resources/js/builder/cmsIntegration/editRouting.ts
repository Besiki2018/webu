import type { AiEditIntent } from '@/builder/ai/editIntentRouter';
import { getNestedSectionAtPath } from '@/builder/cms/nestedSectionTree';
import type { BuilderUpdateOperation } from '@/builder/state/updatePipeline';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

import {
    classifyCmsFieldOwner,
    findComponentFieldDefinition,
    normalizeCmsFieldPath,
} from './contentAuthorityRules';
import type { CmsEditRoute, RoutedCmsEdit } from './types';

function uniqueStrings(values: Array<string | null | undefined>): string[] {
    return Array.from(new Set(
        values
            .filter((value): value is string => typeof value === 'string' && value.trim() !== '')
            .map((value) => value.trim()),
    ));
}

function mergeRoutes(routes: CmsEditRoute[]): CmsEditRoute {
    const uniqueRoutes = Array.from(new Set(routes));

    if (uniqueRoutes.length === 0) {
        return 'content_change';
    }

    if (uniqueRoutes.includes('mixed_change') || uniqueRoutes.length > 1) {
        return 'mixed_change';
    }

    return uniqueRoutes[0] ?? 'content_change';
}

function flattenPatchPaths(value: unknown, prefix = ''): string[] {
    if (value === null || value === undefined || typeof value !== 'object' || Array.isArray(value)) {
        return prefix !== '' ? [prefix] : [];
    }

    return Object.entries(value).flatMap(([key, nestedValue]) => {
        const nextPath = prefix === '' ? key : `${prefix}.${key}`;
        return [nextPath, ...flattenPatchPaths(nestedValue, nextPath)];
    });
}

function resolveOperationFieldOwner(
    section: SectionDraft | null,
    path: string,
    nestedSectionPath?: number[],
) {
    if (!section) {
        return 'mixed' as const;
    }

    const normalizedPath = normalizeCmsFieldPath(path);
    const nestedSection = Array.isArray(nestedSectionPath) && nestedSectionPath.length > 0
        ? getNestedSectionAtPath(section.props ?? {}, nestedSectionPath)
        : null;
    const registryKey = nestedSection?.type ?? section.type;
    const fieldDefinition = registryKey
        ? findComponentFieldDefinition(registryKey, normalizedPath)
        : null;

    return classifyCmsFieldOwner(normalizedPath, fieldDefinition);
}

function resolveOperationRoute(
    operation: BuilderUpdateOperation,
    sectionsByLocalId: Map<string, SectionDraft>,
): { route: CmsEditRoute; touchedPaths: string[]; sectionLocalIds: string[]; reasons: string[] } {
    switch (operation.kind) {
        case 'insert-section':
        case 'delete-section':
        case 'duplicate-section':
        case 'reorder-section':
        case 'insert-nested-section':
        case 'delete-nested-section':
        case 'reorder-nested-section':
            return {
                route: 'structure_change',
                touchedPaths: [],
                sectionLocalIds: 'sectionLocalId' in operation ? [operation.sectionLocalId] : [],
                reasons: [operation.kind],
            };
        case 'set-field':
        case 'unset-field': {
            const section = sectionsByLocalId.get(operation.sectionLocalId);
            const path = normalizeCmsFieldPath(operation.path);
            const owner = resolveOperationFieldOwner(section ?? null, path, operation.nestedSectionPath);
            return {
                route: owner === 'cms'
                    ? 'content_change'
                    : owner === 'builder_structure'
                        ? 'structure_change'
                        : owner === 'code'
                            ? 'code_change'
                            : 'mixed_change',
                touchedPaths: [path],
                sectionLocalIds: [operation.sectionLocalId],
                reasons: [`${operation.kind}:${path}`],
            };
        }
        case 'merge-props': {
            const section = sectionsByLocalId.get(operation.sectionLocalId);
            const patchPaths = flattenPatchPaths(operation.patch).map((path) => normalizeCmsFieldPath(path));
            const routes = patchPaths.map((path) => {
                const owner = resolveOperationFieldOwner(section ?? null, path, operation.nestedSectionPath);
                return owner === 'cms'
                    ? 'content_change'
                    : owner === 'builder_structure'
                        ? 'structure_change'
                        : owner === 'code'
                            ? 'code_change'
                            : 'mixed_change';
            });

            return {
                route: mergeRoutes(routes),
                touchedPaths: patchPaths,
                sectionLocalIds: [operation.sectionLocalId],
                reasons: patchPaths.map((path) => `merge-props:${path}`),
            };
        }
        default:
            return {
                route: 'mixed_change',
                touchedPaths: [],
                sectionLocalIds: [],
                reasons: ['unknown_operation'],
            };
    }
}

export function routeBuilderOperationsToCmsEdit(
    operations: BuilderUpdateOperation[],
    sections: SectionDraft[],
): RoutedCmsEdit {
    const sectionsByLocalId = new Map(sections.map((section) => [section.localId, section]));
    const routed = operations.map((operation) => resolveOperationRoute(operation, sectionsByLocalId));
    const route = mergeRoutes(routed.map((entry) => entry.route));
    const touchedPaths = uniqueStrings(routed.flatMap((entry) => entry.touchedPaths));

    return {
        route,
        reasons: uniqueStrings(routed.flatMap((entry) => entry.reasons)),
        contentFieldPaths: touchedPaths.filter((path) => {
            const sectionLocalId = routed.find((entry) => entry.touchedPaths.includes(path))?.sectionLocalIds[0] ?? null;
            const section = sectionLocalId ? sectionsByLocalId.get(sectionLocalId) ?? null : null;

            return resolveOperationFieldOwner(section, path) === 'cms';
        }),
        structureFieldPaths: touchedPaths.filter((path) => {
            const sectionLocalId = routed.find((entry) => entry.touchedPaths.includes(path))?.sectionLocalIds[0] ?? null;
            const section = sectionLocalId ? sectionsByLocalId.get(sectionLocalId) ?? null : null;

            return resolveOperationFieldOwner(section, path) === 'builder_structure';
        }),
        codeFieldPaths: touchedPaths.filter((path) => {
            const sectionLocalId = routed.find((entry) => entry.touchedPaths.includes(path))?.sectionLocalIds[0] ?? null;
            const section = sectionLocalId ? sectionsByLocalId.get(sectionLocalId) ?? null : null;
            const owner = resolveOperationFieldOwner(section, path);
            return owner === 'code' || owner === 'mixed';
        }),
        sectionLocalIds: uniqueStrings(routed.flatMap((entry) => entry.sectionLocalIds)),
        touchedPaths,
    };
}

export function routeAiIntentToCmsEdit(intent: AiEditIntent): CmsEditRoute {
    switch (intent) {
        case 'file_change':
        case 'regeneration_request':
            return 'code_change';
        case 'structure_change':
        case 'page_change':
            return 'structure_change';
        case 'prop_patch':
        default:
            return 'content_change';
    }
}
