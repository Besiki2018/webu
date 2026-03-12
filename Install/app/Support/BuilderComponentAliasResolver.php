<?php

namespace App\Support;

use Illuminate\Support\Str;

class BuilderComponentAliasResolver
{
    /**
     * Normalize a requested section/component type to a canonical builder registry id when possible.
     */
    public static function normalize(string $sectionType): string
    {
        $normalized = self::normalizeKey($sectionType);
        if ($normalized === '') {
            return '';
        }

        return self::aliasMap()[$normalized] ?? $normalized;
    }

    /**
     * @return array<int, string>
     */
    public static function registeredComponentIds(): array
    {
        $componentIds = config('builder-component-registry.component_ids', []);
        if (! is_array($componentIds)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($value): string => is_string($value) ? trim($value) : '',
            $componentIds
        )));
    }

    public static function isRegistered(string $sectionType): bool
    {
        $normalized = self::normalize($sectionType);
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, self::registeredComponentIds(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function aliasMap(): array
    {
        return [
            'header' => 'webu_header_01',
            'nav' => 'webu_general_navigation_01',
            'navbar' => 'webu_general_navigation_01',
            'navigation' => 'webu_general_navigation_01',
            'menu' => 'webu_general_navigation_01',
            'footer' => 'webu_footer_01',
            'hero' => 'webu_general_hero_01',
            'hero_split_image' => 'webu_general_hero_01',
            'hero_split' => 'webu_general_hero_01',
            'hero_banner' => 'webu_general_hero_01',
            'hero_section' => 'webu_general_hero_01',
            'heading' => 'webu_general_heading_01',
            'headline' => 'webu_general_heading_01',
            'text' => 'webu_general_text_01',
            'content' => 'webu_general_text_01',
            'rich_text_block' => 'webu_general_text_01',
            'image' => 'webu_general_image_01',
            'video' => 'webu_general_video_01',
            'button' => 'webu_general_button_01',
            'spacer' => 'webu_general_spacer_01',
            'section' => 'webu_general_section_01',
            'generic_section' => 'webu_general_section_01',
            'newsletter' => 'webu_general_newsletter_01',
            'cta' => 'webu_general_cta_01',
            'banner' => 'webu_general_banner_01',
            'promo_banner' => 'webu_general_banner_01',
            'features' => 'webu_general_features_01',
            'feature_grid' => 'webu_general_features_01',
            'featuregrid' => 'webu_general_features_01',
            'benefits' => 'webu_general_features_01',
            'services' => 'webu_general_features_01',
            'services_grid' => 'webu_general_features_01',
            'services_grid_cards' => 'webu_general_features_01',
            'service_cards' => 'webu_general_features_01',
            'pricing' => 'webu_general_features_01',
            'faq' => 'webu_general_features_01',
            'cards' => 'webu_general_cards_01',
            'team' => 'webu_general_cards_01',
            'team_cards' => 'webu_general_cards_01',
            'grid' => 'webu_general_grid_01',
            'gallery' => 'webu_general_grid_01',
            'logo_grid' => 'webu_general_grid_01',
            'masonry' => 'webu_general_grid_01',
            'contact' => 'webu_general_form_wrapper_01',
            'contact_form' => 'webu_general_form_wrapper_01',
            'lead_form' => 'webu_general_form_wrapper_01',
            'form' => 'webu_general_form_wrapper_01',
            'testimonials' => 'webu_general_testimonials_01',
            'reviews' => 'webu_general_testimonials_01',
            'social_proof' => 'webu_general_testimonials_01',
            'offcanvas_menu' => 'webu_general_offcanvas_menu_01',
            'product_grid' => 'webu_ecom_product_grid_01',
            'featured_categories' => 'webu_ecom_featured_categories_01',
            'category_list' => 'webu_ecom_category_list_01',
            'cart' => 'webu_ecom_cart_page_01',
            'product_detail' => 'webu_ecom_product_detail_01',
            'product_details' => 'webu_ecom_product_detail_01',
        ];
    }

    private static function normalizeKey(string $value): string
    {
        $normalized = Str::of($value)
            ->lower()
            ->trim()
            ->replaceMatches('/[\s\-]+/', '_')
            ->replaceMatches('/_+/', '_')
            ->value();

        return trim($normalized, '_');
    }
}
