<?php

namespace App\Services;

class CmsAiThemeGenerationEngine
{
    public function __construct(
        protected CmsAiSchemaValidationService $schemaValidator,
        protected CmsThemeTokenValueValidator $themeTokenValidator
    ) {}

    /**
     * Deterministically derive a schema-compatible AI theme output fragment from AI input v1.
     *
     * This engine returns a `theme_output` object that can be embedded directly under
     * `cms-ai-generation-output.v1` (`theme` key), while keeping decision metadata separate.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generateFromAiInput(array $input): array
    {
        $inputValidation = $this->schemaValidator->validateInputPayload($input);
        if (! ($inputValidation['valid'] ?? false)) {
            return [
                'ok' => false,
                'code' => 'invalid_ai_input',
                'errors' => is_array($inputValidation['errors'] ?? null) ? $inputValidation['errors'] : [],
                'warnings' => [],
                'validation' => [
                    'input' => $this->compactValidationReport($inputValidation),
                ],
                'theme_output' => [
                    'theme_settings_patch' => [],
                    'meta' => ['source' => 'keep_existing'],
                ],
            ];
        }

        $request = is_array($input['request'] ?? null) ? $input['request'] : [];
        $platformContext = is_array($input['platform_context'] ?? null) ? $input['platform_context'] : [];
        $constraints = is_array($request['constraints'] ?? null) ? $request['constraints'] : [];
        $preserveThemeSettings = (bool) ($constraints['preserve_theme_settings'] ?? false);

        $templateChoice = $this->resolveTemplateChoice($request, $platformContext);
        $presetChoice = $this->resolvePresetChoice($request, $platformContext, $templateChoice);
        $tokenChoices = $this->resolveTokenChoices($request, $platformContext);

        $rawPatch = $preserveThemeSettings
            ? []
            : $this->buildThemeSettingsPatch(
                presetKey: $presetChoice['resolved_key'],
                tokenChoices: $tokenChoices,
                currentThemeSettings: $this->extractCurrentThemeSettings($platformContext)
            );
        $themeSettingsPatch = $this->filterThemeSettingsPatchToAllowedKeys($rawPatch);

        $tokenValidation = $this->themeTokenValidator->validate($themeSettingsPatch);
        if (! ($tokenValidation['valid'] ?? false)) {
            return [
                'ok' => false,
                'code' => 'theme_token_validation_failed',
                'errors' => [[
                    'code' => 'theme_token_validation_failed',
                    'path' => '$.theme_output.theme_settings_patch',
                    'message' => 'Generated theme_settings_patch failed canonical theme token validation.',
                    'expected' => 'valid canonical theme token values',
                    'actual' => 'invalid theme_settings_patch',
                    'details' => $tokenValidation['errors'] ?? [],
                ]],
                'warnings' => [],
                'validation' => [
                    'input' => $this->compactValidationReport($inputValidation),
                    'theme_tokens' => $tokenValidation,
                ],
                'decisions' => [
                    'template_choice' => $templateChoice,
                    'preset_choice' => $presetChoice,
                    'token_choices' => $tokenChoices,
                ],
                'theme_output' => [
                    'theme_settings_patch' => $themeSettingsPatch,
                    'meta' => ['source' => 'generated'],
                ],
            ];
        }

        $themeOutput = [
            'theme_settings_patch' => $themeSettingsPatch,
            'meta' => [
                'source' => $this->resolveThemeSource($preserveThemeSettings, $presetChoice),
            ],
        ];

        $siteLayoutPatch = $preserveThemeSettings ? [] : $this->buildOptionalSiteLayoutPatch($request, $templateChoice);
        if ($siteLayoutPatch !== []) {
            $themeOutput['site_layout_patch'] = $siteLayoutPatch;
        }

        $warnings = [];
        if ($preserveThemeSettings) {
            $warnings[] = 'preserve_theme_settings=true; returned keep_existing theme output with no patch.';
        }
        if (($presetChoice['fallback_applied'] ?? false) === true) {
            $warnings[] = 'Resolved preset fell back to default because requested preset was unavailable.';
        }
        if (($templateChoice['recommended_slug'] ?? null) === null && ! ($templateChoice['keep_current'] ?? false)) {
            $warnings[] = 'Template family was inferred without a concrete template slug recommendation.';
        }

        return [
            'ok' => true,
            'warnings' => $warnings,
            'theme_output' => $themeOutput,
            'decisions' => [
                'template_choice' => $templateChoice,
                'preset_choice' => $presetChoice,
                'token_choices' => $tokenChoices,
            ],
            'validation' => [
                'input' => $this->compactValidationReport($inputValidation),
                'theme_tokens' => $tokenValidation,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $platformContext
     * @return array<string, mixed>
     */
    private function resolveTemplateChoice(array $request, array $platformContext): array
    {
        $currentTemplateSlug = $this->normalizeString(data_get($platformContext, 'template_blueprint.template_slug'));
        $family = $this->inferTemplateFamily($request, $platformContext);
        $recommendedSlug = $this->recommendedTemplateSlugForFamily($family, $currentTemplateSlug);
        $keepCurrent = $currentTemplateSlug !== null && ($recommendedSlug === $currentTemplateSlug || $recommendedSlug === null);

        $reason = match (true) {
            $this->requestHasEcommerceSignal($request, $platformContext) && $family === 'ecommerce' => 'ecommerce_signal',
            $this->requestHasBookingSignal($request, $platformContext) && $family === 'booking' => 'booking_signal',
            $this->requestHasRestaurantSignal($request) && $family === 'restaurant' => 'industry_keyword',
            $this->requestHasPortfolioSignal($request) && $family === 'portfolio' => 'industry_keyword',
            $currentTemplateSlug !== null => 'template_blueprint',
            default => 'fallback',
        };

        return [
            'family' => $family,
            'current_template_slug' => $currentTemplateSlug,
            'recommended_slug' => $recommendedSlug,
            'keep_current' => $keepCurrent,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $platformContext
     * @param  array<string, mixed>  $templateChoice
     * @return array<string, mixed>
     */
    private function resolvePresetChoice(array $request, array $platformContext, array $templateChoice): array
    {
        $catalog = config('theme-presets', []);
        $catalogKeys = is_array($catalog) ? array_values(array_filter(array_keys($catalog), 'is_string')) : [];
        $haystack = $this->combinedSignalText($request, $platformContext);

        $explicitPreset = $this->extractExplicitPresetMention($haystack, $catalogKeys);
        if ($explicitPreset !== null) {
            return $this->finalizePresetChoice($explicitPreset, 'explicit_prompt', $catalogKeys);
        }

        $keywordPreset = $this->presetFromKeywordHeuristics($haystack, (string) ($templateChoice['family'] ?? 'generic'));
        if ($keywordPreset !== null) {
            return $this->finalizePresetChoice($keywordPreset, 'keyword_profile', $catalogKeys);
        }

        $existingPreset = $this->normalizePresetKey($this->extractExistingPresetKey($platformContext));
        if ($existingPreset !== null) {
            return $this->finalizePresetChoice($existingPreset, 'existing_site_theme', $catalogKeys);
        }

        $familyPreset = $this->defaultPresetForFamily((string) ($templateChoice['family'] ?? 'generic'));
        return $this->finalizePresetChoice($familyPreset, 'template_family_default', $catalogKeys);
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $platformContext
     * @return array<string, mixed>
     */
    private function resolveTokenChoices(array $request, array $platformContext): array
    {
        $haystack = $this->combinedSignalText($request, $platformContext);

        $radiusProfile = match (true) {
            $this->containsAnyKeyword($haystack, ['minimal', 'corporate', 'enterprise', 'legal', 'finance', 'clean']) => 'compact',
            $this->containsAnyKeyword($haystack, ['friendly', 'playful', 'rounded', 'soft', 'kids', 'beauty', 'boutique']) => 'rounded',
            default => 'balanced',
        };

        $shadowProfile = match (true) {
            $this->containsAnyKeyword($haystack, ['flat', 'minimal', 'corporate', 'clean']) => 'minimal',
            $this->containsAnyKeyword($haystack, ['luxury', 'dramatic', 'premium', 'bold']) => 'strong',
            default => 'soft',
        };

        $spacingProfile = match (true) {
            $this->containsAnyKeyword($haystack, ['compact', 'dense', 'enterprise', 'dashboard']) => 'compact',
            $this->containsAnyKeyword($haystack, ['airy', 'editorial', 'spacious', 'luxury']) => 'airy',
            default => 'balanced',
        };

        return [
            'brand_primary_color' => $this->extractFirstHexColor($haystack),
            'brand_secondary_color' => $this->extractSecondHexColor($haystack),
            'radius_profile' => $radiusProfile,
            'shadow_profile' => $shadowProfile,
            'spacing_profile' => $spacingProfile,
            'preferred_mode' => $this->containsAnyKeyword($haystack, ['dark mode', 'dark theme', 'dark']) ? 'dark' : null,
        ];
    }

    /**
     * Build theme_settings_patch with only PART 6–allowed tokens: primary, secondary, font, button radius, spacing.
     * No shadows, breakpoints, or layout — AI may not change layout structure.
     *
     * @param  array<string, mixed>  $tokenChoices
     * @param  array<string, mixed>  $currentThemeSettings
     * @return array<string, mixed>
     */
    private function buildThemeSettingsPatch(string $presetKey, array $tokenChoices, array $currentThemeSettings): array
    {
        $patch = [
            'preset' => $presetKey,
            'theme_tokens' => [
                'version' => CmsThemeTokenLayerResolver::TOKEN_MODEL_VERSION,
                'radii' => $this->radiiTokensForProfile((string) ($tokenChoices['radius_profile'] ?? 'balanced')),
                'spacing' => $this->spacingTokensForProfile((string) ($tokenChoices['spacing_profile'] ?? 'balanced')),
            ],
        ];

        $brandPrimary = $this->normalizeHexColor($tokenChoices['brand_primary_color'] ?? null);
        $brandSecondary = $this->normalizeHexColor($tokenChoices['brand_secondary_color'] ?? null);
        $patch['theme_tokens']['colors'] = [];
        if ($brandPrimary !== null) {
            $patch['colors'] = ['primary' => $brandPrimary];
            $patch['theme_tokens']['colors']['primary'] = $brandPrimary;
            $patch['theme_tokens']['colors']['ring'] = $brandPrimary;
        }
        if ($brandSecondary !== null) {
            $patch['colors'] = ($patch['colors'] ?? []) + ['secondary' => $brandSecondary];
            $patch['theme_tokens']['colors']['secondary'] = $brandSecondary;
        }
        if ($patch['theme_tokens']['colors'] === []) {
            unset($patch['theme_tokens']['colors']);
        }

        $existingTypography = is_array($currentThemeSettings['typography'] ?? null) ? $currentThemeSettings['typography'] : [];
        if ($existingTypography !== []) {
            $patch['typography'] = $this->filterScalarTree($existingTypography);
        }

        return $patch;
    }

    /**
     * Restrict theme_settings_patch to PART 6 allowed keys only (primary, secondary, font, button radius, spacing).
     *
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function filterThemeSettingsPatchToAllowedKeys(array $patch): array
    {
        if ($patch === []) {
            return [];
        }

        $allowedTop = config('design-defaults.cms_ai_theme_allowed_patch_keys', ['preset', 'colors', 'theme_tokens', 'typography']);
        $allowedTokenGroups = config('design-defaults.cms_ai_theme_allowed_token_groups', ['version', 'colors', 'radii', 'spacing']);

        $out = [];
        foreach ($allowedTop as $key) {
            if (! array_key_exists($key, $patch)) {
                continue;
            }
            if ($key === 'theme_tokens') {
                $tokens = is_array($patch['theme_tokens']) ? $patch['theme_tokens'] : [];
                $filtered = [];
                foreach ($allowedTokenGroups as $group) {
                    if (array_key_exists($group, $tokens)) {
                        $filtered[$group] = $tokens[$group];
                    }
                }
                if ($filtered !== []) {
                    $out['theme_tokens'] = $filtered;
                }
                continue;
            }
            $out[$key] = $patch[$key];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $templateChoice
     * @return array<string, mixed>
     */
    private function buildOptionalSiteLayoutPatch(array $request, array $templateChoice): array
    {
        $haystack = $this->combinedSignalText($request, []);
        $layout = [];

        if ($this->containsAnyKeyword($haystack, ['newsletter popup', 'promo popup', 'discount popup', 'exit intent'])) {
            $layout['popup_modal'] = [
                'enabled' => true,
                'headline' => 'Stay in the loop',
                'description' => 'Get updates and offers from the site.',
                'button_label' => 'Subscribe',
            ];
        }

        return $layout;
    }

    /**
     * @param  array<string, mixed>  $platformContext
     * @return array<string, mixed>
     */
    private function extractCurrentThemeSettings(array $platformContext): array
    {
        $candidates = [
            data_get($platformContext, 'site.theme_settings'),
            data_get($platformContext, 'site_settings_snapshot.site.theme_settings'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $platformContext
     */
    private function inferTemplateFamily(array $request, array $platformContext): string
    {
        if ($this->requestHasEcommerceSignal($request, $platformContext)) {
            return 'ecommerce';
        }

        if ($this->requestHasBookingSignal($request, $platformContext)) {
            return 'booking';
        }

        if ($this->requestHasRestaurantSignal($request)) {
            return 'restaurant';
        }

        if ($this->requestHasPortfolioSignal($request)) {
            return 'portfolio';
        }

        $haystack = $this->combinedSignalText($request, $platformContext);
        if ($this->containsAnyKeyword($haystack, ['blog', 'magazine', 'news', 'article'])) {
            return 'blog';
        }

        return 'business';
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $platformContext
     */
    private function requestHasEcommerceSignal(array $request, array $platformContext): bool
    {
        if ((bool) data_get($request, 'constraints.allow_ecommerce', false)) {
            return true;
        }

        $haystack = $this->combinedSignalText($request, $platformContext);
        if ($this->containsAnyKeyword($haystack, ['ecommerce', 'e-commerce', 'store', 'shop', 'cart', 'checkout', 'product listing', 'product page'])) {
            return true;
        }

        return $this->moduleSignalsContain($platformContext, ['ecommerce', 'shop', 'storefront']);
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $platformContext
     */
    private function requestHasBookingSignal(array $request, array $platformContext): bool
    {
        if ((bool) data_get($request, 'constraints.allow_booking', false)) {
            return true;
        }

        $haystack = $this->combinedSignalText($request, $platformContext);
        if ($this->containsAnyKeyword($haystack, ['booking', 'appointment', 'reservation', 'calendar'])) {
            return true;
        }

        return $this->moduleSignalsContain($platformContext, ['booking', 'appointments', 'reservations']);
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function requestHasRestaurantSignal(array $request): bool
    {
        return $this->containsAnyKeyword(
            $this->combinedSignalText($request, []),
            ['restaurant', 'cafe', 'bakery', 'coffee', 'food truck', 'menu']
        );
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function requestHasPortfolioSignal(array $request): bool
    {
        return $this->containsAnyKeyword(
            $this->combinedSignalText($request, []),
            ['portfolio', 'photography', 'designer', 'agency', 'studio']
        );
    }

    /**
     * @param  array<string>  $catalogKeys
     * @return array<string, mixed>
     */
    private function finalizePresetChoice(string $requestedKey, string $reason, array $catalogKeys): array
    {
        $requestedKey = $this->normalizePresetKey($requestedKey) ?? 'default';
        $catalogExists = in_array($requestedKey, $catalogKeys, true);
        $resolvedKey = $catalogExists ? $requestedKey : 'default';

        return [
            'requested_key' => $requestedKey,
            'resolved_key' => $resolvedKey,
            'catalog_exists' => $catalogExists,
            'fallback_applied' => $resolvedKey !== $requestedKey,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string>  $catalogKeys
     */
    private function extractExplicitPresetMention(string $haystack, array $catalogKeys): ?string
    {
        foreach ($catalogKeys as $catalogKey) {
            $pattern = '/\b'.str_replace('\-', '[-_ ]', preg_quote($catalogKey, '/')).'\b/i';
            if (preg_match($pattern, $haystack) === 1) {
                return $catalogKey;
            }
        }

        return null;
    }

    private function presetFromKeywordHeuristics(string $haystack, string $templateFamily): ?string
    {
        $keywordMap = [
            'forest' => ['forest', 'organic', 'eco', 'nature', 'garden', 'farm', 'botanical', 'green'],
            'feminine' => ['beauty', 'cosmetic', 'skincare', 'fashion', 'boutique', 'salon', 'wedding'],
            'fragrant' => ['perfume', 'fragrance', 'floral', 'spa'],
            'arctic' => ['tech', 'saas', 'software', 'startup', 'cloud', 'ai', 'cyber'],
            'slate' => ['corporate', 'finance', 'legal', 'consulting', 'enterprise', 'b2b'],
            'summer' => ['summer', 'food', 'restaurant', 'bakery', 'playful', 'kids'],
            'mocha' => ['coffee', 'cafe', 'artisan', 'roastery'],
            'ocean' => ['ocean', 'beach', 'travel', 'hotel', 'marine', 'resort'],
            'midnight' => ['midnight', 'luxury', 'premium', 'dark elegant', 'dramatic'],
            'ruby' => ['ruby', 'jewelry', 'fine dining', 'romantic'],
            'coral' => ['creative', 'agency', 'lifestyle', 'vibrant'],
        ];

        foreach ($keywordMap as $preset => $keywords) {
            if ($this->containsAnyKeyword($haystack, $keywords)) {
                return $preset;
            }
        }

        return match ($templateFamily) {
            'ecommerce' => 'ocean',
            'restaurant' => 'summer',
            'portfolio' => 'coral',
            'booking' => 'arctic',
            'business' => 'slate',
            default => null,
        };
    }

    private function defaultPresetForFamily(string $family): string
    {
        return match ($family) {
            'ecommerce' => 'ocean',
            'restaurant' => 'summer',
            'portfolio' => 'coral',
            'booking' => 'arctic',
            'blog' => 'slate',
            'business' => 'slate',
            default => 'default',
        };
    }

    private function recommendedTemplateSlugForFamily(string $family, ?string $currentTemplateSlug): ?string
    {
        if ($currentTemplateSlug !== null && $this->templateSlugMatchesFamily($currentTemplateSlug, $family)) {
            return $currentTemplateSlug;
        }

        return match ($family) {
            'ecommerce' => 'ecommerce',
            default => $currentTemplateSlug,
        };
    }

    private function templateSlugMatchesFamily(string $slug, string $family): bool
    {
        $normalized = strtolower($slug);

        return match ($family) {
            'ecommerce' => str_contains($normalized, 'shop') || str_contains($normalized, 'store'),
            'restaurant' => str_contains($normalized, 'restaurant') || str_contains($normalized, 'food') || str_contains($normalized, 'cafe'),
            'portfolio' => str_contains($normalized, 'portfolio') || str_contains($normalized, 'agency') || str_contains($normalized, 'studio'),
            'booking' => str_contains($normalized, 'booking') || str_contains($normalized, 'reservation'),
            'blog' => str_contains($normalized, 'blog') || str_contains($normalized, 'news'),
            'business' => str_contains($normalized, 'business') || str_contains($normalized, 'corporate'),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $platformContext
     */
    private function extractExistingPresetKey(array $platformContext): ?string
    {
        $candidates = [
            data_get($platformContext, 'site.theme_settings.preset'),
            data_get($platformContext, 'site_settings_snapshot.site.theme_settings.preset'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizePresetKey($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $platformContext
     * @param  array<string>  $needles
     */
    private function moduleSignalsContain(array $platformContext, array $needles): bool
    {
        $modules = data_get($platformContext, 'module_registry.modules');
        if (! is_array($modules)) {
            return false;
        }

        $signals = [];
        foreach ($modules as $module) {
            if (! is_array($module)) {
                continue;
            }

            foreach (['key', 'slug', 'name', 'code'] as $field) {
                $value = $this->normalizeString($module[$field] ?? null);
                if ($value !== null) {
                    $signals[] = $value;
                }
            }
        }

        if ($signals === []) {
            return false;
        }

        $haystack = implode(' ', $signals);
        return $this->containsAnyKeyword($haystack, $needles);
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $platformContext
     */
    private function combinedSignalText(array $request, array $platformContext): string
    {
        $parts = [];

        $parts[] = (string) ($request['prompt'] ?? '');
        $userContext = is_array($request['user_context'] ?? null) ? $request['user_context'] : [];
        foreach (['industry', 'business_name', 'brand_tone'] as $key) {
            $parts[] = (string) ($userContext[$key] ?? '');
        }

        $languages = is_array($userContext['languages'] ?? null) ? $userContext['languages'] : [];
        foreach ($languages as $language) {
            if (is_scalar($language)) {
                $parts[] = (string) $language;
            }
        }

        $parts[] = (string) data_get($platformContext, 'template_blueprint.template_slug', '');

        return strtolower(trim(implode(' ', array_filter($parts, static fn ($value) => trim((string) $value) !== ''))));
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function compactValidationReport(array $report): array
    {
        return [
            'valid' => (bool) ($report['valid'] ?? false),
            'schema' => $report['schema'] ?? null,
            'error_count' => (int) ($report['error_count'] ?? 0),
        ];
    }

    private function resolveThemeSource(bool $preserveThemeSettings, array $presetChoice): string
    {
        if ($preserveThemeSettings) {
            return 'keep_existing';
        }

        if (in_array(($presetChoice['reason'] ?? null), ['existing_site_theme', 'template_family_default'], true)) {
            return 'template_derived';
        }

        return 'generated';
    }

    /**
     * @return array<string, string>
     */
    private function radiiTokensForProfile(string $profile): array
    {
        return match ($profile) {
            'compact' => [
                'base' => '0.375rem',
                'card' => '0.5rem',
                'button' => '0.5rem',
            ],
            'rounded' => [
                'base' => '0.75rem',
                'card' => '1rem',
                'button' => '999px',
            ],
            default => [
                'base' => '0.5rem',
                'card' => '0.75rem',
                'button' => '0.75rem',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function spacingTokensForProfile(string $profile): array
    {
        $defaults = config('design-defaults.spacing', []);
        $fallback = [
            'section_y' => $defaults['section_y'] ?? '4rem',
            'stack_gap' => $defaults['stack_gap'] ?? '1rem',
            'container_x' => $defaults['container_x'] ?? '1.25rem',
        ];
        return match ($profile) {
            'compact' => [
                'section_y' => '3rem',
                'stack_gap' => '0.75rem',
                'container_x' => '1rem',
            ],
            'airy' => [
                'section_y' => '6rem',
                'stack_gap' => '1.5rem',
                'container_x' => '1.5rem',
            ],
            default => $fallback,
        };
    }

    /**
     * @return array<string, string>
     */
    private function shadowTokensForProfile(string $profile): array
    {
        return match ($profile) {
            'minimal' => [
                'card' => '0 1px 2px rgba(15, 23, 42, 0.06)',
                'elevated' => '0 4px 10px rgba(15, 23, 42, 0.08)',
            ],
            'strong' => [
                'card' => '0 12px 32px rgba(15, 23, 42, 0.18)',
                'elevated' => '0 24px 48px rgba(15, 23, 42, 0.24)',
            ],
            default => [
                'card' => '0 8px 24px rgba(15, 23, 42, 0.10)',
                'elevated' => '0 16px 36px rgba(15, 23, 42, 0.14)',
            ],
        };
    }

    /**
     * @param  array<string>  $keywords
     */
    private function containsAnyKeyword(string $haystack, array $keywords): bool
    {
        if ($haystack === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            $needle = strtolower(trim((string) $keyword));
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function extractFirstHexColor(string $text): ?string
    {
        if (preg_match('/#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b/', $text, $matches) !== 1) {
            return null;
        }

        return $this->normalizeHexColor($matches[0] ?? null);
    }

    private function extractSecondHexColor(string $text): ?string
    {
        $count = preg_match_all('/#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b/', $text, $matches);
        if ($count < 2) {
            return null;
        }

        return $this->normalizeHexColor($matches[0][1] ?? null);
    }

    private function normalizeHexColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = strtolower(trim($value));
        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $trimmed) !== 1) {
            return null;
        }

        return $trimmed;
    }

    private function normalizePresetKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function filterScalarTree(array $input): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->filterScalarTree($value);
                if ($nested !== []) {
                    $result[$key] = $nested;
                }
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
