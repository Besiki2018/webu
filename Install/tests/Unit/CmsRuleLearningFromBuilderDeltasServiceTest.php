<?php

namespace Tests\Unit;

use App\Models\CmsBuilderDelta;
use App\Models\CmsLearnedRule;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\CmsRuleLearningFromBuilderDeltasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsRuleLearningFromBuilderDeltasServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_clusters_repeated_style_fixes_into_candidate_learned_rules(): void
    {
        [$project, $site] = $this->createPublishedProjectWithSite();

        $pageA = $this->createPage($site, 'shop-a');
        $baselineA = $this->createBaselineRevision($site, $pageA, 1, 'webu_product_grid_01', ['luxury', 'dark'], 'ecommerce');
        $targetA = $this->createTargetRevision($site, $pageA, 2, $baselineA->content_json);

        $pageB = $this->createPage($site, 'shop-b');
        $baselineB = $this->createBaselineRevision($site, $pageB, 1, 'webu_product_grid_01', ['dark', 'luxury'], 'ecommerce');
        $targetB = $this->createTargetRevision($site, $pageB, 2, $baselineB->content_json);

        $learnDate = Carbon::parse('2026-02-24 16:00:00');
        CmsBuilderDelta::query()->create([
            'site_id' => (string) $site->id,
            'project_id' => (string) $project->id,
            'page_id' => $pageA->id,
            'baseline_revision_id' => $baselineA->id,
            'target_revision_id' => $targetA->id,
            'generation_id' => 'gen_a',
            'locale' => 'ka',
            'captured_from' => 'panel_revision_save',
            'patch_ops' => [
                ['op' => 'replace', 'path' => '/sections/0/props/style/columns', 'value' => 3],
                ['op' => 'replace', 'path' => '/sections/0/props/headline', 'value' => 'Custom Headline'], // content edit -> skipped
            ],
            'patch_stats_json' => ['ops_count' => 2],
            'created_at' => $learnDate->copy()->subHour(),
            'updated_at' => $learnDate->copy()->subHour(),
        ]);
        CmsBuilderDelta::query()->create([
            'site_id' => (string) $site->id,
            'project_id' => (string) $project->id,
            'page_id' => $pageB->id,
            'baseline_revision_id' => $baselineB->id,
            'target_revision_id' => $targetB->id,
            'generation_id' => 'gen_b',
            'locale' => 'ka',
            'captured_from' => 'panel_revision_save',
            'patch_ops' => [
                ['op' => 'replace', 'path' => '/sections/0/props/style/columns', 'value' => 3],
                ['op' => 'replace', 'path' => '/sections/0/props/style/gap', 'value' => 28], // one-off -> below threshold
            ],
            'patch_stats_json' => ['ops_count' => 2],
            'created_at' => $learnDate->copy()->subMinutes(30),
            'updated_at' => $learnDate->copy()->subMinutes(30),
        ]);

        $result = app(CmsRuleLearningFromBuilderDeltasService::class)->learnCandidateRules(
            '2000-01-01 00:00:00',
            '2100-01-01 00:00:00',
            $site,
            2
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['source_deltas']);
        $this->assertSame(3, $result['eligible_ops']); // two columns + one gap (headline skipped)
        $this->assertSame(2, $result['clusters']); // columns + gap
        $this->assertSame(1, $result['qualifying_clusters']);
        $this->assertSame(1, $result['upserted']);

        /** @var CmsLearnedRule $rule */
        $rule = CmsLearnedRule::query()->firstOrFail();
        $this->assertSame('tenant', $rule->scope);
        $this->assertSame((string) $site->id, $rule->site_id);
        $this->assertSame((string) $project->id, $rule->project_id);
        $this->assertSame('candidate', $rule->status);
        $this->assertFalse((bool) $rule->active);
        $this->assertSame('builder_delta_cluster', $rule->source);
        $this->assertSame(2, (int) $rule->sample_size);
        $this->assertSame(2, (int) $rule->delta_count);
        $this->assertGreaterThan(0.0, (float) $rule->confidence);

        $this->assertSame('ecommerce', data_get($rule->conditions_json, 'store_type'));
        $this->assertSame('webu_product_grid_01', data_get($rule->conditions_json, 'component_type'));
        $this->assertSame(['dark', 'luxury'], data_get($rule->conditions_json, 'prompt_intent_tags'));
        $this->assertSame('shop', data_get($rule->conditions_json, 'page_template_key'));

        $this->assertSame('json_patch_template', data_get($rule->patch_json, 'format'));
        $this->assertSame('replace', data_get($rule->patch_json, 'op'));
        $this->assertSame('/sections/*/props/style/columns', data_get($rule->patch_json, 'path_pattern'));
        $this->assertSame('/props/style/columns', data_get($rule->patch_json, 'path_suffix'));
        $this->assertSame(3, data_get($rule->patch_json, 'value'));

        $this->assertSame(2, (int) data_get($rule->evidence_json, 'delta_count'));
        $this->assertSame(2, (int) data_get($rule->evidence_json, 'unique_pages'));
        $this->assertContains('/sections/0/props/style/columns', data_get($rule->evidence_json, 'example_paths', []));
        $this->assertNotContains('/sections/0/props/headline', data_get($rule->evidence_json, 'example_paths', []));
    }

    public function test_it_upserts_existing_candidate_rule_deterministically_without_duplicates(): void
    {
        [$project, $site] = $this->createPublishedProjectWithSite();
        $page = $this->createPage($site, 'shop');
        $baseline = $this->createBaselineRevision($site, $page, 1, 'webu_button_01', ['minimal'], 'ecommerce');
        $target = $this->createTargetRevision($site, $page, 2, $baseline->content_json);

        $when = Carbon::parse('2026-02-24 18:00:00');
        foreach ([1, 2] as $i) {
            CmsBuilderDelta::query()->create([
                'site_id' => (string) $site->id,
                'project_id' => (string) $project->id,
                'page_id' => $page->id,
                'baseline_revision_id' => $baseline->id,
                'target_revision_id' => $target->id,
                'generation_id' => 'gen_'.$i,
                'locale' => 'ka',
                'captured_from' => 'panel_revision_save',
                'patch_ops' => [
                    ['op' => 'replace', 'path' => '/sections/0/props/style/border_radius', 'value' => 999],
                ],
                'patch_stats_json' => ['ops_count' => 1],
                'created_at' => $when->copy()->subMinutes(10 - $i),
                'updated_at' => $when->copy()->subMinutes(10 - $i),
            ]);
        }

        $service = app(CmsRuleLearningFromBuilderDeltasService::class);
        $first = $service->learnCandidateRules('2000-01-01 00:00:00', '2100-01-01 00:00:00', $site, 2);
        $second = $service->learnCandidateRules('2000-01-01 00:00:00', '2100-01-01 00:00:00', $site, 2);

        $this->assertSame(1, $first['upserted']);
        $this->assertSame(1, $second['upserted']);
        $this->assertSame(1, CmsLearnedRule::query()->count());

        /** @var CmsLearnedRule $rule */
        $rule = CmsLearnedRule::query()->firstOrFail();
        $this->assertSame(2, (int) $rule->sample_size);
        $this->assertSame(2, (int) $rule->delta_count);
        $this->assertSame('webu_button_01', data_get($rule->conditions_json, 'component_type'));
        $this->assertSame('minimal', data_get($rule->conditions_json, 'prompt_intent_tags.0'));
        $this->assertSame(999, data_get($rule->patch_json, 'value'));
        $this->assertNotNull($rule->last_learned_at);
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

    private function createBaselineRevision(Site $site, Page $page, int $version, string $componentType, array $promptTags, string $family): PageRevision
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
                            'style' => [
                                'columns' => 4,
                                'gap' => 20,
                                'border_radius' => 8,
                            ],
                            'headline' => 'AI default headline',
                        ],
                    ],
                ],
                'ai_generation' => [
                    'schema_version' => 1,
                    'meta' => [
                        'family' => $family,
                        'prompt_tags' => $promptTags,
                    ],
                    'route' => [
                        'template_key' => 'shop',
                    ],
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
