<?php

namespace App\Services\AiWebsiteGeneration;

use Illuminate\Support\Arr;

/**
 * Deterministic content from copy bank (no AI).
 * Picks randomly from category/lang JSON; substitutes {Brand}.
 */
class UltraCheapCopyBank
{
    public function __construct(
        protected string $basePath = ''
    ) {
        $this->basePath = $basePath ?: base_path(config('ultra_cheap.copy_bank_path', 'data/copy-bank'));
    }

    public function getForCategoryAndLang(string $category, string $lang): array
    {
        $path = $this->basePath.DIRECTORY_SEPARATOR.$category.DIRECTORY_SEPARATOR.$lang.'.json';
        if (! is_file($path)) {
            $path = $this->basePath.DIRECTORY_SEPARATOR.'general'.DIRECTORY_SEPARATOR.$lang.'.json';
        }
        if (! is_file($path)) {
            $path = $this->basePath.DIRECTORY_SEPARATOR.'general'.DIRECTORY_SEPARATOR.'en.json';
        }
        if (! is_file($path)) {
            return $this->defaultCopy();
        }
        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : $this->defaultCopy();
    }

    /**
     * Fill section content from copy bank (random pick per key).
     *
     * @param  array{hero_headlines?: array, about_lines?: array, cta_labels?: array, service_descriptions?: array}  $copy
     */
    public function fillSectionContent(string $sectionType, array $defaultContent, array $copy, string $brandName = ''): array
    {
        $brand = $brandName ?: 'My Brand';
        $out = $this->fillValue($defaultContent, $sectionType, $copy, $brand);

        if (isset($out['title']) && ($out['title'] === '' || $out['title'] === null)) {
            $out['title'] = str_replace('{Brand}', $brand, $this->pickOne($copy['hero_headlines'] ?? ['Welcome']));
        }
        if (isset($out['headline']) && ($out['headline'] === '' || $out['headline'] === null)) {
            $out['headline'] = str_replace('{Brand}', $brand, $this->pickOne($copy['hero_headlines'] ?? ['Welcome']));
        }
        if (isset($out['subtitle']) && ($out['subtitle'] === '' || $out['subtitle'] === null)) {
            $out['subtitle'] = $this->pickOne($copy['about_lines'] ?? ['']);
        }
        if (isset($out['button_text']) && ($out['button_text'] === '' || $out['button_text'] === null)) {
            $out['button_text'] = $this->pickOne($copy['cta_labels'] ?? ['Contact us']);
        }
        if (isset($out['cta_text']) && ($out['cta_text'] === '' || $out['cta_text'] === null)) {
            $out['cta_text'] = $this->pickOne($copy['cta_labels'] ?? ['Contact us']);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|string|int|float|bool|null
     */
    private function fillValue(mixed $value, string $sectionType, array $copy, string $brand, string $key = '')
    {
        if (is_array($value)) {
            $filled = [];

            foreach ($value as $nestedKey => $nestedValue) {
                $filled[$nestedKey] = $this->fillValue(
                    $nestedValue,
                    $sectionType,
                    $copy,
                    $brand,
                    is_string($nestedKey) ? $nestedKey : $key
                );
            }

            return $filled;
        }

        if (! is_string($value) || $value !== '') {
            return $value;
        }

        $replacement = $this->pickForSectionKey($key, $sectionType, $copy);
        if ($replacement === null) {
            return $value;
        }

        return str_replace(['{Brand}', '{brand}'], $brand, $replacement);
    }

    private function pickForSectionKey(string $key, string $sectionType, array $copy): ?string
    {
        if ($key === 'link' || $key === 'buttonLink' || $key === 'button_url' || $key === 'buttonUrl' || $key === 'cta_url' || $key === 'ctaUrl') {
            return str_contains($sectionType, 'ecom') ? '/shop' : '/contact';
        }
        if ($key === 'author' || $key === 'name' || $key === 'user_name') {
            return $this->pickOne(['Satisfied client', 'Returning guest', 'Happy customer']);
        }
        if ($key === 'role') {
            return $this->pickOne(['Client', 'Customer', 'Visitor']);
        }
        if ($key === 'title' || $key === 'headline' || $key === 'heading') {
            return $this->pickOne($copy['hero_headlines'] ?? $copy['about_lines'] ?? null);
        }
        if ($key === 'subtitle' || $key === 'body' || $key === 'description' || $key === 'quote' || $key === 'text') {
            return $this->pickOne($copy['about_lines'] ?? $copy['service_descriptions'] ?? null);
        }
        if (str_contains($key, 'button') || str_contains($key, 'cta')) {
            return $this->pickOne($copy['cta_labels'] ?? null);
        }

        return null;
    }

    private function pickOne(?array $arr): string
    {
        if (! is_array($arr) || $arr === []) {
            return '';
        }

        return (string) $arr[array_rand($arr)];
    }

    private function defaultCopy(): array
    {
        return [
            'hero_headlines' => ['Welcome'],
            'about_lines' => ['Learn more about us.'],
            'cta_labels' => ['Contact us'],
            'service_descriptions' => ['Our services.'],
        ];
    }
}
