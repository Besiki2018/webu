<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Parses free-text user prompt into structured AI layout input.
 * Keyword-based extraction; can be replaced or augmented by LLM later.
 */
class AiLayoutPromptParser
{
    /**
     * @return array{business_type: string, industry: string, design_style: string, color_scheme: string, sections_required: array<int, string>}
     */
    public function parse(string $prompt): array
    {
        $text = Str::lower($prompt);
        $sections = $this->extractSections($text);
        $designStyle = $this->extractDesignStyle($text);
        $colorScheme = $this->extractColorScheme($text);
        $industry = $this->extractIndustry($text);

        return [
            'business_type' => $this->inferBusinessType($text, $industry),
            'industry' => $industry,
            'design_style' => $designStyle,
            'color_scheme' => $colorScheme,
            'sections_required' => $sections,
        ];
    }

    private function extractSections(string $text): array
    {
        $sections = [];
        $hero = ['hero', 'banner', 'headline', 'main banner', 'welcome'];
        $products = ['product', 'products', 'featured products', 'product grid', 'catalog'];
        $categories = ['categor', 'category', 'shop by category'];
        $newsletter = ['newsletter', 'subscribe', 'signup', 'sign up', 'email signup'];
        $footer = ['footer', 'contact', 'links'];
        $banner = ['promo', 'promotional', 'cta', 'call to action', 'banner'];

        if ($this->matchesAny($text, $hero)) {
            $sections[] = 'hero';
        }
        if ($this->matchesAny($text, $categories)) {
            $sections[] = 'category-grid';
        }
        if ($this->matchesAny($text, $products)) {
            $sections[] = 'product-grid';
        }
        if ($this->matchesAny($text, $banner)) {
            $sections[] = 'banner';
        }
        if ($this->matchesAny($text, $newsletter)) {
            $sections[] = 'newsletter';
        }
        if ($this->matchesAny($text, $footer)) {
            $sections[] = 'footer';
        }

        if ($sections === []) {
            return ['hero', 'category-grid', 'product-grid', 'banner', 'newsletter', 'footer'];
        }

        return array_values(array_unique($sections));
    }

    private function extractDesignStyle(string $text): string
    {
        if ($this->matchesAny($text, ['minimal', 'simple', 'clean', 'minimalist'])) {
            return 'minimal';
        }
        if ($this->matchesAny($text, ['modern', 'contemporary', 'sleek'])) {
            return 'modern';
        }
        if ($this->matchesAny($text, ['premium', 'luxury', 'high-end', 'elegant'])) {
            return 'premium';
        }

        return 'default';
    }

    private function extractColorScheme(string $text): string
    {
        if ($this->matchesAny($text, ['pastel', 'soft pastel', 'pastel color'])) {
            return 'pastel';
        }
        if ($this->matchesAny($text, ['ocean', 'blue', 'sea'])) {
            return 'ocean';
        }
        if ($this->matchesAny($text, ['forest', 'green', 'nature'])) {
            return 'forest';
        }
        if ($this->matchesAny($text, ['luxury', 'dark', 'sophisticated'])) {
            return 'luxury';
        }

        return 'neutral';
    }

    private function extractIndustry(string $text): string
    {
        if ($this->matchesAny($text, ['cosmetic', 'beauty', 'skincare'])) {
            return 'cosmetics';
        }
        if ($this->matchesAny($text, ['cloth', 'fashion', 'apparel', 'wear'])) {
            return 'fashion';
        }
        if ($this->matchesAny($text, ['electronic', 'tech', 'gadget'])) {
            return 'electronics';
        }
        if ($this->matchesAny($text, ['food', 'restaurant', 'grocery'])) {
            return 'food';
        }
        if ($this->matchesAny($text, ['store', 'shop', 'ecommerce', 'e-commerce', 'online store'])) {
            return 'ecommerce';
        }

        return 'ecommerce';
    }

    private function inferBusinessType(string $text, string $industry): string
    {
        if ($this->matchesAny($text, ['store', 'shop', 'sell', 'product', 'ecommerce', 'e-commerce', 'cart', 'checkout'])) {
            return 'ecommerce';
        }

        return 'ecommerce';
    }

    private function matchesAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (Str::contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
