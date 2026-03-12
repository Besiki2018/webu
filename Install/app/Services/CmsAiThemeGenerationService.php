<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsAiThemeGenerationService
{
    public const ENGINE_VERSION = 1;

    public function __construct(
        protected CmsAiSchemaValidationService $schemaValidator,
        protected CmsThemeTokenValueValidator $themeTokenValidator,
        protected TemplateSelectorService $templateSelector,
    ) {}

    /**
     * Generate AI-output-compatible theme fragment from canonical AI input payload.
     *
     * The engine returns builder/runtime-native `theme_settings_patch` instead of generating
     * parallel render contracts, so runtime contract logic stays centralized.
     *
     * @param  array<string, mixed>  $aiInput
     * @return array<string, mixed>
     */
    public function generateThemeFragment(array $aiInput): array
    {
        $inputValidation = $this->schemaValidator->validateInputPayload($aiInput);
        if (! (bool) ($inputValidation['valid'] ?? false)) {
            return [
                'valid' => false,
                'engine' => $this->engineMeta(),
                'errors' => $inputValidation['errors'] ?? [],
                'warnings' => [],
                'input_validation' => $inputValidation,
                'template_choice' => null,
                'preset_choice' => null,
                'theme' => null,
            ];
        }

        $prompt = Str::lower(trim((string) data_get($aiInput, 'request.prompt', '')));
        $mode = (string) data_get($aiInput, 'request.mode', 'generate_site');
        $constraints = is_array(data_get($aiInput, 'request.constraints')) ? data_get($aiInput, 'request.constraints') : [];
        $userContext = is_array(data_get($aiInput, 'request.user_context')) ? data_get($aiInput, 'request.user_context') : [];

        $templateBlueprintSlug = $this->normalizedNullableString(data_get($aiInput, 'platform_context.template_blueprint.template_slug'));
        $existingSiteThemeSettings = is_array(data_get($aiInput, 'platform_context.site.theme_settings'))
            ? data_get($aiInput, 'platform_context.site.theme_settings')
            : [];

        $signals = $this->collectSignals($prompt, $aiInput);
        $templateChoice = $this->chooseTemplateSlug($templateBlueprintSlug, $signals, $prompt);

        $preserveThemeSettings = (bool) ($constraints['preserve_theme_settings'] ?? false);
        $existingPreset = $this->normalizedNullableString($existingSiteThemeSettings['preset'] ?? null);
        $keepExistingTheme = $preserveThemeSettings || ($mode === 'edit_site' && $existingPreset !== null);

        $presetChoice = $this->choosePreset(
            prompt: $prompt,
            signals: $signals,
            existingPreset: $existingPreset,
            keepExistingTheme: $keepExistingTheme,
        );

        $themeSettingsPatch = [
            'preset' => $presetChoice['resolved'],
        ];

        if (! $keepExistingTheme) {
            $tokenPatch = $this->buildTokenPatch($prompt, $signals, $userContext);
            if ($tokenPatch !== []) {
                $themeSettingsPatch['theme_tokens'] = $tokenPatch;
            }
        }

        $tokenValidation = $this->themeTokenValidator->validate($themeSettingsPatch);
        $warnings = [];

        if (! ($tokenValidation['valid'] ?? false)) {
            $warnings[] = [
                'code' => 'theme_token_patch_validation_failed',
                'message' => 'Generated token patch failed canonical token validator and was reduced to preset-only patch.',
                'details' => $tokenValidation['errors'] ?? [],
            ];

            $themeSettingsPatch = [
                'preset' => $presetChoice['resolved'],
            ];
            $tokenValidation = $this->themeTokenValidator->validate($themeSettingsPatch);
        }

        return [
            'valid' => true,
            'engine' => $this->engineMeta(),
            'errors' => [],
            'warnings' => $warnings,
            'template_choice' => $templateChoice,
            'preset_choice' => [
                ...$presetChoice,
                'signals' => $signals,
            ],
            'theme' => [
                'theme_settings_patch' => $themeSettingsPatch,
                'meta' => [
                    'source' => $keepExistingTheme ? 'keep_existing' : 'generated',
                ],
            ],
            'validation' => [
                'input' => [
                    'valid' => true,
                    'error_count' => 0,
                ],
                'theme_token_patch' => $tokenValidation,
            ],
        ];
    }

    /**
     * @return array{kind:string,version:int}
     */
    private function engineMeta(): array
    {
        return [
            'kind' => 'rule_based_theme_generation',
            'version' => self::ENGINE_VERSION,
        ];
    }

    /**
     * @param  array<string, mixed>  $aiInput
     * @return array<string, bool>
     */
    private function collectSignals(string $prompt, array $aiInput): array
    {
        $moduleRegistryModules = data_get($aiInput, 'platform_context.module_registry.modules', []);
        $moduleEntitlementFlags = data_get($aiInput, 'platform_context.module_entitlements.modules', []);

        $ecommerceEnabled = $this->readModuleEnabledFlag($moduleRegistryModules, 'ecommerce')
            || (bool) (is_array($moduleEntitlementFlags) ? ($moduleEntitlementFlags['ecommerce'] ?? false) : false)
            || $this->containsAny($prompt, ['shop', 'store', 'ecommerce', 'checkout', 'cart', 'product']);

        return [
            'ecommerce' => $ecommerceEnabled,
            'tech' => $this->containsAny($prompt, ['tech', 'software', 'saas', 'startup', 'electronics', 'digital']),
            'warm' => $this->containsAny($prompt, ['restaurant', 'cafe', 'food', 'bakery', 'pizza', 'coffee']),
            'creative' => $this->containsAny($prompt, ['fashion', 'beauty', 'flower', 'art', 'boutique', 'luxury']),
            'health' => $this->containsAny($prompt, ['clinic', 'medical', 'dental', 'wellness', 'therapy']),
        ];
    }

    /**
     * @param  mixed  $modules
     */
    private function readModuleEnabledFlag(mixed $modules, string $key): bool
    {
        if (is_array($modules) && array_is_list($modules)) {
            foreach ($modules as $module) {
                if (! is_array($module)) {
                    continue;
                }

                if ((string) ($module['key'] ?? '') !== $key) {
                    continue;
                }

                if ((bool) ($module['enabled'] ?? false) || (bool) ($module['available'] ?? false)) {
                    return true;
                }
            }

            return false;
        }

        if (is_array($modules) && ! array_is_list($modules)) {
            return (bool) ($modules[$key] ?? false);
        }

        return false;
    }

    /**
     * @param  array<string, bool>  $signals
     * @return array{slug:?string,source:string,reason:string}
     */
    private function chooseTemplateSlug(?string $templateBlueprintSlug, array $signals, string $prompt = ''): array
    {
        if ($templateBlueprintSlug !== null && $templateBlueprintSlug !== '') {
            return [
                'slug' => $templateBlueprintSlug,
                'source' => 'platform_context.template_blueprint',
                'reason' => 'Use current template blueprint as base to avoid parallel template contracts.',
            ];
        }

        if (($signals['ecommerce'] ?? false) === true) {
            $selected = $this->templateSelector->selectFromPrompt($prompt !== '' ? $prompt : 'ecommerce store');
            $slug = $selected['template_id'] ?? 'ecommerce-storefront';
            return [
                'slug' => $slug,
                'source' => 'template_selector',
                'reason' => $selected['reason'] ?? 'Ecommerce signal; template selected from BusinessTemplateMap.',
            ];
        }

        return [
            'slug' => null,
            'source' => 'rule:none',
            'reason' => 'No template override recommendation generated.',
        ];
    }

    /**
     * @param  array<string, bool>  $signals
     * @return array{requested:string,resolved:string,source:string,reason:string,keep_existing:bool}
     */
    private function choosePreset(string $prompt, array $signals, ?string $existingPreset, bool $keepExistingTheme): array
    {
        $source = 'rule:default';
        $reason = 'Fallback preset.';
        $requested = 'default';

        if ($keepExistingTheme && $existingPreset !== null) {
            $requested = $existingPreset;
            $source = 'existing_site_theme';
            $reason = 'Theme preservation requested; keep existing preset.';
        } elseif (($signals['warm'] ?? false) === true) {
            $requested = 'summer';
            $source = 'rule:warm_industry';
            $reason = 'Warm food/hospitality signals detected in prompt.';
        } elseif (($signals['creative'] ?? false) === true) {
            $requested = 'fragrant';
            $source = 'rule:creative_industry';
            $reason = 'Creative/fashion/luxury signals detected in prompt.';
        } elseif (($signals['tech'] ?? false) === true || ($signals['health'] ?? false) === true) {
            $requested = 'arctic';
            $source = 'rule:cool_professional';
            $reason = 'Tech/health/professional signals detected in prompt.';
        } elseif (($signals['ecommerce'] ?? false) === true && $this->containsAny($prompt, ['pet', 'kids', 'playful'])) {
            $requested = 'summer';
            $source = 'rule:ecommerce_playful';
            $reason = 'Ecommerce prompt suggests warm/playful brand direction.';
        }

        $resolved = $this->resolvePresetKey($requested);

        return [
            'requested' => $requested,
            'resolved' => $resolved,
            'source' => $source,
            'reason' => $requested === $resolved ? $reason : 'Requested preset not available; fell back to default.',
            'keep_existing' => $keepExistingTheme,
        ];
    }

    private function resolvePresetKey(string $requested): string
    {
        $catalog = config('theme-presets', []);
        if (is_array($catalog) && isset($catalog[$requested]) && is_array($catalog[$requested])) {
            return $requested;
        }

        return 'default';
    }

    /**
     * @param  array<string, bool>  $signals
     * @param  array<string, mixed>  $userContext
     * @return array<string, mixed>
     */
    private function buildTokenPatch(string $prompt, array $signals, array $userContext): array
    {
        $themeTokens = [
            'version' => CmsThemeTokenLayerResolver::TOKEN_MODEL_VERSION,
        ];

        $primaryColor = $this->inferPrimaryColor($prompt, $signals);
        if ($primaryColor !== null) {
            $themeTokens['colors'] = [
                'primary' => $primaryColor,
            ];
        }

        $radius = $this->inferRadius($prompt, $userContext);
        if ($radius !== null) {
            $themeTokens['radii'] = [
                'base' => $radius,
            ];
        }

        return count($themeTokens) > 1 ? $themeTokens : [];
    }

    /**
     * @param  array<string, bool>  $signals
     */
    private function inferPrimaryColor(string $prompt, array $signals): ?string
    {
        if ($this->containsAny($prompt, ['green', 'eco-friendly', 'sustainable', 'organic', 'nature'])) {
            return '#16a34a';
        }

        if ($this->containsAny($prompt, ['orange', 'warm', 'sunset'])) {
            return '#ea580c';
        }

        if (($signals['tech'] ?? false) === true || ($signals['health'] ?? false) === true) {
            return '#0ea5e9';
        }

        if ($this->containsAny($prompt, ['purple', 'luxury', 'premium'])) {
            return '#7c3aed';
        }

        if ($this->containsAny($prompt, ['pink', 'rose'])) {
            return '#db2777';
        }

        if (($signals['warm'] ?? false) === true) {
            return '#ea580c';
        }

        if (($signals['creative'] ?? false) === true) {
            return '#7c3aed';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $userContext
     */
    private function inferRadius(string $prompt, array $userContext): ?string
    {
        $tone = Str::lower(trim((string) ($userContext['brand_tone'] ?? '')));
        $signalText = trim($prompt.' '.$tone);

        if ($signalText === '') {
            return null;
        }

        if ($this->containsAny($signalText, ['minimal', 'corporate', 'serious', 'professional'])) {
            return '0.375rem';
        }

        if ($this->containsAny($signalText, ['playful', 'friendly', 'kids', 'soft'])) {
            return '0.75rem';
        }

        if ($this->containsAny($signalText, ['luxury', 'elegant', 'premium'])) {
            return '0.625rem';
        }

        return null;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (! is_string($needle)) {
                continue;
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizedNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
