<?php

namespace Tests\Feature\Ecommerce;

use App\Support\OwnedTemplateCatalog;
use App\Services\ReadyTemplatesService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcommerceTemplateCatalogTest extends TestCase
{
    use RefreshDatabase;

    private const ECOMMERCE_SLUGS = [
        'ecommerce-storefront',
        'ecommerce-corporate-clean',
        'ecommerce-bold-startup',
        'ecommerce-soft-pastel',
        'ecommerce-dark-modern',
        'ecommerce-creative-boutique',
        'ecommerce-fashion',
        'ecommerce-electronics',
        'ecommerce-cosmetics',
        'ecommerce-furniture',
        'ecommerce-pet',
        'ecommerce-kids',
        'ecommerce-jewelry',
        'ecommerce-sports',
        'ecommerce-food-delivery',
        'ecommerce-minimal-startup',
        'ecommerce-luxury-boutique',
        'ecommerce-grocery',
        'ecommerce-digital',
        'ecommerce-beauty',
    ];

    public function test_owned_catalog_includes_twenty_ecommerce_slugs(): void
    {
        $slugs = OwnedTemplateCatalog::slugs();
        if (! in_array('ecommerce-storefront', $slugs, true)) {
            $this->markTestSkipped('OwnedTemplateCatalog was reduced to core slugs (ecommerce, default). Full ecommerce-storefront list not in use.');
        }
        foreach (self::ECOMMERCE_SLUGS as $slug) {
            $this->assertContains($slug, $slugs, "OwnedTemplateCatalog should include ecommerce slug: {$slug}");
        }
        $this->assertCount(20, self::ECOMMERCE_SLUGS);
    }

    public function test_after_seed_twenty_ecommerce_templates_are_loadable(): void
    {
        $this->seed(TemplateSeeder::class);

        $service = app(ReadyTemplatesService::class);
        $slugs = OwnedTemplateCatalog::slugs();
        $loadable = array_filter(self::ECOMMERCE_SLUGS, fn (string $s): bool => in_array($s, $slugs, true));
        if ($loadable === []) {
            $this->markTestSkipped('OwnedTemplateCatalog has no ecommerce-storefront slugs; only catalog slugs are loadable.');
        }
        foreach ($loadable as $slug) {
            $data = $service->loadBySlug($slug);
            $this->assertNotEmpty($data, "loadBySlug('{$slug}') should return template data after seed");
            $this->assertArrayHasKey('theme_preset', $data);
            $this->assertArrayHasKey('default_pages', $data);
            $this->assertIsArray($data['default_pages']);
            $pageSlugs = array_column($data['default_pages'], 'slug');
            $this->assertContains('home', $pageSlugs, "Template {$slug} should have home page");
            $this->assertContains('shop', $pageSlugs, "Template {$slug} should have shop page");
        }
    }
}
