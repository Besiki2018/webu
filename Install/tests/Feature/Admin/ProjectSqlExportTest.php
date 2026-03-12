<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectSqlExport;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectSqlExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_admin_can_generate_project_sql_export_and_validate_dry_run(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create([
            'name' => 'Export Ready Project',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.projects.sql-export', $project))
            ->assertOk()
            ->assertJsonPath('success', true);

        $export = ProjectSqlExport::query()
            ->where('project_id', $project->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(ProjectSqlExport::STATUS_COMPLETED, $export->status);
        Storage::disk('local')->assertExists((string) $export->sql_path);
        Storage::disk('local')->assertExists((string) $export->manifest_path);

        $this->actingAs($admin)
            ->postJson(route('admin.projects.sql-restore-dry-run', $project), [
                'export_id' => $export->id,
            ])
            ->assertOk()
            ->assertJsonPath('valid', true);
    }

    public function test_project_sql_export_command_generates_package(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create();

        $this->artisan('project:sql-export', [
            'project' => $project->id,
            '--disk' => 'local',
            '--path' => 'project-sql-exports',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('project_sql_exports', [
            'project_id' => $project->id,
            'status' => ProjectSqlExport::STATUS_COMPLETED,
        ]);
    }

    public function test_non_admin_cannot_trigger_project_sql_export_routes(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($user)
            ->postJson(route('admin.projects.sql-export', $project))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('admin.projects.sql-restore-dry-run', $project))
            ->assertForbidden();
    }
}

