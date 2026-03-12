<?php

namespace Tests\Feature\Cms;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsPublicVerticalModulesEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_public_blog_portfolio_properties_restaurant_and_hotel_endpoints_return_site_scoped_data(): void
    {
        [$site] = $this->createPublishedSite();

        $blogPostId = DB::table('blog_posts')->insertGetId([
            'site_id' => $site->id,
            'title' => 'Launch Notes',
            'slug' => 'launch-notes',
            'excerpt' => 'Public launch post',
            'content' => '<p>hello</p>',
            'status' => 'published',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = DB::table('post_categories')->insertGetId([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'name' => 'News',
            'slug' => 'news',
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('post_category_relations')->insert([
            'post_id' => $blogPostId,
            'category_id' => $categoryId,
        ]);

        $updatesCategoryId = DB::table('post_categories')->insertGetId([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'name' => 'Updates',
            'slug' => 'updates',
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondBlogPostId = DB::table('blog_posts')->insertGetId([
            'site_id' => $site->id,
            'title' => 'Release Wrap Up',
            'slug' => 'release-wrap-up',
            'excerpt' => 'Older published post',
            'content' => '<p>wrap up</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('post_category_relations')->insert([
            'post_id' => $secondBlogPostId,
            'category_id' => $updatesCategoryId,
        ]);

        DB::table('media')->insert([
            [
                'id' => 101,
                'site_id' => $site->id,
                'path' => 'portfolio/brand-redesign-1.jpg',
                'mime' => 'image/jpeg',
                'size' => 1024,
                'meta_json' => json_encode(['w' => 1200, 'h' => 800]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 102,
                'site_id' => $site->id,
                'path' => 'portfolio/brand-redesign-2.jpg',
                'mime' => 'image/jpeg',
                'size' => 2048,
                'meta_json' => json_encode(['w' => 1200, 'h' => 800]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $portfolioItemId = DB::table('portfolio_items')->insertGetId([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'title' => 'Brand Redesign',
            'slug' => 'brand-redesign',
            'excerpt' => 'Portfolio item',
            'content_html' => '<p>case study</p>',
            'cover_media_id' => null,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('portfolio_images')->insert([
            [
                'portfolio_item_id' => $portfolioItemId,
                'media_id' => 101,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'portfolio_item_id' => $portfolioItemId,
                'media_id' => 102,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('properties')->insert([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'title' => 'Downtown Loft',
            'slug' => 'downtown-loft',
            'price' => '250000.00',
            'currency' => 'USD',
            'location_text' => 'Downtown',
            'lat' => '41.7151',
            'lng' => '44.8271',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'area_m2' => '82.50',
            'description_html' => '<p>Great view</p>',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('properties')->insert([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'title' => 'Suburban House',
            'slug' => 'suburban-house',
            'price' => '180000.00',
            'currency' => 'USD',
            'location_text' => 'Suburbs',
            'lat' => '41.8000',
            'lng' => '44.9000',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'area_m2' => '120.00',
            'description_html' => '<p>Family home</p>',
            'status' => 'published',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $restaurantCategoryId = DB::table('restaurant_menu_categories')->insertGetId([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'name' => 'Main Dishes',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('restaurant_menu_items')->insert([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'category_id' => $restaurantCategoryId,
            'name' => 'Khachapuri',
            'description' => 'Cheese bread',
            'price' => '12.50',
            'currency' => 'GEL',
            'media_id' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dessertsCategoryId = DB::table('restaurant_menu_categories')->insertGetId([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'name' => 'Desserts',
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('restaurant_menu_items')->insert([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'category_id' => $dessertsCategoryId,
            'name' => 'Churchkhela',
            'description' => 'Walnut candy',
            'price' => '4.50',
            'currency' => 'GEL',
            'media_id' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomId = DB::table('rooms')->insertGetId([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'name' => 'Deluxe Room',
            'room_type' => 'deluxe',
            'capacity' => 3,
            'price_per_night' => '190.00',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('rooms')->insert([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'name' => 'Executive Suite',
            'room_type' => 'suite',
            'capacity' => 5,
            'price_per_night' => '320.00',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(route('public.sites.posts.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('items.0.slug', 'launch-notes');

        $this->getJson(route('public.sites.posts.index', ['site' => $site->id, 'per_page' => 1, 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('items.0.slug', 'release-wrap-up');

        $this->getJson(route('public.sites.posts.index', ['site' => $site->id, 'category' => 'updates']))
            ->assertOk()
            ->assertJsonPath('meta.category', 'updates')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('items.0.slug', 'release-wrap-up');

        $this->getJson(route('public.sites.posts.index', ['site' => $site->id, 'q' => 'launch']))
            ->assertOk()
            ->assertJsonPath('meta.query', 'launch')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('items.0.slug', 'launch-notes');

        $this->getJson(route('public.sites.posts.show', ['site' => $site->id, 'slug' => 'launch-notes']))
            ->assertOk()
            ->assertJsonPath('post.slug', 'launch-notes')
            ->assertJsonPath('post.categories.0.slug', 'news');

        $this->getJson(route('public.sites.post-categories.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('items.0.slug', 'news')
            ->assertJsonPath('items.0.posts_count', 1)
            ->assertJsonPath('items.1.slug', 'updates')
            ->assertJsonPath('items.1.posts_count', 1);

        $this->getJson(route('public.sites.portfolio.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('items.0.slug', 'brand-redesign');

        $this->getJson(route('public.sites.portfolio.show', ['site' => $site->id, 'slug' => 'brand-redesign']))
            ->assertOk()
            ->assertJsonPath('item.slug', 'brand-redesign')
            ->assertJsonPath('item.images.0.media_id', 101)
            ->assertJsonPath('item.images.1.media_id', 102);

        $this->getJson(route('public.sites.properties.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('items.0.slug', 'downtown-loft')
            ->assertJsonPath('items.0.lat', 41.7151)
            ->assertJsonPath('items.0.lng', 44.8271);

        $this->getJson(route('public.sites.properties.index', ['site' => $site->id, 'min_price' => 200000]))
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('items.0.slug', 'downtown-loft');

        $this->getJson(route('public.sites.properties.index', ['site' => $site->id, 'q' => 'suburb']))
            ->assertOk()
            ->assertJsonPath('meta.query', 'suburb')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('items.0.slug', 'suburban-house')
            ->assertJsonPath('items.0.lat', 41.8)
            ->assertJsonPath('items.0.lng', 44.9);

        $this->getJson(route('public.sites.properties.show', ['site' => $site->id, 'slug' => 'downtown-loft']))
            ->assertOk()
            ->assertJsonPath('property.slug', 'downtown-loft')
            ->assertJsonPath('property.lat', 41.7151)
            ->assertJsonPath('property.lng', 44.8271);

        $this->getJson(route('public.sites.restaurant.menu', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('categories.0.name', 'Main Dishes');

        $this->getJson(route('public.sites.restaurant.menu-items', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Khachapuri')
            ->assertJsonPath('items.0.category_name', 'Main Dishes');

        $this->getJson(route('public.sites.restaurant.menu-items', ['site' => $site->id, 'category' => 'desserts']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'Churchkhela')
            ->assertJsonPath('items.0.category_name', 'Desserts');

        $this->getJson(route('public.sites.restaurant.menu-items', ['site' => $site->id, 'category_id' => $restaurantCategoryId]))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'Khachapuri')
            ->assertJsonPath('items.0.category_id', $restaurantCategoryId);

        $this->getJson(route('public.sites.rooms.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('rooms.0.id', $roomId)
            ->assertJsonPath('rooms.0.room_type', 'deluxe');

        $this->getJson(route('public.sites.rooms.index', ['site' => $site->id, 'q' => 'suite']))
            ->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.room_type', 'suite')
            ->assertJsonPath('rooms.0.name', 'Executive Suite');

        $this->getJson(route('public.sites.rooms.index', ['site' => $site->id, 'capacity' => 4]))
            ->assertOk()
            ->assertJsonCount(1, 'rooms')
            ->assertJsonPath('rooms.0.room_type', 'suite')
            ->assertJsonPath('rooms.0.capacity', 5);

        $this->getJson(route('public.sites.rooms.show', ['site' => $site->id, 'id' => $roomId]))
            ->assertOk()
            ->assertJsonPath('room.id', $roomId)
            ->assertJsonPath('room.currency', 'USD');
    }

    public function test_public_restaurant_and_room_reservations_endpoints_create_pending_rows(): void
    {
        [$site] = $this->createPublishedSite();

        $roomId = DB::table('rooms')->insertGetId([
            'tenant_id' => $site->project->tenant_id,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'name' => 'Suite',
            'room_type' => 'suite',
            'capacity' => 4,
            'price_per_night' => '220.00',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson(route('public.sites.restaurant.reservations.store', ['site' => $site->id]), [
            'customer_name' => '',
            'phone' => '',
            'guests' => 0,
            'starts_at' => '',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['customer_name', 'phone', 'guests', 'starts_at']);

        $restaurantResponse = $this->postJson(route('public.sites.restaurant.reservations.store', ['site' => $site->id]), [
            'customer_name' => 'Nino',
            'phone' => '+995555000111',
            'email' => 'nino@example.com',
            'guests' => 3,
            'starts_at' => '2026-04-20 19:30:00',
            'notes' => 'Window seat',
        ])->assertCreated()
            ->assertJsonPath('reservation.status', 'pending')
            ->assertJsonPath('reservation.customer_name', 'Nino');

        $restaurantReservationId = (int) $restaurantResponse->json('reservation.id');
        $this->assertGreaterThan(0, $restaurantReservationId);

        $this->assertDatabaseHas('table_reservations', [
            'id' => $restaurantReservationId,
            'site_id' => $site->id,
            'customer_name' => 'Nino',
            'status' => 'pending',
            'guests' => 3,
        ]);

        $roomResponse = $this->postJson(route('public.sites.room-reservations.store', ['site' => $site->id]), [
            'room_id' => $roomId,
            'checkin_date' => '2026-04-22',
            'checkout_date' => '2026-04-25',
            'guest_name' => 'Dato',
            'guest_email' => 'dato@example.com',
            'guest_phone' => '+995555222333',
        ])->assertCreated()
            ->assertJsonPath('reservation.room_id', $roomId)
            ->assertJsonPath('reservation.status', 'pending')
            ->assertJsonPath('reservation.nights', 3)
            ->assertJsonPath('reservation.total_price', '660.00');

        $this->postJson(route('public.sites.room-reservations.store', ['site' => $site->id]), [
            'room_id' => $roomId,
            'checkin_date' => '2026-04-25',
            'checkout_date' => '2026-04-24',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'checkout_date must be after checkin_date.');

        $roomReservationId = (int) $roomResponse->json('reservation.id');
        $this->assertGreaterThan(0, $roomReservationId);

        $this->assertDatabaseHas('room_reservations', [
            'id' => $roomReservationId,
            'site_id' => $site->id,
            'room_id' => $roomId,
            'status' => 'pending',
            'total_price' => '660.00',
            'currency' => 'USD',
        ]);
    }

    /**
     * @return array{0: \App\Models\Site, 1: \App\Models\User}
     */
    private function createPublishedSite(): array
    {
        $owner = User::factory()->create();
        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant '.Str::upper(Str::random(4)),
            'slug' => 'tenant-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'created_by_user_id' => $owner->id,
        ]);
        $project = Project::factory()
            ->for($owner)
            ->published(Str::lower(Str::random(10)))
            ->create([
                'tenant_id' => $tenant->id,
                'published_visibility' => 'public',
            ]);

        $site = $project->site()->firstOrFail()->load('project');

        return [$site, $owner];
    }
}
