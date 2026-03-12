<?php

namespace Tests\Feature\Domain;

use App\Models\Plan;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DomainSettingService;
use App\Services\DomainVerificationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DomainAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
        SystemSetting::set('domain_enable_subdomains', true, 'boolean', 'domains');
        SystemSetting::set('domain_enable_custom_domains', true, 'boolean', 'domains');
        SystemSetting::set('domain_base_domain', 'platform.example.com', 'string', 'domains');
    }

    public function test_apex_domain_instructions_include_a_and_www_records(): void
    {
        $project = Project::factory()->create([
            'custom_domain' => 'example.com',
        ]);

        $instructions = app(DomainVerificationService::class)->getVerificationInstructions($project);

        $this->assertSame('dns', $instructions['method']);
        $this->assertSame('A', $instructions['record_type']);
        $this->assertIsArray($instructions['records'] ?? null);
        $this->assertCount(2, $instructions['records']);
        $this->assertSame('A', $instructions['records'][0]['record_type']);
        $this->assertSame('@', $instructions['records'][0]['host']);
        $this->assertSame('CNAME', $instructions['records'][1]['record_type']);
        $this->assertSame('www', $instructions['records'][1]['host']);
    }

    public function test_domain_verify_marks_project_as_verified_when_dns_matches(): void
    {
        $project = Project::factory()->create([
            'custom_domain' => 'www.verify-me.test',
            'custom_domain_verified' => false,
            'custom_domain_ssl_status' => null,
        ]);

        $service = new class(app(DomainSettingService::class), app(NotificationService::class)) extends DomainVerificationService
        {
            protected function checkDnsCnameRecord(string $domain, string $target): bool
            {
                return true;
            }

            protected function checkDnsARecordToBaseDomain(string $domain, string $baseDomain): bool
            {
                return false;
            }
        };

        $result = $service->verify($project);

        $this->assertTrue((bool) ($result['success'] ?? false));

        $project->refresh();
        $this->assertTrue((bool) $project->custom_domain_verified);
        $this->assertSame('pending', $project->custom_domain_ssl_status);
        $this->assertSame(0, (int) ($project->custom_domain_ssl_attempts ?? 0));
    }

    public function test_ssl_provisioning_schedules_retry_and_stores_error_reason(): void
    {
        config()->set('domain.ssl.max_attempts', 5);
        config()->set('domain.ssl.retry_backoff_minutes', 10);

        $project = Project::factory()->create([
            'custom_domain' => 'www.retry-me.test',
            'custom_domain_verified' => true,
            'custom_domain_ssl_status' => 'pending',
            'custom_domain_ssl_attempts' => 0,
            'custom_domain_ssl_next_retry_at' => null,
        ]);

        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'certbot: DNS challenge failed',
                exitCode: 1,
            ),
        ]);

        $this->artisan('domain:provision-ssl')
            ->assertExitCode(0);

        $project->refresh();

        $this->assertSame('pending', $project->custom_domain_ssl_status);
        $this->assertSame(1, (int) $project->custom_domain_ssl_attempts);
        $this->assertNotNull($project->custom_domain_ssl_next_retry_at);
        $this->assertStringContainsString(
            'certbot',
            (string) $project->custom_domain_ssl_last_error
        );
    }

    public function test_ssl_provisioning_marks_failed_after_max_attempts(): void
    {
        config()->set('domain.ssl.max_attempts', 2);
        config()->set('domain.ssl.retry_backoff_minutes', 1);

        $project = Project::factory()->create([
            'custom_domain' => 'www.final-fail.test',
            'custom_domain_verified' => true,
            'custom_domain_ssl_status' => 'pending',
            'custom_domain_ssl_attempts' => 1,
            'custom_domain_ssl_next_retry_at' => null,
        ]);

        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'certbot: final failure',
                exitCode: 1,
            ),
        ]);

        $this->artisan('domain:provision-ssl')
            ->assertExitCode(1);

        $project->refresh();

        $this->assertSame('failed', $project->custom_domain_ssl_status);
        $this->assertSame(2, (int) $project->custom_domain_ssl_attempts);
        $this->assertNull($project->custom_domain_ssl_next_retry_at);
        $this->assertStringContainsString(
            'final failure',
            (string) $project->custom_domain_ssl_last_error
        );
    }

    public function test_publish_and_unpublish_flush_published_cache_directory(): void
    {
        $plan = Plan::factory()->withSubdomains()->create([
            'enable_custom_domains' => true,
        ]);
        $user = User::factory()->create([
            'plan_id' => $plan->id,
        ]);
        $project = Project::factory()->for($user)->create();

        Storage::disk('local')->put("published/{$project->id}/index.html", '<html>cached</html>');
        $this->assertTrue(Storage::disk('local')->exists("published/{$project->id}/index.html"));

        $this->actingAs($user)
            ->postJson("/project/{$project->id}/publish", [
                'subdomain' => 'cache-flush-domain',
                'visibility' => 'public',
            ])
            ->assertOk();

        $project->refresh();
        $reservedSubdomain = $project->subdomain;
        $this->assertSame('cache-flush-domain', $reservedSubdomain);
        $this->assertFalse(Storage::disk('local')->exists("published/{$project->id}/index.html"));

        Storage::disk('local')->put("published/{$project->id}/index.html", '<html>cached-again</html>');
        $this->assertTrue(Storage::disk('local')->exists("published/{$project->id}/index.html"));

        $this->actingAs($user)
            ->postJson("/project/{$project->id}/unpublish")
            ->assertOk();

        $project->refresh();
        $this->assertSame($reservedSubdomain, $project->subdomain);
        $this->assertNull($project->published_at);
        $this->assertFalse(Storage::disk('local')->exists("published/{$project->id}/index.html"));
    }

    public function test_publish_without_subdomain_uses_project_fallback_subdomain(): void
    {
        $plan = Plan::factory()->withSubdomains()->create([
            'enable_custom_domains' => true,
        ]);
        $user = User::factory()->create([
            'plan_id' => $plan->id,
        ]);
        $project = Project::factory()->for($user)->create([
            'subdomain' => null,
        ]);

        $this->assertNotNull($project->fresh()->subdomain);
        $fallback = (string) $project->fresh()->subdomain;

        $this->actingAs($user)
            ->postJson("/project/{$project->id}/publish", [
                'visibility' => 'public',
            ])
            ->assertOk();

        $project->refresh();
        $this->assertSame($fallback, $project->subdomain);
        $this->assertNotNull($project->published_at);
    }

    public function test_repeated_publish_requests_keep_project_state_consistent(): void
    {
        $plan = Plan::factory()->withSubdomains()->create([
            'enable_custom_domains' => true,
        ]);

        $user = User::factory()->create([
            'plan_id' => $plan->id,
        ]);

        $project = Project::factory()->for($user)->create();

        for ($attempt = 1; $attempt <= 15; $attempt++) {
            $this->actingAs($user)
                ->postJson("/project/{$project->id}/publish", [
                    'subdomain' => 'stress-publish-domain',
                    'visibility' => 'public',
                ])
                ->assertOk();
        }

        $project->refresh();
        $this->assertSame('stress-publish-domain', $project->subdomain);
        $this->assertNotNull($project->published_at);
    }
}
