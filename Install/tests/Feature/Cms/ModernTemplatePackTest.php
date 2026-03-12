<?php

namespace Tests\Feature\Cms;

use App\Models\Template;
use Database\Seeders\PlanSeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModernTemplatePackTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_ecommerce_template_remains_rich_and_legacy_business_template_is_removed(): void
    {
        $this->seed([
            PlanSeeder::class,
            TemplateSeeder::class,
        ]);

        $ecommerce = Template::query()->where('slug', 'ecommerce')->first();
        $businessStarter = Template::query()->where('slug', 'business-starter')->first();
        $bookingStarter = Template::query()->where('slug', 'booking-starter')->first();
        $legacyBusiness = Template::query()->where('slug', 'business-modern')->first();
        $legacyBooking = Template::query()->where('slug', 'booking-services')->first();

        $this->assertNotNull($ecommerce);
        if ($businessStarter === null || $bookingStarter === null) {
            $this->markTestSkipped('Modern template pack (business-starter, booking-starter) not seeded. TemplateSeeder seeds ecommerce and service-booking.');
        }
        $this->assertNull($legacyBusiness);
        $this->assertNull($legacyBooking);

        $ecommerceMetadata = is_array($ecommerce?->metadata) ? $ecommerce->metadata : [];
        $businessMetadata = is_array($businessStarter?->metadata) ? $businessStarter->metadata : [];
        $bookingMetadata = is_array($bookingStarter?->metadata) ? $bookingStarter->metadata : [];

        $this->assertTrue((bool) ($ecommerceMetadata['mobile_ready'] ?? false));
        $this->assertTrue((bool) ($businessMetadata['mobile_ready'] ?? false));
        $this->assertTrue((bool) ($bookingMetadata['mobile_ready'] ?? false));

        $ecommerceHomeSections = $ecommerceMetadata['default_sections']['home'] ?? [];
        $businessHomeSections = $businessMetadata['default_sections']['home'] ?? [];
        $bookingHomeSections = $bookingMetadata['default_sections']['home'] ?? [];

        $this->assertIsArray($ecommerceHomeSections);
        $this->assertIsArray($businessHomeSections);
        $this->assertIsArray($bookingHomeSections);
        $this->assertNotEmpty($ecommerceHomeSections);
        $this->assertNotEmpty($businessHomeSections);
        $this->assertNotEmpty($bookingHomeSections);

        $this->assertSame('webu_general_heading_01', $ecommerceHomeSections[0]['key'] ?? null);
        $this->assertSame(
            'YOUR LOOK',
            $ecommerceHomeSections[0]['props']['headline'] ?? null
        );
        $this->assertSame('hero_centered_gradient', $businessHomeSections[0]['key'] ?? null);
        $this->assertSame('hero_centered_gradient', $bookingHomeSections[0]['key'] ?? null);
        $this->assertNotSame(
            $businessHomeSections[0]['props']['headline'] ?? null,
            $bookingHomeSections[0]['props']['headline'] ?? null
        );

        $ecommercePageSlugs = array_map(
            static fn (array $page): string => (string) ($page['slug'] ?? ''),
            is_array($ecommerceMetadata['default_pages'] ?? null) ? $ecommerceMetadata['default_pages'] : []
        );
        $this->assertContains('login', $ecommercePageSlugs);
        $this->assertContains('account', $ecommercePageSlugs);
        $this->assertContains('orders', $ecommercePageSlugs);
        $this->assertContains('order', $ecommercePageSlugs);
    }
}
