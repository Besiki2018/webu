<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryFormsLeadsComponentsRs0401BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_04_01_progress_audit_doc_locks_forms_leads_parity_submit_flow_and_validation_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_FORMS_LEADS_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_04_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_FORMS_LEADS_COMPONENTS_PARITY_RADIO_RUNTIME_HOOKS_OPENAPI_CLOSURE_AUDIT_RS_04_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $formsFeatureTestPath = base_path('tests/Feature/Forms/FormsLeadsModuleApiTest.php');
        $formsServicePath = base_path('app/Cms/Services/CmsFormsLeadsService.php');
        $publicFormControllerPath = base_path('app/Http/Controllers/Cms/PublicFormController.php');
        $webRoutesPath = base_path('routes/web.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $formsRuntimeContractTestPath = base_path('tests/Unit/BuilderCmsFormsRuntimeHooksContractTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryFormsLeadsComponentsRs0401ClosureAuditSyncTest.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $specCoverageGapAuditTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $ecommerceCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $generalUtilitiesCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsGeneralUtilitiesBuilderCoverage.contract.test.ts');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $closureDocPath,
            $cmsPath,
            $formsFeatureTestPath,
            $formsServicePath,
            $publicFormControllerPath,
            $webRoutesPath,
            $builderServicePath,
            $formsRuntimeContractTestPath,
            $closureSyncTestPath,
            $publicCoreOpenApiPath,
            $aliasMapPath,
            $specCoverageGapAuditTestPath,
            $ecommerceCoverageContractPath,
            $generalUtilitiesCoverageContractPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $cms = File::get($cmsPath);
        $formsFeatureTest = File::get($formsFeatureTestPath);
        $formsService = File::get($formsServicePath);
        $publicFormController = File::get($publicFormControllerPath);
        $webRoutes = File::get($webRoutesPath);
        $builderService = File::get($builderServicePath);
        $publicCoreOpenApi = File::get($publicCoreOpenApiPath);
        $aliasMap = File::get($aliasMapPath);
        $specCoverageGapAuditTest = File::get($specCoverageGapAuditTestPath);
        $ecommerceCoverageContract = File::get($ecommerceCoverageContractPath);
        $generalUtilitiesCoverageContract = File::get($generalUtilitiesCoverageContractPath);

        foreach ([
            '# 4) FORMS / LEADS (Universal)',
            '## 4.1 forms.form',
            'Content: formId, successMessage, redirectUrl',
            'Data: POST /forms/{id}/submit',
            '## 4.2 forms.input',
            'Style: input typography, border, radius, focus',
            '## 4.3 forms.textarea',
            'Same as input',
            '## 4.4 forms.select',
            'dataOptionsBinding (optional)',
            '## 4.5 forms.checkbox / forms.radio',
            'Content: options',
            '## 4.6 forms.submit',
            'Style: button styles',
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
            'CmsFormsLeadsService.php',
            'webu-public-core-minimal.v1.openapi.yaml',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit covering `radio` backend support, standalone submit runtime hooks, and OpenAPI submit status alignment',
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
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-04-01`)',
            '## Form Primitives Contract Matrix',
            '### Matrix (`content/style/panel-preview/submit-flow/validation-error/tests`)',
            '`forms.form`',
            '`forms.input`',
            '`forms.textarea`',
            '`forms.select`',
            '`forms.checkbox / forms.radio`',
            '`forms.submit`',
            '`webu_general_form_wrapper_01`',
            '`webu_general_input_01`',
            '`webu_general_textarea_01`',
            '`webu_general_select_01`',
            '`webu_general_checkbox_01` + `webu_general_radio_group_01`',
            '`webu_general_form_submit_01`',
            '## Submit Flow Verification (`POST /forms/{id}/submit`)',
            '### Source-to-Current Submit Contract Matrix',
            '`partial_equivalent`',
            'OpenAPI minimal doc currently documents submit success as `200`, while `PublicFormController::submit()` returns `201`',
            '## Validation / Error-Style Coverage Checks',
            'Builder UI / Preview Validation Styling (`forms.form`)',
            'Primitive Field Validation/Error Style Coverage (`input/textarea/select/checkbox/radio`)',
            'Backend Submit Validation Coverage',
            '`radio` is not an accepted backend form field type in `CmsFormsLeadsService::normalizeFieldType()`',
            '## Runtime Hook / Binding Status for Standalone Form Primitives',
            'no standalone runtime hook for `data-webby-form-submit` was found in `BuilderService` generated runtime script',
            '## DoD Verdict (`RS-04-01`)',
            'Conclusion: `RS-04-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_general_input_01',
            'webu_general_textarea_01',
            'webu_general_select_01',
            'webu_general_checkbox_01',
            'webu_general_radio_group_01',
            'webu_general_form_wrapper_01',
            'webu_general_form_submit_01',
            'validation_state',
            'preview_state',
            'form-wrapper-validation',
            "if (normalized === 'webu_general_input_01')",
            "if (normalized === 'webu_general_textarea_01')",
            "if (normalized === 'webu_general_select_01')",
            "if (normalized === 'webu_general_checkbox_01')",
            "if (normalized === 'webu_general_radio_group_01')",
            "if (normalized === 'webu_general_form_wrapper_01')",
            "if (normalized === 'webu_general_form_submit_01')",
            "if (normalizedSectionType === 'webu_general_input_01')",
            "if (normalizedSectionType === 'webu_general_textarea_01')",
            "if (normalizedSectionType === 'webu_general_select_01')",
            "if (normalizedSectionType === 'webu_general_checkbox_01')",
            "if (normalizedSectionType === 'webu_general_radio_group_01')",
            "if (normalizedSectionType === 'webu_general_form_wrapper_01')",
            "if (normalizedSectionType === 'webu_general_form_submit_01')",
            'data-webby-form-submit',
            'form-wrapper-validation-badge',
            'validation_message',
            'success_color',
            'error_color',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'formId',
            'redirectUrl',
            'dataOptionsBinding',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "forms.form"',
            'webu_general_form_wrapper_01',
            'source_component_key": "forms.input"',
            'webu_general_input_01',
            'source_component_key": "forms.textarea"',
            'webu_general_textarea_01',
            'source_component_key": "forms.select"',
            'webu_general_select_01',
            'source_component_key": "forms.checkbox"',
            'webu_general_checkbox_01',
            'source_component_key": "forms.radio"',
            'webu_general_radio_group_01',
            'source_component_key": "forms.submit"',
            'webu_general_form_submit_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::post('/{site}/forms/{key}/submit'",
            "Route::get('/{site}/forms/{key}'",
            'throttle:public-form-submit',
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }
        $this->assertStringNotContainsString("Route::post('/{site}/forms/{id}/submit'", $webRoutes);

        foreach ([
            'public function submit(Request $request, Site $site, string $key): JsonResponse',
            'return $this->corsJson($payload, 201);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicFormController);
        }

        foreach ([
            'public function submitPublicLead(Site $site, string $key, array $payload, Request $request): array',
            '\'message\' => $successMessage',
            '\'form_id\' => $form->id',
            '\'form_key\' => $form->key',
            '\'component_type\' => \'form\'',
            '\'submit_endpoint\' => route(\'public.sites.forms.submit\'',
            '\'definition_endpoint\' => route(\'public.sites.forms.show\'',
            'Missing required form fields.',
            'Invalid number field value.',
            'Invalid email field value.',
            'Invalid URL field value.',
            'Invalid select field option.',
            'Field value exceeds maximum length.',
            "'select', 'radio' => \$this->normalizeSubmittedSelect(\$field, \$value)",
            "if (\$type === 'select' || \$type === 'radio') {",
            'return in_array($normalized, [\'text\', \'email\', \'tel\', \'textarea\', \'select\', \'radio\', \'checkbox\', \'number\', \'url\', \'hidden\'], true)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $formsService);
        }
        $this->assertStringNotContainsString('redirect_url', $formsService);

        foreach ([
            'route(\'public.sites.forms.submit\'',
            '->assertCreated()',
            '->assertStatus(422)',
            '->assertNotFound()',
            'missing.0',
            'Form not found.',
            'public.sites.forms.submit',
            "Route::post('/{site}/forms/{key}/submit'",
            'test_public_form_submit_supports_radio_fields_and_typed_validation_rules',
            "'type' => 'radio'",
            'Invalid email field value.',
            'Invalid URL field value.',
            'Invalid number field value.',
            'Invalid select field option.',
            'Field value exceeds maximum length.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $formsFeatureTest);
        }

        foreach ([
            '/public/sites/{site}/forms/{key}/submit:',
            '/public/sites/{site}/forms/{key}:',
            "'201':",
            "'422':",
            'Submit form/leads payload',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }
        $this->assertStringNotContainsString('/public/sites/{site}/forms/{id}/submit:', $publicCoreOpenApi);

        foreach ([
            'forms.form',
            'forms.submit',
            'webu_general_form_wrapper_01',
            'webu_general_form_submit_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $specCoverageGapAuditTest);
        }

        foreach ([
            'webu_general_input_01',
            'webu_general_textarea_01',
            'webu_general_select_01',
            'webu_general_checkbox_01',
            'webu_general_radio_group_01',
            'webu_general_form_wrapper_01',
            'validation_state',
            'form-wrapper-validation',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCoverageContract);
        }

        foreach ([
            'webu_general_form_submit_01',
            'data-webby-form-submit',
            "if (normalized === 'webu_general_form_submit_01')",
            "if (normalizedSectionType === 'webu_general_form_submit_01')",
            '| forms.submit | equivalent | `webu_general_form_submit_01` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $generalUtilitiesCoverageContract);
        }

        foreach ([
            "'form_submit_url_pattern' =>",
            'function jsonPost(url, payload)',
            'function cmsFormSubmitUrl(formKey)',
            'function bindStandaloneFormSubmitWidget(node)',
            'function mountFormsRuntime(_payload)',
            'data-webby-form-submit-bound',
            'data-webby-form-submit-state',
            'webby:form-submit:success',
            'mountFormsRuntime(payload);',
            'mountFormsRuntime(window.__WEBBY_CMS__ || null);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }
    }
}
