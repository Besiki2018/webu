<?php

namespace Tests\Unit\AiWebsiteGeneration;

use App\Services\AiWebsiteGeneration\GeneratedSectionImagePlanner;
use Tests\TestCase;

class GeneratedSectionImagePlannerTest extends TestCase
{
    public function test_build_targets_detects_empty_hero_slots_and_generates_industry_specific_query(): void
    {
        $planner = app(GeneratedSectionImagePlanner::class);

        $targets = $planner->buildTargets(
            [
                'style' => 'modern',
                'businessType' => 'clinic',
                'websiteType' => 'business',
                'sourcePrompt' => 'Create a website for a modern veterinary clinic',
            ],
            [
                'slug' => 'home',
                'title' => 'Home',
            ],
            [
                'section_type' => 'webu_general_hero_01',
            ],
            [
                'title' => 'Trusted care for pets',
                'image' => '',
            ],
        );

        $this->assertNotEmpty($targets);
        $heroTarget = collect($targets)->firstWhere('path', 'image');

        $this->assertIsArray($heroTarget);
        $this->assertSame('hero', $heroTarget['role']);
        $this->assertSame('landscape', $heroTarget['orientation']);
        $this->assertSame(5, $heroTarget['provider_limit']);
        $this->assertStringContainsString('veterinary clinic', strtolower((string) $heroTarget['query']));
        $this->assertStringContainsString('interior', strtolower((string) $heroTarget['query']));
        $this->assertStringContainsString('/demo/hero/hero-', (string) $heroTarget['fallback_url']);
    }

    public function test_build_targets_uses_item_context_for_service_card_images_and_skips_filled_slots(): void
    {
        $planner = app(GeneratedSectionImagePlanner::class);

        $targets = $planner->buildTargets(
            [
                'style' => 'modern',
                'businessType' => 'beauty',
                'websiteType' => 'business',
                'sourcePrompt' => 'Create a website for dog grooming services',
            ],
            [
                'slug' => 'services',
                'title' => 'Services',
            ],
            [
                'section_type' => 'webu_general_cards_01',
            ],
            [
                'items' => [
                    ['title' => 'Dog grooming service', 'image' => ''],
                    ['title' => 'Spa treatment', 'image' => '/storage/existing-spa.jpg'],
                ],
            ],
        );

        $this->assertCount(1, $targets);
        $this->assertSame('items.0.image', $targets[0]['path']);
        $this->assertSame('features', $targets[0]['role']);
        $this->assertStringContainsString('dog grooming service', strtolower((string) $targets[0]['query']));
    }
}
