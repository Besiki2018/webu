<?php

namespace Tests\Feature\Cms;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectCmsWorkspaceSectionSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_workspace_sections_are_exposed_with_editable_schema_in_builder_library(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        app(ProjectWorkspaceService::class)->writeFile($project, 'src/sections/BistroServicesSection.tsx', <<<'TSX'
export default function BistroServicesSection() {
  return (
    <section className="section" data-section="BistroServicesSection">
      <div className="container">
        <div className="section-copy">
          <h2 className="section-title">Signature Services</h2>
          <p className="section-description">Private dining, chef tables, and seasonal tasting events.</p>
        </div>
      </div>
    </section>
  );
}
TSX);

        $response = $this->actingAs($user)
            ->get(route('project.cms', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Project/Cms'));

        $pageData = $response->original && method_exists($response->original, 'getData')
            ? $response->original->getData()
            : ($response->viewData('page') ?? []);
        $props = is_array($pageData) && isset($pageData['props'])
            ? $pageData['props']
            : (isset($pageData['page']['props']) ? $pageData['page']['props'] : []);

        $sectionLibrary = $props['sectionLibrary'] ?? [];
        $item = collect($sectionLibrary)->firstWhere('key', 'BistroServicesSection');

        $this->assertNotNull($item);
        $this->assertSame('workspace', data_get($item, 'schema_json._meta.source'));
        $this->assertArrayHasKey('title', data_get($item, 'schema_json.properties', []));
        $this->assertArrayHasKey('subtitle', data_get($item, 'schema_json.properties', []));
        $this->assertIsString((string) data_get($item, 'schema_json.properties.title.default'));
        $this->assertNotSame('', trim((string) data_get($item, 'schema_json.properties.title.default')));
    }
}
