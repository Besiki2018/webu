import { describe, expect, it } from 'vitest';

import {
    getAllowedComponents,
    getCodegenTagMapSnapshot,
    getComponentCodegenMetadata,
    getComponentRegistryIdByCodegenTagName,
    getComponentSchema,
    getComponentSchemaJson,
    getComponentRuntimeEntry,
    getGovernedComponent,
    getRegistrySnapshot,
    isComponentAllowedForProjectSiteType,
    resolveComponentProps,
} from '../componentRegistry';
import { getComponentParameterMeta } from '../componentParameterMetadata';

describe('componentRegistry schema normalization', () => {
    it('normalizes explicit component schema into a serializable builder contract', () => {
        const schema = getComponentSchema('webu_header_01');
        const schemaJson = getComponentSchemaJson('webu_header_01');

        expect(schema).not.toBeNull();
        expect(schema?.schemaVersion).toBe(2);
        expect(schema?.componentKey).toBe('webu_header_01');
        expect(schema?.serializable).toBe(true);
        expect(schema?.responsiveSupport?.enabled).toBe(true);
        expect(schema?.responsiveSupport?.breakpoints).toEqual(['desktop', 'tablet', 'mobile']);
        expect(schema?.contentGroups?.some((group) => group.key === 'content')).toBe(true);
        expect(schema?.styleGroups?.some((group) => group.key === 'layout')).toBe(true);
        expect(schema?.styleGroups?.some((group) => group.key === 'style')).toBe(true);
        expect(schema?.advancedGroups?.some((group) => group.key === 'advanced')).toBe(true);
        expect(schema?.bindingFields).toContain('menu_items');
        expect(schema?.chatTargets).toContain('ctaText');
        expect(schema?.variantDefinitions?.map((definition) => definition.kind)).toEqual(['layout', 'style']);

        expect(schemaJson).not.toBeNull();
        expect(schemaJson?.schema_version).toBe(2);
        expect(schemaJson?.component_key).toBe('webu_header_01');
        expect(schemaJson?.serializable).toBe(true);
        expect(schemaJson?.variant_definitions).toBeInstanceOf(Array);
        expect(schemaJson?.binding_fields).toContain('menu_items');
        expect(schemaJson?.editable_fields).toContain('ctaText');
        expect(
            ((schemaJson?.properties as Record<string, unknown>)?.menu_items as Record<string, unknown>)?.item_fields
        ).toBeInstanceOf(Array);
    });

    it('upgrades legacy registry definitions into normalized schema with groups and responsive metadata', () => {
        const schema = getComponentSchema('webu_general_button_01');
        const schemaJson = getComponentSchemaJson('webu_general_button_01');

        expect(schema).not.toBeNull();
        expect(schema?.schemaVersion).toBe(2);
        expect(schema?.componentKey).toBe('webu_general_button_01');
        expect(schema?.serializable).toBe(true);
        expect(schema?.fields.some((field) => field.path === 'button')).toBe(true);
        expect(schema?.fields.some((field) => field.path === 'layout.alignment')).toBe(true);
        expect(schema?.fields.some((field) => field.path === 'advanced.padding_top')).toBe(true);
        expect(schema?.fields.some((field) => field.path === 'responsive.hide_on_mobile')).toBe(true);
        expect(schema?.contentGroups?.some((group) => group.key === 'content')).toBe(true);
        expect(schema?.styleGroups?.some((group) => group.key === 'layout')).toBe(true);
        expect(schema?.advancedGroups?.some((group) => group.key === 'advanced')).toBe(true);
        expect(schema?.projectTypes).toContain('general');
        expect(schema?.responsiveSupport?.supportsVisibility).toBe(true);
        expect(schemaJson?.fields).toBeInstanceOf(Array);
        expect(schemaJson?.content_groups).toBeInstanceOf(Array);
        expect(schemaJson?._meta).toMatchObject({
            schema_version: 2,
            component_key: 'webu_general_button_01',
            serializable: true,
        });
    });

    it('exposes field metadata kinds from the same normalized schema contract', () => {
        const footerMeta = getComponentParameterMeta('webu_footer_01');
        const footerDescriptionField = footerMeta?.fields.find((field) => field.parameterName === 'description');
        const footerLinksField = footerMeta?.fields.find((field) => field.parameterName === 'links');

        expect(footerMeta?.displayName).toBe('Footer');
        expect(footerDescriptionField?.kind).toBe('richtext');
        expect(footerLinksField?.kind).toBe('menu');
    });

    it('includes normalized schema and schema json in the registry snapshot', () => {
        const snapshot = getRegistrySnapshot();
        const hero = snapshot.webu_general_hero_01;

        expect(hero).toBeDefined();
        expect(hero.schema?.schemaVersion).toBe(2);
        expect(hero.schema_json?.component_key).toBe('webu_general_hero_01');
        expect(hero.schema_json?._meta).toMatchObject({
            schema_version: 2,
            component_key: 'webu_general_hero_01',
        });
        expect(hero.schema?.codegen).toMatchObject({
            tagName: 'HeroSection',
            importPath: '@/sections/HeroSection',
        });
    });

    it('provides a runtime entry with component renderer, defaults, and merged props', () => {
        const runtimeEntry = getComponentRuntimeEntry('webu_general_hero_01');
        const props = resolveComponentProps('webu_general_hero_01', {
            title: 'Storefront Hero',
            style: {
                background_color: '#f8fafc',
            },
        });

        expect(runtimeEntry).not.toBeNull();
        expect(runtimeEntry?.component).toBeTypeOf('function');
        expect(runtimeEntry?.defaults.title).toBe('Build faster websites');
        expect(runtimeEntry?.projectTypes).toContain('general');
        expect(runtimeEntry?.codegen).toMatchObject({
            tagName: 'HeroSection',
            importPath: '@/sections/HeroSection',
            importName: 'HeroSection',
        });
        expect(props.title).toBe('Storefront Hero');
        expect((props.style as Record<string, unknown>)?.background_color).toBe('#f8fafc');
        expect((props.advanced as Record<string, unknown>)?.z_index).toBe(0);
    });

    it('resolves generic CMS section aliases to canonical builder schemas and prop aliases', () => {
        const heroSchema = getComponentSchema('hero');
        const servicesSchema = getComponentSchema('services');
        const contentSchema = getComponentSchema('content');
        const props = resolveComponentProps('hero', {
            title: 'მოგესალმებით',
            cta_text: 'დაწყება',
            cta_link: '/contact',
            image: '/hero.jpg',
        });

        expect(heroSchema?.componentKey).toBe('webu_general_hero_01');
        expect(servicesSchema?.componentKey).toBe('webu_general_features_01');
        expect(contentSchema?.componentKey).toBe('webu_general_text_01');
        expect(props.title).toBe('მოგესალმებით');
        expect(props.buttonText).toBe('დაწყება');
        expect(props.buttonLink).toBe('/contact');
        expect(props.backgroundImage).toBe('/hero.jpg');
    });

    it('keeps explicit schema-backed hero fields authoritative when stale legacy aliases are also present', () => {
        const props = resolveComponentProps('webu_general_hero_01', {
            title: 'Runtime Title',
            headline: 'Legacy Headline',
            buttonLink: '/runtime-link',
            ctaUrl: '/legacy-link',
            image: '/runtime-image.jpg',
            imageUrl: '/legacy-image.jpg',
        });

        expect(props.title).toBe('Runtime Title');
        expect(props.buttonLink).toBe('/runtime-link');
        expect(props.image).toBe('/runtime-image.jpg');
    });

    it('exposes deterministic codegen metadata and reverse lookup from the centralized registry', () => {
        const heroCodegen = getComponentCodegenMetadata('webu_general_hero_01');
        const tagMap = getCodegenTagMapSnapshot();

        expect(heroCodegen).toMatchObject({
            tagName: 'HeroSection',
            importPath: '@/sections/HeroSection',
            importName: 'HeroSection',
        });
        expect(tagMap.webu_general_hero_01).toBe('HeroSection');
        expect(getComponentRegistryIdByCodegenTagName('HeroSection')).toBe('webu_general_hero_01');
    });

    it('builds a governed component catalog with canonical type, label, category, and schema', () => {
        const ecommerceProductGrid = getGovernedComponent('webu_ecom_product_grid_01');
        const landingBanner = getGovernedComponent('webu_general_banner_01');

        expect(ecommerceProductGrid).toMatchObject({
            type: 'webu_ecom_product_grid_01',
            label: 'Product Grid',
            category: 'ecommerce',
        });
        expect(ecommerceProductGrid?.schema.componentKey).toBe('webu_ecom_product_grid_01');
        expect(landingBanner).toMatchObject({
            type: 'webu_general_banner_01',
            label: 'Banner',
            category: 'landing',
        });
    });

    it('filters allowed components by normalized project site type', () => {
        const ecommerceComponents = getAllowedComponents('ecommerce');
        const websiteComponents = getAllowedComponents('website');

        expect(ecommerceComponents.some((component) => component.type === 'webu_ecom_product_grid_01')).toBe(true);
        expect(ecommerceComponents.some((component) => component.category === 'marketing')).toBe(false);
        expect(websiteComponents.some((component) => component.type === 'webu_ecom_product_grid_01')).toBe(false);
        expect(websiteComponents.some((component) => component.type === 'webu_general_banner_01')).toBe(true);
        expect(isComponentAllowedForProjectSiteType('webu_general_hero_01', 'website')).toBe(true);
        expect(isComponentAllowedForProjectSiteType('webu_ecom_product_grid_01', 'website')).toBe(false);
    });
});
