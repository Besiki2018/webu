<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CmsAiOutputSaveEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsAiGeneratedSiteBuilderEditabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_ai_generated_page_can_be_opened_edited_and_republished_via_standard_cms_builder_endpoints(): void
    {
        [$owner, $site] = $this->createOwnerAndSite();

        $result = app(CmsAiOutputSaveEngine::class)->persistOutputForSite(
            $site,
            $this->validAiOutputEnvelope(),
            $owner->id
        );

        $this->assertTrue($result['ok'], json_encode($result, JSON_PRETTY_PRINT));
        $this->assertTrue((bool) data_get($result, 'saved.no_parallel_storage'));

        $page = Page::query()->where('site_id', $site->id)->where('slug', 'home')->firstOrFail();

        $detailResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.pages.show', ['site' => $site->id, 'page' => $page->id]))
            ->assertOk()
            ->assertJsonPath('page.id', $page->id)
            ->assertJsonPath('latest_revision.content_json.ai_generation.saved_via', 'CmsAiOutputSaveEngine');

        $content = $detailResponse->json('latest_revision.content_json');
        $this->assertIsArray($content);
        $this->assertIsArray(data_get($content, 'sections'));
        $this->assertNotEmpty(data_get($content, 'sections'));
        $this->assertIsArray(data_get($content, 'ai_generation.builder_nodes'));

        $editedContent = $content;
        $editedContent['editor_mode'] = 'builder';
        data_set($editedContent, 'meta.milestone_b_builder_editability_smoke', 'panel-revision-roundtrip');

        $revisionResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.revisions.store', ['site' => $site->id, 'page' => $page->id]), [
                'content_json' => $editedContent,
                'locale' => $site->locale ?: 'ka',
            ])
            ->assertCreated()
            ->assertJsonPath('binding_validation.valid', true);

        $revisionId = (int) $revisionResponse->json('revision.id');
        $this->assertGreaterThan(0, $revisionId);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.publish', ['site' => $site->id, 'page' => $page->id]), [
                'revision_id' => $revisionId,
            ])
            ->assertOk()
            ->assertJsonPath('page_id', $page->id)
            ->assertJsonPath('revision_id', $revisionId);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.pages.show', ['site' => $site->id, 'page' => $page->id]))
            ->assertOk()
            ->assertJsonPath('latest_revision.id', $revisionId);
    }

    /**
     * @return array{0: User, 1: \App\Models\Site}
     */
    private function createOwnerAndSite(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->published(strtolower(Str::random(8)))->create();

        /** @var \App\Models\Site $site */
        $site = $project->site()->firstOrFail();

        return [$owner, $site];
    }

    /**
     * @return array<string, mixed>
     */
    private function validAiOutputEnvelope(): array
    {
        return [
            'schema_version' => 1,
            'theme' => [
                'theme_settings_patch' => [
                    'preset' => 'default',
                ],
                'meta' => [
                    'source' => 'generated',
                ],
            ],
            'pages' => [
                [
                    'slug' => 'home',
                    'title' => 'AI Home',
                    'path' => '/',
                    'status' => 'published',
                    'template_key' => 'home',
                    'route_pattern' => '/',
                    'builder_nodes' => [
                        [
                            'type' => 'webu_hero_01',
                            'props' => [
                                'content' => [
                                    'headline' => 'AI Generated Headline',
                                    'subtitle' => 'Builder editability milestone smoke',
                                ],
                                'data' => [],
                                'style' => [],
                                'advanced' => [],
                                'responsive' => [],
                                'states' => [],
                            ],
                            'bindings' => [],
                            'meta' => [
                                'schema_version' => 1,
                                'source' => 'generated',
                                'ai_slot' => 'hero',
                            ],
                        ],
                    ],
                    'page_css' => '/* webu-ai-placement:v1 */',
                    'seo' => [
                        'seo_title' => 'AI Home',
                        'seo_description' => 'AI generated home page',
                    ],
                    'meta' => [
                        'source' => 'generated',
                    ],
                ],
            ],
            'header' => [
                'enabled' => true,
                'section_type' => 'webu_header_01',
                'props' => [
                    'headline' => 'AI Header',
                ],
                'bindings' => [
                    'login_url' => '/account/login',
                ],
                'meta' => [
                    'source' => 'generated',
                ],
            ],
            'footer' => [
                'enabled' => true,
                'section_type' => 'webu_footer_01',
                'props' => [
                    'headline' => 'AI Footer',
                ],
                'meta' => [
                    'source' => 'generated',
                ],
            ],
            'meta' => [
                'generator' => [
                    'kind' => 'ai',
                    'version' => 'v1',
                ],
                'created_at' => '2026-02-24T12:00:00Z',
                'contracts' => [
                    'ai_input_schema' => 'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
                    'canonical_page_node_schema' => 'docs/architecture/schemas/cms-canonical-page-node.v1.schema.json',
                    'canonical_component_registry_schema' => 'docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json',
                ],
                'validation_expectations' => [
                    'strict_top_level' => true,
                    'no_parallel_storage' => true,
                    'builder_native_pages' => true,
                    'component_availability_check_required' => true,
                    'binding_validation_required' => true,
                ],
            ],
        ];
    }
}
