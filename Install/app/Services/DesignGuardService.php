<?php

namespace App\Services;

/**
 * Design Guard: evaluate generated site and apply fixes until score >= threshold (max iterations).
 *
 * PART 4 Automatic Design Fixes: switch template (and replace layout from new template),
 * apply theme typography/spacing from design-defaults, reorder home so hero is first.
 * Hero variant switching and full section order (REQUIRED_HOME_ORDER) are extended later.
 *
 * Builder compatibility (PART 9): This service must only run during AI generation stage.
 * Do not invoke Design Guard when the user edits sections or theme via the builder
 * (e.g. PanelBuilderController::mutateSections, ::updateStyles, ::applyTemplate).
 *
 * @see new tasks.txt — AI Design Guard System PART 4–5, PART 9 Builder Compatibility
 */
class DesignGuardService
{
    public function __construct(
        protected DesignQualityEvaluator $evaluator,
        protected TemplateSelectorService $templateSelector,
        protected ReadyTemplatesService $readyTemplates,
    ) {}

    /**
     * Run evaluation and optional improvement loop (max 5 iterations).
     *
     * @param  array<string, mixed>  $siteJsonLayout  pages + theme
     * @param  array<string, mixed>  $themeTokens
     * @param  string|null  $templateId
     * @param  array{vertical?: string, vibe?: string}  $designBrief
     * @return array{score: int, passed: bool, iterations: int, final_layout: array, issues: array}
     */
    public function evaluateAndImprove(
        array $siteJsonLayout,
        array $themeTokens = [],
        ?string $templateId = null,
        array $designBrief = [],
        int $maxIterations = 5
    ): array {
        $iterations = 0;
        $layout = $siteJsonLayout;
        $theme = $themeTokens;
        $template = $templateId;

        while ($iterations < $maxIterations) {
            $result = $this->evaluator->evaluate($layout, $theme, $template);
            $score = $result['design_score'];
            $issues = $result['design_issues'];

            if ($score >= $this->evaluator->getMinDesignScore()) {
                return [
                    'score' => $score,
                    'passed' => true,
                    'iterations' => $iterations,
                    'final_layout' => $layout,
                    'final_theme' => $theme,
                    'final_template_id' => $template,
                    'issues' => $issues,
                ];
            }

            $improved = $this->applyImprovements($layout, $theme, $template, $designBrief, $issues);
            if (! $improved['changed']) {
                break;
            }
            $layout = $improved['layout'];
            $theme = $improved['theme'];
            $template = $improved['template_id'];
            $iterations++;
        }

        $finalResult = $this->evaluator->evaluate($layout, $theme, $template);

        return [
            'score' => $finalResult['design_score'],
            'passed' => $finalResult['passed'],
            'iterations' => $iterations,
            'final_layout' => $layout,
            'final_theme' => $theme,
            'final_template_id' => $template,
            'issues' => $finalResult['design_issues'],
        ];
    }

    /**
     * PART 4 Automatic Design Fixes: switch template (replace layout from new template),
     * apply typography/spacing from design-defaults, reorder home so hero is first.
     *
     * @param  array<int, array{code: string, message: string}>  $issues
     * @return array{changed: bool, layout: array, theme: array, template_id: ?string}
     */
    private function applyImprovements(
        array $layout,
        array $theme,
        ?string $templateId,
        array $designBrief,
        array $issues
    ): array {
        $changed = false;
        $newLayout = $layout;
        $newTemplate = $templateId;
        $newTheme = $theme;

        foreach ($issues as $issue) {
            $code = (string) ($issue['code'] ?? '');
            if ($code === 'template_quality' && $templateId !== null && $designBrief !== []) {
                $selected = $this->templateSelector->selectFromDesignBrief($designBrief);
                $candidate = $selected['template_id'] ?? null;
                if ($candidate !== null && $candidate !== $templateId) {
                    $templateData = $this->readyTemplates->loadBySlug($candidate);
                    if (isset($templateData['default_pages']) && is_array($templateData['default_pages'])) {
                        $newLayout = $this->defaultPagesToSiteLayout($templateData['default_pages']);
                        $newTheme = array_replace($newTheme, [
                            'preset' => $selected['theme_variant'] ?? $templateData['theme_preset'] ?? $newTheme['preset'] ?? 'luxury_minimal',
                        ]);
                    }
                    $newTemplate = $candidate;
                    $changed = true;
                    break;
                }
            }
            if ($code === 'required_page') {
                // Would need to inject missing page from template; skip for now
            }
            if ($code === 'typography_scale' || $code === 'typography_preset') {
                $newTheme = $this->applyDesignDefaultsToTheme($newTheme, ['typography']);
                $changed = true;
            }
            if ($code === 'spacing_not_8px_grid' || $code === 'spacing_system' || $code === 'card_inconsistent_spacing') {
                $newTheme = $this->applyDesignDefaultsToTheme($newTheme, ['spacing']);
                $changed = true;
            }
            if ($code === 'layout_hero' || $code === 'section_order') {
                $newLayout = $this->reorderHomePageHeroFirst($newLayout);
                $changed = true;
            }
        }

        return [
            'changed' => $changed,
            'layout' => $newLayout,
            'theme' => $newTheme,
            'template_id' => $newTemplate,
        ];
    }

    /**
     * Build site layout shape (pages with slug + sections) from template default_pages.
     *
     * @param  array<int, array{slug: string, title?: string, sections?: array}>  $defaultPages
     * @return array{pages: array<int, array{slug: string, sections: array}>}
     */
    private function defaultPagesToSiteLayout(array $defaultPages): array
    {
        $pages = [];
        foreach ($defaultPages as $p) {
            if (! is_array($p)) {
                continue;
            }
            $slug = (string) ($p['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $pages[] = [
                'slug' => $slug,
                'sections' => is_array($p['sections'] ?? null) ? $p['sections'] : [],
            ];
        }
        return ['pages' => $pages];
    }

    /**
     * Reorder home page sections so a hero-like section is first (PART 4 improve section order).
     *
     * @param  array{pages: array<int, array{slug?: string, sections?: array}>}  $layout
     * @return array{pages: array<int, array{slug?: string, sections?: array}>}
     */
    private function reorderHomePageHeroFirst(array $layout): array
    {
        $pages = is_array($layout['pages'] ?? null) ? $layout['pages'] : [];
        $out = [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                $out[] = $page;
                continue;
            }
            $slug = (string) ($page['slug'] ?? '');
            if ($slug !== 'home') {
                $out[] = $page;
                continue;
            }
            $sections = array_values(array_filter(
                is_array($page['sections'] ?? null) ? $page['sections'] : [],
                'is_array'
            ));
            $heroIndex = null;
            foreach ($sections as $i => $section) {
                if ($this->isHeroLikeSection($section)) {
                    $heroIndex = $i;
                    break;
                }
            }
            if ($heroIndex !== null && $heroIndex > 0) {
                $hero = $sections[$heroIndex];
                array_splice($sections, $heroIndex, 1);
                array_unshift($sections, $hero);
            }
            $out[] = array_merge($page, ['sections' => $sections]);
        }
        return ['pages' => $out];
    }

    private function isHeroLikeSection(array $section): bool
    {
        $key = strtolower((string) ($section['type'] ?? $section['key'] ?? ''));
        return str_contains($key, 'hero') || str_contains($key, 'heading');
    }

    /**
     * Merge design-defaults (typography, spacing) into theme so downstream uses correct scale/grid.
     *
     * @param  array<string, mixed>  $theme
     * @param  array<int, string>  $keys  ['typography', 'spacing']
     * @return array<string, mixed>
     */
    private function applyDesignDefaultsToTheme(array $theme, array $keys): array
    {
        $defaults = config('design-defaults', []);
        $merged = $theme;
        if (in_array('typography', $keys, true) && isset($defaults['typography']) && is_array($defaults['typography'])) {
            $merged['typography'] = array_replace(
                is_array($merged['typography'] ?? null) ? $merged['typography'] : [],
                $defaults['typography']
            );
        }
        if (in_array('spacing', $keys, true) && isset($defaults['spacing']) && is_array($defaults['spacing'])) {
            $merged['spacing'] = array_replace(
                is_array($merged['spacing'] ?? null) ? $merged['spacing'] : [],
                $defaults['spacing']
            );
        }
        return $merged;
    }
}
