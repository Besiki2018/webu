<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsAiGenerationQualityScoringEngine
{
    public const VERSION = 1;

    public function __construct(
        protected CmsAiSchemaValidationService $schemaValidator
    ) {}

    /**
     * Rule-based baseline scorer for AI generation outputs.
     *
     * @param  array<string, mixed>  $aiOutput
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function scoreOutput(array $aiOutput, array $context = []): array
    {
        $schema = $this->schemaValidator->validateOutputPayload($aiOutput);
        if (! ($schema['valid'] ?? false)) {
            return [
                'ok' => false,
                'code' => 'invalid_ai_output',
                'errors' => is_array($schema['errors'] ?? null) ? $schema['errors'] : [],
                'warnings' => [],
                'score' => null,
                'verdict' => 'ineligible',
                'dimensions' => [],
                'gates' => [
                    'validation' => null,
                    'render' => null,
                    'penalty_points' => 0,
                ],
                'summary' => [
                    'scorer_version' => self::VERSION,
                    'eligible' => false,
                    'reason' => 'schema_invalid',
                ],
                'validation' => [
                    'schema' => $this->compactValidationReport($schema),
                ],
            ];
        }

        $pages = is_array($aiOutput['pages'] ?? null) ? array_values($aiOutput['pages']) : [];
        $pageNodes = $this->collectPageNodes($pages);
        $nodeCount = count($pageNodes);
        $pageSlugs = collect($pages)
            ->map(fn ($page): ?string => is_array($page) && is_string($page['slug'] ?? null) ? trim((string) $page['slug']) : null)
            ->filter()
            ->values()
            ->all();

        $visual = $this->scoreVisualConsistency($aiOutput, $pageNodes);
        $layout = $this->scoreLayoutBalance($pages, $pageNodes);
        $funnel = $this->scoreFunnelReadiness($pages, $pageNodes);
        $mobile = $this->scoreMobileFriendliness($pageNodes);

        $dimensions = [
            'visual_consistency' => $visual,
            'layout_balance' => $layout,
            'funnel_readiness' => $funnel,
            'mobile_friendliness' => $mobile,
        ];

        $weightedBase = (int) round(
            ($visual['score'] * 0.30)
            + ($layout['score'] * 0.25)
            + ($funnel['score'] * 0.25)
            + ($mobile['score'] * 0.20)
        );

        $gateAdjustment = $this->applyGateAdjustments($context);
        $finalScore = $this->clampInt($weightedBase + (int) $gateAdjustment['bonus_points'] - (int) $gateAdjustment['penalty_points'], 0, 100);

        $warnings = array_values(array_unique(array_merge(
            $visual['warnings'],
            $layout['warnings'],
            $funnel['warnings'],
            $mobile['warnings'],
            $gateAdjustment['warnings'],
        )));

        $eligible = ($finalScore >= 1) && ! (bool) ($gateAdjustment['hard_fail'] ?? false);

        return [
            'ok' => true,
            'errors' => [],
            'warnings' => $warnings,
            'score' => $finalScore,
            'verdict' => $this->verdictForScore($finalScore, $eligible),
            'dimensions' => [
                'visual_consistency' => $this->formatDimension($visual, 0.30),
                'layout_balance' => $this->formatDimension($layout, 0.25),
                'funnel_readiness' => $this->formatDimension($funnel, 0.25),
                'mobile_friendliness' => $this->formatDimension($mobile, 0.20),
            ],
            'gates' => [
                'validation' => $gateAdjustment['validation'],
                'render' => $gateAdjustment['render'],
                'bonus_points' => $gateAdjustment['bonus_points'],
                'penalty_points' => $gateAdjustment['penalty_points'],
                'hard_fail' => $gateAdjustment['hard_fail'],
            ],
            'summary' => [
                'scorer_version' => self::VERSION,
                'eligible' => $eligible,
                'page_count' => count($pages),
                'node_count' => $nodeCount,
                'page_slugs' => $pageSlugs,
                'ecommerce_signal' => $this->hasEcommerceSignal($pages, $pageNodes),
            ],
            'validation' => [
                'schema' => $this->compactValidationReport($schema),
            ],
        ];
    }

    /**
     * Rank multiple candidate outputs and return the highest-scoring eligible candidate.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    public function rankCandidates(array $candidates): array
    {
        $ranked = [];

        foreach ($candidates as $index => $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $aiOutput = is_array($candidate['ai_output'] ?? null) ? $candidate['ai_output'] : [];
            $context = is_array($candidate['context'] ?? null) ? $candidate['context'] : [];
            $candidateId = $candidate['candidate_id'] ?? ('candidate_'.$index);

            $score = $this->scoreOutput($aiOutput, $context);
            $ranked[] = [
                'candidate_id' => is_scalar($candidateId) ? (string) $candidateId : ('candidate_'.$index),
                'score' => $score,
                'meta' => is_array($candidate['meta'] ?? null) ? $candidate['meta'] : [],
            ];
        }

        usort($ranked, function (array $a, array $b): int {
            $aEligible = (bool) data_get($a, 'score.summary.eligible', false);
            $bEligible = (bool) data_get($b, 'score.summary.eligible', false);
            if ($aEligible !== $bEligible) {
                return $aEligible ? -1 : 1;
            }

            $aScore = (int) (data_get($a, 'score.score') ?? -1);
            $bScore = (int) (data_get($b, 'score.score') ?? -1);
            if ($aScore !== $bScore) {
                return $bScore <=> $aScore;
            }

            $aVerdict = (string) (data_get($a, 'score.verdict') ?? '');
            $bVerdict = (string) (data_get($b, 'score.verdict') ?? '');

            return strcmp($bVerdict, $aVerdict);
        });

        $selected = collect($ranked)->first(fn (array $item): bool => (bool) data_get($item, 'score.summary.eligible', false));

        return [
            'ok' => true,
            'scorer' => [
                'kind' => 'rule_based_quality_scorer',
                'version' => self::VERSION,
            ],
            'ranked_candidates' => $ranked,
            'selected_candidate' => $selected,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<int, array<string, mixed>>
     */
    private function collectPageNodes(array $pages): array
    {
        $nodes = [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $pageSlug = is_string($page['slug'] ?? null) ? (string) $page['slug'] : null;
            foreach ((array) ($page['builder_nodes'] ?? []) as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $nodes[] = [
                    'page_slug' => $pageSlug,
                    'node' => $node,
                ];
            }
        }

        return $nodes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pageNodes
     * @return array{score:int,reasons:array<int,string>,warnings:array<int,string>}
     */
    private function scoreVisualConsistency(array $aiOutput, array $pageNodes): array
    {
        $score = 35;
        $reasons = [];
        $warnings = [];

        $themePreset = trim((string) data_get($aiOutput, 'theme.theme_settings_patch.preset', ''));
        if ($themePreset !== '') {
            $score += 20;
            $reasons[] = 'Theme preset is present in theme_settings_patch.';
        } else {
            $warnings[] = 'visual_consistency: theme preset is missing.';
        }

        $nodeThemePresets = [];
        $accentColors = [];

        foreach ($pageNodes as $entry) {
            $node = is_array($entry['node'] ?? null) ? $entry['node'] : [];
            $nodePreset = trim((string) data_get($node, 'props.style.theme_preset', ''));
            if ($nodePreset !== '') {
                $nodeThemePresets[] = strtolower($nodePreset);
            }

            $accent = trim((string) data_get($node, 'props.style.accent_color', ''));
            if ($accent !== '') {
                $accentColors[] = strtolower($accent);
            }
        }

        if ($nodeThemePresets !== []) {
            $presetCounts = array_count_values($nodeThemePresets);
            $dominantCount = max($presetCounts);
            $dominantRatio = $dominantCount / max(1, count($nodeThemePresets));
            $score += (int) round(25 * $dominantRatio);
            $reasons[] = sprintf('Node theme preset consistency ratio %.0f%%.', $dominantRatio * 100);

            if (count($presetCounts) > 2) {
                $score -= min(15, (count($presetCounts) - 2) * 5);
                $warnings[] = 'visual_consistency: too many distinct node-level theme presets.';
            }
        } elseif ($themePreset !== '') {
            $score += 8;
            $reasons[] = 'Theme-level preset present; node-level style preset hints not required.';
        } else {
            $warnings[] = 'visual_consistency: no theme preset or node-level style preset hints detected.';
        }

        $themePrimary = trim((string) data_get($aiOutput, 'theme.theme_settings_patch.theme_tokens.colors.primary', ''));
        if ($themePrimary === '') {
            $themePrimary = trim((string) data_get($aiOutput, 'theme.theme_settings_patch.colors.primary', ''));
        }
        if ($themePrimary !== '') {
            $accentColors[] = strtolower($themePrimary);
        }

        $accentColors = array_values(array_filter($accentColors, fn (string $v): bool => $v !== ''));
        if ($accentColors === []) {
            $warnings[] = 'visual_consistency: no accent color signals found.';
        } else {
            $uniqueAccents = array_values(array_unique($accentColors));
            if (count($uniqueAccents) <= 2) {
                $score += 12;
                $reasons[] = 'Accent color palette is constrained (<=2 variants).';
            } else {
                $score -= min(20, (count($uniqueAccents) - 2) * 5);
                $warnings[] = 'visual_consistency: accent color palette is too fragmented.';
            }
        }

        return [
            'score' => $this->clampInt($score, 0, 100),
            'reasons' => $reasons,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<int, array<string, mixed>>  $pageNodes
     * @return array{score:int,reasons:array<int,string>,warnings:array<int,string>}
     */
    private function scoreLayoutBalance(array $pages, array $pageNodes): array
    {
        $score = 45;
        $reasons = [];
        $warnings = [];

        if ($pages === []) {
            return [
                'score' => 0,
                'reasons' => [],
                'warnings' => ['layout_balance: no pages were generated.'],
            ];
        }

        $wellSizedPages = 0;
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $slug = strtolower(trim((string) ($page['slug'] ?? '')));
            $nodes = is_array($page['builder_nodes'] ?? null) ? array_values($page['builder_nodes']) : [];
            $nodeCount = count(array_filter($nodes, 'is_array'));

            if ($nodeCount === 0) {
                $score -= 30;
                $warnings[] = "layout_balance: page [{$slug}] has no builder nodes.";
                continue;
            }

            if ($nodeCount >= 1 && $nodeCount <= 6) {
                $wellSizedPages++;
            } elseif ($nodeCount > 10) {
                $score -= 10;
                $warnings[] = "layout_balance: page [{$slug}] has too many sections ({$nodeCount}).";
            }

            $duplicateRun = 1;
            $lastType = null;
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $type = $this->compactNodeType((string) ($node['type'] ?? ''));
                if ($type !== '' && $type === $lastType) {
                    $duplicateRun++;
                    if ($duplicateRun >= 3) {
                        $score -= 5;
                        $warnings[] = "layout_balance: repeated adjacent component type on page [{$slug}] ({$type}).";
                        break;
                    }
                } else {
                    $duplicateRun = 1;
                }
                $lastType = $type;
            }

            if ($slug === 'home' && is_array($nodes[0] ?? null)) {
                $firstType = $this->compactNodeType((string) ($nodes[0]['type'] ?? ''));
                if (str_contains($firstType, 'hero')) {
                    $score += 15;
                    $reasons[] = 'Home page starts with a hero-like section.';
                } else {
                    $score -= 10;
                    $warnings[] = 'layout_balance: home page does not start with a hero-like section.';
                }
            }
        }

        if ($wellSizedPages > 0) {
            $score += min(20, $wellSizedPages * 5);
            $reasons[] = sprintf('%d page(s) have balanced section counts (1..6).', $wellSizedPages);
        }

        if (count($pageNodes) >= count($pages)) {
            $score += 5;
        }

        return [
            'score' => $this->clampInt($score, 0, 100),
            'reasons' => $reasons,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<int, array<string, mixed>>  $pageNodes
     * @return array{score:int,reasons:array<int,string>,warnings:array<int,string>}
     */
    private function scoreFunnelReadiness(array $pages, array $pageNodes): array
    {
        $reasons = [];
        $warnings = [];
        $score = 20;

        $pageMap = [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            $slug = strtolower(trim((string) ($page['slug'] ?? '')));
            if ($slug !== '') {
                $pageMap[$slug] = $page;
            }
        }

        $isEcommerce = $this->hasEcommerceSignal($pages, $pageNodes);
        if ($isEcommerce) {
            $required = ['home', 'product', 'cart', 'checkout'];
            $present = 0;
            foreach ($required as $slug) {
                if (isset($pageMap[$slug])) {
                    $present++;
                } else {
                    $warnings[] = "funnel_readiness: missing ecommerce funnel page [{$slug}].";
                }
            }
            $score += (int) round(($present / count($required)) * 50);
            $reasons[] = sprintf('Ecommerce funnel pages present: %d/%d.', $present, count($required));

            $productRoute = (string) data_get($pageMap, 'product.route_pattern', '');
            if (str_contains($productRoute, '{slug}')) {
                $score += 10;
                $reasons[] = 'Product route pattern includes dynamic slug.';
            } else {
                $warnings[] = 'funnel_readiness: product page route_pattern is missing {slug}.';
            }

            if ($this->hasCtaOnHome($pageMap['home'] ?? null)) {
                $score += 10;
                $reasons[] = 'Home page includes CTA content signal.';
            } else {
                $warnings[] = 'funnel_readiness: home page CTA signal not detected.';
            }

            $checkoutPriority = (string) data_get($pageMap, 'checkout.builder_nodes.0.props.advanced.ai_priority', '');
            if ($checkoutPriority === 'conversion') {
                $score += 10;
                $reasons[] = 'Checkout page has conversion priority marker.';
            } else {
                $warnings[] = 'funnel_readiness: checkout conversion marker missing.';
            }
        } else {
            $required = ['home', 'contact'];
            $present = 0;
            foreach ($required as $slug) {
                if (isset($pageMap[$slug])) {
                    $present++;
                }
            }
            $score += (int) round(($present / count($required)) * 45);
            $reasons[] = sprintf('Business funnel pages present: %d/%d.', $present, count($required));

            if ($this->hasCtaOnHome($pageMap['home'] ?? null)) {
                $score += 15;
                $reasons[] = 'Home page includes CTA content signal.';
            } else {
                $warnings[] = 'funnel_readiness: home page CTA signal not detected.';
            }
        }

        return [
            'score' => $this->clampInt($score, 0, 100),
            'reasons' => $reasons,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pageNodes
     * @return array{score:int,reasons:array<int,string>,warnings:array<int,string>}
     */
    private function scoreMobileFriendliness(array $pageNodes): array
    {
        $score = 35;
        $reasons = [];
        $warnings = [];

        if ($pageNodes === []) {
            return [
                'score' => 0,
                'reasons' => [],
                'warnings' => ['mobile_friendliness: no nodes available to evaluate.'],
            ];
        }

        $canonicalResponsiveGroups = 0;
        $explicitMobileSignals = 0;
        $gridNodesChecked = 0;

        foreach ($pageNodes as $entry) {
            $node = is_array($entry['node'] ?? null) ? $entry['node'] : [];
            $responsive = data_get($node, 'props.responsive');
            $states = data_get($node, 'props.states');
            $style = is_array(data_get($node, 'props.style')) ? data_get($node, 'props.style') : [];
            $advanced = is_array(data_get($node, 'props.advanced')) ? data_get($node, 'props.advanced') : [];

            if (is_array($responsive) && is_array($states)) {
                $canonicalResponsiveGroups++;
            }

            if (
                is_array($responsive)
                && (
                    is_array($responsive['mobile'] ?? null)
                    || is_array($responsive['tablet'] ?? null)
                    || array_key_exists('hide_on_mobile', $responsive)
                    || array_key_exists('hide_on_tablet', $responsive)
                )
            ) {
                $explicitMobileSignals++;
            }

            $nodeType = $this->compactNodeType((string) ($node['type'] ?? ''));
            $looksLikeGrid = str_contains($nodeType, 'grid');
            $gridCols = $style['grid_columns_desktop'] ?? null;
            if ($looksLikeGrid && (is_int($gridCols) || is_numeric($gridCols))) {
                $gridNodesChecked++;
                $cols = (int) $gridCols;
                if ($cols <= 4 && $cols > 0) {
                    $score += 3;
                } elseif ($cols >= 6) {
                    $score -= 5;
                    $warnings[] = "mobile_friendliness: grid node uses high desktop columns ({$cols}).";
                }
            }

            if ((bool) ($advanced['hide_on_mobile'] ?? false) && ! (bool) ($advanced['hide_on_desktop'] ?? false)) {
                $warnings[] = 'mobile_friendliness: a node is hidden on mobile; verify mobile fallback content.';
            }
        }

        $coverageRatio = $canonicalResponsiveGroups / max(1, count($pageNodes));
        $score += (int) round($coverageRatio * 35);
        $reasons[] = sprintf('Canonical responsive/state groups present on %.0f%% of nodes.', $coverageRatio * 100);

        if ($explicitMobileSignals > 0) {
            $score += min(15, $explicitMobileSignals * 3);
            $reasons[] = sprintf('Detected %d explicit responsive/mobile override signal(s).', $explicitMobileSignals);
        } else {
            $score += 5;
            $warnings[] = 'mobile_friendliness: no explicit responsive override signals detected (baseline only).';
        }

        if ($gridNodesChecked > 0) {
            $reasons[] = sprintf('Checked %d grid-like node(s) for desktop column density.', $gridNodesChecked);
        }

        return [
            'score' => $this->clampInt($score, 0, 100),
            'reasons' => $reasons,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *   validation:?array<string,mixed>,
     *   render:?array<string,mixed>,
     *   bonus_points:int,
     *   penalty_points:int,
     *   hard_fail:bool,
     *   warnings:array<int,string>
     * }
     */
    private function applyGateAdjustments(array $context): array
    {
        $bonus = 0;
        $penalty = 0;
        $warnings = [];
        $hardFail = false;

        $validationReport = is_array($context['validation_report'] ?? null) ? $context['validation_report'] : null;
        $renderReport = is_array($context['render_report'] ?? null) ? $context['render_report'] : null;

        if ($validationReport !== null) {
            $validationOk = (bool) ($validationReport['ok'] ?? false);
            if ($validationOk) {
                $bonus += 5;
                $warnings[] = 'gates: validation_report passed (+5 readiness bonus).';
            } else {
                $penalty += 35;
                $hardFail = true;
                $warnings[] = 'gates: validation_report failed (-35 penalty, ineligible).';
            }
        }

        if ($renderReport !== null) {
            $renderOk = (bool) ($renderReport['ok'] ?? false);
            if ($renderOk) {
                $bonus += 5;
                $warnings[] = 'gates: render_report passed (+5 readiness bonus).';
            } else {
                $penalty += 20;
                $warnings[] = 'gates: render_report failed (-20 penalty).';
            }
        }

        return [
            'validation' => $validationReport,
            'render' => $renderReport,
            'bonus_points' => $bonus,
            'penalty_points' => $penalty,
            'hard_fail' => $hardFail,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $homePage
     */
    private function hasCtaOnHome(?array $homePage): bool
    {
        if (! is_array($homePage)) {
            return false;
        }

        $firstNode = is_array(data_get($homePage, 'builder_nodes.0')) ? data_get($homePage, 'builder_nodes.0') : [];
        $content = is_array(data_get($firstNode, 'props.content')) ? data_get($firstNode, 'props.content') : [];

        if (is_array($content['primary_cta'] ?? null)) {
            $label = trim((string) data_get($content, 'primary_cta.label', ''));
            $url = trim((string) data_get($content, 'primary_cta.url', ''));

            return $label !== '' && $url !== '';
        }

        foreach (['cta_label', 'button_label', 'primary_button_label'] as $key) {
            if (trim((string) ($content[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<int, array<string, mixed>>  $pageNodes
     */
    private function hasEcommerceSignal(array $pages, array $pageNodes): bool
    {
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $slug = strtolower(trim((string) ($page['slug'] ?? '')));
            $route = strtolower(trim((string) ($page['route_pattern'] ?? '')));

            if (in_array($slug, ['shop', 'product', 'cart', 'checkout', 'account', 'orders', 'order', 'order-detail'], true)) {
                return true;
            }

            if (str_contains($route, '/product/') || str_contains($route, '/checkout') || str_contains($route, '/cart')) {
                return true;
            }
        }

        foreach ($pageNodes as $entry) {
            $node = is_array($entry['node'] ?? null) ? $entry['node'] : [];
            $type = $this->compactNodeType((string) ($node['type'] ?? ''));
            if (str_contains($type, 'ecommerce') || str_contains($type, 'cart') || str_contains($type, 'checkout') || str_contains($type, 'product')) {
                return true;
            }
        }

        return false;
    }

    private function compactNodeType(string $type): string
    {
        return str_replace(['-', '_', ' '], '', Str::lower(trim($type)));
    }

    /**
     * @param  array{score:int,reasons:array<int,string>,warnings:array<int,string>}  $dimension
     * @return array<string, mixed>
     */
    private function formatDimension(array $dimension, float $weight): array
    {
        return [
            'score' => (int) $dimension['score'],
            'weight' => $weight,
            'weighted_contribution' => round(((int) $dimension['score']) * $weight, 2),
            'reasons' => array_values($dimension['reasons']),
            'warnings' => array_values($dimension['warnings']),
        ];
    }

    private function verdictForScore(int $score, bool $eligible): string
    {
        if (! $eligible) {
            return 'ineligible';
        }

        return match (true) {
            $score >= 85 => 'excellent',
            $score >= 70 => 'good',
            $score >= 50 => 'fair',
            default => 'poor',
        };
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
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
}
