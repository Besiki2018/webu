<?php

namespace App\Services\ProjectWorkspace;

use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WorkspaceComponentMetadataService
{
    public function __construct(
        protected ProjectWorkspaceService $workspace
    ) {}

    /**
     * @return array{
     *     sections: array<string, array<string, mixed>>,
     *     components: array<string, array<string, mixed>>,
     *     layouts: array<string, array<string, mixed>>
     * }
     */
    public function scan(Project $project): array
    {
        $root = $this->workspace->ensureWorkspaceRoot($project);
        $projection = $this->workspace->readWorkspaceProjection($project);

        return [
            'sections' => $this->scanDirectory($root, 'src/sections', is_array($projection['components'] ?? null) ? $projection['components'] : []),
            'components' => $this->scanDirectory($root, 'src/components', array_values(array_filter(
                is_array($projection['layouts'] ?? null) ? $projection['layouts'] : [],
                static fn ($entry): bool => is_array($entry) && (($entry['projection_role'] ?? null) === 'layout-component')
            ))),
            'layouts' => $this->scanDirectory($root, 'src/layouts', array_values(array_filter(
                is_array($projection['layouts'] ?? null) ? $projection['layouts'] : [],
                static fn ($entry): bool => is_array($entry) && (($entry['projection_role'] ?? null) === 'layout')
            ))),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sectionMetadata(Project $project, string $component): ?array
    {
        $sections = $this->scan($project)['sections'] ?? [];

        return is_array($sections[$component] ?? null) ? $sections[$component] : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function scanDirectory(string $root, string $relativeDirectory, array $projectionEntries = []): array
    {
        $directory = $root.'/'.trim($relativeDirectory, '/');
        if (! is_dir($directory)) {
            return [];
        }

        $items = [];

        foreach (File::files($directory) as $file) {
            $extension = strtolower($file->getExtension());
            if (! in_array($extension, ['tsx', 'ts', 'jsx', 'js'], true)) {
                continue;
            }

            $component = $file->getFilenameWithoutExtension();
            $relativePath = str_replace($root.'/', '', str_replace('\\', '/', $file->getPathname()));
            $content = File::get($file->getPathname());
            $projectionEntry = $this->matchProjectionEntry($relativePath, $component, $projectionEntries);
            $fields = $this->extractFields($component, $content, $projectionEntry);

            $items[$component] = [
                'component' => $component,
                'path' => $relativePath,
                'label' => $this->humanizeComponent($component),
                'fields' => $fields,
                'schema_json' => $this->buildSchema($component, $relativePath, $fields, $projectionEntry),
            ];
        }

        ksort($items, SORT_NATURAL | SORT_FLAG_CASE);

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $projectionEntries
     * @return array<string, mixed>
     */
    private function matchProjectionEntry(string $relativePath, string $component, array $projectionEntries): array
    {
        foreach ($projectionEntries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entryPath = trim((string) ($entry['path'] ?? ''));
            $entryComponent = trim((string) ($entry['component_name'] ?? $entry['component'] ?? ''));
            if (($entryPath !== '' && $entryPath === $relativePath) || ($entryComponent !== '' && $entryComponent === $component)) {
                return $entry;
            }
        }

        return [];
    }

    /**
     * @return array<int, array{parameterName: string, type: string, title: string, default?: mixed, format?: string}>
     */
    private function extractFields(string $component, string $content, array $projectionEntry = []): array
    {
        $defaults = $this->extractPropDefaults($content);
        $fieldNames = $this->extractDataFieldNames($content);
        foreach (array_keys($defaults) as $defaultField) {
            $fieldNames[] = $defaultField;
        }
        $fieldNames = array_values(array_unique($fieldNames));

        if ($fieldNames === []) {
            $fieldNames = array_keys($defaults);
        }

        if ($fieldNames === []) {
            $fieldNames = $this->extractPropsAccessNames($content);
        }

        $fields = [];
        foreach ($fieldNames as $fieldName) {
            $fieldName = $this->normalizeFieldName($fieldName);
            if ($fieldName === '' || in_array($fieldName, ['children', 'sectionId'], true)) {
                continue;
            }

            $definition = $this->buildFieldDefinition($fieldName, $defaults[$fieldName] ?? null, array_key_exists($fieldName, $defaults));
            $fields[$fieldName] = $definition;
        }

        foreach ($this->extractHardcodedContentFields($component, $content) as $field) {
            $fieldName = $field['parameterName'];
            if (! isset($fields[$fieldName])) {
                $fields[$fieldName] = $field;
                continue;
            }

            if (! array_key_exists('default', $fields[$fieldName]) && array_key_exists('default', $field)) {
                $fields[$fieldName]['default'] = $field['default'];
            }
        }

        foreach ($this->extractProjectionFields($projectionEntry) as $field) {
            $fieldName = trim((string) ($field['parameterName'] ?? ''));
            if ($fieldName === '') {
                continue;
            }

            if (! isset($fields[$fieldName])) {
                $fields[$fieldName] = $field;
                continue;
            }

            if (! array_key_exists('default', $fields[$fieldName]) && array_key_exists('default', $field)) {
                $fields[$fieldName]['default'] = $field['default'];
            }
        }

        return array_values($fields);
    }

    /**
     * @param  array<string, mixed>  $projectionEntry
     * @return array<int, array{parameterName: string, type: string, title: string, default?: mixed, format?: string}>
     */
    private function extractProjectionFields(array $projectionEntry): array
    {
        $propPaths = array_values(array_filter(array_map(
            static fn ($value): string => is_string($value) ? trim($value) : '',
            is_array($projectionEntry['prop_paths'] ?? null) ? $projectionEntry['prop_paths'] : []
        )));
        if ($propPaths === []) {
            return [];
        }

        $sampleProps = is_array($projectionEntry['sample_props'] ?? null) ? $projectionEntry['sample_props'] : [];
        $fields = [];
        foreach ($propPaths as $path) {
            if ($path === '' || str_ends_with($path, '.')) {
                continue;
            }

            $defaultValue = $this->readProjectionValueAtPath($sampleProps, $path);
            $hasDefault = $defaultValue !== null;
            $definition = $this->buildFieldDefinition($path, $defaultValue, $hasDefault);
            $fields[$path] = $definition;
        }

        return array_values($fields);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPropDefaults(string $content): array
    {
        $patterns = [
            '/export\s+default\s+function\s+\w+\s*\(\s*\{([\s\S]*?)\}\s*(?::|\))/',
            '/function\s+\w+\s*\(\s*\{([\s\S]*?)\}\s*(?::|\))/',
            '/=\s*\(\s*\{([\s\S]*?)\}\s*(?::|\))/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches) !== 1) {
                continue;
            }

            $fields = [];
            foreach ($this->splitTopLevelCsv($matches[1]) as $segment) {
                $segment = trim($segment);
                if ($segment === '' || str_starts_with($segment, '...')) {
                    continue;
                }

                $namePart = $segment;
                $defaultLiteral = null;
                $hasDefault = false;

                if (str_contains($segment, '=')) {
                    [$namePart, $defaultLiteral] = array_map('trim', explode('=', $segment, 2));
                    $hasDefault = true;
                }

                $namePart = trim((string) preg_replace('/:.+$/s', '', $namePart));
                $name = $this->normalizeFieldName($namePart);
                if ($name === '' || in_array($name, ['children', 'sectionId'], true)) {
                    continue;
                }

                if ($hasDefault) {
                    $fields[$name] = $this->parseLiteralValue($defaultLiteral);
                } else {
                    $fields[$name] = null;
                }
            }

            return $fields;
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function extractDataFieldNames(string $content): array
    {
        if (preg_match_all('/data-webu-field\s*=\s*"([A-Za-z0-9_]+)"/', $content, $matches) < 1) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn (string $name): string => $this->normalizeFieldName($name),
            $matches[1] ?? []
        )));
    }

    /**
     * @return array<int, string>
     */
    private function extractPropsAccessNames(string $content): array
    {
        if (preg_match_all('/props\.([A-Za-z_][A-Za-z0-9_]*)/', $content, $matches) < 1) {
            return [];
        }

        $names = [];
        foreach ($matches[1] ?? [] as $name) {
            $normalized = $this->normalizeFieldName((string) $name);
            if ($normalized !== '' && ! in_array($normalized, ['children', 'sectionId'], true)) {
                $names[$normalized] = $normalized;
            }
        }

        return array_values($names);
    }

    /**
     * @return array<int, array{parameterName: string, type: string, title: string, default?: mixed, format?: string}>
     */
    private function extractHardcodedContentFields(string $component, string $content): array
    {
        $fields = [];
        $normalizedComponent = strtolower($component);

        if (str_contains($normalizedComponent, 'header')) {
            $logoText = $this->extractTagText($content, 'div', 'site-brand') ?? $this->extractTagText($content, 'span', 'site-brand');
            if ($logoText !== null) {
                $fields[] = $this->field('logoText', $logoText);
            }

            foreach ($this->extractAnchorFields($content, limit: 4) as $index => $link) {
                $number = $index + 1;
                $fields[] = $this->field("menuLabel{$number}", $link['label']);
                $fields[] = $this->field("menuLink{$number}", $link['href'], 'string', 'url');
            }
        }

        if (str_contains($normalizedComponent, 'footer')) {
            $paragraph = $this->extractFirstTagText($content, 'p');
            if ($paragraph !== null) {
                $fields[] = $this->field('description', $paragraph);
            }
        }

        $headingTag = $this->extractFirstMatchingTag($content, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
        if ($headingTag !== null) {
            $fields[] = $this->field('title', $headingTag);
        }

        $paragraph = $this->extractFirstTagText($content, 'p');
        if ($paragraph !== null) {
            $fields[] = $this->field('subtitle', $paragraph);
        }

        $firstImage = $this->extractFirstImage($content);
        if ($firstImage !== null) {
            $fields[] = $this->field('image', $firstImage['src'], 'string', 'image');
            if ($firstImage['alt'] !== '') {
                $fields[] = $this->field('imageAlt', $firstImage['alt']);
            }
        }

        $anchors = $this->extractAnchorFields($content, limit: 1);
        if ($anchors !== []) {
            $firstLink = $anchors[0];
            $fields[] = $this->field('buttonText', $firstLink['label']);
            $fields[] = $this->field('buttonLink', $firstLink['href'], 'string', 'url');
        }

        $deduped = [];
        foreach ($fields as $field) {
            $deduped[$field['parameterName']] = $field;
        }

        return array_values($deduped);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSchema(string $component, string $path, array $fields, array $projectionEntry = []): array
    {
        $properties = [];
        foreach ($fields as $field) {
            $definition = [
                'type' => $field['type'],
                'title' => $field['title'],
                'control_group' => 'content',
            ];

            if (array_key_exists('default', $field)) {
                $definition['default'] = $field['default'];
            }

            if (isset($field['format']) && is_string($field['format']) && $field['format'] !== '') {
                $definition['format'] = $field['format'];
            }

            $properties[$field['parameterName']] = $definition;
        }

        $editableFields = array_values(array_map(
            static fn (array $field): string => $field['parameterName'],
            $fields
        ));
        $contentGroups = $editableFields !== []
            ? [[
                'key' => 'content',
                'label' => 'Content',
                'description' => 'Primary editable content fields extracted from the workspace component.',
                'fields' => $editableFields,
            ]]
            : [];

        return [
            'schema_version' => 2,
            'type' => 'object',
            'component_key' => $component,
            'display_name' => $this->humanizeComponent($component),
            'category' => $this->inferCategoryFromPath($path),
            'serializable' => true,
            'editable_fields' => $editableFields,
            'chat_targets' => $editableFields,
            'binding_fields' => $editableFields,
            'content_groups' => $contentGroups,
            'style_groups' => [],
            'advanced_groups' => [],
            'responsive_support' => [
                'enabled' => false,
                'breakpoints' => ['desktop', 'tablet', 'mobile'],
                'supportsVisibility' => false,
                'supportsResponsiveOverrides' => false,
                'interactionStates' => ['normal'],
            ],
            'fields' => array_values(array_map(function (array $field): array {
                return [
                    'path' => $field['parameterName'],
                    'label' => $field['title'],
                    'type' => ($field['format'] ?? null) === 'image' ? 'image' : (($field['format'] ?? null) === 'url' ? 'link' : 'text'),
                    'group' => 'content',
                    'default' => $field['default'] ?? null,
                    'chat_editable' => true,
                    'binding_compatible' => true,
                ];
            }, $fields)),
            'properties' => $properties,
            '_meta' => [
                'label' => $this->humanizeComponent($component),
                'description' => 'Workspace component metadata',
                'workspace_path' => $path,
                'source' => 'workspace',
                'projection_source' => $projectionEntry['projection_source'] ?? null,
                'projection_role' => $projectionEntry['projection_role'] ?? null,
                'projection_pages' => is_array($projectionEntry['pages'] ?? null) ? array_values($projectionEntry['pages']) : [],
                'projection_page_paths' => is_array($projectionEntry['page_paths'] ?? null) ? array_values($projectionEntry['page_paths']) : [],
                'projection_prop_paths' => is_array($projectionEntry['prop_paths'] ?? null) ? array_values($projectionEntry['prop_paths']) : [],
                'projection_variants' => is_array($projectionEntry['variants'] ?? null) ? $projectionEntry['variants'] : [],
                'schema_version' => 2,
                'serializable' => true,
                'editable_fields' => $editableFields,
                'chat_targets' => $editableFields,
                'binding_fields' => $editableFields,
                'content_groups' => $contentGroups,
                'style_groups' => [],
                'advanced_groups' => [],
                'responsive_support' => [
                    'enabled' => false,
                    'breakpoints' => ['desktop', 'tablet', 'mobile'],
                    'supportsVisibility' => false,
                    'supportsResponsiveOverrides' => false,
                    'interactionStates' => ['normal'],
                ],
            ],
        ];
    }

    private function inferCategoryFromPath(string $path): string
    {
        $normalized = Str::lower(str_replace('\\', '/', $path));

        if (str_contains($normalized, '/src/components/')) {
            return 'components';
        }

        if (str_contains($normalized, '/src/layouts/')) {
            return 'layout';
        }

        return 'sections';
    }

    /**
     * @return array{parameterName: string, type: string, title: string, default?: mixed, format?: string}
     */
    private function buildFieldDefinition(string $name, mixed $defaultValue, bool $hasDefault): array
    {
        $type = match (true) {
            is_bool($defaultValue) => 'boolean',
            is_int($defaultValue) => 'integer',
            is_float($defaultValue) => 'number',
            is_array($defaultValue) => 'array',
            default => 'string',
        };

        $format = $this->inferFieldFormat($name);
        $definition = [
            'parameterName' => $name,
            'type' => $type,
            'title' => $this->humanizeField($name),
        ];

        if ($hasDefault) {
            $definition['default'] = $defaultValue;
        }

        if ($format !== null) {
            $definition['format'] = $format;
        }

        return $definition;
    }

    /**
     * @return array{parameterName: string, type: string, title: string, default?: mixed, format?: string}
     */
    private function field(string $name, mixed $defaultValue, string $type = 'string', ?string $format = null): array
    {
        $field = [
            'parameterName' => $name,
            'type' => $type,
            'title' => $this->humanizeField($name),
            'default' => $defaultValue,
        ];

        if ($format !== null && $format !== '') {
            $field['format'] = $format;
        }

        return $field;
    }

    /**
     * @return array<int, string>
     */
    private function splitTopLevelCsv(string $value): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $escape = false;

        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $char = $value[$index];

            if ($quote !== null) {
                $buffer .= $char;
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($char === '\\') {
                    $escape = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === '\'') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if (in_array($char, ['{', '[', '('], true)) {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if (in_array($char, ['}', ']', ')'], true)) {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    private function parseLiteralValue(?string $literal): mixed
    {
        $literal = trim((string) $literal);
        if ($literal === '') {
            return '';
        }

        if (($literal[0] ?? '') === '\'' && str_ends_with($literal, '\'')) {
            return stripcslashes(substr($literal, 1, -1));
        }

        if (($literal[0] ?? '') === '"' && str_ends_with($literal, '"')) {
            return json_decode($literal, true) ?? substr($literal, 1, -1);
        }

        if ($literal === 'true') {
            return true;
        }

        if ($literal === 'false') {
            return false;
        }

        if ($literal === 'null') {
            return null;
        }

        if (is_numeric($literal)) {
            return str_contains($literal, '.') ? (float) $literal : (int) $literal;
        }

        return $literal;
    }

    private function normalizeFieldName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '';
    }

    private function humanizeComponent(string $component): string
    {
        return trim((string) preg_replace('/\s+/', ' ', Str::headline($component)));
    }

    private function humanizeField(string $name): string
    {
        $normalized = str_replace(['.', '_'], ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', Str::headline($normalized)));
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $sampleProps
     */
    private function readProjectionValueAtPath(array $sampleProps, string $path): mixed
    {
        $segments = array_values(array_filter(explode('.', $path), static fn ($segment): bool => $segment !== ''));
        $current = $sampleProps;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            if (is_array($current) && ctype_digit($segment) && array_key_exists((int) $segment, $current)) {
                $current = $current[(int) $segment];
                continue;
            }

            return null;
        }

        return $current;
    }

    private function inferFieldFormat(string $name): ?string
    {
        $normalized = strtolower($name);

        if (preg_match('/(image|img|photo|picture|logo|background|banner|thumbnail|avatar|icon)$/', $normalized) === 1) {
            return 'image';
        }

        if (preg_match('/(url|href|link|email|phone|tel)$/', $normalized) === 1) {
            return 'url';
        }

        if (preg_match('/(color|colour)$/', $normalized) === 1) {
            return 'color';
        }

        return null;
    }

    private function extractTagText(string $content, string $tag, ?string $classFragment = null): ?string
    {
        $classPattern = $classFragment !== null
            ? '[^>]*className="[^"]*'.preg_quote($classFragment, '/').'[^"]*"[^>]*'
            : '[^>]*';

        if (preg_match('/<'.$tag.$classPattern.'>([^<]+)<\/'.$tag.'>/i', $content, $matches) !== 1) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));

        return $text !== '' ? $text : null;
    }

    private function extractFirstTagText(string $content, string $tag): ?string
    {
        if (preg_match('/<'.$tag.'[^>]*>(.*?)<\/'.$tag.'>/is', $content, $matches) !== 1) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));

        return $text !== '' ? $text : null;
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function extractFirstMatchingTag(string $content, array $tags): ?string
    {
        foreach ($tags as $tag) {
            $text = $this->extractFirstTagText($content, $tag);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{label: string, href: string}>
     */
    private function extractAnchorFields(string $content, int $limit = 3): array
    {
        if (preg_match_all('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $links = [];
        foreach ($matches as $match) {
            $label = trim(html_entity_decode(strip_tags($match[2] ?? ''), ENT_QUOTES | ENT_HTML5));
            $href = trim((string) ($match[1] ?? ''));
            if ($label === '') {
                continue;
            }

            $links[] = [
                'label' => $label,
                'href' => $href,
            ];

            if (count($links) >= $limit) {
                break;
            }
        }

        return $links;
    }

    /**
     * @return array{src: string, alt: string}|null
     */
    private function extractFirstImage(string $content): ?array
    {
        if (preg_match('/<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*\/?>/i', $content, $matches) === 1) {
            return [
                'src' => trim((string) ($matches[1] ?? '')),
                'alt' => trim((string) ($matches[2] ?? '')),
            ];
        }

        if (preg_match('/<img[^>]*src="([^"]*)"[^>]*\/?>/i', $content, $matches) === 1) {
            return [
                'src' => trim((string) ($matches[1] ?? '')),
                'alt' => '',
            ];
        }

        return null;
    }
}
