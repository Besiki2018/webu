<?php

namespace Tests\Feature\Cms;

use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsBlogPostsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_owner_can_create_update_and_delete_blog_post(): void
    {
        [$owner, $site] = $this->createOwnerWithSite();
        $slug = 'post-'.strtolower(Str::random(6));

        $createResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.blog-posts.store', ['site' => $site->id]), [
                'title' => 'My First Post',
                'slug' => $slug,
                'excerpt' => 'Short summary',
                'content' => 'Long content body',
                'status' => 'draft',
            ])
            ->assertCreated()
            ->assertJsonPath('post.slug', $slug);

        $postId = (int) $createResponse->json('post.id');
        $this->assertGreaterThan(0, $postId);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.blog-posts.update', ['site' => $site->id, 'blogPost' => $postId]), [
                'title' => 'Updated Post',
                'slug' => $slug,
                'excerpt' => 'Updated summary',
                'content' => 'Updated content',
                'status' => 'published',
            ])
            ->assertOk()
            ->assertJsonPath('post.title', 'Updated Post')
            ->assertJsonPath('post.status', 'published');

        $this->actingAs($owner)
            ->deleteJson(route('panel.sites.blog-posts.destroy', ['site' => $site->id, 'blogPost' => $postId]))
            ->assertOk()
            ->assertJsonPath('message', 'Blog post deleted successfully.');

        $this->assertDatabaseMissing('blog_posts', [
            'id' => $postId,
            'site_id' => $site->id,
        ]);
    }

    public function test_blog_post_update_is_blocked_for_foreign_tenant(): void
    {
        [$ownerA, $siteA] = $this->createOwnerWithSite();
        [$ownerB] = $this->createOwnerWithSite();
        $slug = 'post-'.strtolower(Str::random(6));

        $createResponse = $this->actingAs($ownerA)
            ->postJson(route('panel.sites.blog-posts.store', ['site' => $siteA->id]), [
                'title' => 'Tenant A Post',
                'slug' => $slug,
                'status' => 'draft',
            ])
            ->assertCreated();

        $postId = (int) $createResponse->json('post.id');

        $this->actingAs($ownerB)
            ->putJson(route('panel.sites.blog-posts.update', ['site' => $siteA->id, 'blogPost' => $postId]), [
                'title' => 'Hacked',
                'slug' => $slug,
                'status' => 'draft',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('blog_posts', [
            'id' => $postId,
            'site_id' => $siteA->id,
            'title' => 'Tenant A Post',
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

