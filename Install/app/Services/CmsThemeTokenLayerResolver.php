<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Site;
use App\Models\Template;

class CmsThemeTokenLayerResolver
{
    public const LAYERING_VERSION = 1;

    public const TOKEN_MODEL_VERSION = 1;

    public function __construct(
        protected CmsTypographyService $typography
    ) {}

    /**
     * Build canonical theme token layering payload from current Webu storage.
     *
     * Layer order (low -> high):
     * - template defaults
     * - preset defaults
     * - site overrides
     *
     * @return array<string, mixed>
     */
    public function resolveForSite(Site $site, ?Project $project = null): array
    {
        $project = $this->resolveProject($site, $project);
        $template = $this->resolveTemplate($project);

        $siteThemeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $presetResolution = $this->resolvePresetSelection($siteThemeSettings, $project);

        $templateLayer = $this->buildTemplateDefaultsLayer($template);
        $presetLayer = $this->buildPresetDefaultsLayer($presetResolution['resolved_key']);
        $siteLayer = $this->buildSiteOverridesLayer($siteThemeSettings, $presetResolution['requested_key'], $presetResolution['resolved_key']);

        $effectiveThemeSettings = $this->mergeLayers(
            $templateLayer['theme_settings'] ?? [],
            $presetLayer['theme_settings'] ?? [],
            $siteLayer['theme_settings'] ?? []
        );

        $effectiveThemeSettings['preset'] = $presetResolution['resolved_key'];

        $effective = $this->mergeLayers(
            $templateLayer['canonical'] ?? [],
            $presetLayer['canonical'] ?? [],
            $siteLayer['canonical'] ?? []
        );
        if (! is_array($effective)) {
            $effective = [];
        }

        $typographyContext = $site->exists ? $site : null;
        $effectiveTypography = $this->typography->resolveTypography($effectiveThemeSettings, $typographyContext);

        $effectiveThemeTokens = is_array($effective['theme_tokens'] ?? null) ? $effective['theme_tokens'] : [];
        $effectiveThemeTokens['version'] = self::TOKEN_MODEL_VERSION;
        $effectiveThemeTokens['typography'] = $effectiveTypography;
        $effective['theme_tokens'] = $effectiveThemeTokens;
        $effective['theme_preset'] = [
            'key' => $presetResolution['resolved_key'],
        ];
        $effective['layout'] = is_array($effective['layout'] ?? null) ? $effective['layout'] : [];

        return [
            'version' => self::LAYERING_VERSION,
            'token_model_version' => self::TOKEN_MODEL_VERSION,
            'layer_order' => ['template_defaults', 'preset_defaults', 'site_overrides'],
            'theme_preset' => [
                'requested_key' => $presetResolution['requested_key'],
                'resolved_key' => $presetResolution['resolved_key'],
                'fallback_applied' => $presetResolution['requested_key'] !== $presetResolution['resolved_key'],
                'catalog_exists' => $presetLayer['exists'] ?? false,
            ],
            'sources' => [
                'template' => [
                    'id' => $template?->id,
                    'slug' => $template?->slug,
                    'name' => $template?->name,
                    'has_metadata' => is_array($template?->metadata ?? null),
                ],
                'project' => [
                    'id' => $project?->id,
                    'theme_preset' => is_string($project?->theme_preset ?? null) ? $project->theme_preset : null,
                ],
                'site' => [
                    'id' => $site->id,
                    'has_theme_settings' => $siteThemeSettings !== [],
                ],
            ],
            'layers' => [
                'template_defaults' => $this->stripLayerInternalKeys($templateLayer),
                'preset_defaults' => $this->stripLayerInternalKeys($presetLayer),
                'site_overrides' => $this->stripLayerInternalKeys($siteLayer),
            ],
            'effective' => $effective,
            'effective_theme_settings' => $effectiveThemeSettings,
        ];
    }

    /**
     * Resolve theme token layers for a single template (e.g. admin component preview).
     * No site/project; uses template defaults + default preset.
     *
     * @return array<string, mixed>
     */
    public function resolveForTemplate(Template $template, ?Site $site = null): array
    {
        $presetKey = 'default';
        $templateLayer = $this->buildTemplateDefaultsLayer($template);
        $presetLayer = $this->buildPresetDefaultsLayer($presetKey);

        $effectiveThemeSettings = $this->mergeLayers(
            $templateLayer['theme_settings'] ?? [],
            $presetLayer['theme_settings'] ?? []
        );
        $effectiveThemeSettings['preset'] = $presetKey;

        $effective = $this->mergeLayers(
            $templateLayer['canonical'] ?? [],
            $presetLayer['canonical'] ?? []
        );
        if (! is_array($effective)) {
            $effective = [];
        }

        $effectiveTypography = $this->typography->resolveTypography($effectiveThemeSettings, null);
        $effectiveThemeTokens = is_array($effective['theme_tokens'] ?? null) ? $effective['theme_tokens'] : [];
        $effectiveThemeTokens['version'] = self::TOKEN_MODEL_VERSION;
        $effectiveThemeTokens['typography'] = $effectiveTypography;
        $effective['theme_tokens'] = $effectiveThemeTokens;
        $effective['theme_preset'] = ['key' => $presetKey];
        $effective['layout'] = is_array($effective['layout'] ?? null) ? $effective['layout'] : [];

        return [
            'version' => self::LAYERING_VERSION,
            'effective' => $effective,
            'effective_theme_settings' => $effectiveThemeSettings,
        ];
    }

    /**
     * @return array{
     *   requested_key: string,
     *   resolved_key: string
     * }
     */
    private function resolvePresetSelection(array $siteThemeSettings, ?Project $project): array
    {
        $requested = $this->normalizePresetKey($siteThemeSettings['preset'] ?? null)
            ?? $this->normalizePresetKey($project?->theme_preset ?? null)
            ?? 'default';

        $catalog = config('theme-presets', []);
        $resolved = (is_array($catalog) && isset($catalog[$requested]) && is_array($catalog[$requested]))
            ? $requested
            : 'default';

        return [
            'requested_key' => $requested,
            'resolved_key' => $resolved,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTemplateDefaultsLayer(?Template $template): array
    {
        $metadata = is_array($template?->metadata ?? null) ? $template->metadata : [];
        $themeSettings = [];

        $layoutDefaults = $this->firstArray(
            $metadata['layout_defaults'] ?? null,
            data_get($metadata, 'theme_settings.layout'),
            data_get($metadata, 'defaults.layout')
        );
        if ($layoutDefaults !== []) {
            $themeSettings['layout'] = $layoutDefaults;
        }

        $templateThemeTokens = $this->firstArray(
            $metadata['theme_tokens'] ?? null,
            $metadata['tokens'] ?? null,
            $metadata['design_tokens'] ?? null
        );
        if ($templateThemeTokens !== []) {
            $themeSettings['theme_tokens'] = $templateThemeTokens;
        }

        $typographyTokens = is_array($metadata['typography_tokens'] ?? null)
            ? $metadata['typography_tokens']
            : [];
        $templateTypographySettings = $this->mapTemplateTypographyTokensToThemeSettings($typographyTokens);
        if ($templateTypographySettings !== []) {
            $themeSettings = $this->mergeLayers($themeSettings, $templateTypographySettings);
        }

        return [
            'source' => 'template_metadata',
            'exists' => $template instanceof Template,
            'canonical' => $this->canonicalFromThemeSettings($themeSettings),
            'theme_settings' => $themeSettings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPresetDefaultsLayer(string $presetKey): array
    {
        $preset = config("theme-presets.{$presetKey}");
        if (! is_array($preset)) {
            return [
                'source' => 'theme_presets_config',
                'exists' => false,
                'canonical' => [],
                'theme_settings' => [
                    'preset' => 'default',
                ],
            ];
        }

        $colors = ['modes' => []];
        $radii = [];

        foreach (['light', 'dark'] as $mode) {
            $modeValues = is_array($preset[$mode] ?? null) ? $preset[$mode] : [];
            if ($modeValues === []) {
                continue;
            }

            $sanitizedModeValues = [];
            foreach ($modeValues as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                if (is_string($value) || is_numeric($value)) {
                    $sanitizedModeValues[$key] = (string) $value;
                }
            }

            if ($sanitizedModeValues !== []) {
                $colors['modes'][$mode] = $sanitizedModeValues;
            }

            if ($mode === 'light' && isset($sanitizedModeValues['radius']) && trim((string) $sanitizedModeValues['radius']) !== '') {
                $radii['base'] = trim((string) $sanitizedModeValues['radius']);
            }
        }

        $canonical = [
            'theme_preset' => [
                'key' => $presetKey,
                'label' => is_string($preset['name'] ?? null) ? $preset['name'] : $presetKey,
            ],
            'theme_tokens' => [
                'version' => self::TOKEN_MODEL_VERSION,
            ],
            'layout' => [],
        ];

        if (($colors['modes'] ?? []) !== []) {
            $canonical['theme_tokens']['colors'] = $colors;
        }

        if ($radii !== []) {
            $canonical['theme_tokens']['radii'] = $radii;
        }

        return [
            'source' => 'theme_presets_config',
            'exists' => true,
            'canonical' => $canonical,
            'theme_settings' => [
                'preset' => $presetKey,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     * @return array<string, mixed>
     */
    private function buildSiteOverridesLayer(array $themeSettings, string $requestedPresetKey, string $resolvedPresetKey): array
    {
        $canonical = $this->canonicalFromThemeSettings($themeSettings);
        $presetKey = $this->normalizePresetKey($themeSettings['preset'] ?? null);
        if ($presetKey !== null) {
            $canonical['theme_preset'] = ['key' => $presetKey];
        }

        return [
            'source' => 'site.theme_settings',
            'exists' => $themeSettings !== [],
            'canonical' => $canonical,
            'theme_settings' => $themeSettings,
            'meta' => [
                'preset_requested' => $requestedPresetKey,
                'preset_resolved' => $resolvedPresetKey,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     * @return array<string, mixed>
     */
    private function canonicalFromThemeSettings(array $themeSettings): array
    {
        if ($themeSettings === []) {
            return [];
        }

        $canonical = [
            'theme_tokens' => [
                'version' => $this->extractTokenVersion($themeSettings),
            ],
            'layout' => is_array($themeSettings['layout'] ?? null) ? $themeSettings['layout'] : [],
        ];

        if (($colors = $this->extractColorsFromThemeSettings($themeSettings)) !== []) {
            $canonical['theme_tokens']['colors'] = $colors;
        }

        if (($typography = $this->extractTypographyOverridesFromThemeSettings($themeSettings)) !== []) {
            $canonical['theme_tokens']['typography'] = $typography;
        }

        foreach (['spacing', 'radii', 'shadows', 'breakpoints'] as $group) {
            $groupValues = $this->extractThemeTokenGroup($themeSettings, $group);
            if ($groupValues !== []) {
                $canonical['theme_tokens'][$group] = $groupValues;
            }
        }

        if ($canonical['layout'] === []) {
            unset($canonical['layout']);
        }

        if (($canonical['theme_tokens'] ?? []) === ['version' => self::TOKEN_MODEL_VERSION]) {
            unset($canonical['theme_tokens']);
        }

        return $canonical;
    }

    /**
     * @param  array<string, mixed>  $metadataTypographyTokens
     * @return array<string, mixed>
     */
    private function mapTemplateTypographyTokensToThemeSettings(array $metadataTypographyTokens): array
    {
        $baseKey = $this->normalizeFontKey(
            $metadataTypographyTokens['font'] ?? $metadataTypographyTokens['base'] ?? $metadataTypographyTokens['body'] ?? null
        );
        $headingKey = $this->normalizeFontKey($metadataTypographyTokens['heading'] ?? null);
        $bodyKey = $this->normalizeFontKey($metadataTypographyTokens['body'] ?? null);
        $buttonKey = $this->normalizeFontKey($metadataTypographyTokens['button'] ?? null);

        $typography = array_filter([
            'version' => CmsTypographyService::CONTRACT_VERSION,
            'font_key' => $baseKey,
            'heading_font_key' => $headingKey,
            'body_font_key' => $bodyKey,
            'button_font_key' => $buttonKey,
        ], static fn ($value) => $value !== null);

        if ($typography === []) {
            return [];
        }

        return [
            'typography' => $typography,
        ];
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     * @return array<string, mixed>
     */
    private function extractColorsFromThemeSettings(array $themeSettings): array
    {
        $colors = is_array($themeSettings['colors'] ?? null) ? $themeSettings['colors'] : [];
        if ($colors === []) {
            return [];
        }

        return $this->sanitizeRecursiveScalarTree($colors);
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     * @return array<string, mixed>
     */
    private function extractTypographyOverridesFromThemeSettings(array $themeSettings): array
    {
        $typography = is_array($themeSettings['typography'] ?? null) ? $themeSettings['typography'] : [];

        $raw = array_filter([
            'version' => is_int($typography['version'] ?? null) ? (int) $typography['version'] : null,
            'font_key' => $this->normalizeFontKey($typography['font_key'] ?? ($themeSettings['font_key'] ?? null)),
            'heading_font_key' => $this->normalizeFontKey($typography['heading_font_key'] ?? ($themeSettings['heading_font_key'] ?? null)),
            'body_font_key' => $this->normalizeFontKey($typography['body_font_key'] ?? ($themeSettings['body_font_key'] ?? null)),
            'button_font_key' => $this->normalizeFontKey($typography['button_font_key'] ?? ($themeSettings['button_font_key'] ?? null)),
        ], static fn ($value) => $value !== null);

        return $raw;
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     * @return array<string, mixed>
     */
    private function extractThemeTokenGroup(array $themeSettings, string $group): array
    {
        $tokens = is_array($themeSettings['theme_tokens'] ?? null)
            ? $themeSettings['theme_tokens']
            : (is_array($themeSettings['tokens'] ?? null) ? $themeSettings['tokens'] : []);

        $values = is_array($tokens[$group] ?? null) ? $tokens[$group] : [];
        if ($values === []) {
            return [];
        }

        return $this->sanitizeRecursiveScalarTree($values);
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     */
    private function extractTokenVersion(array $themeSettings): int
    {
        $tokens = is_array($themeSettings['theme_tokens'] ?? null)
            ? $themeSettings['theme_tokens']
            : (is_array($themeSettings['tokens'] ?? null) ? $themeSettings['tokens'] : []);

        $version = $tokens['version'] ?? null;
        if (is_int($version) && $version > 0) {
            return $version;
        }

        if (is_numeric($version)) {
            $value = (int) $version;
            if ($value > 0) {
                return $value;
            }
        }

        return self::TOKEN_MODEL_VERSION;
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function sanitizeRecursiveScalarTree(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $targetKey = is_int($key) ? (string) $key : $key;

            if (is_array($item)) {
                $nested = $this->sanitizeRecursiveScalarTree($item);
                if ($nested !== []) {
                    $result[$targetKey] = $nested;
                }
                continue;
            }

            if (is_string($item) || is_numeric($item) || is_bool($item) || $item === null) {
                $result[$targetKey] = $item;
            }
        }

        return $result;
    }

    /**
     * @param  mixed  ...$candidates
     * @return array<string, mixed>
     */
    private function firstArray(mixed ...$candidates): array
    {
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    private function normalizeFontKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $key = trim(strtolower($value));
        if ($key === '') {
            return null;
        }

        $key = str_replace('_', '-', $key);
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $key)) {
            return null;
        }

        return $key;
    }

    private function normalizePresetKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $key = trim(strtolower($value));
        if ($key === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $key)) {
            return null;
        }

        return $key;
    }

    private function resolveProject(Site $site, ?Project $project): ?Project
    {
        if ($project instanceof Project) {
            if ($project->exists) {
                $project->loadMissing('template');
            }

            return $project;
        }

        if ($site->relationLoaded('project')) {
            $relation = $site->getRelation('project');

            return $relation instanceof Project ? $relation : null;
        }

        if ($site->exists) {
            $site->loadMissing('project.template');
            $loaded = $site->project;

            return $loaded instanceof Project ? $loaded : null;
        }

        return null;
    }

    private function resolveTemplate(?Project $project): ?Template
    {
        if (! $project instanceof Project) {
            return null;
        }

        if ($project->relationLoaded('template')) {
            $relation = $project->getRelation('template');

            return $relation instanceof Template ? $relation : null;
        }

        if ($project->exists) {
            $project->loadMissing('template');
        }

        return $project->template instanceof Template ? $project->template : null;
    }

    /**
     * @param  array<string, mixed>  ...$layers
     * @return array<string, mixed>
     */
    private function mergeLayers(array ...$layers): array
    {
        $result = [];

        foreach ($layers as $layer) {
            if ($layer === []) {
                continue;
            }

            $result = $this->mergeRecursive($result, $layer);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function mergeRecursive(array $base, array $override): array
    {
        if ($base === []) {
            return $override;
        }

        foreach ($override as $key => $value) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $targetKey = is_int($key) ? (string) $key : $key;
            $baseValue = $base[$targetKey] ?? null;

            if (is_array($value) && $value === []) {
                continue;
            }

            if (is_array($baseValue) && is_array($value) && ! array_is_list($baseValue) && ! array_is_list($value)) {
                $base[$targetKey] = $this->mergeRecursive($baseValue, $value);
                continue;
            }

            $base[$targetKey] = $value;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $layer
     * @return array<string, mixed>
     */
    private function stripLayerInternalKeys(array $layer): array
    {
        unset($layer['theme_settings']);

        return $layer;
    }
}
