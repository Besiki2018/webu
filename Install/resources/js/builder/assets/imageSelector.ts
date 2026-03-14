import type { StockImageImportRequest, StockImageOrientation } from './stockImageTypes';

export interface StockImageSelectionContext {
    fieldLabel: string;
    sectionType?: string | null;
    componentKey?: string | null;
    pageTitle?: string | null;
    projectName?: string | null;
    currentValue?: string | null;
    sectionLocalId?: string | null;
    pageSlug?: string | null;
}

function normalizeWords(value: string | null | undefined): string {
    return typeof value === 'string' ? value.trim().replace(/\s+/g, ' ') : '';
}

export function inferStockImageOrientation(context: StockImageSelectionContext): StockImageOrientation {
    const probe = [
        context.fieldLabel,
        context.sectionType,
        context.componentKey,
    ].map((value) => normalizeWords(value).toLowerCase()).join(' ');

    if (/(avatar|team|portrait|staff|testimonial|author|profile)/.test(probe)) {
        return 'portrait';
    }

    if (/(hero|banner|cover|background|cta)/.test(probe)) {
        return 'landscape';
    }

    return 'square';
}

export function inferStockImageQuery(context: StockImageSelectionContext): string {
    const fieldLabel = normalizeWords(context.fieldLabel).toLowerCase();
    const pageTitle = normalizeWords(context.pageTitle);
    const projectName = normalizeWords(context.projectName);
    const sectionType = normalizeWords(context.sectionType ?? context.componentKey).toLowerCase();

    const subject = projectName !== '' ? projectName : (pageTitle !== '' ? pageTitle : 'business');

    if (/(team|staff|portrait|avatar|profile)/.test(fieldLabel) || /(team|staff)/.test(sectionType)) {
        return `professional ${subject} portrait`;
    }

    if (/(gallery|photo grid|portfolio)/.test(fieldLabel) || /(gallery|portfolio)/.test(sectionType)) {
        return `${subject} lifestyle photography`;
    }

    if (/(feature|service|card)/.test(fieldLabel) || /(feature|service|card)/.test(sectionType)) {
        return `${subject} service photo`;
    }

    if (/(cta|contact|form|background)/.test(fieldLabel) || /(cta|contact|form)/.test(sectionType)) {
        return `${subject} consultation`;
    }

    return `modern ${subject}`;
}

export function buildStockImageImportContext(
    projectId: string,
    context: StockImageSelectionContext,
): Omit<StockImageImportRequest, 'provider' | 'image_id' | 'download_url'> {
    return {
        project_id: projectId,
        imported_by: 'visual_builder',
        section_local_id: context.sectionLocalId ?? null,
        component_key: context.componentKey ?? context.sectionType ?? null,
        page_slug: context.pageSlug ?? null,
        query: inferStockImageQuery(context),
    };
}
