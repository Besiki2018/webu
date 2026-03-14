import { buildBuilderPageModelFromContentJson, type BuilderPageModel } from '@/builder/model/pageModel';
import { resolveComponentRegistryKey } from '@/builder/componentRegistry';
import { CMS_PAGE_BINDING_EXTRA_CONTENT_KEY } from '@/builder/cmsIntegration/cmsPageBinding';
import { cloneRecordData } from '@/builder/runtime/clone';
import { getGeneratedSectionPrimaryProps } from './projectGraph';
import type { GeneratedPage, GeneratedProjectGraph, GeneratedSection } from './types';

export interface GeneratedBuilderPageModelEntry {
    pageId: string;
    slug: string;
    title: string;
    model: BuilderPageModel;
}

const SECTION_KIND_TO_REGISTRY_KEY: Record<string, string> = {
    header: 'webu_header_01',
    hero: 'webu_general_hero_01',
    features: 'webu_general_features_01',
    cta: 'webu_general_cta_01',
    footer: 'webu_footer_01',
};

function asString(value: unknown): string | undefined {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : undefined;
}

function asMenuItems(value: unknown): Array<Record<string, unknown>> | undefined {
    if (!Array.isArray(value)) {
        return undefined;
    }

    return value.filter((item): item is Record<string, unknown> => item !== null && typeof item === 'object');
}

function asArray(value: unknown): unknown[] | undefined {
    return Array.isArray(value) ? value : undefined;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function cloneRecord<T extends Record<string, unknown>>(value: T | null | undefined): T {
    return cloneRecordData(value);
}

function resolveBuilderSectionType(section: GeneratedSection): string {
    const explicitRegistryKey = resolveComponentRegistryKey(section.registryKey ?? '');
    if (explicitRegistryKey) {
        return explicitRegistryKey;
    }

    const componentRegistryKey = resolveComponentRegistryKey(section.components[0]?.registryKey ?? section.components[0]?.key ?? '');
    if (componentRegistryKey) {
        return componentRegistryKey;
    }

    if (section.kind === 'content') {
        const props = getGeneratedSectionPrimaryProps(section);
        if (Array.isArray(props.items)) {
            return 'webu_general_features_01';
        }

        if (asString(props.image) || asString(props.image_url)) {
            return 'webu_general_image_01';
        }

        if (asString(props.body) && !asString(props.title) && !asString(props.headline)) {
            return 'webu_general_text_01';
        }

        if (asString(props.title) || asString(props.headline) || asString(props.eyebrow)) {
            return 'webu_general_heading_01';
        }

        return 'webu_general_section_01';
    }

    return SECTION_KIND_TO_REGISTRY_KEY[section.kind] ?? 'webu_general_section_01';
}

function mapSectionPropsToBuilderProps(section: GeneratedSection, builderSectionType: string): Record<string, unknown> {
    const props = getGeneratedSectionPrimaryProps(section);

    switch (builderSectionType) {
        case 'webu_header_01':
            return {
                ...props,
                logoText: asString(props.logoText) ?? asString(props.logo) ?? asString(props.brandName),
                menu_items: asMenuItems(props.menu_items) ?? asMenuItems(props.menu) ?? asMenuItems(props.navigationItems),
                ctaText: asString(props.ctaText) ?? asString(props.ctaLabel),
                ctaLink: asString(props.ctaLink) ?? asString(props.ctaUrl),
            };
        case 'webu_general_hero_01':
            return {
                ...props,
                title: asString(props.title) ?? asString(props.headline),
                headline: asString(props.headline) ?? asString(props.title),
                subtitle: asString(props.subtitle) ?? asString(props.description) ?? asString(props.subheading),
                buttonText: asString(props.buttonText) ?? asString(props.ctaLabel),
                buttonLink: asString(props.buttonLink) ?? asString(props.ctaUrl),
                image: asString(props.image) ?? asString(props.image_url) ?? asString(props.backgroundImage),
            };
        case 'webu_general_features_01':
            return {
                ...props,
                title: asString(props.title) ?? section.label ?? 'Features',
                items: Array.isArray(props.items) ? props.items : [],
            };
        case 'webu_general_cards_01':
        case 'webu_general_grid_01':
        case 'webu_general_testimonials_01':
        case 'webu_ecom_product_grid_01':
            return {
                ...props,
                title: asString(props.title) ?? section.label ?? 'Collection',
                subtitle: asString(props.subtitle) ?? asString(props.description),
                items: asArray(props.items) ?? [],
            };
        case 'webu_general_form_wrapper_01':
            return {
                ...props,
                title: asString(props.title) ?? section.label ?? 'Form',
                subtitle: asString(props.subtitle) ?? asString(props.description),
                submitLabel: asString(props.submitLabel) ?? asString(props.buttonLabel) ?? asString(props.ctaLabel),
                fields: asArray(props.fields) ?? [],
                formType: asString(props.formType),
            };
        case 'faq_accordion_plus':
            return {
                ...props,
                title: asString(props.title) ?? section.label ?? 'FAQ',
                items: asArray(props.items) ?? [],
            };
        case 'webu_general_cta_01':
            return {
                ...props,
                title: asString(props.title) ?? section.label ?? 'Call to Action',
                subtitle: asString(props.subtitle) ?? asString(props.description),
                buttonLabel: asString(props.buttonLabel) ?? asString(props.buttonText) ?? asString(props.ctaLabel),
                buttonUrl: asString(props.buttonUrl) ?? asString(props.buttonLink) ?? asString(props.ctaUrl),
            };
        case 'webu_footer_01':
            return {
                ...props,
                logoText: asString(props.logoText) ?? asString(props.logo) ?? asString(props.brandName),
                links: Array.isArray(props.links) ? props.links : [],
            };
        case 'webu_general_heading_01':
            return {
                ...props,
                title: asString(props.title) ?? asString(props.headline) ?? section.label,
                headline: asString(props.headline) ?? asString(props.title),
                description: asString(props.description) ?? asString(props.body),
            };
        case 'webu_general_text_01':
            return {
                ...props,
                body: asString(props.body) ?? asString(props.description) ?? asString(props.text),
            };
        case 'webu_general_image_01':
            return {
                ...props,
                image_url: asString(props.image_url) ?? asString(props.image),
                image_alt: asString(props.image_alt) ?? asString(props.imageAlt),
            };
        case 'webu_general_section_01':
        default:
            return {
                ...props,
                title: asString(props.title) ?? asString(props.headline) ?? section.label ?? 'Section',
                body: asString(props.body) ?? asString(props.description),
            };
    }
}

/**
 * Integration point: this is the narrow bridge from the future workspace-first
 * project graph into today's builder `BuilderPageModel`.
 */
export function buildBuilderPageModelFromGeneratedPage(page: GeneratedPage): BuilderPageModel {
    return buildBuilderPageModelFromContentJson({
        ...(isRecord(page.metadata[CMS_PAGE_BINDING_EXTRA_CONTENT_KEY])
            ? { [CMS_PAGE_BINDING_EXTRA_CONTENT_KEY]: cloneRecord(page.metadata[CMS_PAGE_BINDING_EXTRA_CONTENT_KEY] as Record<string, unknown>) }
            : {}),
        sections: page.sections
            .slice()
            .sort((left, right) => left.order - right.order)
            .map((section) => {
                const builderSectionType = resolveBuilderSectionType(section);

                return {
                    type: builderSectionType,
                    localId: section.localId,
                    props: mapSectionPropsToBuilderProps(section, builderSectionType),
                    ...(isRecord(section.metadata.bindingMeta)
                        ? { binding: cloneRecord(section.metadata.bindingMeta as Record<string, unknown>) }
                        : {}),
                };
            }),
    });
}

export function buildBuilderPageModelsFromProjectGraph(graph: GeneratedProjectGraph): GeneratedBuilderPageModelEntry[] {
    return graph.pages.map((page) => ({
        pageId: page.id,
        slug: page.slug,
        title: page.title,
        model: buildBuilderPageModelFromGeneratedPage(page),
    }));
}
