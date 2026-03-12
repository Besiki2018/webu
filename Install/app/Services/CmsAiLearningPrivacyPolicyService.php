<?php

namespace App\Services;

use App\Models\SystemSetting;

class CmsAiLearningPrivacyPolicyService
{
    public const FLAG_GENERATION_LEARNING_ENABLED = 'cms_ai_learning_generation_enabled';

    public const FLAG_ALLOW_GLOBAL_LEARNED_RULES = 'cms_ai_learning_allow_global_rules';

    public const FLAG_REPRODUCIBILITY_ENABLED = 'cms_ai_learning_reproducibility_enabled';

    /**
     * Resolve tenant/system privacy policy for learning-enhanced generation.
     *
     * @param  array<string, mixed>  $aiInput
     * @return array<string, mixed>
     */
    public function resolveForAiInput(array $aiInput): array
    {
        $tenant = $this->resolveTenantFlags($aiInput);
        $system = $this->resolveSystemFlags();

        $tenantOptOut = (bool) ($tenant['opt_out'] ?? false);
        $learningEnabled = (bool) ($system['generation_learning_enabled'] ?? true) && ! $tenantOptOut;
        $emitReproducibility = (bool) ($system['reproducibility_enabled'] ?? true) && ! $tenantOptOut;
        $allowGlobalRules = (bool) ($system['allow_global_learned_rules'] ?? true) && ! $tenantOptOut;

        $reasons = [];
        if (! (bool) ($system['generation_learning_enabled'] ?? true)) {
            $reasons[] = 'system_learning_generation_disabled';
        }
        if ($tenantOptOut) {
            $reasons[] = 'tenant_opt_out';
        }
        if (! (bool) ($system['reproducibility_enabled'] ?? true)) {
            $reasons[] = 'system_reproducibility_disabled';
        }
        if (! (bool) ($system['allow_global_learned_rules'] ?? true)) {
            $reasons[] = 'global_learned_rules_disabled';
        }

        $status = 'enabled';
        if ($tenantOptOut) {
            $status = 'tenant_opt_out';
        } elseif (! (bool) ($system['generation_learning_enabled'] ?? true)) {
            $status = 'system_learning_generation_disabled';
        } elseif (! (bool) ($system['reproducibility_enabled'] ?? true)) {
            $status = 'reproducibility_disabled';
        } elseif (! (bool) ($system['allow_global_learned_rules'] ?? true)) {
            $status = 'global_rules_limited';
        }

        return [
            'schema_version' => 1,
            'policy_version' => 'p6-g3-03.v1',
            'tenant' => $tenant,
            'system' => $system,
            'effective' => [
                'tenant_opt_out' => $tenantOptOut,
                'apply_learned_rules' => $learningEnabled,
                'emit_reproducibility' => $emitReproducibility,
                'allow_global_learned_rules' => $allowGlobalRules,
            ],
            'diagnostics' => [
                'status' => $status,
                'reasons' => array_values(array_unique($reasons)),
                'tenant_opt_out' => $tenantOptOut,
                'tenant_opt_out_source' => $tenant['opt_out_source'] ?? null,
                'site_id' => $tenant['site_id'] ?? null,
                'project_id' => $tenant['project_id'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $aiInput
     * @return array<string, mixed>
     */
    private function resolveTenantFlags(array $aiInput): array
    {
        $siteThemeSettings = is_array(data_get($aiInput, 'platform_context.site.theme_settings'))
            ? (array) data_get($aiInput, 'platform_context.site.theme_settings')
            : [];

        $candidates = [
            ['path' => 'ai_learning.opt_out', 'value' => data_get($siteThemeSettings, 'ai_learning.opt_out')],
            ['path' => 'ai_learning.enabled', 'value' => data_get($siteThemeSettings, 'ai_learning.enabled'), 'invert' => true],
            ['path' => 'privacy.ai_learning_opt_out', 'value' => data_get($siteThemeSettings, 'privacy.ai_learning_opt_out')],
            ['path' => 'privacy.learning_opt_out', 'value' => data_get($siteThemeSettings, 'privacy.learning_opt_out')],
            ['path' => 'cms.ai_learning_opt_out', 'value' => data_get($siteThemeSettings, 'cms.ai_learning_opt_out')],
        ];

        $resolved = false;
        $optOut = false;
        $source = null;
        foreach ($candidates as $candidate) {
            $raw = $candidate['value'] ?? null;
            if (! is_bool($raw) && ! is_numeric($raw) && ! is_string($raw)) {
                continue;
            }
            $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                continue;
            }
            if (($candidate['invert'] ?? false) === true) {
                $bool = ! $bool;
            }
            $resolved = true;
            $optOut = $bool;
            $source = (string) ($candidate['path'] ?? 'unknown');
            break;
        }

        return [
            'site_id' => $this->safeString(data_get($aiInput, 'platform_context.site.id')),
            'project_id' => $this->safeString(data_get($aiInput, 'platform_context.project.id')),
            'opt_out' => $optOut,
            'opt_out_explicit' => $resolved,
            'opt_out_source' => $source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSystemFlags(): array
    {
        return [
            'generation_learning_enabled' => (bool) SystemSetting::get(self::FLAG_GENERATION_LEARNING_ENABLED, true),
            'allow_global_learned_rules' => (bool) SystemSetting::get(self::FLAG_ALLOW_GLOBAL_LEARNED_RULES, true),
            'reproducibility_enabled' => (bool) SystemSetting::get(self::FLAG_REPRODUCIBILITY_ENABLED, true),
        ];
    }

    private function safeString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
