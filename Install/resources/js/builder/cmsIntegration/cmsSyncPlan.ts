import type {
    CmsBindingModel,
    CmsBindingProvenance,
    CmsEditRoute,
    CmsFieldOwner,
    CmsSyncPlan,
    CmsSyncPlanFileEffect,
    CmsSyncPlanSectionEntry,
} from './types';

function mergeFileOwner(owners: CmsFieldOwner[]): CmsFieldOwner {
    const uniqueOwners = Array.from(new Set(owners));

    if (uniqueOwners.length === 0) {
        return 'cms';
    }

    if (uniqueOwners.includes('mixed') || uniqueOwners.length > 1) {
        return 'mixed';
    }

    return uniqueOwners[0] ?? 'cms';
}

function cloneProvenance(provenance: CmsBindingProvenance): CmsBindingProvenance {
    return { ...provenance };
}

export function buildCmsSyncPlan(
    bindingModel: CmsBindingModel,
    input: {
        route: CmsEditRoute;
        filePaths?: string[];
    },
): CmsSyncPlan {
    const sectionEffects: CmsSyncPlanSectionEntry[] = bindingModel.sections.map((section) => ({
        localId: section.localId,
        sectionType: section.type,
        owner: section.contentOwner,
        contentFieldPaths: [...section.contentFieldPaths],
        visualFieldPaths: [...section.visualFieldPaths],
        codeFieldPaths: [...section.codeFieldPaths],
        syncDirection: section.syncDirection,
        conflictStatus: section.conflictStatus,
    }));

    const aggregatedOwner = mergeFileOwner(bindingModel.sections.map((section) => section.contentOwner));
    const fileEffects: CmsSyncPlanFileEffect[] = (input.filePaths ?? []).map((path) => ({
        path,
        owner: aggregatedOwner,
        syncDirection: bindingModel.page.syncDirection,
        conflictStatus: bindingModel.page.conflictStatus,
        cmsBacked: true,
        sectionLocalIds: bindingModel.sections.map((section) => section.localId),
        contentFieldPaths: bindingModel.sections.flatMap((section) => section.contentFieldPaths),
        visualFieldPaths: bindingModel.sections.flatMap((section) => section.visualFieldPaths),
        codeFieldPaths: bindingModel.sections.flatMap((section) => section.codeFieldPaths),
    }));

    return {
        schemaVersion: 1,
        contentAuthority: 'cms',
        layoutAuthority: 'cms_revision',
        codeAuthority: 'workspace',
        previewAuthority: 'derived',
        route: input.route,
        requiresCmsRevisionSave: input.route !== 'code_change',
        requiresWorkspaceMirror: true,
        requiresPreviewRefresh: true,
        pageId: bindingModel.page.pageId,
        pageSlug: bindingModel.page.slug,
        sectionEffects,
        fileEffects,
        provenance: cloneProvenance(bindingModel.page.provenance),
    };
}
