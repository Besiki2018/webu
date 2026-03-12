<?php

namespace App\Services;

use App\Models\SectionLibrary;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CmsSectionBindingService
{
    public function __construct(
        protected CmsCanonicalBindingResolver $canonicalBindings,
        protected CmsDynamicControlHookService $dynamicControlHooks,
        protected ?ComponentVariantRegistry $variantRegistry = null
    ) {}

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $sectionCache = null;

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function buildSectionPayload(string $sectionKey, array $props = []): array
    {
        $normalizedType = $this->normalizeSectionKey($sectionKey);
        $binding = $this->resolveBinding($normalizedType);
        $variant = $this->variantRegistry
            ? $this->variantRegistry->resolveFromProps($normalizedType, $props)
            : ['layout' => '', 'style' => ''];

        return [
            'key' => $normalizedType,
            'type' => $normalizedType,
            'props' => $props,
            'binding' => $binding,
            'variant' => $variant,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveBinding(string $sectionKey): array
    {
        $normalized = $this->normalizeSectionKey($sectionKey);
        $catalog = $this->sectionCatalog();

        $resolved = $catalog[$normalized] ?? $this->resolveAliasBinding($normalized, $catalog);
        if ($resolved === null) {
            $defaultBinding = $this->defaultEcommerceBindingForSectionKey($normalized);
            if ($defaultBinding !== null) {
                return $defaultBinding;
            }
            return [
                'source' => 'template_metadata',
                'section_key' => $normalized,
                'editable_fields' => [],
                'bindings' => [],
                'bindings_normalized' => [],
            ];
        }

        $schema = is_array($resolved['schema'] ?? null) ? $resolved['schema'] : [];
        $properties = Arr::get($schema, 'properties', []);
        $editableFields = is_array($properties) ? array_values(array_keys($properties)) : [];
        $editableFields = $this->filterEditableFieldsForDataDrivenSections(
            $normalized,
            $editableFields,
            $schema
        );

        $bindings = is_array(Arr::get($schema, 'bindings', [])) ? Arr::get($schema, 'bindings', []) : [];

        return [
            'source' => 'sections_library',
            'section_key' => $resolved['key'],
            'category' => $resolved['category'],
            'editable_fields' => $editableFields,
            'bindings' => $bindings,
            'bindings_normalized' => $this->normalizeBindingDefinitions($bindings),
            'dynamic_controls' => $this->dynamicControlHooks->buildHooks($editableFields, $schema),
        ];
    }

    private function normalizeSectionKey(string $sectionKey): string
    {
        $normalized = trim(Str::lower($sectionKey));

        return $normalized !== '' ? $normalized : 'section';
    }

    /**
     * PART 5 CMS Binding: when section is not in library, apply default ecommerce binding from config
     * (product_grid → products, product_details → product_by_slug, category_menu → categories, cart, checkout).
     *
     * @return array<string, mixed>|null
     */
    private function defaultEcommerceBindingForSectionKey(string $normalized): ?array
    {
        $patterns = config('cms-section-bindings.pattern_to_binding_key', []);
        $defaults = config('cms-section-bindings.default_bindings', []);
        if ($patterns === [] || $defaults === []) {
            return null;
        }
        foreach ($patterns as $pattern => $bindingKey) {
            if (str_contains($normalized, Str::lower($pattern))) {
                $def = $defaults[$bindingKey] ?? null;
                if (! is_array($def) || ! isset($def['bindings'])) {
                    return null;
                }
                $bindings = is_array($def['bindings']) ? $def['bindings'] : [];
                return [
                    'source' => 'cms_binding_contract',
                    'section_key' => $normalized,
                    'editable_fields' => [],
                    'bindings' => $bindings,
                    'bindings_normalized' => $this->normalizeBindingDefinitions($bindings),
                ];
            }
        }
        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sectionCatalog(): array
    {
        if ($this->sectionCache !== null) {
            return $this->sectionCache;
        }

        $this->sectionCache = SectionLibrary::query()
            ->get(['key', 'category', 'schema_json'])
            ->mapWithKeys(static fn (SectionLibrary $section): array => [
                Str::lower(trim((string) $section->key)) => [
                    'key' => (string) $section->key,
                    'category' => (string) $section->category,
                    'schema' => is_array($section->schema_json) ? $section->schema_json : [],
                ],
            ])
            ->all();

        return $this->sectionCache;
    }

    /**
     * @param  array<string, array<string, mixed>>  $catalog
     * @return array<string, mixed>|null
     */
    private function resolveAliasBinding(string $normalized, array $catalog): ?array
    {
        $compact = str_replace(['-', '_', ' '], '', $normalized);

        foreach ($catalog as $candidate => $payload) {
            $candidateCompact = str_replace(['-', '_', ' '], '', $candidate);
            if ($candidateCompact === $compact) {
                return $payload;
            }
        }

        foreach ($catalog as $candidate => $payload) {
            if (str_contains($candidate, $normalized) || str_contains($normalized, $candidate)) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $editableFields
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function filterEditableFieldsForDataDrivenSections(string $normalizedKey, array $editableFields, array $schema): array
    {
        if ($editableFields === []) {
            return $editableFields;
        }

        $htmlTemplate = is_string($schema['html_template'] ?? null) ? (string) $schema['html_template'] : '';
        $usesProductsBinding = $normalizedKey === 'webu_product_grid_01'
            || str_contains($htmlTemplate, 'data-webby-ecommerce-products');
        $usesProductDetailBinding = $normalizedKey === 'webu_product_card_01';

        if (! $usesProductsBinding && ! $usesProductDetailBinding) {
            return $editableFields;
        }

        $filtered = array_values(array_filter($editableFields, function ($field) use ($usesProductsBinding, $usesProductDetailBinding): bool {
            $rootKey = Str::lower(trim($field));
            if ($rootKey === '') {
                return false;
            }

            if (preg_match('/^(link|heading|paragraph|image|button)_\d+$/', $rootKey) === 1) {
                return false;
            }

            if ($usesProductsBinding) {
                if (in_array($rootKey, ['name', 'price', 'compare_at_price', 'sku', 'short_description', 'description'], true)) {
                    return false;
                }

                return true;
            }

            if ($usesProductDetailBinding) {
                return in_array($rootKey, ['product_id', 'product_slug', 'variant_id', 'collection', 'title', 'headline', 'subtitle'], true);
            }

            return true;
        }));

        return $filtered;
    }

    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array<int|string, mixed>
     */
    private function normalizeBindingDefinitions(array $bindings): array
    {
        $normalized = [];

        foreach ($bindings as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $this->normalizeBindingDefinitions($value);

                if (is_string($value['expression'] ?? null)) {
                    $normalizedExpression = $this->canonicalBindings->normalizeExpression((string) $value['expression']);
                    if ($normalizedExpression !== null) {
                        $normalized[$key]['expression'] = $normalizedExpression;
                    }
                }

                if (is_string($value['path'] ?? null)) {
                    $normalizedPath = $this->canonicalBindings->normalizeExpression((string) $value['path']);
                    if ($normalizedPath !== null) {
                        $normalized[$key]['path'] = trim($normalizedPath, '{} ');
                    }
                }

                continue;
            }

            if (is_string($value)) {
                $normalizedExpression = $this->canonicalBindings->normalizeExpression($value);
                $normalized[$key] = $normalizedExpression ?? $value;
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
