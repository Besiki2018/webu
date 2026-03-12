<?php

namespace App\Services;

use App\Models\SectionLibrary;
use Illuminate\Support\Str;

class CmsComponentLibraryCatalogService
{
    /**
     * Build the shared component-library catalog used by admin library and CMS builder.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildCatalog(): array
    {
        $catalog = [];
        $configComponents = $this->configComponentsByKey();
        $syntheticId = -1000;

        $sections = SectionLibrary::query()
            ->orderBy('category')
            ->orderBy('key')
            ->get();

        foreach ($sections as $section) {
            $key = Str::lower(trim((string) $section->key));
            if ($key === '') {
                continue;
            }

            $meta = collect($section->schema_json['_meta'] ?? []);
            $config = $configComponents[$key] ?? null;
            $folder = $this->resolveFolder($key, $config);
            $category = $this->normalizeCategory((string) ($config['category'] ?? $section->category ?? 'general'));
            $label = $this->displayLabel($key, (string) ($meta->get('label', $section->key)));
            $description = $this->displayDescription($key, (string) ($config['description'] ?? $meta->get('description', '')));
            $schemaJson = is_array($section->schema_json) ? $section->schema_json : [];

            $schemaJson['_meta'] = array_filter([
                ...((is_array($schemaJson['_meta'] ?? null) ? $schemaJson['_meta'] : [])),
                'label' => $label,
                'description' => $description !== '' ? $description : null,
                'webu_folder' => $folder,
            ], static fn ($value) => $value !== null && $value !== '');

            $catalogItem = [
                'id' => $section->id,
                'key' => $key,
                'category' => $category,
                'category_label' => $this->categoryLabel($category),
                'label' => $label,
                'description' => $description,
                'location_hint' => $this->locationHint($folder),
                'folder' => $folder,
                'schema_json' => $schemaJson,
                'enabled' => (bool) $section->enabled,
            ];

            if (! $this->isBuilderVisible($key, $config, $schemaJson)) {
                continue;
            }

            $catalog[$key] = $catalogItem;
        }

        foreach ($configComponents as $key => $config) {
            $existing = $catalog[$key] ?? null;
            $folder = $this->resolveFolder($key, $config);
            $category = $this->normalizeCategory((string) ($config['category'] ?? ($existing['category'] ?? 'general')));
            $label = $this->displayLabel($key, (string) ($config['label'] ?? ($existing['label'] ?? $key)));
            $description = $this->displayDescription($key, (string) ($config['description'] ?? ($existing['description'] ?? '')));
            $schemaJson = is_array($existing['schema_json'] ?? null) ? $existing['schema_json'] : ['type' => 'object', 'properties' => []];

            $schemaJson['_meta'] = array_filter([
                ...((is_array($schemaJson['_meta'] ?? null) ? $schemaJson['_meta'] : [])),
                'label' => $label,
                'description' => $description !== '' ? $description : null,
                'webu_folder' => $folder,
            ], static fn ($value) => $value !== null && $value !== '');

            $catalogItem = [
                'id' => $existing['id'] ?? $syntheticId--,
                'key' => $key,
                'category' => $category,
                'category_label' => $this->categoryLabel($category),
                'label' => $label,
                'description' => $description,
                'location_hint' => $this->locationHint($folder),
                'folder' => $folder,
                'schema_json' => $schemaJson,
                'enabled' => (bool) ($existing['enabled'] ?? true),
            ];

            if (! $this->isBuilderVisible($key, $config, $schemaJson)) {
                continue;
            }

            $catalog[$key] = $catalogItem;
        }

        foreach ($this->syntheticEntries() as $entry) {
            $key = $entry['key'];
            if (isset($catalog[$key])) {
                continue;
            }

            $folder = $this->resolveFolder($key, $entry);
            $label = $this->displayLabel($key, (string) ($entry['label'] ?? $key));
            $description = $this->displayDescription($key, (string) ($entry['description'] ?? ''));
            $category = $this->normalizeCategory((string) ($entry['category'] ?? 'general'));

            $schemaJson = [
                'type' => 'object',
                'properties' => [],
                '_meta' => array_filter([
                    'label' => $label,
                    'description' => $description !== '' ? $description : null,
                    'webu_folder' => $folder,
                ], static fn ($value) => $value !== null && $value !== ''),
            ];

            if (! $this->isBuilderVisible($key, $entry, $schemaJson)) {
                continue;
            }

            $catalog[$key] = [
                'id' => $syntheticId--,
                'key' => $key,
                'category' => $category,
                'category_label' => $this->categoryLabel($category),
                'label' => $label,
                'description' => $description,
                'location_hint' => $this->locationHint($folder),
                'folder' => $folder,
                'schema_json' => $schemaJson,
                'enabled' => true,
            ];
        }

        $categoryOrder = [
            'general' => 0,
            'ecommerce' => 1,
            'business' => 2,
            'content' => 3,
            'booking' => 4,
            'layout' => 5,
        ];

        $items = array_values($catalog);
        $items = array_values(array_filter($items, static function (array $item): bool {
            $key = (string) ($item['key'] ?? '');
            if ($key === 'header') {
                return false;
            }
            return true;
        }));
        usort($items, function (array $left, array $right) use ($categoryOrder): int {
            $leftCategory = (string) ($left['category'] ?? '');
            $rightCategory = (string) ($right['category'] ?? '');
            $leftOrder = $categoryOrder[$leftCategory] ?? 99;
            $rightOrder = $categoryOrder[$rightCategory] ?? 99;
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return mb_strtolower((string) ($left['label'] ?? '')) <=> mb_strtolower((string) ($right['label'] ?? ''));
        });

        return $items;
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @param  array<string, mixed>  $schemaJson
     */
    private function isBuilderVisible(string $key, ?array $config, array $schemaJson): bool
    {
        if ($key === 'header' || str_contains($key, 'placeholder')) {
            return false;
        }

        $meta = is_array($schemaJson['_meta'] ?? null) ? $schemaJson['_meta'] : [];
        if (($config['hidden_in_builder'] ?? false) === true || ($meta['hidden_in_builder'] ?? false) === true) {
            return false;
        }

        if (($config['temporary'] ?? false) === true || ($meta['temporary'] ?? false) === true) {
            return false;
        }

        if (array_key_exists('production_ready', $config ?? []) && $config['production_ready'] === false) {
            return false;
        }

        if (($meta['production_ready'] ?? true) === false) {
            return false;
        }

        return true;
    }

    public function categoryLabel(string $category): string
    {
        return $this->categoryLabels()[$this->normalizeCategory($category)] ?? Str::headline($category);
    }

    private function normalizeCategory(string $category): string
    {
        $normalized = Str::lower(trim($category));

        return $normalized !== '' ? $normalized : 'general';
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    private function resolveFolder(string $key, ?array $config): ?string
    {
        $folder = trim((string) ($config['folder'] ?? ''));
        if ($folder !== '') {
            return $folder;
        }

        return match (true) {
            str_contains($key, 'header') && ! str_contains($key, 'footer') => 'Header',
            str_contains($key, 'footer') => 'Footer',
            str_contains($key, 'hero') => 'Hero',
            str_contains($key, 'banner'), str_contains($key, 'cta') => 'Banner',
            str_contains($key, 'newsletter') => 'Newsletter',
            str_contains($key, 'product_card') => 'ProductCard',
            str_contains($key, 'product_grid') => 'ProductGrid',
            str_contains($key, 'category_list') => 'CategoryGrid',
            str_contains($key, 'category_card') => 'CategoryCard',
            str_contains($key, 'cart') => 'Cart',
            str_contains($key, 'checkout') => 'Checkout',
            str_contains($key, 'product_details') => 'ProductDetails',
            str_contains($key, 'placeholder') => 'Placeholder',
            default => null,
        };
    }

    private function displayLabel(string $key, string $fallback): string
    {
        $map = $this->componentLabels();
        if (isset($map[$key])) {
            return $map[$key];
        }

        $clean = trim($fallback);
        if ($clean !== '') {
            return $clean;
        }

        return Str::of($key)
            ->replace(['webu_', 'ecommerce_', 'booking_'], '')
            ->replace('_', ' ')
            ->headline()
            ->value();
    }

    private function displayDescription(string $key, string $fallback): string
    {
        $descriptions = $this->componentDescriptions();
        if (isset($descriptions[$key])) {
            return $descriptions[$key];
        }

        return trim($fallback);
    }

    private function locationHint(?string $folder): string
    {
        if ($folder !== null && $folder !== '') {
            return 'webu/' . $folder;
        }

        return 'CMS სექცია';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function configComponentsByKey(): array
    {
        $entries = [];
        foreach ((array) config('webu-builder-components.components', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = Str::lower(trim((string) ($entry['key'] ?? '')));
            if ($key === '') {
                continue;
            }

            $entries[$key] = $entry;
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function syntheticEntries(): array
    {
        return [
            ['key' => 'webu_header_01', 'label' => 'ჰედერი', 'category' => 'general', 'folder' => 'Header'],
            ['key' => 'webu_footer_01', 'label' => 'ფუტერი', 'category' => 'general', 'folder' => 'Footer'],
            ['key' => 'hero_split_image', 'label' => 'ჰირო სლაიდი', 'category' => 'general', 'folder' => 'Hero'],
            ['key' => 'webu_general_offcanvas_menu_01', 'label' => 'გვერდითი მენიუ', 'category' => 'general', 'folder' => 'Header'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function categoryLabels(): array
    {
        return [
            'general' => 'ზოგადი',
            'ecommerce' => 'მაღაზია',
            'business' => 'ბიზნესი',
            'content' => 'კონტენტი',
            'booking' => 'ჯავშნები',
            'layout' => 'ლეიაუტი',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function componentLabels(): array
    {
        return [
            'hero' => 'ჰირო',
            'banner' => 'ბანერი',
            'footer' => 'ფუტერი',
            'webu_header_01' => 'ჰედერი',
            'webu_footer_01' => 'ფუტერი',
            'hero_split_image' => 'ჰირო სლაიდი',
            'webu_general_offcanvas_menu_01' => 'გვერდითი მენიუ',
            'webu_ecom_product_grid_01' => 'პროდუქტების ბადე',
            'webu_ecom_product_card_01' => 'პროდუქტის ბარათი',
            'webu_ecom_category_list_01' => 'კატეგორიების ბადე',
            'webu_ecom_category_card_01' => 'კატეგორიის ბარათი',
            'webu_ecom_cart_page_01' => 'კალათა',
            'webu_ecom_checkout_01' => 'ჩექაუთი',
            'webu_ecom_product_details_01' => 'პროდუქტის დეტალი',
            'webu_general_newsletter_01' => 'ნიუსლეტერი',
            'webu_general_placeholder_01' => 'ცარიელი ბლოკი',
            'webu_general_heading_01' => 'სათაური',
            'booking_calendar_embed' => 'ჯავშნის კალენდარი',
            'booking_service_pricing' => 'სერვისის ფასები',
            'booking_staff_cards' => 'სტაფის ბარათები',
            'booking_steps_timeline' => 'ჯავშნის ნაბიჯები',
            'booking_widget_inline' => 'ჯავშნის ვიჯეტი',
            'before_after_showcase' => 'მანამდე / შემდეგ',
            'contact_split_form' => 'კონტაქტის ფორმა',
            'map_contact_block' => 'რუკა და კონტაქტი',
            'portfolio_masonry_grid' => 'პორტფოლიოს ბადე',
            'process_timeline_steps' => 'პროცესის ნაბიჯები',
            'services_grid_cards' => 'სერვისების ბარათები',
            'services_split_feature' => 'სერვისის highlight',
            'team_profile_cards' => 'გუნდის ბარათები',
            'blog_cards_preview' => 'ბლოგის ბარათები',
            'faq_accordion_plus' => 'ხშირი კითხვები',
            'feature_comparison_table' => 'შედარების ცხრილი',
            'gallery_mosaic' => 'გალერეა',
            'rich_text_block' => 'ტექსტის ბლოკი',
            'ecommerce_cart_summary_strip' => 'კალათის შეჯამება',
            'ecommerce_category_tiles' => 'კატეგორიის ფილები',
            'ecommerce_checkout_faq' => 'ჩექაუთის კითხვები',
            'ecommerce_collection_banner' => 'კოლექციის ბანერი',
            'ecommerce_featured_product' => 'რჩეული პროდუქტი',
            'ecommerce_product_grid' => 'პროდუქტების ბადე',
            'cta_banner_bold' => 'CTA ბანერი',
            'hero_centered_gradient' => 'ცენტრირებული ჰირო',
            'logo_cloud_wall' => 'ბრენდების ლოგოები',
            'pricing_tiers_pro' => 'ფასების გეგმები',
            'stats_cards' => 'სტატისტიკის ბარათები',
            'testimonial_masonry' => 'შეფასებების ბადე',
            'testimonial_slider' => 'შეფასებების სლაიდერი',
            'trust_badges_inline' => 'ნდობის ბეიჯები',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function componentDescriptions(): array
    {
        return [
            'hero' => 'მთავარი ბანერი',
            'banner' => 'CTA ან სარეკლამო ბლოკი',
            'footer' => 'საიტის ქვედა ნაწილი',
            'hero_split_image' => 'სლაიდიანი ჰირო ბლოკი',
            'webu_general_offcanvas_menu_01' => 'გახსნადი მენიუ',
        ];
    }
}
