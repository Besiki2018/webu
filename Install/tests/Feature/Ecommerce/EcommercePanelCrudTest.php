<?php

namespace Tests\Feature\Ecommerce;

use App\Models\EcommerceCategory;
use App\Models\EcommerceOrder;
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
use App\Plugins\Couriers\ManualCourier\ManualCourierPlugin;
use App\Plugins\PaymentGateways\BankOfGeorgia\BankOfGeorgiaPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommercePanelCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_owner_can_crud_categories_and_products_in_site_scope(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $categoryResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.categories.store', ['site' => $site->id]), [
                'name' => 'Pet Food',
                'slug' => 'pet-food',
                'status' => 'active',
                'sort_order' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('category.site_id', $site->id);

        $categoryId = (int) $categoryResponse->json('category.id');
        $this->assertDatabaseHas('ecommerce_categories', [
            'id' => $categoryId,
            'site_id' => $site->id,
            'slug' => 'pet-food',
        ]);

        $productResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.products.store', ['site' => $site->id]), [
                'category_id' => $categoryId,
                'name' => 'Premium Dog Snack',
                'slug' => 'premium-dog-snack',
                'sku' => 'DOG-SNACK-001',
                'price' => '49.90',
                'currency' => 'GEL',
                'status' => 'draft',
                'stock_tracking' => true,
                'stock_quantity' => 15,
            ])
            ->assertCreated()
            ->assertJsonPath('product.site_id', $site->id);

        $productId = (int) $productResponse->json('product.id');
        $this->assertDatabaseHas('ecommerce_products', [
            'id' => $productId,
            'site_id' => $site->id,
            'slug' => 'premium-dog-snack',
            'sku' => 'DOG-SNACK-001',
        ]);

        $categoriesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.categories.index', ['site' => $site->id]))
            ->assertOk();
        $categorySlugs = array_column($categoriesResponse->json('categories') ?? [], 'slug');
        $this->assertContains('pet-food', $categorySlugs);

        $productsResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertOk();
        $productSlugs = array_column($productsResponse->json('products') ?? [], 'slug');
        $this->assertContains('premium-dog-snack', $productSlugs);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.products.show', ['site' => $site->id, 'product' => $productId]))
            ->assertOk()
            ->assertJsonPath('product.category_id', $categoryId);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.products.update', ['site' => $site->id, 'product' => $productId]), [
                'status' => 'active',
                'stock_quantity' => 42,
            ])
            ->assertOk();

        $this->assertDatabaseHas('ecommerce_products', [
            'id' => $productId,
            'site_id' => $site->id,
            'status' => 'active',
            'stock_quantity' => 42,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('panel.sites.ecommerce.products.destroy', ['site' => $site->id, 'product' => $productId]))
            ->assertOk();

        $this->assertSoftDeleted('ecommerce_products', [
            'id' => $productId,
            'site_id' => $site->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('panel.sites.ecommerce.categories.destroy', ['site' => $site->id, 'category' => $categoryId]))
            ->assertOk();

        $this->assertDatabaseMissing('ecommerce_categories', [
            'id' => $categoryId,
            'site_id' => $site->id,
        ]);
    }

    public function test_cross_site_resource_access_returns_not_found(): void
    {
        $owner = User::factory()->create();
        [, $siteA] = $this->createPublishedProjectWithSite($owner);
        [, $siteB] = $this->createPublishedProjectWithSite($owner);

        $category = EcommerceCategory::query()->create([
            'site_id' => $siteB->id,
            'name' => 'Site B Category',
            'slug' => 'site-b-category',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.categories.update', ['site' => $siteA->id, 'category' => $category->id]), [
                'name' => 'Updated',
            ])
            ->assertNotFound();
    }

    public function test_other_tenant_user_cannot_access_ecommerce_panel_endpoints(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.ecommerce.categories.index', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.ecommerce.orders.index', ['site' => $site->id]))
            ->assertForbidden();
    }

    public function test_orders_are_site_scoped_and_updatable(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $order = EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'ORD-1001',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
            'subtotal' => '120.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '120.00',
            'paid_total' => '0.00',
            'outstanding_total' => '120.00',
            'placed_at' => now(),
        ]);

        $item = $order->items()->create([
            'site_id' => $site->id,
            'name' => 'Premium Dog Snack',
            'quantity' => 2,
            'unit_price' => '60.00',
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'line_total' => '120.00',
        ]);

        $order->payments()->create([
            'site_id' => $site->id,
            'provider' => 'manual',
            'status' => 'pending',
            'amount' => '120.00',
            'currency' => 'GEL',
            'is_installment' => false,
        ]);

        $this->assertNotNull($item->id);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.orders.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('orders.0.order_number', 'ORD-1001');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.orders.show', ['site' => $site->id, 'order' => $order->id]))
            ->assertOk()
            ->assertJsonPath('order.items.0.name', 'Premium Dog Snack');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.orders.update', ['site' => $site->id, 'order' => $order->id]), [
                'status' => 'processing',
                'payment_status' => 'paid',
                'notes' => 'Paid at pickup',
            ])
            ->assertOk()
            ->assertJsonPath('order.status', 'processing')
            ->assertJsonPath('order.payment_status', 'paid');

        $this->assertDatabaseHas('ecommerce_orders', [
            'id' => $order->id,
            'site_id' => $site->id,
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        $order->refresh();
        $this->assertNotNull($order->paid_at);
    }

    public function test_owner_can_manage_site_level_payment_provider_settings(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->activateGateway('bank-of-georgia', BankOfGeorgiaPlugin::class, [
            'sandbox' => true,
            'client_id' => 'global-client-id',
            'client_secret' => 'global-client-secret',
            'merchant_id' => 'global-merchant-id',
            'payment_token_url' => 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token',
            'sandbox_payment_base_url' => 'https://api.bog.ge',
            'payment_order_path' => '/payments/v1/ecommerce/orders',
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.payment-gateways.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('site_id', (string) $site->id)
            ->assertJsonPath('providers.0.slug', 'bank-of-georgia')
            ->assertJsonPath('providers.0.availability', 'inherit');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.payment-gateways.update', ['site' => $site->id, 'provider' => 'bank-of-georgia']), [
                'availability' => 'enabled',
                'config' => [
                    'client_id' => 'site-client-id',
                    'client_secret' => 'site-client-secret',
                    'merchant_id' => 'site-merchant-id',
                    'sandbox' => false,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('provider.slug', 'bank-of-georgia')
            ->assertJsonPath('provider.availability', 'enabled')
            ->assertJsonPath('provider.is_enabled', true)
            ->assertJsonPath('provider.mode', 'live');

        $this->assertDatabaseHas('site_payment_gateway_settings', [
            'site_id' => $site->id,
            'provider_slug' => 'bank-of-georgia',
            'availability' => 'enabled',
            'updated_by' => $owner->id,
        ]);

        $setting = SitePaymentGatewaySetting::query()
            ->where('site_id', $site->id)
            ->where('provider_slug', 'bank-of-georgia')
            ->firstOrFail();

        $this->assertSame('site-client-id', $setting->config['client_id'] ?? null);
        $this->assertSame('site-client-secret', $setting->config['client_secret'] ?? null);
        $this->assertSame('site-merchant-id', $setting->config['merchant_id'] ?? null);
        $this->assertSame(false, $setting->config['sandbox'] ?? null);
    }

    public function test_other_tenant_user_cannot_manage_site_level_payment_provider_settings(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->activateGateway('bank-of-georgia', BankOfGeorgiaPlugin::class, [
            'sandbox' => true,
            'client_id' => 'global-client-id',
            'client_secret' => 'global-client-secret',
            'merchant_id' => 'global-merchant-id',
        ]);

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.ecommerce.payment-gateways.index', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->putJson(route('panel.sites.ecommerce.payment-gateways.update', ['site' => $site->id, 'provider' => 'bank-of-georgia']), [
                'availability' => 'disabled',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_manage_site_level_shipping_provider_settings(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'City Express',
            'base_rate' => 5,
            'per_item_rate' => 1,
            'currency' => 'GEL',
            'eta_min_days' => 1,
            'eta_max_days' => 2,
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.shipping.couriers.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('site_id', (string) $site->id)
            ->assertJsonPath('couriers.0.slug', 'manual-courier')
            ->assertJsonPath('couriers.0.availability', 'inherit');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.shipping.couriers.update', ['site' => $site->id, 'courier' => 'manual-courier']), [
                'availability' => 'enabled',
                'config' => [
                    'service_name' => 'Site Courier',
                    'base_rate' => '11',
                    'per_item_rate' => '3',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('courier.slug', 'manual-courier')
            ->assertJsonPath('courier.availability', 'enabled')
            ->assertJsonPath('courier.is_enabled', true);

        $this->assertDatabaseHas('site_courier_settings', [
            'site_id' => $site->id,
            'courier_slug' => 'manual-courier',
            'availability' => 'enabled',
            'updated_by' => $owner->id,
        ]);

        $setting = SiteCourierSetting::query()
            ->where('site_id', $site->id)
            ->where('courier_slug', 'manual-courier')
            ->firstOrFail();

        $this->assertSame('Site Courier', $setting->config['service_name'] ?? null);
        $this->assertSame('11', $setting->config['base_rate'] ?? null);
        $this->assertSame('3', $setting->config['per_item_rate'] ?? null);
    }

    public function test_other_tenant_user_cannot_manage_site_level_shipping_provider_settings(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'City Express',
            'base_rate' => 5,
            'per_item_rate' => 1,
            'currency' => 'GEL',
        ]);

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.ecommerce.shipping.couriers.index', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->putJson(route('panel.sites.ecommerce.shipping.couriers.update', ['site' => $site->id, 'courier' => 'manual-courier']), [
                'availability' => 'disabled',
            ])
            ->assertForbidden();
    }

    public function test_owner_cannot_manage_shipping_providers_when_plan_disables_shipping(): void
    {
        $restrictedPlan = Plan::factory()
            ->withEcommerce(true)
            ->withShipping(false)
            ->create([
                'name' => 'No Shipping Plan',
            ]);

        $owner = User::factory()->withPlan($restrictedPlan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'City Express',
            'base_rate' => 5,
            'per_item_rate' => 1,
            'currency' => 'GEL',
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.shipping.couriers.index', ['site' => $site->id]))
            ->assertForbidden()
            ->assertJsonPath('code', 'site_entitlement_required')
            ->assertJsonPath('feature', 'shipping');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.shipping.couriers.update', ['site' => $site->id, 'courier' => 'manual-courier']), [
                'availability' => 'enabled',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'site_entitlement_required')
            ->assertJsonPath('feature', 'shipping');
    }

    public function test_panel_shipping_management_respects_plan_allowed_courier_providers(): void
    {
        $restrictedPlan = Plan::factory()
            ->withEcommerce(true)
            ->withShipping(true)
            ->withAllowedCourierProviders(['fleet-courier'])
            ->create([
                'name' => 'Fleet Courier Only',
            ]);

        $owner = User::factory()->withPlan($restrictedPlan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'City Express',
            'base_rate' => 5,
            'per_item_rate' => 1,
            'currency' => 'GEL',
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.shipping.couriers.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonCount(0, 'couriers');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.shipping.couriers.update', ['site' => $site->id, 'courier' => 'manual-courier']), [
                'availability' => 'enabled',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'site_entitlement_required')
            ->assertJsonPath('reason', 'courier_provider_not_allowed');
    }

    public function test_owner_can_create_refresh_and_cancel_shipments_for_order(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'City Express',
            'base_rate' => 5,
            'per_item_rate' => 1,
            'currency' => 'GEL',
            'tracking_base_url' => 'https://tracking.example.com/items',
        ]);

        $order = EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'ORD-SHIP-1001',
            'status' => 'processing',
            'payment_status' => 'paid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
            'subtotal' => '100.00',
            'tax_total' => '0.00',
            'shipping_total' => '5.00',
            'discount_total' => '0.00',
            'grand_total' => '105.00',
            'paid_total' => '105.00',
            'outstanding_total' => '0.00',
            'placed_at' => now(),
        ]);

        $createResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.shipments.store', ['site' => $site->id, 'order' => $order->id]), [
                'provider_slug' => 'manual-courier',
            ])
            ->assertCreated()
            ->assertJsonPath('shipment.provider_slug', 'manual-courier')
            ->assertJsonPath('shipment.status', 'created');

        $shipmentId = (int) $createResponse->json('shipment.id');
        $this->assertGreaterThan(0, $shipmentId);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.shipments.refresh', [
                'site' => $site->id,
                'order' => $order->id,
                'shipment' => $shipmentId,
            ]), [
                'status_override' => 'in_transit',
            ])
            ->assertOk()
            ->assertJsonPath('shipment.status', 'in_transit');

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.shipments.cancel', [
                'site' => $site->id,
                'order' => $order->id,
                'shipment' => $shipmentId,
            ]), [
                'reason' => 'Customer changed address',
            ])
            ->assertOk()
            ->assertJsonPath('shipment.status', 'cancelled');

        $this->assertDatabaseHas('ecommerce_shipments', [
            'id' => $shipmentId,
            'site_id' => $site->id,
            'order_id' => $order->id,
            'status' => 'cancelled',
            'provider_slug' => 'manual-courier',
        ]);

        $eventsCount = EcommerceShipment::query()
            ->where('id', $shipmentId)
            ->firstOrFail()
            ->events()
            ->count();
        $this->assertGreaterThanOrEqual(3, $eventsCount);
    }

    public function test_other_tenant_user_cannot_manage_order_shipments(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->activateCourier('manual-courier', ManualCourierPlugin::class, [
            'service_name' => 'City Express',
            'base_rate' => 5,
            'per_item_rate' => 1,
            'currency' => 'GEL',
        ]);

        $order = EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'ORD-SHIP-2001',
            'status' => 'processing',
            'payment_status' => 'paid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
            'subtotal' => '50.00',
            'tax_total' => '0.00',
            'shipping_total' => '5.00',
            'discount_total' => '0.00',
            'grand_total' => '55.00',
            'paid_total' => '55.00',
            'outstanding_total' => '0.00',
            'placed_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->postJson(route('panel.sites.ecommerce.orders.shipments.store', ['site' => $site->id, 'order' => $order->id]), [
                'provider_slug' => 'manual-courier',
            ])
            ->assertForbidden();
    }

    private function createPublishedProjectWithSite(User $owner): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

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
