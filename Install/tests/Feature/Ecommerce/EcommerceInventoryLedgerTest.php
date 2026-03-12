<?php

namespace Tests\Feature\Ecommerce;

use App\Models\EcommerceInventoryReservation;
use App\Models\EcommerceProduct;
use App\Models\EcommerceStockMovement;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommerceInventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_cart_reservations_prevent_oversell_across_carts(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Inventory Guard Product',
            'slug' => 'inventory-guard-product',
            'sku' => 'INV-GUARD-1',
            'price' => '20.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 3,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartAId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer-a@example.com',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartAId]), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->assertDatabaseHas('ecommerce_inventory_items', [
            'site_id' => $site->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 2,
        ]);

        $cartBId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer-b@example.com',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartBId]), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Requested quantity exceeds available stock.');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartBId]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('ecommerce_inventory_items', [
            'site_id' => $site->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 3,
        ]);

        $reservationsCount = EcommerceInventoryReservation::query()
            ->where('site_id', $site->id)
            ->where('product_id', $product->id)
            ->count();
        $this->assertSame(2, $reservationsCount);

        $reserveMovements = EcommerceStockMovement::query()
            ->where('site_id', $site->id)
            ->where('product_id', $product->id)
            ->where('movement_type', EcommerceStockMovement::TYPE_RESERVE)
            ->count();
        $this->assertSame(2, $reserveMovements);
    }

    public function test_checkout_commits_reservations_into_stock_ledger_and_updates_on_hand(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Checkout Commit Product',
            'slug' => 'checkout-commit-product',
            'sku' => 'INV-COMMIT-1',
            'price' => '35.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 5,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartResponse = $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
        ])->assertCreated();
        $cartId = (string) $cartResponse->json('cart.id');

        $addResponse = $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $cartItemId = (int) $addResponse->json('cart.items.0.id');
        $this->assertGreaterThan(0, $cartItemId);

        $this->assertDatabaseHas('ecommerce_inventory_reservations', [
            'site_id' => $site->id,
            'cart_id' => $cartId,
            'cart_item_id' => $cartItemId,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
            'shipping_address_json' => [
                'city' => 'Tbilisi',
                'address' => 'Rustaveli 1',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('order.grand_total', '70.00');

        $product->refresh();
        $this->assertSame(3, (int) $product->stock_quantity);

        $this->assertDatabaseHas('ecommerce_inventory_items', [
            'site_id' => $site->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
        ]);

        $this->assertDatabaseMissing('ecommerce_inventory_reservations', [
            'site_id' => $site->id,
            'cart_item_id' => $cartItemId,
        ]);

        $this->assertDatabaseHas('ecommerce_stock_movements', [
            'site_id' => $site->id,
            'product_id' => $product->id,
            'movement_type' => EcommerceStockMovement::TYPE_COMMIT,
            'reason' => 'checkout_commit',
            'quantity_delta' => -2,
            'reserved_delta' => -2,
            'quantity_on_hand_before' => 5,
            'quantity_on_hand_after' => 3,
            'quantity_reserved_before' => 2,
            'quantity_reserved_after' => 0,
        ]);
    }

    public function test_panel_stock_update_rejects_quantity_below_reserved_amount(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Reserved Stock Product',
            'slug' => 'reserved-stock-product',
            'sku' => 'INV-RES-1',
            'price' => '40.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 5,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 4,
        ])->assertOk();

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.products.update', ['site' => $site->id, 'product' => $product->id]), [
                'stock_quantity' => 2,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Stock quantity cannot be lower than currently reserved quantity.');

        $product->refresh();
        $this->assertSame(5, (int) $product->stock_quantity);

        $this->assertDatabaseHas('ecommerce_inventory_items', [
            'site_id' => $site->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 4,
        ]);
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
