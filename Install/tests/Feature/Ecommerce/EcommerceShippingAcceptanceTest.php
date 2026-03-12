<?php

namespace Tests\Feature\Ecommerce;

use App\Models\EcommerceShipmentEvent;
use App\Models\EcommerceProduct;
use App\Models\Plugin;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Plugins\Couriers\ManualCourier\ManualCourierPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommerceShippingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_shipping_happy_path_quote_selection_checkout_and_tracking_flow(): void
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
            'tracking_base_url' => 'https://tracking.example.com/items',
        ]);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Acceptance Shipping Product',
            'slug' => 'acceptance-shipping-product',
            'sku' => 'SHIP-ACC-1',
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
        ])
            ->assertOk()
            ->assertJsonPath('cart.subtotal', '80.00')
            ->assertJsonPath('cart.grand_total', '80.00');

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
        )
            ->assertOk()
            ->assertJsonPath('shipping.providers.0.provider', 'manual-courier')
            ->assertJsonPath('shipping.providers.0.rates.0.amount', '9.00');

        $shippingProvider = (string) $shippingOptionsResponse->json('shipping.providers.0.provider');
        $shippingRateId = (string) $shippingOptionsResponse->json('shipping.providers.0.rates.0.rate_id');

        $this->putJson(route('public.sites.ecommerce.carts.shipping.update', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_provider' => $shippingProvider,
            'shipping_rate_id' => $shippingRateId,
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertOk()
            ->assertJsonPath('cart.shipping_total', '9.00')
            ->assertJsonPath('cart.grand_total', '89.00')
            ->assertJsonPath('shipping.selected_rate.provider', 'manual-courier');

        $checkoutResponse = $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
            'shipping_address_json' => $shippingAddress,
        ])
            ->assertCreated()
            ->assertJsonPath('order.shipping_total', '9.00')
            ->assertJsonPath('order.grand_total', '89.00');

        $orderId = (int) $checkoutResponse->json('order.id');
        $orderNumber = (string) $checkoutResponse->json('order.order_number');
        $this->assertGreaterThan(0, $orderId);
        $this->assertNotSame('', $orderNumber);

        $shipmentResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.shipments.store', ['site' => $site->id, 'order' => $orderId]), [
                'provider_slug' => 'manual-courier',
                'shipment_reference' => 'ACC-SHP-1001',
                'tracking_number' => 'ACC-TRK-1001',
            ])
            ->assertCreated()
            ->assertJsonPath('shipment.status', 'created');

        $shipmentId = (int) $shipmentResponse->json('shipment.id');
        $this->assertGreaterThan(0, $shipmentId);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.shipments.refresh', [
                'site' => $site->id,
                'order' => $orderId,
                'shipment' => $shipmentId,
            ]), [
                'status_override' => 'in_transit',
            ])
            ->assertOk()
            ->assertJsonPath('shipment.status', 'in_transit');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.orders.show', ['site' => $site->id, 'order' => $orderId]))
            ->assertOk()
            ->assertJsonPath('order.fulfillment_status', 'partial')
            ->assertJsonPath('order.shipments.0.status', 'in_transit');

        $this->getJson(route('public.sites.ecommerce.shipments.track', [
            'site' => $site->id,
            'order_number' => $orderNumber,
            'shipment_reference' => 'ACC-SHP-1001',
        ]))
            ->assertOk()
            ->assertJsonPath('tracking.order_number', $orderNumber)
            ->assertJsonPath('tracking.shipment.shipment_reference', 'ACC-SHP-1001')
            ->assertJsonPath('tracking.shipment.status', 'in_transit');

        $this->assertDatabaseHas('ecommerce_shipment_events', [
            'site_id' => $site->id,
            'shipment_id' => $shipmentId,
            'event_type' => EcommerceShipmentEvent::TYPE_PUBLIC_TRACK,
        ]);
    }

    public function test_shipping_selection_resets_after_cart_change_and_invalid_rate_is_rejected(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'Manual Shipping',
            'base_rate' => 4,
            'per_item_rate' => 1,
            'currency' => 'GEL',
            'eta_min_days' => 1,
            'eta_max_days' => 2,
        ]);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Stale Shipping Guard Product',
            'slug' => 'stale-shipping-guard-product',
            'sku' => 'SHIP-ACC-2',
            'price' => '30.00',
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

        $addResponse = $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $itemId = (int) $addResponse->json('cart.items.0.id');
        $this->assertGreaterThan(0, $itemId);

        $shippingAddress = [
            'country_code' => 'GE',
            'city' => 'Tbilisi',
            'address' => 'Aghmashenebeli 5',
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

        $this->putJson(route('public.sites.ecommerce.carts.shipping.update', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_provider' => $shippingProvider,
            'shipping_rate_id' => $shippingRateId,
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertOk()
            ->assertJsonPath('cart.shipping_total', '5.00')
            ->assertJsonPath('cart.grand_total', '35.00');

        $this->putJson(route('public.sites.ecommerce.carts.items.update', [
            'site' => $site->id,
            'cart' => $cartId,
            'item' => $itemId,
        ]), [
            'quantity' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('cart.subtotal', '90.00')
            ->assertJsonPath('cart.shipping_total', '0.00')
            ->assertJsonPath('cart.grand_total', '90.00')
            ->assertJsonPath('cart.meta_json.shipping_selection', null);

        $this->putJson(route('public.sites.ecommerce.carts.shipping.update', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_provider' => $shippingProvider,
            'shipping_rate_id' => 'manual-courier:invalid-rate',
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Selected shipping rate is not available.');

        $this->putJson(route('public.sites.ecommerce.carts.shipping.update', ['site' => $site->id, 'cart' => $cartId]), [
            'shipping_provider' => $shippingProvider,
            'shipping_rate_id' => $shippingRateId,
            'shipping_address_json' => $shippingAddress,
            'currency' => 'GEL',
        ])
            ->assertOk()
            ->assertJsonPath('cart.shipping_total', '7.00')
            ->assertJsonPath('cart.grand_total', '97.00');

        $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
            'shipping_address_json' => $shippingAddress,
        ])
            ->assertCreated()
            ->assertJsonPath('order.shipping_total', '7.00')
            ->assertJsonPath('order.grand_total', '97.00');
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(User $owner, bool $enableEcommerce): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
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
