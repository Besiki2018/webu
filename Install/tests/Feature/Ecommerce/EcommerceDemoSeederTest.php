<?php

namespace Tests\Feature\Ecommerce;

use App\Models\EcommerceCategory;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductImage;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\EcommerceDemoSeederService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommerceDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_seeder_creates_categories_products_images_discounts_stock(): void
    {
        [$project, $site] = $this->createProjectWithSite();

        $service = app(EcommerceDemoSeederService::class);
        $service->run($site);

        $site->refresh();
        $categories = EcommerceCategory::query()->where('site_id', $site->id)->get();
        $this->assertGreaterThanOrEqual(6, $categories->count(), 'Expected at least 6 categories');
        $slugs = $categories->pluck('slug')->sort()->values()->all();
        $this->assertContains('featured', $slugs);
        $this->assertContains('best-sellers', $slugs);
        $this->assertContains('sale', $slugs);

        $products = EcommerceProduct::query()->where('site_id', $site->id)->get();
        $this->assertGreaterThanOrEqual(24, $products->count(), 'Expected at least 24 products');

        $withDiscount = $products->filter(fn ($p) => $p->compare_at_price !== null)->count();
        $this->assertGreaterThan(0, $withDiscount, 'Expected at least some products with compare_at_price (discounts)');

        $withStock = $products->filter(fn ($p) => $p->stock_quantity > 0)->count();
        $this->assertGreaterThanOrEqual(24, $withStock, 'Expected all products to have stock_quantity');

        $images = EcommerceProductImage::query()->where('site_id', $site->id)->get();
        $this->assertGreaterThanOrEqual(24, $images->count(), 'Expected at least 1 image per product (24 total min)');
        $productIds = $products->pluck('id')->all();
        foreach ($productIds as $productId) {
            $count = $images->where('product_id', $productId)->count();
            $this->assertGreaterThanOrEqual(1, $count, "Product {$productId} should have at least 1 image");
        }

        $this->assertTrue($service->isSeeded($site), 'Site should be marked as seeded');
    }

    public function test_seeder_is_idempotent(): void
    {
        [$project, $site] = $this->createProjectWithSite();
        $service = app(EcommerceDemoSeederService::class);

        $service->run($site);
        $site->refresh();

        $categoriesAfterFirst = EcommerceCategory::query()->where('site_id', $site->id)->count();
        $productsAfterFirst = EcommerceProduct::query()->where('site_id', $site->id)->count();
        $imagesAfterFirst = EcommerceProductImage::query()->where('site_id', $site->id)->count();

        $service->run($site->fresh());
        $site->refresh();

        $this->assertSame($categoriesAfterFirst, EcommerceCategory::query()->where('site_id', $site->id)->count(), 'Categories must not duplicate on second run');
        $this->assertSame($productsAfterFirst, EcommerceProduct::query()->where('site_id', $site->id)->count(), 'Products must not duplicate on second run');
        $this->assertSame($imagesAfterFirst, EcommerceProductImage::query()->where('site_id', $site->id)->count(), 'Images must not duplicate on second run');
    }

    public function test_seeder_with_force_re_runs_and_updates_or_creates(): void
    {
        [$project, $site] = $this->createProjectWithSite();
        $service = app(EcommerceDemoSeederService::class);

        $service->run($site);
        $site->refresh();
        $productsFirst = EcommerceProduct::query()->where('site_id', $site->id)->count();

        $service->run($site->fresh(), true);
        $site->refresh();
        $productsSecond = EcommerceProduct::query()->where('site_id', $site->id)->count();
        $this->assertSame($productsFirst, $productsSecond, 'With force, product count should remain same (updateOrCreate)');
    }

    private function createProjectWithSite(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}
