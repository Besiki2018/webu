import type {
    GeneratedPage,
    GeneratedProjectGraph,
    GeneratedSection,
    WorkspaceManifest,
    WorkspaceManifestFileOwnership,
} from '@/builder/codegen/types';
import { cloneRecordData } from '@/builder/runtime/clone';

import { CMS_PAGE_BINDING_EXTRA_CONTENT_KEY } from './cmsPageBinding';
import type { CmsBindingModel, CmsBoundSection, CmsFieldOwner } from './types';
import { serializeCmsBindingModel } from './cmsBindingModel';

function cloneRecord<T extends Record<string, unknown>>(value: T | null | undefined): T {
    return cloneRecordData(value);
}

function uniqueStrings(values: Array<string | null | undefined>): string[] {
    return Array.from(new Set(
        values
            .filter((value): value is string => typeof value === 'string' && value.trim() !== '')
            .map((value) => value.trim()),
    ));
}

function mergeOwner(owners: CmsFieldOwner[]): CmsFieldOwner {
    const uniqueOwners = uniqueStrings(owners) as CmsFieldOwner[];

    if (uniqueOwners.length === 0) {
        return 'cms';
    }

    if (uniqueOwners.includes('mixed') || uniqueOwners.length > 1) {
        return 'mixed';
    }

    return uniqueOwners[0] ?? 'cms';
}

function findBoundSection(
    sections: CmsBoundSection[],
    section: Pick<GeneratedSection, 'localId' | 'id'>,
): CmsBoundSection | null {
    return sections.find((candidate) => (
        candidate.localId === section.localId
        || (candidate.sectionId !== null && candidate.sectionId === section.id)
    )) ?? null;
}

function applyBoundSectionToGeneratedSection(
    section: GeneratedSection,
    boundSection: CmsBoundSection | null,
): GeneratedSection {
    if (!boundSection) {
        return section;
    }

    return {
        ...section,
        cmsBacked: boundSection.cmsBacked,
        contentOwner: boundSection.contentOwner,
        cmsFieldPaths: [...boundSection.contentFieldPaths],
        visualFieldPaths: [...boundSection.visualFieldPaths],
        codeFieldPaths: [...boundSection.codeFieldPaths],
        syncDirection: boundSection.syncDirection,
        conflictStatus: boundSection.conflictStatus,
        metadata: {
            ...cloneRecord(section.metadata),
            bindingMeta: cloneRecord(boundSection.bindingMeta),
            cmsBinding: {
                local_id: boundSection.localId,
                registry_key: boundSection.registryKey,
                content_owner: boundSection.contentOwner,
                content_fields: boundSection.contentFieldPaths,
                visual_fields: boundSection.visualFieldPaths,
                code_owned_fields: boundSection.codeFieldPaths,
                sync_direction: boundSection.syncDirection,
                conflict_status: boundSection.conflictStatus,
            },
        },
    };
}

export function applyCmsBindingModelToGeneratedPage(
    page: GeneratedPage,
    bindingModel: CmsBindingModel,
): GeneratedPage {
    const sections = page.sections.map((section) => applyBoundSectionToGeneratedSection(
        section,
        findBoundSection(bindingModel.sections, section),
    ));
    const pageOwner = mergeOwner(bindingModel.sections.map((section) => section.contentOwner));

    return {
        ...page,
        cmsBacked: true,
        contentOwner: pageOwner,
        cmsFieldPaths: uniqueStrings(bindingModel.sections.flatMap((section) => section.contentFieldPaths)),
        visualFieldPaths: uniqueStrings(bindingModel.sections.flatMap((section) => section.visualFieldPaths)),
        codeFieldPaths: uniqueStrings(bindingModel.sections.flatMap((section) => section.codeFieldPaths)),
        syncDirection: bindingModel.page.syncDirection,
        conflictStatus: bindingModel.page.conflictStatus,
        sections,
        metadata: {
            ...cloneRecord(page.metadata),
            [CMS_PAGE_BINDING_EXTRA_CONTENT_KEY]: serializeCmsBindingModel(bindingModel),
        },
    };
}

export function applyCmsBindingModelToProjectGraph(
    graph: GeneratedProjectGraph,
    bindingModel: CmsBindingModel,
): GeneratedProjectGraph {
    return {
        ...graph,
        pages: graph.pages.map((page) => (
            page.id === bindingModel.page.pageId || page.slug === bindingModel.page.slug
                ? applyCmsBindingModelToGeneratedPage(page, bindingModel)
                : page
        )),
        metadata: {
            ...cloneRecord(graph.metadata),
            [CMS_PAGE_BINDING_EXTRA_CONTENT_KEY]: serializeCmsBindingModel(bindingModel),
        },
    };
}

function aggregateSectionBindingsForEntry(
    entry: WorkspaceManifestFileOwnership,
    bindingModel: CmsBindingModel,
): CmsBoundSection[] {
    const localIds = new Set(entry.sectionLocalIds);
    const componentKeys = new Set(entry.componentKeys);

    const matched = bindingModel.sections.filter((section) => (
        localIds.has(section.localId)
        || (section.registryKey !== null && componentKeys.has(section.registryKey))
    ));

    if (matched.length > 0) {
        return matched;
    }

    if (entry.ownerType === 'page' || entry.ownerType === 'layout') {
        return [...bindingModel.sections];
    }

    return [];
}

function applyBindingToOwnershipEntry(
    entry: WorkspaceManifestFileOwnership,
    bindingModel: CmsBindingModel,
): WorkspaceManifestFileOwnership {
    const matchedSections = aggregateSectionBindingsForEntry(entry, bindingModel);
    if (matchedSections.length === 0) {
        return entry;
    }

    return {
        ...entry,
        cmsBacked: true,
        contentOwner: mergeOwner(matchedSections.map((section) => section.contentOwner)),
        cmsFieldPaths: uniqueStrings(matchedSections.flatMap((section) => section.contentFieldPaths)),
        visualFieldPaths: uniqueStrings(matchedSections.flatMap((section) => section.visualFieldPaths)),
        codeFieldPaths: uniqueStrings(matchedSections.flatMap((section) => section.codeFieldPaths)),
        syncDirection: matchedSections.some((section) => section.syncDirection === 'bidirectional')
            ? 'bidirectional'
            : bindingModel.page.syncDirection,
        conflictStatus: matchedSections.some((section) => section.conflictStatus === 'requires_manual_merge')
            ? 'requires_manual_merge'
            : bindingModel.page.conflictStatus,
    };
}

export function applyCmsBindingModelToWorkspaceManifest(
    manifest: WorkspaceManifest,
    bindingModel: CmsBindingModel,
): WorkspaceManifest {
    const pageCmsFieldPaths = uniqueStrings(bindingModel.sections.flatMap((section) => section.contentFieldPaths));
    const pageVisualFieldPaths = uniqueStrings(bindingModel.sections.flatMap((section) => section.visualFieldPaths));
    const pageCodeFieldPaths = uniqueStrings(bindingModel.sections.flatMap((section) => section.codeFieldPaths));

    return {
        ...manifest,
        generatedPages: manifest.generatedPages.map((page) => (
            page.pageId === bindingModel.page.pageId || page.slug === bindingModel.page.slug
                ? {
                    ...page,
                    cmsBacked: true,
                    contentOwner: bindingModel.page.contentOwner,
                    cmsFieldPaths: pageCmsFieldPaths,
                    visualFieldPaths: pageVisualFieldPaths,
                    codeFieldPaths: pageCodeFieldPaths,
                    syncDirection: bindingModel.page.syncDirection,
                    conflictStatus: bindingModel.page.conflictStatus,
                }
                : page
        )),
        fileOwnership: manifest.fileOwnership.map((entry) => applyBindingToOwnershipEntry(entry, bindingModel)),
        cmsBinding: serializeCmsBindingModel(bindingModel),
    };
}
