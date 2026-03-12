<?php

namespace Tests\Feature\Cms;

use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsMenuBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_menu_builder_crud_flow_works_for_project_owner(): void
    {
        [$owner, $site] = $this->createOwnerWithSite();

        $this->actingAs($owner)
            ->getJson(route('panel.sites.menus.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonFragment(['key' => 'header'])
            ->assertJsonFragment(['key' => 'footer']);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.menus.store', ['site' => $site->id]), [
                'key' => 'main-navigation',
            ])
            ->assertCreated()
            ->assertJsonPath('menu.key', 'main-navigation');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.menus.update', ['site' => $site->id, 'key' => 'main-navigation']), [
                'items_json' => [
                    [
                        'label' => 'Shop',
                        'url' => '/shop',
                        'slug' => 'shop',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('menu.key', 'main-navigation')
            ->assertJsonPath('menu.items_json.0.label', 'Shop');

        $this->actingAs($owner)
            ->deleteJson(route('panel.sites.menus.destroy', ['site' => $site->id, 'key' => 'main-navigation']))
            ->assertOk()
            ->assertJsonPath('deleted_key', 'main-navigation');

        $menusAfterDelete = collect(
            $this->actingAs($owner)
                ->getJson(route('panel.sites.menus.index', ['site' => $site->id]))
                ->assertOk()
                ->json('menus')
        );

        $this->assertFalse($menusAfterDelete->contains(fn (array $menu): bool => ($menu['key'] ?? null) === 'main-navigation'));
    }

    public function test_system_menus_cannot_be_deleted(): void
    {
        [$owner, $site] = $this->createOwnerWithSite();

        $this->actingAs($owner)
            ->deleteJson(route('panel.sites.menus.destroy', ['site' => $site->id, 'key' => 'header']))
            ->assertStatus(422)
            ->assertJsonPath('error', 'System menu cannot be deleted.');
    }

    public function test_menu_builder_persists_nested_page_links(): void
    {
        [$owner, $site] = $this->createOwnerWithSite();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.menus.store', ['site' => $site->id]), [
                'key' => 'main-navigation',
            ])
            ->assertCreated();

        $this->actingAs($owner)
            ->putJson(route('panel.sites.menus.update', ['site' => $site->id, 'key' => 'main-navigation']), [
                'items_json' => [
                    [
                        'id' => 'root-home',
                        'label' => 'Home',
                        'url' => '/',
                        'slug' => 'home',
                        'source' => 'page',
                        'page_id' => 11,
                        'parent_id' => null,
                    ],
                    [
                        'id' => 'child-shop',
                        'label' => 'Shop',
                        'url' => '/shop',
                        'slug' => 'shop',
                        'source' => 'page',
                        'page_id' => 22,
                        'parent_id' => 'root-home',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('menu.items_json.0.id', 'root-home')
            ->assertJsonPath('menu.items_json.1.parent_id', 'root-home')
            ->assertJsonPath('menu.items_json.1.source', 'page')
            ->assertJsonPath('menu.items_json.1.page_id', 22);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.menus.show', ['site' => $site->id, 'key' => 'main-navigation']))
            ->assertOk()
            ->assertJsonPath('items_json.0.id', 'root-home')
            ->assertJsonPath('items_json.1.parent_id', 'root-home')
            ->assertJsonPath('items_json.1.source', 'page')
            ->assertJsonPath('items_json.1.page_id', 22);
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
