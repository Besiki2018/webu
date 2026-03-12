<?php

namespace Tests\Feature\Templates;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplatePublicDefaultSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_default_site_endpoint_returns_latest_public_site_for_template(): void
    {
        $template = Template::factory()->create([
            'slug' => 'webu-shop-01',
            'name' => 'Webu Shop 01',
        ]);

        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published('webu-shop-demo')
            ->create([
                'template_id' => $template->id,
                'published_visibility' => 'public',
            ]);

        $site = $project->site()->firstOrFail();

        $this->getJson('/public/templates/webu-shop-01/default-site')
            ->assertOk()
            ->assertJsonPath('template_slug', 'webu-shop-01')
            ->assertJsonPath('site_id', $site->id);
    }

    public function test_default_site_endpoint_returns_404_when_template_has_no_public_site(): void
    {
        Template::factory()->create([
            'slug' => 'webu-shop-01',
            'name' => 'Webu Shop 01',
        ]);

        $this->getJson('/public/templates/webu-shop-01/default-site')
            ->assertNotFound();
    }
}
