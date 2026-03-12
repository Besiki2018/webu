<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BuilderEcommercePdpCartRuntimeHooksContractTest extends TestCase
{
    public function test_builder_ecommerce_runtime_script_keeps_pdp_cart_coupon_runtime_hook_contract(): void
    {
        $path = base_path('app/Services/BuilderService.php');
        $this->assertFileExists($path);

        $source = File::get($path);

        foreach ([
            "'coupon_url_pattern' =>",
            "'product_detail_selector' => '[data-webby-ecommerce-product-detail]'",
            "'product_gallery_selector' => '[data-webby-ecommerce-product-gallery]'",
            "'coupon_selector' => '[data-webby-ecommerce-coupon]'",
            'function resolveProductSlugForWidget(container, options) {',
            'function applyCoupon(cartId, payload) {',
            'function removeCoupon(cartId, payload) {',
            'function mountProductDetailWidget(container, options) {',
            'data-webby-ecommerce-product-detail-bound',
            'data-webby-ecommerce-product-detail-state',
            'function mountProductGalleryWidget(container, options) {',
            'data-webby-ecommerce-product-gallery-bound',
            'data-webby-ecommerce-product-gallery-state',
            'function mountCouponWidget(container, options) {',
            'data-webby-ecommerce-coupon-bound',
            'data-webby-ecommerce-coupon-state',
            "var productDetailSelector = (ecommerce.widgets && ecommerce.widgets.product_detail_selector)",
            "var productGallerySelector = (ecommerce.widgets && ecommerce.widgets.product_gallery_selector)",
            "var couponSelector = (ecommerce.widgets && ecommerce.widgets.coupon_selector)",
            'mountProductDetailWidget(node, {});',
            'mountProductGalleryWidget(node, {});',
            'mountCouponWidget(node, {});',
            'applyCoupon: applyCoupon,',
            'removeCoupon: removeCoupon,',
            'mountProductDetailWidget: mountProductDetailWidget,',
            'mountProductGalleryWidget: mountProductGalleryWidget,',
            'mountCouponWidget: mountCouponWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }
}

