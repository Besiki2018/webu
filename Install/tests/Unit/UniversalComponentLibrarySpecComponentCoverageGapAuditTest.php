<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibrarySpecComponentCoverageGapAuditTest extends TestCase
{
    public function test_component_library_spec_component_gap_audit_matrix_covers_all_source_spec_component_keys_in_order(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md');

        foreach ([$roadmapPath, $docPath] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $doc = File::get($docPath);

        $this->assertStringContainsString('# CODEX PROMPT — Webu Universal Component Library Spec', $roadmap);
        $this->assertStringContainsString('# 15) AI Prompt → Industry Mapping Rules (must)', $roadmap);
        $this->assertStringContainsString('Source-spec reference: `PROJECT_ROADMAP_TASKS_KA.md:6439`', $doc);
        $this->assertStringContainsString('Coverage Matrix (70 source-spec component keys)', $doc);
        $this->assertStringContainsString('Component-library spec implementation coverage: **COMPLETE**', $doc);

        $sourceComponentKeys = $this->parseSourceSpecComponentKeys($roadmap);
        $matrix = $this->parseCoverageMatrix($doc);
        $matrixKeys = array_column($matrix, 'key');

        $this->assertCount(70, $sourceComponentKeys, 'Unexpected component key count in source-spec sections #1..#14');
        $this->assertSame($sourceComponentKeys, $matrixKeys, 'Component coverage matrix must preserve source-spec key order');

        $statusCounts = ['exact' => 0, 'equivalent' => 0, 'partial' => 0, 'missing' => 0];
        $rowsByKey = [];

        foreach ($matrix as $row) {
            $statusCounts[$row['status']]++;
            $rowsByKey[$row['key']] = $row;
        }

        $this->assertSame([
            'exact' => 0,
            'equivalent' => 70,
            'partial' => 0,
            'missing' => 0,
        ], $statusCounts);

        $this->assertStringContainsString('- Total source-spec component keys audited: `70`', $doc);
        $this->assertStringContainsString('- `exact`: `0`', $doc);
        $this->assertStringContainsString('- `equivalent`: `70`', $doc);
        $this->assertStringContainsString('- `partial`: `0`', $doc);
        $this->assertStringContainsString('- `missing`: `0`', $doc);

        // Representative mappings across foundations, ecommerce and verticals.
        $this->assertSame('equivalent', $rowsByKey['layout.section']['status']);
        $this->assertStringContainsString('`webu_general_section_01`', $rowsByKey['layout.section']['current']);
        $this->assertSame('equivalent', $rowsByKey['basic.iconBox']['status']);
        $this->assertStringContainsString('`webu_general_icon_box_01`', $rowsByKey['basic.iconBox']['current']);
        $this->assertSame('equivalent', $rowsByKey['nav.logo']['status']);
        $this->assertStringContainsString('`webu_general_nav_logo_01`', $rowsByKey['nav.logo']['current']);
        $this->assertSame('equivalent', $rowsByKey['nav.menu']['status']);
        $this->assertStringContainsString('`webu_general_nav_menu_01`', $rowsByKey['nav.menu']['current']);
        $this->assertSame('equivalent', $rowsByKey['nav.search']['status']);
        $this->assertStringContainsString('`webu_general_nav_search_01`', $rowsByKey['nav.search']['current']);
        $this->assertSame('equivalent', $rowsByKey['nav.cartIcon']['status']);
        $this->assertStringContainsString('`webu_ecom_cart_icon_01`', $rowsByKey['nav.cartIcon']['current']);
        $this->assertSame('equivalent', $rowsByKey['nav.accountIcon']['status']);
        $this->assertStringContainsString('`webu_general_nav_account_icon_01`', $rowsByKey['nav.accountIcon']['current']);
        $this->assertSame('equivalent', $rowsByKey['footer.footer']['status']);
        $this->assertStringContainsString('`webu_general_footer_01`', $rowsByKey['footer.footer']['current']);
        $this->assertSame('equivalent', $rowsByKey['forms.form']['status']);
        $this->assertStringContainsString('`webu_general_form_wrapper_01`', $rowsByKey['forms.form']['current']);
        $this->assertSame('equivalent', $rowsByKey['forms.submit']['status']);
        $this->assertStringContainsString('`webu_general_form_submit_01`', $rowsByKey['forms.submit']['current']);

        $this->assertSame('equivalent', $rowsByKey['ecom.productGrid']['status']);
        $this->assertStringContainsString('`webu_ecom_product_grid_01`', $rowsByKey['ecom.productGrid']['current']);
        $this->assertSame('equivalent', $rowsByKey['ecom.couponBox']['status']);
        $this->assertStringContainsString('`webu_ecom_coupon_ui_01`', $rowsByKey['ecom.couponBox']['current']);
        $this->assertSame('equivalent', $rowsByKey['ecom.productDetail']['status']);
        $this->assertStringContainsString('webu_ecom_add_to_cart_button_01', $rowsByKey['ecom.productDetail']['current']);

        $this->assertSame('equivalent', $rowsByKey['svc.serviceList']['status']);
        $this->assertSame('equivalent', $rowsByKey['svc.serviceDetail']['status']);
        $this->assertStringContainsString('`webu_svc_service_detail_01`', $rowsByKey['svc.serviceDetail']['current']);
        $this->assertSame('equivalent', $rowsByKey['svc.pricingTable']['status']);
        $this->assertStringContainsString('`webu_svc_pricing_table_01`', $rowsByKey['svc.pricingTable']['current']);
        $this->assertSame('equivalent', $rowsByKey['svc.faq']['status']);
        $this->assertStringContainsString('`webu_svc_faq_01`', $rowsByKey['svc.faq']['current']);
        $this->assertSame('equivalent', $rowsByKey['book.bookingForm']['status']);
        $this->assertSame('equivalent', $rowsByKey['book.bookingManage']['status']);
        $this->assertStringContainsString('`webu_book_booking_manage_01`', $rowsByKey['book.bookingManage']['current']);
        $this->assertSame('equivalent', $rowsByKey['port.portfolioDetail']['status']);
        $this->assertStringContainsString('`webu_portfolio_project_detail_01`', $rowsByKey['port.portfolioDetail']['current']);
        $this->assertSame('equivalent', $rowsByKey['blog.postList']['status']);
        $this->assertStringContainsString('`webu_blog_post_list_01`', $rowsByKey['blog.postList']['current']);
        $this->assertSame('equivalent', $rowsByKey['blog.postDetail']['status']);
        $this->assertStringContainsString('`webu_blog_post_detail_01`', $rowsByKey['blog.postDetail']['current']);
        $this->assertSame('equivalent', $rowsByKey['blog.categoryList']['status']);
        $this->assertStringContainsString('`webu_blog_category_list_01`', $rowsByKey['blog.categoryList']['current']);
        $this->assertSame('equivalent', $rowsByKey['re.propertyDetail']['status']);
        $this->assertStringContainsString('`webu_realestate_property_detail_01`', $rowsByKey['re.propertyDetail']['current']);
        $this->assertSame('equivalent', $rowsByKey['re.map']['status']);
        $this->assertStringContainsString('`webu_realestate_map_01`', $rowsByKey['re.map']['current']);
        $this->assertSame('equivalent', $rowsByKey['rest.tableReservationForm']['status']);
        $this->assertSame('equivalent', $rowsByKey['hotel.reservationForm']['status']);
        $this->assertSame('equivalent', $rowsByKey['auth.auth']['status']);
        $this->assertSame('equivalent', $rowsByKey['auth.profile']['status']);
        $this->assertStringContainsString('`webu_ecom_account_profile_01`', $rowsByKey['auth.profile']['current']);
        $this->assertSame('equivalent', $rowsByKey['auth.security']['status']);
        $this->assertStringContainsString('`webu_ecom_account_security_01`', $rowsByKey['auth.security']['current']);
        $this->assertSame('equivalent', $rowsByKey['misc.testimonials']['status']);
        $this->assertStringContainsString('`webu_general_testimonials_01`', $rowsByKey['misc.testimonials']['current']);
        $this->assertSame('equivalent', $rowsByKey['misc.trustBadges']['status']);
        $this->assertStringContainsString('`webu_general_trust_badges_01`', $rowsByKey['misc.trustBadges']['current']);
        $this->assertSame('equivalent', $rowsByKey['misc.statsCounter']['status']);
        $this->assertStringContainsString('`webu_general_stats_counter_01`', $rowsByKey['misc.statsCounter']['current']);

        foreach ([
            'CmsEcommerceBuilderCoverage.contract.test.ts',
            'CmsBookingBuilderCoverage.contract.test.ts',
            'CmsBlogBuilderCoverage.contract.test.ts',
            'CmsPortfolioBuilderCoverage.contract.test.ts',
            'CmsRealEstateBuilderCoverage.contract.test.ts',
            'CmsRestaurantBuilderCoverage.contract.test.ts',
            'CmsHotelBuilderCoverage.contract.test.ts',
            'CmsUniversalComponentLibraryActivation.contract.test.ts',
            'CmsPhase3PrimaryTabsWrapperSummary.contract.test.ts',
            'CmsPhase3ResponsiveStateWrapperSummary.contract.test.ts',
            'CmsUniversalBindingNamespaceCompatibility.contract.test.ts',
            'UniversalAiIndustryComponentMappingP5F5Test.php',
            'UNIVERSAL_COMPONENT_LIBRARY_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $this->assertStringContainsString('Component-library spec component coverage gap audit baseline: **COMPLETE**', $doc);
        $this->assertStringContainsString('Component-library spec implementation coverage: **COMPLETE**', $doc);
    }

    /**
     * @return list<string>
     */
    private function parseSourceSpecComponentKeys(string $roadmap): array
    {
        $startHeading = '# CODEX PROMPT — Webu Universal Component Library Spec (Elementor-level, All Industries)';
        $endHeading = '# 15) AI Prompt → Industry Mapping Rules (must)';

        $startPos = strpos($roadmap, $startHeading);
        $endPos = strpos($roadmap, $endHeading);

        $this->assertNotFalse($startPos, 'Could not locate component-library source-spec start');
        $this->assertNotFalse($endPos, 'Could not locate component-library AI mapping section start');
        $this->assertGreaterThan($startPos, $endPos, 'Component-library source-spec section ordering changed unexpectedly');

        $block = substr($roadmap, $startPos, $endPos - $startPos);
        $components = [];

        foreach (preg_split('/\R/', $block) as $line) {
            if (! preg_match('/^##\s+\d+\.\d+\s+(.+)$/', $line, $m)) {
                continue;
            }

            $rhs = trim($m[1]);
            $parts = array_map('trim', explode('/', $rhs));

            $slashComponents = [];
            foreach ($parts as $part) {
                if (preg_match('/^[a-z]+\.[a-zA-Z][\w]*$/', $part) === 1) {
                    $slashComponents[] = $part;
                }
            }

            if (count($slashComponents) === count($parts) && count($slashComponents) > 1) {
                foreach ($slashComponents as $component) {
                    $components[] = $component;
                }

                continue;
            }

            $token = strtok($rhs, ' ');
            if (is_string($token) && preg_match('/^[a-z]+\.[a-zA-Z][\w]*$/', $token) === 1) {
                $components[] = $token;
            }
        }

        return $components;
    }

    /**
     * @return list<array{key:string,status:string,current:string,notes:string}>
     */
    private function parseCoverageMatrix(string $doc): array
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

            if (! str_starts_with($line, '|')) {
                continue;
            }

            if (preg_match('/^\|\s*-+\s*\|/', $line) === 1) {
                continue;
            }

            if (preg_match('/^\|\s*([a-z]+\.[a-zA-Z][\w]*)\s*\|\s*(exact|equivalent|partial|missing)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|\s*$/', $line, $m) === 1) {
                $rows[] = [
                    'key' => $m[1],
                    'status' => $m[2],
                    'current' => $m[3],
                    'notes' => $m[4],
                ];
            }
        }

        $this->assertNotEmpty($rows, 'Component coverage matrix rows were not parsed');

        return $rows;
    }
}
