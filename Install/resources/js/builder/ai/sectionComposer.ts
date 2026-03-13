import { getCatalogEntry, type AiComponentCatalogEntry } from './componentCatalog';
import type { AiProjectType } from './projectTypeDetector';
import { setValueAtPath } from '../state/sectionProps';

export interface SectionComposerContext {
    prompt: string;
    projectType: AiProjectType;
    brandName?: string | null;
    tone?: string | null;
    sectionIndex?: number;
    totalSections?: number;
}

function normalizePrompt(prompt: string): string {
    return prompt.toLowerCase().trim();
}

function titleCase(value: string): string {
    return value
        .split(/\s+/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function extractBrandName(prompt: string, explicitBrandName?: string | null): string | null {
    if (typeof explicitBrandName === 'string' && explicitBrandName.trim() !== '') {
        return explicitBrandName.trim();
    }

    const match = prompt.match(/\b(?:for|called|named)\s+([a-z0-9][a-z0-9\s&'-]{1,40})/i);
    if (!match?.[1]) {
        return null;
    }

    return titleCase(match[1].trim().split(/\s+/).slice(0, 3).join(' '));
}

function inferSubject(prompt: string, projectType: AiProjectType): string {
    const normalized = normalizePrompt(prompt);
    if (normalized.includes('cosmetic') || normalized.includes('beauty') || normalized.includes('skincare')) return 'beauty';
    if (normalized.includes('veterinary') || normalized.includes('pet')) return 'pet care';
    if (normalized.includes('restaurant') || normalized.includes('food')) return 'dining';
    if (normalized.includes('furniture')) return 'furniture';
    if (normalized.includes('clinic') || normalized.includes('medical')) return 'care';
    if (normalized.includes('portfolio')) return 'creative work';

    switch (projectType) {
        case 'ecommerce':
            return 'products';
        case 'clinic':
            return 'care';
        case 'restaurant':
            return 'dining';
        case 'portfolio':
            return 'work';
        default:
            return 'services';
    }
}

function getPrimaryCta(projectType: AiProjectType): { label: string; href: string } {
    switch (projectType) {
        case 'ecommerce':
            return { label: 'Shop now', href: '/shop' };
        case 'booking':
        case 'clinic':
        case 'restaurant':
            return { label: 'Book now', href: '/book' };
        case 'portfolio':
            return { label: 'View work', href: '/work' };
        case 'blog':
            return { label: 'Read articles', href: '/blog' };
        default:
            return { label: 'Get started', href: '/contact' };
    }
}

function resolveFieldPath(entry: AiComponentCatalogEntry, aliases: string[]): string | null {
    for (const alias of aliases) {
        const exact = entry.propsSchema.find((field) => field.path === alias);
        if (exact) {
            return exact.path;
        }
    }

    for (const alias of aliases) {
        const partial = entry.propsSchema.find((field) => field.path.split('.').pop() === alias);
        if (partial) {
            return partial.path;
        }
    }

    return null;
}

function isCompatibleValue(fieldType: string, value: unknown): boolean {
    switch (fieldType) {
        case 'text':
        case 'richtext':
        case 'image':
        case 'video':
        case 'icon':
        case 'link':
        case 'menu':
        case 'button-group':
        case 'color':
        case 'alignment':
        case 'radius':
        case 'shadow':
        case 'overlay':
        case 'visibility':
        case 'select':
        case 'layout-variant':
        case 'style-variant':
            return typeof value === 'string' || value === null || Array.isArray(value) || typeof value === 'object';
        case 'number':
            return typeof value === 'number';
        case 'boolean':
            return typeof value === 'boolean';
        case 'spacing':
        case 'width':
        case 'height':
        case 'typography':
        case 'repeater':
            return typeof value === 'string' || typeof value === 'number' || Array.isArray(value) || value === null || typeof value === 'object';
        default:
            return true;
    }
}

function applyCandidatePatch(
    entry: AiComponentCatalogEntry,
    patch: Record<string, unknown>,
    aliases: string[],
    value: unknown,
): Record<string, unknown> {
    const fieldPath = resolveFieldPath(entry, aliases);
    if (!fieldPath) {
        return patch;
    }

    const field = entry.propsSchema.find((item) => item.path === fieldPath);
    if (!field || !isCompatibleValue(field.type, value)) {
        return patch;
    }

    return setValueAtPath(patch, fieldPath, value);
}

function buildItems(items: Array<{ title?: string; description?: string; quote?: string; name?: string; role?: string }>): Array<Record<string, unknown>> {
    return items.map((item) => ({
        ...(item.title ? { title: item.title } : {}),
        ...(item.description ? { description: item.description } : {}),
        ...(item.quote ? { quote: item.quote } : {}),
        ...(item.name ? { name: item.name } : {}),
        ...(item.role ? { role: item.role } : {}),
    }));
}

function buildPatch(entry: AiComponentCatalogEntry, context: SectionComposerContext): Record<string, unknown> {
    const brand = extractBrandName(context.prompt, context.brandName) ?? 'Your brand';
    const subject = inferSubject(context.prompt, context.projectType);
    const primaryCta = getPrimaryCta(context.projectType);
    const title = context.projectType === 'ecommerce'
        ? `${titleCase(subject)} that customers remember`
        : context.projectType === 'clinic'
            ? `${brand} helps people book care faster`
            : context.projectType === 'restaurant'
                ? `${brand} brings guests back for the next table`
                : `${brand} helps teams launch with confidence`;
    const subtitle = context.projectType === 'ecommerce'
        ? `Discover curated ${subject} collections with fast checkout and clear merchandising.`
        : context.projectType === 'clinic'
            ? 'Build trust with clear services, practitioner credibility, and fast appointment access.'
            : context.projectType === 'restaurant'
                ? 'Show signature dishes, atmosphere, and a simple path to reserve.'
                : 'Clarify the offer, show proof quickly, and move visitors toward a clear action.';

    let patch: Record<string, unknown> = {};

    switch (entry.layoutType) {
        case 'hero':
            patch = applyCandidatePatch(entry, patch, ['title', 'headline'], title);
            patch = applyCandidatePatch(entry, patch, ['subtitle', 'description', 'body'], subtitle);
            patch = applyCandidatePatch(entry, patch, ['buttonText', 'buttonLabel', 'ctaText', 'cta_label'], primaryCta.label);
            patch = applyCandidatePatch(entry, patch, ['buttonLink', 'buttonUrl', 'ctaLink', 'cta_url'], primaryCta.href);
            patch = applyCandidatePatch(entry, patch, ['eyebrow'], titleCase(subject));
            patch = applyCandidatePatch(entry, patch, ['imageAlt'], `${brand} ${subject}`);
            break;
        case 'features':
            patch = applyCandidatePatch(entry, patch, ['title', 'headline'], `${brand} at a glance`);
            patch = applyCandidatePatch(entry, patch, ['items'], buildItems([
                { title: 'Clear positioning', description: 'Communicate the offer in a single scan.' },
                { title: 'Fast trust signals', description: 'Use proof, testimonials, and outcomes early.' },
                { title: 'Action-oriented layout', description: 'Guide visitors toward the next step without friction.' },
            ]));
            break;
        case 'product-grid':
            patch = applyCandidatePatch(entry, patch, ['title', 'headline'], 'Featured products');
            patch = applyCandidatePatch(entry, patch, ['subtitle', 'description'], `Highlight the most relevant ${subject} collection first.`);
            patch = applyCandidatePatch(entry, patch, ['add_to_cart_label', 'buttonText', 'cta_label'], 'Add to cart');
            patch = applyCandidatePatch(entry, patch, ['productCount', 'products_per_page'], 8);
            break;
        case 'testimonials':
            patch = applyCandidatePatch(entry, patch, ['title', 'headline'], 'Trusted by customers');
            patch = applyCandidatePatch(entry, patch, ['items'], buildItems([
                { quote: 'The site feels polished and makes the offer easy to understand.', name: 'A. Customer', role: 'Client' },
                { quote: 'Editing content is simple and the layout stays focused.', name: 'J. Visitor', role: 'Lead' },
            ]));
            break;
        case 'cta':
            patch = applyCandidatePatch(entry, patch, ['title', 'headline'], 'Ready to launch the next version?');
            patch = applyCandidatePatch(entry, patch, ['subtitle', 'description'], 'Keep the layout editable while moving quickly from concept to production.');
            patch = applyCandidatePatch(entry, patch, ['buttonLabel', 'buttonText', 'ctaText', 'cta_label'], primaryCta.label);
            patch = applyCandidatePatch(entry, patch, ['buttonUrl', 'buttonLink', 'ctaLink', 'cta_url'], primaryCta.href);
            break;
        case 'faq':
            patch = applyCandidatePatch(entry, patch, ['title', 'headline'], 'Common questions');
            patch = applyCandidatePatch(entry, patch, ['items'], buildItems([
                { title: 'How fast can we launch?', description: 'Start with the generated structure and refine each section in place.' },
                { title: 'Can the layout be edited later?', description: 'Yes. Every generated section stays editable through the same builder inspector.' },
            ]));
            break;
        case 'form':
            patch = applyCandidatePatch(entry, patch, ['title', 'headline'], context.projectType === 'clinic' ? 'Book a consultation' : 'Talk to the team');
            patch = applyCandidatePatch(entry, patch, ['subtitle', 'description'], 'Collect the most important intent data without forcing a long form.');
            patch = applyCandidatePatch(entry, patch, ['submit_label', 'buttonLabel', 'buttonText'], primaryCta.label);
            break;
        case 'cards':
        case 'grid':
            patch = applyCandidatePatch(entry, patch, ['title', 'headline'], `${brand} highlights`);
            patch = applyCandidatePatch(entry, patch, ['items'], buildItems([
                { title: 'Primary offer', description: 'Lead with the strongest outcome.' },
                { title: 'Supporting proof', description: 'Add credibility where decisions happen.' },
                { title: 'Next step', description: 'Keep one clear CTA visible.' },
            ]));
            break;
        case 'banner':
            patch = applyCandidatePatch(entry, patch, ['title', 'headline'], `${brand} now live`);
            patch = applyCandidatePatch(entry, patch, ['subtitle', 'description'], 'Promote the clearest offer without interrupting the page flow.');
            patch = applyCandidatePatch(entry, patch, ['cta_label', 'buttonText', 'buttonLabel'], primaryCta.label);
            patch = applyCandidatePatch(entry, patch, ['cta_url', 'buttonLink', 'buttonUrl'], primaryCta.href);
            break;
        case 'header':
        case 'navigation':
            patch = applyCandidatePatch(entry, patch, ['ctaText', 'buttonText', 'buttonLabel'], primaryCta.label);
            patch = applyCandidatePatch(entry, patch, ['ctaLink', 'buttonLink', 'buttonUrl'], primaryCta.href);
            break;
        case 'footer':
            patch = applyCandidatePatch(entry, patch, ['copyright'], `© ${new Date().getFullYear()} ${brand}`);
            break;
        default:
            patch = applyCandidatePatch(entry, patch, ['title', 'headline'], title);
            patch = applyCandidatePatch(entry, patch, ['subtitle', 'description', 'body'], subtitle);
            break;
    }

    return patch;
}

export function composeSectionProps(componentKey: string, context: SectionComposerContext): Record<string, unknown> {
    const entry = getCatalogEntry(componentKey);
    if (!entry) {
        return {};
    }

    return buildPatch(entry, context);
}
