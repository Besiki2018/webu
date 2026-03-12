<?php

namespace App\Services;

/**
 * Evaluates generated website design quality for Design Guard.
 *
 * Input: site_json_layout, theme_tokens, template_id.
 * Output: design_score (0–100), design_issues.
 *
 * PART 4 Design Quality Rules: 8px spacing, typography scale (H1 48, H2 36, H3 24, body 16),
 * consistent cards, balanced layout, mobile responsiveness.
 *
 * @see new tasks.txt — AI Design Guard System PART 1–2, PART 4 Design Quality Rules
 */
class DesignQualityEvaluator
{
    public const MIN_DESIGN_SCORE_THRESHOLD = 85;

    public const MAX_ITERATIONS = 5;

    public function getMinDesignScore(): int
    {
        $fromConfig = config('design-defaults.min_design_score', null);

        return is_int($fromConfig) && $fromConfig >= 0 && $fromConfig <= 100
            ? $fromConfig
            : self::MIN_DESIGN_SCORE_THRESHOLD;
    }

    /**
     * Evaluate design quality of a generated site.
     *
     * @param  array<string, mixed>  $siteJsonLayout  Pages/sections structure (e.g. pages with builder_nodes or sections)
     * @param  array<string, mixed>  $themeTokens  Theme settings (preset, colors, typography)
     * @param  string|null  $templateId  Template slug used for generation
     * @return array{design_score: int, design_issues: array<int, array{code: string, message: string, severity: string}>}
     */
    public function evaluate(
        array $siteJsonLayout,
        array $themeTokens = [],
        ?string $templateId = null
    ): array {
        $issues = [];
        $score = 100;

        $this->evaluateLayoutBalance($siteJsonLayout, $issues, $score);
        $this->evaluateTypography($themeTokens, $siteJsonLayout, $issues, $score);
        $this->evaluateSpacingSystem($siteJsonLayout, $themeTokens, $issues, $score);
        $this->evaluateTypographyScaleInLayout($siteJsonLayout, $issues, $score);
        $this->evaluateSpacingInLayout($siteJsonLayout, $issues, $score);
        $this->evaluateCardConsistency($siteJsonLayout, $issues, $score);
        $this->evaluateMobileResponsiveness($siteJsonLayout, $issues, $score);
        $this->evaluateProductCardQuality($siteJsonLayout, $issues, $score);
        $this->evaluateSectionDensity($siteJsonLayout, $issues, $score);
        $this->evaluateRequiredPages($siteJsonLayout, $issues, $score);
        $this->evaluateTemplateQuality($templateId, $issues, $score);

        $designScore = max(0, min(100, $score));

        return [
            'design_score' => $designScore,
            'design_issues' => array_values($issues),
            'passed' => $designScore >= $this->getMinDesignScore(),
            'threshold' => $this->getMinDesignScore(),
        ];
    }

    /**
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateLayoutBalance(array $siteJsonLayout, array &$issues, int &$score): void
    {
        $pages = is_array($siteJsonLayout['pages'] ?? null) ? $siteJsonLayout['pages'] : [];
        $homePage = null;
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            if ((string) ($page['slug'] ?? '') === 'home') {
                $homePage = $page;
                break;
            }
        }

        if ($homePage === null) {
            $issues[] = ['code' => 'missing_home', 'message' => 'Home page is missing.', 'severity' => 'critical'];
            $score -= 25;
            return;
        }

        $sections = $this->extractSectionsFromPage($homePage);
        $sectionKeys = array_map(fn ($s) => $this->sectionTypeFromNode($s), $sections);
        $hasHero = $this->hasHeroLikeSection($sectionKeys);
        $hasProducts = $this->hasProductSection($sectionKeys);
        $hasCategories = $this->hasCategoryLikeSection($sectionKeys);

        if (! $hasHero) {
            $issues[] = ['code' => 'layout_hero', 'message' => 'Home page should start with a hero section.', 'severity' => 'warning'];
            $score -= 10;
        }
        if ($hasHero && count($sections) > 0 && ! $this->isFirstSectionHeroLike($sections)) {
            $issues[] = ['code' => 'section_order', 'message' => 'Home page should start with hero section (improve section order).', 'severity' => 'warning'];
            $score -= 5;
        }
        if (! $hasProducts) {
            $issues[] = ['code' => 'layout_products', 'message' => 'Home page should include featured products.', 'severity' => 'warning'];
            $score -= 8;
        }
        if (count($sections) > 12) {
            $issues[] = ['code' => 'section_density_high', 'message' => 'Home page has too many sections; may feel crowded.', 'severity' => 'info'];
            $score -= 3;
        }
        if (count($sections) < 2) {
            $issues[] = ['code' => 'section_density_low', 'message' => 'Home page has very few sections.', 'severity' => 'warning'];
            $score -= 5;
        }
    }

    /**
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateTypography(array $themeTokens, array $siteJsonLayout, array &$issues, int &$score): void
    {
        $preset = (string) ($themeTokens['preset'] ?? '');
        if ($preset === '') {
            return;
        }
        $presets = config('theme-presets', []);
        if (! isset($presets[$preset])) {
            $issues[] = ['code' => 'typography_preset', 'message' => 'Theme preset not found; typography scale may be inconsistent.', 'severity' => 'info'];
            $score -= 5;
        }
    }

    /**
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateSpacingSystem(array $siteJsonLayout, array $themeTokens, array &$issues, int &$score): void
    {
        $spacing = config('design-defaults.spacing', []);
        if ($spacing === []) {
            return;
        }
        $allowed = config('design-defaults.allowed_spacing_px', [8, 16, 24, 32, 48, 64]);
        $allowed = is_array($allowed) ? array_map('intval', $allowed) : [8, 16, 24, 32, 48, 64];
        foreach ($spacing as $key => $value) {
            if (is_numeric($value) && ! in_array((int) $value, $allowed, true)) {
                $issues[] = ['code' => 'spacing_system', 'message' => 'Spacing value is not in 8px grid (8,16,24,32,48,64).', 'severity' => 'info'];
                $score -= 2;
                break;
            }
        }
    }

    /**
     * PART 4: Typography scale (H1 48px, H2 36px, H3 24px, body 16px). Incorrect typography reduces score.
     *
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateTypographyScaleInLayout(array $siteJsonLayout, array &$issues, int &$score): void
    {
        $scale = config('component-variants.design_rules.typography_scale', ['h1' => 48, 'h2' => 36, 'h3' => 24, 'body' => 16]);
        $allowedSizes = is_array($scale) ? array_values(array_map('intval', $scale)) : [48, 36, 24, 16];
        $invalid = [];
        $pages = is_array($siteJsonLayout['pages'] ?? null) ? $siteJsonLayout['pages'] : [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            foreach ($this->extractSectionsFromPage($page) as $section) {
                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                $collected = $this->collectTypographyPxFromProps($props);
                foreach ($collected as $px) {
                    if ($px > 0 && ! in_array($px, $allowedSizes, true)) {
                        $invalid[$px] = true;
                    }
                }
            }
        }
        if ($invalid !== []) {
            $issues[] = ['code' => 'typography_scale', 'message' => 'Typography sizes should follow scale (H1 48px, H2 36px, H3 24px, body 16px). Found invalid sizes.', 'severity' => 'warning'];
            $score -= 8;
        }
    }

    /**
     * PART 4: 8px spacing system. Random spacing reduces score.
     *
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateSpacingInLayout(array $siteJsonLayout, array &$issues, int &$score): void
    {
        $allowed = config('design-defaults.allowed_spacing_px', [8, 16, 24, 32, 48, 64]);
        $allowed = is_array($allowed) ? array_map('intval', $allowed) : [8, 16, 24, 32, 48, 64];
        $invalidCount = 0;
        $pages = is_array($siteJsonLayout['pages'] ?? null) ? $siteJsonLayout['pages'] : [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            foreach ($this->extractSectionsFromPage($page) as $section) {
                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                $collected = $this->collectSpacingPxFromProps($props);
                foreach ($collected as $px) {
                    if ($px > 0 && ! in_array($px, $allowed, true)) {
                        $invalidCount++;
                    }
                }
            }
        }
        if ($invalidCount > 0) {
            $issues[] = ['code' => 'spacing_not_8px_grid', 'message' => 'Spacing should use 8px grid (8,16,24,32,48,64).', 'severity' => 'warning'];
            $score -= min(10, 2 + $invalidCount);
        }
    }

    /**
     * PART 4: Consistent card design. Card-like sections should use consistent padding.
     *
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateCardConsistency(array $siteJsonLayout, array &$issues, int &$score): void
    {
        $allowed = config('design-defaults.allowed_spacing_px', [8, 16, 24, 32, 48, 64]);
        $allowed = is_array($allowed) ? array_map('intval', $allowed) : [8, 16, 24, 32, 48, 64];
        $cardLike = ['product_grid', 'product_carousel', 'testimonial', 'feature', 'card'];
        $inconsistent = 0;
        $pages = is_array($siteJsonLayout['pages'] ?? null) ? $siteJsonLayout['pages'] : [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            foreach ($this->extractSectionsFromPage($page) as $section) {
                $type = $this->sectionTypeFromNode($section);
                $isCardLike = false;
                foreach ($cardLike as $keyword) {
                    if (stripos($type, $keyword) !== false) {
                        $isCardLike = true;
                        break;
                    }
                }
                if (! $isCardLike) {
                    continue;
                }
                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                $collected = $this->collectSpacingPxFromProps($props);
                foreach ($collected as $px) {
                    if ($px > 0 && ! in_array($px, $allowed, true)) {
                        $inconsistent++;
                    }
                }
            }
        }
        if ($inconsistent > 0) {
            $issues[] = ['code' => 'card_inconsistent_spacing', 'message' => 'Card sections should use consistent padding from 8px grid.', 'severity' => 'info'];
            $score -= 4;
        }
    }

    /**
     * PART 4: Mobile responsiveness. Mobile problems reduce score.
     *
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateMobileResponsiveness(array $siteJsonLayout, array &$issues, int &$score): void
    {
        $hasResponsiveHint = false;
        $pages = is_array($siteJsonLayout['pages'] ?? null) ? $siteJsonLayout['pages'] : [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            foreach ($this->extractSectionsFromPage($page) as $section) {
                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                if ($this->nodeHasResponsiveHint($props)) {
                    $hasResponsiveHint = true;
                    break 2;
                }
            }
        }
        if (! $hasResponsiveHint && $pages !== []) {
            $issues[] = ['code' => 'mobile_responsive', 'message' => 'Layout should consider mobile responsiveness (grid columns, typography scaling).', 'severity' => 'info'];
            $score -= 5;
        }
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<int, int>
     */
    private function collectTypographyPxFromProps(array $props): array
    {
        $out = [];
        $keys = ['font_size', 'headline_size', 'heading_size', 'title_font_size', 'subtitle_font_size'];
        foreach ($keys as $key) {
            if (isset($props[$key]) && is_numeric($props[$key])) {
                $out[] = (int) $props[$key];
            }
        }
        $style = is_array($props['style'] ?? null) ? $props['style'] : [];
        if (isset($style['fontSize']) && is_numeric($style['fontSize'])) {
            $out[] = (int) $style['fontSize'];
        }
        $typo = is_array($props['typography'] ?? null) ? $props['typography'] : [];
        foreach (['h1', 'h2', 'h3', 'body'] as $k) {
            if (isset($typo[$k]) && is_numeric($typo[$k])) {
                $out[] = (int) $typo[$k];
            }
        }
        return $out;
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<int, int>
     */
    private function collectSpacingPxFromProps(array $props): array
    {
        $out = [];
        $keys = ['padding_y', 'padding_x', 'padding_top', 'padding_bottom', 'gap', 'margin', 'padding', 'section_gap', 'stack_gap'];
        foreach ($keys as $key) {
            if (isset($props[$key]) && is_numeric($props[$key])) {
                $out[] = (int) $props[$key];
            }
        }
        $style = is_array($props['style'] ?? null) ? $props['style'] : [];
        foreach (['padding', 'margin', 'gap', 'paddingTop', 'paddingBottom'] as $k) {
            if (isset($style[$k]) && is_numeric($style[$k])) {
                $out[] = (int) $style[$k];
            }
        }
        return $out;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function nodeHasResponsiveHint(array $props): bool
    {
        if (isset($props['responsive']) && is_array($props['responsive']) && $props['responsive'] !== []) {
            return true;
        }
        if (isset($props['columns_mobile']) || isset($props['mobile_columns'])) {
            return true;
        }
        $style = is_array($props['style'] ?? null) ? $props['style'] : [];
        if (isset($style['responsive']) || isset($style['columns'])) {
            return true;
        }
        return false;
    }

    /**
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateProductCardQuality(array $siteJsonLayout, array &$issues, int &$score): void
    {
        $pages = is_array($siteJsonLayout['pages'] ?? null) ? $siteJsonLayout['pages'] : [];
        $hasProductGrid = false;
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            $sections = $this->extractSectionsFromPage($page);
            foreach ($sections as $section) {
                $type = $this->sectionTypeFromNode($section);
                if (str_contains((string) $type, 'product_grid') || str_contains((string) $type, 'product_carousel')) {
                    $hasProductGrid = true;
                    break 2;
                }
            }
        }
        if (! $hasProductGrid) {
            $issues[] = ['code' => 'product_card_missing', 'message' => 'No product grid/carousel section found.', 'severity' => 'warning'];
            $score -= 10;
        }
    }

    /**
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateSectionDensity(array $siteJsonLayout, array &$issues, int &$score): void
    {
        $pages = is_array($siteJsonLayout['pages'] ?? null) ? $siteJsonLayout['pages'] : [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            $sections = $this->extractSectionsFromPage($page);
            $n = count($sections);
            if ($n > 15) {
                $issues[] = ['code' => 'density_high', 'message' => "Page has {$n} sections; consider reducing for balance.", 'severity' => 'info'];
                $score -= 2;
            }
        }
    }

    /**
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateRequiredPages(array $siteJsonLayout, array &$issues, int &$score): void
    {
        $required = ['home', 'shop', 'product', 'cart', 'checkout', 'contact'];
        $pages = is_array($siteJsonLayout['pages'] ?? null) ? $siteJsonLayout['pages'] : [];
        $slugs = [];
        foreach ($pages as $page) {
            if (is_array($page) && isset($page['slug'])) {
                $slugs[] = (string) $page['slug'];
            }
        }
        foreach ($required as $slug) {
            if (! in_array($slug, $slugs, true)) {
                $issues[] = ['code' => 'required_page', 'message' => "Required ecommerce page missing: {$slug}.", 'severity' => 'critical'];
                $score -= 12;
            }
        }
    }

    /**
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function evaluateTemplateQuality(?string $templateId, array &$issues, int &$score): void
    {
        if ($templateId === null || $templateId === '') {
            return;
        }
        $allMeta = config('template_metadata', []);
        $metadata = is_array($allMeta[$templateId] ?? null) ? $allMeta[$templateId] : null;
        if (is_array($metadata) && isset($metadata['quality_score'])) {
            $qs = (int) $metadata['quality_score'];
            if ($qs < self::MIN_DESIGN_SCORE_THRESHOLD) {
                $issues[] = ['code' => 'template_quality', 'message' => "Template {$templateId} has quality_score {$qs} below threshold.", 'severity' => 'warning'];
                $score -= 5;
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractSectionsFromPage(array $page): array
    {
        $nodes = $page['builder_nodes'] ?? $page['sections'] ?? [];
        if (! is_array($nodes)) {
            return [];
        }
        return array_values(array_filter($nodes, 'is_array'));
    }

    private function sectionTypeFromNode(array $node): string
    {
        return (string) ($node['type'] ?? $node['key'] ?? '');
    }

    private function hasHeroLikeSection(array $sectionKeys): bool
    {
        foreach ($sectionKeys as $key) {
            if (stripos($key, 'heading') !== false || stripos($key, 'hero') !== false) {
                return true;
            }
        }
        return false;
    }

    private function hasProductSection(array $sectionKeys): bool
    {
        foreach ($sectionKeys as $key) {
            if (stripos($key, 'product_grid') !== false || stripos($key, 'product_carousel') !== false) {
                return true;
            }
        }
        return false;
    }

    private function hasCategoryLikeSection(array $sectionKeys): bool
    {
        foreach ($sectionKeys as $key) {
            if (stripos($key, 'categor') !== false || stripos($key, 'collection') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if the first section in the list is hero-like (hero/heading).
     *
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function isFirstSectionHeroLike(array $sections): bool
    {
        $first = $sections[0] ?? null;
        if (! is_array($first)) {
            return false;
        }
        $key = strtolower((string) ($first['type'] ?? $first['key'] ?? ''));

        return str_contains($key, 'hero') || str_contains($key, 'heading');
    }
}
