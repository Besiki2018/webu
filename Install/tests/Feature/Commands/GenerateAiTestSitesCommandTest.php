<?php

namespace Tests\Feature\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GenerateAiTestSitesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_dry_run_completes_without_writing_manifest(): void
    {
        User::factory()->create();
        $manifestPath = base_path('ai-generation-tests/manifest.json');
        if (is_file($manifestPath)) {
            @unlink($manifestPath);
        }

        $exitCode = Artisan::call('webu:generate-ai-test-sites', [
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Generating', $output);
        $this->assertStringContainsString('Done', $output);
        $this->assertStringContainsString('10 entries', $output);
        $this->assertFileDoesNotExist($manifestPath);
    }

    public function test_generates_manifest_when_run_with_one_scenario(): void
    {
        User::factory()->create();
        $manifestPath = base_path('ai-generation-tests/manifest.json');
        $outDir = base_path('ai-generation-tests');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }
        if (is_file($manifestPath)) {
            @unlink($manifestPath);
        }

        $exitCode = Artisan::call('webu:generate-ai-test-sites', [
            '--scenarios' => ['online_clothing_store'],
            '--repeat' => 1,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($manifestPath);
        $data = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('sites', $data);
        $this->assertCount(1, $data['sites']);
        $this->assertArrayHasKey('project_id', $data['sites'][0]);
        $this->assertArrayHasKey('storefront_base', $data['sites'][0]);
    }
}
