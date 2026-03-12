<?php

namespace Tests\Unit;

use App\Models\SectionLibrary;
use App\Services\BuilderSectionCatalogService;
use App\Services\CmsComponentLibraryCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuilderComponentCatalogProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_component_catalog_excludes_placeholder_only_entries_from_builder_library(): void
    {
        SectionLibrary::query()->create([
            'key' => 'webu_general_placeholder_01',
            'category' => 'general',
            'schema_json' => [
                '_meta' => [
                    'label' => 'Placeholder',
                ],
            ],
            'enabled' => true,
        ]);

        SectionLibrary::query()->create([
            'key' => 'webu_general_text_01',
            'category' => 'general',
            'schema_json' => [
                '_meta' => [
                    'label' => 'Text',
                ],
            ],
            'enabled' => true,
        ]);

        $catalog = app(CmsComponentLibraryCatalogService::class)->buildCatalog();
        $keys = array_column($catalog, 'key');

        $this->assertContains('webu_general_text_01', $keys);
        $this->assertNotContains('webu_general_placeholder_01', $keys);
    }

    public function test_builder_section_catalog_hides_placeholder_sections_from_grouped_results(): void
    {
        SectionLibrary::query()->create([
            'key' => 'webu_general_placeholder_01',
            'category' => 'general',
            'schema_json' => [
                '_meta' => [
                    'temporary' => true,
                ],
            ],
            'enabled' => true,
        ]);

        SectionLibrary::query()->create([
            'key' => 'webu_general_heading_01',
            'category' => 'content',
            'schema_json' => [
                '_meta' => [
                    'label' => 'Heading',
                ],
            ],
            'enabled' => true,
        ]);

        $grouped = app(BuilderSectionCatalogService::class)->grouped();
        $keys = collect($grouped)
            ->flatMap(fn (array $group) => $group['items'] ?? [])
            ->pluck('key')
            ->all();

        $this->assertContains('webu_general_heading_01', $keys);
        $this->assertNotContains('webu_general_placeholder_01', $keys);
    }
}
