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

class UniversalContentNormalizationTablesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_normalization_tables_exist_with_canonical_columns(): void
    {
        foreach (['menu_items', 'post_categories', 'post_category_relations'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        $this->assertTrue(Schema::hasColumns('menu_items', [
            'menu_id', 'title', 'url', 'parent_id', 'sort_order', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('post_categories', [
            'tenant_id', 'project_id', 'site_id', 'name', 'slug', 'parent_id', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('post_category_relations', [
            'post_id', 'category_id',
        ]));
    }

    public function test_content_normalization_tables_support_menu_tree_and_blog_category_pivot_flow(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::query()->create([
            'name' => 'Content Normalization Tenant',
            'slug' => 'content-norm-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'created_by_user_id' => $owner->id,
        ]);

        $project = Project::factory()->for($owner)->create([
            'tenant_id' => (string) $tenant->id,
            'type' => 'company',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $site = Site::query()->where('project_id', (string) $project->id)->first();

        if (! $site) {
            $site = Site::query()->create([
                'project_id' => (string) $project->id,
                'name' => 'Content Normalization Site',
                'subdomain' => 'contentnorm-'.Str::lower(Str::random(6)),
                'status' => 'draft',
                'locale' => 'en',
                'theme_settings' => ['project_type' => 'company'],
            ]);
        }

        $menuKey = 'content-norm-'.Str::lower(Str::random(8));

        $menuId = DB::table('menus')->insertGetId([
            'site_id' => (string) $site->id,
            'key' => $menuKey,
            'items_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parentMenuItemId = DB::table('menu_items')->insertGetId([
            'menu_id' => $menuId,
            'title' => 'Products',
            'url' => '/products',
            'parent_id' => null,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'menu_id' => $menuId,
            'title' => 'Pricing',
            'url' => '/products/pricing',
            'parent_id' => $parentMenuItemId,
            'sort_order' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $postId = DB::table('blog_posts')->insertGetId([
            'site_id' => (string) $site->id,
            'title' => 'Launch Post',
            'slug' => 'launch-post',
            'excerpt' => 'Launch excerpt',
            'content' => '<p>Launch</p>',
            'status' => 'published',
            'cover_media_id' => null,
            'published_at' => now(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = DB::table('post_categories')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'site_id' => (string) $site->id,
            'name' => 'News',
            'slug' => 'news',
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('post_category_relations')->insert([
            'post_id' => $postId,
            'category_id' => $categoryId,
        ]);

        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menuId,
            'title' => 'Products',
            'parent_id' => null,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menuId,
            'title' => 'Pricing',
            'parent_id' => $parentMenuItemId,
        ]);
        $this->assertDatabaseHas('post_categories', [
            'id' => $categoryId,
            'project_id' => (string) $project->id,
            'slug' => 'news',
        ]);
        $this->assertDatabaseHas('post_category_relations', [
            'post_id' => $postId,
            'category_id' => $categoryId,
        ]);
    }
}
