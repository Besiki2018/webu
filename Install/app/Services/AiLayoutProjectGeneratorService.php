<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Creates a new project (and site) from AI-generated layout JSON and theme tokens.
 *
 * - Default content is written into the project's CMS (page revisions): hero, footer, product grid title,
 *   newsletter, etc. come from the project/site name so components show project-based content from the start.
 * - Design is applied (theme preset + baked CSS snapshot so the project is not linked to default webu).
 * - The user then edits that content and design in the builder; everything comes from the project's CMS.
 */
class AiLayoutProjectGeneratorService
{
    public function __construct(
        protected AiLayoutGeneratorService $layoutGenerator,
        protected SiteProvisioningService $provisioning,
        protected WebuDesignSnapshotService $designSnapshot
    ) {}

    /**
     * Create project and provision site from AI layout + theme.
     *
     * @param  array{page?: string, sections: array<int, array{component: string, variant?: string, bindings?: array<string, string>}>}  $layout
     * @param  array<string, mixed>  $themeTokens  primary_color, secondary_color, font_family, border_radius
     */
    public function createProjectFromAiLayout(User $user, array $layout, array $themeTokens, string $projectName = 'My Store'): Project
    {
        if (! $user->canCreateMoreProjects()) {
            throw new \InvalidArgumentException(__('You have reached the maximum number of projects.'));
        }

        $defaultPages = $this->layoutGenerator->layoutToTemplateDefaultPages($layout);
        $themePreset = $this->resolveThemePresetFromTokens($themeTokens);

        $templateData = [
            'name' => $projectName,
            'slug' => \Illuminate\Support\Str::slug($projectName),
            'theme_preset' => $themePreset,
            'default_pages' => $defaultPages,
            'ai_layout_tokens' => $themeTokens,
        ];

        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => $projectName,
            'template_id' => null,
            'theme_preset' => $themePreset,
            'last_viewed_at' => now(),
        ]);

        $site = $this->provisioning->provisionFromReadyTemplate($project, $templateData, []);

        if ($site) {
            $current = is_array($site->theme_settings) ? $site->theme_settings : [];
            if (! empty($themeTokens)) {
                $overrides = $this->themeTokensToSettingsOverrides($themeTokens);
                if ($overrides !== []) {
                    $current = array_merge($current, $overrides);
                }
            }
            // Bake design snapshot so this project is not linked to default webu components.
            $bakedCssUrl = $this->designSnapshot->bakeSiteDesignCss($site);
            if ($bakedCssUrl !== null) {
                $current['design_snapshot'] = [
                    'baked_css_url' => $bakedCssUrl,
                    'detached' => true,
                    'created_at' => now()->toIso8601String(),
                ];
            }
            $site->forceFill(['theme_settings' => $current])->save();
        }

        return $project->fresh();
    }

    private function resolveThemePresetFromTokens(array $themeTokens): string
    {
        $styleMap = config('ai-layout-generator.design_style_to_theme_preset', []);
        $preset = Arr::get($styleMap, 'default', 'default');
        $colorScheme = Arr::get($themeTokens, 'color_scheme', 'neutral');
        if ($colorScheme === 'luxury') {
            return 'luxury_minimal';
        }
        if ($colorScheme === 'pastel') {
            return 'default';
        }

        return $preset;
    }

    /**
     * @return array<string, mixed>
     */
    private function themeTokensToSettingsOverrides(array $tokens): array
    {
        $out = [];
        if (isset($tokens['primary_color'])) {
            $out['primary_hex'] = $tokens['primary_color'];
        }
        if (isset($tokens['secondary_color'])) {
            $out['secondary_hex'] = $tokens['secondary_color'];
        }
        if (isset($tokens['font_family'])) {
            $out['font_family'] = $tokens['font_family'];
        }
        if (isset($tokens['border_radius'])) {
            $out['radius'] = $tokens['border_radius'];
        }

        return $out;
    }
}
