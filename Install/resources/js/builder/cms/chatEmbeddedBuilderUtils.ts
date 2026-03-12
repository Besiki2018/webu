export interface GeneratedBuilderPageIdentity {
    page_id: string | number | null;
    slug?: string | null;
    title?: string | null;
}

export function buildFallbackSectionProps(label: string, previewText: string): Record<string, unknown> {
    const nextProps: Record<string, unknown> = {};
    const trimmedLabel = label.trim();
    const trimmedPreviewText = previewText.trim();

    if (trimmedLabel !== '') {
        nextProps.title = trimmedLabel;
        nextProps.headline = trimmedLabel;
    }

    if (trimmedPreviewText !== '') {
        nextProps.description = trimmedPreviewText;
    }

    return nextProps;
}

function sanitizeGeneratedPageSegment(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export function buildGeneratedPagePath(page: Pick<GeneratedBuilderPageIdentity, 'page_id' | 'slug' | 'title'>, index: number): string {
    const candidate = page.slug ?? page.title ?? (page.page_id !== null ? `page-${page.page_id}` : `page-${index + 1}`);
    const segment = sanitizeGeneratedPageSegment(candidate || '') || `page-${index + 1}`;
    return `derived-preview/pages/${segment}/Page.tsx`;
}
