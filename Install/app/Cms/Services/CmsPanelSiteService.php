<?php

namespace App\Cms\Services;

use App\Cms\Contracts\CmsPanelSiteServiceContract;
use App\Cms\Contracts\CmsRepositoryContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Cms\Support\LocalizedCmsPayload;
use App\Models\Site;
use App\Models\SiteCustomFont;
use App\Models\User;
use App\Services\CmsTypographyService;
use App\Services\CmsLocaleResolver;
use App\Services\CmsThemeTokenLayerResolver;
use App\Services\CmsThemeTokenValueValidator;
use App\Services\SiteCustomFontService;
use InvalidArgumentException;
use Illuminate\Http\UploadedFile;

class CmsPanelSiteService implements CmsPanelSiteServiceContract
{
    public function __construct(
        protected CmsRepositoryContract $repository,
        protected CmsTypographyService $typography,
        protected SiteCustomFontService $customFonts,
        protected LocalizedCmsPayload $localizedPayload,
        protected CmsThemeTokenLayerResolver $themeTokenLayers,
        protected CmsThemeTokenValueValidator $themeTokenValidator
    ) {}

    public function settings(Site $site, ?string $requestedLocale = null): array
    {
        $site = $this->repository->loadSiteGlobalSettings($site);
        $global = $site->globalSettings;
        $logoPath = $global?->logoMedia?->path;
        $siteLocale = $this->localizedPayload->normalizeLocale($site->locale, CmsLocaleResolver::PRIMARY_LOCALE);
        $resolvedLocale = $this->localizedPayload->normalizeLocale($requestedLocale, $siteLocale);
        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $configuredLocales = $this->configuredAvailableLocales($themeSettings, $siteLocale);

        $contactPayload = $this->localizedPayload->resolve($global?->contact_json ?? [], $resolvedLocale, $siteLocale);
        $socialPayload = $this->localizedPayload->resolve($global?->social_links_json ?? [], $resolvedLocale, $siteLocale);
        $analyticsPayload = $this->localizedPayload->resolve($global?->analytics_ids_json ?? [], $resolvedLocale, $siteLocale);
        $uiTranslationsPayload = $this->localizedPayload->resolve(
            $themeSettings['ui_translations'] ?? [],
            $resolvedLocale,
            $siteLocale
        );

        $availableLocales = $this->localizedPayload->mergeLocaleList(
            $themeSettings['ui_translations'] ?? [],
            array_merge(
                $configuredLocales,
                $contactPayload['available_locales'],
                $socialPayload['available_locales'],
                $analyticsPayload['available_locales']
            ),
            $siteLocale
        );
        $resolvedTypography = $this->typography->resolveTypography($site->theme_settings ?? [], $site);
        $typographyPolicy = $this->typography->planPolicy($site);

        return [
            'site' => [
                'id' => $site->id,
                'project_id' => $site->project_id,
                'name' => $site->name,
                'status' => $site->status,
                'locale' => $site->locale,
                'primary_domain' => $site->primary_domain,
                'subdomain' => $site->subdomain,
                'theme_settings' => $themeSettings,
                'updated_at' => $site->updated_at?->toISOString(),
            ],
            'typography' => $resolvedTypography,
            'theme_token_layers' => $this->themeTokenLayers->resolveForSite($site),
            'available_fonts' => $this->typography->availableFonts($site),
            'typography_policy' => $typographyPolicy,
            'global_settings' => [
                'logo_media_id' => $global?->logo_media_id,
                'logo_asset_url' => $logoPath ? route('public.sites.assets', ['site' => $site->id, 'path' => $logoPath]) : null,
                'contact_json' => $contactPayload['content'],
                'social_links_json' => $socialPayload['content'],
                'analytics_ids_json' => $analyticsPayload['content'],
            ],
            'localization' => [
                'default_locale' => $siteLocale,
                'requested_locale' => $requestedLocale,
                'resolved_locale' => $resolvedLocale,
                'available_locales' => $availableLocales,
                'ui_translations' => is_array($uiTranslationsPayload['content']) ? $uiTranslationsPayload['content'] : [],
            ],
        ];
    }

    public function updateSettings(Site $site, array $payload): void
    {
        $existingThemeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $siteLocale = $this->localizedPayload->normalizeLocale($site->locale, CmsLocaleResolver::PRIMARY_LOCALE);
        $siteUpdates = [];

        foreach (['name', 'locale'] as $field) {
            if (array_key_exists($field, $payload)) {
                $siteUpdates[$field] = $payload[$field];
            }
        }

        if (array_key_exists('locale', $siteUpdates)) {
            $siteLocale = $this->localizedPayload->normalizeLocale((string) $siteUpdates['locale'], $siteLocale);
        }

        $translationLocale = $this->localizedPayload->normalizeLocale(
            $payload['translation_locale'] ?? null,
            $siteLocale
        );

        $nextThemeSettings = $existingThemeSettings;
        if (array_key_exists('theme_settings', $payload) && is_array($payload['theme_settings'])) {
            $nextThemeSettings = $payload['theme_settings'];
        }

        $configuredLocales = $this->configuredAvailableLocales($nextThemeSettings, $siteLocale);
        if (array_key_exists('available_locales', $payload) && is_array($payload['available_locales'])) {
            $configuredLocales = $this->sanitizeLocaleList($payload['available_locales'], $siteLocale);
        }
        $configuredLocales = $this->sanitizeLocaleList($configuredLocales, $siteLocale);

        $nextThemeSettings['localization'] = [
            ...(is_array($nextThemeSettings['localization'] ?? null) ? $nextThemeSettings['localization'] : []),
            'available_locales' => $configuredLocales,
        ];

        if (array_key_exists('ui_translations', $payload) && is_array($payload['ui_translations'])) {
            $nextThemeSettings['ui_translations'] = $this->localizedPayload->mergeForLocale(
                $nextThemeSettings['ui_translations'] ?? [],
                $translationLocale,
                $payload['ui_translations'],
                $siteLocale,
                $configuredLocales
            );
        }

        $nextThemeSettings = $this->normalizeCanonicalThemeTokenSettings($nextThemeSettings);
        $this->themeTokenValidator->assertValidThemeSettings($nextThemeSettings);

        $unsupported = $this->typography->findUnsupportedFontKeys($nextThemeSettings, $site);
        if ($unsupported !== []) {
            throw new CmsDomainException(
                sprintf('Unsupported typography font key(s): %s', implode(', ', $unsupported)),
                422,
                ['allowed_font_keys' => $this->typography->fontKeys($site)]
            );
        }

        $normalizedThemeSettings = $this->typography->normalizeThemeSettings($nextThemeSettings, $site);
        if ($normalizedThemeSettings !== (is_array($site->theme_settings) ? $site->theme_settings : [])) {
            $siteUpdates['theme_settings'] = $normalizedThemeSettings;
        }

        if ($siteUpdates !== []) {
            $site = $this->repository->updateSite($site, $siteUpdates);
        }

        $global = $this->repository->firstOrCreateGlobalSetting($site);
        $globalUpdates = [];

        foreach (['contact_json', 'social_links_json', 'analytics_ids_json'] as $field) {
            if (! array_key_exists($field, $payload) || ! is_array($payload[$field])) {
                continue;
            }

            $globalUpdates[$field] = $this->localizedPayload->mergeForLocale(
                $global?->{$field} ?? [],
                $translationLocale,
                $payload[$field],
                $siteLocale,
                $configuredLocales
            );
        }

        if (array_key_exists('logo_media_id', $payload)) {
            $logoMediaId = $payload['logo_media_id'];
            if ($logoMediaId !== null && ! $this->repository->findMediaById($site, $logoMediaId)) {
                throw new CmsDomainException('Selected logo media does not belong to this site.', 422);
            }

            $globalUpdates['logo_media_id'] = $logoMediaId;
        }

        if ($globalUpdates !== []) {
            $this->repository->updateGlobalSetting($global, $globalUpdates);
        }
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     * @return array<int, string>
     */
    private function configuredAvailableLocales(array $themeSettings, string $siteLocale): array
    {
        $localization = is_array($themeSettings['localization'] ?? null)
            ? $themeSettings['localization']
            : [];
        $rawLocales = is_array($localization['available_locales'] ?? null)
            ? $localization['available_locales']
            : [];

        return $this->sanitizeLocaleList($rawLocales, $siteLocale);
    }

    /**
     * @param  array<int, mixed>  $locales
     * @return array<int, string>
     */
    private function sanitizeLocaleList(array $locales, string $siteLocale): array
    {
        $normalized = [];
        foreach ($locales as $locale) {
            if (! is_string($locale)) {
                continue;
            }

            $value = $this->localizedPayload->normalizeLocaleOrNull($locale);
            if ($value !== null) {
                $normalized[] = $value;
            }
        }

        return $this->localizedPayload->mergeLocaleList([], array_merge($normalized, [$siteLocale]), $siteLocale);
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     * @return array<string, mixed>
     */
    private function normalizeCanonicalThemeTokenSettings(array $themeSettings): array
    {
        $next = $themeSettings;

        $layout = is_array($next['layout'] ?? null) ? $next['layout'] : [];
        if ($layout !== []) {
            $layout['version'] = is_int($layout['version'] ?? null) ? (int) $layout['version'] : 1;
            $next['layout'] = $layout;
        }

        $legacyColors = is_array($next['colors'] ?? null) ? $next['colors'] : [];
        $themeTokens = is_array($next['theme_tokens'] ?? null)
            ? $next['theme_tokens']
            : (is_array($next['tokens'] ?? null) ? $next['tokens'] : []);
        $canonicalColors = is_array($themeTokens['colors'] ?? null) ? $themeTokens['colors'] : [];

        foreach (['primary', 'secondary', 'accent'] as $colorKey) {
            $value = $legacyColors[$colorKey] ?? null;
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $canonicalColors[$colorKey] = $trimmed;
        }

        if ($canonicalColors !== []) {
            $themeTokens['colors'] = $canonicalColors;
        }

        if ($themeTokens !== []) {
            $themeTokens['version'] = is_int($themeTokens['version'] ?? null) ? (int) $themeTokens['version'] : 1;
            $next['theme_tokens'] = $themeTokens;
        }

        return $next;
    }

    public function typography(Site $site): array
    {
        $typographyPolicy = $this->typography->planPolicy($site);

        return [
            'site_id' => $site->id,
            'typography' => $this->typography->resolveTypography($site->theme_settings ?? [], $site),
            'available_fonts' => $this->typography->availableFonts($site),
            'typography_policy' => $typographyPolicy,
            'updated_at' => $site->updated_at?->toISOString(),
        ];
    }

    public function updateTypography(Site $site, array $payload): array
    {
        try {
            $result = $this->typography->applyTypographyToThemeSettings(
                is_array($site->theme_settings) ? $site->theme_settings : [],
                $payload,
                $site
            );
        } catch (InvalidArgumentException $exception) {
            throw new CmsDomainException(
                $exception->getMessage(),
                422,
                ['allowed_font_keys' => $this->typography->fontKeys($site)]
            );
        }

        $site = $this->repository->updateSite($site, [
            'theme_settings' => $result['theme_settings'],
        ]);

        return [
            'message' => 'Typography settings updated successfully.',
            'site_id' => $site->id,
            'typography' => $result['typography'],
            'available_fonts' => $this->typography->availableFonts($site),
            'typography_policy' => $this->typography->planPolicy($site),
            'updated_at' => $site->updated_at?->toISOString(),
        ];
    }

    public function allowedFontKeys(Site $site): array
    {
        return $this->typography->fontKeys($site);
    }

    public function uploadCustomFont(Site $site, UploadedFile $file, User $user, array $payload): array
    {
        $typographyPolicy = $this->typography->planPolicy($site);
        if (! $typographyPolicy['custom_fonts_enabled']) {
            throw new CmsDomainException(
                'Your plan does not include custom font uploads.',
                403,
                [
                    'code' => 'site_entitlement_required',
                    'feature' => 'typography',
                    'reason' => 'custom_fonts_not_enabled',
                    'site_id' => $site->id,
                    'plan_slug' => $typographyPolicy['plan_slug'],
                ]
            );
        }

        $font = $this->customFonts->upload($site, $file, $user, $payload);

        if (
            is_array($typographyPolicy['allowed_font_keys'])
            && ! in_array((string) $font->key, $typographyPolicy['allowed_font_keys'], true)
        ) {
            $this->customFonts->delete($site, $font);

            throw new CmsDomainException(
                'Uploaded font key is not allowed for your current plan.',
                403,
                [
                    'code' => 'site_entitlement_required',
                    'feature' => 'typography',
                    'reason' => 'typography_font_key_not_allowed',
                    'site_id' => $site->id,
                    'plan_slug' => $typographyPolicy['plan_slug'],
                    'font_key' => (string) $font->key,
                    'allowed_font_keys' => $typographyPolicy['allowed_font_keys'],
                ]
            );
        }

        $availableFonts = $this->typography->availableFonts($site);

        return [
            'message' => 'Custom font uploaded successfully.',
            'site_id' => $site->id,
            'font' => collect($availableFonts)->firstWhere('key', $font->key),
            'typography' => $this->typography->resolveTypography($site->theme_settings ?? [], $site),
            'available_fonts' => $availableFonts,
            'typography_policy' => $typographyPolicy,
        ];
    }

    public function deleteCustomFont(Site $site, SiteCustomFont $font): array
    {
        $this->customFonts->delete($site, $font);

        $site = $site->refresh();
        $normalizedTheme = $this->typography->normalizeThemeSettings(
            is_array($site->theme_settings) ? $site->theme_settings : [],
            $site
        );

        if ($normalizedTheme !== (is_array($site->theme_settings) ? $site->theme_settings : [])) {
            $site = $this->repository->updateSite($site, [
                'theme_settings' => $normalizedTheme,
            ]);
        }

        return [
            'message' => 'Custom font deleted successfully.',
            'site_id' => $site->id,
            'typography' => $this->typography->resolveTypography($site->theme_settings ?? [], $site),
            'available_fonts' => $this->typography->availableFonts($site),
            'typography_policy' => $this->typography->planPolicy($site),
        ];
    }
}
