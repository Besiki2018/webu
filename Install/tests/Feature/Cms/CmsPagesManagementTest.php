<?php

namespace Tests\Feature\Cms;

use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsPagesManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_owner_can_delete_page_from_panel_endpoint(): void
    {
        [$owner, $site] = $this->createOwnerWithSite();
        $slug = 'about-'.strtolower(Str::random(6));

        $createResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.store', ['site' => $site->id]), [
                'title' => 'About',
                'slug' => $slug,
                'content_json' => [
                    'sections' => [],
                ],
            ])
            ->assertCreated();

        $pageId = (int) $createResponse->json('page.id');
        $this->assertGreaterThan(0, $pageId);

        $this->actingAs($owner)
            ->deleteJson(route('panel.sites.pages.destroy', ['site' => $site->id, 'page' => $pageId]))
            ->assertOk()
            ->assertJsonPath('message', 'Page deleted successfully.');

        $this->assertDatabaseMissing('pages', [
            'id' => $pageId,
            'site_id' => $site->id,
        ]);
    }

    public function test_delete_page_is_blocked_for_foreign_tenant(): void
    {
        [$ownerA, $siteA] = $this->createOwnerWithSite();
        [$ownerB, ] = $this->createOwnerWithSite();
        $slug = 'team-'.strtolower(Str::random(6));

        $createResponse = $this->actingAs($ownerA)
            ->postJson(route('panel.sites.pages.store', ['site' => $siteA->id]), [
                'title' => 'Team',
                'slug' => $slug,
                'content_json' => [
                    'sections' => [],
                ],
            ])
            ->assertCreated();

        $pageId = (int) $createResponse->json('page.id');

        $this->actingAs($ownerB)
            ->deleteJson(route('panel.sites.pages.destroy', ['site' => $siteA->id, 'page' => $pageId]))
            ->assertStatus(403);

        $this->assertDatabaseHas('pages', [
            'id' => $pageId,
            'site_id' => $siteA->id,
        ]);
    }

    private function createOwnerWithSite(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$owner, $site];
    }
}
