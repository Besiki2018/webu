<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Validation rules for Template Pack import.
 *
 * Import must fail if:
 * - pages.json is missing required ecommerce pages
 * - unknown component types exist
 * - bindings refer to unknown sources
 * - component ids missing in binding map (optional strict check)
 *
 * @see docs/VALIDATION_RULES.md
 */
class TemplatePackImportValidator
{
    public function __construct()
    {
        $this->requiredPages = config('template_pack_export.required_ecommerce_pages', [
            'home', 'shop', 'product', 'cart', 'checkout', 'contact',
        ]);
        $this->allowedBindingSources = ['template_metadata', 'sections_library', 'cms_products', 'cms_categories', 'cms_cart', 'cms_checkout'];
    }

    /** @var list<string> */
    private array $requiredPages;

    /** @var list<string> */
    private array $allowedBindingSources;

    public function validateZip(UploadedFile $file): void
    {
        if ($file->getClientOriginalExtension() !== 'zip' && $file->getMimeType() !== 'application/zip') {
            throw new \InvalidArgumentException('File must be a ZIP archive.');
        }
        if ($file->getSize() > 50 * 1024 * 1024) {
            throw new \InvalidArgumentException('ZIP size must not exceed 50MB.');
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function validateManifest(array $manifest): void
    {
        $required = config('template_pack_export.manifest_required_keys', [
            'format_version', 'name', 'slug', 'exported_at', 'source', 'layout_version',
        ]);
        foreach ($required as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new \InvalidArgumentException("manifest.json missing required key: {$key}.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $pages
     */
    public function validatePages(array $pages): void
    {
        $pageList = $pages['pages'] ?? [];
        if (! is_array($pageList)) {
            throw new \InvalidArgumentException('layout/pages.json must contain a "pages" array.');
        }

        $slugs = [];
        foreach ($pageList as $p) {
            if (is_array($p) && isset($p['slug'])) {
                $slugs[] = (string) $p['slug'];
            }
        }

        foreach ($this->requiredPages as $required) {
            if (! in_array($required, $slugs, true)) {
                throw new \InvalidArgumentException("Required ecommerce page missing: {$required}. Pages must include: ".implode(', ', $this->requiredPages));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $pages
     * @param  array<string, mixed>  $bindingsMap
     * @param  array<string, mixed>  $registrySnapshot
     */
    public function validateBindingsAndRegistry(array $pages, array $bindingsMap, array $registrySnapshot): void
    {
        $allowedTypes = array_fill_keys($registrySnapshot['component_types'] ?? [], true);

        $pageList = $pages['pages'] ?? [];
        foreach ($pageList as $p) {
            if (! is_array($p)) {
                continue;
            }
            foreach ($p['sections'] ?? [] as $sec) {
                if (! is_array($sec)) {
                    continue;
                }
                $type = $sec['type'] ?? $sec['key'] ?? null;
                if ($type !== null && $type !== '' && ! isset($allowedTypes[$type])) {
                    throw new \InvalidArgumentException("Unknown component type in pages: {$type}. All types must exist in components/registry.snapshot.json.");
                }
            }
        }

        $bindings = $bindingsMap['bindings'] ?? [];
        if (is_array($bindings)) {
            foreach ($bindings as $id => $binding) {
                if (! is_array($binding)) {
                    continue;
                }
                $source = $binding['source'] ?? '';
                if ($source !== '' && ! in_array($source, $this->allowedBindingSources, true)) {
                    throw new \InvalidArgumentException("Binding source not allowed: {$source} for component {$id}.");
                }
            }
        }
    }

    /**
     * Check CSS files for namespace guidance (.webu-template, .webu-*, .template-*).
     * Returns list of warning messages (does not fail import).
     *
     * @return string[]
     */
    public function getCssScopeWarnings(string $basePath): array
    {
        $warnings = [];
        $allowedPrefixes = ['.webu-', '.template-', ':root', '@media', '@import', '@keyframes', '@font-face', '/*'];
        $files = [
            $basePath.'/presentation/css/components.css',
            $basePath.'/presentation/css/templates/template.css',
        ];
        foreach ($files as $path) {
            if (! is_file($path)) {
                continue;
            }
            $css = file_get_contents($path);
            if (preg_match_all('/^\s*([^{]+)\s*\{/m', $css, $m)) {
                foreach ($m[1] as $selector) {
                    $sel = trim($selector);
                    if ($sel === '') {
                        continue;
                    }
                    $ok = false;
                    foreach ($allowedPrefixes as $prefix) {
                        if (str_starts_with($sel, $prefix)) {
                            $ok = true;
                            break;
                        }
                    }
                    if (! $ok && ! str_contains($sel, ',')) {
                        $warnings[] = 'CSS selector should be scoped under .webu-* or .template-*: '.substr($sel, 0, 60);
                    }
                }
            }
        }

        return array_slice(array_unique($warnings), 0, 5);
    }
}
