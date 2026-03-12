<?php

namespace Tests\Feature\Templates;

use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TemplateDuplicateCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_cleanup_command_deletes_duplicate_suffix_when_source_matches_base(): void
    {
        $planA = Plan::factory()->create();
        $planB = Plan::factory()->create();

        $base = Template::factory()->create([
            'slug' => 'demo-template',
            'name' => 'Demo Template',
            'metadata' => [
                'source_root' => 'directory:starters/demo-template',
            ],
        ]);
        $base->plans()->sync([$planA->id]);

        $duplicate = Template::factory()->create([
            'slug' => 'demo-template-2',
            'name' => 'Demo Template 2',
            'metadata' => [
                'source_root' => 'directory:starters/demo-template',
            ],
        ]);
        $duplicate->plans()->sync([$planB->id]);

        $exitCode = Artisan::call('templates:cleanup-duplicates');
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertNotNull(Template::query()->where('slug', 'demo-template')->first());
        $this->assertNull(Template::query()->where('slug', 'demo-template-2')->first());

        $planIds = Template::query()
            ->where('slug', 'demo-template')
            ->firstOrFail()
            ->plans()
            ->pluck('plans.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertEqualsCanonicalizing([$planA->id, $planB->id], $planIds);
    }

    public function test_cleanup_command_skips_duplicate_suffix_when_source_does_not_match(): void
    {
        Template::factory()->create([
            'slug' => 'demo-template',
            'name' => 'Demo Template',
            'metadata' => [
                'source_root' => 'directory:starters/demo-template-a',
            ],
        ]);

        Template::factory()->create([
            'slug' => 'demo-template-2',
            'name' => 'Demo Template 2',
            'metadata' => [
                'source_root' => 'directory:starters/demo-template-b',
            ],
        ]);

        $exitCode = Artisan::call('templates:cleanup-duplicates');
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertNotNull(Template::query()->where('slug', 'demo-template')->first());
        $this->assertNotNull(Template::query()->where('slug', 'demo-template-2')->first());
    }

    public function test_cleanup_command_handles_legacy_directory_placeholder_source(): void
    {
        Template::factory()->create([
            'slug' => 'legacy-demo',
            'name' => 'Legacy Demo',
            'metadata' => [
                'source_root' => 'directory:legacy-demo',
            ],
        ]);

        Template::factory()->create([
            'slug' => 'legacy-demo-2',
            'name' => 'Legacy Demo 2',
            'metadata' => [
                'source_root' => 'directory',
            ],
        ]);

        $exitCode = Artisan::call('templates:cleanup-duplicates');
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertNotNull(Template::query()->where('slug', 'legacy-demo')->first());
        $this->assertNull(Template::query()->where('slug', 'legacy-demo-2')->first());
    }

    public function test_cleanup_command_handles_legacy_slug_only_source_root(): void
    {
        Template::factory()->create([
            'slug' => 'legacy-slug',
            'name' => 'Legacy Slug',
            'metadata' => [
                'source_root' => 'legacy-slug',
            ],
        ]);

        Template::factory()->create([
            'slug' => 'legacy-slug-2',
            'name' => 'Legacy Slug 2',
            'metadata' => [
                'source_root' => 'directory',
            ],
        ]);

        $exitCode = Artisan::call('templates:cleanup-duplicates');
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertNotNull(Template::query()->where('slug', 'legacy-slug')->first());
        $this->assertNull(Template::query()->where('slug', 'legacy-slug-2')->first());
    }

    public function test_cleanup_command_handles_humanized_legacy_source_root(): void
    {
        Template::factory()->create([
            'slug' => 'react-template',
            'name' => 'React Template',
            'metadata' => [
                'source_root' => 'React Template',
            ],
        ]);

        Template::factory()->create([
            'slug' => 'react-template-2',
            'name' => 'React Template 2',
            'metadata' => [
                'source_root' => 'directory',
            ],
        ]);

        $exitCode = Artisan::call('templates:cleanup-duplicates');
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertNotNull(Template::query()->where('slug', 'react-template')->first());
        $this->assertNull(Template::query()->where('slug', 'react-template-2')->first());
    }

    public function test_cleanup_command_handles_directory_source_with_extra_path_prefix(): void
    {
        Template::factory()->create([
            'slug' => 'prefix-compat',
            'name' => 'Prefix Compat',
            'metadata' => [
                'source_root' => 'directory:starters/prefix-compat',
            ],
        ]);

        Template::factory()->create([
            'slug' => 'prefix-compat-2',
            'name' => 'Prefix Compat 2',
            'metadata' => [
                'source_root' => 'directory:@scope/starters/prefix-compat',
            ],
        ]);

        $exitCode = Artisan::call('templates:cleanup-duplicates');
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertNotNull(Template::query()->where('slug', 'prefix-compat')->first());
        $this->assertNull(Template::query()->where('slug', 'prefix-compat-2')->first());
    }
}
