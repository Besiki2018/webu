import {
    getShortDisplayName,
    resolveComponentRegistryKey,
} from '@/builder/componentRegistry';

import {
    buildCmsFieldOwnershipSnapshot,
    serializeFieldBindingForMetadata,
} from './contentAuthorityRules';
import type {
    CmsBindingConflictStatus,
    CmsBindingProvenance,
    CmsBindingProvenanceEditor,
    CmsBindingSyncDirection,
    CmsBoundSection,
    CmsFieldBinding,
    CmsFieldOwner,
} from './types';

export const CMS_SECTION_BINDING_META_KEY = 'webu_v2';

export interface CmsSectionBindingInput {
    sectionId?: string | null;
    localId: string;
    type: string;
    props: Record<string, unknown>;
    bindingMeta?: Record<string, unknown> | null;
}

export interface BuildCmsSectionBindingOptions {
    createdBy?: CmsBindingProvenanceEditor;
    lastEditor?: CmsBindingProvenanceEditor;
    timestamp?: string | null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function cloneRecord<T extends Record<string, unknown>>(value: T | null | undefined): T {
    if (!value) {
        return {} as T;
    }

    try {
        return JSON.parse(JSON.stringify(value)) as T;
    } catch {
        return { ...value };
    }
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

function mergeSectionOwner(owners: CmsFieldOwner[]): CmsFieldOwner {
    const uniqueOwners = uniqueStrings(owners) as CmsFieldOwner[];

    if (uniqueOwners.length === 0) {
        return 'builder_structure';
    }

    if (uniqueOwners.includes('mixed')) {
        return 'mixed';
    }

    if (uniqueOwners.length > 1) {
        return 'mixed';
    }

    return uniqueOwners[0] ?? 'builder_structure';
}

function deriveSyncDirection(owner: CmsFieldOwner): CmsBindingSyncDirection {
    switch (owner) {
        case 'cms':
        case 'builder_structure':
            return 'cms_to_workspace';
        case 'code':
            return 'none';
        case 'mixed':
        default:
            return 'bidirectional';
    }
}

function deriveConflictStatus(
    provenance: CmsBindingProvenance,
    owner: CmsFieldOwner,
): CmsBindingConflictStatus {
    if (provenance.requiresManualMerge) {
        return 'requires_manual_merge';
    }

    if (owner === 'mixed' && provenance.userCustomized) {
        return 'needs_workspace_sync';
    }

    return 'clean';
}

function readExistingProvenance(
    bindingMeta: Record<string, unknown> | null | undefined,
    fallbackEditor: CmsBindingProvenanceEditor,
    timestamp: string | null,
): CmsBindingProvenance {
    const metaRecord = isRecord(bindingMeta?.[CMS_SECTION_BINDING_META_KEY]) ? bindingMeta?.[CMS_SECTION_BINDING_META_KEY] as Record<string, unknown> : {};
    const provenanceRecord = isRecord(metaRecord.provenance) ? metaRecord.provenance : {};
    const createdBy = normalizeText(typeof provenanceRecord.created_by === 'string' ? provenanceRecord.created_by : null) as CmsBindingProvenanceEditor;
    const lastEditor = normalizeText(typeof provenanceRecord.last_editor === 'string' ? provenanceRecord.last_editor : null) as CmsBindingProvenanceEditor;

    return {
        createdBy: createdBy || fallbackEditor,
        lastEditor: lastEditor || fallbackEditor,
        createdAt: normalizeText(typeof provenanceRecord.created_at === 'string' ? provenanceRecord.created_at : null) || timestamp,
        updatedAt: normalizeText(typeof provenanceRecord.updated_at === 'string' ? provenanceRecord.updated_at : null) || timestamp,
        generatedDefault: provenanceRecord.generated_default !== false,
        userCustomized: provenanceRecord.user_customized === true,
        requiresManualMerge: provenanceRecord.requires_manual_merge === true,
    };
}

export function buildCmsSectionBinding(
    input: CmsSectionBindingInput,
    options: BuildCmsSectionBindingOptions = {},
): CmsBoundSection {
    const timestamp = options.timestamp ?? null;
    const registryKey = resolveComponentRegistryKey(input.type) ?? input.type;
    const ownership = buildCmsFieldOwnershipSnapshot(registryKey, input.props);
    const existingBindingMeta = cloneRecord(input.bindingMeta);
    const provenance = readExistingProvenance(
        existingBindingMeta,
        options.createdBy ?? options.lastEditor ?? 'system',
        timestamp,
    );

    provenance.lastEditor = options.lastEditor ?? provenance.lastEditor;
    provenance.updatedAt = timestamp ?? provenance.updatedAt;
    provenance.userCustomized = provenance.userCustomized || provenance.lastEditor !== provenance.createdBy;

    const contentOwner = mergeSectionOwner(ownership.fields.map((field) => field.owner));
    const syncDirection = deriveSyncDirection(contentOwner);
    const conflictStatus = deriveConflictStatus(provenance, contentOwner);
    const label = getShortDisplayName(registryKey, input.type);

    const fieldBindings: CmsFieldBinding[] = ownership.fields.map((field) => {
        const contentKey = field.propPath.split('.')[0] ?? field.propPath;

        return {
            key: field.propPath,
            contentKey,
            propPath: field.propPath,
            owner: field.owner,
            persistenceLocation: field.persistenceLocation,
            syncDirection: field.owner === 'mixed' ? 'bidirectional' : syncDirection,
            conflictStatus,
            fieldType: field.fieldType,
            sectionLocalId: input.localId,
            componentKey: registryKey,
            registryFieldPath: field.fieldDefinition?.path ?? null,
            group: field.fieldDefinition?.group ?? null,
            bindingCompatible: field.fieldDefinition?.bindingCompatible !== false,
            staticDefaultOnly: field.staticDefaultOnly,
            visualOnly: field.visualOnly,
            codeOwned: field.codeOwned,
            provenance,
            exampleValue: input.props[contentKey],
        };
    });

    const metadataPayload = {
        schema_version: 1,
        cms_backed: true,
        section_id: input.sectionId ?? null,
        section_local_id: input.localId,
        section_type: input.type,
        registry_key: registryKey,
        section_label: label,
        content_owner: contentOwner,
        sync_direction: syncDirection,
        conflict_status: conflictStatus,
        content_fields: ownership.contentFieldPaths,
        visual_fields: ownership.visualFieldPaths,
        code_owned_fields: ownership.codeFieldPaths,
        static_default_fields: ownership.staticDefaultFieldPaths,
        field_bindings: fieldBindings.map(serializeFieldBindingForMetadata),
        provenance: {
            created_by: provenance.createdBy,
            last_editor: provenance.lastEditor,
            created_at: provenance.createdAt,
            updated_at: provenance.updatedAt,
            generated_default: provenance.generatedDefault,
            user_customized: provenance.userCustomized,
            requires_manual_merge: provenance.requiresManualMerge,
        },
    } satisfies Record<string, unknown>;

    return {
        sectionId: input.sectionId ?? null,
        localId: input.localId,
        type: input.type,
        registryKey,
        label,
        cmsBacked: true,
        contentOwner,
        syncDirection,
        conflictStatus,
        fieldBindings,
        contentFieldPaths: ownership.contentFieldPaths,
        visualFieldPaths: ownership.visualFieldPaths,
        codeFieldPaths: ownership.codeFieldPaths,
        staticDefaultFieldPaths: ownership.staticDefaultFieldPaths,
        bindingMeta: {
            ...existingBindingMeta,
            [CMS_SECTION_BINDING_META_KEY]: metadataPayload,
        },
        provenance,
    };
}
