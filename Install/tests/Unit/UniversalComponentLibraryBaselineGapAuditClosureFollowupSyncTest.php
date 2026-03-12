<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryBaselineGapAuditClosureFollowupSyncTest extends TestCase
{
    public function test_baseline_gap_audits_include_followup_closure_links_for_2026_02_26_pass(): void
    {
        $qaDir = base_path('docs/qa');
        $unitDir = base_path('tests/Unit');

        $qaFiles = collect(File::files($qaDir))->map->getFilename();
        $unitFiles = collect(File::files($unitDir))->map->getFilename();

        $baselineFiles = $qaFiles
            ->filter(static fn (string $name): bool => str_contains($name, 'BASELINE_GAP_AUDIT') && str_ends_with($name, '_2026_02_25.md'))
            ->values();

        $this->assertCount(18, $baselineFiles);

        $closureDocsById = [];
        foreach ($qaFiles as $name) {
            if (!str_contains($name, 'CLOSURE_AUDIT') || !str_ends_with($name, '_2026_02_26.md')) {
                continue;
            }

            if (preg_match('/_(RS_\d{2}_\d{2}|ECM_\d{2})_2026_02_26\.md$/', $name, $matches) === 1) {
                $closureDocsById[$matches[1]] = $name;
            }
        }

        $closureTestsById = [];
        foreach ($unitFiles as $name) {
            if (!str_ends_with($name, 'ClosureAuditSyncTest.php')) {
                continue;
            }

            if (preg_match('/Rs(\d{4})/i', $name, $matches) === 1) {
                $digits = $matches[1];
                $closureTestsById['RS_'.substr($digits, 0, 2).'_'.substr($digits, 2)] = $name;
                continue;
            }

            if (preg_match('/Ecm(\d{2})/i', $name, $matches) === 1) {
                $closureTestsById['ECM_'.$matches[1]] = $name;
            }
        }

        foreach ($baselineFiles as $baselineName) {
            $this->assertMatchesRegularExpression('/_(RS_\d{2}_\d{2}|ECM_\d{2})_2026_02_25\.md$/', $baselineName);
            preg_match('/_(RS_\d{2}_\d{2}|ECM_\d{2})_2026_02_25\.md$/', $baselineName, $idMatches);
            $id = (string) ($idMatches[1] ?? '');

            $this->assertArrayHasKey($id, $closureDocsById, "Missing closure doc for {$baselineName}");
            $this->assertArrayHasKey($id, $closureTestsById, "Missing closure test for {$baselineName}");

            $baselineDoc = File::get($qaDir.'/'.$baselineName);
            $closureDocPath = 'Install/docs/qa/'.$closureDocsById[$id];
            $closureTestPath = 'Install/tests/Unit/'.$closureTestsById[$id];

            $this->assertStringContainsString('## Follow-up Closure Note (2026-02-26)', $baselineDoc);
            $this->assertStringContainsString($closureDocPath, $baselineDoc);
            $this->assertStringContainsString($closureTestPath, $baselineDoc);
        }
    }

    public function test_legacy_and_governance_reconciliation_docs_mark_in_progress_mentions_as_historical_baseline(): void
    {
        $paths = [
            base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_BUILDER_CORE_REGISTRY_DYNAMIC_BINDING_COMPONENT_REQUIREMENTS_RECONCILIATION_AUDIT_AR_02_2026_02_25.md'),
            base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_ACCEPTANCE_DELIVERABLES_RECONCILIATION_AUDIT_AR_04_2026_02_25.md'),
            base_path('docs/qa/PROGRAM_IMPLEMENTATION_RULES_DEDUP_MAPPING_DELIVERY_MILESTONES_ALIGNMENT_AUDIT_GV_04_2026_02_25.md'),
        ];

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $doc = File::get($path);
            $this->assertStringContainsString('## Follow-up Closure Note (2026-02-26)', $doc);
            $this->assertStringContainsString('historical baseline', strtolower($doc));
        }
    }
}
