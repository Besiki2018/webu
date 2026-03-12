<?php

namespace Tests\Feature\Cms;

use App\Models\CmsBuilderDelta;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsBuilderDeltaCapturePipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_panel_page_revision_save_captures_builder_delta_from_ai_baseline(): void
    {
        [$owner, $site] = $this->createOwnerAndSite();
        $page = Page::query()->create([
            'site_id' => (string) $site->id,
            'title' => 'AI Delta Page',
            'slug' => 'ai-delta-page',
            'status' => 'draft',
        ]);

        $baselineContent = $this->aiBaselineContent('AI Headline');
        PageRevision::query()->create([
            'site_id' => (string) $site->id,
            'page_id' => $page->id,
            'version' => 1,
            'content_json' => $baselineContent,
            'created_by' => null,
        ]);

        $manualContent = $baselineContent;
        $manualContent['sections'][0]['props']['headline'] = 'Edited in Builder';
        $manualContent['sections'][0]['props']['subtitle'] = 'Manual subtitle';

        $response = $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.revisions.store', ['site' => $site->id, 'page' => $page->id]), [
                'locale' => $site->locale ?: 'ka',
                'content_json' => $manualContent,
            ])
            ->assertCreated()
            ->assertJsonPath('revision.version', 2);

        $delta = CmsBuilderDelta::query()->firstOrFail();
        $this->assertSame($page->id, $delta->page_id);
        $this->assertSame((int) $response->json('revision.id'), $delta->target_revision_id);
        $this->assertStringStartsWith('gen_', $delta->generation_id);

        $paths = collect($delta->patch_ops ?? [])->pluck('path')->filter()->values()->all();
        $this->assertNotEmpty($paths);
        $encodedPatch = json_encode($delta->patch_ops, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertIsString($encodedPatch);
        $this->assertStringContainsString('Edited in Builder', $encodedPatch);
        $this->assertStringContainsString('Manual subtitle', $encodedPatch);
        $this->assertSame('panel_revision_save', $delta->captured_from);
    }

    public function test_non_primary_locale_panel_revision_save_skips_builder_delta_capture(): void
    {
        [$owner, $site] = $this->createOwnerAndSite();
        $page = Page::query()->create([
            'site_id' => (string) $site->id,
            'title' => 'AI Delta Locale Page',
            'slug' => 'ai-delta-locale-page',
            'status' => 'draft',
        ]);

        $baselineContent = $this->aiBaselineContent('KA Headline');
        PageRevision::query()->create([
            'site_id' => (string) $site->id,
            'page_id' => $page->id,
            'version' => 1,
            'content_json' => $baselineContent,
            'created_by' => null,
        ]);

        $manualContent = $baselineContent;
        $manualContent['sections'][0]['props']['headline'] = 'English translation';

        $alternateLocale = strtolower((string) ($site->locale ?: 'ka')) === 'ka' ? 'en' : 'ka';

        $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.revisions.store', ['site' => $site->id, 'page' => $page->id]), [
                'locale' => $alternateLocale,
                'content_json' => $manualContent,
            ])
            ->assertCreated();

        $this->assertSame(0, CmsBuilderDelta::query()->count());
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
    private function aiBaselineContent(string $headline): array
    {
        return [
            'schema_version' => 1,
            'editor_mode' => 'builder',
            'text_editor_html' => '',
            'sections' => [
                [
                    'type' => 'webu_hero_01',
                    'props' => [
                        'headline' => $headline,
                    ],
                ],
            ],
            'ai_generation' => [
                'schema_version' => 1,
                'saved_via' => 'CmsAiOutputSaveEngine',
                'builder_nodes' => [
                    [
                        'type' => 'webu_hero_01',
                        'props' => [
                            'content' => ['headline' => $headline],
                            'data' => [],
                            'style' => [],
                            'advanced' => [],
                            'responsive' => [],
                            'states' => [],
                        ],
                        'bindings' => [],
                        'meta' => ['ai_slot' => 'hero'],
                    ],
                ],
                'page_css' => '/* webu-ai-placement:v1 */',
                'route' => [
                    'path' => '/',
                    'route_pattern' => '/',
                    'template_key' => 'home',
                ],
                'meta' => ['family' => 'ecommerce'],
            ],
        ];
    }
}
