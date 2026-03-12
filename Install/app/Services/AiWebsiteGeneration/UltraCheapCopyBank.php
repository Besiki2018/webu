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
        $out = $defaultContent;
        $brand = $brandName ?: 'My Brand';
        foreach ($out as $key => $val) {
            if (! is_string($val) || $val === '') {
                $replacement = $this->pickForSectionKey($key, $sectionType, $copy);
                if ($replacement !== null) {
                    $out[$key] = str_replace(['{Brand}', '{brand}'], $brand, $replacement);
                }
            }
        }
        if (isset($out['title']) && ($out['title'] === '' || $out['title'] === null)) {
            $out['title'] = str_replace('{Brand}', $brand, $this->pickOne($copy['hero_headlines'] ?? ['Welcome']));
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

    private function pickForSectionKey(string $key, string $sectionType, array $copy): ?string
    {
        if ($key === 'title' || $key === 'heading') {
            return $this->pickOne($copy['hero_headlines'] ?? $copy['about_lines'] ?? null);
        }
        if ($key === 'subtitle' || $key === 'body' || $key === 'description') {
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
