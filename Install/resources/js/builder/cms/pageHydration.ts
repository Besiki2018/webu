import { isRecord } from '@/builder/state/sectionProps';

export type CmsPageEditorMode = 'builder' | 'text';

export interface CmsPageHydrationSectionTemplate {
    type: string;
    props?: Record<string, unknown>;
}

export interface CmsPageHydrationRevisionLike {
    content_json?: unknown;
}

export interface CmsPageHydrationDetailLike {
    page: {
        slug: string;
    };
    latest_revision?: CmsPageHydrationRevisionLike | null;
    published_revision?: CmsPageHydrationRevisionLike | null;
}

export interface CmsResolvedPageHydrationContent {
    contentRecord: Record<string, unknown>;
    rawSections: unknown[];
    resolvedEditorMode: CmsPageEditorMode;
    resolvedTextEditorHtml: string;
}

export function resolveCmsPageHydrationContent(
    detail: CmsPageHydrationDetailLike,
    options: {
        isEmbeddedMode: boolean;
        templateSectionsBySlug: Map<string, CmsPageHydrationSectionTemplate[]>;
    },
): CmsResolvedPageHydrationContent {
    const contentSource = detail.latest_revision?.content_json
        ?? detail.published_revision?.content_json
        ?? {};

    const contentRecord = isRecord(contentSource)
        ? contentSource
        : {};

    const resolvedEditorMode: CmsPageEditorMode = options.isEmbeddedMode
        ? 'builder'
        : (contentRecord.editor_mode === 'text' ? 'text' : 'builder');
    const resolvedTextEditorHtml = typeof contentRecord.text_editor_html === 'string'
        ? contentRecord.text_editor_html
        : '';

    const hasExplicitSectionsArray = Array.isArray(contentRecord.sections);
    let rawSections: unknown[] = hasExplicitSectionsArray
        ? (contentRecord.sections as unknown[])
        : [];

    const pageSlug = detail.page.slug.trim().toLowerCase();
    const templateSections = options.templateSectionsBySlug.get(pageSlug) ?? [];
    const hasPersistedRevision = Boolean(detail.latest_revision || detail.published_revision);

    // Explicit empty sections from a saved revision are authoritative. Only legacy
    // never-revised content should fall back to template defaults.
    if (!hasPersistedRevision && !hasExplicitSectionsArray && rawSections.length === 0 && templateSections.length > 0) {
        rawSections = templateSections.map((section) => ({
            type: section.type,
            props: section.props,
        }));
    }

    return {
        contentRecord,
        rawSections,
        resolvedEditorMode,
        resolvedTextEditorHtml,
    };
}
