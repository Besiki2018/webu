import { resolveComponentRegistryKey } from '@/builder/componentRegistry';
import { cloneRecordData } from '@/builder/runtime/clone';
import { normalizePath, setValueAtPath } from '@/builder/state/sectionProps';
import type { BuilderUpdateOperation } from '@/builder/state/updatePipeline';
import { resolveGeneratedSectionKindFromRegistryKey } from './projectGraph';
import type { GeneratedPage, GeneratedSectionInput, ProjectGraphPatchInstruction } from './types';

function cloneRecord(value: Record<string, unknown> | null | undefined): Record<string, unknown> {
    return cloneRecordData(value);
}

function normalizeGeneratedSectionInput(sectionType: string, localId: string | null | undefined, props: Record<string, unknown> = {}): GeneratedSectionInput {
    const registryKey = resolveComponentRegistryKey(sectionType);

    return {
        localId: localId?.trim() || `section-${registryKey ?? sectionType}`,
        kind: resolveGeneratedSectionKindFromRegistryKey(registryKey ?? sectionType),
        registryKey,
        label: null,
        props: cloneRecord(props),
    };
}

function buildUnsupportedInstruction(
    page: Pick<GeneratedPage, 'id' | 'slug'>,
    operationKind: string,
    reason: string,
    metadata?: Record<string, unknown>,
): ProjectGraphPatchInstruction {
    return {
        kind: 'unsupported-builder-operation',
        source: 'builder',
        pageId: page.id,
        pageSlug: page.slug,
        operationKind,
        reason,
        metadata,
    };
}

/**
 * Integration point: the current builder still mutates `BuilderUpdateOperation`s.
 * This adapter preserves that API surface while producing codegen-side graph patch
 * instructions for the future workspace-first runtime.
 */
export function buildProjectGraphPatchFromBuilderOperations(
    page: Pick<GeneratedPage, 'id' | 'slug'>,
    operations: BuilderUpdateOperation[],
): ProjectGraphPatchInstruction[] {
    return operations.map((operation) => {
        switch (operation.kind) {
            case 'set-field': {
                const path = normalizePath(operation.path);
                return {
                    kind: 'update-section-props',
                    source: 'builder',
                    pageId: page.id,
                    pageSlug: page.slug,
                    sectionId: operation.sectionLocalId,
                    propsPatch: setValueAtPath({}, path, operation.value),
                };
            }
            case 'unset-field': {
                const path = normalizePath(operation.path).join('.');
                return {
                    kind: 'unset-section-props',
                    source: 'builder',
                    pageId: page.id,
                    pageSlug: page.slug,
                    sectionId: operation.sectionLocalId,
                    paths: path === '' ? [] : [path],
                };
            }
            case 'merge-props':
                return {
                    kind: 'update-section-props',
                    source: 'builder',
                    pageId: page.id,
                    pageSlug: page.slug,
                    sectionId: operation.sectionLocalId,
                    propsPatch: cloneRecord(operation.patch),
                };
            case 'insert-section':
                return {
                    kind: 'insert-section',
                    source: 'builder',
                    pageId: page.id,
                    pageSlug: page.slug,
                    afterSectionId: operation.afterSectionId ?? null,
                    insertIndex: operation.insertIndex ?? null,
                    section: normalizeGeneratedSectionInput(operation.sectionType, operation.localId, operation.props),
                };
            case 'delete-section':
                return {
                    kind: 'delete-section',
                    source: 'builder',
                    pageId: page.id,
                    pageSlug: page.slug,
                    sectionId: operation.sectionLocalId,
                };
            case 'duplicate-section':
                return {
                    kind: 'duplicate-section',
                    source: 'builder',
                    pageId: page.id,
                    pageSlug: page.slug,
                    sectionId: operation.sectionLocalId,
                    newSectionId: operation.newLocalId,
                };
            case 'reorder-section':
                return {
                    kind: 'move-section',
                    source: 'builder',
                    pageId: page.id,
                    pageSlug: page.slug,
                    sectionId: operation.sectionLocalId,
                    toIndex: operation.toIndex,
                };
            case 'insert-nested-section':
            case 'delete-nested-section':
            case 'reorder-nested-section':
                return buildUnsupportedInstruction(
                    page,
                    operation.kind,
                    'nested_sections_not_supported_yet',
                    'nestedSectionPath' in operation ? { nestedSectionPath: operation.nestedSectionPath } : undefined,
                );
            default:
                return buildUnsupportedInstruction(
                    page,
                    (operation as { kind?: string }).kind ?? 'unknown',
                    'operation_not_supported',
                );
        }
    });
}
