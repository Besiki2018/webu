import type {
    CmsBindingConflictStatus,
    CmsBindingProvenance,
    CmsBindingProvenanceEditor,
    CmsBindingSyncDirection,
    CmsBoundPage,
    CmsBoundSection,
    CmsFieldOwner,
    CmsPageFieldBinding,
} from './types';

export const CMS_PAGE_BINDING_EXTRA_CONTENT_KEY = 'webu_cms_binding';

export interface CmsPageBindingInput {
    pageId: string | null;
    slug: string | null;
    title: string | null;
    seoTitle?: string | null;
    seoDescription?: string | null;
    sections: CmsBoundSection[];
    createdBy?: CmsBindingProvenanceEditor;
    lastEditor?: CmsBindingProvenanceEditor;
    timestamp?: string | null;
    metadata?: Record<string, unknown> | null;
}

function normalizeText(value: string | null | undefined, fallback = ''): string {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback;
}

function uniqueStrings(values: string[]): string[] {
    return Array.from(new Set(values.filter((value) => value.trim() !== '')));
}

function mergePageOwner(owners: CmsFieldOwner[]): CmsFieldOwner {
    const uniqueOwners = uniqueStrings(owners) as CmsFieldOwner[];

    if (uniqueOwners.length === 0) {
        return 'cms';
    }

    if (uniqueOwners.includes('mixed') || uniqueOwners.length > 1) {
        return 'mixed';
    }

    return uniqueOwners[0] ?? 'cms';
}

function derivePageSyncDirection(owner: CmsFieldOwner): CmsBindingSyncDirection {
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

function buildPageFieldBindings(): CmsPageFieldBinding[] {
    return [
        {
            key: 'page.title',
            owner: 'cms',
            persistenceLocation: 'pages.title',
            syncDirection: 'cms_to_workspace',
            conflictStatus: 'clean',
        },
        {
            key: 'page.slug',
            owner: 'builder_structure',
            persistenceLocation: 'pages.slug',
            syncDirection: 'cms_to_workspace',
            conflictStatus: 'clean',
        },
        {
            key: 'page.seo_title',
            owner: 'cms',
            persistenceLocation: 'pages.seo_title',
            syncDirection: 'cms_to_workspace',
            conflictStatus: 'clean',
        },
        {
            key: 'page.seo_description',
            owner: 'cms',
            persistenceLocation: 'pages.seo_description',
            syncDirection: 'cms_to_workspace',
            conflictStatus: 'clean',
        },
    ];
}

export function serializeCmsPageBindingRootMetadata(page: CmsBoundPage): Record<string, unknown> {
    return {
        schema_version: 1,
        authorities: {
            content: 'cms',
            layout: 'cms_revision',
            code: 'workspace',
            preview: 'derived',
        },
        page: {
            page_id: page.pageId,
            slug: page.slug,
            title: page.title,
            seo_title: page.seoTitle,
            seo_description: page.seoDescription,
            content_owner: page.contentOwner,
            sync_direction: page.syncDirection,
            conflict_status: page.conflictStatus,
            page_fields: page.pageFields,
        },
        sections: page.sections.map((section) => ({
            local_id: section.localId,
            type: section.type,
            registry_key: section.registryKey,
            content_owner: section.contentOwner,
            cms_backed: section.cmsBacked,
            content_fields: section.contentFieldPaths,
            visual_fields: section.visualFieldPaths,
            code_owned_fields: section.codeFieldPaths,
            static_default_fields: section.staticDefaultFieldPaths,
        })),
        provenance: {
            created_by: page.provenance.createdBy,
            last_editor: page.provenance.lastEditor,
            created_at: page.provenance.createdAt,
            updated_at: page.provenance.updatedAt,
            generated_default: page.provenance.generatedDefault,
            user_customized: page.provenance.userCustomized,
            requires_manual_merge: page.provenance.requiresManualMerge,
        },
    };
}

export function buildCmsPageBinding(input: CmsPageBindingInput): CmsBoundPage {
    const pageFields = buildPageFieldBindings();
    const timestamp = input.timestamp ?? null;
    const pageOwner = mergePageOwner([
        ...input.sections.map((section) => section.contentOwner),
        ...pageFields.map((field) => field.owner),
    ]);
    const syncDirection = derivePageSyncDirection(pageOwner);
    const provenance: CmsBindingProvenance = {
        createdBy: input.createdBy ?? input.lastEditor ?? 'system',
        lastEditor: input.lastEditor ?? input.createdBy ?? 'system',
        createdAt: timestamp,
        updatedAt: timestamp,
        generatedDefault: true,
        userCustomized: (input.lastEditor ?? input.createdBy ?? 'system') !== 'ai',
        requiresManualMerge: pageOwner === 'mixed' && input.sections.some((section) => section.conflictStatus === 'requires_manual_merge'),
    };
    const conflictStatus: CmsBindingConflictStatus = provenance.requiresManualMerge
        ? 'requires_manual_merge'
        : (pageOwner === 'mixed' ? 'needs_workspace_sync' : 'clean');

    return {
        pageId: input.pageId,
        slug: normalizeText(input.slug, 'home').toLowerCase(),
        title: normalizeText(input.title, 'Untitled Page'),
        seoTitle: normalizeText(input.seoTitle) || null,
        seoDescription: normalizeText(input.seoDescription) || null,
        cmsBacked: true,
        contentOwner: pageOwner,
        syncDirection,
        conflictStatus,
        pageFields,
        sections: input.sections,
        provenance,
        metadata: {
            ...(input.metadata ?? {}),
            [CMS_PAGE_BINDING_EXTRA_CONTENT_KEY]: serializeCmsPageBindingRootMetadata({
                pageId: input.pageId,
                slug: normalizeText(input.slug, 'home').toLowerCase(),
                title: normalizeText(input.title, 'Untitled Page'),
                seoTitle: normalizeText(input.seoTitle) || null,
                seoDescription: normalizeText(input.seoDescription) || null,
                cmsBacked: true,
                contentOwner: pageOwner,
                syncDirection,
                conflictStatus,
                pageFields,
                sections: input.sections,
                provenance,
                metadata: input.metadata ?? {},
            }),
        },
    };
}
