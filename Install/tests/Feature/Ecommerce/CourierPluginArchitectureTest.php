<?php

namespace Tests\Feature\Ecommerce;

use App\Contracts\CourierPlugin;
use App\Models\Plugin;
use App\Models\SystemSetting;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierPluginArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_plugin_manager_discovers_manual_courier_plugin_manifest(): void
    {
        /** @var PluginManager $manager */
        $manager = app(PluginManager::class);

        $plugin = collect($manager->discover())->firstWhere('slug', 'manual-courier');

        $this->assertNotNull($plugin);
        $this->assertSame('courier', $plugin['type']);
        $this->assertSame('App\\Plugins\\Couriers\\ManualCourier\\ManualCourierPlugin', $plugin['namespace']);
    }

    public function test_plugin_manager_resolves_only_active_courier_plugins(): void
    {
        Plugin::query()->create([
            'name' => 'Manual Courier',
            'slug' => 'manual-courier',
            'type' => 'courier',
            'class' => 'App\\Plugins\\Couriers\\ManualCourier\\ManualCourierPlugin',
            'version' => '1.0.0',
            'status' => 'active',
            'config' => ['base_rate' => 5],
            'metadata' => null,
            'migrations' => null,
            'installed_at' => now(),
        ]);

        Plugin::query()->create([
            'name' => 'Disabled Courier',
            'slug' => 'disabled-courier',
            'type' => 'courier',
            'class' => 'App\\Plugins\\Couriers\\ManualCourier\\ManualCourierPlugin',
            'version' => '1.0.0',
            'status' => 'inactive',
            'config' => ['base_rate' => 9],
            'metadata' => null,
            'migrations' => null,
            'installed_at' => now(),
        ]);

        Plugin::query()->create([
            'name' => 'Some Payment',
            'slug' => 'some-payment',
            'type' => 'payment_gateway',
            'class' => 'App\\Plugins\\Couriers\\ManualCourier\\ManualCourierPlugin',
            'version' => '1.0.0',
            'status' => 'active',
            'config' => [],
            'metadata' => null,
            'migrations' => null,
            'installed_at' => now(),
        ]);

        /** @var PluginManager $manager */
        $manager = app(PluginManager::class);

        $couriers = $manager->getActiveCouriers();
        $this->assertCount(1, $couriers);
        $this->assertInstanceOf(CourierPlugin::class, $couriers->first());

        $resolved = $manager->getCourierBySlug('manual-courier');
        $this->assertInstanceOf(CourierPlugin::class, $resolved);

        $missing = $manager->getCourierBySlug('disabled-courier');
        $this->assertNull($missing);
    }

    public function test_manual_courier_base_plugin_provides_quote_and_shipment_lifecycle_contract(): void
    {
        Plugin::query()->create([
            'name' => 'Manual Courier',
            'slug' => 'manual-courier',
            'type' => 'courier',
            'class' => 'App\\Plugins\\Couriers\\ManualCourier\\ManualCourierPlugin',
            'version' => '1.0.0',
            'status' => 'active',
            'config' => [
                'base_rate' => 7,
                'per_item_rate' => 1,
                'currency' => 'GEL',
                'tracking_base_url' => 'https://tracking.example.com/items',
            ],
            'metadata' => null,
            'migrations' => null,
            'installed_at' => now(),
        ]);

        /** @var PluginManager $manager */
        $manager = app(PluginManager::class);
        $courier = $manager->getCourierBySlug('manual-courier');

        $this->assertInstanceOf(CourierPlugin::class, $courier);

        $quote = $courier->quote([
            'subtotal' => 80,
            'items' => [
                ['quantity' => 2],
                ['quantity' => 1],
            ],
            'currency' => 'GEL',
        ]);

        $this->assertSame('manual-courier', $quote['provider']);
        $this->assertSame('10.00', $quote['rates'][0]['amount']);
        $this->assertSame('GEL', $quote['rates'][0]['currency']);

        $shipment = $courier->createShipment([
            'order_id' => 1001,
            'shipment_reference' => 'MAN-REF-1001',
            'tracking_number' => 'TRK-1001',
        ]);

        $this->assertSame('manual-courier', $shipment['provider']);
        $this->assertSame('MAN-REF-1001', $shipment['shipment_reference']);
        $this->assertSame('TRK-1001', $shipment['tracking_number']);
        $this->assertSame('https://tracking.example.com/items/TRK-1001', $shipment['tracking_url']);

        $tracking = $courier->track([
            'shipment_reference' => $shipment['shipment_reference'],
            'tracking_number' => $shipment['tracking_number'],
            'status' => 'dispatched',
        ]);
        $this->assertSame('dispatched', $tracking['status']);

        $cancelled = $courier->cancelShipment([
            'shipment_reference' => $shipment['shipment_reference'],
        ]);
        $this->assertSame('cancelled', $cancelled['status']);
    }
}
