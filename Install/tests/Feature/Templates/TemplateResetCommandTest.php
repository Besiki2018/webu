<?php

namespace Tests\Feature\Templates;

use App\Models\OperationLog;
use App\Models\Project;
use App\Models\SectionLibrary;
use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TemplateResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_command_wipes_templates_sections_and_sites_and_keeps_users_projects(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Template::factory()->create([
            'slug' => 'legacy-template',
            'name' => 'Legacy Template',
        ]);

        SectionLibrary::query()->create([
            'key' => 'legacy_section',
            'category' => 'legacy',
            'schema_json' => ['foo' => 'bar'],
            'enabled' => true,
        ]);

        $project->site()->firstOrFail();

        $this->assertSame(1, Template::query()->count());
        $this->assertSame(1, SectionLibrary::query()->count());
        $this->assertSame(1, Site::query()->count());
        $this->assertGreaterThanOrEqual(1, \App\Models\Page::query()->count());

        $exitCode = Artisan::call('templates:reset', [
            '--force' => true,
            '--skip-backup' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertSame(0, Template::query()->count());
        $this->assertSame(0, SectionLibrary::query()->count());
        $this->assertSame(0, Site::query()->count());
        $this->assertSame(0, \App\Models\Page::query()->count());

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Project::query()->count());

        $this->assertDatabaseHas('operation_logs', [
            'channel' => OperationLog::CHANNEL_SYSTEM,
            'event' => 'templates_reset',
            'status' => OperationLog::STATUS_SUCCESS,
        ]);
    }

    public function test_reset_command_can_preserve_sites_when_without_sites_option_is_set(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Template::factory()->create(['slug' => 'legacy-template']);

        SectionLibrary::query()->create([
            'key' => 'legacy_section',
            'category' => 'legacy',
            'schema_json' => ['foo' => 'bar'],
            'enabled' => true,
        ]);

        $project->site()->firstOrFail();

        $exitCode = Artisan::call('templates:reset', [
            '--force' => true,
            '--skip-backup' => true,
            '--without-sites' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertSame(0, Template::query()->count());
        $this->assertSame(0, SectionLibrary::query()->count());
        $this->assertSame(1, Site::query()->count());
        $this->assertGreaterThanOrEqual(1, \App\Models\Page::query()->count());
    }
}
