<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class LegacyReferenceArchiveEcommerceFullIntegrationBuilderCoreRegistryDynamicBindingComponentRequirementsReconciliationAr02SyncTest extends TestCase
{
    public function test_ar_02_legacy_builder_registry_dynamic_binding_and_component_family_reconciliation_audit_locks_truthfully(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_BUILDER_CORE_REGISTRY_DYNAMIC_BINDING_COMPONENT_REQUIREMENTS_RECONCILIATION_AUDIT_AR_02_2026_02_25.md');

        $registryDocPath = base_path('docs/architecture/CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md');
        $registrySchemaPath = base_path('docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json');
        $dynamicUxContractPath = base_path('resources/js/Pages/Project/__tests__/CmsDynamicAndThemeUx.contract.test.ts');
        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');

        $coverageGapBaselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md');
        $aliasMapJsonPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $coverageGapAuditTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $aliasMapTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php');

        $rs0001DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_BASE_NODE_TABS_REGISTRY_AUDIT_RS_00_01_2026_02_25.md');
        $rs0003DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_DATA_BINDING_RULE_COVERAGE_AUDIT_RS_00_03_2026_02_25.md');
        $rs0101DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_FOUNDATION_LAYOUT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_01_01_2026_02_25.md');
        $rs0201DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BASIC_CONTENT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_02_01_2026_02_25.md');
        $rs0401DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_FORMS_LEADS_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_04_01_2026_02_25.md');
        $rs0501DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md');
        $rs0502DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md');
        $rs0503DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md');
        $rs0504DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_DEFERRED_ADDONS_VNEXT_SCOPE_ACTIVATION_AUDIT_RS_05_04_2026_02_25.md');
        $rs1301DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $registryDocPath,
            $registrySchemaPath,
            $dynamicUxContractPath,
            $cmsPath,
            $coverageGapBaselineDocPath,
            $aliasMapJsonPath,
            $coverageGapAuditTestPath,
            $aliasMapTestPath,
            $rs0001DocPath,
            $rs0003DocPath,
            $rs0101DocPath,
            $rs0201DocPath,
            $rs0401DocPath,
            $rs0501DocPath,
            $rs0502DocPath,
            $rs0503DocPath,
            $rs0504DocPath,
            $rs1301DocPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $registryDoc = File::get($registryDocPath);
        $registrySchema = File::get($registrySchemaPath);
        $dynamicUxContract = File::get($dynamicUxContractPath);
        $cms = File::get($cmsPath);
        $coverageGapBaselineDoc = File::get($coverageGapBaselineDocPath);
        $coverageGapAuditTest = File::get($coverageGapAuditTestPath);
        $aliasMapTest = File::get($aliasMapTestPath);

        $rs0001Doc = File::get($rs0001DocPath);
        $rs0003Doc = File::get($rs0003DocPath);
        $rs0101Doc = File::get($rs0101DocPath);
        $rs0201Doc = File::get($rs0201DocPath);
        $rs0401Doc = File::get($rs0401DocPath);
        $rs0501Doc = File::get($rs0501DocPath);
        $rs0502Doc = File::get($rs0502DocPath);
        $rs0503Doc = File::get($rs0503DocPath);
        $rs0504Doc = File::get($rs0504DocPath);
        $rs1301Doc = File::get($rs1301DocPath);

        $aliasMapJson = json_decode(File::get($aliasMapJsonPath), true, 512, JSON_THROW_ON_ERROR);

        foreach ([
            '2.1 Component Registry',
            'Implement registry:',
            'category (Layout / Basic / Media / Form / Ecommerce / Account)',
            'props_schema (content/style/advanced definitions)',
            'default_props',
            'renderer',
            'controls_config (panel controls)',
            'All components must use same schema.',
            '2.2 Dynamic Data Binding (must work for ecommerce)',
            'Binding UI: “Dynamic” icon.',
            '{{store.name}}',
            '{{product.title}}',
            '{{cart.total}}',
            '{{customer.name}}',
            '3.1 Layout Components',
            '3.2 Basic Components',
            '3.3 Form Components',
            '4.1 Product Discovery Components',
            '4.2 Product Detail Components',
            '4.3 Cart Components',
            '4.4 Checkout Components',
            '4.5 Customer Account Components',
            '4.6 Admin/Store Owner Components (optional for builder)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `AR-02` (`DONE`, `P0`)',
            'LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_BUILDER_CORE_REGISTRY_DYNAMIC_BINDING_COMPONENT_REQUIREMENTS_RECONCILIATION_AUDIT_AR_02_2026_02_25.md',
            'LegacyReferenceArchiveEcommerceFullIntegrationBuilderCoreRegistryDynamicBindingComponentRequirementsReconciliationAr02SyncTest.php',
            'CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md',
            'cms-canonical-component-registry-entry.v1.schema.json',
            'CmsDynamicAndThemeUx.contract.test.ts',
            'UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php',
            'UniversalComponentLibrarySpecEquivalenceAliasMapTest.php',
            '`✅` canonical registry v1 contract (`type/category/props_schema/default_props/renderer/controls_config`) reconciled as adapter-layer implementation path and mapped to source `2.1`',
            '`✅` dynamic binding icon/UX/runtime support reconciled to current canonical namespace+resolver+validator pipeline',
            '`✅` source-listed component families (`Layout`, `Basic`, `Form`, ecommerce `4.1..4.5`, optional `4.6`) are all marked `implemented/partial/missing` with evidence',
            '`⚠️` source exact registry category taxonomy (`Layout/Basic/Media/Form/Ecommerce/Account`) and dynamic examples (`{{store.name}}`, `{{product.title}}`) are partially variant',
            '`⚠️` ecommerce family parity remains broadly `partial` at runtime/API exactness level per existing `RS-05-*` / `RS-13-01` baseline-gap audits',
            '`🧪` AR-02 reconciliation sync lock added (registry + dynamic binding + family coverage matrix state)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Scope',
            '## Closure Rationale (Why `AR-02` Can Be `DONE`)',
            '## Registry Schema Parity Audit (Source `2.1 Component Registry`)',
            '### Registry Field Matrix (`exact / equivalent / partial`)',
            '### Registry Category Taxonomy Parity (`Layout / Basic / Media / Form / Ecommerce / Account`)',
            '## Dynamic Binding Icon / UX / Runtime Support Audit (Source `2.2`)',
            '### Dynamic Binding Requirement Matrix (`exact / equivalent / partial`)',
            'builder UI `Dynamic` icon/action',
            'canonical `site.*` / `project.*` namespaces',
            '`{{key.path}}`',
            '## Component Family Coverage Matrix (Source `3.x` + `4.x`)',
            '### Matrix (`implemented / partial / missing`)',
            '## Inventory Baseline vs Runtime/API Parity Truth',
            '## Ecommerce Component Requirement Coverage Matrix (Source `4.x` Cross-Cutting Requirements)',
            'layout options',
            'loading/empty/error states design',
            'skeleton loaders',
            'pagination/infinite scroll',
            'API binding (tenant isolation)',
            'SEO-safe output for public pages',
            '## Key Truthful Variants and Gaps (AR-02 Synthesis Notes)',
            '## DoD Verdict (`AR-02`)',
            'Conclusion: `AR-02` is `DONE`.',
            '## Follow-up Mapping (Non-blocking for `AR-02` Closure)',
            '`AR-03`',
            '`AR-04`',
            '`ECM-01..04`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            '`3.1 Layout Components`',
            '`3.2 Basic Components`',
            '`3.3 Form Components`',
            '`4.1 Product Discovery Components`',
            '`4.2 Product Detail Components`',
            '`4.3 Cart Components`',
            '`4.4 Checkout Components`',
            '`4.5 Customer Account Components`',
            '`4.6 Admin/Store Owner Components (optional for builder)`',
            '`partial`',
            '`implemented`',
            '`missing`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            '`2.1.registry`',
            '`2.2.dynamicBinding`',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $doc, 'Doc should use human-readable source headings, not internal synthetic keys');
        }

        foreach ([
            '| `3.1 Layout Components` |',
            '| `3.2 Basic Components` |',
            '| `3.3 Form Components` |',
            '| `4.1 Product Discovery Components` |',
            '| `4.2 Product Detail Components` |',
            '| `4.3 Cart Components` |',
            '| `4.4 Checkout Components` |',
            '| `4.5 Customer Account Components` |',
            '| `4.6 Admin/Store Owner Components (optional for builder)` |',
        ] as $rowPrefix) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($rowPrefix, '/').'.*\|\s*`(?:implemented|partial|missing)`\s*\|/m', $doc);
        }

        foreach ([
            'Required Fields (v1)',
            '`type`',
            '`category`',
            '`props_schema`',
            '`default_props`',
            '`renderer`',
            '`controls_config`',
            'v1 registry entries are adapter artifacts composed from existing sources',
        ] as $needle) {
            $this->assertStringContainsString($needle, $registryDoc);
        }

        foreach ([
            '"required": [',
            '"type"',
            '"category"',
            '"props_schema"',
            '"default_props"',
            '"renderer"',
            '"controls_config"',
            '"additionalProperties": true',
            '"supports_dynamic_bindings"',
            '"supports_responsive"',
            '"supports_states"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $registrySchema);
        }

        foreach ([
            'CMS dynamic bindings and theme UX contracts',
            "{t('Dynamic')}",
            "{t('Clear')}",
            'renderDynamicBindingHint',
            'renderDynamicBindingActions',
            'binding_namespaces',
            'onApplyExpression',
        ] as $needle) {
            $this->assertStringContainsString($needle, $dynamicUxContract);
        }

        foreach ([
            'const renderDynamicBindingHint =',
            'const renderDynamicBindingActions =',
            'binding_namespaces',
            '{t(\'Dynamic\')}',
            '{t(\'Clear\')}',
            'onApplyExpression',
            'CanonicalControlGroup',
            "'bindings'",
            "'meta'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'Coverage Matrix (70 source-spec component keys)',
            'Component-library spec implementation coverage: **COMPLETE**',
            '| Source component key | Status | Current Builder component(s) | Notes |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $coverageGapBaselineDoc);
        }

        $this->assertSame('v1', $aliasMapJson['version'] ?? null);
        $this->assertSame('complete_exact_plus_equivalent', $aliasMapJson['coverage_status'] ?? null);
        $this->assertSame(70, $aliasMapJson['mapping_count'] ?? null);
        $this->assertCount(70, $aliasMapJson['mappings'] ?? []);

        foreach ([
            'test_component_library_spec_component_gap_audit_matrix_covers_all_source_spec_component_keys_in_order',
            'Coverage Matrix (70 source-spec component keys)',
            'rowsByKey[\'ecom.productGrid\']',
            'rowsByKey[\'auth.security\']',
        ] as $needle) {
            $this->assertStringContainsString($needle, $coverageGapAuditTest);
        }

        foreach ([
            'test_component_library_spec_equivalence_alias_map_v1_matches_gap_audit_matrix_order_and_canonical_keys',
            'mapping_count',
            'coverage_status',
            'rowsByKey[\'ecom.productDetail\']',
            'rowsByKey[\'auth.security\']',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMapTest);
        }

        foreach ([
            '## Executive Result (`RS-00-01`)',
            '## DoD Verdict (`RS-00-01`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0001Doc);
        }

        foreach ([
            '## Executive Result (`RS-00-03`)',
            '## DoD Verdict (`RS-00-03`)',
            '`{{key.path}}`',
            'raw-path mode',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0003Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Executive Result (`RS-01-01`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0101Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-02-01` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0201Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-04-01` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0401Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-05-01` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0501Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-05-02` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0502Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-05-03` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0503Doc);
        }

        foreach ([
            'Status: `DONE`',
            'Conclusion: `RS-05-04` is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0504Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-13-01` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs1301Doc);
        }
    }
}
