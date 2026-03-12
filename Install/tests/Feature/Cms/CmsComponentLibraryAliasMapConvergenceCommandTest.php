<?php

namespace Tests\Feature\Cms;

use App\Models\SectionLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** @group docs-sync */
class CmsComponentLibraryAliasMapConvergenceCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $exportDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportDir = storage_path('app/cms/component-library-alias-map-convergence-exports/tests');
        File::deleteDirectory($this->exportDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->exportDir);

        parent::tearDown();
    }

    public function test_it_reports_sorted_candidates_with_registry_diagnostics_and_blockers(): void
    {
        SectionLibrary::query()->create([
            'key' => 'webu_general_heading_01',
            'category' => 'general',
            'schema_json' => ['props' => []],
            'enabled' => true,
        ]);

        SectionLibrary::query()->create([
            'key' => 'webu_general_section_01',
            'category' => 'layout',
            'schema_json' => ['props' => []],
            'enabled' => true,
        ]);

        SectionLibrary::query()->create([
            'key' => 'webu_general_button_01',
            'category' => 'general',
            'schema_json' => ['props' => []],
            'enabled' => false,
        ]);

        $exitCode = Artisan::call('cms:component-library-alias-map-convergence', [
            '--source-key' => [
                'basic.heading',
                'layout.section',
                'basic.button',
                'ecom.productDetail',
            ],
            '--export-json' => true,
            '--output' => 'tests/h1-report.json',
            '--overwrite' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $reportPath = storage_path('app/cms/component-library-alias-map-convergence-exports/tests/h1-report.json');
        $this->assertFileExists($reportPath);
        $payload = json_decode((string) File::get($reportPath), true);
        $this->assertIsArray($payload);

        $this->assertSame(true, data_get($payload, 'canonical_registry_diagnostics.available'));
        $this->assertSame(1, (int) data_get($payload, 'summary.status_breakdown.ready_for_exact_patch_preview'));
        $this->assertSame(1, (int) data_get($payload, 'summary.status_breakdown.needs_review'));
        $this->assertSame(2, (int) data_get($payload, 'summary.status_breakdown.blocked'));

        $candidates = array_values(array_filter((array) data_get($payload, 'candidates'), 'is_array'));
        $this->assertCount(4, $candidates);

        $this->assertSame('basic.heading', data_get($candidates, '0.source_component_key'));
        $this->assertSame('ready_for_exact_patch_preview', data_get($candidates, '0.candidate_status'));

        $this->assertSame('layout.section', data_get($candidates, '1.source_component_key'));
        $this->assertSame('needs_review', data_get($candidates, '1.candidate_status'));
        $this->assertContains('layout_primitive_semantic_review', (array) data_get($candidates, '1.soft_blockers', []));

        $this->assertSame('basic.button', data_get($candidates, '2.source_component_key'));
        $this->assertContains('canonical_registry_keys_disabled', (array) data_get($candidates, '2.hard_blockers', []));

        $this->assertSame('ecom.productDetail', data_get($candidates, '3.source_component_key'));
        $this->assertContains('composite_alias_multiple_canonical_keys', (array) data_get($candidates, '3.hard_blockers', []));

        $this->assertSame(1, (int) data_get($payload, 'patch_preview.operations_count'));
        $this->assertSame('replace', data_get($payload, 'patch_preview.operations.0.op'));
        $this->assertSame('equivalent', data_get($payload, 'patch_preview.operations.0.from'));
        $this->assertSame('exact', data_get($payload, 'patch_preview.operations.0.value'));
    }

    public function test_it_exports_non_destructive_patch_preview_json_for_ready_candidates_only(): void
    {
        SectionLibrary::query()->create([
            'key' => 'webu_general_heading_01',
            'category' => 'general',
            'schema_json' => ['props' => []],
            'enabled' => true,
        ]);

        $exitCode = Artisan::call('cms:component-library-alias-map-convergence', [
            '--source-key' => ['basic.heading', 'ecom.productDetail'],
            '--export-patch-preview' => true,
            '--output' => 'tests/h1-patch-preview.json',
            '--overwrite' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $exportPath = storage_path('app/cms/component-library-alias-map-convergence-exports/tests/h1-patch-preview.json');
        $this->assertFileExists($exportPath);
        $exportPayload = json_decode((string) File::get($exportPath), true);
        $this->assertIsArray($exportPayload);
        $this->assertSame(true, data_get($exportPayload, 'non_destructive'));
        $this->assertSame(true, data_get($exportPayload, 'review_first'));
        $this->assertSame(false, data_get($exportPayload, 'registry_rewrites_included'));
        $this->assertSame(1, (int) data_get($exportPayload, 'operations_count'));
        $this->assertContains('basic.heading', (array) data_get($exportPayload, 'candidate_lists.ready_for_exact_patch_preview', []));
        $this->assertContains('ecom.productDetail', (array) data_get($exportPayload, 'candidate_lists.blocked', []));
    }

    public function test_it_fails_when_ready_candidate_assertion_threshold_is_not_met(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-convergence', [
            '--source-key' => ['basic.heading'],
            '--assert-min-ready' => 1,
            '--export-json' => true,
            '--output' => 'tests/h1-assert-threshold-fail.json',
            '--overwrite' => true,
        ]);

        $this->assertSame(1, $exitCode, Artisan::output());

        $reportPath = storage_path('app/cms/component-library-alias-map-convergence-exports/tests/h1-assert-threshold-fail.json');
        $this->assertFileExists($reportPath);

        $payload = json_decode((string) File::get($reportPath), true);
        $this->assertIsArray($payload);
        $this->assertSame(1, (int) data_get($payload, 'summary.status_breakdown.blocked'));
        $this->assertSame(0, (int) data_get($payload, 'summary.status_breakdown.ready_for_exact_patch_preview'));
    }

    public function test_it_rejects_single_output_path_when_both_export_modes_are_requested(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-convergence', [
            '--export-json' => true,
            '--export-patch-preview' => true,
            '--output' => 'tests/dual-export-output.json',
            '--overwrite' => true,
        ]);

        $this->assertSame(1, $exitCode, Artisan::output());
        $this->assertFileDoesNotExist(storage_path('app/cms/component-library-alias-map-convergence-exports/tests/dual-export-output.json'));
    }

    public function test_it_protects_existing_export_file_without_overwrite_flag(): void
    {
        $targetPath = storage_path('app/cms/component-library-alias-map-convergence-exports/tests/protected-patch-preview.json');
        File::ensureDirectoryExists(dirname($targetPath));
        File::put($targetPath, '{"keep":"me"}');

        $exitCode = Artisan::call('cms:component-library-alias-map-convergence', [
            '--source-key' => ['basic.heading'],
            '--export-patch-preview' => true,
            '--output' => 'tests/protected-patch-preview.json',
        ]);

        $this->assertSame(1, $exitCode, Artisan::output());
        $this->assertSame('{"keep":"me"}', (string) File::get($targetPath));
    }
}
