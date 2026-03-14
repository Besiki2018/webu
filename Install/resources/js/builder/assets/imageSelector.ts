import type { StockImageImportRequest, StockImageOrientation } from './stockImageTypes';
import { resolveComponentImageSlot, type ComponentImageRole } from '@/builder/componentImageSlots';

export interface StockImageSelectionContext {
    fieldLabel: string;
    fieldPath?: string | null;
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

function resolveImageRole(context: StockImageSelectionContext): ComponentImageRole | null {
    const slot = resolveComponentImageSlot(context.componentKey ?? context.sectionType, context.fieldPath);
    if (slot) {
        return slot.role;
    }

    const probe = [
        context.fieldLabel,
        context.fieldPath,
        context.sectionType,
        context.componentKey,
    ].map((value) => normalizeWords(value).toLowerCase()).join(' ');

    if (/(logo)/.test(probe)) {
        return 'logo';
    }

    if (/(avatar|team|portrait|staff|testimonial|author|profile)/.test(probe)) {
        return 'avatar';
    }

    if (/(gallery|portfolio|grid)/.test(probe)) {
        return 'gallery';
    }

    if (/(feature|service|card)/.test(probe)) {
        return 'card';
    }

    if (/(cta|contact|form)/.test(probe)) {
        return 'cta';
    }

    if (/(hero|banner|cover|overlay)/.test(probe)) {
        return 'hero';
    }

    if (/(background)/.test(probe)) {
        return 'background';
    }

    return null;
}

export function inferStockImageOrientation(context: StockImageSelectionContext): StockImageOrientation {
    const slot = resolveComponentImageSlot(context.componentKey ?? context.sectionType, context.fieldPath);
    if (slot) {
        return slot.orientation;
    }

    const probe = [
        context.fieldLabel,
        context.fieldPath,
        context.sectionType,
        context.componentKey,
    ].map((value) => normalizeWords(value).toLowerCase()).join(' ');

    if (/(logo)/.test(probe)) {
        return 'square';
    }

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
    const role = resolveImageRole(context);

    const subject = projectName !== '' ? projectName : (pageTitle !== '' ? pageTitle : 'business');

    switch (role) {
        case 'logo':
            return `${subject} logo icon`;
        case 'avatar':
            return `professional ${subject} portrait`;
        case 'gallery':
            return `${subject} lifestyle photography`;
        case 'card':
            return `${subject} service photo`;
        case 'cta':
            return `${subject} consultation`;
        case 'background':
        case 'hero':
            return `modern ${subject}`;
        case 'content':
            return `${subject} editorial photo`;
        default:
            break;
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
        prop_path: context.fieldPath ?? null,
        page_slug: context.pageSlug ?? null,
        query: inferStockImageQuery(context),
    };
}
