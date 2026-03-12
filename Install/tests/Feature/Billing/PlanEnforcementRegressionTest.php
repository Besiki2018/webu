<?php

namespace Tests\Feature\Billing;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\EcommerceOrder;
use App\Models\EcommerceProduct;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanEnforcementRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_downgrade_blocks_disabled_modules_and_upgrade_restores_entitlements(): void
    {
        $premium = Plan::factory()
            ->withEcommerce(true)
            ->withBooking(true)
            ->create([
                'name' => 'Premium',
                'price' => 59,
                'sort_order' => 2,
            ]);

        $basic = Plan::factory()
            ->withEcommerce(false)
            ->withBooking(false)
            ->create([
                'name' => 'Basic',
                'price' => 12,
                'sort_order' => 1,
            ]);

        $owner = User::factory()->withPlan($premium)->create();
        $subscription = Subscription::factory()
            ->for($owner)
            ->for($premium)
            ->active()
            ->create([
                'amount' => 59,
            ]);

        [, $site] = $this->createPublishedProjectWithModules($owner, true, true);

        $bookingService = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Plan Regression Service',
            'slug' => 'plan-regression-service',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 30,
            'max_parallel_bookings' => 3,
            'requires_staff' => false,
            'price' => '35.00',
            'currency' => 'GEL',
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.categories.index', ['site' => $site->id]))
            ->assertOk();

        $this->getJson(route('public.sites.booking.services', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('services.0.id', $bookingService->id);

        $this->switchOwnerPlan($owner, $subscription, $basic);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.categories.index', ['site' => $site->id]))
            ->assertForbidden()
            ->assertJsonPath('code', 'site_entitlement_required')
            ->assertJsonPath('feature', 'ecommerce');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.services.index', ['site' => $site->id]))
            ->assertForbidden()
            ->assertJsonPath('code', 'site_entitlement_required')
            ->assertJsonPath('feature', 'booking');

        $this->switchOwnerPlan($owner, $subscription, $premium);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.categories.index', ['site' => $site->id]))
            ->assertOk();

        $this->getJson(route('public.sites.booking.services', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('services.0.id', $bookingService->id);
    }

    public function test_downgrade_enforces_lower_limits_and_upgrade_allows_new_activity(): void
    {
        $premium = Plan::factory()
            ->withEcommerce(true)
            ->withBooking(true)
            ->withProductLimit(10)
            ->withMonthlyOrderLimit(10)
            ->withMonthlyBookingLimit(10)
            ->create([
                'name' => 'Premium',
                'price' => 59,
                'sort_order' => 2,
            ]);

        $restricted = Plan::factory()
            ->withEcommerce(true)
            ->withBooking(true)
            ->withProductLimit(1)
            ->withMonthlyOrderLimit(1)
            ->withMonthlyBookingLimit(1)
            ->create([
                'name' => 'Restricted',
                'price' => 19,
                'sort_order' => 1,
            ]);

        $owner = User::factory()->withPlan($premium)->create();
        $subscription = Subscription::factory()
            ->for($owner)
            ->for($premium)
            ->active()
            ->create([
                'amount' => 59,
            ]);

        [, $site] = $this->createPublishedProjectWithModules($owner, true, true);

        $productA = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Existing Product A',
            'slug' => 'existing-product-a',
            'sku' => 'EX-A-001',
            'price' => '20.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 100,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Existing Product B',
            'slug' => 'existing-product-b',
            'sku' => 'EX-B-001',
            'price' => '25.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 100,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'ORD-REG-001',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'existing-order@example.com',
            'customer_name' => 'Existing Order',
            'subtotal' => '20.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '20.00',
            'paid_total' => '0.00',
            'outstanding_total' => '20.00',
            'placed_at' => now(),
        ]);

        $bookingService = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Booking Service',
            'slug' => 'booking-service',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 30,
            'max_parallel_bookings' => 5,
            'requires_staff' => false,
            'price' => '30.00',
            'currency' => 'GEL',
        ]);

        Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $bookingService->id,
            'booking_number' => 'BKG-REG-001',
            'status' => Booking::STATUS_PENDING,
            'source' => 'panel',
            'customer_email' => 'existing-booking@example.com',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'collision_starts_at' => now()->addHour(),
            'collision_ends_at' => now()->addHours(2),
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'Asia/Tbilisi',
            'service_fee' => '30.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '30.00',
            'paid_total' => '0.00',
            'outstanding_total' => '30.00',
            'currency' => 'GEL',
        ]);

        $this->switchOwnerPlan($owner, $subscription, $restricted);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.products.store', ['site' => $site->id]), [
                'name' => 'Blocked Product',
                'slug' => 'blocked-product',
                'sku' => 'BLOCK-001',
                'price' => '18.00',
                'currency' => 'GEL',
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'products_limit_reached');

        $restrictedCartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'restricted@example.com',
            'customer_name' => 'Restricted User',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $restrictedCartId]), [
            'product_id' => $productA->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $restrictedCartId]), [
            'customer_email' => 'restricted@example.com',
            'customer_name' => 'Restricted User',
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'monthly_orders_limit_reached');

        $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $bookingService->id,
            'starts_at' => now()->addDay()->setTime(10, 0)->toISOString(),
            'duration_minutes' => 30,
            'timezone' => 'Asia/Tbilisi',
            'customer_email' => 'restricted-booking@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'monthly_bookings_limit_reached');

        $this->switchOwnerPlan($owner, $subscription, $premium);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.products.store', ['site' => $site->id]), [
                'name' => 'Allowed Product',
                'slug' => 'allowed-product',
                'sku' => 'ALLOW-001',
                'price' => '18.00',
                'currency' => 'GEL',
                'status' => 'active',
            ])
            ->assertCreated();

        $upgradedCartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'upgraded@example.com',
            'customer_name' => 'Upgraded User',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $upgradedCartId]), [
            'product_id' => $productA->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $upgradedCartId]), [
            'customer_email' => 'upgraded@example.com',
            'customer_name' => 'Upgraded User',
        ])->assertCreated();

        $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $bookingService->id,
            'starts_at' => now()->addDays(2)->setTime(11, 0)->toISOString(),
            'duration_minutes' => 30,
            'timezone' => 'Asia/Tbilisi',
            'customer_email' => 'upgraded-booking@example.com',
        ])
            ->assertCreated();
    }

    private function switchOwnerPlan(User $owner, Subscription $subscription, Plan $plan): void
    {
        $subscription->update([
            'plan_id' => $plan->id,
            'amount' => $plan->price,
        ]);

        $owner->forceFill([
            'plan_id' => $plan->id,
        ])->saveQuietly();

        $owner->refresh();
        $subscription->refresh();
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithModules(User $owner, bool $enableEcommerce, bool $enableBooking): array
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
        $moduleSettings['booking'] = $enableBooking;
        $settings['modules'] = $moduleSettings;
        $site->update(['theme_settings' => $settings]);

        return [$project, $site];
    }
}
