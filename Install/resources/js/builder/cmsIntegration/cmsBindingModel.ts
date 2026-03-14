import type { BuilderPageModel } from '@/builder/model/pageModel';
import { cloneRecordData } from '@/builder/runtime/clone';

import { buildCmsPageBinding, CMS_PAGE_BINDING_EXTRA_CONTENT_KEY, serializeCmsPageBindingRootMetadata } from './cmsPageBinding';
import { buildCmsSectionBinding } from './cmsSectionBinding';
import type {
    CmsBindingModel,
    CmsBindingProvenanceEditor,
    CmsBoundSection,
} from './types';

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function cloneRecord<T extends Record<string, unknown>>(value: T | null | undefined): T {
    return cloneRecordData(value);
}

function normalizeText(value: string | null | undefined, fallback = ''): string {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback;
}

export interface BuildCmsBindingModelInput {
    page: {
        id: string | number | null;
        slug: string | null;
        title: string | null;
        seoTitle?: string | null;
        seoDescription?: string | null;
    };
    model: BuilderPageModel;
    editor?: CmsBindingProvenanceEditor;
    createdBy?: CmsBindingProvenanceEditor;
    timestamp?: string | null;
    metadata?: Record<string, unknown> | null;
}

export function serializeCmsBindingModel(bindingModel: CmsBindingModel): Record<string, unknown> {
    return {
        schema_version: bindingModel.schemaVersion,
        authorities: bindingModel.authorities,
        preview_hydration: bindingModel.previewHydration,
        page: serializeCmsPageBindingRootMetadata(bindingModel.page),
    };
}

export function buildCmsBindingModelFromBuilderPageModel(
    input: BuildCmsBindingModelInput,
): CmsBindingModel {
    const timestamp = input.timestamp ?? null;
    const sections: CmsBoundSection[] = input.model.sections.map((section) => buildCmsSectionBinding({
        sectionId: `${input.page.id ?? input.page.slug ?? 'page'}:${section.localId}`,
        localId: section.localId,
        type: section.type,
        props: section.props,
        bindingMeta: section.bindingMeta,
    }, {
        createdBy: input.createdBy ?? input.editor ?? 'system',
        lastEditor: input.editor ?? input.createdBy ?? 'system',
        timestamp,
    }));

    const page = buildCmsPageBinding({
        pageId: input.page.id !== null && input.page.id !== undefined ? String(input.page.id) : null,
        slug: input.page.slug,
        title: input.page.title,
        seoTitle: input.page.seoTitle ?? null,
        seoDescription: input.page.seoDescription ?? null,
        sections,
        createdBy: input.createdBy ?? input.editor ?? 'system',
        lastEditor: input.editor ?? input.createdBy ?? 'system',
        timestamp,
        metadata: input.metadata ?? null,
    });

    return {
        schemaVersion: 1,
        page,
        sections,
        authorities: {
            content: 'cms',
            layout: 'cms_revision',
            code: 'workspace',
            preview: 'derived',
        },
        previewHydration: {
            source: 'cms_revision+workspace',
            builderReady: true,
        },
        metadata: {
            ...(input.metadata ?? {}),
            [CMS_PAGE_BINDING_EXTRA_CONTENT_KEY]: serializeCmsPageBindingRootMetadata(page),
        },
    };
}

export function applyCmsBindingModelToBuilderPageModel(
    model: BuilderPageModel,
    bindingModel: CmsBindingModel,
): BuilderPageModel {
    const sectionByLocalId = new Map(bindingModel.sections.map((section) => [section.localId, section]));
    const nextExtraContent = cloneRecord(model.extraContent);
    nextExtraContent[CMS_PAGE_BINDING_EXTRA_CONTENT_KEY] = serializeCmsPageBindingRootMetadata(bindingModel.page);

    return {
        ...model,
        extraContent: nextExtraContent,
        sections: model.sections.map((section) => {
            const boundSection = sectionByLocalId.get(section.localId);
            if (!boundSection) {
                return section;
            }

            return {
                ...section,
                bindingMeta: cloneRecord(boundSection.bindingMeta),
            };
        }),
    };
}

export function applyCmsBindingModelToContentJson(
    contentJson: Record<string, unknown>,
    bindingModel: CmsBindingModel,
): Record<string, unknown> {
    const sectionByLocalId = new Map(bindingModel.sections.map((section) => [section.localId, section]));
    const nextContent = cloneRecord(contentJson);
    const rawSections = Array.isArray(nextContent.sections) ? nextContent.sections : [];

    nextContent.sections = rawSections.map((entry) => {
        if (!isRecord(entry)) {
            return entry;
        }

        const localId = normalizeText(typeof entry.localId === 'string' ? entry.localId : null);
        const boundSection = localId !== '' ? sectionByLocalId.get(localId) : null;
        if (!boundSection) {
            return entry;
        }

        return {
            ...entry,
            binding: cloneRecord(boundSection.bindingMeta),
        };
    });
    nextContent[CMS_PAGE_BINDING_EXTRA_CONTENT_KEY] = serializeCmsPageBindingRootMetadata(bindingModel.page);

    return nextContent;
}
