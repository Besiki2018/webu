<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Orchestrates: DesignBrief → TemplateVariantPlan → ThemeTokens → Blueprint → Design Guard.
 * Director-guided AI prompting: brief → plan → tokens → blueprint (from template + plan) → Design Guard evaluation;
 * if score < threshold, adjust via director rules and re-run (max iterations). AI is only allowed to output Blueprint JSON or Patch JSON.
 *
 * @see new tasks.txt — AI Design Director PART 5 (Director-guided AI Prompting)
 */
class AiDesignDirectorOrchestrator
{
    public function __construct(
        protected DesignBriefGenerator $briefGenerator,
        protected TemplateSelectorService $templateSelector,
        protected TemplateVariantPlanner $variantPlanner,
        protected ThemeTokenComposer $tokenComposer,
        protected DesignQualityEvaluator $designEvaluator,
        protected DesignGuardService $designGuard,
        protected AiOutputValidator $outputValidator,
        protected ReadyTemplatesService $readyTemplates,
    ) {}

    /**
     * Full pipeline: user input → brief → plan → tokens → blueprint (template + plan) → Design Guard.
     * Builds JSON blueprint from selected template and runs Design Guard (evaluate + improve loop); returns final blueprint and score.
     *
     * @param  array{business_type?: string, brand_vibe?: string, target_audience?: string, required_features?: array, content_assets?: array}  $userInput
     * @return array{
     *   ok: bool,
     *   design_brief: array,
     *   template_selection: array,
     *   variant_plan: array,
     *   theme_tokens: array,
     *   blueprint: array{pages: array, theme: array, template_id: string}|null,
     *   design_score: int|null,
     *   design_passed: bool,
     *   design_guard_iterations: int,
     *   errors: array
     * }
     */
    public function run(array $userInput): array
    {
        $errors = [];

        $designBrief = $this->briefGenerator->generate($userInput);
        $templateSelection = $this->templateSelector->selectFromDesignBrief($designBrief);
        $variantPlan = $this->variantPlanner->plan($designBrief);
        $tokenResult = $this->tokenComposer->compose($designBrief, $userInput['content_assets'] ?? []);
        $themeTokens = $tokenResult['theme_tokens'] ?? [];
        if (! ($tokenResult['valid'] ?? false)) {
            foreach ($tokenResult['errors'] ?? [] as $err) {
                $errors[] = ['code' => 'theme_tokens', 'message' => is_array($err) ? ($err['error'] ?? json_encode($err)) : (string) $err];
            }
        }

        $templateId = $templateSelection['template_id'] ?? $variantPlan['template_id'] ?? null;
        $blueprint = null;
        $designScore = null;
        $designPassed = false;
        $designGuardIterations = 0;

        if ($templateId !== null && $templateId !== '') {
            $templateData = $this->readyTemplates->loadBySlug($templateId);
            if ($templateData !== [] && isset($templateData['default_pages']) && is_array($templateData['default_pages'])) {
                $siteLayout = $this->defaultPagesToSiteLayout($templateData['default_pages']);
                $theme = array_merge(
                    ['preset' => $templateSelection['theme_variant'] ?? $templateData['theme_preset'] ?? 'luxury_minimal'],
                    $themeTokens
                );
                $guardResult = $this->designGuard->evaluateAndImprove(
                    $siteLayout,
                    $theme,
                    $templateId,
                    $designBrief,
                    5
                );
                $designScore = $guardResult['score'];
                $designPassed = $guardResult['passed'];
                $designGuardIterations = $guardResult['iterations'];
                $blueprint = [
                    'pages' => $guardResult['final_layout']['pages'] ?? $siteLayout['pages'],
                    'theme' => $guardResult['final_theme'] ?? $theme,
                    'template_id' => $guardResult['final_template_id'] ?? $templateId,
                ];
                Log::channel('single')->info('Director-guided pipeline', [
                    'brief' => $designBrief,
                    'plan' => $variantPlan,
                    'tokens_keys' => array_keys($themeTokens),
                    'score' => $designScore,
                    'passed' => $designPassed,
                    'iterations' => $designGuardIterations,
                ]);
            } else {
                $errors[] = ['code' => 'template_load', 'message' => 'Template not found or has no default_pages: ' . $templateId];
            }
        } else {
            $errors[] = ['code' => 'template_selection', 'message' => 'No template_id from selection or plan.'];
        }

        return [
            'ok' => $errors === [],
            'design_brief' => $designBrief,
            'template_selection' => $templateSelection,
            'variant_plan' => $variantPlan,
            'theme_tokens' => $themeTokens,
            'blueprint' => $blueprint,
            'design_score' => $designScore,
            'design_passed' => $designPassed,
            'design_guard_iterations' => $designGuardIterations,
            'errors' => $errors,
        ];
    }

    /**
     * Convert template default_pages to site layout shape expected by Design Guard (pages with slug + sections).
     *
     * @param  array<int, array{slug: string, title?: string, sections: array}>  $defaultPages
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
     * Validate generated blueprint (pages + theme) with Design Guard and AI Output Validator.
     *
     * @param  array{pages: array, theme?: array}  $blueprint
     * @param  array{vertical?: string, vibe?: string}  $designBrief
     * @return array{valid: bool, output_valid: bool, design_passed: bool, score: int, errors: array}
     */
    public function validateBlueprint(array $blueprint, array $designBrief = []): array
    {
        $outputValidation = $this->outputValidator->validate($blueprint);
        $siteLayout = ['pages' => $blueprint['pages'] ?? []];
        $themeTokens = $blueprint['theme']['theme_settings_patch'] ?? $blueprint['theme'] ?? [];
        $templateId = $designBrief['template_id'] ?? null;
        if ($templateId === null && isset($blueprint['template_id'])) {
            $templateId = $blueprint['template_id'];
        }

        $evaluateResult = $this->designEvaluator->evaluate($siteLayout, $themeTokens, $templateId);

        return [
            'valid' => $outputValidation['valid'] && $evaluateResult['passed'],
            'output_valid' => $outputValidation['valid'],
            'design_passed' => $evaluateResult['passed'],
            'score' => $evaluateResult['design_score'],
            'errors' => array_merge(
                $outputValidation['errors'],
                array_map(fn ($i) => ['code' => $i['code'], 'message' => $i['message']], $evaluateResult['design_issues'])
            ),
        ];
    }
}
