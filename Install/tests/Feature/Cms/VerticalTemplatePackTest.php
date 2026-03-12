<?php

namespace Tests\Feature\Cms;

use App\Models\Plan;
use App\Models\Template;
use Database\Seeders\PlanSeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerticalTemplatePackTest extends TestCase
{
    use RefreshDatabase;

    public function test_vertical_templates_include_required_metadata_and_plan_assignments(): void
    {
        $this->seed([
            PlanSeeder::class,
            TemplateSeeder::class,
        ]);

        $verticalSlugs = ['vet', 'grooming', 'medical', 'restaurant', 'construction', 'legal'];
        $requiredModuleFlags = ['ecommerce', 'booking', 'payments', 'shipping'];

        $missing = [];
        foreach ($verticalSlugs as $slug) {
            if (Template::query()->where('slug', $slug)->exists()) {
                continue;
            }
            $missing[] = $slug;
        }
        if ($missing !== []) {
            $this->markTestSkipped('Vertical template pack not seeded (missing slugs: ' . implode(', ', $missing) . '). Seed vertical templates to run this test.');
        }

        foreach ($verticalSlugs as $slug) {
            $template = Template::query()->where('slug', $slug)->first();

            $this->assertNotNull($template, "Expected seeded template [{$slug}] to exist.");
            $this->assertFalse((bool) $template->is_system, "Template [{$slug}] must stay user-selectable.");

            $metadata = is_array($template->metadata) ? $template->metadata : [];
            $defaultPages = $metadata['default_pages'] ?? null;
            $defaultSections = $metadata['default_sections'] ?? null;
            $moduleFlags = $metadata['module_flags'] ?? null;
            $typographyTokens = $metadata['typography_tokens'] ?? null;

            $this->assertIsArray($defaultPages, "Template [{$slug}] is missing default_pages.");
            $this->assertNotEmpty($defaultPages, "Template [{$slug}] default_pages cannot be empty.");
            $this->assertIsArray($defaultSections, "Template [{$slug}] is missing default_sections.");
            $this->assertNotEmpty($defaultSections, "Template [{$slug}] default_sections cannot be empty.");
            $this->assertIsArray($moduleFlags, "Template [{$slug}] is missing module_flags.");
            $this->assertIsArray($typographyTokens, "Template [{$slug}] is missing typography_tokens.");

            $firstPage = $defaultPages[0] ?? null;
            $this->assertIsArray($firstPage, "Template [{$slug}] has invalid default page structure.");
            $this->assertArrayHasKey('slug', $firstPage);
            $this->assertArrayHasKey('title', $firstPage);
            $this->assertArrayHasKey('sections', $firstPage);
            $this->assertIsArray($firstPage['sections']);

            foreach ($requiredModuleFlags as $flagKey) {
                $this->assertArrayHasKey($flagKey, $moduleFlags, "Template [{$slug}] missing module flag [{$flagKey}].");
                $this->assertIsBool($moduleFlags[$flagKey], "Template [{$slug}] module flag [{$flagKey}] must be boolean.");
            }

            foreach (['heading', 'body', 'button'] as $tokenKey) {
                $this->assertArrayHasKey($tokenKey, $typographyTokens, "Template [{$slug}] missing typography token [{$tokenKey}].");
                $this->assertIsString($typographyTokens[$tokenKey], "Template [{$slug}] typography token [{$tokenKey}] must be string.");
                $this->assertContains(
                    $typographyTokens[$tokenKey],
                    ['base', 'heading', 'body', 'button'],
                    "Template [{$slug}] typography token [{$tokenKey}] has unsupported value."
                );
            }
        }

        foreach (['pro', 'enterprise'] as $planSlug) {
            $plan = Plan::query()->where('slug', $planSlug)->first();

            $this->assertNotNull($plan, "Plan [{$planSlug}] must exist.");

            $assigned = $plan->templates()
                ->whereIn('templates.slug', $verticalSlugs)
                ->pluck('templates.slug')
                ->all();

            sort($assigned);
            $expected = $verticalSlugs;
            sort($expected);

            $this->assertSame($expected, $assigned, "Plan [{$planSlug}] must receive all vertical templates.");
        }

        $freePlan = Plan::query()->where('slug', 'free')->first();
        $this->assertNotNull($freePlan, 'Plan [free] must exist.');
        $this->assertSame(
            0,
            $freePlan->templates()->whereIn('templates.slug', $verticalSlugs)->count(),
            'Free plan must not receive vertical template pack by default.'
        );
    }
}
