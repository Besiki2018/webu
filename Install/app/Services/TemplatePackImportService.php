<?php

namespace App\Services;

use App\Models\Template;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Import a Webu Template Pack (ZIP).
 *
 * Allowed from local edits: CSS, assets, component-class-map, theme token defaults.
 * Not allowed: changing bindings source/types, removing required ecommerce pages,
 * unknown component types, raw HTML injection.
 */
class TemplatePackImportService
{
    public function __construct(
        protected TemplatePackImportValidator $validator
    ) {}

    /**
     * Preview ZIP: validate and return summary without creating template.
     *
     * @return array{name: string, slug: string, pages_count: int, bindings_count: int, warnings: string[]}
     */
    public function preview(UploadedFile $file): array
    {
        $this->validator->validateZip($file);

        $tempDir = storage_path('app/temp/template-pack-preview-'.uniqid());
        mkdir($tempDir, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw new \RuntimeException('Invalid or unreadable ZIP file.');
        }

        $zipRoot = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (str_contains($entry, 'manifest.json')) {
                $zipRoot = dirname($entry);
                break;
            }
        }
        if ($zipRoot === null || $zipRoot === '.') {
            $zip->close();
            $this->removeDirectory($tempDir);
            throw new \RuntimeException('ZIP must contain webu-template-pack/manifest.json.');
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $basePath = $tempDir.'/'.$zipRoot;
        $manifestPath = $basePath.'/manifest.json';
        if (! is_file($manifestPath)) {
            $this->removeDirectory($tempDir);
            throw new \RuntimeException('manifest.json not found in pack.');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            $this->removeDirectory($tempDir);
            throw new \RuntimeException('manifest.json is invalid.');
        }

        $this->validator->validateManifest($manifest);
        $pages = $this->readJson($basePath.'/layout/pages.json');
        $this->validator->validatePages($pages);
        $bindingsMap = $this->readJson($basePath.'/layout/bindings.map.json');
        $registrySnapshot = $this->readJson($basePath.'/components/registry.snapshot.json');
        $this->validator->validateBindingsAndRegistry($pages, $bindingsMap, $registrySnapshot);

        $warnings = $this->validator->getCssScopeWarnings($basePath);

        $pagesCount = count($pages['pages'] ?? []);
        $bindingsCount = count($bindingsMap['bindings'] ?? []);

        $this->removeDirectory($tempDir);

        return [
            'name' => $manifest['name'] ?? 'Imported Template',
            'slug' => $manifest['slug'] ?? '',
            'pages_count' => $pagesCount,
            'bindings_count' => $bindingsCount,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate and import uploaded ZIP. Returns template and any warnings.
     *
     * @return array{template: Template, warnings: string[]}
     */
    public function import(UploadedFile $file, string $name = '', string $slug = ''): array
    {
        $this->validator->validateZip($file);

        $tempDir = storage_path('app/temp/template-pack-import-'.uniqid());
        mkdir($tempDir, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw new \RuntimeException('Invalid or unreadable ZIP file.');
        }

        $zipRoot = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (str_contains($entry, 'manifest.json')) {
                $zipRoot = dirname($entry);
                break;
            }
        }
        if ($zipRoot === null || $zipRoot === '.') {
            $zip->close();
            throw new \RuntimeException('ZIP must contain webu-template-pack/manifest.json.');
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $basePath = $tempDir.'/'.$zipRoot;
        $manifestPath = $basePath.'/manifest.json';
        if (! is_file($manifestPath)) {
            $this->removeDirectory($tempDir);
            throw new \RuntimeException('manifest.json not found in pack.');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            $this->removeDirectory($tempDir);
            throw new \RuntimeException('manifest.json is invalid.');
        }

        $this->validator->validateManifest($manifest);
        $pages = $this->readJson($basePath.'/layout/pages.json');
        $this->validator->validatePages($pages);
        $bindingsMap = $this->readJson($basePath.'/layout/bindings.map.json');
        $registrySnapshot = $this->readJson($basePath.'/components/registry.snapshot.json');
        $this->validator->validateBindingsAndRegistry($pages, $bindingsMap, $registrySnapshot);

        $warnings = $this->validator->getCssScopeWarnings($basePath);

        $themeTokens = $this->readJson($basePath.'/layout/theme.tokens.json');
        $variants = $this->readJson($basePath.'/layout/variants.json');
        $componentClassMap = is_file($basePath.'/components/overrides/component-class-map.json')
            ? $this->readJson($basePath.'/components/overrides/component-class-map.json')
            : [];

        $tokensCssPath = $basePath.'/presentation/css/tokens.css';
        if (is_file($tokensCssPath)) {
            $themeTokens = $this->mergeTokensFromCss($themeTokens, file_get_contents($tokensCssPath));
        }

        $templateName = $name ?: ($manifest['name'] ?? 'Imported Template');
        $templateSlug = $slug ?: ($manifest['slug'] ?? Str::slug($templateName));

        $defaultPages = [];
        foreach ($pages['pages'] ?? [] as $p) {
            $sections = [];
            foreach ($p['sections'] ?? [] as $sec) {
                $sections[] = [
                    'key' => $sec['type'] ?? 'section',
                    'props' => $sec['props'] ?? [],
                    'enabled' => true,
                ];
            }
            $defaultPages[] = [
                'slug' => $p['slug'] ?? 'page',
                'title' => $p['title'] ?? 'Page',
                'sections' => $sections,
            ];
        }

        $metadata = [
            'vertical' => 'ecommerce',
            'framework' => 'webu_template_pack',
            'module_flags' => [
                'cms_pages' => true,
                'cms_menus' => true,
                'ecommerce' => true,
                'payments' => true,
            ],
            'default_pages' => $defaultPages,
            'theme_tokens' => $themeTokens['theme_tokens'] ?? [],
            'typography_tokens' => $themeTokens['typography_tokens'] ?? [],
            'colors' => $themeTokens['colors'] ?? [],
        ];

        $template = Template::updateOrCreate(
            ['slug' => $templateSlug],
            [
                'name' => $templateName,
                'description' => 'Imported from Template Pack. CMS bindings preserved.',
                'category' => 'ecommerce',
                'version' => '1.0.0',
                'is_system' => false,
                'zip_path' => null,
                'thumbnail' => null,
                'metadata' => $metadata,
            ]
        );

        $this->removeDirectory($tempDir);

        return ['template' => $template, 'warnings' => $warnings];
    }

    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function mergeTokensFromCss(array $themeTokens, string $css): array
    {
        if (preg_match_all('/--([a-z0-9-]+):\s*([^;]+);/i', $css, $m, PREG_SET_ORDER)) {
            $tokens = $themeTokens['theme_tokens'] ?? [];
            foreach ($m as $match) {
                $key = str_replace('-', '_', trim($match[1]));
                $tokens[$key] = trim($match[2]);
            }
            $themeTokens['theme_tokens'] = $tokens;
        }

        return $themeTokens;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($path);
    }
}
