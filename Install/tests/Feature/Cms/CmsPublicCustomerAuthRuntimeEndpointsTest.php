<?php

namespace Tests\Feature\Cms;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsPublicCustomerAuthRuntimeEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_public_customer_session_login_me_and_profile_update_json_endpoints_are_site_scoped(): void
    {
        [$site] = $this->createPublishedSite();

        $customer = User::factory()->create([
            'name' => 'Customer One',
            'email' => 'customer+'.Str::lower(Str::random(6)).'@example.test',
            'password' => Hash::make('secret-pass-123'),
        ]);

        $this->postJson(route('public.sites.customers.login', ['site' => $site->id]), [
            'email' => $customer->email,
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('reason', 'invalid_credentials');

        $this->postJson(route('public.sites.customers.login', ['site' => $site->id]), [
            'email' => $customer->email,
            'password' => 'secret-pass-123',
            'remember' => true,
        ])->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.email', $customer->email)
            ->assertJsonPath('auth.method', 'password')
            ->assertJsonPath('auth.session', 'web');

        $this->getJson(route('public.sites.customers.me', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('customer.id', $customer->id);

        $this->putJson(route('public.sites.customers.me.update', ['site' => $site->id]), [
            'name' => 'Updated Customer',
            'email' => 'updated+'.Str::lower(Str::random(6)).'@example.test',
        ])->assertOk()
            ->assertJsonPath('updated', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('customer.name', 'Updated Customer');

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Updated Customer',
        ]);
    }

    public function test_public_customer_register_and_logout_json_endpoints_work_for_site_scope(): void
    {
        [$site] = $this->createPublishedSite();

        $email = 'new-customer+'.Str::lower(Str::random(6)).'@example.test';
        $password = 'register-pass-123';

        $this->postJson(route('public.sites.customers.register', ['site' => $site->id]), [
            'name' => 'Storefront Customer',
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('registered', true)
            ->assertJsonPath('customer.email', $email)
            ->assertJsonPath('auth.method', 'register')
            ->assertJsonPath('auth.session', 'web');

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name' => 'Storefront Customer',
        ]);

        $this->postJson(route('public.sites.customers.logout', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('logged_out', true);

        $this->getJson(route('public.sites.customers.me', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('customer', null);
    }

    public function test_public_customer_me_update_requires_authentication(): void
    {
        [$site] = $this->createPublishedSite();

        $this->putJson(route('public.sites.customers.me.update', ['site' => $site->id]), [
            'name' => 'Guest Update',
        ])->assertStatus(401)
            ->assertJsonPath('reason', 'customer_auth_required');
    }

    public function test_public_customer_otp_and_social_auth_json_endpoints_honor_backend_settings_and_return_runtime_variants(): void
    {
        [$site] = $this->createPublishedSite();

        SystemSetting::set('passwordless_otp_enabled', false, 'boolean', 'auth');
        SystemSetting::set('google_login_enabled', false, 'boolean', 'auth');
        SystemSetting::set('facebook_login_enabled', false, 'boolean', 'auth');

        $this->postJson(route('public.sites.auth.otp.request', ['site' => $site->id]), [
            'phone' => '+995555111222',
        ])->assertStatus(403)
            ->assertJsonPath('reason', 'otp_disabled');

        $this->postJson(route('public.sites.auth.google', ['site' => $site->id]))
            ->assertStatus(403)
            ->assertJsonPath('reason', 'social_provider_disabled')
            ->assertJsonPath('provider', 'google');

        $this->postJson(route('public.sites.auth.facebook', ['site' => $site->id]))
            ->assertStatus(403)
            ->assertJsonPath('reason', 'social_provider_disabled')
            ->assertJsonPath('provider', 'facebook');

        SystemSetting::set('passwordless_otp_enabled', true, 'boolean', 'auth');
        SystemSetting::set('google_login_enabled', true, 'boolean', 'auth');
        SystemSetting::set('google_client_id', 'google-client', 'string', 'auth');
        SystemSetting::set('google_client_secret', 'google-secret', 'string', 'auth');
        SystemSetting::set('facebook_login_enabled', true, 'boolean', 'auth');
        SystemSetting::set('facebook_client_id', 'facebook-client', 'string', 'auth');
        SystemSetting::set('facebook_client_secret', 'facebook-secret', 'string', 'auth');

        $otpRequestResponse = $this->postJson(route('public.sites.auth.otp.request', ['site' => $site->id]), [
            'phone' => '+995555111222',
        ])->assertCreated()
            ->assertJsonPath('otp_request.status', 'pending')
            ->assertJsonPath('otp_request.phone', '+995555111222');

        $debugCode = (string) $otpRequestResponse->json('otp_request.debug_code');
        $this->assertNotSame('', $debugCode);

        $this->assertDatabaseHas('otp_requests', [
            'project_id' => $site->project_id,
            'phone' => '+995555111222',
            'status' => 'pending',
        ]);

        $this->postJson(route('public.sites.auth.otp.verify', ['site' => $site->id]), [
            'phone' => '+995555111222',
            'code' => '000000',
        ])->assertStatus(422)
            ->assertJsonPath('reason', 'otp_invalid_code');

        $this->postJson(route('public.sites.auth.otp.verify', ['site' => $site->id]), [
            'phone' => '+995555111222',
            'code' => $debugCode,
        ])->assertOk()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('auth.method', 'otp')
            ->assertJsonPath('auth.session', 'none')
            ->assertJsonPath('customer.phone', '+995555111222');

        $this->assertDatabaseHas('otp_requests', [
            'project_id' => $site->project_id,
            'phone' => '+995555111222',
            'status' => 'verified',
        ]);
        $this->assertDatabaseHas('customers', [
            'project_id' => $site->project_id,
            'phone' => '+995555111222',
            'status' => 'active',
        ]);

        $this->postJson(route('public.sites.auth.google', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('provider', 'google')
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('mode', 'redirect')
            ->assertJsonPath('redirect_url', '/auth/google')
            ->assertJsonPath('callback_url', '/auth/google/callback');

        $this->postJson(route('public.sites.auth.facebook', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('provider', 'facebook')
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('mode', 'redirect')
            ->assertJsonPath('redirect_url', '/auth/facebook')
            ->assertJsonPath('callback_url', '/auth/facebook/callback');
    }

    /**
     * @return array{0: \App\Models\Site, 1: \App\Models\User}
     */
    private function createPublishedSite(): array
    {
        $owner = User::factory()->create();
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.Str::upper(Str::random(4)),
            'slug' => 'tenant-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'created_by_user_id' => $owner->id,
        ]);

        $project = Project::factory()
            ->for($owner)
            ->published(Str::lower(Str::random(10)))
            ->create([
                'tenant_id' => $tenant->id,
                'published_visibility' => 'public',
            ]);

        $site = $project->site()->firstOrFail()->load('project');

        return [$site, $owner];
    }
}
