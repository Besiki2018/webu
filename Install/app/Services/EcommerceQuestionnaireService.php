<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Structured e-commerce questionnaire: 10 core + optional questions.
 * One question at a time; answers → DesignBrief → requirement_config for blueprint.
 *
 * @see new tasks.txt — AI Chat Questionnaire System PART 1–6, 9, 11
 */
class EcommerceQuestionnaireService
{
    /**
     * Get the next question key to show (or null if complete).
     *
     * @param  array<string, mixed>  $answers  Collected answers key => value
     * @param  bool  $includeOptional  Whether to show optional questions after core
     * @return string|null  Next question key or null when complete
     */
    public function getNextQuestionKey(array $answers, bool $includeOptional = true): ?string
    {
        $core = config('questionnaire.core_questions', []);
        foreach ($core as $q) {
            $key = $q['key'] ?? '';
            if ($key === '') {
                continue;
            }
            if (! array_key_exists($key, $answers)) {
                return $key;
            }
            $val = $answers[$key];
            if ($q['required'] ?? false && ($val === null || $val === '' || (is_array($val) && $val === []))) {
                return $key;
            }
        }
        if (! $includeOptional) {
            return null;
        }
        $optional = config('questionnaire.optional_questions', []);
        foreach ($optional as $q) {
            $key = $q['key'] ?? '';
            if ($key === '' || array_key_exists($key, $answers)) {
                continue;
            }
            return $key;
        }
        return null;
    }

    /**
     * Get question definition by key.
     *
     * @return array<string, mixed>|null
     */
    public function getQuestion(string $key): ?array
    {
        foreach (config('questionnaire.core_questions', []) as $q) {
            if (($q['key'] ?? '') === $key) {
                return $q;
            }
        }
        foreach (config('questionnaire.optional_questions', []) as $q) {
            if (($q['key'] ?? '') === $key) {
                return $q;
            }
        }
        return null;
    }

    /**
     * Get current questionnaire state for frontend (next question or completed).
     *
     * @param  array<string, mixed>  $answers
     * @return array{completed: bool, next_question: array|null, answers: array, design_brief: array|null}
     */
    public function getState(array $answers): array
    {
        $nextKey = $this->getNextQuestionKey($answers);
        $completed = $nextKey === null;
        $nextQuestion = $nextKey !== null ? $this->getQuestion($nextKey) : null;
        $designBrief = $completed ? $this->buildDesignBriefFromAnswers($answers) : null;

        return [
            'completed' => $completed,
            'next_question' => $nextQuestion,
            'answers' => $answers,
            'design_brief' => $designBrief,
        ];
    }

    /**
     * Build DesignBrief from questionnaire answers (for TemplateSelector + Blueprint).
     *
     * @param  array<string, mixed>  $answers
     * @return array{vertical: string, vibe: string, tone: string, product_volume: string, homepage_focus: string, payments: array, shipping: array, currency: string, contact: string, store_name: string, target_audience: string, content_assets: array, required_features: array, recommended_templates: array, must_have_sections: array}
     */
    public function buildDesignBriefFromAnswers(array $answers): array
    {
        $briefGenerator = app(DesignBriefGenerator::class);
        $vertical = $this->mapBusinessTypeToVertical((string) ($answers['business_type'] ?? 'general'));
        $vibe = $this->mapStyleToVibe((string) ($answers['brand_style'] ?? 'modern'));
        $tone = (string) ($answers['store_tone'] ?? 'premium');
        $productVolume = (string) ($answers['product_volume'] ?? '10-50');
        $homepageFocus = (string) ($answers['homepage_focus'] ?? 'products');
        $payments = is_array($answers['payments'] ?? null) ? array_values($answers['payments']) : ['card'];
        $shipping = is_array($answers['shipping'] ?? null) ? array_values($answers['shipping']) : ['courier'];
        $currency = trim((string) ($answers['currency'] ?? 'GEL'));
        if ($currency === '') {
            $currency = 'GEL';
        }
        $contact = trim((string) ($answers['contact'] ?? ''));
        $storeName = trim((string) ($answers['store_name'] ?? ''));
        $targetAudience = (string) ($answers['target_audience'] ?? 'everyone');

        $contentAssets = [];
        if (isset($answers['brand_colors']) && is_array($answers['brand_colors'])) {
            $contentAssets['primary_color'] = $answers['brand_colors']['primary'] ?? null;
            $contentAssets['secondary_color'] = $answers['brand_colors']['secondary'] ?? null;
        }

        $input = [
            'business_type' => $answers['business_type'] ?? 'general',
            'brand_vibe' => $vibe,
            'target_audience' => $targetAudience,
            'required_features' => array_merge(
                $productVolume === '50+' ? ['compact'] : [],
                in_array('card', $payments, true) ? [] : ['minimal_ui']
            ),
            'content_assets' => $contentAssets,
        ];
        $brief = $briefGenerator->generate($input);

        return array_merge($brief, [
            'vertical' => $vertical,
            'vibe' => $vibe,
            'tone' => $tone,
            'product_volume' => $productVolume,
            'homepage_focus' => $homepageFocus,
            'payments' => $payments,
            'shipping' => $shipping,
            'currency' => $currency,
            'contact' => $contact,
            'store_name' => $storeName,
            'target_audience' => $targetAudience,
        ]);
    }

    /**
     * Build requirement_config (siteType, businessType, designStyle, payments, shipping, etc.) for DesignDecisionService.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function buildRequirementConfigFromAnswers(array $answers): array
    {
        $brief = $this->buildDesignBriefFromAnswers($answers);
        $designStyleMap = [
            'luxury_minimal' => 'luxury_minimal',
            'minimal' => 'luxury_minimal',
            'modern' => 'bold_startup',
            'bold' => 'bold_startup',
            'playful' => 'soft_pastel',
            'corporate' => 'corporate_clean',
            'luxury' => 'luxury_minimal',
        ];
        $designStyle = $designStyleMap[$brief['vibe'] ?? ''] ?? 'luxury_minimal';

        return [
            'siteType' => 'ecommerce',
            'businessType' => $answers['business_type'] ?? 'general',
            'designStyle' => $designStyle,
            'payments' => $brief['payments'],
            'shipping' => $brief['shipping'],
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'categories', 'featured_products', 'testimonials', 'newsletter'],
            'store_name' => $brief['store_name'] ?? '',
            'currency' => $brief['currency'] ?? 'GEL',
            'contact' => $brief['contact'] ?? '',
        ];
    }

    /**
     * Apply answers to site_settings shape (store_name, currency, contact, logo).
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function buildSiteSettingsFromAnswers(array $answers): array
    {
        return [
            'store_name' => trim((string) ($answers['store_name'] ?? '')),
            'currency' => trim((string) ($answers['currency'] ?? 'GEL')) ?: 'GEL',
            'contact' => trim((string) ($answers['contact'] ?? '')),
            'logo' => $answers['logo'] ?? null,
        ];
    }

    private function mapBusinessTypeToVertical(string $businessType): string
    {
        $map = [
            'fashion' => 'fashion',
            'cosmetics' => 'beauty',
            'electronics' => 'electronics',
            'furniture' => 'furniture',
            'pet' => 'pet',
            'kids' => 'kids',
            'digital' => 'digital',
        ];
        return $map[$businessType] ?? 'ecommerce';
    }

    private function mapStyleToVibe(string $style): string
    {
        $map = [
            'minimal' => 'luxury_minimal',
            'modern' => 'bold_startup',
            'luxury' => 'luxury_minimal',
            'playful' => 'soft_pastel',
            'bold' => 'bold_startup',
            'corporate' => 'corporate_clean',
        ];
        return $map[$style] ?? 'luxury_minimal';
    }
}
