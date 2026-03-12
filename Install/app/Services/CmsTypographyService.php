<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteCustomFont;
use InvalidArgumentException;

class CmsTypographyService
{
    public const CONTRACT_VERSION = 1;

    private const DEFAULT_FALLBACK_STACK = '"Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif';

    public function __construct(
        protected SiteCustomFontService $customFonts
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableFonts(?Site $site = null): array
    {
        $fonts = $this->configuredFonts();
        $planPolicy = $site instanceof Site
            ? $this->planPolicy($site)
            : $this->defaultPlanPolicy();

        if ($site instanceof Site) {
            if ($planPolicy['custom_fonts_enabled']) {
                $fonts = $this->mergeCustomSiteFonts($fonts, $site);
            }

            $fonts = $this->filterFontsByAllowlist($fonts, $planPolicy['allowed_font_keys']);
        }

        if ($fonts === []) {
            $fallbackKey = 'tbc-contractica';
            $fonts[$fallbackKey] = [
                'key' => $fallbackKey,
                'label' => 'TBC Contractica',
                'stack' => '"TBC Contractica", "Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif',
                'is_default' => true,
                'source_type' => 'system',
                'custom_font_id' => null,
                'font_faces' => [],
            ];

            return array_values($fonts);
        }

        $keys = array_keys($fonts);
        $configuredDefault = $this->normalizeKey(config('cms.typography.default_font_key'));
        $defaultKey = ($configuredDefault !== null && in_array($configuredDefault, $keys, true))
            ? $configuredDefault
            : $keys[0];

        foreach ($fonts as $key => $font) {
            $fonts[$key]['is_default'] = $key === $defaultKey;
        }

        return array_values($fonts);
    }

    /**
     * Resolve typography entitlement policy for a site owner plan.
     *
     * @return array{
     *   plan_slug: string|null,
     *   custom_fonts_enabled: bool,
     *   allowed_font_keys: array<int, string>|null
     * }
     */
    public function planPolicy(Site $site): array
    {
        $site->loadMissing('project.user.plan', 'project.user.activeSubscription.plan');
        $owner = $site->project?->user;
        $plan = $owner?->getCurrentPlan();

        if (! $plan) {
            return $this->defaultPlanPolicy();
        }

        return [
            'plan_slug' => is_string($plan->slug) && trim($plan->slug) !== '' ? $plan->slug : null,
            'custom_fonts_enabled' => $plan->customFontsEnabled(),
            'allowed_font_keys' => $plan->getAllowedTypographyFontKeys(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function fontKeys(?Site $site = null): array
    {
        return array_values(array_map(
            fn (array $font): string => $font['key'],
            $this->availableFonts($site)
        ));
    }

    public function defaultFontKey(?Site $site = null): string
    {
        $fonts = $this->availableFontsByKey($site);
        if ($fonts === []) {
            return 'tbc-contractica';
        }

        $keys = array_keys($fonts);
        $configuredDefault = $this->normalizeKey(config('cms.typography.default_font_key'));

        if ($configuredDefault !== null && in_array($configuredDefault, $keys, true)) {
            return $configuredDefault;
        }

        return $keys[0];
    }

    /**
     * Resolve normalized typography contract from arbitrary theme settings.
     *
     * @param  array<string, mixed>  $themeSettings
     * @return array{
     *   version: int,
     *   font_key: string,
     *   heading_font_key: string,
     *   body_font_key: string,
     *   button_font_key: string,
     *   font_stack: string,
     *   heading_font_stack: string,
     *   body_font_stack: string,
     *   button_font_stack: string,
     *   font_faces: array<int, array<string, mixed>>
     * }
     */
    public function resolveTypography(array $themeSettings, ?Site $site = null): array
    {
        $fontsByKey = $this->availableFontsByKey($site);
        if ($fontsByKey === []) {
            return [
                'version' => self::CONTRACT_VERSION,
                'font_key' => 'tbc-contractica',
                'heading_font_key' => 'tbc-contractica',
                'body_font_key' => 'tbc-contractica',
                'button_font_key' => 'tbc-contractica',
                'font_stack' => '"TBC Contractica", "Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif',
                'heading_font_stack' => '"TBC Contractica", "Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif',
                'body_font_stack' => '"TBC Contractica", "Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif',
                'button_font_stack' => '"TBC Contractica", "Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif',
                'font_faces' => [],
            ];
        }

        $defaultKey = $this->defaultFontKey($site);
        $allowedKeys = array_keys($fontsByKey);

        $typography = is_array($themeSettings['typography'] ?? null)
            ? $themeSettings['typography']
            : [];

        $baseKey = $this->pickAllowedOrFallback(
            $typography['font_key'] ?? ($themeSettings['font_key'] ?? null),
            $allowedKeys,
            $defaultKey
        );

        $headingKey = $this->pickAllowedOrFallback(
            $typography['heading_font_key'] ?? ($themeSettings['heading_font_key'] ?? null),
            $allowedKeys,
            $baseKey
        );

        $bodyKey = $this->pickAllowedOrFallback(
            $typography['body_font_key'] ?? ($themeSettings['body_font_key'] ?? null),
            $allowedKeys,
            $baseKey
        );

        $buttonKey = $this->pickAllowedOrFallback(
            $typography['button_font_key'] ?? ($themeSettings['button_font_key'] ?? null),
            $allowedKeys,
            $bodyKey
        );

        return [
            'version' => self::CONTRACT_VERSION,
            'font_key' => $baseKey,
            'heading_font_key' => $headingKey,
            'body_font_key' => $bodyKey,
            'button_font_key' => $buttonKey,
            'font_stack' => $this->fontStackForKeyFromMap($fontsByKey, $baseKey),
            'heading_font_stack' => $this->fontStackForKeyFromMap($fontsByKey, $headingKey),
            'body_font_stack' => $this->fontStackForKeyFromMap($fontsByKey, $bodyKey),
            'button_font_stack' => $this->fontStackForKeyFromMap($fontsByKey, $buttonKey),
            'font_faces' => $this->resolveFontFacesFromMap([$baseKey, $headingKey, $bodyKey, $buttonKey], $fontsByKey),
        ];
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     * @param  array<string, mixed>  $input
     * @return array{theme_settings: array<string, mixed>, typography: array<string, mixed>}
     */
    public function applyTypographyToThemeSettings(array $themeSettings, array $input, ?Site $site = null): array
    {
        $current = $this->resolveTypography($themeSettings, $site);
        $allowedKeys = $this->fontKeys($site);

        $baseKey = array_key_exists('font_key', $input)
            ? $this->requireAllowedKey($input['font_key'], $allowedKeys, 'font_key')
            : $current['font_key'];

        $headingKey = array_key_exists('heading_font_key', $input)
            ? $this->pickAllowedOrFallback($input['heading_font_key'], $allowedKeys, $baseKey)
            : $current['heading_font_key'];

        $bodyKey = array_key_exists('body_font_key', $input)
            ? $this->pickAllowedOrFallback($input['body_font_key'], $allowedKeys, $baseKey)
            : $current['body_font_key'];

        $buttonKey = array_key_exists('button_font_key', $input)
            ? $this->pickAllowedOrFallback($input['button_font_key'], $allowedKeys, $bodyKey)
            : $current['button_font_key'];

        $next = $themeSettings;
        unset($next['font_key'], $next['heading_font_key'], $next['body_font_key'], $next['button_font_key']);

        $next['typography'] = [
            'version' => self::CONTRACT_VERSION,
            'font_key' => $baseKey,
            'heading_font_key' => $headingKey,
            'body_font_key' => $bodyKey,
            'button_font_key' => $buttonKey,
        ];

        return [
            'theme_settings' => $next,
            'typography' => $this->resolveTypography($next, $site),
        ];
    }

    /**
     * Normalize legacy theme settings into canonical typography contract.
     *
     * @param  array<string, mixed>  $themeSettings
     * @return array<string, mixed>
     */
    public function normalizeThemeSettings(array $themeSettings, ?Site $site = null): array
    {
        return $this->applyTypographyToThemeSettings($themeSettings, [], $site)['theme_settings'];
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     * @return array<int, string>
     */
    public function findUnsupportedFontKeys(array $themeSettings, ?Site $site = null): array
    {
        $keys = [
            $themeSettings['font_key'] ?? null,
            $themeSettings['heading_font_key'] ?? null,
            $themeSettings['body_font_key'] ?? null,
            $themeSettings['button_font_key'] ?? null,
        ];

        $typography = is_array($themeSettings['typography'] ?? null)
            ? $themeSettings['typography']
            : [];

        $keys[] = $typography['font_key'] ?? null;
        $keys[] = $typography['heading_font_key'] ?? null;
        $keys[] = $typography['body_font_key'] ?? null;
        $keys[] = $typography['button_font_key'] ?? null;

        $allowed = $this->fontKeys($site);
        $unsupported = [];

        foreach ($keys as $candidate) {
            $normalized = $this->normalizeKey($candidate);
            if ($normalized === null) {
                continue;
            }

            if (! in_array($normalized, $allowed, true)) {
                $unsupported[] = $normalized;
            }
        }

        return array_values(array_unique($unsupported));
    }

    private function fontStackForKey(string $key, ?Site $site = null): string
    {
        return $this->fontStackForKeyFromMap($this->availableFontsByKey($site), $key);
    }

    private function pickAllowedOrFallback(mixed $candidate, array $allowedKeys, string $fallback): string
    {
        $normalized = $this->normalizeKey($candidate);
        if ($normalized === null) {
            return $fallback;
        }

        return in_array($normalized, $allowedKeys, true)
            ? $normalized
            : $fallback;
    }

    private function requireAllowedKey(mixed $value, array $allowedKeys, string $field): string
    {
        $normalized = $this->normalizeKey($value);
        if ($normalized === null || ! in_array($normalized, $allowedKeys, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported %s value. Allowed: %s',
                $field,
                implode(', ', $allowedKeys)
            ));
        }

        return $normalized;
    }

    private function normalizeKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $key = trim(strtolower($value));
        if ($key === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $key)) {
            return null;
        }

        return $key;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function configuredFonts(): array
    {
        $configuredFonts = config('cms.typography.fonts', []);
        $fonts = [];

        if (! is_array($configuredFonts)) {
            return [];
        }

        foreach ($configuredFonts as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = $this->normalizeKey($item['key'] ?? null);
            if ($key === null) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $stack = trim((string) ($item['stack'] ?? ''));
            if ($label === '' || $stack === '') {
                continue;
            }

            $fonts[$key] = [
                'key' => $key,
                'label' => $label,
                'stack' => $stack,
                'is_default' => false,
                'source_type' => is_string($item['source_type'] ?? null) && trim((string) $item['source_type']) !== ''
                    ? trim((string) $item['source_type'])
                    : 'system',
                'custom_font_id' => null,
                'font_faces' => $this->normalizeConfiguredFontFaces($item['font_faces'] ?? [], $label),
            ];

            if (is_string($item['source_url'] ?? null) && trim((string) $item['source_url']) !== '') {
                $fonts[$key]['source_url'] = trim((string) $item['source_url']);
            }
        }

        return $fonts;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function mergeCustomSiteFonts(array $fonts, Site $site): array
    {
        SiteCustomFont::query()
            ->where('site_id', $site->id)
            ->orderBy('created_at')
            ->get()
            ->each(function (SiteCustomFont $font) use (&$fonts): void {
                $family = $this->normalizeFontFamily($font->font_family);
                $assetUrl = $this->customFonts->storageUrl($font);

                $fonts[$font->key] = [
                    'key' => $font->key,
                    'label' => $font->label,
                    'stack' => sprintf('%s, %s', $family, $this->fallbackStack()),
                    'is_default' => false,
                    'source_type' => 'custom',
                    'custom_font_id' => (int) $font->id,
                    'asset_url' => $assetUrl,
                    'format' => $font->format,
                    'font_weight' => (int) $font->font_weight,
                    'font_style' => $font->font_style,
                    'font_display' => $font->font_display,
                    'font_faces' => [
                        [
                            'font_family' => trim((string) $font->font_family),
                            'src_url' => $assetUrl,
                            'format' => $font->format,
                            'font_weight' => (int) $font->font_weight,
                            'font_style' => $font->font_style,
                            'font_display' => $font->font_display,
                        ],
                    ],
                ];
            });

        return $fonts;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fonts
     * @param  array<int, string>|null  $allowedFontKeys
     * @return array<string, array<string, mixed>>
     */
    private function filterFontsByAllowlist(array $fonts, ?array $allowedFontKeys): array
    {
        if (! is_array($allowedFontKeys) || $allowedFontKeys === []) {
            return $fonts;
        }

        $allowedLookup = array_fill_keys($allowedFontKeys, true);

        return array_filter(
            $fonts,
            static fn (array $font, string $key): bool => isset($allowedLookup[$key]),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function availableFontsByKey(?Site $site = null): array
    {
        $fonts = [];
        foreach ($this->availableFonts($site) as $font) {
            $key = $font['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $fonts[$key] = $font;
        }

        return $fonts;
    }

    /**
     * @param  array<int, string>  $fontKeys
     * @return array<int, array<string, mixed>>
     */
    private function resolveFontFaces(array $fontKeys, ?Site $site = null): array
    {
        return $this->resolveFontFacesFromMap($fontKeys, $this->availableFontsByKey($site));
    }

    /**
     * @param  array<int, string>  $fontKeys
     * @param  array<string, array<string, mixed>>  $fonts
     * @return array<int, array<string, mixed>>
     */
    private function resolveFontFacesFromMap(array $fontKeys, array $fonts): array
    {
        $faces = [];

        foreach (array_values(array_unique($fontKeys)) as $key) {
            $font = $fonts[$key] ?? null;
            if (! is_array($font)) {
                continue;
            }

            $fontFaces = $font['font_faces'] ?? [];
            if (! is_array($fontFaces)) {
                continue;
            }

            foreach ($fontFaces as $face) {
                if (! is_array($face)) {
                    continue;
                }

                $family = trim((string) ($face['font_family'] ?? ''));
                $src = trim((string) ($face['src_url'] ?? ''));
                if ($family === '' || $src === '') {
                    continue;
                }

                $signature = implode('|', [
                    $family,
                    $src,
                    (string) ($face['format'] ?? ''),
                    (string) ($face['font_weight'] ?? ''),
                    (string) ($face['font_style'] ?? ''),
                    (string) ($face['font_display'] ?? ''),
                ]);

                $faces[$signature] = [
                    'font_family' => $family,
                    'src_url' => $src,
                    'format' => (string) ($face['format'] ?? ''),
                    'font_weight' => (int) ($face['font_weight'] ?? 400),
                    'font_style' => (string) ($face['font_style'] ?? 'normal'),
                    'font_display' => (string) ($face['font_display'] ?? 'swap'),
                ];
            }
        }

        return array_values($faces);
    }

    /**
     * @param  array<string, array<string, mixed>>  $fonts
     */
    private function fontStackForKeyFromMap(array $fonts, string $key): string
    {
        if (isset($fonts[$key]) && is_string($fonts[$key]['stack'] ?? null) && trim((string) $fonts[$key]['stack']) !== '') {
            return trim((string) $fonts[$key]['stack']);
        }

        $first = reset($fonts);
        if (! is_array($first)) {
            return '"TBC Contractica", "Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif';
        }

        return trim((string) ($first['stack'] ?? '"TBC Contractica", "Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif'));
    }

    private function fallbackStack(): string
    {
        $configured = trim((string) config('cms.typography.fallback_stack', ''));

        return $configured !== '' ? $configured : self::DEFAULT_FALLBACK_STACK;
    }

    private function normalizeFontFamily(string $family): string
    {
        $family = trim($family);
        $family = trim($family, "\"' ");

        if ($family === '') {
            $family = 'Custom Font';
        }

        return sprintf('"%s"', str_replace('"', '\\"', $family));
    }

    /**
     * @param  mixed  $faces
     * @return array<int, array<string, mixed>>
     */
    private function normalizeConfiguredFontFaces(mixed $faces, string $defaultFamily): array
    {
        if (! is_array($faces)) {
            return [];
        }

        $normalized = [];

        foreach ($faces as $face) {
            if (! is_array($face)) {
                continue;
            }

            $family = trim((string) ($face['font_family'] ?? $defaultFamily));
            $srcUrl = $this->sanitizeConfiguredFontFaceUrl($face['src_url'] ?? null);
            if ($family === '' || $srcUrl === '') {
                continue;
            }

            $format = $this->sanitizeConfiguredFontFaceFormat($face['format'] ?? null);
            $weight = $this->sanitizeConfiguredFontFaceWeight($face['font_weight'] ?? null);
            $style = $this->sanitizeConfiguredFontFaceStyle($face['font_style'] ?? null);
            $display = $this->sanitizeConfiguredFontFaceDisplay($face['font_display'] ?? null);

            $signature = implode('|', [$family, $srcUrl, $format, (string) $weight, $style, $display]);
            $normalized[$signature] = [
                'font_family' => $family,
                'src_url' => $srcUrl,
                'format' => $format,
                'font_weight' => $weight,
                'font_style' => $style,
                'font_display' => $display,
            ];
        }

        return array_values($normalized);
    }

    private function sanitizeConfiguredFontFaceUrl(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $url = trim($value);
        if ($url === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $url) === 1 || str_starts_with($url, '/')) {
            return $url;
        }

        return '';
    }

    private function sanitizeConfiguredFontFaceFormat(mixed $value): string
    {
        if (! is_string($value)) {
            return 'woff2';
        }

        $format = strtolower(trim($value));
        if (in_array($format, ['woff2', 'woff', 'truetype', 'opentype'], true)) {
            return $format;
        }

        return 'woff2';
    }

    private function sanitizeConfiguredFontFaceWeight(mixed $value): int
    {
        $weight = (int) $value;
        $allowed = [100, 200, 300, 400, 500, 600, 700, 800, 900];

        if (! in_array($weight, $allowed, true)) {
            return 400;
        }

        return $weight;
    }

    private function sanitizeConfiguredFontFaceStyle(mixed $value): string
    {
        if (! is_string($value)) {
            return 'normal';
        }

        $style = strtolower(trim($value));
        if (in_array($style, ['normal', 'italic', 'oblique'], true)) {
            return $style;
        }

        return 'normal';
    }

    private function sanitizeConfiguredFontFaceDisplay(mixed $value): string
    {
        if (! is_string($value)) {
            return 'swap';
        }

        $display = strtolower(trim($value));
        if (in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true)) {
            return $display;
        }

        return 'swap';
    }

    /**
     * @return array{
     *   plan_slug: string|null,
     *   custom_fonts_enabled: bool,
     *   allowed_font_keys: array<int, string>|null
     * }
     */
    private function defaultPlanPolicy(): array
    {
        return [
            'plan_slug' => null,
            'custom_fonts_enabled' => true,
            'allowed_font_keys' => null,
        ];
    }
}
