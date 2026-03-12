<?php

namespace App\Services;

use App\Models\SectionLibrary;

class BuilderSectionCatalogService
{
    /**
     * @return array<int, array{category:string, items:array<int, array{id:int,key:string,category:string,label:string,enabled:bool}>}>
     */
    public function grouped(): array
    {
        $sections = SectionLibrary::query()
            ->orderBy('category')
            ->orderBy('key')
            ->get()
            ->map(function (SectionLibrary $section): array {
                $meta = collect($section->schema_json['_meta'] ?? []);
                $label = $meta->get('label', $section->key);

                return [
                    'id' => $section->id,
                    'key' => $section->key,
                    'category' => $section->category,
                    'label' => is_string($label) ? $label : $section->key,
                    'enabled' => (bool) $section->enabled,
                ];
            })
            ->values()
            ->all();

        $existingKeys = array_fill_keys(array_column($sections, 'key'), true);
        $canonicalEntries = [
            ['key' => 'webu_header_01', 'label' => __('Header 01'), 'category' => 'layout'],
            ['key' => 'webu_footer_01', 'label' => __('Footer 01'), 'category' => 'layout'],
            ['key' => 'hero_split_image', 'label' => __('Hero Split Image'), 'category' => 'general'],
            ['key' => 'webu_general_offcanvas_menu_01', 'label' => __('Offcanvas Menu'), 'category' => 'general'],
        ];

        foreach ($canonicalEntries as $entry) {
            if (isset($existingKeys[$entry['key']])) {
                continue;
            }

            $sections[] = [
                'id' => -1 * (1000 + count($sections)),
                'key' => $entry['key'],
                'category' => $entry['category'],
                'label' => $entry['label'],
                'enabled' => true,
            ];
        }

        return collect($sections)
            ->groupBy('category')
            ->map(fn ($items, $category) => [
                'category' => (string) $category,
                'items' => $items
                    ->filter(fn ($item) => $this->isProductionReadySection($item))
                    ->filter(fn ($item) => (bool) ($item['enabled'] ?? false))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isProductionReadySection(array $item): bool
    {
        $key = strtolower(trim((string) ($item['key'] ?? '')));
        if ($key === '' || str_contains($key, 'placeholder')) {
            return false;
        }

        $meta = collect(data_get($item, 'schema_json._meta', []));
        if ((bool) $meta->get('hidden_in_builder', false) || (bool) $meta->get('temporary', false)) {
            return false;
        }

        if ($meta->has('production_ready') && $meta->get('production_ready') === false) {
            return false;
        }

        return true;
    }
}
