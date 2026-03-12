import {
    builderBridgePagesMatch,
    hasBuilderBridgePageIdentity,
    normalizeBuilderBridgePageIdentity,
    type BuilderBridgePageIdentity,
} from '@/builder/cms/embeddedBuilderBridgeContract';

export interface WorkspaceBuilderStructureItem {
    localId: string;
    sectionKey: string;
    label: string;
    previewText: string;
    props: Record<string, unknown>;
}

export interface WorkspaceBuilderCodeSection {
    localId: string;
    type: string;
    props: Record<string, unknown>;
    propsText: string;
}

export interface WorkspaceBuilderCodePage {
    path: string;
    pageId: number | null;
    slug: string | null;
    title: string | null;
    revisionSource: string | null;
    sections: WorkspaceBuilderCodeSection[];
}

export interface WorkspaceBuilderStructureSnapshot {
    page: BuilderBridgePageIdentity;
    sections: WorkspaceBuilderCodeSection[];
}

export function getWorkspaceBuilderPageIdentity(
    page: {
        pageId?: number | null;
        slug?: string | null;
        title?: string | null;
    } | null | undefined,
): BuilderBridgePageIdentity {
    return normalizeBuilderBridgePageIdentity({
        pageId: page?.pageId ?? null,
        pageSlug: page?.slug ?? null,
        pageTitle: page?.title ?? null,
    });
}

export function resolveWorkspaceBuilderActivePage<TPage extends Pick<WorkspaceBuilderCodePage, 'pageId' | 'slug'>>(
    pages: TPage[],
    page: BuilderBridgePageIdentity | null | undefined,
): TPage | null {
    if (pages.length === 0) {
        return null;
    }

    const normalizedPage = normalizeBuilderBridgePageIdentity(page);
    if (!hasBuilderBridgePageIdentity(normalizedPage)) {
        return pages[0] ?? null;
    }

    return pages.find((candidate) => (
        builderBridgePagesMatch(normalizedPage, getWorkspaceBuilderPageIdentity(candidate))
    )) ?? null;
}

export function buildWorkspaceStructureItemsFromCodePage(
    page: Pick<WorkspaceBuilderCodePage, 'sections'> | null | undefined,
    options: {
        getDisplayLabel: (sectionKey: string) => string;
        buildPreviewText: (props: Record<string, unknown>, fallback: string) => string;
    },
): WorkspaceBuilderStructureItem[] {
    const sections = page?.sections ?? [];

    return sections.map((section) => {
        const sectionKey = (section.type ?? '').trim() || 'section';
        const label = options.getDisplayLabel(sectionKey);

        return {
            localId: section.localId ?? `generated-section-${sectionKey}`,
            sectionKey,
            label,
            previewText: options.buildPreviewText(section.props ?? {}, label || sectionKey),
            props: section.props ?? {},
        };
    });
}

export function upsertWorkspaceBuilderCodePages(
    current: WorkspaceBuilderCodePage[],
    snapshot: WorkspaceBuilderStructureSnapshot,
    options: {
        buildPagePath: (page: BuilderBridgePageIdentity, index: number) => string;
    },
): WorkspaceBuilderCodePage[] {
    const { page, sections } = snapshot;
    const targetIndex = current.findIndex((candidate) => (
        builderBridgePagesMatch(page, getWorkspaceBuilderPageIdentity(candidate))
    ));

    if (targetIndex >= 0) {
        const nextPages = [...current];
        nextPages[targetIndex] = {
            ...nextPages[targetIndex],
            slug: page.pageSlug ?? nextPages[targetIndex].slug,
            title: page.pageTitle ?? nextPages[targetIndex].title,
            revisionSource: 'draft',
            sections,
        };

        return nextPages;
    }

    if (current.length === 1 && !hasBuilderBridgePageIdentity(page)) {
        return [{
            ...current[0],
            revisionSource: 'draft',
            sections,
        }];
    }

    return [
        ...current,
        {
            path: options.buildPagePath(page, current.length),
            pageId: page.pageId,
            slug: page.pageSlug,
            title: page.pageTitle,
            revisionSource: 'draft',
            sections,
        },
    ];
}
