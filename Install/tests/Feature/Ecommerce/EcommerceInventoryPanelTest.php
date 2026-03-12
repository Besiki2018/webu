<?php

namespace Tests\Feature\Ecommerce;

use App\Ecommerce\Contracts\EcommerceInventoryServiceContract;
use App\Models\EcommerceInventoryItem;
use App\Models\EcommerceProduct;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommerceInventoryPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_owner_can_manage_inventory_locations_adjustments_and_stocktake_flow(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Inventory Panel Product',
            'slug' => 'inventory-panel-product',
            'sku' => 'INV-PANEL-1',
            'price' => '99.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 10,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        app(EcommerceInventoryServiceContract::class)->syncInventorySnapshotForProduct(
            $site,
            $product,
            reason: 'inventory_panel_test_seed'
        );

        $dashboardResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.inventory.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('site_id', (string) $site->id);
        $itemsCount = (int) ($dashboardResponse->json('summary.items_count') ?? 0);
        $this->assertGreaterThanOrEqual(1, $itemsCount, 'Demo seeding may add extra inventory items');

        $inventoryItems = $dashboardResponse->json('inventory_items') ?? [];
        $this->assertNotEmpty($inventoryItems);
        $inventoryItemId = (int) $inventoryItems[0]['id'];
        $defaultLocationId = (int) $dashboardResponse->json('locations.0.id');

        $locationResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.inventory.locations.store', ['site' => $site->id]), [
                'name' => 'Secondary Warehouse',
                'key' => 'secondary-warehouse',
                'status' => 'active',
                'is_default' => true,
                'notes' => 'Overflow stock area',
            ])
            ->assertCreated()
            ->assertJsonPath('location.key', 'secondary-warehouse')
            ->assertJsonPath('location.is_default', true);

        $secondaryLocationId = (int) $locationResponse->json('location.id');
        $this->assertNotSame($defaultLocationId, $secondaryLocationId);

        $this->assertDatabaseHas('ecommerce_inventory_locations', [
            'id' => $secondaryLocationId,
            'site_id' => $site->id,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('ecommerce_inventory_locations', [
            'id' => $defaultLocationId,
            'site_id' => $site->id,
            'is_default' => false,
        ]);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.inventory.items.update', ['site' => $site->id, 'inventoryItem' => $inventoryItemId]), [
                'location_id' => $secondaryLocationId,
                'low_stock_threshold' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('inventory_item.location_id', $secondaryLocationId)
            ->assertJsonPath('inventory_item.low_stock_threshold', 3);

        $this->assertDatabaseHas('ecommerce_inventory_items', [
            'id' => $inventoryItemId,
            'site_id' => $site->id,
            'location_id' => $secondaryLocationId,
            'low_stock_threshold' => 3,
        ]);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.inventory.items.adjust', ['site' => $site->id, 'inventoryItem' => $inventoryItemId]), [
                'quantity_delta' => -4,
                'reason' => 'damaged_items',
            ])
            ->assertOk()
            ->assertJsonPath('inventory_item.quantity_on_hand', 6);

        $this->assertDatabaseHas('ecommerce_stock_movements', [
            'site_id' => $site->id,
            'inventory_item_id' => $inventoryItemId,
            'movement_type' => 'adjust',
            'reason' => 'damaged_items',
            'quantity_delta' => -4,
        ]);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.inventory.items.stocktake', ['site' => $site->id, 'inventoryItem' => $inventoryItemId]), [
                'counted_quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('inventory_item.quantity_on_hand', 2)
            ->assertJsonPath('inventory_item.is_low_stock', true);

        $this->assertDatabaseHas('ecommerce_inventory_items', [
            'id' => $inventoryItemId,
            'site_id' => $site->id,
            'quantity_on_hand' => 2,
            'low_stock_threshold' => 3,
        ]);

        $this->assertDatabaseHas('ecommerce_stock_movements', [
            'site_id' => $site->id,
            'inventory_item_id' => $inventoryItemId,
            'movement_type' => 'stocktake',
            'reason' => 'stocktake_count',
            'quantity_on_hand_after' => 2,
        ]);

        $product->refresh();
        $this->assertSame(2, (int) $product->stock_quantity);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.inventory.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('summary.low_stock_count', 1)
            ->assertJsonPath('inventory_items.0.location_id', $secondaryLocationId)
            ->assertJsonPath('inventory_items.0.available_quantity', 2);
    }

    public function test_cross_site_inventory_item_access_returns_not_found(): void
    {
        $owner = User::factory()->create();
        [, $siteA] = $this->createPublishedProjectWithSite($owner, true);
        [, $siteB] = $this->createPublishedProjectWithSite($owner, true);

        $productB = EcommerceProduct::query()->create([
            'site_id' => $siteB->id,
            'name' => 'Cross Site Product',
            'slug' => 'cross-site-product',
            'sku' => 'INV-CROSS-1',
            'price' => '20.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 7,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        app(EcommerceInventoryServiceContract::class)->syncInventorySnapshotForProduct(
            $siteB,
            $productB,
            reason: 'inventory_cross_site_seed'
        );

        $itemIdB = (int) EcommerceInventoryItem::query()
            ->where('site_id', $siteB->id)
            ->where('product_id', $productB->id)
            ->value('id');

        $this->assertGreaterThan(0, $itemIdB);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.inventory.items.update', ['site' => $siteA->id, 'inventoryItem' => $itemIdB]), [
                'low_stock_threshold' => 2,
            ])
            ->assertNotFound()
            ->assertJsonPath('error', 'Route scope mismatch detected.')
            ->assertJsonPath('code', 'tenant_scope_route_binding_mismatch');
    }

    public function test_other_tenant_user_cannot_access_inventory_panel_endpoints(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Tenant Guard Product',
            'slug' => 'tenant-guard-product',
            'sku' => 'INV-TENANT-1',
            'price' => '12.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 4,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        app(EcommerceInventoryServiceContract::class)->syncInventorySnapshotForProduct(
            $site,
            $product,
            reason: 'inventory_tenant_guard_seed'
        );

        $itemId = (int) EcommerceInventoryItem::query()
            ->where('site_id', $site->id)
            ->where('product_id', $product->id)
            ->value('id');

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.ecommerce.inventory.index', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->postJson(route('panel.sites.ecommerce.inventory.items.adjust', ['site' => $site->id, 'inventoryItem' => $itemId]), [
                'quantity_delta' => -1,
                'reason' => 'intruder',
            ])
            ->assertForbidden();
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
