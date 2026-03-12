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

describe('CMS ecommerce builder component coverage contracts', () => {
    it('keeps Phase 2 core builder component keys in general library, including switch and form wrapper validation shell', () => {
        const cms = read(cmsPagePath);

        [
            'webu_general_section_01',
            'webu_general_container_01',
            'webu_general_grid_01',
            'webu_general_columns_01',
            'webu_general_spacer_01',
            'webu_general_divider_01',
            'webu_general_heading_01',
            'webu_general_text_01',
            'webu_general_button_01',
            'webu_general_icon_01',
            'webu_general_image_01',
            'webu_general_video_01',
            'webu_general_html_01',
            'webu_general_card_01',
            'webu_general_badge_01',
            'webu_general_alert_01',
            'webu_general_input_01',
            'webu_general_textarea_01',
            'webu_general_select_01',
            'webu_general_checkbox_01',
            'webu_general_radio_group_01',
            'webu_general_switch_01',
            'webu_general_form_wrapper_01',
        ].forEach((key) => expect(cms).toContain(key));

        expect(cms).toContain('validation_state');
        expect(cms).toContain('form-wrapper-validation');
        expect(cms).toContain("if (normalized === 'webu_general_switch_01')");
        expect(cms).toContain("if (normalizedSectionType === 'webu_general_form_wrapper_01')");
    });

    it('keeps canonical ecommerce component keys and runtime marker hooks for product/cart/checkout/account flows', () => {
        const cms = read(cmsPagePath);

        [
            'webu_ecom_product_grid_01',
            'webu_ecom_category_list_01',
            'webu_ecom_product_search_01',
            'webu_ecom_product_detail_01',
            'webu_ecom_product_gallery_01',
            'webu_ecom_product_tabs_01',
            'webu_ecom_add_to_cart_button_01',
            'webu_ecom_cart_icon_01',
            'webu_ecom_cart_page_01',
            'webu_ecom_coupon_ui_01',
            'webu_ecom_checkout_form_01',
            'webu_ecom_order_summary_01',
            'webu_ecom_shipping_selector_01',
            'webu_ecom_payment_selector_01',
            'webu_ecom_auth_01',
            'webu_ecom_account_dashboard_01',
            'webu_ecom_account_profile_01',
            'webu_ecom_account_security_01',
            'webu_ecom_orders_list_01',
            'webu_ecom_order_detail_01',
        ].forEach((key) => expect(cms).toContain(key));

        [
            'data-webby-ecommerce-products',
            'data-webby-ecommerce-categories',
            'data-webby-ecommerce-search',
            'data-webby-ecommerce-product-tabs',
            'data-webby-ecommerce-add-to-cart',
            'data-webby-ecommerce-cart-icon',
            'data-webby-ecommerce-cart',
            'data-webby-ecommerce-coupon',
            'data-webby-ecommerce-checkout-form',
            'data-webby-ecommerce-order-summary',
            'data-webby-ecommerce-shipping-selector',
            'data-webby-ecommerce-payment-selector',
            'data-webby-ecommerce-auth',
            'data-webby-ecommerce-account-dashboard',
            'data-webby-ecommerce-account-profile',
            'data-webby-ecommerce-account-security',
            'data-webby-ecommerce-orders-list',
            'data-webby-ecommerce-order-detail',
        ].forEach((marker) => expect(cms).toContain(marker));
    });

    it('keeps route param binding hints plus loading/empty/error/skeleton/pagination preview controls in ecommerce schemas', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('{{route.params.slug}}');
        expect(cms).toContain('{{route.params.id}}');

        expect(cms).toContain('preview_state');
        expect(cms).toContain('loading_title');
        expect(cms).toContain('error_title');
        expect(cms).toContain('empty_title');
        expect(cms).toContain('skeleton_items');
        expect(cms).toContain('pagination_mode');

        expect(cms).toContain('data-webu-role-state="loading"');
        expect(cms).toContain('data-webu-role-state="error"');
        expect(cms).toContain('data-webu-role-state="empty"');
        expect(cms).toContain('data-webu-role="ecom-skeleton-grid"');
        expect(cms).toContain('applyEcomPreviewState');
        expect(cms).toContain("paginationMode === 'infinite'");
    });
});
