<?php

namespace Tests\Feature\Cms;

use App\Models\AiRevision;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\WebuCodex\CodebaseScanner;
use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiContentPatchFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_owner_can_apply_ai_content_patch_and_publish_revision(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();
        $page = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $beforeCount = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->count();

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'page_slug' => 'home',
                'mode' => 'replace',
                'patch' => [
                    'sections' => [
                        [
                            'type' => 'webu_general_heading_01',
                            'props' => [
                                'headline' => 'AI Updated Title',
                            ],
                        ],
                    ],
                ],
                'publish' => true,
                'instruction' => 'Update hero section title',
                'idempotency_key' => 'patch-001',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('replay', false);

        $revisionId = (int) $response->json('revision.id');
        $revision = PageRevision::query()->findOrFail($revisionId);

        $this->assertNotNull($revision->published_at);
        $this->assertSame(
            $beforeCount + 1,
            PageRevision::query()->where('site_id', $site->id)->where('page_id', $page->id)->count()
        );
        $this->assertDatabaseHas('operation_logs', [
            'project_id' => $project->id,
            'event' => 'ai_content_patch_applied',
        ]);
    }

    public function test_ai_content_patch_honors_idempotency_key_replay(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $first = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'page_slug' => 'home',
                'mode' => 'replace',
                'patch' => [
                    'sections' => [
                        ['type' => 'webu_general_heading_01', 'props' => ['headline' => 'First']],
                    ],
                ],
                'idempotency_key' => 'same-key',
            ])
            ->assertOk();

        $second = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'page_slug' => 'home',
                'mode' => 'replace',
                'patch' => [
                    'sections' => [
                        ['type' => 'webu_general_heading_01', 'props' => ['headline' => 'Should not create second revision']],
                    ],
                ],
                'idempotency_key' => 'same-key',
            ])
            ->assertOk()
            ->assertJsonPath('replay', true);

        $this->assertSame(
            (int) $first->json('revision.id'),
            (int) $second->json('revision.id')
        );
    }

    public function test_non_owner_cannot_apply_ai_content_patch(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'patch' => ['sections' => []],
            ])
            ->assertForbidden();
    }

    public function test_owner_can_apply_rfc6902_json_patch(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();
        $page = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'page_slug' => 'home',
                'patch_format' => 'rfc6902',
                'patch' => [
                    ['op' => 'replace', 'path' => '/sections', 'value' => [['type' => 'webu_general_text_01', 'props' => ['heading' => 'RFC 6902 added section']]]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $content = $response->json('revision.content_json');
        $this->assertIsArray($content);
        $sections = $content['sections'] ?? [];
        $this->assertNotEmpty($sections);
        $last = end($sections);
        $this->assertSame('webu_general_text_01', $last['type'] ?? null);
        $this->assertSame('RFC 6902 added section', $last['props']['heading'] ?? null);
    }

    public function test_apply_ai_patch_creates_ai_revision_record(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();
        $page = Page::query()->where('site_id', $site->id)->where('slug', 'home')->firstOrFail();

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'page_slug' => 'home',
                'mode' => 'replace',
                'patch' => [
                    'sections' => [
                        ['type' => 'webu_general_heading_01', 'props' => ['headline' => 'Saved for history']],
                    ],
                ],
                'instruction' => 'Set hero title',
            ])
            ->assertOk();

        $this->assertDatabaseHas('ai_revisions', [
            'site_id' => $site->id,
            'page_id' => $page->id,
        ]);
        $aiRev = AiRevision::query()->where('site_id', $site->id)->where('page_id', $page->id)->latest('id')->first();
        $this->assertNotNull($aiRev);
        $this->assertSame('Set hero title', $aiRev->prompt_text);
        $this->assertArrayHasKey('sections', $aiRev->snapshot_after);
        $this->assertNotEmpty($aiRev->snapshot_after['sections']);
    }

    public function test_ai_content_patch_regenerates_workspace_projection_and_invalidates_cached_scan(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $workspace = app(ProjectWorkspaceService::class);
        $scanner = app(CodebaseScanner::class);

        $workspace->initializeProjectCodebase($project);
        $scanner->writeIndex($project, $scanner->scan($project));

        $workspaceRoot = storage_path('workspaces/'.$project->id);
        $indexPath = $workspaceRoot.'/.webu/index.json';
        $this->assertFileExists($indexPath);

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'page_slug' => 'home',
                'mode' => 'replace',
                'patch' => [
                    'sections' => [
                        ['type' => 'webu_general_heading_01', 'localId' => 'hero-cms', 'props' => ['headline' => 'Workspace refreshed from CMS']],
                    ],
                ],
                'instruction' => 'Refresh workspace projection',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $pageContent = File::get($workspaceRoot.'/src/pages/home/Page.tsx');
        $this->assertStringContainsString('Workspace refreshed from CMS', $pageContent);
        $this->assertFileDoesNotExist($indexPath);
    }

    public function test_rollback_to_ai_revision_restores_layout(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();
        $page = Page::query()->where('site_id', $site->id)->where('slug', 'home')->firstOrFail();

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'page_slug' => 'home',
                'mode' => 'replace',
                'patch' => [
                    'sections' => [
                        ['type' => 'webu_general_heading_01', 'props' => ['headline' => 'Version A']],
                    ],
                ],
                'instruction' => 'First change',
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'page_slug' => 'home',
                'mode' => 'replace',
                'patch' => [
                    'sections' => [
                        ['type' => 'webu_general_heading_01', 'props' => ['headline' => 'Version B']],
                    ],
                ],
                'instruction' => 'Second change',
            ])
            ->assertOk();

        $aiRevFirst = AiRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->orderBy('id')
            ->first();
        $this->assertNotNull($aiRevFirst);
        $titleA = $aiRevFirst->snapshot_after['sections'][0]['props']['headline'] ?? null;
        $this->assertSame('Version A', $titleA);

        $rollbackResponse = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-revisions.rollback', $project), [
                'ai_revision_id' => $aiRevFirst->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $restoredContent = $rollbackResponse->json('revision.content_json');
        $this->assertIsArray($restoredContent);
        $sections = $restoredContent['sections'] ?? [];
        $this->assertNotEmpty($sections);
        $this->assertSame('Version A', $sections[0]['props']['headline'] ?? null);
    }

    /** Part 7: AI must produce JSON patch only; raw HTML in props is rejected. */
    public function test_ai_patch_rejects_raw_html_in_section_props(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'page_slug' => 'home',
                'patch' => [
                    'sections' => [
                        [
                            'type' => 'webu_general_text_01',
                            'props' => [
                                'title' => 'Safe title',
                                'body' => '<div class="custom"><p>Raw HTML block that should be rejected for security and design consistency.</p></div>',
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('errors.patch'));
    }

    /** Part 7: AI must produce JSON patch only; raw CSS in props is rejected. */
    public function test_ai_patch_rejects_raw_css_in_section_props(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-content-patch', $project), [
                'page_slug' => 'home',
                'patch' => [
                    'sections' => [
                        [
                            'type' => 'webu_general_heading_01',
                            'props' => [
                                'headline' => 'Title',
                                'custom_css' => '.hero {} .sub { font-size: 2rem; margin: 20px; color: red; }',
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }
}

