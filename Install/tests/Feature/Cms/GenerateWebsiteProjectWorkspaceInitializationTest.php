<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AiWebsiteGeneration\GenerateWebsiteProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GenerateWebsiteProjectWorkspaceInitializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_generated_websites_create_real_workspace_files_and_component_index(): void
    {
        $user = User::factory()->create();

        $result = app(GenerateWebsiteProjectService::class)->generate([
            'userPrompt' => 'Create a website for restaurant',
            'user_id' => $user->id,
            'ultra_cheap_mode' => true,
        ]);

        $project = $result['project']->fresh();
        $workspaceRoot = storage_path('workspaces/'.$project->id);

        $this->assertFileExists($workspaceRoot.'/package.json');
        $this->assertFileExists($workspaceRoot.'/index.html');
        $this->assertFileExists($workspaceRoot.'/src/main.tsx');
        $this->assertFileExists($workspaceRoot.'/src/pages/home/Page.tsx');
        $this->assertFileExists($workspaceRoot.'/src/sections/HeroSection.tsx');
        $this->assertFileExists($workspaceRoot.'/.webu/index.json');
        $this->assertFileExists($workspaceRoot.'/.webu/component-parameters.json');

        $index = json_decode(File::get($workspaceRoot.'/.webu/index.json'), true);

        $this->assertIsArray($index);
        $this->assertArrayHasKey('component_parameters', $index);
        $this->assertArrayHasKey('sections', $index['component_parameters']);
        $this->assertArrayHasKey('HeroSection', $index['component_parameters']['sections']);
        $this->assertArrayHasKey('fields', $index['component_parameters']['sections']['HeroSection']);

        $homePage = Page::query()
            ->where('site_id', $result['site']->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $homeRevision = PageRevision::query()
            ->where('site_id', $result['site']->id)
            ->where('page_id', $homePage->id)
            ->latest('version')
            ->firstOrFail();

        $sections = $homeRevision->content_json['sections'] ?? [];
        $this->assertNotEmpty($sections);
        foreach (array_values($sections) as $index => $section) {
            $this->assertSame('section-'.$index, $section['localId'] ?? null);
        }
    }
}
