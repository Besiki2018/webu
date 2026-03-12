<?php

namespace Tests\Feature\Cms;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * new tasks.txt § 12 — Builder automated tests: schema validation, bindings validation,
 * drag-drop reorder produces correct patches, responsive overrides stored correctly,
 * history rollback (revisions), CMS edit reflects in preview (revision content).
 */
class BuilderSectionMutationsAndValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_drag_drop_reorder_produces_correct_patches(): void
    {
        [$owner, $site] = $this->makeOwnerAndSite();
        $home = $site->pages()->where('slug', 'home')->firstOrFail();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.builder.sections.mutate', ['site' => $site->id]), [
                'action' => 'add',
                'page_id' => $home->id,
                'section' => [
                    'type' => 'webu_general_heading_01',
                    'props' => ['headline' => 'First'],
                ],
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.builder.sections.mutate', ['site' => $site->id]), [
                'action' => 'add',
                'page_id' => $home->id,
                'section' => [
                    'type' => 'webu_general_text_01',
                    'props' => ['title' => 'Second'],
                ],
            ])
            ->assertOk();

        $revBefore = $home->revisions()->latest('version')->firstOrFail();
        $sections = $revBefore->content_json['sections'] ?? [];
        $firstIndex = collect($sections)->search(fn ($s) => ($s['props']['headline'] ?? '') === 'First');
        $this->assertNotFalse($firstIndex, 'First section should exist');

        $response = $this->actingAs($owner)
            ->postJson(route('panel.sites.builder.sections.mutate', ['site' => $site->id]), [
                'action' => 'reorder',
                'page_id' => $home->id,
                'from_index' => $firstIndex,
                'to_index' => count($sections) - 1,
            ])
            ->assertOk();

        $content = $response->json('revision.content_json');
        $this->assertIsArray($content['sections'] ?? null);
        $order = array_values(array_map(fn ($s) => $s['props']['headline'] ?? $s['props']['title'] ?? $s['type'], $content['sections']));
        $this->assertContains('First', $order);
        $this->assertContains('Second', $order);
        $lastLabel = $order[count($order) - 1] ?? '';
        $this->assertSame('First', $lastLabel, 'Reorder should move First to last position');
    }

    public function test_schema_validation_section_structure_persisted(): void
    {
        [$owner, $site] = $this->makeOwnerAndSite();
        $home = $site->pages()->where('slug', 'home')->firstOrFail();

        $response = $this->actingAs($owner)
            ->postJson(route('panel.sites.builder.sections.mutate', ['site' => $site->id]), [
                'action' => 'add',
                'page_id' => $home->id,
                'index' => 0,
                'section' => [
                    'type' => 'webu_ecom_product_grid_01',
                    'props' => [
                        'title' => 'Shop',
                        'products_per_page' => 12,
                    ],
                ],
            ])
            ->assertOk();

        $response->assertJsonPath('revision.content_json.sections.0.type', 'webu_ecom_product_grid_01');
        $response->assertJsonPath('revision.content_json.sections.0.props.title', 'Shop');
        $response->assertJsonPath('revision.content_json.sections.0.props.products_per_page', 12);
        $response->assertJsonStructure(['binding_validation']);
    }

    public function test_bindings_validation_returned_from_mutate_and_store_revision(): void
    {
        [$owner, $site] = $this->makeOwnerAndSite();
        $home = $site->pages()->where('slug', 'home')->firstOrFail();

        $mutateResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.builder.sections.mutate', ['site' => $site->id]), [
                'action' => 'add',
                'page_id' => $home->id,
                'section' => [
                    'type' => 'webu_general_heading_01',
                    'props' => ['headline' => 'Hello'],
                ],
            ])
            ->assertOk();

        $mutateResponse->assertJsonStructure(['binding_validation' => ['valid', 'warnings']]);
        $this->assertArrayHasKey('valid', $mutateResponse->json('binding_validation'));

        $contentJson = $mutateResponse->json('revision.content_json');
        $storeResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.revisions.store', ['site' => $site->id, 'page' => $home->id]), [
                'content_json' => $contentJson,
            ])
            ->assertCreated();

        $storeResponse->assertJsonStructure(['binding_validation' => ['valid', 'warnings']]);
    }

    public function test_responsive_overrides_stored_correctly_in_revision(): void
    {
        [$owner, $site] = $this->makeOwnerAndSite();
        $home = $site->pages()->where('slug', 'home')->firstOrFail();

        $contentJson = [
            'sections' => [
                [
                    'type' => 'webu_general_heading_01',
                    'props' => [
                        'headline' => 'Hero',
                        'style' => [
                            'desktop' => [
                                'paddingTop' => '2rem',
                                'paddingBottom' => '2rem',
                            ],
                            'tablet' => [
                                'paddingTop' => '1.5rem',
                                'paddingBottom' => '1.5rem',
                            ],
                            'mobile' => [
                                'paddingTop' => '1rem',
                                'paddingBottom' => '1rem',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.revisions.store', ['site' => $site->id, 'page' => $home->id]), [
                'content_json' => $contentJson,
            ])
            ->assertCreated();

        $revision = $response->json('revision');
        $this->assertIsArray($revision['content_json']['sections'] ?? null);
        $section = $revision['content_json']['sections'][0] ?? [];
        $style = $section['props']['style'] ?? [];
        $this->assertSame('2rem', $style['desktop']['paddingTop'] ?? null);
        $this->assertSame('1.5rem', $style['tablet']['paddingTop'] ?? null);
        $this->assertSame('1rem', $style['mobile']['paddingTop'] ?? null);
    }

    public function test_history_revisions_stored_and_retrievable(): void
    {
        [$owner, $site] = $this->makeOwnerAndSite();
        $home = $site->pages()->where('slug', 'home')->firstOrFail();

        $contentV1 = [
            'sections' => [
                ['type' => 'webu_general_heading_01', 'props' => ['headline' => 'Version One']],
            ],
        ];
        $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.revisions.store', ['site' => $site->id, 'page' => $home->id]), [
                'content_json' => $contentV1,
            ])
            ->assertCreated();

        $contentV2 = [
            'sections' => [
                ['type' => 'webu_general_heading_01', 'props' => ['headline' => 'Version Two']],
            ],
        ];
        $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.revisions.store', ['site' => $site->id, 'page' => $home->id]), [
                'content_json' => $contentV2,
            ])
            ->assertCreated();

        $revisions = $home->revisions()->orderBy('version')->get();
        $this->assertGreaterThanOrEqual(2, $revisions->count());

        $latest = $home->revisions()->latest('version')->firstOrFail();
        $this->assertSame('Version Two', $latest->content_json['sections'][0]['props']['headline'] ?? null);

        $previous = $home->revisions()->where('version', $latest->version - 1)->first();
        if ($previous) {
            $this->assertSame('Version One', $previous->content_json['sections'][0]['props']['headline'] ?? null);
        }
    }

    public function test_cms_edit_reflects_in_page_detail_revision_content(): void
    {
        [$owner, $site] = $this->makeOwnerAndSite();
        $home = $site->pages()->where('slug', 'home')->firstOrFail();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.builder.sections.mutate', ['site' => $site->id]), [
                'action' => 'add',
                'page_id' => $home->id,
                'section' => [
                    'type' => 'webu_general_heading_01',
                    'props' => ['headline' => 'Edited in builder'],
                ],
            ])
            ->assertOk();

        $response = $this->actingAs($owner)
            ->getJson(route('panel.sites.pages.show', ['site' => $site->id, 'page' => $home->id]));

        $response->assertOk();
        $latest = $response->json('latest_revision');
        $this->assertNotNull($latest);
        $sections = $latest['content_json']['sections'] ?? [];
        $headlines = array_filter(array_map(fn ($s) => $s['props']['headline'] ?? null, $sections));
        $this->assertContains('Edited in builder', $headlines);
    }

    private function makeOwnerAndSite(): array
    {
        $plan = Plan::factory()->withProjectLimit(10)->create();
        $owner = User::factory()->withPlan($plan)->create();

        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$owner, $site];
    }
}
