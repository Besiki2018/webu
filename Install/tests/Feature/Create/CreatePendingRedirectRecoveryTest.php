<?php

namespace Tests\Feature\Create;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreatePendingRedirectRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_create_page_exposes_recent_pending_redirect_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                'create_pending_redirect_url' => '/project/test-project/cms',
                'create_pending_redirect_at' => now()->toIso8601String(),
            ])
            ->get(route('create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Create')
                ->where('pendingRedirectUrl', '/project/test-project/cms')
            );
    }

    public function test_create_page_ignores_stale_pending_redirect_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                'create_pending_redirect_url' => '/project/stale-project/cms',
                'create_pending_redirect_at' => now()->subMinutes(10)->toIso8601String(),
            ])
            ->get(route('create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Create')
                ->where('pendingRedirectUrl', null)
            );
    }
}
