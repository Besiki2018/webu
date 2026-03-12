<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Applies AI ChangeSet site-level operations: updateTheme, updateGlobalComponent.
 * Updates site.theme_settings and persists.
 */
class AiSiteEditorSiteOpsService
{
    public function __construct(
        protected FixedLayoutComponentService $fixedLayoutComponents
    ) {}

    /**
     * Apply site-level operations (updateTheme, updateGlobalComponent) to the site.
     *
     * @param  array<int, array<string, mixed>>  $siteOps  Operations with op: 'updateTheme' | 'updateGlobalComponent'
     */
    public function apply(Site $site, array $siteOps, array $context = []): void
    {
        $themeSettings = $site->theme_settings;
        if (! is_array($themeSettings)) {
            $themeSettings = [];
        }

        foreach ($siteOps as $i => $op) {
            if (! is_array($op) || empty($op['op']) || ! is_string($op['op'])) {
                continue;
            }
            if ($op['op'] === 'updateTheme' && isset($op['patch']) && is_array($op['patch'])) {
                $themeSettings = array_replace_recursive($themeSettings, $op['patch']);
            }
            if ($op['op'] === 'updateGlobalComponent') {
                $component = trim((string) ($op['component'] ?? ''));
                $patch = is_array($op['patch'] ?? null) ? $op['patch'] : [];
                if ($component === '' || $patch === []) {
                    Log::debug('ai_site_editor.site_ops.skip_global_component', [
                        'index' => $i,
                        'component' => $component,
                        'has_patch' => $patch !== [],
                    ]);
                    continue;
                }
                $layout = Arr::get($themeSettings, 'layout');
                if (! is_array($layout)) {
                    $layout = [];
                }
                if (strtolower($component) === 'header') {
                    $sectionKey = trim((string) ($layout['header_section_key'] ?? 'webu_header_01'));
                    $currentProps = is_array($layout['header_props'] ?? null) ? $layout['header_props'] : [];
                    $normalizedCurrentProps = $this->fixedLayoutComponents->normalizeProps(
                        $sectionKey,
                        $currentProps,
                        isset($context['instruction']) ? (string) $context['instruction'] : null
                    );
                    $normalizedPatch = $this->fixedLayoutComponents->normalizeProps(
                        $sectionKey,
                        array_filter([
                            'layout_variant' => $currentProps['layout_variant'] ?? null,
                            'variant' => $currentProps['variant'] ?? null,
                        ], static fn ($value) => $value !== null && $value !== '') + $patch,
                        isset($context['instruction']) ? (string) $context['instruction'] : null
                    );
                    $layout['header_props'] = $this->fixedLayoutComponents->normalizeProps(
                        $sectionKey,
                        array_replace_recursive($normalizedCurrentProps, $normalizedPatch),
                        isset($context['instruction']) ? (string) $context['instruction'] : null
                    );
                } elseif (strtolower($component) === 'footer') {
                    $sectionKey = trim((string) ($layout['footer_section_key'] ?? 'webu_footer_01'));
                    $currentProps = is_array($layout['footer_props'] ?? null) ? $layout['footer_props'] : [];
                    $normalizedCurrentProps = $this->fixedLayoutComponents->normalizeProps(
                        $sectionKey,
                        $currentProps,
                        isset($context['instruction']) ? (string) $context['instruction'] : null
                    );
                    $normalizedPatch = $this->fixedLayoutComponents->normalizeProps(
                        $sectionKey,
                        array_filter([
                            'layout_variant' => $currentProps['layout_variant'] ?? null,
                            'variant' => $currentProps['variant'] ?? null,
                        ], static fn ($value) => $value !== null && $value !== '') + $patch,
                        isset($context['instruction']) ? (string) $context['instruction'] : null
                    );
                    $layout['footer_props'] = $this->fixedLayoutComponents->normalizeProps(
                        $sectionKey,
                        array_replace_recursive($normalizedCurrentProps, $normalizedPatch),
                        isset($context['instruction']) ? (string) $context['instruction'] : null
                    );
                }
                $themeSettings['layout'] = $layout;
            }
        }

        try {
            $site->update(['theme_settings' => $themeSettings]);
        } catch (\Throwable $e) {
            Log::warning('ai_site_editor.site_ops.update_failed', [
                'site_id' => $site->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
