import {
    getAllowedComponents,
    getComponentRuntimeEntry,
    getGovernedComponentCatalog,
    type BuilderComponentSchema,
    type BuilderFieldDefinition,
} from '../componentRegistry';
import type { ProjectSiteType } from '../projectTypes';
import {
    inferAiProjectTypeFromBuilderProjectType,
    mapAiProjectTypeToSiteType,
    type AiProjectType,
} from './projectTypeDetector';

export type AiComponentCategory = 'ecommerce' | 'booking' | 'landing' | 'marketing' | 'blog' | 'general';
export type AiComponentLayoutType =
    | 'header'
    | 'footer'
    | 'hero'
    | 'features'
    | 'product-grid'
    | 'testimonials'
    | 'cta'
    | 'faq'
    | 'form'
    | 'navigation'
    | 'grid'
    | 'cards'
    | 'banner'
    | 'content'
    | 'media'
    | 'section';

export interface AiComponentVariantOption {
    id: string;
    label: string;
    kind: 'layout' | 'style';
}

export interface AiComponentCatalogEntry {
    componentKey: string;
    label: string;
    category: AiComponentCategory;
    projectTypesAllowed: AiProjectType[];
    layoutType: AiComponentLayoutType;
    propsSchema: BuilderFieldDefinition[];
    defaultProps: Record<string, unknown>;
    variants: AiComponentVariantOption[];
}

function normalizeCategory(category: string): AiComponentCategory {
    switch (category) {
        case 'ecommerce':
            return 'ecommerce';
        case 'booking':
            return 'booking';
        case 'landing':
            return 'landing';
        case 'marketing':
            return 'marketing';
        case 'blog':
            return 'blog';
        default:
            return 'general';
    }
}

function inferLayoutType(componentKey: string, schema: BuilderComponentSchema): AiComponentLayoutType {
    const normalizedKey = componentKey.toLowerCase();
    const normalizedName = schema.displayName.toLowerCase();

    if (normalizedKey.includes('header') || normalizedName.includes('header')) return 'header';
    if (normalizedKey.includes('footer') || normalizedName.includes('footer')) return 'footer';
    if (normalizedKey.includes('hero') || normalizedName.includes('hero')) return 'hero';
    if (normalizedKey.includes('product_grid') || normalizedName.includes('product')) return 'product-grid';
    if (normalizedKey.includes('testimonial') || normalizedName.includes('testimonial')) return 'testimonials';
    if (normalizedKey.includes('faq') || normalizedName.includes('faq')) return 'faq';
    if (normalizedKey.includes('form') || normalizedName.includes('form') || normalizedName.includes('contact')) return 'form';
    if (normalizedKey.includes('navigation') || normalizedName.includes('navigation')) return 'navigation';
    if (normalizedKey.includes('grid')) return 'grid';
    if (normalizedKey.includes('card') || normalizedName.includes('card')) return 'cards';
    if (normalizedKey.includes('banner') || normalizedName.includes('banner')) return 'banner';
    if (normalizedKey.includes('image') || normalizedKey.includes('video')) return 'media';
    if (normalizedKey.includes('cta') || normalizedName.includes('call to action')) return 'cta';
    if (normalizedKey.includes('features') || normalizedName.includes('features')) return 'features';
    if (normalizedKey.includes('text') || normalizedName.includes('text')) return 'content';

    return 'section';
}

function inferProjectTypesAllowed(schema: BuilderComponentSchema, category: AiComponentCategory): AiProjectType[] {
    const explicit = new Set<AiProjectType>();
    (schema.projectTypes ?? []).forEach((projectType) => {
        explicit.add(inferAiProjectTypeFromBuilderProjectType(projectType));
    });

    if (explicit.size > 0) {
        if (category === 'booking') {
            explicit.add('booking');
            explicit.add('clinic');
            explicit.add('restaurant');
        }
        return [...explicit];
    }

    switch (category) {
        case 'ecommerce':
            return ['ecommerce'];
        case 'booking':
            return ['booking', 'clinic', 'restaurant'];
        case 'landing':
            return ['landing'];
        case 'marketing':
            return ['landing', 'business', 'saas'];
        case 'blog':
            return ['blog', 'business'];
        default:
            return ['landing', 'business', 'portfolio', 'clinic', 'restaurant', 'booking', 'saas', 'blog', 'education'];
    }
}

function extractVariantOptions(schema: BuilderComponentSchema): AiComponentVariantOption[] {
    return (schema.variantDefinitions ?? []).flatMap((definition) => (
        definition.options.map((option) => ({
            id: option.value,
            label: option.label,
            kind: definition.kind,
        }))
    ));
}

function buildCatalogEntry(componentKey: string): AiComponentCatalogEntry | null {
    const runtimeEntry = getComponentRuntimeEntry(componentKey);
    if (!runtimeEntry) {
        return null;
    }

    const category = normalizeCategory(runtimeEntry.category);
    return {
        componentKey: runtimeEntry.componentKey,
        label: runtimeEntry.displayName,
        category,
        projectTypesAllowed: inferProjectTypesAllowed(runtimeEntry.schema, category),
        layoutType: inferLayoutType(runtimeEntry.componentKey, runtimeEntry.schema),
        propsSchema: runtimeEntry.schema.fields,
        defaultProps: runtimeEntry.defaults,
        variants: extractVariantOptions(runtimeEntry.schema),
    };
}

export function getComponentCatalog(): AiComponentCatalogEntry[] {
    return getGovernedComponentCatalog()
        .map((entry) => buildCatalogEntry(entry.type))
        .filter((entry): entry is AiComponentCatalogEntry => entry !== null);
}

export function getAllowedComponentCatalog(projectType: AiProjectType | ProjectSiteType | string): AiComponentCatalogEntry[] {
    const siteType = ((): ProjectSiteType => {
        if (projectType === 'ecommerce' || projectType === 'booking' || projectType === 'landing' || projectType === 'website') {
            return projectType;
        }

        return mapAiProjectTypeToSiteType(projectType as AiProjectType);
    })();

    return getAllowedComponents(siteType)
        .map((component) => buildCatalogEntry(component.type))
        .filter((entry): entry is AiComponentCatalogEntry => entry !== null);
}

export function getCatalogEntry(componentKey: string): AiComponentCatalogEntry | null {
    return buildCatalogEntry(componentKey);
}
