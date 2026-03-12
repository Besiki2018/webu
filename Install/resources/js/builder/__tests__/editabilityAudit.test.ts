/**
 * Editability audit — ensures every builder-available component meets minimum editability standards.
 * Fails if any component lacks usable schema, editable fields, or key content controls.
 */

import { describe, expect, it } from 'vitest';
import {
    getAvailableComponents,
    getComponentSchema,
    getComponentSchemaJson,
    getEditableFieldPaths,
} from '../componentRegistry';
import { collectBuilderSchemaPrimitiveFieldDescriptors } from '@/lib/schemaPrimitiveFields';

export const MIN_CONTENT_FIELDS = 1;
export const MIN_TOTAL_FIELDS = 1;

/** Key components that must have specific content fields for professional editing. */
const KEY_COMPONENT_EDITABILITY: Record<string, string[]> = {
    webu_header_01: ['logoText', 'menu_items', 'ctaText', 'ctaLink'],
    webu_footer_01: ['copyright', 'links', 'socialLinks', 'newsletterHeading', 'newsletterButtonLabel'],
    webu_general_hero_01: ['title', 'subtitle', 'buttonText', 'buttonLink', 'image'],
    webu_general_features_01: ['title', 'items'],
    webu_general_cards_01: ['title', 'items'],
    webu_general_grid_01: ['title', 'items'],
    webu_general_navigation_01: ['links'],
    webu_general_cta_01: ['title', 'subtitle', 'buttonText', 'buttonLink'],
    webu_general_testimonials_01: ['title', 'items'],
    faq_accordion_plus: ['title', 'items'],
    webu_general_banner_01: ['title', 'cta_label', 'cta_url'],
    webu_general_offcanvas_menu_01: ['title', 'menu_items', 'trigger_label'],
    webu_general_card_01: ['title', 'body', 'link_url'],
    webu_general_form_wrapper_01: ['title', 'submit_label', 'namePlaceholder', 'emailPlaceholder', 'messagePlaceholder'],
    webu_general_newsletter_01: ['title', 'buttonText', 'placeholder', 'subtitle'],
    webu_general_heading_01: ['headline', 'title'],
    webu_general_text_01: ['body'],
    webu_general_image_01: ['image_url', 'image_alt', 'image_link'],
    webu_ecom_product_grid_01: ['title', 'add_to_cart_label', 'cta_label'],
    webu_ecom_cart_page_01: ['title', 'emptyMessage'],
};

describe('Editability audit', () => {
    it('every builder-available component has usable schema for sidebar generation', () => {
        const ids = getAvailableComponents();
        expect(ids.length).toBeGreaterThan(0);

        for (const id of ids) {
            const schema = getComponentSchema(id);
            const schemaJson = getComponentSchemaJson(id);

            expect(schema, `getComponentSchema("${id}")`).not.toBeNull();
            expect(schema?.componentKey).toBe(id);
            expect(schema?.fields?.length).toBeGreaterThanOrEqual(MIN_TOTAL_FIELDS);

            expect(schemaJson, `getComponentSchemaJson("${id}")`).not.toBeNull();
            expect(typeof schemaJson).toBe('object');
            expect(schemaJson?.properties !== undefined || (schemaJson && Object.keys(schemaJson).length > 0)).toBe(true);
        }
    });

    it('every component has at least one content-editable field', () => {
        const ids = getAvailableComponents();

        for (const id of ids) {
            const editablePaths = getEditableFieldPaths(id);
            const contentFields = getComponentSchema(id)?.fields?.filter(
                (f) => f.group === 'content' || f.group === 'data' || f.group === 'bindings'
            ) ?? [];

            expect(
                editablePaths.length >= MIN_CONTENT_FIELDS || contentFields.length >= MIN_CONTENT_FIELDS,
                `"${id}" has no editable content fields (editablePaths: ${editablePaths.length}, contentFields: ${contentFields.length})`
            ).toBe(true);
        }
    });

    it('key content components expose expected important content fields', () => {
        for (const [componentId, requiredPaths] of Object.entries(KEY_COMPONENT_EDITABILITY)) {
            const ids = getAvailableComponents();
            if (!ids.includes(componentId)) continue;

            const schema = getComponentSchema(componentId);
            const editablePaths = getEditableFieldPaths(componentId);
            const fieldPaths = new Set(schema?.fields?.map((f) => f.path) ?? []);

            for (const path of requiredPaths) {
                const hasPath = editablePaths.includes(path) || fieldPaths.has(path);
                expect(
                    hasPath,
                    `"${componentId}" must have editable field "${path}" (editable: ${editablePaths.join(', ')}, fields: ${[...fieldPaths].join(', ')})`
                ).toBe(true);
            }
        }
    });

    it('collectBuilderSchemaPrimitiveFieldDescriptors produces usable fields for key components', () => {
        const keyIds = ['webu_header_01', 'webu_footer_01', 'webu_general_hero_01', 'webu_general_card_01'];

        for (const id of keyIds) {
            const schema = getComponentSchema(id);
            if (!schema) continue;

            const descriptors = collectBuilderSchemaPrimitiveFieldDescriptors(schema, {});
            expect(
                descriptors.length,
                `"${id}" should produce primitive field descriptors for sidebar controls`
            ).toBeGreaterThan(0);

            const contentDescriptors = descriptors.filter(
                (d) => (d.definition as { control_group?: string })?.control_group === 'content'
            );
            expect(
                contentDescriptors.length >= 1,
                `"${id}" should have at least one content-group descriptor`
            ).toBe(true);
        }
    });

    it('header/footer/hero/features/cards/grid have repeater or menu item fields with itemFields for nested editing', () => {
        const schema = getComponentSchema('webu_header_01');
        const menuField = schema?.fields?.find((f) => f.path === 'menu_items' || f.type === 'menu');
        expect(menuField).toBeDefined();
        expect(menuField?.type === 'menu' || (menuField?.itemFields?.length ?? 0) > 0).toBeTruthy();

        const footerSchema = getComponentSchema('webu_footer_01');
        const linksField = footerSchema?.fields?.find((f) => f.path === 'links' || f.path === 'socialLinks');
        expect(linksField).toBeDefined();
        expect(linksField?.type === 'menu' || (linksField?.itemFields?.length ?? 0) > 0).toBeTruthy();

        const featuresSchema = getComponentSchema('webu_general_features_01');
        const featuresItemsField = featuresSchema?.fields?.find((f) => f.path === 'items');
        expect(featuresItemsField).toBeDefined();
        expect(featuresItemsField?.type).toBe('repeater');
        expect((featuresItemsField?.itemFields?.length ?? 0)).toBeGreaterThan(0);

        const cardsSchema = getComponentSchema('webu_general_cards_01');
        const cardsItemsField = cardsSchema?.fields?.find((f) => f.path === 'items');
        expect(cardsItemsField).toBeDefined();
        expect(cardsItemsField?.type).toBe('repeater');
        expect((cardsItemsField?.itemFields?.length ?? 0)).toBeGreaterThan(0);

        const gridSchema = getComponentSchema('webu_general_grid_01');
        const gridItemsField = gridSchema?.fields?.find((f) => f.path === 'items');
        expect(gridItemsField).toBeDefined();
        expect(gridItemsField?.type).toBe('repeater');
        expect((gridItemsField?.itemFields?.length ?? 0)).toBeGreaterThan(0);

        const testimonialsSchema = getComponentSchema('webu_general_testimonials_01');
        const testimonialsItemsField = testimonialsSchema?.fields?.find((f) => f.path === 'items');
        expect(testimonialsItemsField).toBeDefined();
        expect((testimonialsItemsField?.itemFields?.length ?? 0) >= 0).toBeTruthy();

        const faqSchema = getComponentSchema('faq_accordion_plus');
        const faqItemsField = faqSchema?.fields?.find((f) => f.path === 'items');
        expect(faqItemsField).toBeDefined();
        expect((faqItemsField?.itemFields?.length ?? 0) >= 0).toBeTruthy();
    });
});
