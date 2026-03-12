<?php

namespace Tests\Feature\Security;

use App\Models\EcommerceProduct;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RateLimitProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_login_route_is_throttled_under_burst_attempts(): void
    {
        User::factory()->create([
            'email' => 'ratelimit@example.com',
            'password' => bcrypt('secret-password'),
        ]);

        $lastResponse = null;
        for ($attempt = 1; $attempt <= 14; $attempt++) {
            $lastResponse = $this->from('/login')->post('/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'invalid-password',
            ]);
        }

        $this->assertNotNull($lastResponse);
        $lastResponse->assertStatus(429);
    }

    public function test_public_checkout_endpoint_is_throttled(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, [
            'ecommerce' => true,
        ]);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Throttle Product',
            'slug' => 'throttle-product',
            'sku' => 'THR-001',
            'price' => '5.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 30,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
        ])->assertCreated()->json('cart.id');

        $lastResponse = null;
        for ($attempt = 1; $attempt <= 12; $attempt++) {
            $lastResponse = $this->postJson(route('public.sites.ecommerce.carts.checkout', [
                'site' => $site->id,
                'cart' => $cartId,
            ]), [
                'customer_email' => 'buyer@example.com',
                'customer_name' => 'Buyer',
            ]);
        }

        $this->assertNotNull($lastResponse);
        $lastResponse->assertStatus(429);
    }

    public function test_public_booking_create_endpoint_is_throttled(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, [
            'booking' => true,
        ]);

        $lastResponse = null;
        for ($attempt = 1; $attempt <= 14; $attempt++) {
            $lastResponse = $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
                'service_id' => 999999,
                'starts_at' => now()->addDay()->toISOString(),
                'customer_email' => 'guest@example.com',
            ]);
        }

        $this->assertNotNull($lastResponse);
        $lastResponse->assertStatus(429);
    }

    /**
     * @param  array<string, bool>  $modules
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(User $owner, array $modules = []): array
    {
        $plan = Plan::factory()
            ->withEcommerce((bool) ($modules['ecommerce'] ?? true))
            ->withBooking((bool) ($modules['booking'] ?? true))
            ->create([
                'enable_custom_domains' => true,
                'enable_subdomains' => true,
            ]);

        $owner->forceFill([
            'plan_id' => $plan->id,
        ])->save();

        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create([
                'published_visibility' => 'public',
            ]);

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $moduleSettings = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];

        foreach ($modules as $moduleKey => $enabled) {
            $moduleSettings[(string) $moduleKey] = (bool) $enabled;
        }

        $settings['modules'] = $moduleSettings;
        $site->update([
            'theme_settings' => $settings,
        ]);

        return [$project, $site];
    }
}
