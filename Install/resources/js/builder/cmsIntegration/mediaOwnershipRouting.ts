import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';
import { cloneRecordData } from '@/builder/runtime/clone';

import {
    classifyCmsFieldOwner,
    findComponentFieldDefinition,
    normalizeCmsFieldPath,
} from './contentAuthorityRules';
import { CMS_SECTION_BINDING_META_KEY } from './cmsSectionBinding';
import type {
    CmsBindingProvenanceEditor,
    CmsFieldOwner,
    CmsMediaBindingMetadata,
    CmsMediaFieldOwner,
    CmsMediaUpdateSource,
} from './types';

interface ApplySectionMediaBindingInput {
    componentKey?: string | null;
    propPath: string | string[];
    assetUrl: string | null;
    media?: {
        id?: number | string | null;
        metaJson?: Record<string, unknown> | null;
    } | null;
    importedBy?: CmsBindingProvenanceEditor | null;
    projectId?: string | null;
    pageSlug?: string | null;
    nestedSectionPath?: number[] | null;
    source: CmsMediaUpdateSource;
    timestamp?: string | null;
}

export interface ResolvedCmsMediaFieldOwner {
    owner: CmsMediaFieldOwner;
    rawOwner: CmsFieldOwner;
    normalizedPath: string;
    registryKey: string | null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function cloneRecord<T extends Record<string, unknown>>(value: T | null | undefined): T {
    return cloneRecordData(value);
}

function normalizeText(value: string | null | undefined): string {
    return typeof value === 'string' ? value.trim() : '';
}

function toMediaFieldOwner(owner: CmsFieldOwner): CmsMediaFieldOwner {
    if (owner === 'builder_structure') {
        return 'builder';
    }

    if (owner === 'code') {
        return 'code';
    }

    return 'cms';
}

function readMetaString(metaJson: Record<string, unknown> | null | undefined, key: string): string | null {
    const value = metaJson?.[key];

    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function buildQualifiedPropPath(propPath: string, nestedSectionPath: number[] | null | undefined): string {
    if (!Array.isArray(nestedSectionPath) || nestedSectionPath.length === 0) {
        return propPath;
    }

    return `nested.${nestedSectionPath.join('.')}.${propPath}`;
}

export function resolveCmsMediaFieldOwner(
    componentKey: string | null | undefined,
    propPath: string | string[],
): ResolvedCmsMediaFieldOwner {
    const normalizedPath = normalizeCmsFieldPath(propPath);
    const registryKey = normalizeText(componentKey);
    const fieldDefinition = registryKey !== ''
        ? findComponentFieldDefinition(registryKey, normalizedPath)
        : null;
    const rawOwner = classifyCmsFieldOwner(normalizedPath, fieldDefinition);

    return {
        owner: toMediaFieldOwner(rawOwner),
        rawOwner,
        normalizedPath,
        registryKey: registryKey !== '' ? registryKey : null,
    };
}

export function applySectionMediaBinding(
    section: SectionDraft,
    input: ApplySectionMediaBindingInput,
): { section: SectionDraft; owner: CmsMediaFieldOwner; rawOwner: CmsFieldOwner } {
    const resolved = resolveCmsMediaFieldOwner(input.componentKey ?? section.type, input.propPath);
    const bindingMeta = cloneRecord(section.bindingMeta);
    const existingSectionMeta = isRecord(bindingMeta[CMS_SECTION_BINDING_META_KEY])
        ? cloneRecord(bindingMeta[CMS_SECTION_BINDING_META_KEY] as Record<string, unknown>)
        : {};
    const existingProvenance = isRecord(existingSectionMeta.provenance)
        ? cloneRecord(existingSectionMeta.provenance as Record<string, unknown>)
        : {};
    const existingMediaFields = isRecord(existingSectionMeta.media_fields)
        ? cloneRecord(existingSectionMeta.media_fields as Record<string, unknown>)
        : {};
    const metaJson = isRecord(input.media?.metaJson) ? input.media?.metaJson : null;
    const timestamp = normalizeText(input.timestamp) || new Date().toISOString();
    const importedBy = normalizeText(
        typeof input.importedBy === 'string'
            ? input.importedBy
            : readMetaString(metaJson, 'imported_by'),
    ) || 'visual_builder';
    const qualifiedPropPath = buildQualifiedPropPath(
        resolved.normalizedPath,
        input.nestedSectionPath ?? null,
    );

    const mediaBinding: CmsMediaBindingMetadata = {
        owner: resolved.owner,
        assetUrl: normalizeText(input.assetUrl) || null,
        mediaId: input.media?.id !== null && input.media?.id !== undefined ? String(input.media.id) : null,
        provider: readMetaString(metaJson, 'stock_provider'),
        providerImageId: readMetaString(metaJson, 'stock_image_id'),
        importedBy,
        component: normalizeText(input.componentKey ?? section.type) || null,
        propPath: resolved.normalizedPath,
        qualifiedPropPath,
        nestedSectionPath: Array.isArray(input.nestedSectionPath) && input.nestedSectionPath.length > 0
            ? [...input.nestedSectionPath]
            : null,
        projectId: normalizeText(input.projectId) || null,
        sectionLocalId: normalizeText(section.localId) || null,
        pageSlug: normalizeText(input.pageSlug) || null,
        source: input.source,
        updatedAt: timestamp,
    };

    existingMediaFields[qualifiedPropPath] = {
        owner: mediaBinding.owner,
        asset_url: mediaBinding.assetUrl,
        media_id: mediaBinding.mediaId,
        provider: mediaBinding.provider,
        provider_image_id: mediaBinding.providerImageId,
        imported_by: mediaBinding.importedBy,
        component: mediaBinding.component,
        prop_path: mediaBinding.propPath,
        qualified_prop_path: mediaBinding.qualifiedPropPath,
        nested_section_path: mediaBinding.nestedSectionPath,
        project_id: mediaBinding.projectId,
        section_local_id: mediaBinding.sectionLocalId,
        page_slug: mediaBinding.pageSlug,
        source: mediaBinding.source,
        updated_at: mediaBinding.updatedAt,
    } satisfies Record<string, unknown>;

    existingSectionMeta.media_fields = existingMediaFields;
    existingSectionMeta.provenance = {
        ...existingProvenance,
        last_editor: importedBy,
        updated_at: timestamp,
        user_customized: true,
    };

    bindingMeta[CMS_SECTION_BINDING_META_KEY] = existingSectionMeta;

    return {
        section: {
            ...section,
            bindingMeta,
        },
        owner: resolved.owner,
        rawOwner: resolved.rawOwner,
    };
}
