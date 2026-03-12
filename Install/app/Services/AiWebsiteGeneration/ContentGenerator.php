<?php

namespace App\Services\AiWebsiteGeneration;

/**
 * Generates SHORT copy for sections. No lorem ipsum; all editable.
 * Patches content_json only; keeps style_json intact.
 */
class ContentGenerator
{
    /**
     * @param  array{brandName: string, websiteType: string, cta: string|null}  $brief
     * @param  array<int, array{slug: string, title: string, sections: array}>  $pages
     * @return array<int, array<int, array<string, mixed>>>  page index => section index => content patch
     */
    public function generate(array $brief, array $pages): array
    {
        $brand = $brief['brandName'] ?? 'My Website';
        $cta = $brief['cta'] ?? 'Contact us';
        $out = [];
        foreach ($pages as $pageIndex => $page) {
            $slug = $page['slug'] ?? 'home';
            $out[$pageIndex] = [];
            foreach ($page['sections'] ?? [] as $secIndex => $section) {
                $type = $section['section_type'] ?? 'content';
                $out[$pageIndex][$secIndex] = $this->contentForSectionType($type, $brand, $cta, $slug);
            }
        }
        return $out;
    }

    /** @return array<string, mixed> */
    private function contentForSectionType(string $type, string $brand, string $cta, string $pageSlug): array
    {
        return match ($type) {
            'hero' => [
                'title' => $brand,
                'subtitle' => $this->shortSubtitle($pageSlug),
                'cta_text' => $cta,
                'cta_link' => $pageSlug === 'home' ? '/contact' : '/contact',
                'image' => '',
            ],
            'cta' => [
                'title' => 'Ready to get started?',
                'button_text' => $cta,
                'button_link' => '/contact',
            ],
            'heading' => [
                'title' => ucfirst(str_replace('-', ' ', $pageSlug)),
                'subtitle' => '',
            ],
            'content' => ['body' => 'Add your content here.'],
            'contact' => [
                'heading' => 'Get in touch',
                'email' => '',
                'phone' => '',
            ],
            'features' => [
                'heading' => 'What we offer',
                'items' => [],
            ],
            'gallery' => [
                'heading' => 'Our work',
                'images' => [],
            ],
            default => [],
        };
    }

    private function shortSubtitle(string $pageSlug): string
    {
        return match ($pageSlug) {
            'home' => 'Welcome. Edit this text in the CMS.',
            'about' => 'Learn more about us.',
            'contact' => 'We’d love to hear from you.',
            'shop' => 'Browse our products.',
            default => 'Edit this in the CMS.',
        };
    }
}
