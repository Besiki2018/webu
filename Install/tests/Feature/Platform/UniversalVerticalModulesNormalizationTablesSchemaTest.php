<?php

namespace Tests\Feature\Platform;

use App\Models\Project;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UniversalVerticalModulesNormalizationTablesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_vertical_module_normalization_tables_exist_with_canonical_columns(): void
    {
        foreach ([
            'rooms',
            'room_images',
            'room_reservations',
            'restaurant_menu_categories',
            'restaurant_menu_items',
            'table_reservations',
            'portfolio_items',
            'portfolio_images',
            'properties',
            'property_images',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        $this->assertTrue(Schema::hasColumns('rooms', ['tenant_id', 'project_id', 'name', 'room_type', 'capacity', 'price_per_night', 'currency', 'status']));
        $this->assertTrue(Schema::hasColumns('room_images', ['room_id', 'media_id', 'sort_order']));
        $this->assertTrue(Schema::hasColumns('room_reservations', ['tenant_id', 'project_id', 'customer_id', 'room_id', 'checkin_date', 'checkout_date', 'status', 'total_price', 'currency']));
        $this->assertTrue(Schema::hasColumns('restaurant_menu_categories', ['tenant_id', 'project_id', 'name', 'sort_order']));
        $this->assertTrue(Schema::hasColumns('restaurant_menu_items', ['category_id', 'name', 'description', 'price', 'currency', 'media_id', 'status']));
        $this->assertTrue(Schema::hasColumns('table_reservations', ['tenant_id', 'project_id', 'customer_name', 'phone', 'guests', 'starts_at', 'status', 'notes']));
        $this->assertTrue(Schema::hasColumns('portfolio_items', ['tenant_id', 'project_id', 'title', 'slug', 'excerpt', 'content_html', 'cover_media_id', 'status']));
        $this->assertTrue(Schema::hasColumns('portfolio_images', ['portfolio_item_id', 'media_id', 'sort_order']));
        $this->assertTrue(Schema::hasColumns('properties', ['tenant_id', 'project_id', 'title', 'slug', 'price', 'currency', 'location_text', 'lat', 'lng', 'bedrooms', 'bathrooms', 'area_m2', 'description_html', 'status']));
        $this->assertTrue(Schema::hasColumns('property_images', ['property_id', 'media_id', 'sort_order']));
    }

    public function test_vertical_module_normalization_tables_support_relational_insert_flow(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::query()->create([
            'name' => 'Vertical Norm Tenant',
            'slug' => 'vertical-norm-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'created_by_user_id' => $owner->id,
        ]);

        $project = Project::factory()->for($owner)->create([
            'tenant_id' => (string) $tenant->id,
            'type' => 'vertical-demo',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $site = Site::query()->where('project_id', (string) $project->id)->first();
        $this->assertNotNull($site);

        $mediaId = DB::table('media')->insertGetId([
            'site_id' => (string) $site->id,
            'path' => 'demo/vertical/asset-1.jpg',
            'mime' => 'image/jpeg',
            'size' => 12345,
            'meta_json' => json_encode(['w' => 1200, 'h' => 800]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Guest Demo',
            'email' => 'guest-demo@example.test',
            'phone' => '+995555000000',
            'password_hash' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomId = DB::table('rooms')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'name' => 'Deluxe Room',
            'room_type' => 'deluxe',
            'capacity' => 2,
            'price_per_night' => 180,
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('room_images')->insert([
            'room_id' => $roomId,
            'media_id' => $mediaId,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomReservationId = DB::table('room_reservations')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'customer_id' => $customerId,
            'room_id' => $roomId,
            'checkin_date' => now()->addDays(7)->toDateString(),
            'checkout_date' => now()->addDays(10)->toDateString(),
            'status' => 'confirmed',
            'total_price' => 540,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $restaurantCategoryId = DB::table('restaurant_menu_categories')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'name' => 'Main Dishes',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $restaurantItemId = DB::table('restaurant_menu_items')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'category_id' => $restaurantCategoryId,
            'name' => 'Grilled Salmon',
            'description' => 'With seasonal vegetables',
            'price' => 24.50,
            'currency' => 'USD',
            'media_id' => $mediaId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tableReservationId = DB::table('table_reservations')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'customer_name' => 'Nino Demo',
            'phone' => '+995555111222',
            'guests' => 4,
            'starts_at' => now()->addDays(2)->setTime(19, 30),
            'status' => 'confirmed',
            'notes' => 'Window seat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $portfolioItemId = DB::table('portfolio_items')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'title' => 'Brand Redesign',
            'slug' => 'brand-redesign',
            'excerpt' => 'Case study excerpt',
            'content_html' => '<p>Portfolio content</p>',
            'cover_media_id' => $mediaId,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('portfolio_images')->insert([
            'portfolio_item_id' => $portfolioItemId,
            'media_id' => $mediaId,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $propertyId = DB::table('properties')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'title' => 'Modern Loft',
            'slug' => 'modern-loft',
            'price' => 250000,
            'currency' => 'USD',
            'location_text' => 'Tbilisi, Vake',
            'lat' => 41.7151000,
            'lng' => 44.8271000,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'area_m2' => 85.5,
            'description_html' => '<p>Bright property</p>',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('property_images')->insert([
            'property_id' => $propertyId,
            'media_id' => $mediaId,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('rooms', ['id' => $roomId, 'room_type' => 'deluxe']);
        $this->assertDatabaseHas('room_images', ['room_id' => $roomId, 'media_id' => $mediaId]);
        $this->assertDatabaseHas('room_reservations', ['id' => $roomReservationId, 'room_id' => $roomId, 'customer_id' => $customerId, 'status' => 'confirmed']);
        $this->assertDatabaseHas('restaurant_menu_categories', ['id' => $restaurantCategoryId, 'name' => 'Main Dishes']);
        $this->assertDatabaseHas('restaurant_menu_items', ['id' => $restaurantItemId, 'category_id' => $restaurantCategoryId, 'status' => 'active']);
        $this->assertDatabaseHas('table_reservations', ['id' => $tableReservationId, 'guests' => 4, 'status' => 'confirmed']);
        $this->assertDatabaseHas('portfolio_items', ['id' => $portfolioItemId, 'slug' => 'brand-redesign', 'status' => 'published']);
        $this->assertDatabaseHas('portfolio_images', ['portfolio_item_id' => $portfolioItemId, 'media_id' => $mediaId]);
        $this->assertDatabaseHas('properties', ['id' => $propertyId, 'slug' => 'modern-loft', 'status' => 'published']);
        $this->assertDatabaseHas('property_images', ['property_id' => $propertyId, 'media_id' => $mediaId]);
    }
}
