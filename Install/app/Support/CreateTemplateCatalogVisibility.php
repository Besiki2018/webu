<?php

namespace App\Support;

use App\Models\Template;

class CreateTemplateCatalogVisibility
{
    private const EXCLUDED_SLUGS = [
        'ecommerce',
        'ecommerce-storefront',
        'service-booking',
        'portfolio-agency',
    ];

    private const EXCLUDED_CATEGORIES = [
        'ecommerce',
        'booking',
        'portfolio',
    ];

    public static function allowsTemplate(Template $template): bool
    {
        return self::allows(
            (string) $template->slug,
            (string) $template->name,
            $template->category ? (string) $template->category : null
        );
    }

    /**
     * @param  array{slug?: string, name?: string, category?: string|null}  $template
     */
    public static function allowsReadyTemplate(array $template): bool
    {
        return self::allows(
            (string) ($template['slug'] ?? ''),
            (string) ($template['name'] ?? ''),
            isset($template['category']) ? (string) $template['category'] : null
        );
    }

    public static function allowsSlug(string $slug): bool
    {
        return ! in_array(self::normalize($slug), self::EXCLUDED_SLUGS, true);
    }

    private static function allows(string $slug, string $name, ?string $category): bool
    {
        $normalizedSlug = self::normalize($slug);
        if (in_array($normalizedSlug, self::EXCLUDED_SLUGS, true)) {
            return false;
        }

        $normalizedCategory = self::normalize((string) $category);
        if ($normalizedCategory !== '' && in_array($normalizedCategory, self::EXCLUDED_CATEGORIES, true)) {
            return false;
        }

        $normalizedName = self::normalize($name);

        return ! in_array($normalizedName, ['e-commerce store', 'portfolio / agency'], true);
    }

    private static function normalize(string $value): string
    {
        return trim(mb_strtolower($value, 'UTF-8'));
    }
}
