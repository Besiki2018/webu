<?php

namespace App\Services;

use App\Support\OwnedTemplateCatalog;

/**
 * Template Metadata + Governance (PART 7).
 *
 * Validates that template_metadata has required keys per template and that
 * ecommerce catalog slugs used for Director selection have metadata.
 * Director uses this metadata for selection (TemplateSelectorService, TemplateVariantPlanner).
 *
 * @see new tasks.txt — AI Design Director PART 7 (Template Metadata + Governance)
 */
class TemplateMetadataGovernance
{
    /** Required keys per template entry. */
    public const REQUIRED_KEYS = [
        'template_id',
        'verticals',
        'vibes',
        'density',
        'quality_score',
        'hero_variants_supported',
        'product_card_variants_supported',
    ];

    /**
     * Validate all template metadata entries. Returns errors for missing or invalid keys.
     *
     * @return array{valid: bool, errors: array<int, array{template_id: string, message: string}>}
     */
    public function validate(): array
    {
        $metadata = config('template_metadata', []);
        $errors = [];

        foreach ($metadata as $slug => $entry) {
            if (! is_array($entry)) {
                $errors[] = ['template_id' => (string) $slug, 'message' => 'Entry must be an array.'];
                continue;
            }
            foreach (self::REQUIRED_KEYS as $key) {
                if (! array_key_exists($key, $entry)) {
                    $errors[] = ['template_id' => (string) $slug, 'message' => "Missing required key: {$key}."];
                }
            }
            if (isset($entry['verticals']) && ! is_array($entry['verticals'])) {
                $errors[] = ['template_id' => (string) $slug, 'message' => 'verticals must be an array.'];
            }
            if (isset($entry['vibes']) && ! is_array($entry['vibes'])) {
                $errors[] = ['template_id' => (string) $slug, 'message' => 'vibes must be an array.'];
            }
            if (isset($entry['hero_variants_supported']) && ! is_array($entry['hero_variants_supported']) {
                $errors[] = ['template_id' => (string) $slug, 'message' => 'hero_variants_supported must be an array.'];
            }
            if (isset($entry['product_card_variants_supported']) && ! is_array($entry['product_card_variants_supported']) {
                $errors[] = ['template_id' => (string) $slug, 'message' => 'product_card_variants_supported must be an array.'];
            }
            if (array_key_exists('quality_score', $entry)) {
                $score = $entry['quality_score'];
                if (! is_int($score) && ! (is_numeric($score) && (int) $score == $score)) {
                    $errors[] = ['template_id' => (string) $slug, 'message' => 'quality_score must be an integer.'];
                } elseif ((int) $score < 0 || (int) $score > 100) {
                    $errors[] = ['template_id' => (string) $slug, 'message' => 'quality_score must be 0–100.'];
                }
            }
            if (isset($entry['template_id']) && (string) $entry['template_id'] !== (string) $slug) {
                $errors[] = ['template_id' => (string) $slug, 'message' => 'template_id must match array key.'];
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Ensure every ecommerce catalog slug used for selection has a metadata entry.
     * Returns list of slugs that are in catalog but missing from template_metadata.
     *
     * @return array<int, string>
     */
    public function catalogSlugsMissingMetadata(): array
    {
        $slugs = OwnedTemplateCatalog::slugs();
        $metadata = config('template_metadata', []);
        $ecommerceSlugs = array_values(array_filter($slugs, static fn (string $s): bool => str_starts_with($s, 'ecommerce') || $s === 'default'));
        $missing = [];
        foreach ($ecommerceSlugs as $slug) {
            if (! isset($metadata[$slug]) || ! is_array($metadata[$slug])) {
                $missing[] = $slug;
            }
        }
        return $missing;
    }

    /**
     * Get required metadata keys (for schema/docs).
     *
     * @return array<int, string>
     */
    public static function getRequiredKeys(): array
    {
        return self::REQUIRED_KEYS;
    }
}
