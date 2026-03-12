<?php

namespace App\Services;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Models\Project;
use App\Models\Site;
use App\Models\Template;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Export a project or template as a Webu Template Pack (ZIP).
 *
 * ZIP structure: webu-template-pack/
 *   manifest.json
 *   layout/pages.json, theme.tokens.json, variants.json, bindings.map.json
 *   components/registry.snapshot.json, overrides/component-class-map.json
 *   presentation/css/tokens.css, base.css, components.css, templates/template.css
 *   presentation/html/preview.html, partials/, mock-data/
 *   assets/images/, fonts/
 *   docs/README.md, BINDINGS.md, BUILDER_CONTROLS.md
 *
 * HTML/CSS is presentation layer; layout JSON is source of truth.
 */
class TemplatePackExportService
{
    public function __construct(
        protected CmsRepositoryContract $repository,
        protected CmsThemeTokenLayerResolver $tokenResolver
    ) {}

    /**
     * Export project (site + pages + theme) as Template Pack ZIP.
     * Returns storage path to the ZIP file.
     */
    public function exportProject(Project $project): string
    {
        $site = $this->repository->findSiteByProject($project);
        if (! $site) {
            throw new \RuntimeException('Site not found for project.');
        }

        $template = $project->template_id
            ? Template::find($project->template_id)
            : null;

        return $this->buildZip($project->name, $project->name ?: 'export', $site, $template, $project->theme_preset ?? 'default', 'project');
    }

    /**
     * Export template (metadata + default pages) as Template Pack ZIP.
     * Uses template metadata only; no live site data.
     */
    public function exportTemplate(Template $template): string
    {
        $name = $template->name ?: $template->slug;
        $slug = $template->slug ?: 'template-export';

        return $this->buildZipFromTemplateMetadata($name, $slug, $template);
    }

    /**
     * Build ZIP from live site + optional template.
     */
    private function buildZip(string $name, string $slug, Site $site, ?Template $template, string $themePreset, string $source): string
    {
        $config = config('template_pack_export', []);
        $root = $config['zip_root'] ?? 'webu-template-pack';

        $tempDir = storage_path('app/temp/template-pack-'.uniqid());
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $basePath = $tempDir.'/'.$root;
        mkdir($basePath, 0755, true);

        $pages = $this->gatherPagesJson($site);
        $themeTokens = $this->gatherThemeTokensJson($site, $template, $themePreset);
        $variants = $this->gatherVariantsJson($site);
        $bindingsMap = $this->gatherBindingsMap($site);
        $registrySnapshot = $this->gatherRegistrySnapshot($site);
        $componentClassMap = $this->gatherComponentClassMap($site);

        $this->writeLayout($basePath, $pages, $themeTokens, $variants, $bindingsMap);
        $this->writeComponents($basePath, $registrySnapshot, $componentClassMap);
        $this->writePresentationCss($basePath, $themeTokens);
        $this->writePresentationHtmlStubs($basePath);
        $this->writeAssetsStubs($basePath);
        $this->writeDocs($basePath, $name, $slug);

        $manifest = [
            'format_version' => $config['format_version'] ?? 1,
            'name' => $name,
            'slug' => preg_replace('/[^a-z0-9_-]/', '-', strtolower($slug)),
            'exported_at' => now()->toIso8601String(),
            'source' => $source,
            'layout_version' => 1,
            'pages_count' => count($pages['pages'] ?? []),
            'theme_preset' => $themePreset,
        ];

        $this->writeJson($basePath.'/manifest.json', $manifest);

        $zipPath = storage_path('app/template-packs/'.Str::slug($name).'-'.date('Y-m-d-His').'.zip');
        $zipDir = dirname($zipPath);
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP file.');
        }

        $this->addDirectoryToZip($zip, $basePath, $root);
        $zip->close();

        $this->removeDirectory($tempDir);

        return $zipPath;
    }

    /**
     * Build ZIP from template metadata only (no site).
     */
    private function buildZipFromTemplateMetadata(string $name, string $slug, Template $template): string
    {
        $config = config('template_pack_export', []);
        $root = $config['zip_root'] ?? 'webu-template-pack';

        $tempDir = storage_path('app/temp/template-pack-'.uniqid());
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $basePath = $tempDir.'/'.$root;
        mkdir($basePath, 0755, true);

        $metadata = is_array($template->metadata) ? $template->metadata : [];
        $defaultPages = $metadata['default_pages'] ?? $metadata['default_sections'] ?? [];
        $pages = $this->normalizePagesFromMetadata($defaultPages);
        $themeTokens = $this->themeTokensFromTemplateMetadata($metadata);
        $variants = [];
        $bindingsMap = [];
        $registrySnapshot = $this->registrySnapshotFromTemplateMetadata($metadata);
        $componentClassMap = $this->defaultComponentClassMap();

        $this->writeLayout($basePath, $pages, $themeTokens, $variants, $bindingsMap);
        $this->writeComponents($basePath, $registrySnapshot, $componentClassMap);
        $this->writePresentationCss($basePath, $themeTokens);
        $this->writePresentationHtmlStubs($basePath);
        $this->writeAssetsStubs($basePath);
        $this->writeDocs($basePath, $name, $slug);

        $manifest = [
            'format_version' => $config['format_version'] ?? 1,
            'name' => $name,
            'slug' => preg_replace('/[^a-z0-9_-]/', '-', strtolower($slug)),
            'exported_at' => now()->toIso8601String(),
            'source' => 'template',
            'layout_version' => 1,
            'pages_count' => count($pages['pages'] ?? []),
            'theme_preset' => $metadata['theme_preset'] ?? 'default',
        ];

        $this->writeJson($basePath.'/manifest.json', $manifest);

        $zipPath = storage_path('app/template-packs/'.Str::slug($name).'-'.date('Y-m-d-His').'.zip');
        $zipDir = dirname($zipPath);
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP file.');
        }

        $this->addDirectoryToZip($zip, $basePath, $root);
        $zip->close();

        $this->removeDirectory($tempDir);

        return $zipPath;
    }

    private function gatherPagesJson(Site $site): array
    {
        $pages = $this->repository->listPages($site);
        $out = [];
        foreach ($pages as $page) {
            $revision = $this->repository->latestRevision($site, $page);
            $content = $revision?->content_json ?? [];
            $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
            $sectionRows = [];
            foreach ($sections as $sec) {
                if (! is_array($sec)) {
                    continue;
                }
                $type = trim((string) ($sec['type'] ?? ''));
                if ($type === '') {
                    continue;
                }
                $sectionRows[] = [
                    'type' => $type,
                    'props' => is_array($sec['props'] ?? null) ? $sec['props'] : [],
                    'variant' => $sec['variant'] ?? null,
                ];
            }
            $out[] = [
                'slug' => $page->slug,
                'title' => $page->title,
                'sections' => $sectionRows,
            ];
        }

        return ['pages' => $out, 'version' => 1];
    }

    private function normalizePagesFromMetadata(array $defaultPages): array
    {
        $pages = [];
        foreach ($defaultPages as $i => $page) {
            if (! is_array($page)) {
                continue;
            }
            $slug = $page['slug'] ?? ('page-'.($i + 1));
            $title = $page['title'] ?? ucfirst($slug);
            $sections = [];
            foreach ($page['sections'] ?? [] as $sec) {
                if (is_string($sec)) {
                    $sections[] = ['type' => $sec, 'props' => [], 'variant' => null];
                } elseif (is_array($sec)) {
                    $sections[] = [
                        'type' => $sec['key'] ?? $sec['type'] ?? 'section',
                        'props' => $sec['props'] ?? [],
                        'variant' => $sec['variant'] ?? null,
                    ];
                }
            }
            $pages[] = ['slug' => $slug, 'title' => $title, 'sections' => $sections];
        }

        return ['pages' => $pages, 'version' => 1];
    }

    private function gatherThemeTokensJson(Site $site, ?Template $template, string $themePreset): array
    {
        $resolved = $this->tokenResolver->resolveForSite($site, $site->project);
        $themeSettings = $site->theme_settings ?? [];
        $tokens = $resolved['theme_tokens'] ?? $themeSettings['theme_tokens'] ?? [];
        if (! is_array($tokens)) {
            $tokens = [];
        }

        return [
            'version' => 1,
            'theme_preset' => $themePreset,
            'theme_tokens' => $tokens,
            'colors' => $themeSettings['colors'] ?? [],
            'typography_tokens' => $themeSettings['typography_tokens'] ?? ($template->metadata['typography_tokens'] ?? []),
        ];
    }

    private function themeTokensFromTemplateMetadata(array $metadata): array
    {
        return [
            'version' => 1,
            'theme_preset' => $metadata['theme_preset'] ?? 'default',
            'theme_tokens' => $metadata['theme_tokens'] ?? [],
            'colors' => $metadata['colors'] ?? [],
            'typography_tokens' => $metadata['typography_tokens'] ?? [],
        ];
    }

    private function gatherVariantsJson(Site $site): array
    {
        $themeSettings = $site->theme_settings ?? [];
        return [
            'version' => 1,
            'variants' => $themeSettings['variants'] ?? $themeSettings['component_variants'] ?? [],
        ];
    }

    private function gatherBindingsMap(Site $site): array
    {
        $map = [];
        $pages = $this->repository->listPages($site);
        foreach ($pages as $page) {
            $revision = $this->repository->latestRevision($site, $page);
            $content = $revision?->content_json ?? [];
            $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
            foreach ($sections as $idx => $sec) {
                if (! is_array($sec)) {
                    continue;
                }
                $id = $sec['id'] ?? ($page->slug.'-section-'.$idx);
                $map[$id] = [
                    'source' => $sec['binding']['source'] ?? 'template_metadata',
                    'section_key' => $sec['type'] ?? '',
                    'params' => $sec['binding']['params'] ?? [],
                ];
            }
        }

        return ['bindings' => $map, 'version' => 1];
    }

    private function gatherRegistrySnapshot(Site $site): array
    {
        $keys = [];
        $pages = $this->repository->listPages($site);
        foreach ($pages as $page) {
            $revision = $this->repository->latestRevision($site, $page);
            $content = $revision?->content_json ?? [];
            foreach (is_array($content['sections'] ?? null) ? $content['sections'] : [] as $sec) {
                if (is_array($sec) && ! empty($sec['type'])) {
                    $keys[$sec['type']] = true;
                }
            }
        }

        return ['component_types' => array_keys($keys), 'version' => 1];
    }

    private function registrySnapshotFromTemplateMetadata(array $metadata): array
    {
        $keys = [];
        foreach ($metadata['default_pages'] ?? [] as $page) {
            foreach ($page['sections'] ?? [] as $sec) {
                $type = is_array($sec) ? ($sec['key'] ?? $sec['type'] ?? null) : $sec;
                if ($type) {
                    $keys[$type] = true;
                }
            }
        }
        foreach ($metadata['default_sections'] ?? [] as $sections) {
            foreach (is_array($sections) ? $sections : [] as $sec) {
                $type = is_array($sec) ? ($sec['key'] ?? $sec['type'] ?? null) : $sec;
                if ($type) {
                    $keys[$type] = true;
                }
            }
        }

        return ['component_types' => array_keys($keys), 'version' => 1];
    }

    private function gatherComponentClassMap(Site $site): array
    {
        return $this->defaultComponentClassMap();
    }

    private function defaultComponentClassMap(): array
    {
        return [
            'version' => 1,
            'mapping' => [
                'header' => ['base' => 'webu-header', 'variants' => ['minimal' => 'header--minimal', 'default' => 'header--default']],
                'footer' => ['base' => 'webu-footer', 'variants' => ['minimal' => 'footer--minimal', 'default' => 'footer--default']],
                'product_card' => ['base' => 'webu-product-card', 'variants' => ['premium' => 'product-card--premium', 'default' => 'product-card--default']],
                'product_grid' => ['base' => 'webu-product-grid', 'variants' => ['default' => 'product-grid--default']],
                'hero' => ['base' => 'webu-hero', 'variants' => ['default' => 'hero--default']],
            ],
        ];
    }

    private function writeLayout(string $basePath, array $pages, array $themeTokens, array $variants, array $bindingsMap): void
    {
        $layoutDir = $basePath.'/layout';
        if (! is_dir($layoutDir)) {
            mkdir($layoutDir, 0755, true);
        }
        $this->writeJson($layoutDir.'/pages.json', $pages);
        $this->writeJson($layoutDir.'/theme.tokens.json', $themeTokens);
        $this->writeJson($layoutDir.'/variants.json', $variants);
        $this->writeJson($layoutDir.'/bindings.map.json', $bindingsMap);
    }

    private function writeComponents(string $basePath, array $registrySnapshot, array $componentClassMap): void
    {
        $componentsDir = $basePath.'/components';
        if (! is_dir($componentsDir)) {
            mkdir($componentsDir, 0755, true);
        }
        $this->writeJson($componentsDir.'/registry.snapshot.json', $registrySnapshot);
        $overridesDir = $basePath.'/components/overrides';
        if (! is_dir($overridesDir)) {
            mkdir($overridesDir, 0755, true);
        }
        $this->writeJson($overridesDir.'/component-class-map.json', $componentClassMap);
    }

    private function writePresentationCss(string $basePath, array $themeTokens): void
    {
        $cssDir = $basePath.'/presentation/css';
        if (! is_dir($cssDir)) {
            mkdir($cssDir, 0755, true);
        }
        $tokensCss = $this->generateTokensCss($themeTokens);
        file_put_contents($cssDir.'/tokens.css', $tokensCss);
        file_put_contents($cssDir.'/base.css', "/* Webu base styles - safe to override */\n.webu-template {}\n");
        file_put_contents($cssDir.'/components.css', "/* Component-level overrides - use .webu-* or .template-* */\n");
        $templateDir = $basePath.'/presentation/css/templates';
        if (! is_dir($templateDir)) {
            mkdir($templateDir, 0755, true);
        }
        file_put_contents($templateDir.'/template.css', "/* Template-specific skin */\n.webu-template {}\n");
    }

    private function generateTokensCss(array $themeTokens): string
    {
        $lines = ["/* Generated from theme.tokens.json - safe to edit for local styling */", ":root {"];
        $tokens = $themeTokens['theme_tokens'] ?? $themeTokens;
        if (is_array($tokens)) {
            foreach ($tokens as $key => $value) {
                if (is_scalar($value) && ! is_bool($value)) {
                    $var = '--'.str_replace('_', '-', $key);
                    $lines[] = "  {$var}: {$value};";
                }
            }
        }
        foreach ($themeTokens['colors'] ?? [] as $key => $value) {
            if (is_scalar($value)) {
                $lines[] = "  --color-".str_replace('_', '-', $key).": {$value};";
            }
        }
        $lines[] = '}';
        return implode("\n", $lines);
    }

    private function getPreviewHtmlContent(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Webu Template Pack — Local Preview</title>
  <link rel="stylesheet" href="../css/tokens.css">
  <link rel="stylesheet" href="../css/base.css">
  <link rel="stylesheet" href="../css/components.css">
  <link rel="stylesheet" href="../css/templates/template.css">
</head>
<body class="webu-template">
  <div id="preview-root"></div>
  <p class="preview-fallback" style="padding:1rem;color:#666;">Loading layout… (serve this folder with a local server, e.g. <code>npx serve .</code> from pack root)</p>
  <script>
(function() {
  const root = document.getElementById('preview-root');
  const fallback = document.querySelector('.preview-fallback');
  const base = window.location.pathname.replace(/\/presentation\/html\/.*$/, '');
  const layoutUrl = base ? base + '/layout/pages.json' : '../../layout/pages.json';
  fetch(layoutUrl).then(function(r) { return r.ok ? r.json() : null; }).then(function(data) {
    if (!data || !Array.isArray(data.pages)) { fallback.textContent = 'Could not load layout/pages.json'; return; }
    fallback.style.display = 'none';
    data.pages.forEach(function(page) {
      const pageEl = document.createElement('div');
      pageEl.className = 'webu-page';
      pageEl.setAttribute('data-page-slug', page.slug || '');
      const title = document.createElement('h2');
      title.textContent = (page.title || page.slug || 'Page');
      title.style.cssText = 'margin:1rem 0 0.5rem; font-size:1rem;';
      pageEl.appendChild(title);
      (page.sections || []).forEach(function(sec) {
        const type = sec.type || sec.key || 'section';
        const variant = sec.variant || 'default';
        const div = document.createElement('div');
        div.className = 'webu-section webu-' + (type.replace(/_/g, '-')) + ' webu-variant-' + (String(variant).replace(/\s+/g, '-').toLowerCase());
        div.setAttribute('data-webu-section', type);
        div.innerHTML = '<span style="font-size:12px;color:#999;">' + type + '</span>';
        pageEl.appendChild(div);
      });
      root.appendChild(pageEl);
    });
  }).catch(function() { fallback.textContent = 'Failed to load layout. Serve the pack root with a local server (e.g. npx serve .).'; });
})();
  </script>
</body>
</html>
HTML;
    }

    private function writePresentationHtmlStubs(string $basePath): void
    {
        $htmlDir = $basePath.'/presentation/html';
        if (! is_dir($htmlDir)) {
            mkdir($htmlDir, 0755, true);
        }
        $preview = $this->getPreviewHtmlContent();
        file_put_contents($htmlDir.'/preview.html', $preview);
        $partialsDir = $htmlDir.'/partials';
        if (! is_dir($partialsDir)) {
            mkdir($partialsDir, 0755, true);
        }
        foreach (['header.html', 'footer.html', 'hero.html', 'product-card.html', 'product-grid.html'] as $f) {
            file_put_contents($partialsDir.'/'.$f, "<!-- {$f} partial - for local preview only; import ignores -->\n");
        }
        $mockDir = $htmlDir.'/mock-data';
        if (! is_dir($mockDir)) {
            mkdir($mockDir, 0755, true);
        }
        file_put_contents($mockDir.'/home.json', json_encode(['sections' => []], JSON_PRETTY_PRINT));
    }

    private function writeAssetsStubs(string $basePath): void
    {
        $imagesDir = $basePath.'/assets/images';
        $fontsDir = $basePath.'/assets/fonts';
        if (! is_dir($imagesDir)) {
            mkdir($imagesDir, 0755, true);
        }
        if (! is_dir($fontsDir)) {
            mkdir($fontsDir, 0755, true);
        }
    }

    private function writeDocs(string $basePath, string $name, string $slug): void
    {
        $docsDir = $basePath.'/docs';
        if (! is_dir($docsDir)) {
            mkdir($docsDir, 0755, true);
        }
        $readme = "# {$name}\n\nWebu Template Pack. Edit CSS under presentation/css; layout is in layout/pages.json.\n";
        file_put_contents($docsDir.'/README.md', $readme);
        file_put_contents($docsDir.'/BINDINGS.md', "# Bindings\n\nSee layout/bindings.map.json for component binding sources.\n");
        file_put_contents($docsDir.'/BUILDER_CONTROLS.md', "# Builder controls\n\nSchema is preserved on import; builder remains compatible.\n");
    }

    private function writeJson(string $path, array $data): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function addDirectoryToZip(ZipArchive $zip, string $basePath, string $zipRoot): void
    {
        $len = strlen($basePath) + 1;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            $path = $file->getPathname();
            if (is_dir($path)) {
                $zip->addEmptyDir($zipRoot.'/'.substr($path, $len));
            } else {
                $zip->addFile($path, $zipRoot.'/'.substr($path, $len));
            }
        }
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
