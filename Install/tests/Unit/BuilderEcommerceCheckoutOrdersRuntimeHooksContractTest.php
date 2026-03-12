<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest extends TestCase
{
    public function test_builder_ecommerce_runtime_script_keeps_checkout_shipping_payment_orders_runtime_hook_contract(): void
    {
        $path = base_path('app/Services/BuilderService.php');
        $this->assertFileExists($path);

        $source = File::get($path);

        foreach ([
            "'checkout_validate_url_pattern' =>",
            "'customer_orders_url' =>",
            "'customer_order_url_pattern' =>",
            "'checkout_form_selector' => '[data-webby-ecommerce-checkout-form]'",
            "'order_summary_selector' => '[data-webby-ecommerce-order-summary]'",
            "'shipping_selector' => '[data-webby-ecommerce-shipping-selector]'",
            "'payment_selector' => '[data-webby-ecommerce-payment-selector]'",
            "'orders_list_selector' => '[data-webby-ecommerce-orders-list]'",
            "'order_detail_selector' => '[data-webby-ecommerce-order-detail]'",
            'function validateCheckout(cartId, payload) {',
            'function getOrders(params) {',
            'function getOrder(orderId) {',
            'function resolveOrderIdForWidget(container, options) {',
            'function mountCheckoutFormWidget(container, options) {',
            'data-webby-ecommerce-checkout-form-bound',
            'data-webby-ecommerce-checkout-form-state',
            'function mountOrderSummaryWidget(container, options) {',
            'data-webby-ecommerce-order-summary-bound',
            'data-webby-ecommerce-order-summary-state',
            'function mountShippingSelectorWidget(container, options) {',
            'data-webby-ecommerce-shipping-selector-bound',
            'data-webby-ecommerce-shipping-selector-state',
            'function mountPaymentSelectorWidget(container, options) {',
            'data-webby-ecommerce-payment-selector-bound',
            'data-webby-ecommerce-payment-selector-state',
            'function mountOrdersListWidget(container, options) {',
            'data-webby-ecommerce-orders-list-bound',
            'data-webby-ecommerce-orders-list-state',
            'function mountOrderDetailWidget(container, options) {',
            'data-webby-ecommerce-order-detail-bound',
            'data-webby-ecommerce-order-detail-state',
            "var checkoutFormSelector = (ecommerce.widgets && ecommerce.widgets.checkout_form_selector)",
            "var orderSummarySelector = (ecommerce.widgets && ecommerce.widgets.order_summary_selector)",
            "var shippingSelector = (ecommerce.widgets && ecommerce.widgets.shipping_selector)",
            "var paymentSelector = (ecommerce.widgets && ecommerce.widgets.payment_selector)",
            "var ordersListSelector = (ecommerce.widgets && ecommerce.widgets.orders_list_selector)",
            "var orderDetailSelector = (ecommerce.widgets && ecommerce.widgets.order_detail_selector)",
            'mountCheckoutFormWidget(node, {});',
            'mountOrderSummaryWidget(node, {});',
            'mountShippingSelectorWidget(node, {});',
            'mountPaymentSelectorWidget(node, {});',
            'mountOrdersListWidget(node, {});',
            'mountOrderDetailWidget(node, {});',
            'validateCheckout: validateCheckout,',
            'getOrders: getOrders,',
            'getOrder: getOrder,',
            'mountCheckoutFormWidget: mountCheckoutFormWidget,',
            'mountOrderSummaryWidget: mountOrderSummaryWidget,',
            'mountShippingSelectorWidget: mountShippingSelectorWidget,',
            'mountPaymentSelectorWidget: mountPaymentSelectorWidget,',
            'mountOrdersListWidget: mountOrdersListWidget,',
            'mountOrderDetailWidget: mountOrderDetailWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }
}

