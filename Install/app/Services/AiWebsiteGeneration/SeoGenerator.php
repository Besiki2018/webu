<?php

namespace App\Services\AiWebsiteGeneration;

/**
 * Generates per-page SEO: seo_title, meta_description, optional og_title, og_image.
 * Stored in website_seo table; CMS allows editing per page.
 */
class SeoGenerator
{
    /**
     * @param  array{brandName: string}  $brief
     * @param  array<int, array{slug: string, title: string}>  $pages
     * @param  string  $locale
     * @return array<int, array{seo_title: string, meta_description: string, og_title: string|null, og_image: string|null}>
     */
    public function generate(array $brief, array $pages, string $locale = 'ka'): array
    {
        $brand = $brief['brandName'] ?? 'Website';
        $out = [];
        foreach ($pages as $index => $page) {
            $title = $page['title'] ?? ucfirst($page['slug'] ?? 'Page');
            $slug = $page['slug'] ?? 'page';
            $out[$index] = [
                'seo_title' => $slug === 'home' ? $brand : "{$brand} | {$title}",
                'meta_description' => $this->metaDescription($brand, $title, $slug),
                'og_title' => $slug === 'home' ? $brand : "{$brand} | {$title}",
                'og_image' => null,
            ];
        }
        return $out;
    }

    private function metaDescription(string $brand, string $title, string $slug): string
    {
        $base = "{$brand} - {$title}. ";
        if ($slug === 'home') {
            return $base . 'Discover more.';
        }
        if ($slug === 'contact') {
            return $base . 'Get in touch.';
        }
        return $base . 'Find out more.';
    }
}
