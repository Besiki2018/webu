<?php

namespace Tests\Feature\Create;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreateTemplateCatalogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
        $this->seed([
            PlanSeeder::class,
            TemplateSeeder::class,
        ]);
    }

    public function test_create_page_excludes_ecommerce_booking_and_portfolio_start_templates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Create')
                ->where('templates', fn ($templates): bool => $this->catalogExcludesBlockedTemplates($templates))
                ->where('readyTemplates', fn ($templates): bool => $this->catalogExcludesBlockedTemplates($templates))
            );
    }

    private function catalogExcludesBlockedTemplates(mixed $templates): bool
    {
        if ($templates instanceof Collection) {
            $templates = $templates->values()->all();
        }

        if (! is_array($templates)) {
            return false;
        }

        foreach ($templates as $template) {
            if (! is_array($template)) {
                continue;
            }

            $slug = strtolower(trim((string) ($template['slug'] ?? '')));
            $category = strtolower(trim((string) ($template['category'] ?? '')));

            if (in_array($slug, ['ecommerce', 'ecommerce-storefront', 'service-booking', 'portfolio-agency'], true)) {
                return false;
            }

            if (in_array($category, ['ecommerce', 'booking', 'portfolio'], true)) {
                return false;
            }
        }

        return true;
    }
}
