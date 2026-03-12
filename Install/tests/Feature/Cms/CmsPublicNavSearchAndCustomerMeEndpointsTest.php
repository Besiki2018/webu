<?php

namespace Tests\Feature\Cms;

use App\Models\BlogPost;
use App\Models\EcommerceProduct;
use App\Models\Page;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsPublicNavSearchAndCustomerMeEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_public_nav_search_endpoint_returns_mode_scoped_results_for_site_products_and_posts(): void
    {
        [$site] = $this->createPublishedSite();

        Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Pricing',
            'slug' => 'pricing',
            'status' => 'published',
            'seo_title' => 'Pricing Plans',
            'seo_description' => 'Compare pricing tiers',
        ]);

        BlogPost::query()->create([
            'site_id' => $site->id,
            'title' => 'Pricing Tips',
            'slug' => 'pricing-tips',
            'excerpt' => 'How to choose the right plan',
            'content' => 'Pricing guide for teams',
            'status' => 'published',
            'published_at' => now(),
        ]);

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Pricing Keyboard',
            'slug' => 'pricing-keyboard',
            'short_description' => 'Keyboard for pricing team',
            'description' => 'Mechanical keyboard',
            'price' => '199.00',
            'currency' => 'GEL',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $baseUrl = route('public.sites.search', ['site' => $site->id]);

        $this->getJson($baseUrl.'?q=pricing&mode=site')
            ->assertOk()
            ->assertJsonPath('mode', 'site')
            ->assertJsonPath('items.0.type', 'page')
            ->assertJsonPath('items.0.slug', 'pricing');

        $this->getJson($baseUrl.'?q=pricing&mode=products')
            ->assertOk()
            ->assertJsonPath('mode', 'products')
            ->assertJsonPath('items.0.type', 'product')
            ->assertJsonPath('items.0.slug', 'pricing-keyboard')
            ->assertJsonPath('items.0.currency', 'GEL');

        $this->getJson($baseUrl.'?q=pricing&mode=posts')
            ->assertOk()
            ->assertJsonPath('mode', 'posts')
            ->assertJsonPath('items.0.type', 'post')
            ->assertJsonPath('items.0.slug', 'pricing-tips');
    }

    public function test_public_customer_me_endpoint_returns_guest_and_authenticated_session_payload(): void
    {
        [$site] = $this->createPublishedSite();

        $url = route('public.sites.customers.me', ['site' => $site->id]);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('customer', null)
            ->assertJsonPath('links.login', '/login')
            ->assertHeader('Cache-Control', 'no-store, private');

        $customer = User::factory()->create([
            'name' => 'Storefront Customer',
            'email' => 'customer+'.Str::lower(Str::random(6)).'@example.test',
        ]);

        $this->actingAs($customer)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.name', $customer->name)
            ->assertJsonPath('customer.email', $customer->email)
            ->assertJsonPath('links.account', '/account')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    /**
     * @return array{0: \App\Models\Site, 1: \App\Models\User}
     */
    private function createPublishedSite(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published(Str::lower(Str::random(10)))
            ->create([
                'published_visibility' => 'public',
            ]);

        $site = $project->site()->firstOrFail();

        return [$site, $owner];
    }
}

