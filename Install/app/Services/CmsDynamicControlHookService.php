<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsDynamicControlHookService
{
    /**
     * @param  array<int, string>  $editableFields
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function buildHooks(array $editableFields, array $schema = []): array
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $hooks = [];

        foreach ($editableFields as $field) {
            $fieldKey = trim((string) $field);
            if ($fieldKey === '' || $this->isNonContentField($fieldKey)) {
                continue;
            }

            $schemaProperty = is_array($properties[$fieldKey] ?? null) ? $properties[$fieldKey] : [];
            $kind = $this->detectKind($fieldKey, $schemaProperty);
            if ($kind === null) {
                continue;
            }

            $hooks[$fieldKey] = [
                'supports_dynamic' => true,
                'kind' => $kind,
                'accepted_value_types' => $this->acceptedValueTypes($kind),
                'binding_namespaces' => $this->recommendedNamespaces($kind),
                'examples' => $this->bindingExamples($kind),
            ];

            if (Str::lower($fieldKey) === 'product_slug') {
                $hooks[$fieldKey]['binding_namespaces'] = ['route', 'ecommerce', 'page'];
                $hooks[$fieldKey]['examples'] = ['{{route.params.slug}}', '{{route.slug}}'];
            }
        }

        return [
            'version' => 1,
            'fields' => $hooks,
        ];
    }

    private function isNonContentField(string $field): bool
    {
        $lower = Str::lower(trim($field));

        if ($lower === '') {
            return true;
        }

        if (Str::endsWith($lower, '_typography')) {
            return true;
        }

        return preg_match('/^(icon_class|class_name|css_class|id|sort_order|position|variant_id)$/', $lower) === 1;
    }

    /**
     * @param  array<string, mixed>  $schemaProperty
     */
    private function detectKind(string $field, array $schemaProperty): ?string
    {
        $lower = Str::lower(trim($field));
        $schemaType = Str::lower(trim((string) ($schemaProperty['type'] ?? '')));
        $format = Str::lower(trim((string) ($schemaProperty['format'] ?? '')));

        if ($schemaType === 'object') {
            if (preg_match('/(cta|button|link|url)$/', $lower) === 1) {
                return 'link';
            }

            return null;
        }

        if ($format === 'uri' || preg_match('/(image|logo|thumb|thumbnail|photo|banner|avatar).*(url|src)?$/', $lower) === 1) {
            return 'image';
        }

        if (preg_match('/(link|href|url)$/', $lower) === 1) {
            return 'link';
        }

        if (preg_match('/(headline|title|subtitle|description|body|text|label|caption|placeholder|content)/', $lower) === 1) {
            return 'text';
        }

        if ($schemaType === 'string') {
            return 'text';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function acceptedValueTypes(string $kind): array
    {
        return match ($kind) {
            'image' => ['string', 'object'],
            'link' => ['string', 'object'],
            default => ['string'],
        };
    }

    /**
     * @return array<int, string>
     */
    private function recommendedNamespaces(string $kind): array
    {
        return match ($kind) {
            'image' => ['global', 'site', 'ecommerce', 'content'],
            'link' => ['route', 'page', 'site', 'global', 'ecommerce', 'booking', 'customer'],
            default => ['site', 'page', 'route', 'global', 'menu', 'ecommerce', 'booking', 'content', 'customer'],
        };
    }

    /**
     * @return array<int, string>
     */
    private function bindingExamples(string $kind): array
    {
        return match ($kind) {
            'image' => ['{{global.logo.url}}', '{{content.featured_image}}'],
            'link' => ['{{route.slug}}', '{{page.slug}}', '{{ecommerce.endpoints.products}}'],
            default => ['{{site.name}}', '{{page.title}}', '{{global.contact.phone}}'],
        };
    }
}
