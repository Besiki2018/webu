<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryFormsLeadsComponentsRs0401ClosureAuditSyncTest extends TestCase
{
    public function test_rs_04_01_closure_audit_locks_forms_radio_runtime_hook_and_openapi_alignment_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_FORMS_LEADS_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_04_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_FORMS_LEADS_COMPONENTS_PARITY_RADIO_RUNTIME_HOOKS_OPENAPI_CLOSURE_AUDIT_RS_04_01_2026_02_26.md');

        $formsServicePath = base_path('app/Cms/Services/CmsFormsLeadsService.php');
        $publicFormControllerPath = base_path('app/Http/Controllers/Cms/PublicFormController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $formsFeatureTestPath = base_path('tests/Feature/Forms/FormsLeadsModuleApiTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderCmsFormsRuntimeHooksContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryFormsLeadsComponentsRs0401BaselineGapAuditSyncTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $formsServicePath,
            $publicFormControllerPath,
            $builderServicePath,
            $publicCoreOpenApiPath,
            $formsFeatureTestPath,
            $runtimeContractTestPath,
            $baselineSyncTestPath,
            base_path('routes/web.php'),
            base_path('resources/js/Pages/Project/Cms.tsx'),
            base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php'),
            base_path('resources/js/Pages/Project/__tests__/CmsGeneralUtilitiesBuilderCoverage.contract.test.ts'),
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $baselineDoc = File::get($baselineDocPath);
        $closureDoc = File::get($closureDocPath);
        $formsService = File::get($formsServicePath);
        $publicFormController = File::get($publicFormControllerPath);
        $builderService = File::get($builderServicePath);
        $publicCoreOpenApi = File::get($publicCoreOpenApiPath);
        $formsFeatureTest = File::get($formsFeatureTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);

        foreach ([
            '# 4) FORMS / LEADS (Universal)',
            '## 4.1 forms.form',
            'Data: POST /forms/{id}/submit',
            '## 4.4 forms.select',
            'dataOptionsBinding (optional)',
            '## 4.5 forms.checkbox / forms.radio',
            '## 4.6 forms.submit',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-04-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_FORMS_LEADS_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_04_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_FORMS_LEADS_COMPONENTS_PARITY_RADIO_RUNTIME_HOOKS_OPENAPI_CLOSURE_AUDIT_RS_04_01_2026_02_26.md',
            'UniversalComponentLibraryFormsLeadsComponentsRs0401BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryFormsLeadsComponentsRs0401ClosureAuditSyncTest.php',
            'FormsLeadsModuleApiTest.php',
            'BuilderCmsFormsRuntimeHooksContractTest.php',
            '`✅` backend forms schema + submit validation now supports `radio` field type',
            '`✅` standalone `data-webby-form-submit` runtime hook now exists in `BuilderService` CMS runtime',
            '`✅` `webu-public-core-minimal` OpenAPI submit success response now matches runtime (`201`)',
            '`✅` DoD closure achieved via form primitives parity evidence + submit success/error flow evidence + runtime hook coverage',
            '`⚠️` source exactness gaps remain',
            '`⚠️` submit endpoint remains an accepted path/id variant',
            '`🧪` RS-04-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'backend form schema validation supports `checkbox`, but `radio` is not included in `CmsFormsLeadsService::normalizeFieldType()`',
            'no standalone runtime hook for `data-webby-form-submit` was found in `BuilderService` generated runtime script',
            'Conclusion: `RS-04-01` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $baselineDoc);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-04-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'backend `radio` field-type support',
            'standalone published runtime hook for `data-webby-form-submit`',
            'OpenAPI submit success status alignment (`201` to match runtime)',
            '## Executive Result (`RS-04-01`)',
            '`RS-04-01` is now **DoD-complete** as a forms/leads parity verification task.',
            '## Closure Delta Against Baseline (`2026-02-25`)',
            'accepted_variant',
            'non_blocking_exactness_gap',
            '## Backend `radio` Field Support Closure (`CmsFormsLeadsService`)',
            '## OpenAPI Submit Status Alignment Closure',
            '## Standalone Form Submit Runtime Hook Closure (`BuilderService`)',
            'data-webby-form-submit-bound',
            'data-webby-form-submit-state',
            'webby:form-submit:success',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'BuilderCmsFormsRuntimeHooksContractTest.php',
            '## Remaining Exactness Gaps (Truthful, Non-Blocking for `RS-04-01` DoD)',
            '## DoD Closure Matrix (`RS-04-01`)',
            'all 6 form elements validated',
            'form submission success/error flows evidenced',
            '## DoD Verdict (`RS-04-01`)',
            '`RS-04-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            "return match (\$type) {",
            "'select', 'radio' => \$this->normalizeSubmittedSelect(\$field, \$value)",
            "if (\$type === 'select' || \$type === 'radio') {",
            "return in_array(\$normalized, ['text', 'email', 'tel', 'textarea', 'select', 'radio', 'checkbox', 'number', 'url', 'hidden'], true)",
        ] as $needle) {
            $this->assertStringContainsString($needle, $formsService);
        }

        foreach ([
            'public function submit(Request $request, Site $site, string $key): JsonResponse',
            'return $this->corsJson($payload, 201);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicFormController);
        }

        foreach ([
            '/public/sites/{site}/forms/{key}/submit:',
            "'201':",
            "'422':",
            'Submit form/leads payload',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }

        foreach ([
            "'form_submit_url_pattern' =>",
            'function jsonPost(url, payload)',
            'function cmsFormSubmitUrl(formKey)',
            'function setStandaloneFormSubmitState(node, state, message)',
            'function resolveStandaloneFormSubmitEndpoint(node, form)',
            'function bindStandaloneFormSubmitWidget(node)',
            'function mountFormsRuntime(_payload)',
            '[data-webby-form-submit]',
            'data-webby-form-submit-bound',
            'data-webby-form-submit-state',
            'webby:form-submit:click',
            'webby:form-submit:success',
            'webby:form-submit:error',
            'jsonPost(endpoint, payload)',
            'mountFormsRuntime(payload);',
            'mountFormsRuntime(window.__WEBBY_CMS__ || null);',
            'mountFormsRuntime: function () {',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'test_public_form_submit_supports_radio_fields_and_typed_validation_rules',
            "'type' => 'radio'",
            "->assertJsonPath('form.schema_json.fields.3.type', 'radio')",
            "'Invalid email field value.'",
            "'Invalid URL field value.'",
            "'Invalid number field value.'",
            "'Invalid select field option.'",
            "'Field value exceeds maximum length.'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $formsFeatureTest);
        }

        foreach ([
            'BuilderCmsFormsRuntimeHooksContractTest',
            'function bindStandaloneFormSubmitWidget(node)',
            'function mountFormsRuntime(_payload)',
            'data-webby-form-submit-bound',
            'webby:form-submit:success',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }
    }
}

