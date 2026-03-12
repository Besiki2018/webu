<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class TemplateImportContractService
{
    /**
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validateSourceRoot(string $sourceRoot): array
    {
        $errors = [];
        $warnings = [];

        if (! is_dir($sourceRoot)) {
            $errors[] = 'Source root is not a directory.';

            return [
                'valid' => false,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        if (! is_readable($sourceRoot)) {
            $errors[] = 'Source root is not readable.';
        }

        $hasTemplateJson = File::exists($sourceRoot.'/template.json');
        $hasIndexHtml = File::exists($sourceRoot.'/index.html');
        $hasPackageJson = File::exists($sourceRoot.'/package.json');
        $hasCodeDir = File::isDirectory($sourceRoot.'/src')
            || File::isDirectory($sourceRoot.'/pages')
            || File::isDirectory($sourceRoot.'/app');
        $hasPublicDir = File::isDirectory($sourceRoot.'/public');
        $hasGatsbyConfig = File::exists($sourceRoot.'/gatsby-config.js')
            || File::exists($sourceRoot.'/gatsby-node.js');

        if (
            ! $hasTemplateJson
            && ! $hasIndexHtml
            && ! ($hasPackageJson && ($hasCodeDir || $hasPublicDir))
            && ! ($hasPackageJson && $hasGatsbyConfig)
        ) {
            $errors[] = 'Root must contain template.json, index.html, package.json with src/pages/app/public directory, or Gatsby config.';
        }

        $fileCount = count(File::allFiles($sourceRoot));
        if ($fileCount === 0) {
            $errors[] = 'Source root has no files to import.';
        }

        if (! $hasTemplateJson) {
            $warnings[] = 'template.json is missing; metadata will be inferred.';
        }

        if ($hasTemplateJson) {
            $this->appendTemplateManifestWarnings($sourceRoot, $warnings);
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validateMetadata(array $metadata): array
    {
        $errors = [];
        $warnings = [];

        foreach (['vertical', 'framework', 'module_flags', 'default_pages', 'default_sections'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $metadata)) {
                $errors[] = "Missing metadata key: {$requiredKey}.";
            }
        }

        if (array_key_exists('module_flags', $metadata) && ! is_array($metadata['module_flags'])) {
            $errors[] = 'module_flags must be an object.';
        }

        if (array_key_exists('default_pages', $metadata)) {
            if (! is_array($metadata['default_pages']) || $metadata['default_pages'] === []) {
                $errors[] = 'default_pages must contain at least one page.';
            } else {
                $slugs = [];
                foreach ($metadata['default_pages'] as $index => $page) {
                    if (! is_array($page)) {
                        $errors[] = "default_pages[{$index}] must be an object.";
                        continue;
                    }

                    $slug = trim((string) ($page['slug'] ?? ''));
                    if ($slug === '') {
                        $errors[] = "default_pages[{$index}] missing slug.";
                        continue;
                    }

                    if (in_array($slug, $slugs, true)) {
                        $errors[] = "default_pages has duplicate slug: {$slug}.";
                    }
                    $slugs[] = $slug;
                }
            }
        }

        if (array_key_exists('default_sections', $metadata)) {
            if (! is_array($metadata['default_sections']) || $metadata['default_sections'] === []) {
                $errors[] = 'default_sections must contain at least one page section list.';
            }
        }

        if (! isset($metadata['section_inventory']) || ! is_array($metadata['section_inventory'])) {
            $warnings[] = 'section_inventory is missing.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function appendTemplateManifestWarnings(string $sourceRoot, array &$warnings): void
    {
        $manifest = $this->readJsonFile($sourceRoot.'/template.json');
        if (! is_array($manifest)) {
            $warnings[] = 'template.json is not valid JSON.';

            return;
        }

        $this->appendDemoContentWarnings($sourceRoot, $manifest, $warnings);
        $this->appendBindingMarkerWarnings($sourceRoot, $manifest, $warnings);
        $this->appendEncodingWarnings($sourceRoot, $warnings);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<int, string>  $warnings
     */
    private function appendDemoContentWarnings(string $sourceRoot, array $manifest, array &$warnings): void
    {
        $declaresDemoContent = (bool) ($manifest['demo_content'] ?? false)
            || is_string($manifest['demoContentPath'] ?? null);

        if (! $declaresDemoContent) {
            return;
        }

        $demoPathRaw = is_string($manifest['demoContentPath'] ?? null)
            ? $manifest['demoContentPath']
            : 'demo-content/';
        $demoPath = trim(str_replace('\\', '/', $demoPathRaw), '/');

        if ($demoPath === '') {
            $demoPath = 'demo-content';
        }

        $demoDir = $sourceRoot.'/'.$demoPath;
        if (! File::isDirectory($demoDir)) {
            $warnings[] = "template.json declares demo content but directory is missing: {$demoPath}/";

            return;
        }

        $contentManifestPath = $demoDir.'/content.json';
        if (! File::exists($contentManifestPath)) {
            $warnings[] = "Demo content directory is missing content.json ({$demoPath}/content.json).";

            return;
        }

        $contentManifest = $this->readJsonFile($contentManifestPath);
        if (! is_array($contentManifest)) {
            $warnings[] = "Demo content manifest is not valid JSON: {$demoPath}/content.json";

            return;
        }

        $datasets = is_array($contentManifest['datasets'] ?? null) ? $contentManifest['datasets'] : [];
        foreach (['products_file', 'posts_file'] as $datasetKey) {
            $fileName = $this->normalizeDatasetFileName($datasets[$datasetKey] ?? null);
            if (! $fileName) {
                continue;
            }

            $datasetPath = $demoDir.'/'.$fileName;
            if (! File::exists($datasetPath)) {
                $warnings[] = "Demo dataset file declared in content.json is missing: {$demoPath}/{$fileName}";
                continue;
            }

            $decoded = $this->readJsonFile($datasetPath);
            if (! is_array($decoded)) {
                $warnings[] = "Demo dataset file is not valid JSON: {$demoPath}/{$fileName}";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<int, string>  $warnings
     */
    private function appendBindingMarkerWarnings(string $sourceRoot, array $manifest, array &$warnings): void
    {
        $isBuilderTemplate = is_array($manifest['components'] ?? null)
            || is_array($manifest['builder'] ?? null)
            || is_array($manifest['pages'] ?? null);

        if (! $isBuilderTemplate) {
            return;
        }

        $htmlFiles = collect(File::allFiles($sourceRoot))
            ->filter(fn ($file) => strtolower((string) $file->getExtension()) === 'html')
            ->take(50)
            ->values();

        if ($htmlFiles->isEmpty()) {
            return;
        }

        $hasCmsMarker = false;
        $hasEcommerceMarker = false;

        foreach ($htmlFiles as $file) {
            $contents = File::get($file->getPathname());

            if (
                str_contains($contents, 'data-webu-section')
                || str_contains($contents, 'data-webu-field')
                || str_contains($contents, 'data-webu-menu')
                || str_contains($contents, 'data-webu-contact')
                || str_contains($contents, 'data-webu-logo')
            ) {
                $hasCmsMarker = true;
            }

            if (
                str_contains($contents, 'data-webby-ecommerce-products')
                || str_contains($contents, 'data-webby-ecommerce-cart')
            ) {
                $hasEcommerceMarker = true;
            }

            if ($hasCmsMarker && $hasEcommerceMarker) {
                break;
            }
        }

        if (! $hasCmsMarker) {
            $warnings[] = 'No Webu CMS binding markers detected in HTML (e.g. data-webu-section/data-webu-field).';
        }

        $declaresEcommercePages = collect((array) ($manifest['pages'] ?? []))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->contains(fn ($page) => in_array($page, ['shop', 'product', 'cart', 'checkout'], true));

        if ($declaresEcommercePages && ! $hasEcommerceMarker) {
            $warnings[] = 'Template appears to include ecommerce pages but no ecommerce binding markers were detected (data-webby-ecommerce-products/cart).';
        }

        $this->appendPageBlueprintStructureWarnings($sourceRoot, $manifest, $warnings);
        $this->appendPageBlueprintEcommerceBindingWarnings($sourceRoot, $manifest, $warnings);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function appendEncodingWarnings(string $sourceRoot, array &$warnings): void
    {
        $htmlFiles = collect(File::allFiles($sourceRoot))
            ->filter(fn ($file) => strtolower((string) $file->getExtension()) === 'html')
            ->take(30)
            ->values();

        if ($htmlFiles->isEmpty()) {
            return;
        }

        foreach ($htmlFiles as $file) {
            $contents = File::get($file->getPathname());
            if (! is_string($contents) || $contents === '') {
                continue;
            }

            if (! $this->containsSuspectedMojibake($contents)) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', str_replace($sourceRoot, '', $file->getPathname())), '/');
            $warnings[] = "Potential text encoding issue detected in HTML ({$relative}); verify source file is saved as UTF-8 to avoid mojibake in CMS defaults.";

            break;
        }
    }

    private function containsSuspectedMojibake(string $contents): bool
    {
        // Common mojibake fragments seen after UTF-8 text is interpreted as Latin-1/Windows-1252 and re-encoded.
        if (preg_match('/(?:Ã.|Â.|â(?:€™|€œ|€|€“|€”|€¦|„¢))/u', $contents) === 1) {
            return true;
        }

        // Detect patterns like "á" frequently produced by broken Georgian UTF-8 decoding.
        return preg_match('/\xC3[\x80-\xBF]\xC2[\x80-\xBF]/', $contents) === 1;
    }

    /**
     * Validate page blueprint file presence / JSON readability / referenced page template files.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<int, string>  $warnings
     */
    private function appendPageBlueprintStructureWarnings(string $sourceRoot, array $manifest, array &$warnings): void
    {
        $pageBlueprintPathRaw = (string) ($manifest['pageBlueprintsPath'] ?? 'pages/');
        $pageBlueprintPath = trim(str_replace('\\', '/', $pageBlueprintPathRaw), '/');
        if ($pageBlueprintPath === '') {
            $pageBlueprintPath = 'pages';
        }

        $pageBlueprintDir = $sourceRoot.'/'.$pageBlueprintPath;
        $hasExplicitBlueprintPath = is_string($manifest['pageBlueprintsPath'] ?? null)
            && trim((string) $manifest['pageBlueprintsPath']) !== '';

        $declaredPages = collect((array) ($manifest['pages'] ?? []))
            ->map(function ($value): string {
                if (is_string($value)) {
                    return strtolower(trim($value));
                }

                if (! is_array($value)) {
                    return '';
                }

                foreach (['slug', 'key', 'name'] as $key) {
                    if (isset($value[$key]) && is_string($value[$key])) {
                        $candidate = strtolower(trim((string) $value[$key]));
                        if ($candidate !== '') {
                            return $candidate;
                        }
                    }
                }

                return '';
            })
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        if (! File::isDirectory($pageBlueprintDir)) {
            if ($hasExplicitBlueprintPath && $declaredPages->isNotEmpty()) {
                $warnings[] = "template.json declares pageBlueprintsPath but directory is missing: {$pageBlueprintPath}/";
            }

            return;
        }

        foreach ($declaredPages as $pageKey) {
            $expectedBlueprint = $pageBlueprintDir.'/'.$pageKey.'.json';
            if (! File::exists($expectedBlueprint)) {
                $warnings[] = "Declared page blueprint is missing JSON definition: {$pageBlueprintPath}/{$pageKey}.json";
            }
        }

        $pageFiles = collect(File::files($pageBlueprintDir))
            ->filter(fn ($file) => strtolower((string) $file->getExtension()) === 'json')
            ->take(50)
            ->values();

        foreach ($pageFiles as $pageFile) {
            $relativeBlueprintPath = $pageBlueprintPath.'/'.$pageFile->getFilename();
            $blueprint = $this->readJsonFile($pageFile->getPathname());
            if (! is_array($blueprint)) {
                $warnings[] = "Page blueprint JSON is not valid: {$relativeBlueprintPath}";

                continue;
            }

            $pageTemplateFile = trim((string) ($blueprint['file'] ?? ''));
            if ($pageTemplateFile === '') {
                continue;
            }

            if (str_contains($pageTemplateFile, '..')) {
                $warnings[] = "Page blueprint references unsafe template path (..): {$relativeBlueprintPath}";

                continue;
            }

            $normalizedPageTemplateFile = ltrim(str_replace('\\', '/', $pageTemplateFile), '/');
            $absolutePageTemplatePath = $sourceRoot.'/'.$normalizedPageTemplateFile;
            if (! File::exists($absolutePageTemplatePath)) {
                $warnings[] = sprintf(
                    'Page blueprint references missing page template file: %s -> %s',
                    $relativeBlueprintPath,
                    $normalizedPageTemplateFile
                );
            }
        }
    }

    /**
     * Validate page blueprints against component/page-level ecommerce binding markers.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<int, string>  $warnings
     */
    private function appendPageBlueprintEcommerceBindingWarnings(string $sourceRoot, array $manifest, array &$warnings): void
    {
        $pageBlueprintPathRaw = (string) ($manifest['pageBlueprintsPath'] ?? 'pages/');
        $pageBlueprintPath = trim(str_replace('\\', '/', $pageBlueprintPathRaw), '/');
        if ($pageBlueprintPath === '') {
            $pageBlueprintPath = 'pages';
        }

        $pageBlueprintDir = $sourceRoot.'/'.$pageBlueprintPath;
        if (! File::isDirectory($pageBlueprintDir)) {
            return;
        }

        $pageFiles = collect(File::files($pageBlueprintDir))
            ->filter(fn ($file) => strtolower((string) $file->getExtension()) === 'json')
            ->take(30)
            ->values();

        if ($pageFiles->isEmpty()) {
            return;
        }

        $componentHtmlByName = $this->resolveComponentHtmlTemplates($sourceRoot, $manifest);

        $pageRuleDefinitions = [
            [
                'pages' => ['shop'],
                'marker' => 'data-webby-ecommerce-products',
                'warning' => 'Shop page blueprint (%s) appears to lack a products binding marker in referenced components/page template (data-webby-ecommerce-products).',
            ],
            [
                'pages' => ['cart'],
                'marker' => 'data-webby-ecommerce-cart',
                'page_tokens_all' => ['shop_cart_table'],
                'warning' => 'Cart page blueprint (%s) appears to lack cart runtime anchors in referenced components/page template (data-webby-ecommerce-cart or .shop_cart_table).',
            ],
            [
                'pages' => ['checkout'],
                'marker' => 'data-webby-ecommerce-checkout',
                'page_tokens_all' => ['order_review', 'order_table'],
                'warning' => 'Checkout page blueprint (%s) appears to lack checkout runtime anchors in page template (data-webby-ecommerce-checkout or .order_review/.order_table).',
            ],
            [
                'pages' => ['product'],
                'page_tokens_all' => ['product_description'],
                'page_tokens_any' => ['btn-addtocart', 'add-to-cart'],
                'warning' => 'Product page blueprint (%s) appears to lack product detail runtime anchors in page template (.product_description plus add-to-cart trigger).',
            ],
            [
                'pages' => ['login', 'register', 'login-register', 'auth', 'signup', 'sign-up'],
                'marker' => 'data-webby-ecommerce-auth',
                'page_tokens_all' => ['login_register_wrap'],
                'page_tokens_any' => ['login_wrap', 'btn-login', 'different_login'],
                'warning' => 'Auth page blueprint (%s) appears to lack authentication runtime anchors in referenced components/page template (data-webby-ecommerce-auth or .login_register_wrap/.login_wrap).',
            ],
            [
                'pages' => ['account', 'profile', 'my-account', 'myaccount'],
                'marker' => 'data-webby-ecommerce-account-dashboard',
                'page_tokens_all' => ['dashboard_content'],
                'page_tokens_any' => ['dashboard_menu', 'dashboard-tab', 'account-detail-tab'],
                'warning' => 'Account page blueprint (%s) appears to lack account dashboard runtime anchors in referenced components/page template (data-webby-ecommerce-account-dashboard or .dashboard_menu/.dashboard_content).',
            ],
            [
                'pages' => ['orders', 'orders-list', 'account-orders', 'order-history'],
                'marker' => 'data-webby-ecommerce-orders-list',
                'warning' => 'Orders page blueprint (%s) appears to lack orders list runtime marker in referenced components/page template (data-webby-ecommerce-orders-list).',
            ],
            [
                'pages' => ['order', 'order-detail', 'order-status'],
                'marker' => 'data-webby-ecommerce-order-detail',
                'warning' => 'Order detail page blueprint (%s) appears to lack order detail runtime marker in referenced components/page template (data-webby-ecommerce-order-detail).',
            ],
        ];
        $rulesByPage = [];
        foreach ($pageRuleDefinitions as $pageRuleDefinition) {
            $pageAliases = array_values(array_filter(
                array_map(fn ($value): string => strtolower(trim((string) $value)), (array) ($pageRuleDefinition['pages'] ?? [])),
                fn (string $value): bool => $value !== ''
            ));

            if ($pageAliases === []) {
                continue;
            }

            $pageRule = $pageRuleDefinition;
            unset($pageRule['pages']);

            foreach ($pageAliases as $pageAlias) {
                $rulesByPage[$pageAlias] = $pageRule;
            }
        }

        foreach ($pageFiles as $pageFile) {
            $pageKey = strtolower((string) pathinfo($pageFile->getFilename(), PATHINFO_FILENAME));
            $pageRule = $rulesByPage[$pageKey] ?? null;
            if (! is_array($pageRule)) {
                continue;
            }

            $blueprint = $this->readJsonFile($pageFile->getPathname());
            if (! is_array($blueprint)) {
                continue;
            }

            $components = collect((array) ($blueprint['components'] ?? []))
                ->map(fn ($value): string => trim((string) $value))
                ->filter(fn (string $value): bool => $value !== '')
                ->values()
                ->all();

            $pageTemplateFile = trim((string) ($blueprint['file'] ?? ''));
            $pageTemplateContents = '';
            if ($pageTemplateFile !== '' && ! str_contains($pageTemplateFile, '..')) {
                $absolute = $sourceRoot.'/'.ltrim(str_replace('\\', '/', $pageTemplateFile), '/');
                if (File::exists($absolute)) {
                    $pageTemplateContents = (string) File::get($absolute);
                }
            }

            $requiredMarker = is_string($pageRule['marker'] ?? null) ? (string) $pageRule['marker'] : null;
            $markerFoundInComponents = false;
            $markerFoundInPageTemplate = false;
            if ($requiredMarker) {
                $markerFoundInComponents = collect($components)
                    ->contains(function (string $componentName) use ($componentHtmlByName, $requiredMarker): bool {
                        $contents = $componentHtmlByName[$componentName] ?? '';

                        return is_string($contents) && $contents !== '' && str_contains($contents, $requiredMarker);
                    });
                $markerFoundInPageTemplate = $pageTemplateContents !== '' && str_contains($pageTemplateContents, $requiredMarker);
            }

            $pageTokensAll = array_values(array_filter(
                array_map(fn ($value): string => trim((string) $value), (array) ($pageRule['page_tokens_all'] ?? [])),
                fn (string $value): bool => $value !== ''
            ));
            $pageTokensAny = array_values(array_filter(
                array_map(fn ($value): string => trim((string) $value), (array) ($pageRule['page_tokens_any'] ?? [])),
                fn (string $value): bool => $value !== ''
            ));

            $pageTemplateHasAllTokens = $pageTokensAll === []
                || collect($pageTokensAll)->every(fn (string $token): bool => str_contains($pageTemplateContents, $token));
            $pageTemplateHasAnyToken = $pageTokensAny === []
                || collect($pageTokensAny)->contains(fn (string $token): bool => str_contains($pageTemplateContents, $token));

            $pageTemplateAnchorsSatisfied = ($pageTokensAll !== [] || $pageTokensAny !== [])
                ? ($pageTemplateHasAllTokens && $pageTemplateHasAnyToken)
                : false;

            if ($markerFoundInComponents || $markerFoundInPageTemplate || $pageTemplateAnchorsSatisfied) {
                continue;
            }

            $warningTemplate = (string) ($pageRule['warning'] ?? 'Page blueprint (%s) is missing required ecommerce/runtime bindings.');
            $warnings[] = sprintf($warningTemplate, $pageFile->getFilename());
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, string>
     */
    private function resolveComponentHtmlTemplates(string $sourceRoot, array $manifest): array
    {
        $result = [];

        $manifestComponents = is_array($manifest['components'] ?? null) ? $manifest['components'] : [];
        foreach ($manifestComponents as $component) {
            if (! is_array($component)) {
                continue;
            }

            $name = trim((string) ($component['name'] ?? ''));
            $htmlPath = trim((string) ($component['html'] ?? ''));
            if ($name === '' || $htmlPath === '' || str_contains($htmlPath, '..')) {
                continue;
            }

            $absolute = $sourceRoot.'/'.ltrim(str_replace('\\', '/', $htmlPath), '/');
            if (! File::exists($absolute)) {
                continue;
            }

            $result[$name] = (string) File::get($absolute);
        }

        if ($result !== []) {
            return $result;
        }

        $componentDirs = File::isDirectory($sourceRoot.'/components')
            ? collect(File::directories($sourceRoot.'/components'))->take(50)
            : collect();

        foreach ($componentDirs as $dir) {
            $name = basename((string) $dir);
            $htmlPath = rtrim((string) $dir, '/').'/component.html';
            if (! File::exists($htmlPath)) {
                continue;
            }

            $result[$name] = (string) File::get($htmlPath);
        }

        return $result;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function readJsonFile(string $path): ?array
    {
        if (! File::exists($path)) {
            return null;
        }

        $raw = File::get($path);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeDatasetFileName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $file = basename(str_replace('\\', '/', trim($value)));
        if ($file === '') {
            return null;
        }

        if (preg_match('/^[a-z0-9._-]+$/i', $file) !== 1) {
            return null;
        }

        return $file;
    }
}
