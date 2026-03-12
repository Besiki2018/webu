import type { AnalyzeResult, PageStructure } from '@/hooks/useAiSiteEditor';
import type { SelectedTargetContext } from '@/builder/selectedTargetContext';

export interface AiPageContextSection {
    id: string;
    type: string;
    label?: string;
    editable_fields?: string[];
    props?: Record<string, unknown>;
}

export interface AiResolvedPageContext {
    page: PageStructure | null;
    pageId: number | null;
    pageSlug: string | null;
    sections: AiPageContextSection[];
    componentTypes: string[];
}

function normalizeSlug(value: string | null | undefined): string | null {
    return typeof value === 'string' && value.trim() !== ''
        ? value.trim().toLowerCase()
        : null;
}

export function hasExplicitAiPageTarget(options?: {
    pageId?: number | null;
    pageSlug?: string | null;
}): boolean {
    return typeof options?.pageId === 'number' || normalizeSlug(options?.pageSlug) !== null;
}

export function resolveAnalyzePage(
    pages: PageStructure[] | undefined,
    options?: {
        pageId?: number | null;
        pageSlug?: string | null;
    },
): PageStructure | null {
    if (!Array.isArray(pages) || pages.length === 0) {
        return null;
    }

    const explicitTarget = hasExplicitAiPageTarget(options);

    if (typeof options?.pageId === 'number') {
        const matchedById = pages.find((page) => page.id === options.pageId) ?? null;
        if (matchedById) {
            return matchedById;
        }
    }

    const requestedSlug = normalizeSlug(options?.pageSlug);
    if (requestedSlug !== null) {
        const matchedBySlug = pages.find((page) => normalizeSlug(page.slug) === requestedSlug) ?? null;
        if (matchedBySlug) {
            return matchedBySlug;
        }
    }

    return explicitTarget ? null : (pages[0] ?? null);
}

export function buildAiResolvedPageContext(
    analyzeResult: AnalyzeResult,
    options?: {
        pageId?: number | null;
        pageSlug?: string | null;
    },
): AiResolvedPageContext {
    const page = resolveAnalyzePage(analyzeResult.pages, options);
    const componentTypes = Array.isArray(analyzeResult.available_components) && analyzeResult.available_components.length > 0
        ? analyzeResult.available_components.filter((value): value is string => typeof value === 'string' && value.trim() !== '')
        : (analyzeResult.pages?.flatMap((entry) => (
            Array.isArray(entry.sections)
                ? entry.sections
                    .map((section) => (typeof section.type === 'string' ? section.type.trim() : ''))
                    .filter((value): value is string => value !== '')
                : []
        )) ?? []);

    return {
        page,
        pageId: page?.id ?? (typeof options?.pageId === 'number' ? options.pageId : null),
        pageSlug: page?.slug ?? normalizeSlug(options?.pageSlug),
        sections: page?.sections?.map((section) => ({
            id: section.id,
            type: section.type,
            label: section.label,
            ...(Array.isArray(section.editable_fields) ? { editable_fields: section.editable_fields } : {}),
            ...(section.props && typeof section.props === 'object' ? { props: section.props } : {}),
        })) ?? [],
        componentTypes,
    };
}

export function buildAiPageContextPayload(
    analyzeResult: AnalyzeResult,
    options?: {
        pageId?: number | null;
        pageSlug?: string | null;
        locale?: string | null;
        selectedTarget?: SelectedTargetContext | null;
        recentEdits?: string | null;
        selectedSectionId?: string | null;
        selectedParameterPath?: string | null;
        selectedElementId?: string | null;
    },
): Record<string, unknown> | null {
    const resolved = buildAiResolvedPageContext(analyzeResult, options);

    if (!resolved.page && resolved.sections.length === 0) {
        return null;
    }

    return {
        page_slug: resolved.pageSlug ?? 'home',
        page_id: resolved.pageId,
        sections: resolved.sections,
        component_types: resolved.componentTypes,
        global_components: analyzeResult.global_components ?? [],
        locale: options?.locale ?? null,
        ...(options?.recentEdits && options.recentEdits.trim() !== '' ? { recent_edits: options.recentEdits.trim().slice(0, 500) } : {}),
        ...(options?.selectedTarget ? { selected_target: options.selectedTarget } : {}),
        ...(options?.selectedSectionId ? { selected_section_id: options.selectedSectionId } : {}),
        ...(options?.selectedParameterPath ? { selected_parameter_path: options.selectedParameterPath } : {}),
        ...(options?.selectedElementId ? { selected_element_id: options.selectedElementId } : {}),
    };
}
