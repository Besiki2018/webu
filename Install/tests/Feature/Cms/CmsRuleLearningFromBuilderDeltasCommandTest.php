<?php

namespace Tests\Feature\Cms;

use App\Models\CmsBuilderDelta;
use App\Models\CmsLearnedRule;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsRuleLearningFromBuilderDeltasCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_cms_learn_rules_from_builder_deltas_command_creates_candidate_rules(): void
    {
        [$project, $site] = $this->createPublishedProjectWithSite();
        $pageA = $this->createPage($site, 'shop-a');
        $baseA = $this->createBaselineRevision($site, $pageA, 1, 'webu_product_grid_01');
        $targetA = $this->createTargetRevision($site, $pageA, 2, $baseA->content_json);

        $pageB = $this->createPage($site, 'shop-b');
        $baseB = $this->createBaselineRevision($site, $pageB, 1, 'webu_product_grid_01');
        $targetB = $this->createTargetRevision($site, $pageB, 2, $baseB->content_json);

        $now = Carbon::parse('2026-02-24 20:00:00');
        foreach ([[ $pageA, $baseA, $targetA, 'gen_a'], [ $pageB, $baseB, $targetB, 'gen_b']] as [$page, $baseline, $target, $gen]) {
            CmsBuilderDelta::query()->create([
                'site_id' => (string) $site->id,
                'project_id' => (string) $project->id,
                'page_id' => $page->id,
                'baseline_revision_id' => $baseline->id,
                'target_revision_id' => $target->id,
                'generation_id' => $gen,
                'locale' => 'ka',
                'captured_from' => 'panel_revision_save',
                'patch_ops' => [
                    ['op' => 'replace', 'path' => '/sections/0/props/style/columns', 'value' => 3],
                ],
                'patch_stats_json' => ['ops_count' => 1],
                'created_at' => $now->copy()->subMinutes(5),
                'updated_at' => $now->copy()->subMinutes(5),
            ]);
        }

        $this->artisan('cms:learn-rules-from-builder-deltas', [
            '--site' => (string) $site->id,
            '--since' => '2000-01-01 00:00:00',
            '--until' => '2100-01-01 00:00:00',
            '--min-occurrences' => 2,
        ])
            ->expectsOutputToContain('Learned CMS rule candidates from builder deltas')
            ->assertSuccessful();

        /** @var CmsLearnedRule $rule */
        $rule = CmsLearnedRule::query()->firstOrFail();
        $this->assertSame((string) $site->id, $rule->site_id);
        $this->assertSame(2, (int) $rule->sample_size);
        $this->assertSame('candidate', $rule->status);
        $this->assertFalse((bool) $rule->active);
        $this->assertSame('webu_product_grid_01', data_get($rule->conditions_json, 'component_type'));
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(): array
    {
        $plan = Plan::factory()->create();
        $owner = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($owner)->published(strtolower(Str::random(10)))->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }

    private function createPage(Site $site, string $slug): Page
    {
        return Page::query()->create([
            'site_id' => (string) $site->id,
            'title' => Str::headline($slug),
            'slug' => $slug,
            'status' => 'draft',
        ]);
    }

    private function createBaselineRevision(Site $site, Page $page, int $version, string $componentType): PageRevision
    {
        return PageRevision::query()->create([
            'site_id' => (string) $site->id,
            'page_id' => $page->id,
            'version' => $version,
            'content_json' => [
                'schema_version' => 1,
                'editor_mode' => 'builder',
                'sections' => [
                    [
                        'type' => $componentType,
                        'props' => [
                            'style' => ['columns' => 4],
                        ],
                    ],
                ],
                'ai_generation' => [
                    'meta' => [
                        'family' => 'ecommerce',
                        'prompt_tags' => ['luxury'],
                    ],
                    'route' => ['template_key' => 'shop'],
                ],
            ],
            'created_by' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function createTargetRevision(Site $site, Page $page, int $version, array $content): PageRevision
    {
        return PageRevision::query()->create([
            'site_id' => (string) $site->id,
            'page_id' => $page->id,
            'version' => $version,
            'content_json' => $content,
            'created_by' => null,
        ]);
    }
}
