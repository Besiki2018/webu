<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibrarySpecEquivalenceAliasMapTest extends TestCase
{
    public function test_component_library_spec_equivalence_alias_map_v1_matches_gap_audit_matrix_order_and_canonical_keys(): void
    {
        $jsonPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP_V1.md');
        $gapAuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md');
        $summaryPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SOURCE_SPEC_COMPLETION_SUMMARY.md');

        foreach ([$jsonPath, $docPath, $gapAuditPath, $summaryPath] as $path) {
            $this->assertFileExists($path);
        }

        $json = json_decode(File::get($jsonPath), true, 512, JSON_THROW_ON_ERROR);
        $doc = File::get($docPath);
        $gapAudit = File::get($gapAuditPath);
        $summary = File::get($summaryPath);

        $this->assertSame('v1', $json['version'] ?? null);
        $this->assertSame('PROJECT_ROADMAP_TASKS_KA.md:6439-6878', $json['source_spec_ref'] ?? null);
        $this->assertSame('Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md', $json['gap_audit_ref'] ?? null);
        $this->assertSame('complete_exact_plus_equivalent', $json['coverage_status'] ?? null);
        $this->assertSame(70, $json['mapping_count'] ?? null);
        $this->assertIsArray($json['mappings'] ?? null);
        $this->assertCount(70, $json['mappings']);

        foreach ([
            'optional hardening artifact',
            'UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json',
            'machine-readable alias map',
            'Alias-map artifact: **COMPLETE**',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $this->assertStringContainsString('UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json', $summary);
        $this->assertStringContainsString('UniversalComponentLibrarySpecEquivalenceAliasMapTest.php', $summary);

        $gapRows = $this->parseGapAuditMatrixRows($gapAudit);
        $jsonRows = $json['mappings'];

        $this->assertSame(
            array_column($gapRows, 'source_component_key'),
            array_column($jsonRows, 'source_component_key'),
            'Alias-map must preserve gap-audit/source-spec row ordering'
        );

        $seenSourceKeys = [];
        foreach ($jsonRows as $index => $row) {
            $this->assertArrayHasKey('source_component_key', $row);
            $this->assertArrayHasKey('coverage', $row);
            $this->assertArrayHasKey('canonical_builder_keys', $row);

            $this->assertSame('equivalent', $row['coverage']);
            $this->assertIsArray($row['canonical_builder_keys']);
            $this->assertNotEmpty($row['canonical_builder_keys']);
            $this->assertSame($gapRows[$index]['canonical_builder_keys'], $row['canonical_builder_keys']);

            $this->assertFalse(isset($seenSourceKeys[$row['source_component_key']]));
            $seenSourceKeys[$row['source_component_key']] = true;
        }

        $rowsByKey = [];
        foreach ($jsonRows as $row) {
            $rowsByKey[$row['source_component_key']] = $row;
        }

        // Representative exactness-convergence aliases across domains and split composites.
        $this->assertSame(['webu_general_section_01'], $rowsByKey['layout.section']['canonical_builder_keys']);
        $this->assertSame(['webu_general_nav_logo_01'], $rowsByKey['nav.logo']['canonical_builder_keys']);
        $this->assertSame(['webu_ecom_product_grid_01'], $rowsByKey['ecom.productGrid']['canonical_builder_keys']);
        $this->assertSame([
            'webu_ecom_product_detail_01',
            'webu_ecom_add_to_cart_button_01',
            'webu_ecom_product_tabs_01',
        ], $rowsByKey['ecom.productDetail']['canonical_builder_keys']);
        $this->assertSame(['webu_svc_service_detail_01'], $rowsByKey['svc.serviceDetail']['canonical_builder_keys']);
        $this->assertSame(['webu_blog_post_detail_01'], $rowsByKey['blog.postDetail']['canonical_builder_keys']);
        $this->assertSame(['webu_realestate_property_detail_01'], $rowsByKey['re.propertyDetail']['canonical_builder_keys']);
        $this->assertSame(['webu_ecom_account_security_01'], $rowsByKey['auth.security']['canonical_builder_keys']);
    }

    /**
     * @return list<array{source_component_key:string,canonical_builder_keys:list<string>}>
     */
    private function parseGapAuditMatrixRows(string $doc): array
    {
        $rows = [];
        $inMatrix = false;

        foreach (preg_split('/\R/', $doc) as $line) {
            if (str_starts_with($line, '| Source component key | Status | Current Builder component(s) | Notes |')) {
                $inMatrix = true;
                continue;
            }

            if (! $inMatrix) {
                continue;
            }

            if (str_starts_with($line, '## Summary')) {
                break;
            }

            if (! str_starts_with($line, '|') || preg_match('/^\|\s*-+\s*\|/', $line) === 1) {
                continue;
            }

            if (preg_match('/^\|\s*([a-z]+\.[a-zA-Z][\w]*)\s*\|\s*(exact|equivalent|partial|missing)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|\s*$/', $line, $m) !== 1) {
                continue;
            }

            preg_match_all('/`([A-Za-z0-9_]+)`/', $m[3], $matches);
            $rows[] = [
                'source_component_key' => $m[1],
                'canonical_builder_keys' => $matches[1],
            ];
        }

        $this->assertCount(70, $rows, 'Gap-audit matrix row count changed unexpectedly');

        return $rows;
    }
}
