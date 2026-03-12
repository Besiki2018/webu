<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BuilderCmsFormsRuntimeHooksContractTest extends TestCase
{
    public function test_builder_cms_runtime_script_keeps_standalone_form_submit_runtime_hook_contract(): void
    {
        $path = base_path('app/Services/BuilderService.php');
        $this->assertFileExists($path);

        $source = File::get($path);

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
            $this->assertStringContainsString($needle, $source);
        }
    }
}

