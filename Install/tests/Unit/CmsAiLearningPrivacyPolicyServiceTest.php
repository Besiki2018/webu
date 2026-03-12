<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\CmsAiLearningPrivacyPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsAiLearningPrivacyPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_tenant_opt_out_from_site_theme_settings_and_enforces_learning_and_reproducibility_disable(): void
    {
        $result = app(CmsAiLearningPrivacyPolicyService::class)->resolveForAiInput([
            'platform_context' => [
                'project' => ['id' => 'project-1'],
                'site' => [
                    'id' => 'site-1',
                    'theme_settings' => [
                        'ai_learning' => [
                            'opt_out' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('p6-g3-03.v1', $result['policy_version']);
        $this->assertTrue((bool) data_get($result, 'tenant.opt_out'));
        $this->assertSame('ai_learning.opt_out', data_get($result, 'tenant.opt_out_source'));
        $this->assertFalse((bool) data_get($result, 'effective.apply_learned_rules'));
        $this->assertFalse((bool) data_get($result, 'effective.emit_reproducibility'));
        $this->assertFalse((bool) data_get($result, 'effective.allow_global_learned_rules'));
        $this->assertSame('tenant_opt_out', data_get($result, 'diagnostics.status'));
        $this->assertContains('tenant_opt_out', (array) data_get($result, 'diagnostics.reasons', []));
    }

    public function test_it_applies_system_privacy_flags_for_global_rules_and_reproducibility(): void
    {
        SystemSetting::set(CmsAiLearningPrivacyPolicyService::FLAG_ALLOW_GLOBAL_LEARNED_RULES, false, 'boolean', 'privacy');
        SystemSetting::set(CmsAiLearningPrivacyPolicyService::FLAG_REPRODUCIBILITY_ENABLED, false, 'boolean', 'privacy');

        $result = app(CmsAiLearningPrivacyPolicyService::class)->resolveForAiInput([
            'platform_context' => [
                'project' => ['id' => 'project-1'],
                'site' => [
                    'id' => 'site-1',
                    'theme_settings' => [],
                ],
            ],
        ]);

        $this->assertFalse((bool) data_get($result, 'tenant.opt_out'));
        $this->assertTrue((bool) data_get($result, 'effective.apply_learned_rules'));
        $this->assertFalse((bool) data_get($result, 'effective.allow_global_learned_rules'));
        $this->assertFalse((bool) data_get($result, 'effective.emit_reproducibility'));
        $this->assertContains('global_learned_rules_disabled', (array) data_get($result, 'diagnostics.reasons', []));
        $this->assertContains('system_reproducibility_disabled', (array) data_get($result, 'diagnostics.reasons', []));
    }
}
