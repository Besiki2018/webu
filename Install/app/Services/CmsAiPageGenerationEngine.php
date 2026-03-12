<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsAiPageGenerationEngine
{
    public function __construct(
        protected CmsAiSchemaValidationService $schemaValidator
    ) {}

    /**
     * Deterministically derive schema-compatible AI page output fragments from AI input v1.
     *
     * Produces `pages_output` entries that can be embedded directly under
     * `cms-ai-generation-output.v1` (`pages` key), while keeping persistence and runtime
     * conversion in the existing page revision/content flow (no parallel storage).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generateFromAiInput(array $input): array
    {
        $inputValidation = $this->schemaValidator->validateInputPayload($input);
        if (! ($inputValidation['valid'] ?? false)) {
            return [
                'ok' => false,
                'code' => 'invalid_ai_input',
                'errors' => is_array($inputValidation['errors'] ?? null) ? $inputValidation['errors'] : [],
                'warnings' => [],
                'validation' => [
                    'input' => $this->compactValidationReport($inputValidation),
                ],
                'pages_output' => [],
            ];
        }

        $request = is_array($input['request'] ?? null) ? $input['request'] : [];
        $platformContext = is_array($input['platform_context'] ?? null) ? $input['platform_context'] : [];
        $mode = strtolower(trim((string) ($request['mode'] ?? 'generate_site')));

        if ($mode === 'generate_theme') {
            return [
                'ok' => true,
                'warnings' => ['request.mode=generate_theme; page generation engine returned no page fragments.'],
                'pages_output' => [],
                'decisions' => [
                    'mode' => $mode,
                    'requested_page_slugs' => [],
                    'resolved_page_slugs' => [],
                    'page_strategy' => 'no_pages_for_theme_mode',
                ],
                'validation' => [
                    'input' => $this->compactValidationReport($inputValidation),
                    'output_pages' => [
                        'valid' => true,
                        'schema' => 'docs/architecture/schemas/cms-ai-generation-output.v1.schema.json',
                        'error_count' => 0,
                    ],
                    'canonical_nodes' => [
                        'valid' => true,
                        'error_count' => 0,
                        'errors' => [],
                    ],
                ],
            ];
        }

        $templateBlueprint = is_array($platformContext['template_blueprint'] ?? null) ? $platformContext['template_blueprint'] : [];
        $templateDefaultPages = $this->normalizeTemplateDefaultPages($templateBlueprint['default_pages'] ?? []);
        $templateDefaultSections = $this->normalizeTemplateDefaultSections($templateBlueprint['default_sections'] ?? []);
        $pagesSnapshot = $this->normalizePagesSnapshot($platformContext['pages_snapshot'] ?? []);
        $sectionLibrary = $this->normalizeSectionLibrary($platformContext['section_library'] ?? []);

        $pagePlan = $this->resolvePagePlan(
            request: $request,
            platformContext: $platformContext,
            mode: $mode,
            templateDefaultPages: $templateDefaultPages,
            pagesSnapshot: $pagesSnapshot,
        );

        $warnings = $pagePlan['warnings'];
        $pagesOutput = [];

        foreach ($pagePlan['pages'] as $pageIndex => $pageDescriptor) {
            $built = $this->buildPageOutput(
                pageDescriptor: $pageDescriptor,
                pageIndex: $pageIndex,
                request: $request,
                templateDefaultSections: $templateDefaultSections,
                sectionLibrary: $sectionLibrary,
            );

            $warnings = array_merge($warnings, $built['warnings']);
            $pagesOutput[] = $built['page'];
        }

        $canonicalNodeValidation = $this->validateGeneratedCanonicalNodes($pagesOutput);
        $outputValidation = $this->schemaValidator->validateOutputPayload($this->minimalOutputEnvelope($pagesOutput));

        $errors = [];
        if (! ($outputValidation['valid'] ?? false)) {
            $errors = array_merge($errors, is_array($outputValidation['errors'] ?? null) ? $outputValidation['errors'] : []);
        }
        if (! ($canonicalNodeValidation['valid'] ?? false)) {
            $errors = array_merge($errors, $canonicalNodeValidation['errors']);
        }

        return [
            'ok' => $errors === [],
            'warnings' => array_values(array_unique(array_filter($warnings, fn ($value) => is_string($value) && trim($value) !== ''))),
            'errors' => $errors,
            'pages_output' => $pagesOutput,
            'decisions' => [
                'mode' => $mode,
                'requested_page_slugs' => $pagePlan['requested_page_slugs'],
                'resolved_page_slugs' => array_values(array_map(fn (array $page): string => (string) ($page['slug'] ?? ''), $pagesOutput)),
                'page_strategy' => $pagePlan['strategy'],
                'ecommerce_signal' => $pagePlan['ecommerce_signal'],
            ],
            'validation' => [
                'input' => $this->compactValidationReport($inputValidation),
                'output_pages' => $this->compactValidationReport($outputValidation),
                'canonical_nodes' => $canonicalNodeValidation,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $platformContext
     * @param  array<int, array<string, mixed>>  $templateDefaultPages
     * @param  array<string, array<string, mixed>>  $pagesSnapshot
     * @return array{
     *   pages: array<int, array<string, mixed>>,
     *   warnings: array<int, string>,
     *   strategy: string,
     *   requested_page_slugs: array<int, string>,
     *   ecommerce_signal: bool
     * }
     */
    private function resolvePagePlan(
        array $request,
        array $platformContext,
        string $mode,
        array $templateDefaultPages,
        array $pagesSnapshot,
    ): array {
        $warnings = [];
        $requestedSlugs = $this->normalizeTargetPageSlugs(data_get($request, 'target.page_slugs', []));
        $ecommerceSignal = $this->hasEcommerceSignal($request, $platformContext);
        $preserveExistingPages = (bool) data_get($request, 'constraints.preserve_existing_pages', false);

        $templatePagesBySlug = [];
        foreach ($templateDefaultPages as $page) {
            $slug = (string) ($page['slug'] ?? '');
            if ($slug !== '') {
                $templatePagesBySlug[$slug] = $page;
            }
        }

        $descriptors = [];
        $strategy = 'inferred';

        if ($requestedSlugs !== []) {
            $strategy = 'target_page_slugs';
            foreach ($requestedSlugs as $slug) {
                $templatePage = $templatePagesBySlug[$slug] ?? null;
                $snapshotPage = $pagesSnapshot[$slug] ?? null;
                $descriptors[] = $this->buildPageDescriptor(
                    slug: $slug,
                    title: $this->firstNonEmptyString(
                        is_array($templatePage) ? ($templatePage['title'] ?? null) : null,
                        is_array($snapshotPage) ? ($snapshotPage['title'] ?? null) : null,
                        Str::headline(str_replace('-', ' ', $slug))
                    ),
                    templateSections: is_array($templatePage) ? ($templatePage['sections'] ?? []) : [],
                    existingSnapshot: $snapshotPage,
                    source: $snapshotPage !== null ? 'keep_existing' : (is_array($templatePage) ? 'template_derived' : 'generated'),
                    preserveExisting: $preserveExistingPages,
                );
            }

            return [
                'pages' => $descriptors,
                'warnings' => $warnings,
                'strategy' => $strategy,
                'requested_page_slugs' => $requestedSlugs,
                'ecommerce_signal' => $ecommerceSignal,
            ];
        }

        if ($mode === 'edit_page') {
            $strategy = 'edit_page_fallback';
            $fallbackSlug = array_key_first($pagesSnapshot) ?? 'home';
            $warnings[] = 'edit_page request had no target.page_slugs; fell back to first page snapshot or home.';
            $snapshotPage = $pagesSnapshot[$fallbackSlug] ?? null;
            $templatePage = $templatePagesBySlug[$fallbackSlug] ?? null;

            $descriptors[] = $this->buildPageDescriptor(
                slug: $fallbackSlug,
                title: $this->firstNonEmptyString(
                    is_array($snapshotPage) ? ($snapshotPage['title'] ?? null) : null,
                    is_array($templatePage) ? ($templatePage['title'] ?? null) : null,
                    Str::headline(str_replace('-', ' ', $fallbackSlug))
                ),
                templateSections: is_array($templatePage) ? ($templatePage['sections'] ?? []) : [],
                existingSnapshot: $snapshotPage,
                source: $snapshotPage !== null ? 'keep_existing' : 'generated',
                preserveExisting: true,
            );

            return [
                'pages' => $descriptors,
                'warnings' => $warnings,
                'strategy' => $strategy,
                'requested_page_slugs' => [],
                'ecommerce_signal' => $ecommerceSignal,
            ];
        }

        if ($templateDefaultPages !== [] && in_array($mode, ['generate_site', 'edit_site'], true)) {
            $strategy = 'template_default_pages';
            foreach ($templateDefaultPages as $page) {
                $slug = (string) ($page['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $snapshotPage = $pagesSnapshot[$slug] ?? null;
                $descriptors[] = $this->buildPageDescriptor(
                    slug: $slug,
                    title: $this->firstNonEmptyString($page['title'] ?? null, Str::headline(str_replace('-', ' ', $slug))),
                    templateSections: $page['sections'] ?? [],
                    existingSnapshot: $snapshotPage,
                    source: $snapshotPage !== null ? 'template_derived' : 'template_derived',
                    preserveExisting: $preserveExistingPages
                );
            }

            if ($ecommerceSignal && $mode === 'generate_site') {
                foreach ($this->ecommerceCorePageSlugs() as $coreSlug) {
                    if (collect($descriptors)->contains(fn (array $page): bool => ($page['slug'] ?? null) === $coreSlug)) {
                        continue;
                    }
                    $snapshotPage = $pagesSnapshot[$coreSlug] ?? null;
                    $descriptors[] = $this->buildPageDescriptor(
                        slug: $coreSlug,
                        title: Str::headline(str_replace('-', ' ', $coreSlug)),
                        templateSections: [],
                        existingSnapshot: $snapshotPage,
                        source: $snapshotPage !== null ? 'generated' : 'generated',
                        preserveExisting: $preserveExistingPages
                    );
                    $warnings[] = "Template default_pages did not include required ecommerce page [{$coreSlug}]; added generated fallback page.";
                }
            }

            return [
                'pages' => $descriptors,
                'warnings' => $warnings,
                'strategy' => $strategy,
                'requested_page_slugs' => [],
                'ecommerce_signal' => $ecommerceSignal,
            ];
        }

        $strategy = 'heuristic_inference';
        $inferredSlugs = $ecommerceSignal ? $this->ecommerceCorePageSlugs() : ['home', 'about', 'contact'];
        if ($mode === 'generate_pages') {
            $inferredSlugs = $this->inferPageSlugsFromPrompt((string) ($request['prompt'] ?? ''), $ecommerceSignal);
        }

        foreach ($inferredSlugs as $slug) {
            $snapshotPage = $pagesSnapshot[$slug] ?? null;
            $descriptors[] = $this->buildPageDescriptor(
                slug: $slug,
                title: $this->firstNonEmptyString(
                    is_array($snapshotPage) ? ($snapshotPage['title'] ?? null) : null,
                    Str::headline(str_replace('-', ' ', $slug))
                ),
                templateSections: [],
                existingSnapshot: $snapshotPage,
                source: $snapshotPage !== null ? 'generated' : 'generated',
                preserveExisting: $preserveExistingPages
            );
        }

        return [
            'pages' => $descriptors,
            'warnings' => $warnings,
            'strategy' => $strategy,
            'requested_page_slugs' => [],
            'ecommerce_signal' => $ecommerceSignal,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $existingSnapshot
     * @param  array<int, string>  $templateSections
     * @return array<string, mixed>
     */
    private function buildPageDescriptor(
        string $slug,
        string $title,
        array $templateSections,
        ?array $existingSnapshot,
        string $source,
        bool $preserveExisting,
    ): array {
        return [
            'slug' => $slug,
            'title' => $title,
            'template_sections' => array_values(array_filter($templateSections, fn ($value) => is_string($value) && trim($value) !== '')),
            'existing_snapshot' => $existingSnapshot,
            'source' => ($preserveExisting && $existingSnapshot !== null) ? 'keep_existing' : $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $pageDescriptor
     * @param  array<string, array<int, array<string, mixed>>>  $templateDefaultSections
     * @param  array<string, array<string, mixed>>  $sectionLibrary
     * @return array{page: array<string, mixed>, warnings: array<int, string>}
     */
    private function buildPageOutput(
        array $pageDescriptor,
        int $pageIndex,
        array $request,
        array $templateDefaultSections,
        array $sectionLibrary,
    ): array {
        $warnings = [];
        $slug = (string) ($pageDescriptor['slug'] ?? 'page-'.($pageIndex + 1));
        $title = trim((string) ($pageDescriptor['title'] ?? Str::headline($slug)));
        if ($title === '') {
            $title = Str::headline($slug);
        }

        $route = $this->routeProfileForSlug($slug);

        $templateSectionsForPage = is_array($templateDefaultSections[$slug] ?? null)
            ? $templateDefaultSections[$slug]
            : [];

        $requestedSectionAliases = $this->resolveRequestedSectionAliasesForPage(
            slug: $slug,
            templateSectionRows: $templateSectionsForPage,
            templateSectionKeys: is_array($pageDescriptor['template_sections'] ?? null) ? $pageDescriptor['template_sections'] : []
        );

        $builderNodes = [];
        foreach ($requestedSectionAliases as $sectionIndex => $alias) {
            $resolved = $this->resolveSectionAliasToLibraryKey($alias, $slug, $sectionLibrary);
            if (($resolved['matched'] ?? false) === false) {
                $warnings[] = "No section_library match for alias [{$alias}] on page [{$slug}]; using alias as fallback type.";
            }

            $sectionRow = $this->findTemplateSectionRow($templateSectionsForPage, $alias);
            $builderNodes[] = $this->buildCanonicalPageNode(
                pageSlug: $slug,
                pageTitle: $title,
                sectionIndex: $sectionIndex,
                sectionAlias: $alias,
                sectionType: (string) ($resolved['key'] ?? $alias),
                sectionSchema: is_array($resolved['schema'] ?? null) ? $resolved['schema'] : [],
                templateSectionRow: $sectionRow,
                prompt: (string) ($request['prompt'] ?? '')
            );
        }

        if ($builderNodes === []) {
            $fallbackResolved = $this->resolveSectionAliasToLibraryKey('hero', $slug, $sectionLibrary);
            $builderNodes[] = $this->buildCanonicalPageNode(
                pageSlug: $slug,
                pageTitle: $title,
                sectionIndex: 0,
                sectionAlias: 'hero',
                sectionType: (string) ($fallbackResolved['key'] ?? 'hero'),
                sectionSchema: is_array($fallbackResolved['schema'] ?? null) ? $fallbackResolved['schema'] : [],
                templateSectionRow: null,
                prompt: (string) ($request['prompt'] ?? '')
            );
            $warnings[] = "No sections resolved for page [{$slug}]; inserted fallback hero node.";
        }

        $seoTitle = $title;
        $seoDescription = $this->truncateSentence($this->seoDescriptionForPage($slug, $title, (string) ($request['prompt'] ?? '')), 160);

        return [
            'warnings' => $warnings,
            'page' => [
                'slug' => $slug,
                'title' => $title,
                'path' => $route['path'],
                'status' => 'draft',
                'template_key' => $route['template_key'],
                'route_pattern' => $route['route_pattern'],
                'builder_nodes' => $builderNodes,
                'page_css' => '',
                'seo' => [
                    'seo_title' => $seoTitle,
                    'seo_description' => $seoDescription,
                ],
                'meta' => [
                    'required_page' => (bool) $route['required_page'],
                    'source' => in_array(($pageDescriptor['source'] ?? null), ['generated', 'template_derived', 'keep_existing'], true)
                        ? $pageDescriptor['source']
                        : 'generated',
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $templateSectionRows
     * @param  array<int, string>  $templateSectionKeys
     * @return array<int, string>
     */
    private function resolveRequestedSectionAliasesForPage(string $slug, array $templateSectionRows, array $templateSectionKeys): array
    {
        $aliases = [];

        foreach ($templateSectionRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $enabled = array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true;
            if (! $enabled) {
                continue;
            }

            $key = $this->normalizeSlugKey($row['key'] ?? null);
            if ($key !== null) {
                $aliases[] = $key;
            }
        }

        foreach ($templateSectionKeys as $key) {
            $normalized = $this->normalizeSlugKey($key);
            if ($normalized !== null) {
                $aliases[] = $normalized;
            }
        }

        if ($aliases !== []) {
            return array_values(array_unique($aliases));
        }

        return $this->fallbackSectionAliasesForPageSlug($slug);
    }

    /**
     * @param  array<int, array<string, mixed>>  $templateSectionRows
     * @return array<string, mixed>|null
     */
    private function findTemplateSectionRow(array $templateSectionRows, string $alias): ?array
    {
        $alias = strtolower(trim($alias));
        foreach ($templateSectionRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowKey = strtolower(trim((string) ($row['key'] ?? '')));
            if ($rowKey === $alias) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $sectionLibrary
     * @return array{key:string,matched:bool,schema:array<string,mixed>}
     */
    private function resolveSectionAliasToLibraryKey(string $alias, string $pageSlug, array $sectionLibrary): array
    {
        $alias = strtolower(trim($alias));
        $pageSlug = strtolower(trim($pageSlug));

        if ($alias === '') {
            return ['key' => 'hero', 'matched' => false, 'schema' => []];
        }

        $candidates = [$alias];
        foreach ($this->sectionAliasCandidates($alias, $pageSlug) as $candidate) {
            $normalizedCandidate = $this->normalizeSlugKey($candidate);
            if ($normalizedCandidate === null) {
                continue;
            }

            if (! in_array($normalizedCandidate, $candidates, true)) {
                $candidates[] = $normalizedCandidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (isset($sectionLibrary[$candidate])) {
                return [
                    'key' => $candidate,
                    'matched' => true,
                    'schema' => is_array($sectionLibrary[$candidate]['schema'] ?? null) ? $sectionLibrary[$candidate]['schema'] : [],
                ];
            }
        }

        foreach ($sectionLibrary as $libraryKey => $entry) {
            $normalizedKey = strtolower((string) $libraryKey);
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && str_contains($normalizedKey, $candidate)) {
                    return [
                        'key' => $libraryKey,
                        'matched' => true,
                        'schema' => is_array($entry['schema'] ?? null) ? $entry['schema'] : [],
                    ];
                }
            }
        }

        return [
            'key' => $alias,
            'matched' => false,
            'schema' => [],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sectionAliasCandidates(string $alias, string $pageSlug): array
    {
        $map = [
            'hero' => ['hero_split_image', 'hero_banner', 'hero_section'],
            'services' => ['services_grid', 'service_cards', 'features_grid'],
            'features' => ['features_grid', 'service_cards'],
            'products' => ['ecommerce_product_grid', 'product_grid', 'featured_products'],
            'shop' => ['ecommerce_product_grid', 'product_grid'],
            'product' => ['ecommerce_product_detail', 'ecommerce_product_detail_01', 'product_detail'],
            'cart' => ['ecommerce_cart', 'webu_ecom_cart_01', 'cart_page'],
            'checkout' => ['ecommerce_checkout', 'webu_ecom_checkout_form_01', 'checkout_form'],
            'account' => ['ecommerce_account', 'auth_account', 'account_dashboard'],
            'login' => ['auth_login_register', 'login_register', 'auth_form'],
            'orders' => ['ecommerce_orders', 'account_orders', 'orders_list'],
            'contact' => ['contact_split_form', 'contact_form', 'contact'],
            'faq' => ['faq', 'faq_list'],
        ];

        $candidates = $map[$alias] ?? [];

        if ($pageSlug === 'product' || str_contains($pageSlug, 'product')) {
            $candidates[] = 'ecommerce_product_detail';
            $candidates[] = 'product_detail';
        }
        if ($pageSlug === 'shop') {
            $candidates[] = 'ecommerce_product_grid';
        }

        return array_values(array_unique(array_filter($candidates, fn ($value) => is_string($value) && $value !== '')));
    }

    /**
     * @param  array<string, mixed>  $sectionSchema
     * @param  array<string, mixed>|null  $templateSectionRow
     * @return array<string, mixed>
     */
    private function buildCanonicalPageNode(
        string $pageSlug,
        string $pageTitle,
        int $sectionIndex,
        string $sectionAlias,
        string $sectionType,
        array $sectionSchema,
        ?array $templateSectionRow,
        string $prompt,
    ): array {
        $templateProps = is_array($templateSectionRow['props'] ?? null) ? $this->filterScalarTree($templateSectionRow['props']) : [];
        $contentProps = $templateProps !== []
            ? $templateProps
            : $this->defaultContentPropsForSection($pageSlug, $pageTitle, $sectionAlias, $sectionIndex, $prompt);

        $bindings = is_array($sectionSchema['bindings'] ?? null)
            ? $this->filterScalarTree($sectionSchema['bindings'])
            : [];
        $bindings = $this->normalizeCanonicalBindings($bindings);

        // Common ecommerce dynamic route placeholders for product detail pages.
        if ($this->isProductPageSlug($pageSlug)) {
            if (! isset($bindings['product_slug'])) {
                $bindings['product_slug'] = '{{route.params.slug}}';
            }
            if (! isset($contentProps['product_slug'])) {
                $contentProps['product_slug'] = '{{route.params.slug}}';
            }
        }

        return [
            'id' => sprintf('%s_%02d', Str::slug($pageSlug, '_'), $sectionIndex + 1),
            'type' => $sectionType,
            'props' => [
                'content' => $contentProps,
                'data' => [],
                'style' => [],
                'advanced' => [],
                'responsive' => [],
                'states' => [],
            ],
            'bindings' => $bindings,
            'meta' => [
                'schema_version' => 1,
                'source' => 'ai_generation',
                'label' => Str::headline(str_replace(['_', '-'], ' ', $sectionAlias)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultContentPropsForSection(
        string $pageSlug,
        string $pageTitle,
        string $sectionAlias,
        int $sectionIndex,
        string $prompt,
    ): array {
        $shortPrompt = $this->truncateSentence(trim($prompt), 100);
        $pageHeadline = $pageTitle !== '' ? $pageTitle : Str::headline($pageSlug);

        return match (true) {
            $sectionIndex === 0 && in_array($sectionAlias, ['hero', 'hero_split_image', 'hero_banner'], true) => [
                'headline' => $pageHeadline,
                'subtitle' => $shortPrompt !== '' ? $shortPrompt : "Generated {$pageHeadline} section",
                'primary_cta' => [
                    'label' => $this->primaryCtaLabelForPage($pageSlug),
                    'url' => $this->primaryCtaUrlForPage($pageSlug),
                ],
            ],
            str_contains($sectionAlias, 'product') || $sectionAlias === 'shop' => [
                'title' => $pageHeadline,
                'collection' => $pageSlug === 'shop' ? 'all-products' : 'featured',
            ],
            str_contains($sectionAlias, 'contact') => [
                'title' => 'Contact Us',
                'subtitle' => 'Send us a message and we will respond soon.',
            ],
            $sectionAlias === 'faq' => [
                'title' => 'Frequently Asked Questions',
            ],
            default => [
                'title' => $pageHeadline,
                'description' => "Generated section for {$pageHeadline}",
            ],
        };
    }

    /**
     * @return array{path:string,route_pattern:?string,template_key:?string,required_page:bool}
     */
    private function routeProfileForSlug(string $slug): array
    {
        $slug = strtolower(trim($slug));

        $profiles = [
            'home' => ['path' => '/', 'route_pattern' => null, 'required_page' => true],
            'shop' => ['path' => '/shop', 'route_pattern' => null, 'required_page' => true],
            'products' => ['path' => '/shop', 'route_pattern' => null, 'required_page' => true],
            'product' => ['path' => '/product/{slug}', 'route_pattern' => '/product/{slug}', 'required_page' => true],
            'product-detail' => ['path' => '/product/{slug}', 'route_pattern' => '/product/{slug}', 'required_page' => true],
            'cart' => ['path' => '/cart', 'route_pattern' => null, 'required_page' => true],
            'checkout' => ['path' => '/checkout', 'route_pattern' => null, 'required_page' => true],
            'login' => ['path' => '/account/login', 'route_pattern' => null, 'required_page' => false],
            'login-register' => ['path' => '/account/login', 'route_pattern' => null, 'required_page' => false],
            'account' => ['path' => '/account', 'route_pattern' => null, 'required_page' => false],
            'orders' => ['path' => '/account/orders', 'route_pattern' => null, 'required_page' => false],
            'order-detail' => ['path' => '/account/orders/{id}', 'route_pattern' => '/account/orders/{id}', 'required_page' => false],
            'contact' => ['path' => '/contact', 'route_pattern' => null, 'required_page' => false],
            'about' => ['path' => '/about', 'route_pattern' => null, 'required_page' => false],
        ];

        $profile = $profiles[$slug] ?? null;
        if ($profile === null) {
            $path = '/'.trim($slug, '/');
            if ($path === '/') {
                $path = '/home';
            }

            return [
                'path' => $path,
                'route_pattern' => null,
                'template_key' => null,
                'required_page' => false,
            ];
        }

        return [
            'path' => $profile['path'],
            'route_pattern' => $profile['route_pattern'],
            'template_key' => null,
            'required_page' => (bool) $profile['required_page'],
        ];
    }

    private function seoDescriptionForPage(string $slug, string $title, string $prompt): string
    {
        $title = trim($title);
        $prompt = trim($prompt);

        if ($prompt !== '') {
            return "{$title} page generated from AI prompt: {$prompt}";
        }

        return "Generated {$slug} page for {$title}.";
    }

    private function primaryCtaLabelForPage(string $pageSlug): string
    {
        return match (strtolower(trim($pageSlug))) {
            'home', 'shop' => 'Shop Now',
            'product', 'product-detail' => 'Add to Cart',
            'contact' => 'Contact Us',
            default => 'Learn More',
        };
    }

    private function primaryCtaUrlForPage(string $pageSlug): string
    {
        return match (strtolower(trim($pageSlug))) {
            'home' => '/shop',
            'shop' => '/shop',
            'product', 'product-detail' => '/cart',
            'contact' => '/contact',
            default => '/',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array{valid: bool, error_count: int, errors: array<int, array<string, mixed>>}
     */
    private function validateGeneratedCanonicalNodes(array $pages): array
    {
        $errors = [];

        foreach ($pages as $pageIndex => $page) {
            $nodes = is_array($page['builder_nodes'] ?? null) ? $page['builder_nodes'] : [];
            foreach ($nodes as $nodeIndex => $node) {
                $path = '$.pages['.$pageIndex.'].builder_nodes['.$nodeIndex.']';
                if (! is_array($node)) {
                    $errors[] = $this->engineError('invalid_type', $path, 'Canonical page node must be an object.', 'object', gettype($node));
                    continue;
                }

                foreach (['type', 'props', 'bindings', 'meta'] as $requiredKey) {
                    if (! array_key_exists($requiredKey, $node)) {
                        $errors[] = $this->engineError('missing_required_key', $path.'.'.$requiredKey, 'Missing canonical page node key.', $requiredKey, 'missing');
                    }
                }

                $props = is_array($node['props'] ?? null) ? $node['props'] : [];
                foreach (['content', 'data', 'style', 'advanced', 'responsive', 'states'] as $groupKey) {
                    if (! array_key_exists($groupKey, $props) || ! is_array($props[$groupKey])) {
                        $errors[] = $this->engineError(
                            'invalid_props_group',
                            $path.'.props.'.$groupKey,
                            'Canonical page node props group is required and must be object.',
                            'object',
                            is_array($props[$groupKey] ?? null) ? 'array' : gettype($props[$groupKey] ?? null)
                        );
                    }
                }

                if (! is_array($node['meta'] ?? null) || ! is_int($node['meta']['schema_version'] ?? null) || (int) $node['meta']['schema_version'] < 1) {
                    $errors[] = $this->engineError(
                        'invalid_meta_schema_version',
                        $path.'.meta.schema_version',
                        'Canonical page node meta.schema_version must be integer >= 1.',
                        'integer >= 1',
                        $node['meta']['schema_version'] ?? null
                    );
                }
            }
        }

        return [
            'valid' => $errors === [],
            'error_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function compactValidationReport(array $report): array
    {
        return [
            'valid' => (bool) ($report['valid'] ?? false),
            'schema' => $report['schema'] ?? null,
            'error_count' => (int) ($report['error_count'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pagesOutput
     * @return array<string, mixed>
     */
    private function minimalOutputEnvelope(array $pagesOutput): array
    {
        return [
            'schema_version' => 1,
            'theme' => [
                'theme_settings_patch' => [],
            ],
            'pages' => $pagesOutput,
            'header' => [
                'enabled' => true,
                'section_type' => null,
                'props' => [],
            ],
            'footer' => [
                'enabled' => true,
                'section_type' => null,
                'props' => [],
            ],
            'meta' => [
                'generator' => [
                    'kind' => 'ai',
                    'version' => 'v1',
                ],
                'created_at' => now()->toIso8601String(),
                'contracts' => [
                    'ai_input_schema' => 'docs/architecture/schemas/cms-ai-generation-input.v1.schema.json',
                    'canonical_page_node_schema' => 'docs/architecture/schemas/cms-canonical-page-node.v1.schema.json',
                    'canonical_component_registry_schema' => 'docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json',
                ],
                'validation_expectations' => [
                    'strict_top_level' => true,
                    'no_parallel_storage' => true,
                    'builder_native_pages' => true,
                    'component_availability_check_required' => true,
                    'binding_validation_required' => true,
                ],
            ],
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTemplateDefaultPages(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $pages = [];
        foreach ($value as $page) {
            if (! is_array($page)) {
                continue;
            }

            $slug = $this->normalizeSlugKey($page['slug'] ?? null);
            if ($slug === null) {
                continue;
            }

            $title = trim((string) ($page['title'] ?? Str::headline($slug)));
            $sections = [];
            foreach ((array) ($page['sections'] ?? []) as $section) {
                $sectionKey = is_array($section) ? ($section['key'] ?? $section['type'] ?? null) : $section;
                $normalizedSection = $this->normalizeSlugKey($sectionKey);
                if ($normalizedSection !== null) {
                    $sections[] = $normalizedSection;
                }
            }

            $pages[] = [
                'slug' => $slug,
                'title' => $title !== '' ? $title : Str::headline($slug),
                'sections' => array_values(array_unique($sections)),
            ];
        }

        return $pages;
    }

    /**
     * @param  mixed  $value
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function normalizeTemplateDefaultSections(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $slug => $rows) {
            $pageSlug = $this->normalizeSlugKey($slug);
            if ($pageSlug === null || ! is_array($rows)) {
                continue;
            }

            $normalizedRows = [];
            foreach ($rows as $row) {
                if (is_string($row)) {
                    $key = $this->normalizeSlugKey($row);
                    if ($key === null) {
                        continue;
                    }
                    $normalizedRows[] = [
                        'key' => $key,
                        'enabled' => true,
                        'props' => [],
                    ];
                    continue;
                }

                if (! is_array($row)) {
                    continue;
                }

                $key = $this->normalizeSlugKey($row['key'] ?? $row['type'] ?? null);
                if ($key === null) {
                    continue;
                }

                $normalizedRows[] = [
                    'key' => $key,
                    'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,
                    'props' => is_array($row['props'] ?? null) ? $this->filterScalarTree($row['props']) : [],
                ];
            }

            if ($normalizedRows !== []) {
                $normalized[$pageSlug] = $normalizedRows;
            }
        }

        return $normalized;
    }

    /**
     * @param  mixed  $value
     * @return array<string, array<string, mixed>>
     */
    private function normalizePagesSnapshot(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $pages = [];
        foreach ($value as $page) {
            if (! is_array($page)) {
                continue;
            }

            $slug = $this->normalizeSlugKey($page['slug'] ?? null);
            if ($slug === null) {
                continue;
            }

            $pages[$slug] = [
                'slug' => $slug,
                'title' => trim((string) ($page['title'] ?? Str::headline($slug))),
                'status' => trim((string) ($page['status'] ?? 'draft')),
            ];
        }

        return $pages;
    }

    /**
     * @param  mixed  $value
     * @return array<string, array<string, mixed>>
     */
    private function normalizeSectionLibrary(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $library = [];
        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $enabled = (bool) ($entry['enabled'] ?? false);
            if (! $enabled) {
                continue;
            }

            $key = $this->normalizeSlugKey($entry['key'] ?? null);
            if ($key === null) {
                continue;
            }

            $library[$key] = [
                'key' => $key,
                'category' => is_string($entry['category'] ?? null) ? $entry['category'] : null,
                'schema' => is_array($entry['schema_json'] ?? null) ? $entry['schema_json'] : [],
            ];
        }

        return $library;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeTargetPageSlugs(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $slugs = [];
        foreach ($value as $slug) {
            $normalized = $this->normalizeSlugKey($slug);
            if ($normalized !== null) {
                $slugs[] = $normalized;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @return array<int, string>
     */
    private function inferPageSlugsFromPrompt(string $prompt, bool $ecommerceSignal): array
    {
        $haystack = strtolower($prompt);
        $slugs = [];

        if ($ecommerceSignal) {
            $slugs[] = 'home';
            if ($this->containsAnyKeyword($haystack, ['shop', 'catalog', 'collection', 'listing'])) {
                $slugs[] = 'shop';
            }
            if ($this->containsAnyKeyword($haystack, ['product', 'detail', 'sku'])) {
                $slugs[] = 'product';
            }
            if ($this->containsAnyKeyword($haystack, ['cart'])) {
                $slugs[] = 'cart';
            }
            if ($this->containsAnyKeyword($haystack, ['checkout', 'payment'])) {
                $slugs[] = 'checkout';
            }
            if ($this->containsAnyKeyword($haystack, ['account', 'login', 'orders'])) {
                $slugs[] = 'account';
            }
        } else {
            $slugs[] = 'home';
            if ($this->containsAnyKeyword($haystack, ['about', 'story', 'team'])) {
                $slugs[] = 'about';
            }
            if ($this->containsAnyKeyword($haystack, ['services', 'service'])) {
                $slugs[] = 'services';
            }
            if ($this->containsAnyKeyword($haystack, ['contact', 'book', 'appointment'])) {
                $slugs[] = 'contact';
            }
        }

        if ($slugs === []) {
            $slugs = $ecommerceSignal ? ['home', 'shop', 'product'] : ['home', 'about', 'contact'];
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @return array<int, string>
     */
    private function ecommerceCorePageSlugs(): array
    {
        return ['home', 'shop', 'product', 'cart', 'checkout'];
    }

    /**
     * @return array<int, string>
     */
    private function fallbackSectionAliasesForPageSlug(string $slug): array
    {
        return match (strtolower(trim($slug))) {
            'home' => ['hero', 'products', 'faq'],
            'shop', 'products' => ['products'],
            'product', 'product-detail' => ['product'],
            'cart' => ['cart'],
            'checkout' => ['checkout'],
            'account', 'login', 'login-register' => ['login'],
            'orders', 'order-detail' => ['orders'],
            'contact' => ['contact'],
            default => ['hero'],
        };
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $platformContext
     */
    private function hasEcommerceSignal(array $request, array $platformContext): bool
    {
        if ((bool) data_get($request, 'constraints.allow_ecommerce', false)) {
            return true;
        }

        $modules = data_get($platformContext, 'module_registry.modules');
        if (is_array($modules)) {
            foreach ($modules as $module) {
                if (! is_array($module)) {
                    continue;
                }
                $key = strtolower(trim((string) ($module['key'] ?? '')));
                if ($key === 'ecommerce' && ((bool) ($module['enabled'] ?? false) || (bool) ($module['available'] ?? false))) {
                    return true;
                }
            }
        }

        $entitlements = data_get($platformContext, 'module_entitlements.modules');
        if (is_array($entitlements) && (bool) ($entitlements['ecommerce'] ?? false)) {
            return true;
        }

        $haystack = strtolower(trim((string) ($request['prompt'] ?? '')));
        return $this->containsAnyKeyword($haystack, ['ecommerce', 'e-commerce', 'shop', 'store', 'cart', 'checkout', 'product']);
    }

    private function isProductPageSlug(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        return in_array($slug, ['product', 'product-detail'], true) || str_contains($slug, 'product');
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @return array<string, mixed>
     */
    private function normalizeCanonicalBindings(array $bindings): array
    {
        $normalized = [];

        foreach ($bindings as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                $normalized[$key] = $this->normalizeCanonicalBindings($value);
                continue;
            }

            if (! is_string($value)) {
                $normalized[$key] = $value;
                continue;
            }

            $candidate = trim($value);
            if ($candidate !== '' && ! str_contains($candidate, '{{') && preg_match('/^(project|site|page|route|menu|global|customer|ecommerce|booking|content|system)\.[A-Za-z0-9_.\\[\\]-]+$/', $candidate) === 1) {
                $normalized[$key] = '{{'.$candidate.'}}';
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function filterScalarTree(array $input): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->filterScalarTree($value);
                if ($nested !== []) {
                    $result[$key] = $nested;
                }

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function normalizeSlugKey(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = strtolower($raw);
        if ($normalized === 'index') {
            return 'home';
        }

        // keep dynamic placeholders if present, but normalize common separators
        $normalized = str_replace([' ', '_'], '-', $normalized);
        $normalized = preg_replace('/[^a-z0-9{}\/-]+/', '-', $normalized) ?? $normalized;
        $normalized = trim((string) $normalized, '-');
        $normalized = str_replace('/', '-', $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function containsAnyKeyword(string $haystack, array $keywords): bool
    {
        if ($haystack === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            $needle = strtolower(trim((string) $keyword));
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function firstNonEmptyString(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $candidate = trim((string) $value);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function truncateSentence(string $value, int $limit): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(0, $limit - 1))).'…';
    }

    /**
     * @return array<string, mixed>
     */
    private function engineError(string $code, string $path, string $message, mixed $expected, mixed $actual): array
    {
        return [
            'code' => $code,
            'path' => $path,
            'message' => $message,
            'expected' => $expected,
            'actual' => is_scalar($actual) || $actual === null ? $actual : get_debug_type($actual),
        ];
    }
}
