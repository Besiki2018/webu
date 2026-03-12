<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\EcommerceCategory;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_cross_tenant_panel_access_is_blocked_for_ecommerce_and_booking(): void
    {
        [$ownerA, $siteA] = $this->createTenant(['ecommerce' => true, 'booking' => true]);
        [, $siteB] = $this->createTenant(['ecommerce' => true, 'booking' => true]);

        $this->actingAs($ownerA)
            ->getJson(route('panel.sites.ecommerce.categories.index', ['site' => $siteB->id]))
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->getJson(route('panel.sites.booking.bookings.index', ['site' => $siteB->id]))
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->getJson(route('panel.sites.ecommerce.categories.index', ['site' => $siteA->id]))
            ->assertOk();
    }

    public function test_cross_site_resource_binding_is_not_leaked_within_same_owner_scope(): void
    {
        [$owner] = $this->createTenant(['ecommerce' => true, 'booking' => true]);
        [, $siteA] = $this->createTenant(['ecommerce' => true, 'booking' => true], $owner);
        [, $siteB] = $this->createTenant(['ecommerce' => true, 'booking' => true], $owner);

        $categoryB = EcommerceCategory::query()->create([
            'site_id' => $siteB->id,
            'name' => 'Site B Category',
            'slug' => 'site-b-category',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.ecommerce.categories.update', [
                'site' => $siteA->id,
                'category' => $categoryB->id,
            ]), [
                'name' => 'Updated Name',
            ])
            ->assertNotFound();

        $serviceB = BookingService::query()->create([
            'site_id' => $siteB->id,
            'name' => 'Site B Service',
            'slug' => 'site-b-service',
            'status' => 'active',
            'duration_minutes' => 60,
            'slot_interval_minutes' => 30,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'parallel_bookings_limit' => 1,
            'price' => 20,
            'currency' => 'GEL',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $bookingB = Booking::query()->create([
            'site_id' => $siteB->id,
            'service_id' => $serviceB->id,
            'booking_number' => 'BKG-LEAK-001',
            'status' => Booking::STATUS_PENDING,
            'source' => 'panel',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'collision_starts_at' => now()->addDay(),
            'collision_ends_at' => now()->addDay()->addHour(),
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'UTC',
            'service_fee' => 20,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 20,
            'paid_total' => 0,
            'outstanding_total' => 20,
            'currency' => 'GEL',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.bookings.show', [
                'site' => $siteA->id,
                'booking' => $bookingB->id,
            ]))
            ->assertNotFound();
    }

    /**
     * @param  array<string, bool>  $modules
     * @return array{0: User, 1: Site}
     */
    private function createTenant(array $modules, ?User $owner = null): array
    {
        if ($owner === null) {
            $plan = Plan::factory()
                ->withEcommerce((bool) ($modules['ecommerce'] ?? true))
                ->withBooking((bool) ($modules['booking'] ?? true))
                ->create();

            $owner = User::factory()->create([
                'plan_id' => $plan->id,
            ]);
        }

        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $moduleSettings = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        foreach ($modules as $key => $enabled) {
            $moduleSettings[(string) $key] = (bool) $enabled;
        }
        $settings['modules'] = $moduleSettings;
        $site->update(['theme_settings' => $settings]);

        return [$owner, $site];
    }
}
