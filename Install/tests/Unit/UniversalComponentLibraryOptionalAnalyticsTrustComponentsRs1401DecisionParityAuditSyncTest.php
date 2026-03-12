<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryOptionalAnalyticsTrustComponentsRs1401DecisionParityAuditSyncTest extends TestCase
{
    public function test_rs_14_01_optional_v1_decision_and_parity_audit_locks_misc_components_truthfully(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_OPTIONAL_ANALYTICS_TRUST_COMPONENTS_DECISION_PARITY_AUDIT_RS_14_01_2026_02_25.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $builderServicePath = base_path('app/Services/BuilderService.php');

        $generalUtilitiesContractPath = base_path('resources/js/Pages/Project/__tests__/CmsGeneralUtilitiesBuilderCoverage.contract.test.ts');
        $coverageGapAuditUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $aliasMapUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $cmsPath,
            $aliasMapPath,
            $builderServicePath,
            $generalUtilitiesContractPath,
            $coverageGapAuditUnitTestPath,
            $aliasMapUnitTestPath,
            $activationFrontendContractPath,
            $activationUnitTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $cms = File::get($cmsPath);
        $aliasMap = File::get($aliasMapPath);
        $builderService = File::get($builderServicePath);
        $generalUtilitiesContract = File::get($generalUtilitiesContractPath);
        $coverageGapAuditUnitTest = File::get($coverageGapAuditUnitTestPath);
        $aliasMapUnitTest = File::get($aliasMapUnitTestPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);

        foreach ([
            '# 14) ANALYTICS / TRUST COMPONENTS (Optional v1)',
            '## 14.1 misc.testimonials',
            'Content: items or bind from CMS',
            'Style: cards',
            '## 14.2 misc.trustBadges',
            'Content: icons/text',
            '## 14.3 misc.statsCounter',
            'Content: numbers, animation',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-14-01` (`DONE`, `P2`)',
            'UNIVERSAL_COMPONENT_LIBRARY_OPTIONAL_ANALYTICS_TRUST_COMPONENTS_DECISION_PARITY_AUDIT_RS_14_01_2026_02_25.md',
            'UniversalComponentLibraryOptionalAnalyticsTrustComponentsRs1401DecisionParityAuditSyncTest.php',
            'CmsGeneralUtilitiesBuilderCoverage.contract.test.ts',
            '`✅` optional-v1 decision recorded and closed: `misc.testimonials` + `misc.trustBadges` implemented, `misc.statsCounter` accepted as implemented-with-variant-note (preview-driven counters; runtime count-up helper deferred)',
            '`✅` parity checks documented for cards/badges/counter behavior with canonical alias mappings (`webu_general_testimonials_01`, `webu_general_trust_badges_01`, `webu_general_stats_counter_01`)',
            '`✅` builder placeholders + component-specific preview-update branches evidenced for all 3 optional components (`data-webby-misc-testimonials`, `data-webby-misc-trust-badges`, `data-webby-misc-stats-counter`)',
            '`⚠️` `misc.statsCounter` source animation behavior is implemented as preview-level `animate_preview` behavior; dedicated published-runtime count-up helper/mount is explicitly deferred as non-blocking optional-v1 enhancement',
            '`🧪` RS-14-01 decision/parity sync lock added (optional-v1 closure state)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Scope',
            '## Closure Rationale (Why `RS-14-01` Can Be `DONE`)',
            '`misc.testimonials` → implemented in v1',
            '`misc.trustBadges` → implemented in v1',
            '`misc.statsCounter` → implemented in v1 as static/preview-driven counters',
            'accepted optional-v1 variant / deferred enhancement',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Optional-v1 Decision Matrix (`RS-14-01`)',
            '`implement_with_variant_note`',
            '## Analytics / Trust Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-helper/tests/optional-v1-decision`)',
            '`misc.testimonials`',
            '`misc.trustBadges`',
            '`misc.statsCounter`',
            '`webu_general_testimonials_01`',
            '`webu_general_trust_badges_01`',
            '`webu_general_stats_counter_01`',
            '## Parity Checks (Cards / Badges / Counter Animation Behavior)',
            '### `misc.testimonials` (Cards)',
            '### `misc.trustBadges` (Badges)',
            '### `misc.statsCounter` (Counter Animation Behavior)',
            '`animate_preview`',
            'preview-only',
            '## Runtime Helper Scope Check (`BuilderService`)',
            'no dedicated misc/general runtime helper',
            '## Evidence Summary (Existing Locks Reused)',
            '## DoD Verdict (`RS-14-01`)',
            'Conclusion: `RS-14-01` is `DONE`.',
            '## Follow-up Note (Non-blocking)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_general_testimonials_01',
            'webu_general_trust_badges_01',
            'webu_general_stats_counter_01',
            'data-webby-misc-testimonials',
            'data-webby-misc-trust-badges',
            'data-webby-misc-stats-counter',
            'items_count',
            'layout',
            'show_rating',
            'show_avatar',
            'quote_style',
            'show_label',
            'style_variant',
            'icon_fallback',
            'show_labels',
            'show_suffix',
            'animate_preview',
            'value_prefix',
            'value_suffix',
            "if (normalized === 'webu_general_testimonials_01')",
            "if (normalized === 'webu_general_trust_badges_01')",
            "if (normalized === 'webu_general_stats_counter_01')",
            "if (normalizedSectionType === 'webu_general_testimonials_01')",
            "if (normalizedSectionType === 'webu_general_trust_badges_01')",
            "if (normalizedSectionType === 'webu_general_stats_counter_01')",
            'data-webu-role="testimonials-grid"',
            'data-webu-role="trust-badges-row"',
            'data-webu-role="stats-counter-grid"',
            'const animatePreview = parseBooleanProp(effectiveProps.animate_preview, false);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "misc.testimonials"',
            'webu_general_testimonials_01',
            'source_component_key": "misc.trustBadges"',
            'webu_general_trust_badges_01',
            'source_component_key": "misc.statsCounter"',
            'webu_general_stats_counter_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            'window.WebbyEcommerce',
            'window.WebbyBooking',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }
        foreach ([
            'data-webby-misc-testimonials',
            'data-webby-misc-trust-badges',
            'data-webby-misc-stats-counter',
            'mountStatsCounterWidget',
            'WebbyMisc',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $builderService);
        }

        foreach ([
            'CMS general utility components builder coverage contracts',
            'webu_general_testimonials_01',
            'webu_general_trust_badges_01',
            'webu_general_stats_counter_01',
            'data-webby-misc-testimonials',
            'data-webby-misc-trust-badges',
            'data-webby-misc-stats-counter',
            'data-webu-role="testimonials-grid"',
            'data-webu-role="trust-badges-row"',
            'data-webu-role="stats-counter-grid"',
            "if (normalizedSectionType === 'webu_general_stats_counter_01')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $generalUtilitiesContract);
        }

        foreach ([
            "rowsByKey['misc.testimonials']",
            'webu_general_testimonials_01',
            "rowsByKey['misc.trustBadges']",
            'webu_general_trust_badges_01',
            "rowsByKey['misc.statsCounter']",
            'webu_general_stats_counter_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $coverageGapAuditUnitTest);
        }

        foreach ([
            'webu_ecom_account_security_01',
            "rowsByKey['auth.security']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMapUnitTest);
        }

        foreach ([
            'CMS universal component library activation contracts',
            'BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER',
            'builderSectionAvailabilityMatrix',
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationFrontendContract);
        }
        foreach ([
            "key: 'ecommerce'",
            "key: 'booking'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationUnitTest);
        }
    }
}
