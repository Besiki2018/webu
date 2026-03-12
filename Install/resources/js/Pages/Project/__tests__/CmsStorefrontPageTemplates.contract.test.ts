import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS storefront page template presets contracts', () => {
    it('keeps canonical storefront page template catalog for MVP ecommerce pages', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('CANONICAL_STOREFRONT_PAGE_TEMPLATE_PRESETS');

        [
            "key: 'home'",
            "key: 'product-listing'",
            "key: 'product-detail'",
            "key: 'cart'",
            "key: 'checkout'",
            "key: 'login-register'",
            "key: 'account'",
            "key: 'orders-list'",
            "key: 'order-detail'",
            "key: 'contact'",
        ].forEach((entry) => expect(cms).toContain(entry));

        [
            "slug: 'home'",
            "slug: 'shop'",
            "slug: 'product'",
            "slug: 'cart'",
            "slug: 'checkout'",
            "slug: 'login'",
            "slug: 'account'",
            "slug: 'orders'",
            "slug: 'order'",
            "slug: 'contact'",
        ].forEach((entry) => expect(cms).toContain(entry));

        [
            "route_pattern: '/product/:slug'",
            "route_pattern: '/account/orders'",
            "route_pattern: '/account/orders/:id'",
            "optional: true",
        ].forEach((entry) => expect(cms).toContain(entry));
    });

    it('uses canonical page template presets in create-page dialog and create payload section scaffolding', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("template_key: ''");
        expect(cms).toContain('handleCreatePageTemplatePresetChange');
        expect(cms).toContain('data-webu-role="create-page-template-select"');
        expect(cms).toContain("t('Page Template')");
        expect(cms).toContain("t('Blank Page')");
        expect(cms).toContain('selectedCreatePageTemplatePreset.route_pattern');

        expect(cms).toContain('const templateSectionsPayload = selectedTemplatePreset');
        expect(cms).toContain('hydrateSectionDefaultsFromCms(section.type, cloneRecord(section.props))');
        expect(cms).toContain('sections: templateSectionsPayload');
    });

    it('merges canonical page templates as fallback for templateSectionsBySlug without overriding explicit theme blueprint defaults', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('CANONICAL_STOREFRONT_PAGE_TEMPLATE_PRESETS.forEach((preset) => {');
        expect(cms).toContain('const existing = map.get(variantSlug);');
        expect(cms).toContain('if (Array.isArray(existing) && existing.length > 0) {');
        expect(cms).toContain('preset.section_blueprints.map((section) => ({');
        expect(cms).toContain('templateSectionsBySlug.get(pageSlug) ?? []');
        expect(cms).toContain('No template sections found for this page');
    });

    it('keeps ecommerce storefront header/footer default bindings and layout variants wired in cms page templates flow', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("if (normalizedKey.startsWith('webu_header_')) {");
        expect(cms).toContain("setIfMissing('login_url', getLinkUrlValue(next, 'link_03'));");
        expect(cms).toContain("setIfMissing('cart_view_button', next.link_20 ?? next.cart_view_button);");
        expect(cms).toContain("setIfMissing('cart_checkout_button', next.link_21 ?? next.cart_checkout_button);");
        expect(cms).toContain("setIfMissing('products_menu_url', '/shop');");
        expect(cms).toContain("setIfMissing('login_url', '/account/login');");
        expect(cms).toContain("setIfMissing('cart_view_button', { label: 'View Cart', url: '/cart' });");
        expect(cms).toContain("setIfMissing('cart_checkout_button', { label: 'Checkout', url: '/checkout' });");
        expect(cms).toContain("setIfMissing('cart_total_label', label);");

        expect(cms).toContain("if (normalizedKey.startsWith('webu_footer_')) {");
        expect(cms).toContain("setIfMissing('account_link_1', next.link_12 ?? next.account_link_1);");
        expect(cms).toContain("setIfMissing('account_link_4', next.link_15 ?? next.account_link_4);");
        expect(cms).toContain("setIfMissing('account_link_5', next.link_16 ?? next.account_link_5);");
        expect(cms).toContain("setIfMissing('account_link_2', { label: 'My Account', url: '/account' });");
        expect(cms).toContain("setIfMissing('account_link_3', { label: 'Orders', url: '/account/orders' });");

        expect(cms).toContain("headerVariant: 'webu_header_01'");
        expect(cms).toContain("footerVariant: 'webu_footer_01'");
        expect(cms).toContain("layout.header_section_key = builderLayoutForm.headerVariant || 'webu_header_01';");
        expect(cms).toContain("layout.footer_section_key = builderLayoutForm.footerVariant || 'webu_footer_01';");
    });
});
