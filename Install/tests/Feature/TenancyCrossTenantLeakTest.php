<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Models\PageSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-tenant data leak tests: tenant A must not read/update/delete tenant B's data.
 * - Repo get with wrong tenant returns null.
 * - Repo update with wrong tenant does not change the row.
 */
class TenancyCrossTenantLeakTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Website $websiteA;

    protected Website $websiteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
            'status' => 'active',
            'owner_user_id' => $this->admin->id,
            'created_by_user_id' => $this->admin->id,
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
            'status' => 'active',
            'owner_user_id' => $this->admin->id,
            'created_by_user_id' => $this->admin->id,
        ]);
        $this->websiteA = Website::create([
            'name' => 'Site A',
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->admin->id,
        ]);
        $this->websiteB = Website::create([
            'name' => 'Site B',
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_website_repository_get_returns_null_when_website_belongs_to_other_tenant(): void
    {
        $repo = app(\App\Repositories\TenantScoped\WebsiteRepository::class);

        $found = $repo->get($this->tenantA->id, $this->websiteB->id);

        $this->assertNull($found);
    }

    public function test_website_repository_get_returns_website_when_tenant_matches(): void
    {
        $repo = app(\App\Repositories\TenantScoped\WebsiteRepository::class);

        $found = $repo->get($this->tenantA->id, $this->websiteA->id);

        $this->assertNotNull($found);
        $this->assertSame($this->websiteA->id, $found->id);
    }

    public function test_section_repository_update_does_not_affect_other_tenant_section(): void
    {
        $pageB = WebsitePage::create([
            'website_id' => $this->websiteB->id,
            'tenant_id' => $this->tenantB->id,
            'slug' => 'home',
            'title' => 'Home',
            'order' => 0,
        ]);
        $sectionB = PageSection::create([
            'page_id' => $pageB->id,
            'tenant_id' => $this->tenantB->id,
            'website_id' => $this->websiteB->id,
            'section_type' => 'hero',
            'order' => 0,
        ]);

        $repo = app(\App\Repositories\TenantScoped\PageSectionRepository::class);
        $updated = $repo->update($this->tenantA->id, $this->websiteA->id, (int) $sectionB->id, ['settings_json' => ['title' => 'hacked']]);

        $this->assertFalse($updated);
        $sectionB->refresh();
        $this->assertNotEquals('hacked', $sectionB->settings_json['title'] ?? null);
    }
}
