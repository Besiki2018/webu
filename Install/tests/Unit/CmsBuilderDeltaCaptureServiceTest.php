<?php

namespace Tests\Unit;

use App\Models\CmsBuilderDelta;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\CmsBuilderDeltaCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsBuilderDeltaCaptureServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_captures_patch_ops_from_ai_baseline_to_manual_revision(): void
    {
        [$project, $site, $owner] = $this->createPublishedProjectWithSite();
        $page = Page::query()->create([
            'site_id' => (string) $site->id,
            'title' => 'Delta Test',
            'slug' => 'delta-test',
            'status' => 'draft',
        ]);

        $baselineContent = $this->aiBaselineContent('AI Headline');
        $baselineRevision = PageRevision::query()->create([
            'site_id' => (string) $site->id,
            'page_id' => $page->id,
            'version' => 1,
            'content_json' => $baselineContent,
            'created_by' => null,
        ]);

        $manualContent = $baselineContent;
        $manualContent['sections'][0]['props']['headline'] = 'Manual Headline';
        $manualContent['sections'][0]['props']['cta'] = ['label' => 'Shop now', 'url' => '/shop'];
        $manualRevision = PageRevision::query()->create([
            'site_id' => (string) $site->id,
            'page_id' => $page->id,
            'version' => 2,
            'content_json' => $manualContent,
            'created_by' => $owner->id,
        ]);

        $delta = app(CmsBuilderDeltaCaptureService::class)->captureAfterManualRevisionSave(
            $site,
            $page,
            $baselineRevision,
            $manualRevision,
            $owner->id,
            'ka',
            'ka',
        );

        $this->assertNotNull($delta);
        $this->assertInstanceOf(CmsBuilderDelta::class, $delta);
        $this->assertSame((string) $site->id, $delta->site_id);
        $this->assertSame((string) $project->id, $delta->project_id);
        $this->assertSame($page->id, $delta->page_id);
        $this->assertSame($baselineRevision->id, $delta->baseline_revision_id);
        $this->assertSame($manualRevision->id, $delta->target_revision_id);
        $this->assertStringStartsWith('gen_', $delta->generation_id);
        $this->assertSame('panel_revision_save', $delta->captured_from);
        $this->assertSame($owner->id, $delta->created_by);

        $ops = $delta->patch_ops ?? [];
        $this->assertIsArray($ops);
        $this->assertNotEmpty($ops);
        $paths = collect($ops)->pluck('path')->filter()->values()->all();
        $this->assertContains('/sections/0/props/headline', $paths);
        $this->assertContains('/sections/0/props/cta', $paths);
        $this->assertSame('fingerprint', data_get($delta->patch_stats_json, 'generation_id_source'));
        $this->assertSame(1, (int) data_get($delta->patch_stats_json, 'touched_sections_count'));
    }

    public function test_it_skips_non_primary_locale_delta_capture_in_g1_baseline(): void
    {
        [, $site, $owner] = $this->createPublishedProjectWithSite();
        $page = Page::query()->create([
            'site_id' => (string) $site->id,
            'title' => 'Delta Locale Test',
            'slug' => 'delta-locale',
            'status' => 'draft',
        ]);

        $content = $this->aiBaselineContent('AI Headline');
        $baselineRevision = PageRevision::query()->create([
            'site_id' => (string) $site->id,
            'page_id' => $page->id,
            'version' => 1,
            'content_json' => $content,
            'created_by' => null,
        ]);
        $nextContent = $content;
        $nextContent['sections'][0]['props']['headline'] = 'English Translation Edit';
        $manualRevision = PageRevision::query()->create([
            'site_id' => (string) $site->id,
            'page_id' => $page->id,
            'version' => 2,
            'content_json' => $nextContent,
            'created_by' => $owner->id,
        ]);

        $delta = app(CmsBuilderDeltaCaptureService::class)->captureAfterManualRevisionSave(
            $site,
            $page,
            $baselineRevision,
            $manualRevision,
            $owner->id,
            'en',
            'ka',
        );

        $this->assertNull($delta);
        $this->assertSame(0, CmsBuilderDelta::query()->count());
    }

    /**
     * @return array{0: Project, 1: Site, 2: User}
     */
    private function createPublishedProjectWithSite(): array
    {
        $plan = Plan::factory()->create();
        $owner = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($owner)->published(strtolower(Str::random(10)))->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site, $owner];
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
                            'content' => ['headline' => 'AI baseline'],
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
                'meta' => [
                    'family' => 'ecommerce',
                    'prompt_tags' => ['clean'],
                ],
            ],
        ];
    }
}
