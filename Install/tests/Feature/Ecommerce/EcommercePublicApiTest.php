<?php

namespace Tests\Feature\Ecommerce;

use App\Cms\Contracts\CmsModuleRegistryServiceContract;
use App\Models\EcommerceCart;
use App\Models\EcommerceCategory;
use App\Models\EcommerceProduct;
use App\Models\EcommerceShipment;
use App\Models\Plan;
use App\Models\Plugin;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteCourierSetting;
use App\Models\SitePaymentGatewaySetting;
use App\Models\SystemSetting;
use App\Models\User;
use App\Plugins\PaymentGateways\BankOfGeorgia\BankOfGeorgiaPlugin;
use App\Plugins\PaymentGateways\Fleet\FleetPlugin;
use App\Plugins\Couriers\ManualCourier\ManualCourierPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommercePublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_public_products_endpoints_return_only_published_active_products(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Premium Dog Snack',
            'slug' => 'premium-dog-snack',
            'sku' => 'DOG-SNACK-1',
            'price' => '29.90',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 50,
            'published_at' => now(),
        ]);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Hidden Draft Product',
            'slug' => 'hidden-draft-product',
            'sku' => 'DRAFT-1',
            'price' => '99.00',
            'currency' => 'GEL',
            'status' => 'draft',
            'stock_tracking' => true,
            'stock_quantity' => 2,
        ]);

        $productsResponse = $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertOk();
        $slugs = array_column($productsResponse->json('products') ?? [], 'slug');
        $this->assertContains('premium-dog-snack', $slugs);
        $this->assertGreaterThanOrEqual(1, count($slugs));

        $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'premium-dog-snack']))
            ->assertOk()
            ->assertJsonPath('product.slug', 'premium-dog-snack');

        $this->getJson(route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'hidden-draft-product']))
            ->assertNotFound();
    }

    public function test_public_products_endpoint_accepts_source_style_catalog_query_aliases(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $categorySupplements = EcommerceCategory::query()->create([
            'site_id' => $site->id,
            'name' => 'Supplements',
            'slug' => 'supplements',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $categoryAccessories = EcommerceCategory::query()->create([
            'site_id' => $site->id,
            'name' => 'Accessories',
            'slug' => 'accessories',
            'status' => 'active',
            'sort_order' => 2,
        ]);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'category_id' => $categoryAccessories->id,
            'name' => 'Alpha Travel Bowl',
            'slug' => 'alpha-travel-bowl',
            'sku' => 'ALPHA-BOWL-1',
            'price' => '18.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 10,
            'published_at' => now()->subSeconds(30),
        ]);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'category_id' => $categorySupplements->id,
            'name' => 'Alpha Wellness Pack (Older)',
            'slug' => 'alpha-wellness-pack-older',
            'sku' => 'ALPHA-OLD-1',
            'price' => '30.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 20,
            'published_at' => now()->subMinutes(2),
        ]);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'category_id' => $categorySupplements->id,
            'name' => 'Alpha Wellness Pack (Newer)',
            'slug' => 'alpha-wellness-pack-newer',
            'sku' => 'ALPHA-NEW-1',
            'price' => '35.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 25,
            'published_at' => now()->subMinute(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.index', [
            'site' => $site->id,
            'q' => 'alpha',
            'category' => 'supplements',
            'per_page' => 1,
            'page' => 2,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.slug', 'alpha-wellness-pack-older')
            ->assertJsonPath('products.0.category.slug', 'supplements')
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('pagination.limit', 1)
            ->assertJsonPath('pagination.offset', 1)
            ->assertJsonPath('pagination.has_more', false);
    }

    public function test_public_cart_add_item_accepts_source_style_payload_aliases_product_slug_and_qty(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Alias Payload Product',
            'slug' => 'alias-payload-product',
            'sku' => 'ALIAS-001',
            'price' => '21.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 40,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
        ])->assertCreated()->json('cart.id');

        $this->assertNotSame('', $cartId);

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_slug' => 'alias-payload-product',
            'qty' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('cart.items.0.product_slug', 'alias-payload-product')
            ->assertJsonPath('cart.items.0.quantity', 2)
            ->assertJsonPath('cart.items.0.line_total', '42.00');
    }

    public function test_public_checkout_validate_endpoint_returns_preflight_shipping_payment_and_checkout_contract(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'Validate Courier',
            'base_rate' => 5,
            'per_item_rate' => 2,
            'currency' => 'GEL',
            'eta_min_days' => 1,
            'eta_max_days' => 2,
        ]);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Checkout Validate Product',
            'slug' => 'checkout-validate-product',
            'sku' => 'CHK-VALIDATE-1',
            'price' => '40.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 20,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'validate@example.com',
            'customer_name' => 'Validate Buyer',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson(route('public.sites.ecommerce.carts.checkout.validate', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'validate@example.com',
            'customer_name' => 'Validate Buyer',
            'shipping_address_json' => [
                'country_code' => 'GE',
                'city' => 'Tbilisi',
                'address' => 'Rustaveli 12',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('site_id', (string) $site->id)
            ->assertJsonPath('cart_id', $cartId)
            ->assertJsonPath('cart.items.0.product_slug', 'checkout-validate-product')
            ->assertJsonPath('validation.valid', true)
            ->assertJsonPath('validation.checkout_endpoint', route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]))
            ->assertJsonPath('payments.site_id', (string) $site->id)
            ->assertJsonPath('payments.providers.0.slug', 'manual')
            ->assertJsonPath('shipping.providers.0.provider', 'manual-courier')
            ->assertJsonPath('shipping.providers.0.rates.0.rate_id', 'manual-courier:manual-standard');
    }

    public function test_public_checkout_validate_endpoint_rejects_empty_cart(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'validate-empty@example.com',
            'customer_name' => 'Empty Cart Buyer',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.checkout.validate', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'validate-empty@example.com',
            'customer_name' => 'Empty Cart Buyer',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Cart is empty.');
    }

    public function test_public_customer_orders_endpoints_require_auth_and_are_scoped_to_authenticated_customer_email(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $viewer = User::factory()->create([
            'email' => 'buyer@example.com',
        ]);
        $otherViewer = User::factory()->create([
            'email' => 'other@example.com',
        ]);

        $buyerOrderId = $this->createCheckoutOrder($site);

        $otherProduct = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Other Customer Product',
            'slug' => 'other-customer-product',
            'sku' => 'OTH-001',
            'price' => '19.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 15,
            'published_at' => now(),
        ]);

        $otherCartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => $otherViewer->email,
            'customer_name' => 'Other Buyer',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $otherCartId]), [
            'product_id' => $otherProduct->id,
            'quantity' => 1,
        ])->assertOk();

        $otherOrderId = (int) $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $otherCartId]), [
            'customer_email' => $otherViewer->email,
            'customer_name' => 'Other Buyer',
        ])->assertCreated()->json('order.id');

        $this->getJson(route('public.sites.ecommerce.customer_orders.index', [
            'site' => $site->id,
            'per_page' => 10,
            'page' => 1,
        ]))
            ->assertStatus(401)
            ->assertJsonPath('reason', 'customer_auth_required');

        $this->actingAs($viewer)
            ->getJson(route('public.sites.ecommerce.customer_orders.index', [
                'site' => $site->id,
                'per_page' => 10,
                'page' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('meta.customer_email', 'buyer@example.com')
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('orders.0.id', $buyerOrderId)
            ->assertJsonPath('orders.0.customer_email', 'buyer@example.com');

        $this->actingAs($viewer)
            ->getJson(route('public.sites.ecommerce.customer_orders.show', ['site' => $site->id, 'order' => $buyerOrderId]))
            ->assertOk()
            ->assertJsonPath('order.id', $buyerOrderId)
            ->assertJsonPath('order.customer_email', 'buyer@example.com')
            ->assertJsonPath('order.items.0.quantity', 1);

        $this->actingAs($viewer)
            ->getJson(route('public.sites.ecommerce.customer_orders.show', ['site' => $site->id, 'order' => $otherOrderId]))
            ->assertNotFound();
    }

    public function test_public_storefront_end_to_end_matrix_covers_listing_product_cart_checkout_and_order_conversion(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $categorySupplements = EcommerceCategory::query()->create([
            'site_id' => $site->id,
            'name' => 'Supplements',
            'slug' => 'supplements',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $categoryAccessories = EcommerceCategory::query()->create([
            'site_id' => $site->id,
            'name' => 'Accessories',
            'slug' => 'accessories',
            'status' => 'active',
            'sort_order' => 2,
        ]);

        $alphaProduct = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'category_id' => $categorySupplements->id,
            'name' => 'Alpha Wellness Pack',
            'slug' => 'alpha-wellness-pack',
            'sku' => 'ALPHA-001',
            'short_description' => 'Alpha support formula',
            'description' => 'Detailed alpha product description for storefront product page.',
            'price' => '35.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 30,
            'allow_backorder' => false,
            'published_at' => now()->subMinute(),
        ]);

        $betaProduct = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'category_id' => $categoryAccessories->id,
            'name' => 'Beta Travel Bowl',
            'slug' => 'beta-travel-bowl',
            'sku' => 'BETA-001',
            'short_description' => 'Portable bowl for trips',
            'description' => 'Detailed beta product description for storefront product page.',
            'price' => '20.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 50,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'category_id' => $categorySupplements->id,
            'name' => 'Gamma Draft Product',
            'slug' => 'gamma-draft-product',
            'sku' => 'GAMMA-001',
            'price' => '999.00',
            'currency' => 'GEL',
            'status' => 'draft',
            'stock_tracking' => true,
            'stock_quantity' => 2,
            'allow_backorder' => false,
        ]);

        $paginatedResponse = $this->getJson(route('public.sites.ecommerce.products.index', [
            'site' => $site->id,
            'limit' => 1,
            'offset' => 0,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'products');
        $this->assertSame('beta-travel-bowl', $paginatedResponse->json('products.0.slug'));
        $this->assertGreaterThanOrEqual(2, (int) $paginatedResponse->json('pagination.total'));
        $this->assertSame(1, (int) $paginatedResponse->json('pagination.limit'));
        $this->assertSame(0, (int) $paginatedResponse->json('pagination.offset'));
        $this->assertTrue((bool) $paginatedResponse->json('pagination.has_more'));

        $this->getJson(route('public.sites.ecommerce.products.index', [
            'site' => $site->id,
            'search' => 'alpha',
            'category_slug' => 'supplements',
            'limit' => 10,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.slug', 'alpha-wellness-pack')
            ->assertJsonPath('products.0.category.slug', 'supplements')
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('pagination.has_more', false);

        $this->getJson(route('public.sites.ecommerce.products.show', [
            'site' => $site->id,
            'slug' => 'alpha-wellness-pack',
        ]))
            ->assertOk()
            ->assertJsonPath('product.slug', 'alpha-wellness-pack')
            ->assertJsonPath('product.category.slug', 'supplements')
            ->assertJsonPath('product.price', '35.00')
            ->assertJsonPath('product.description', 'Detailed alpha product description for storefront product page.');

        $cartResponse = $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'matrix-buyer@example.com',
            'customer_name' => 'Matrix Buyer',
            'meta_json' => ['source' => 'e2e-matrix'],
        ])->assertCreated();

        $cartId = (string) $cartResponse->json('cart.id');
        $this->assertNotSame('', $cartId);
        $cartResponse
            ->assertJsonPath('cart.items', [])
            ->assertJsonPath('cart.meta_json.source', 'e2e-matrix');

        $this->getJson(route('public.sites.ecommerce.carts.show', ['site' => $site->id, 'cart' => $cartId]))
            ->assertOk()
            ->assertJsonPath('cart.id', $cartId)
            ->assertJsonCount(0, 'cart.items');

        $addAlphaResponse = $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $alphaProduct->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('cart.subtotal', '70.00')
            ->assertJsonPath('cart.grand_total', '70.00')
            ->assertJsonPath('cart.items.0.product_slug', 'alpha-wellness-pack');

        $cartItemId = (int) $addAlphaResponse->json('cart.items.0.id');
        $this->assertGreaterThan(0, $cartItemId);

        $this->getJson(route('public.sites.ecommerce.carts.show', ['site' => $site->id, 'cart' => $cartId]))
            ->assertOk()
            ->assertJsonPath('cart.items.0.id', $cartItemId)
            ->assertJsonPath('cart.items.0.quantity', 2)
            ->assertJsonPath('cart.items.0.product_url', route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => 'alpha-wellness-pack']));

        $this->putJson(route('public.sites.ecommerce.carts.items.update', [
            'site' => $site->id,
            'cart' => $cartId,
            'item' => $cartItemId,
        ]), [
            'quantity' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 3)
            ->assertJsonPath('cart.items.0.line_total', '105.00')
            ->assertJsonPath('cart.subtotal', '105.00')
            ->assertJsonPath('cart.grand_total', '105.00');

        $this->deleteJson(route('public.sites.ecommerce.carts.items.destroy', [
            'site' => $site->id,
            'cart' => $cartId,
            'item' => $cartItemId,
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'cart.items')
            ->assertJsonPath('cart.subtotal', '0.00')
            ->assertJsonPath('cart.grand_total', '0.00');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $alphaProduct->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $betaProduct->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonCount(2, 'cart.items')
            ->assertJsonPath('cart.subtotal', '75.00')
            ->assertJsonPath('cart.grand_total', '75.00');

        $checkoutResponse = $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'matrix-buyer@example.com',
            'customer_name' => 'Matrix Buyer',
            'shipping_address_json' => [
                'country_code' => 'GE',
                'city' => 'Tbilisi',
                'address' => 'Rustaveli 15',
            ],
            'billing_address_json' => [
                'country_code' => 'GE',
                'city' => 'Tbilisi',
                'address' => 'Rustaveli 15',
            ],
            'notes' => 'Please ring the bell',
            'meta_json' => [
                'checkout_flow' => 'e2e-matrix',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('cart_id', $cartId)
            ->assertJsonPath('order.customer_email', 'matrix-buyer@example.com')
            ->assertJsonPath('order.customer_name', 'Matrix Buyer')
            ->assertJsonPath('order.subtotal', '75.00')
            ->assertJsonPath('order.grand_total', '75.00')
            ->assertJsonPath('order.outstanding_total', '75.00')
            ->assertJsonPath('order.notes', 'Please ring the bell')
            ->assertJsonPath('order.meta_json.source', 'public_storefront')
            ->assertJsonPath('order.meta_json.checkout_payload.checkout_flow', 'e2e-matrix')
            ->assertJsonCount(2, 'order.items');

        $orderId = (int) $checkoutResponse->json('order.id');
        $this->assertGreaterThan(0, $orderId);
        $this->assertIsString($checkoutResponse->json('order.order_number'));
        $this->assertNotSame('', (string) $checkoutResponse->json('order.order_number'));
        $this->assertSame(
            route('public.sites.ecommerce.orders.payment.start', ['site' => $site->id, 'order' => $orderId]),
            (string) $checkoutResponse->json('meta.payment_start_endpoint')
        );

        $this->assertDatabaseHas('ecommerce_carts', [
            'id' => $cartId,
            'site_id' => $site->id,
            'status' => 'converted',
            'converted_order_id' => $orderId,
        ]);
        $this->assertDatabaseHas('ecommerce_orders', [
            'id' => $orderId,
            'site_id' => $site->id,
            'customer_email' => 'matrix-buyer@example.com',
            'subtotal' => '75.00',
            'grand_total' => '75.00',
        ]);
        $this->assertSame(
            2,
            \App\Models\EcommerceOrderItem::query()->where('order_id', $orderId)->count(),
            'Expected checkout to create two order items from cart line items.'
        );

        $this->postJson(route('public.sites.ecommerce.orders.payment.start', ['site' => $site->id, 'order' => $orderId]), [
            'provider' => 'manual',
            'method' => 'cash_on_delivery',
            'is_installment' => false,
        ])
            ->assertOk()
            ->assertJsonPath('payment.provider', 'manual')
            ->assertJsonPath('payment.amount', '75.00');
    }

    public function test_public_cart_checkout_and_payment_start_flow_works(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Vet Consultation Package',
            'slug' => 'vet-consultation-package',
            'sku' => 'VET-001',
            'price' => '80.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 20,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $createCartResponse = $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated();

        $cartId = (string) $createCartResponse->json('cart.id');
        $this->assertNotSame('', $cartId);

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('cart.subtotal', '160.00')
            ->assertJsonPath('cart.grand_total', '160.00');

        $checkoutResponse = $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
            'shipping_address_json' => [
                'city' => 'Tbilisi',
                'address' => 'Rustaveli 1',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('order.grand_total', '160.00')
            ->assertJsonPath('order.outstanding_total', '160.00');

        $orderId = (int) $checkoutResponse->json('order.id');
        $this->assertGreaterThan(0, $orderId);

        $this->assertDatabaseHas('ecommerce_orders', [
            'id' => $orderId,
            'site_id' => $site->id,
            'customer_email' => 'buyer@example.com',
            'grand_total' => '160.00',
            'outstanding_total' => '160.00',
        ]);

        $this->assertDatabaseHas('ecommerce_order_items', [
            'site_id' => $site->id,
            'order_id' => $orderId,
            'product_id' => $product->id,
            'quantity' => 2,
            'line_total' => '160.00',
        ]);

        $this->assertDatabaseHas('ecommerce_carts', [
            'id' => $cartId,
            'site_id' => $site->id,
            'status' => 'converted',
            'converted_order_id' => $orderId,
        ]);

        $this->postJson(route('public.sites.ecommerce.orders.payment.start', ['site' => $site->id, 'order' => $orderId]), [
            'provider' => 'manual',
            'method' => 'bank_transfer',
            'is_installment' => false,
        ])
            ->assertOk()
            ->assertJsonPath('payment.provider', 'manual')
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.amount', '160.00');

        $this->assertDatabaseHas('ecommerce_order_payments', [
            'site_id' => $site->id,
            'order_id' => $orderId,
            'provider' => 'manual',
            'status' => 'pending',
            'amount' => '160.00',
        ]);

        $this->postJson(route('public.sites.ecommerce.orders.payment.start', ['site' => $site->id, 'order' => $orderId]), [
            'provider' => 'manual',
            'method' => 'installment',
            'is_installment' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Installment payments require an online payment provider.');
    }

    public function test_coupon_can_be_applied_and_removed_with_totals_recalculated(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Coupon Test Product',
            'slug' => 'coupon-test-product',
            'sku' => 'COUPON-001',
            'price' => '50.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 30,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'coupon@example.com',
            'customer_name' => 'Coupon Buyer',
        ])->assertCreated()->json('cart.id');

        $addResponse = $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $cartItemId = (int) $addResponse->json('cart.items.0.id');
        $this->assertGreaterThan(0, $cartItemId);

        $this->postJson(route('public.sites.ecommerce.carts.coupon.apply', ['site' => $site->id, 'cart' => $cartId]), [
            'code' => 'save10',
            'meta_json' => ['source' => 'coupon-ui-test'],
        ])
            ->assertOk()
            ->assertJsonPath('coupon.code', 'SAVE10')
            ->assertJsonPath('coupon.type', 'percent')
            ->assertJsonPath('coupon.source', 'built_in')
            ->assertJsonPath('coupon.effective_discount_total', '10.00')
            ->assertJsonPath('coupon.meta_json.source', 'coupon-ui-test')
            ->assertJsonPath('cart.discount_total', '10.00')
            ->assertJsonPath('cart.grand_total', '90.00');

        $this->putJson(route('public.sites.ecommerce.carts.items.update', [
            'site' => $site->id,
            'cart' => $cartId,
            'item' => $cartItemId,
        ]), [
            'quantity' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('cart.subtotal', '150.00')
            ->assertJsonPath('cart.discount_total', '15.00')
            ->assertJsonPath('cart.grand_total', '135.00')
            ->assertJsonPath('cart.coupon.code', 'SAVE10')
            ->assertJsonPath('cart.coupon.effective_discount_total', '15.00');

        $this->deleteJson(route('public.sites.ecommerce.carts.coupon.remove', ['site' => $site->id, 'cart' => $cartId]))
            ->assertOk()
            ->assertJsonPath('coupon', null)
            ->assertJsonPath('cart.discount_total', '0.00')
            ->assertJsonPath('cart.grand_total', '150.00')
            ->assertJsonPath('cart.coupon', null);

        $this->postJson(route('public.sites.ecommerce.carts.coupon.apply', ['site' => $site->id, 'cart' => $cartId]), [
            'code' => 'NOPE-INVALID',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Coupon code is invalid.');
    }

    public function test_cart_identity_token_can_resume_guest_cart_and_reject_mismatch(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Identity Test Product',
            'slug' => 'identity-test-product',
            'sku' => 'IDENTITY-001',
            'price' => '25.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 40,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $createCart = $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'guest@example.com',
            'customer_name' => 'Guest Buyer',
        ])->assertCreated();

        $cartId = (string) $createCart->json('cart.id');
        $this->assertNotSame('', $cartId);

        $identityToken = (string) ($createCart->json('meta.cart_identity_token') ?? '');
        $this->assertNotSame('', $identityToken);
        $this->assertSame($identityToken, (string) $createCart->json('cart.meta_json.identity.token'));
        $this->assertJson($createCart->json('cart.meta_json.identity') ? json_encode($createCart->json('cart.meta_json.identity')) : '{}');

        $resumeCart = $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'cart_identity_token' => $identityToken,
            'customer_name' => 'Resumed Guest Buyer',
        ])->assertCreated();

        $resumeCart
            ->assertJsonPath('cart.id', $cartId)
            ->assertJsonPath('meta.cart_identity_reused', true)
            ->assertJsonPath('cart.customer_name', 'Resumed Guest Buyer');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'cart_identity_token' => $identityToken,
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('cart.subtotal', '50.00');

        $this->getJson(route('public.sites.ecommerce.carts.show', [
            'site' => $site->id,
            'cart' => $cartId,
            'cart_identity_token' => 'wrong-token',
        ]))
            ->assertStatus(409)
            ->assertJsonPath('error', 'Cart identity token mismatch.')
            ->assertJsonPath('code', 'cart_identity_mismatch');

        $this->putJson(route('public.sites.ecommerce.carts.shipping.update', ['site' => $site->id, 'cart' => $cartId]), [
            'cart_identity_token' => 'wrong-token',
            'shipping_provider' => 'manual-courier',
            'shipping_rate_id' => 'manual-courier:manual-standard',
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'cart_identity_mismatch');
    }

    public function test_shipping_options_can_be_selected_and_persisted_to_order_on_checkout(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'City Express',
            'base_rate' => 5,
            'per_item_rate' => 2,
            'currency' => 'GEL',
            'eta_min_days' => 1,
            'eta_max_days' => 2,
        ]);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Shipping Test Product',
            'slug' => 'shipping-test-product',
            'sku' => 'SHIP-001',
            'price' => '40.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 20,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $shippingAddress = [
            'country_code' => 'GE',
            'city' => 'Tbilisi',
            'address' => 'Rustaveli 10',
        ];

        $shippingOptionsResponse = $this->postJson(
            route('public.sites.ecommerce.carts.shipping.options', ['site' => $site->id, 'cart' => $cartId]),
            [
                'shipping_address_json' => $shippingAddress,
                'currency' => 'GEL',
            ]
        )->assertOk();

        $shippingProvider = (string) $shippingOptionsResponse->json('shipping.providers.0.provider');
        $shippingRateId = (string) $shippingOptionsResponse->json('shipping.providers.0.rates.0.rate_id');

        $this->assertSame('manual-courier', $shippingProvider);
        $this->assertSame('manual-courier:manual-standard', $shippingRateId);
        $this->assertSame('9.00', (string) $shippingOptionsResponse->json('shipping.providers.0.rates.0.amount'));

        $this->putJson(route('public.sites.ecommerce.carts.shipping.update', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_provider' => $shippingProvider,
            'shipping_rate_id' => $shippingRateId,
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertOk()
            ->assertJsonPath('cart.shipping_total', '9.00')
            ->assertJsonPath('cart.grand_total', '89.00')
            ->assertJsonPath('shipping.selected_rate.provider', 'manual-courier')
            ->assertJsonPath('shipping.selected_rate.rate_id', 'manual-courier:manual-standard');

        $checkoutResponse = $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
            'shipping_address_json' => $shippingAddress,
        ])
            ->assertCreated()
            ->assertJsonPath('order.shipping_total', '9.00')
            ->assertJsonPath('order.grand_total', '89.00');

        $orderId = (int) $checkoutResponse->json('order.id');
        $this->assertDatabaseHas('ecommerce_orders', [
            'id' => $orderId,
            'site_id' => $site->id,
            'shipping_total' => '9.00',
            'grand_total' => '89.00',
            'outstanding_total' => '89.00',
        ]);
    }

    public function test_shipping_options_respect_site_level_courier_availability_settings(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'Manual Default',
            'base_rate' => 5,
            'per_item_rate' => 1,
            'currency' => 'GEL',
            'eta_min_days' => 1,
            'eta_max_days' => 2,
        ]);

        SiteCourierSetting::query()->create([
            'site_id' => $site->id,
            'courier_slug' => 'manual-courier',
            'availability' => SiteCourierSetting::AVAILABILITY_DISABLED,
            'config' => [],
        ]);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Courier Toggle Product',
            'slug' => 'courier-toggle-product',
            'sku' => 'COUR-001',
            'price' => '25.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 10,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $shippingAddress = [
            'country_code' => 'GE',
            'city' => 'Tbilisi',
            'address' => 'Aghmashenebeli 5',
        ];

        $this->postJson(route('public.sites.ecommerce.carts.shipping.options', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertOk()
            ->assertJsonCount(0, 'shipping.providers');

        $setting = SiteCourierSetting::query()
            ->where('site_id', $site->id)
            ->where('courier_slug', 'manual-courier')
            ->firstOrFail();

        $setting->update([
            'availability' => SiteCourierSetting::AVAILABILITY_ENABLED,
            'config' => [
                'service_name' => 'Site Courier',
                'base_rate' => 13,
                'per_item_rate' => 0,
                'currency' => 'GEL',
                'eta_min_days' => 1,
                'eta_max_days' => 2,
            ],
        ]);

        $this->postJson(route('public.sites.ecommerce.carts.shipping.options', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertOk()
            ->assertJsonPath('shipping.providers.0.provider', 'manual-courier')
            ->assertJsonPath('shipping.providers.0.rates.0.amount', '13.00');
    }

    public function test_shipping_options_are_blocked_when_plan_disables_shipping_methods(): void
    {
        $restrictedPlan = Plan::factory()
            ->withEcommerce(true)
            ->withShipping(false)
            ->create([
                'name' => 'No Shipping',
            ]);

        $owner = User::factory()->withPlan($restrictedPlan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'City Express',
            'base_rate' => 5,
            'per_item_rate' => 2,
            'currency' => 'GEL',
            'eta_min_days' => 1,
            'eta_max_days' => 2,
        ]);

        $cartId = $this->createCartWithSingleItem($site);

        $shippingAddress = [
            'country_code' => 'GE',
            'city' => 'Tbilisi',
            'address' => 'Rustaveli 10',
        ];

        $this->postJson(route('public.sites.ecommerce.carts.shipping.options', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertOk()
            ->assertJsonCount(0, 'shipping.providers');

        $this->putJson(route('public.sites.ecommerce.carts.shipping.update', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_provider' => 'manual-courier',
            'shipping_rate_id' => 'manual-courier:manual-standard',
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'shipping_not_enabled');
    }

    public function test_shipping_options_respect_plan_allowed_courier_providers(): void
    {
        $restrictedPlan = Plan::factory()
            ->withEcommerce(true)
            ->withShipping(true)
            ->withAllowedCourierProviders(['fleet-courier'])
            ->create([
                'name' => 'Fleet Courier Only',
            ]);

        $owner = User::factory()->withPlan($restrictedPlan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'City Express',
            'base_rate' => 5,
            'per_item_rate' => 2,
            'currency' => 'GEL',
            'eta_min_days' => 1,
            'eta_max_days' => 2,
        ]);

        $cartId = $this->createCartWithSingleItem($site);

        $shippingAddress = [
            'country_code' => 'GE',
            'city' => 'Tbilisi',
            'address' => 'Rustaveli 10',
        ];

        $this->postJson(route('public.sites.ecommerce.carts.shipping.options', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertOk()
            ->assertJsonCount(0, 'shipping.providers');

        $this->putJson(route('public.sites.ecommerce.carts.shipping.update', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_provider' => 'manual-courier',
            'shipping_rate_id' => 'manual-courier:manual-standard',
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'courier_provider_not_allowed');

        $restrictedPlan->update([
            'allowed_courier_providers' => ['manual-courier'],
        ]);

        $this->postJson(route('public.sites.ecommerce.carts.shipping.options', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertOk()
            ->assertJsonPath('shipping.providers.0.provider', 'manual-courier');
    }

    public function test_public_shipment_tracking_returns_status_by_order_number_and_reference(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'City Express',
            'base_rate' => 5,
            'per_item_rate' => 1,
            'currency' => 'GEL',
            'tracking_base_url' => 'https://tracking.example.com/items',
        ]);

        $order = \App\Models\EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'ORD-TRACK-1001',
            'status' => 'processing',
            'payment_status' => 'paid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
            'subtotal' => '100.00',
            'tax_total' => '0.00',
            'shipping_total' => '7.00',
            'discount_total' => '0.00',
            'grand_total' => '107.00',
            'paid_total' => '107.00',
            'outstanding_total' => '0.00',
            'placed_at' => now(),
        ]);

        EcommerceShipment::query()->create([
            'site_id' => $site->id,
            'order_id' => $order->id,
            'provider_slug' => 'manual-courier',
            'shipment_reference' => 'MAN-REF-1001',
            'tracking_number' => 'TRK-1001',
            'tracking_url' => 'https://tracking.example.com/items/TRK-1001',
            'status' => 'created',
            'meta_json' => [],
        ]);

        $this->getJson(route('public.sites.ecommerce.shipments.track', [
            'site' => $site->id,
            'order_number' => 'ORD-TRACK-1001',
            'shipment_reference' => 'MAN-REF-1001',
        ]))
            ->assertOk()
            ->assertJsonPath('tracking.order_number', 'ORD-TRACK-1001')
            ->assertJsonPath('tracking.shipment.provider_slug', 'manual-courier')
            ->assertJsonPath('tracking.shipment.shipment_reference', 'MAN-REF-1001')
            ->assertJsonPath('tracking.shipment.status', 'in_transit');

        $this->getJson(route('public.sites.ecommerce.shipments.track', [
            'site' => $site->id,
            'order_number' => 'ORD-UNKNOWN',
            'shipment_reference' => 'MAN-REF-1001',
        ]))
            ->assertNotFound();
    }

    public function test_payment_options_endpoint_returns_manual_and_active_local_gateways(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateGateway('bank-of-georgia', BankOfGeorgiaPlugin::class, [
            'sandbox' => true,
            'client_id' => 'bog-client-id',
            'client_secret' => 'bog-client-secret',
            'merchant_id' => 'bog-merchant-id',
        ]);

        $this->activateGateway('fleet', FleetPlugin::class, [
            'sandbox' => true,
            'merchant_id' => 'fleet-merchant-id',
            'merchant_secret' => 'fleet-merchant-secret',
        ]);

        $response = $this->getJson(route('public.sites.ecommerce.payment.options', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('site_id', (string) $site->id)
            ->assertJsonPath('currency', 'GEL');

        $providers = collect($response->json('providers', []));
        $this->assertTrue($providers->contains(fn (array $provider): bool => ($provider['slug'] ?? null) === 'manual'));
        $this->assertTrue($providers->contains(fn (array $provider): bool => ($provider['slug'] ?? null) === 'bank-of-georgia' && ($provider['supports_installment'] ?? false) === true));
        $this->assertTrue($providers->contains(fn (array $provider): bool => ($provider['slug'] ?? null) === 'fleet' && ($provider['supports_installment'] ?? false) === true));
    }

    public function test_payment_options_respect_site_level_provider_availability_settings(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateGateway('bank-of-georgia', BankOfGeorgiaPlugin::class, [
            'sandbox' => true,
            'client_id' => 'bog-client-id',
            'client_secret' => 'bog-client-secret',
            'merchant_id' => 'bog-merchant-id',
        ]);
        $this->activateGateway('fleet', FleetPlugin::class, [
            'sandbox' => true,
            'merchant_id' => 'fleet-merchant-id',
            'merchant_secret' => 'fleet-merchant-secret',
        ]);

        SitePaymentGatewaySetting::query()->create([
            'site_id' => $site->id,
            'provider_slug' => 'bank-of-georgia',
            'availability' => SitePaymentGatewaySetting::AVAILABILITY_DISABLED,
            'config' => [],
        ]);

        SitePaymentGatewaySetting::query()->create([
            'site_id' => $site->id,
            'provider_slug' => 'fleet',
            'availability' => SitePaymentGatewaySetting::AVAILABILITY_ENABLED,
            'config' => [],
        ]);

        $response = $this->getJson(route('public.sites.ecommerce.payment.options', ['site' => $site->id]))
            ->assertOk();

        $providers = collect($response->json('providers', []));
        $this->assertTrue($providers->contains(fn (array $provider): bool => ($provider['slug'] ?? null) === 'manual'));
        $this->assertFalse($providers->contains(fn (array $provider): bool => ($provider['slug'] ?? null) === 'bank-of-georgia'));
        $this->assertTrue($providers->contains(fn (array $provider): bool => ($provider['slug'] ?? null) === 'fleet'));
    }

    public function test_payment_start_rejects_site_disabled_provider(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateGateway('bank-of-georgia', BankOfGeorgiaPlugin::class, [
            'sandbox' => true,
            'client_id' => 'bog-client-id',
            'client_secret' => 'bog-client-secret',
            'merchant_id' => 'bog-merchant-id',
        ]);

        SitePaymentGatewaySetting::query()->create([
            'site_id' => $site->id,
            'provider_slug' => 'bank-of-georgia',
            'availability' => SitePaymentGatewaySetting::AVAILABILITY_DISABLED,
            'config' => [],
        ]);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Disabled Provider Product',
            'slug' => 'disabled-provider-product',
            'sku' => 'DISABLED-1',
            'price' => '20.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 20,
            'published_at' => now(),
        ]);

        $cartResponse = $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated();

        $cartId = (string) $cartResponse->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $orderId = (int) $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated()->json('order.id');

        $this->postJson(route('public.sites.ecommerce.orders.payment.start', ['site' => $site->id, 'order' => $orderId]), [
            'provider' => 'bank-of-georgia',
            'method' => 'card',
            'is_installment' => false,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'Selected payment provider is not available.');
    }

    public function test_payment_options_hide_online_gateways_when_plan_disables_online_payments(): void
    {
        $restrictedPlan = Plan::factory()
            ->withEcommerce(true)
            ->withOnlinePayments(false)
            ->withInstallments(false)
            ->create([
                'name' => 'Offline Only',
            ]);

        $owner = User::factory()->withPlan($restrictedPlan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateGateway('bank-of-georgia', BankOfGeorgiaPlugin::class, [
            'sandbox' => true,
            'client_id' => 'bog-client-id',
            'client_secret' => 'bog-client-secret',
            'merchant_id' => 'bog-merchant-id',
        ]);
        $this->activateGateway('fleet', FleetPlugin::class, [
            'sandbox' => true,
            'merchant_id' => 'fleet-merchant-id',
            'merchant_secret' => 'fleet-merchant-secret',
        ]);

        $response = $this->getJson(route('public.sites.ecommerce.payment.options', ['site' => $site->id]))
            ->assertOk();

        $providers = collect($response->json('providers', []));
        $this->assertCount(1, $providers);
        $this->assertSame('manual', $providers->first()['slug'] ?? null);
    }

    public function test_payment_plan_enforcement_blocks_disallowed_provider_and_installments(): void
    {
        Http::fake([
            'https://sandbox.pay.flitt.dev/api/checkout/url' => Http::response([
                'status' => 'success',
                'checkout_url' => 'https://sandbox.pay.flitt.dev/checkout/fleet-session',
                'payment_id' => 'fleet-payment-123',
                'order_status' => 'created',
            ], 200),
        ]);

        $restrictedPlan = Plan::factory()
            ->withEcommerce(true)
            ->withOnlinePayments(true)
            ->withInstallments(false)
            ->withAllowedPaymentProviders(['fleet'])
            ->create([
                'name' => 'Fleet Only No Installments',
            ]);

        $owner = User::factory()->withPlan($restrictedPlan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateGateway('bank-of-georgia', BankOfGeorgiaPlugin::class, [
            'sandbox' => true,
            'client_id' => 'bog-client-id',
            'client_secret' => 'bog-client-secret',
            'merchant_id' => 'bog-merchant-id',
        ]);
        $this->activateGateway('fleet', FleetPlugin::class, [
            'sandbox' => true,
            'merchant_id' => 'fleet-merchant-id',
            'merchant_secret' => 'fleet-merchant-secret',
        ]);

        $orderId = $this->createCheckoutOrder($site);

        $this->postJson(route('public.sites.ecommerce.orders.payment.start', ['site' => $site->id, 'order' => $orderId]), [
            'provider' => 'bank-of-georgia',
            'method' => 'card',
            'is_installment' => false,
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'payment_provider_not_allowed');

        $this->postJson(route('public.sites.ecommerce.orders.payment.start', ['site' => $site->id, 'order' => $orderId]), [
            'provider' => 'fleet',
            'method' => 'installment',
            'is_installment' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'installments_not_enabled');

        $this->postJson(route('public.sites.ecommerce.orders.payment.start', ['site' => $site->id, 'order' => $orderId]), [
            'provider' => 'fleet',
            'method' => 'card',
            'is_installment' => false,
        ])
            ->assertOk()
            ->assertJsonPath('payment.provider', 'fleet')
            ->assertJsonPath('payment.is_installment', false);
    }

    public function test_private_storefront_is_hidden_for_guest_but_visible_for_owner(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true, true);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Private Product',
            'slug' => 'private-product',
            'sku' => 'PRV-001',
            'price' => '15.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 5,
            'published_at' => now(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertNotFound();

        $privateResponse = $this->actingAs($owner)
            ->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertOk();
        $slugs = array_column($privateResponse->json('products') ?? [], 'slug');
        $this->assertGreaterThanOrEqual(1, count($slugs));
    }

    public function test_draft_storefront_preview_is_visible_for_owner_only_when_draft_query_is_enabled(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createDraftProjectWithSite($owner, true);

        $this->instance(CmsModuleRegistryServiceContract::class, new class implements CmsModuleRegistryServiceContract
        {
            public function modules(Site $site, ?User $user = null): array
            {
                return [
                    'site_id' => (string) $site->id,
                    'project_id' => (string) $site->project_id,
                    'modules' => [[
                        'key' => 'ecommerce',
                        'available' => true,
                        'enabled' => true,
                        'requested' => true,
                        'implemented' => true,
                        'globally_enabled' => true,
                        'entitled' => true,
                        'project_type_allowed' => true,
                        'project_type_gate' => [
                            'framework_enabled' => false,
                            'project_type' => null,
                            'reason' => null,
                            'rule' => null,
                        ],
                        'reason' => null,
                    ]],
                    'summary' => [
                        'total' => 1,
                        'available' => 1,
                        'disabled' => 0,
                        'not_entitled' => 0,
                        'blocked_by_project_type' => 0,
                    ],
                ];
            }

            public function entitlements(Site $site, ?User $user = null): array
            {
                return [
                    'site_id' => (string) $site->id,
                    'project_id' => (string) $site->project_id,
                    'features' => [],
                    'modules' => ['ecommerce' => true],
                    'reasons' => ['ecommerce' => null],
                    'plan' => null,
                ];
            }
        });

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Draft Preview Product',
            'slug' => 'draft-preview-product',
            'sku' => 'DRF-001',
            'price' => '15.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 5,
            'published_at' => now(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id, 'draft' => 1]))
            ->assertOk()
            ->assertJsonPath('products.0.slug', 'draft-preview-product');

        $this->actingAs($owner)
            ->getJson(route('public.sites.ecommerce.products.show', [
                'site' => $site->id,
                'slug' => 'draft-preview-product',
                'draft' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('product.slug', 'draft-preview-product');

        $this->actingAs($owner)
            ->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id, 'draft' => 1]), [
                'currency' => 'GEL',
                'customer_email' => 'draft-preview@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('site_id', (string) $site->id);
    }

    public function test_storefront_returns_not_found_when_ecommerce_module_not_enabled(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, false);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Module Disabled Product',
            'slug' => 'module-disabled-product',
            'sku' => 'MOD-001',
            'price' => '10.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 4,
            'published_at' => now(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertNotFound();
    }

    private function createPublishedProjectWithSite(User $owner, bool $enableEcommerce, bool $private = false): array
    {
        $project = Project::factory()
            ->for($owner)
            ->when(
                $private,
                fn ($factory) => $factory->privatePublished(strtolower(Str::random(10))),
                fn ($factory) => $factory->published(strtolower(Str::random(10)))
            )
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $moduleSettings = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $moduleSettings['ecommerce'] = $enableEcommerce;
        $settings['modules'] = $moduleSettings;
        $site->update(['theme_settings' => $settings]);

        return [$project, $site];
    }

    private function createDraftProjectWithSite(User $owner, bool $enableEcommerce): array
    {
        [$project, $site] = $this->createPublishedProjectWithSite($owner, $enableEcommerce);
        $project->forceFill([
            'published_at' => null,
        ])->save();
        $site->forceFill([
            'status' => 'draft',
        ])->save();

        return [$project, $site];
    }

    private function activateGateway(string $slug, string $class, array $config): void
    {
        Plugin::query()->create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'type' => 'payment_gateway',
            'class' => $class,
            'version' => '1.0.0',
            'status' => 'active',
            'config' => $config,
            'metadata' => ['slug' => $slug],
            'migrations' => null,
            'installed_at' => now(),
        ]);
    }

    private function createCheckoutOrder(Site $site): int
    {
        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Payment Plan Product',
            'slug' => 'payment-plan-product-'.strtolower(Str::random(6)),
            'sku' => 'PAY-PLAN-'.strtoupper(Str::random(6)),
            'price' => '30.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 50,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        return (int) $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated()->json('order.id');
    }

    private function createCartWithSingleItem(Site $site): string
    {
        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Shipping Policy Product',
            'slug' => 'shipping-policy-product-'.strtolower(Str::random(6)),
            'sku' => 'SHIP-POL-'.strtoupper(Str::random(6)),
            'price' => '40.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 20,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        return $cartId;
    }

    private function activateCourier(string $slug, string $class, array $config): void
    {
        Plugin::query()->create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'type' => 'courier',
            'class' => $class,
            'version' => '1.0.0',
            'status' => 'active',
            'config' => $config,
            'metadata' => ['slug' => $slug],
            'migrations' => null,
            'installed_at' => now(),
        ]);
    }
}
