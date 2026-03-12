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

class UniversalPartialParityRowsCanonicalMigrationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_parity_rows_have_canonical_columns_and_tables_after_migration_batch(): void
    {
        $this->assertTrue(Schema::hasColumns('tenants', ['email', 'phone', 'logo_media_id']));
        $this->assertTrue(Schema::hasColumns('tenant_users', ['password_hash', 'role']));
        $this->assertTrue(Schema::hasColumns('projects', ['slug', 'primary_domain', 'subdomain', 'status']));
        $this->assertTrue(Schema::hasColumns('pages', ['tenant_id', 'project_id', 'page_json', 'page_css', 'og_image_media_id', 'published_at', 'version']));
        $this->assertTrue(Schema::hasColumns('page_revisions', ['page_json', 'page_css']));
        $this->assertTrue(Schema::hasColumns('menus', ['tenant_id', 'project_id', 'name']));
        $this->assertTrue(Schema::hasColumns('media', ['tenant_id', 'project_id', 'url', 'file_name', 'mime_type', 'width', 'height', 'alt']));

        foreach (['posts', 'project_settings', 'feature_flags', 'leads', 'product_category_relations', 'order_addresses'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        $this->assertTrue(Schema::hasColumns('posts', ['tenant_id', 'project_id', 'title', 'slug', 'status', 'excerpt', 'content_html', 'cover_media_id', 'seo_title', 'seo_description', 'published_at']));
        $this->assertTrue(Schema::hasColumns('project_settings', ['project_id', 'key', 'value_json']));
        $this->assertTrue(Schema::hasColumns('feature_flags', ['tenant_id', 'project_id', 'key', 'enabled', 'rules_json']));
        $this->assertTrue(Schema::hasColumns('leads', ['tenant_id', 'project_id', 'name', 'email', 'phone', 'message', 'status']));
        $this->assertTrue(Schema::hasColumns('product_category_relations', ['product_id', 'category_id']));
        $this->assertTrue(Schema::hasColumns('order_addresses', ['order_id', 'type', 'name', 'phone', 'country', 'city', 'address1', 'address2', 'zip']));
    }

    public function test_partial_parity_rows_support_canonical_insert_flow_without_breaking_existing_tables(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::query()->create([
            'name' => 'Parity Tenant',
            'slug' => 'parity-tenant-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'created_by_user_id' => $owner->id,
        ]);

        $project = Project::factory()->for($owner)->create([
            'tenant_id' => (string) $tenant->id,
            'type' => 'ecommerce',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $site = Site::query()->where('project_id', (string) $project->id)->first();
        $this->assertNotNull($site);

        DB::table('projects')->where('id', (string) $project->id)->update([
            'slug' => 'parity-demo-project',
            'primary_domain' => 'parity.example.test',
            'subdomain' => 'parity-demo',
            'status' => 'published',
            'updated_at' => now(),
        ]);

        $tenantUserId = DB::table('tenant_users')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'platform_user_id' => $owner->id,
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@example.test',
            'phone' => '+995555222333',
            'password_hash' => '$2y$10$demohashdemohashdemohashdemo1234567890123456789012',
            'role' => 'owner',
            'role_legacy' => 'owner',
            'status' => 'active',
            'last_login_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mediaId = DB::table('media')->insertGetId([
            'site_id' => (string) $site->id,
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'path' => 'uploads/parity/hero.jpg',
            'url' => 'https://cdn.example.test/uploads/parity/hero.jpg',
            'file_name' => 'hero.jpg',
            'mime' => 'image/jpeg',
            'mime_type' => 'image/jpeg',
            'size' => 654321,
            'width' => 1600,
            'height' => 900,
            'alt' => 'Parity Hero',
            'meta_json' => json_encode(['source' => 'test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenants')->where('id', (string) $tenant->id)->update([
            'email' => 'tenant@example.test',
            'phone' => '+995555444555',
            'logo_media_id' => $mediaId,
            'updated_at' => now(),
        ]);

        $pageId = DB::table('pages')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'title' => 'Home',
            'slug' => 'parity-home',
            'status' => 'published',
            'page_json' => json_encode(['sections' => []]),
            'page_css' => '/* page css */',
            'seo_title' => 'Home SEO',
            'seo_description' => 'Home page',
            'og_image_media_id' => $mediaId,
            'published_at' => now(),
            'version' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('page_revisions')->insert([
            'site_id' => (string) $site->id,
            'page_id' => $pageId,
            'version' => 3,
            'content_json' => json_encode(['sections' => []]),
            'page_json' => json_encode(['sections' => []]),
            'page_css' => '/* rev css */',
            'created_by' => $owner->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = DB::table('menus')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'name' => 'Main Menu',
            'key' => 'main',
            'items_json' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $postId = DB::table('posts')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'title' => 'Parity Post',
            'slug' => 'parity-post',
            'status' => 'published',
            'excerpt' => 'Excerpt',
            'content_html' => '<p>Body</p>',
            'cover_media_id' => $mediaId,
            'seo_title' => 'Post SEO',
            'seo_description' => 'Post desc',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_settings')->insert([
            'project_id' => (string) $project->id,
            'key' => 'theme.tokens',
            'value_json' => json_encode(['colors' => ['primary' => '#111111']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('feature_flags')->insert([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'key' => 'booking.enabled',
            'enabled' => true,
            'rules_json' => json_encode(['audience' => 'all']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leads')->insert([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Lead One',
            'email' => 'lead@example.test',
            'phone' => '+995555000111',
            'message' => 'Need pricing',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = DB::table('ecommerce_categories')->insertGetId([
            'site_id' => (string) $site->id,
            'name' => 'Accessories',
            'slug' => 'accessories',
            'status' => 'active',
            'sort_order' => 1,
            'meta_json' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('ecommerce_products')->insertGetId([
            'site_id' => (string) $site->id,
            'category_id' => null,
            'name' => 'Parity Product',
            'slug' => 'parity-product',
            'sku' => 'PARITY-001',
            'short_description' => 'Short',
            'description' => 'Long',
            'price' => 99.99,
            'currency' => 'USD',
            'status' => 'published',
            'stock_tracking' => true,
            'stock_quantity' => 10,
            'allow_backorder' => false,
            'is_digital' => false,
            'attributes_json' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_category_relations')->insert([
            'product_id' => $productId,
            'category_id' => $categoryId,
            'created_at' => now(),
        ]);

        $orderId = DB::table('ecommerce_orders')->insertGetId([
            'site_id' => (string) $site->id,
            'order_number' => 'ORD-PARITY-001',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'USD',
            'subtotal' => 99.99,
            'tax_total' => 0,
            'shipping_total' => 10,
            'discount_total' => 0,
            'grand_total' => 109.99,
            'paid_total' => 0,
            'outstanding_total' => 109.99,
            'placed_at' => now(),
            'notes' => 'Parity order',
            'meta_json' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_addresses')->insert([
            'order_id' => $orderId,
            'type' => 'shipping',
            'name' => 'Receiver',
            'phone' => '+995555999888',
            'country' => 'GE',
            'city' => 'Tbilisi',
            'address1' => 'Rustaveli Ave 1',
            'address2' => null,
            'zip' => '0108',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('tenant_users', ['id' => $tenantUserId, 'role' => 'owner']);
        $this->assertDatabaseHas('tenants', ['id' => (string) $tenant->id, 'logo_media_id' => $mediaId]);
        $this->assertDatabaseHas('projects', ['id' => (string) $project->id, 'slug' => 'parity-demo-project', 'status' => 'published']);
        $this->assertDatabaseHas('media', ['id' => $mediaId, 'tenant_id' => (string) $tenant->id, 'file_name' => 'hero.jpg']);
        $this->assertDatabaseHas('pages', ['id' => $pageId, 'project_id' => (string) $project->id, 'slug' => 'parity-home', 'version' => 3]);
        $this->assertDatabaseHas('page_revisions', ['page_id' => $pageId, 'version' => 3]);
        $this->assertDatabaseHas('menus', ['id' => $menuId, 'project_id' => (string) $project->id, 'name' => 'Main Menu']);
        $this->assertDatabaseHas('posts', ['id' => $postId, 'slug' => 'parity-post', 'status' => 'published']);
        $this->assertDatabaseHas('project_settings', ['project_id' => (string) $project->id, 'key' => 'theme.tokens']);
        $this->assertDatabaseHas('feature_flags', ['tenant_id' => (string) $tenant->id, 'project_id' => (string) $project->id, 'key' => 'booking.enabled', 'enabled' => 1]);
        $this->assertDatabaseHas('leads', ['project_id' => (string) $project->id, 'email' => 'lead@example.test', 'status' => 'new']);
        $this->assertDatabaseHas('product_category_relations', ['product_id' => $productId, 'category_id' => $categoryId]);
        $this->assertDatabaseHas('order_addresses', ['order_id' => $orderId, 'type' => 'shipping', 'city' => 'Tbilisi']);
    }
}
