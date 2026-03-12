<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Runs design quality tests for example e-commerce verticals (Design Guard PART 8).
 *
 * Generates blueprint per vertical via DesignDecisionService, evaluates with DesignQualityEvaluator,
 * returns scores and issues. When score < threshold, logs a design issue.
 *
 * @see new tasks.txt — AI Design Guard System PART 8 (Visual Test Generation)
 */
class DesignTestRunnerService
{
    /** @var array<string, string> businessType => template slug for test verticals */
    private const TEST_VERTICALS = [
        'fashion' => 'ecommerce-fashion',
        'electronics' => 'ecommerce-electronics',
        'cosmetics' => 'ecommerce-cosmetics',
        'pet' => 'ecommerce-pet',
        'furniture' => 'ecommerce-furniture',
    ];

    public function __construct(
        protected DesignDecisionService $designDecision,
        protected DesignQualityEvaluator $evaluator
    ) {}

    /**
     * Run design tests for all example verticals.
     *
     * @return array{threshold: int, results: array<int, array{vertical: string, label: string, template_slug: string, design_score: int, passed: bool, design_issues: array, logged: bool}>}
     */
    public function run(): array
    {
        $threshold = $this->evaluator->getMinDesignScore();
        $results = [];

        foreach (self::TEST_VERTICALS as $businessType => $templateSlug) {
            $result = $this->runOne($businessType, $templateSlug);
            $results[] = $result;

            if (! $result['passed'] && $result['logged']) {
                Log::channel('single')->warning('Design test failed: score below threshold', [
                    'vertical' => $result['vertical'],
                    'template_slug' => $result['template_slug'],
                    'design_score' => $result['design_score'],
                    'threshold' => $threshold,
                    'issues' => $result['design_issues'],
                ]);
            }
        }

        return [
            'threshold' => $threshold,
            'results' => $results,
        ];
    }

    /**
     * Run design test for one vertical.
     *
     * @return array{vertical: string, label: string, template_slug: string, design_score: int, passed: bool, design_issues: array, logged: bool}
     */
    public function runOne(string $businessType, string $templateSlug): array
    {
        $config = [
            'siteType' => 'ecommerce',
            'businessType' => $businessType,
            'designStyle' => 'luxury_minimal',
        ];

        $blueprint = $this->designDecision->configToBlueprint($config);
        $siteJsonLayout = $this->blueprintToSiteJsonLayout($blueprint);
        $themeTokens = ['preset' => $blueprint['theme_preset']];

        $evaluation = $this->evaluator->evaluate($siteJsonLayout, $themeTokens, $templateSlug);
        $passed = $evaluation['passed'];
        $logged = ! $passed;

        return [
            'vertical' => $businessType,
            'label' => $this->labelForVertical($businessType),
            'template_slug' => $templateSlug,
            'design_score' => $evaluation['design_score'],
            'passed' => $passed,
            'design_issues' => $evaluation['design_issues'],
            'logged' => $logged,
        ];
    }

    /**
     * @param  array{theme_preset: string, default_pages: array<int, array{slug: string, title: string, sections: array}>}  $blueprint
     * @return array{pages: array<int, array{slug: string, sections: array}>}
     */
    private function blueprintToSiteJsonLayout(array $blueprint): array
    {
        $pages = [];
        foreach ($blueprint['default_pages'] ?? [] as $p) {
            $pages[] = [
                'slug' => (string) ($p['slug'] ?? ''),
                'sections' => $p['sections'] ?? [],
            ];
        }

        return ['pages' => $pages];
    }

    private function labelForVertical(string $businessType): string
    {
        return match ($businessType) {
            'fashion' => 'Fashion store',
            'electronics' => 'Electronics store',
            'cosmetics' => 'Cosmetics store',
            'pet' => 'Pet store',
            'furniture' => 'Furniture store',
            default => ucfirst($businessType).' store',
        };
    }

    /** @return array<string, string> */
    public static function getTestVerticals(): array
    {
        return self::TEST_VERTICALS;
    }
}
