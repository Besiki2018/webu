<?php

namespace Tests\Integration;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SiteProvisioningService;
use App\Services\UnifiedAgent\UnifiedWebuSiteAgentOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedWebuSiteAgentOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_run_edit_rejects_empty_instruction(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $orchestrator = $this->app->make(UnifiedWebuSiteAgentOrchestrator::class);

        $result = $orchestrator->runEdit($project, [
            'instruction' => '',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Instruction is required.', $result['error']);
        $this->assertSame('empty_instruction', $result['error_code']);
    }

    public function test_run_edit_with_provisioned_site(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $this->app->make(SiteProvisioningService::class)->provisionForProject($project->fresh());

        $orchestrator = $this->app->make(UnifiedWebuSiteAgentOrchestrator::class);
        $result = $orchestrator->runEdit($project, [
            'instruction' => 'change header design',
            'page_slug' => 'home',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('diagnostic_log', $result);
    }
}
