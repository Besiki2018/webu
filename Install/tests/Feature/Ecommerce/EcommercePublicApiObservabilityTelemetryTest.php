<?php

namespace Tests\Feature\Ecommerce;

use App\Models\CmsTelemetryEvent;
use App\Models\EcommerceProduct;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommercePublicApiObservabilityTelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_public_ecommerce_api_requests_emit_latency_telemetry_with_checkout_trace_metadata(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Observability Telemetry Product',
            'slug' => 'observability-telemetry-product',
            'sku' => 'OBS-API-1',
            'price' => '30.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 10,
            'published_at' => now(),
        ]);

        $this->getJson(route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertOk();

        /** @var CmsTelemetryEvent $productsEvent */
        $productsEvent = CmsTelemetryEvent::query()
            ->where('site_id', (string) $site->id)
            ->where('source', 'api')
            ->where('event_name', 'cms_api.request_completed')
            ->where('route_path', '/public/sites/'.$site->id.'/ecommerce/products')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('public_api', $productsEvent->channel);
        $this->assertSame('api', $productsEvent->source);
        $this->assertSame('public.sites.ecommerce.products.index', data_get($productsEvent->meta_json, 'route_name'));
        $this->assertIsInt(data_get($productsEvent->meta_json, 'duration_ms'));
        $this->assertGreaterThanOrEqual(0, (int) data_get($productsEvent->meta_json, 'duration_ms'));

        $createCart = $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'obs@example.com',
            'customer_name' => 'Obs Buyer',
        ])->assertCreated();

        $cartId = (string) $createCart->json('cart.id');
        $this->assertNotSame('', $cartId);

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $traceId = 'trace-checkout-observability-1';
        $this->withHeaders([
            'X-Webu-Trace-Id' => $traceId,
        ])->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'obs@example.com',
            'customer_name' => 'Obs Buyer',
        ])->assertCreated();

        /** @var CmsTelemetryEvent $checkoutEvent */
        $checkoutEvent = CmsTelemetryEvent::query()
            ->where('site_id', (string) $site->id)
            ->where('source', 'api')
            ->where('event_name', 'cms_api.request_completed')
            ->where('route_path', '/public/sites/'.$site->id.'/ecommerce/carts/'.$cartId.'/checkout')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('public.sites.ecommerce.carts.checkout', data_get($checkoutEvent->meta_json, 'route_name'));
        $this->assertSame('checkout', data_get($checkoutEvent->meta_json, 'flow'));
        $this->assertSame($traceId, data_get($checkoutEvent->meta_json, 'trace_id'));
        $this->assertSame(201, (int) data_get($checkoutEvent->meta_json, 'status_code'));
        $this->assertSame('ok', data_get($checkoutEvent->meta_json, 'outcome'));
        $this->assertIsInt(data_get($checkoutEvent->meta_json, 'duration_ms'));
        $this->assertGreaterThanOrEqual(0, (int) data_get($checkoutEvent->meta_json, 'duration_ms'));
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
}

