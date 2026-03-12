<?php

namespace App\Services;

use App\Support\OwnedTemplateCatalog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CmsAiPageGenerationService
{
    public const ENGINE_VERSION = 1;

    public const REPRODUCIBILITY_VERSION = 'p6-g3-02.v1';

    public const REPRODUCIBILITY_SCHEMA_VERSION = 1;

    public const REPRODUCIBILITY_CANONICAL_JSON_VERSION = 1;

    public const REPRODUCIBILITY_FINGERPRINT_ALGORITHM = 'sha256';

    public function __construct(
        protected CmsAiSchemaValidationService $schemaValidator,
        protected CmsAiThemeGenerationService $themeGeneration,
        protected CmsAiComponentPlacementStylingRulesService $placementStylingRules,
        protected CmsAiIndustryComponentMappingService $industryComponentMapping,
        protected CmsAiLearnedRuleApplicationService $learnedRuleApplication,
        protected CmsAiLearningPrivacyPolicyService $learningPrivacyPolicy,
        protected ReadyTemplatesService $readyTemplates,
    ) {}

    /**
     * Generate AI-output-compatible page artifacts (`pages[]`) using builder-native canonical nodes.
     *
     * @param  array<string, mixed>  $aiInput
     * @return array<string, mixed>
     */
    public function generatePagesFragment(array $aiInput): array
    {
        $traceStartedAtMs = (int) round(microtime(true) * 1000);

        $inputValidation = $this->schemaValidator->validateInputPayload($aiInput);
        if (! (bool) ($inputValidation['valid'] ?? false)) {
            return $this->finalizeGenerationResultWithTrace($aiInput, $traceStartedAtMs, [
                'valid' => false,
                'engine' => $this->engineMeta(),
                'errors' => $inputValidation['errors'] ?? [],
                'warnings' => [],
                'input_validation' => $inputValidation,
                'page_plan' => null,
                'pages' => null,
            ]);
        }

        $mode = (string) data_get($aiInput, 'request.mode', 'generate_site');
        $targetPageSlugs = $this->normalizeSlugList(data_get($aiInput, 'request.target.page_slugs'));
        $constraints = is_array(data_get($aiInput, 'request.constraints')) ? data_get($aiInput, 'request.constraints') : [];
        $preserveExistingPages = (bool) ($constraints['preserve_existing_pages'] ?? false);
        $maxNewPages = $this->normalizeNonNegativeInt($constraints['max_new_pages'] ?? null);
        $locale = (string) data_get($aiInput, 'request.locale', 'en');

        $themeFragment = $this->themeGeneration->generateThemeFragment($aiInput);
        $templateChoiceSlug = $this->normalizeNullableString(data_get($themeFragment, 'template_choice.slug'))
            ?? $this->normalizeNullableString(data_get($aiInput, 'platform_context.template_blueprint.template_slug'));
        $warnings = [];
        $industryMapping = $this->industryComponentMapping->mapFromAiInput($aiInput);
        $industryMappingAliasSummary = $this->buildIndustryMappingAliasSummary($industryMapping);
        $learningPrivacyPolicy = $this->learningPrivacyPolicy->resolveForAiInput($aiInput);
        $siteFamily = $this->inferSiteFamily($aiInput, $templateChoiceSlug);
        $mappedIndustryFamily = $this->normalizeNullableString(data_get($industryMapping, 'industry_family'));
        if ($mappedIndustryFamily !== null) {
            $siteFamily = $mappedIndustryFamily;
            if (! in_array($mappedIndustryFamily, ['ecommerce', 'blog', 'business'], true)) {
                $warnings[] = "AI industry mapping resolved [{$mappedIndustryFamily}] component groups; page generation uses business-page fallback while preserving industry component hints.";
            }
        }

        if ($mode === 'generate_theme') {
            return $this->finalizeGenerationResultWithTrace($aiInput, $traceStartedAtMs, [
                'valid' => true,
                'engine' => $this->engineMeta(),
                'errors' => [],
                'warnings' => ['request.mode=generate_theme; page generation skipped.'],
                'input_validation' => ['valid' => true, 'error_count' => 0],
                'page_plan' => [
                    'site_family' => $siteFamily,
                    'ai_industry_component_mapping' => $industryMapping,
                    'component_library_spec_aliases' => $industryMappingAliasSummary,
                    'template_choice_slug' => $templateChoiceSlug,
                    'requested_page_slugs' => $targetPageSlugs,
                    'generated_page_slugs' => [],
                    'reason' => 'theme_only_request',
                ],
                'pages' => [],
            ]);
        }

        if ($preserveExistingPages && $targetPageSlugs === []) {
            return $this->finalizeGenerationResultWithTrace($aiInput, $traceStartedAtMs, [
                'valid' => true,
                'engine' => $this->engineMeta(),
                'errors' => [],
                'warnings' => ['preserve_existing_pages=true with no target page_slugs; no pages generated.'],
                'input_validation' => ['valid' => true, 'error_count' => 0],
                'page_plan' => [
                    'site_family' => $siteFamily,
                    'ai_industry_component_mapping' => $industryMapping,
                    'component_library_spec_aliases' => $industryMappingAliasSummary,
                    'template_choice_slug' => $templateChoiceSlug,
                    'requested_page_slugs' => [],
                    'generated_page_slugs' => [],
                    'reason' => 'preserve_existing_pages',
                ],
                'pages' => [],
            ]);
        }

        $catalog = $this->pageCatalogForFamily($siteFamily);
        $selectedDefinitions = $this->selectPageDefinitions($catalog, $targetPageSlugs, $maxNewPages, $mode);

        if ($targetPageSlugs !== [] && count($selectedDefinitions) < count($targetPageSlugs)) {
            $missing = array_values(array_diff($targetPageSlugs, array_column($selectedDefinitions, 'slug')));
            if ($missing !== []) {
                $warnings[] = 'Some requested page slugs were not recognized and were skipped: '.implode(', ', $missing);
            }
        }

        if ($maxNewPages !== null && count($selectedDefinitions) >= $maxNewPages && count($catalog) > $maxNewPages) {
            $warnings[] = 'max_new_pages constraint applied; page generation output was truncated.';
        }

        $templateData = [];
        if ($templateChoiceSlug !== null && $templateChoiceSlug !== '' && OwnedTemplateCatalog::contains($templateChoiceSlug)) {
            $templateData = $this->readyTemplates->loadBySlug($templateChoiceSlug);
        }

        $pages = [];
        foreach ($selectedDefinitions as $definition) {
            $templatePage = null;
            if ($templateData !== [] && isset($templateData['default_pages']) && is_array($templateData['default_pages'])) {
                foreach ($templateData['default_pages'] as $tp) {
                    if (! is_array($tp)) {
                        continue;
                    }
                    if ((string) ($tp['slug'] ?? '') === (string) ($definition['slug'] ?? '')) {
                        $templatePage = $tp;
                        break;
                    }
                }
            }
            $pages[] = $this->buildPageOutput($definition, $aiInput, $locale, $templatePage);
        }

        $rulesResult = $this->placementStylingRules->applyRules($pages, [
            'site_family' => $siteFamily,
            'template_choice_slug' => $templateChoiceSlug,
            'mode' => $mode,
        ]);
        $pages = is_array($rulesResult['pages'] ?? null) ? $rulesResult['pages'] : $pages;
        foreach ((array) ($rulesResult['warnings'] ?? []) as $ruleWarning) {
            if (is_string($ruleWarning) && trim($ruleWarning) !== '') {
                $warnings[] = $ruleWarning;
            }
        }

        $learnedRulesResult = $this->learnedRuleApplication->applyToGeneratedPages($pages, $aiInput, [
            'site_family' => $siteFamily,
            'template_choice_slug' => $templateChoiceSlug,
            'mode' => $mode,
            'privacy_policy' => $learningPrivacyPolicy,
        ]);
        $pages = is_array($learnedRulesResult['pages'] ?? null) ? $learnedRulesResult['pages'] : $pages;
        foreach ((array) ($learnedRulesResult['warnings'] ?? []) as $learningWarning) {
            if (is_string($learningWarning) && trim($learningWarning) !== '') {
                $warnings[] = $learningWarning;
            }
        }

        $reproducibility = $this->buildGenerationReproducibilityMetadata(
            $pages,
            $aiInput,
            [
                'site_family' => $siteFamily,
                'template_choice_slug' => $templateChoiceSlug,
                'mode' => $mode,
                'placement_rules_diagnostics' => is_array($rulesResult['diagnostics'] ?? null) ? $rulesResult['diagnostics'] : [],
                'learned_rules_diagnostics' => is_array($learnedRulesResult['diagnostics'] ?? null) ? $learnedRulesResult['diagnostics'] : [],
                'privacy_policy' => $learningPrivacyPolicy,
                'industry_mapping_aliases' => $industryMappingAliasSummary,
            ]
        );
        $pages = is_array($reproducibility['pages'] ?? null) ? $reproducibility['pages'] : $pages;
        $reproducibilitySummary = is_array($reproducibility['summary'] ?? null) ? $reproducibility['summary'] : null;

        return $this->finalizeGenerationResultWithTrace($aiInput, $traceStartedAtMs, [
            'valid' => true,
            'engine' => $this->engineMeta(),
            'errors' => [],
            'warnings' => $warnings,
            'input_validation' => [
                'valid' => true,
                'error_count' => 0,
            ],
            'page_plan' => [
                'site_family' => $siteFamily,
                'ai_industry_component_mapping' => $industryMapping,
                'component_library_spec_aliases' => $industryMappingAliasSummary,
                'template_choice_slug' => $templateChoiceSlug,
                'requested_page_slugs' => $targetPageSlugs,
                'generated_page_slugs' => array_values(array_map(fn (array $page): string => (string) $page['slug'], $pages)),
                'mode' => $mode,
                'max_new_pages' => $maxNewPages,
                'placement_styling_rules' => is_array($rulesResult['diagnostics'] ?? null) ? $rulesResult['diagnostics'] : null,
                'learning_generation_version' => data_get($learnedRulesResult, 'diagnostics.generation_version'),
                'applied_rules' => is_array(data_get($learnedRulesResult, 'diagnostics.applied_rules')) ? data_get($learnedRulesResult, 'diagnostics.applied_rules') : [],
                'learned_rules_application' => is_array($learnedRulesResult['diagnostics'] ?? null) ? $learnedRulesResult['diagnostics'] : null,
                'learning_privacy' => is_array($learningPrivacyPolicy['diagnostics'] ?? null) ? $learningPrivacyPolicy['diagnostics'] : null,
                'reproducibility' => $reproducibilitySummary,
            ],
            'pages' => $pages,
        ]);
    }

    /**
     * @return array{kind:string,version:int}
     */
    private function engineMeta(): array
    {
        return [
            'kind' => 'rule_based_page_generation',
            'version' => self::ENGINE_VERSION,
        ];
    }

    /**
     * @param  array<string, mixed>  $aiInput
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function finalizeGenerationResultWithTrace(array $aiInput, int $traceStartedAtMs, array $result): array
    {
        $nowMs = (int) round(microtime(true) * 1000);
        $durationMs = max(0, $nowMs - $traceStartedAtMs);
        $requestId = $this->normalizeNullableString(data_get($aiInput, 'meta.request_id'));
        $traceId = $requestId !== null
            ? 'ai-gen-'.$requestId
            : 'ai-gen-'.substr(sha1(json_encode([
                'started_at_ms' => $traceStartedAtMs,
                'prompt' => (string) data_get($aiInput, 'request.prompt', ''),
                'mode' => (string) data_get($aiInput, 'request.mode', 'generate_site'),
            ])), 0, 24);

        Log::info('cms.ai.generation.trace', [
            'trace_id' => substr($traceId, 0, 120),
            'flow' => 'ai_generation',
            'step' => 'page_generation',
            'status' => (bool) ($result['valid'] ?? false) ? 'ok' : 'error',
            'duration_ms' => $durationMs,
            'request_id' => $requestId,
            'mode' => (string) data_get($aiInput, 'request.mode', 'generate_site'),
            'project_id' => $this->normalizeNullableString(data_get($aiInput, 'platform_context.project.id')),
            'site_id' => $this->normalizeNullableString(data_get($aiInput, 'platform_context.site.id')),
            'page_count' => is_array($result['pages'] ?? null) ? count((array) $result['pages']) : 0,
            'warning_count' => is_array($result['warnings'] ?? null) ? count((array) $result['warnings']) : 0,
            'error_count' => is_array($result['errors'] ?? null) ? count((array) $result['errors']) : 0,
            'engine' => data_get($result, 'engine.kind'),
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $aiInput
     */
    private function inferSiteFamily(array $aiInput, ?string $templateChoiceSlug): string
    {
        $prompt = Str::lower((string) data_get($aiInput, 'request.prompt', ''));

        if (
            (bool) data_get($aiInput, 'request.constraints.allow_ecommerce', false)
            || $this->modulesContainSignal($aiInput, ['ecommerce', 'shop', 'storefront'])
            || $this->containsAny($prompt, ['ecommerce', 'shop', 'store', 'checkout', 'cart', 'product'])
            || ($templateChoiceSlug !== null && str_contains($templateChoiceSlug, 'shop'))
        ) {
            return 'ecommerce';
        }

        if ($this->containsAny($prompt, ['blog', 'magazine', 'news', 'article'])) {
            return 'blog';
        }

        return 'business';
    }

    /**
     * @param  array<string, mixed>  $aiInput
     * @param  array<string>  $signals
     */
    private function modulesContainSignal(array $aiInput, array $signals): bool
    {
        $modules = data_get($aiInput, 'platform_context.module_registry.modules');
        if (! is_array($modules)) {
            return false;
        }

        $haystackParts = [];
        foreach ($modules as $module) {
            if (! is_array($module)) {
                continue;
            }
            foreach (['key', 'slug', 'name'] as $field) {
                $value = $this->normalizeNullableString($module[$field] ?? null);
                if ($value !== null) {
                    $haystackParts[] = $value;
                }
            }
        }

        return $this->containsAny(Str::lower(implode(' ', $haystackParts)), $signals);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pageCatalogForFamily(string $family): array
    {
        if ($family === 'ecommerce') {
            return [
                $this->pageDefinition('home', 'Home', '/', 'home', null, true),
                $this->pageDefinition('shop', 'Shop', '/shop', 'product-listing', null, true),
                $this->pageDefinition('product', 'Product Detail', '/product/:slug', 'product-detail', '/product/:slug', true),
                $this->pageDefinition('cart', 'Cart', '/cart', 'cart', null, true),
                $this->pageDefinition('checkout', 'Checkout', '/checkout', 'checkout', null, true),
                $this->pageDefinition('login', 'Login / Register', '/account/login', 'login-register', null, true),
                $this->pageDefinition('account', 'My Account', '/account', 'account', null, true),
                $this->pageDefinition('orders', 'Orders', '/account/orders', 'orders-list', '/account/orders', true),
                $this->pageDefinition('order', 'Order Detail', '/account/orders/:id', 'order-detail', '/account/orders/:id', true),
                $this->pageDefinition('contact', 'Contact', '/contact', 'contact', null, false),
            ];
        }

        if ($family === 'blog') {
            return [
                $this->pageDefinition('home', 'Home', '/', 'home', null, true),
                $this->pageDefinition('blog', 'Blog', '/blog', 'listing', null, true),
                $this->pageDefinition('post', 'Post Detail', '/blog/:slug', 'detail', '/blog/:slug', true),
                $this->pageDefinition('contact', 'Contact', '/contact', 'contact', null, false),
            ];
        }

        return [
            $this->pageDefinition('home', 'Home', '/', 'home', null, true),
            $this->pageDefinition('about', 'About', '/about', 'about', null, false),
            $this->pageDefinition('services', 'Services', '/services', 'services', null, false),
            $this->pageDefinition('contact', 'Contact', '/contact', 'contact', null, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageDefinition(string $slug, string $title, string $path, ?string $templateKey, ?string $routePattern, bool $required): array
    {
        return [
            'slug' => $slug,
            'title' => $title,
            'path' => $path,
            'template_key' => $templateKey,
            'route_pattern' => $routePattern,
            'required_page' => $required,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalog
     * @param  array<int, string>  $targetPageSlugs
     * @return array<int, array<string, mixed>>
     */
    private function selectPageDefinitions(array $catalog, array $targetPageSlugs, ?int $maxNewPages, string $mode): array
    {
        $selected = $catalog;

        if ($targetPageSlugs !== []) {
            $selected = array_values(array_filter(
                $catalog,
                fn (array $def): bool => in_array((string) $def['slug'], $targetPageSlugs, true)
            ));
        } elseif ($mode === 'edit_page') {
            $selected = array_values(array_filter(
                $catalog,
                fn (array $def): bool => (string) $def['slug'] === 'home'
            ));
        }

        if ($maxNewPages !== null) {
            $selected = array_slice($selected, 0, $maxNewPages);
        }

        return $selected;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $aiInput
     * @param  array{slug: string, title?: string, sections?: array<int, array{key: string, props?: array}>}|null  $templatePage
     * @return array<string, mixed>
     */
    private function buildPageOutput(array $definition, array $aiInput, string $locale, ?array $templatePage = null): array
    {
        $brandName = $this->resolveBrandName($aiInput);
        $slug = (string) $definition['slug'];
        $title = (string) $definition['title'];
        $path = (string) $definition['path'];
        $templateKey = $definition['template_key'] ?? null;
        $routePattern = $definition['route_pattern'] ?? null;

        $sections = is_array($templatePage['sections'] ?? null) ? $templatePage['sections'] : [];
        $builderNodes = $sections !== []
            ? $this->templateSectionsToBuilderNodes($sections)
            : $this->builderNodesForPageSlug($slug, $aiInput);

        return [
            'slug' => $slug,
            'title' => $this->localizeTitle((string) ($templatePage['title'] ?? $title), $locale),
            'path' => $path,
            'status' => 'draft',
            'template_key' => is_string($templateKey) ? $templateKey : null,
            'route_pattern' => is_string($routePattern) ? $routePattern : null,
            'builder_nodes' => $builderNodes,
            'page_css' => $this->pageCssForPageSlug($slug),
            'seo' => [
                'seo_title' => $this->seoTitleForPage($title, $brandName),
                'seo_description' => $this->seoDescriptionForPage($title, $brandName),
            ],
            'meta' => [
                'required_page' => (bool) ($definition['required_page'] ?? false),
                'source' => $sections !== [] ? 'template' : 'generated',
            ],
        ];
    }

    /**
     * Convert template pack sections (key + props) to canonical builder_nodes for CmsAiOutputSaveEngine.
     *
     * @param  array<int, array{key: string, props?: array<string, mixed>}>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function templateSectionsToBuilderNodes(array $sections): array
    {
        $nodes = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $key = trim((string) ($section['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $props = is_array($section['props'] ?? null) ? $section['props'] : [];
            $nodes[] = [
                'type' => $key,
                'props' => [
                    'content' => [],
                    'data' => $props,
                    'style' => [],
                    'advanced' => [],
                    'responsive' => [],
                    'states' => [],
                ],
                'bindings' => [],
                'meta' => ['label' => $this->humanizeSectionKey($key)],
                'children' => [],
            ];
        }
        return $nodes;
    }

    private function humanizeSectionKey(string $key): string
    {
        $label = str_replace(['webu_ecom_', 'webu_general_', '_01'], ['', '', ''], $key);
        $label = trim(preg_replace('/_+/', ' ', $label) ?? $key);

        return $label === '' ? $key : ucwords($label);
    }

    /**
     * @param  array<string, mixed>  $aiInput
     * @return array<int, array<string, mixed>>
     */
    private function builderNodesForPageSlug(string $slug, array $aiInput): array
    {
        $brandName = $this->resolveBrandName($aiInput);

        return match ($slug) {
            'home' => [
                $this->sectionNode(
                    'Hero',
                    [
                        $this->headingNode("Welcome to {$brandName}"),
                        $this->textNode('Start with a clean, builder-editable storefront layout generated from AI input.'),
                        $this->buttonNode('Shop Now', '/shop'),
                    ],
                    ['variant' => 'hero']
                ),
                $this->sectionNode(
                    'Featured Products',
                    [
                        $this->productsGridNode('Featured products'),
                    ],
                    ['variant' => 'catalog']
                ),
            ],
            'shop' => [
                $this->sectionNode(
                    'Shop Catalog',
                    [
                        $this->headingNode('Browse Products'),
                        $this->productsGridNode('All products'),
                    ]
                ),
            ],
            'product' => [
                $this->sectionNode(
                    'Product Detail',
                    [
                        $this->productDetailNode(),
                        $this->buttonNode('View Cart', '/cart'),
                    ]
                ),
            ],
            'cart' => [
                $this->sectionNode(
                    'Cart',
                    [
                        $this->headingNode('Your Cart'),
                        $this->cartSummaryNode(),
                        $this->buttonNode('Proceed to Checkout', '/checkout'),
                    ]
                ),
            ],
            'checkout' => [
                $this->sectionNode(
                    'Checkout',
                    [
                        $this->checkoutFormNode(),
                        $this->orderSummaryNode(),
                    ]
                ),
            ],
            'login' => [
                $this->sectionNode(
                    'Auth',
                    [
                        $this->authNode(),
                    ]
                ),
            ],
            'account' => [
                $this->sectionNode(
                    'Account Dashboard',
                    [
                        $this->accountDashboardNode(),
                    ]
                ),
            ],
            'orders' => [
                $this->sectionNode(
                    'Orders List',
                    [
                        $this->ordersListNode(),
                    ]
                ),
            ],
            'order' => [
                $this->sectionNode(
                    'Order Detail',
                    [
                        $this->orderDetailNode(),
                    ]
                ),
            ],
            'contact' => [
                $this->sectionNode(
                    'Contact',
                    [
                        $this->headingNode('Contact Us'),
                        $this->textNode("We'll get back to you as soon as possible."),
                        $this->contactFormNode(),
                    ]
                ),
            ],
            'about' => [
                $this->sectionNode(
                    'About',
                    [
                        $this->headingNode("About {$brandName}"),
                        $this->textNode('Add your story, mission, and team details here.'),
                    ]
                ),
            ],
            'services' => [
                $this->sectionNode(
                    'Services',
                    [
                        $this->headingNode('Services'),
                        $this->textNode('Describe services with editable cards and CTAs.'),
                    ]
                ),
            ],
            'blog' => [
                $this->sectionNode(
                    'Blog Listing',
                    [
                        $this->headingNode('Latest Articles'),
                        $this->postsListNode(),
                    ]
                ),
            ],
            'post' => [
                $this->sectionNode(
                    'Post Detail',
                    [
                        $this->postDetailNode(),
                    ]
                ),
            ],
            default => [
                $this->sectionNode(
                    Str::headline($slug),
                    [
                        $this->headingNode(Str::headline($slug)),
                        $this->textNode('Builder-editable page generated from AI page engine.'),
                    ]
                ),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $styleContent
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function sectionNode(string $label, array $children = [], array $styleContent = []): array
    {
        return $this->node(
            type: 'section',
            content: ['label' => $label],
            data: [],
            style: ['layout' => 'stack'] + $styleContent,
            advanced: [],
            responsive: [],
            states: [],
            bindings: [],
            meta: ['label' => $label],
            children: $children,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function headingNode(string $text): array
    {
        return $this->node(
            type: 'heading',
            content: ['text' => $text, 'level' => 'h2'],
            data: [],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [],
            meta: ['label' => 'Heading']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function textNode(string $text): array
    {
        return $this->node(
            type: 'text',
            content: ['text' => $text],
            data: [],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [],
            meta: ['label' => 'Text']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buttonNode(string $label, string $url): array
    {
        return $this->node(
            type: 'button',
            content: ['label' => $label],
            data: ['url' => $url],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [
                'props.data.url' => $url,
            ],
            meta: ['label' => 'Button']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function productsGridNode(string $label): array
    {
        return $this->node(
            type: 'products-grid',
            content: ['title' => $label],
            data: ['page_size' => 12],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [
                'props.data.items' => '{{public.ecommerce.products.items}}',
                'props.data.pagination' => '{{public.ecommerce.products.pagination}}',
            ],
            meta: ['label' => 'Products Grid']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function productDetailNode(): array
    {
        return $this->node(
            type: 'product-detail',
            content: ['title' => 'Product'],
            data: ['slug' => null],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [
                'props.data.slug' => '{{route.params.slug}}',
                'props.data.product' => '{{public.ecommerce.product}}',
            ],
            meta: ['label' => 'Product Detail']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cartSummaryNode(): array
    {
        return $this->node(
            type: 'cart-summary',
            content: ['title' => 'Cart Summary'],
            data: [],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [
                'props.data.cart' => '{{public.ecommerce.cart}}',
            ],
            meta: ['label' => 'Cart Summary']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutFormNode(): array
    {
        return $this->node(
            type: 'checkout-form',
            content: ['title' => 'Checkout'],
            data: [],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [
                'props.data.checkout' => '{{public.ecommerce.checkout}}',
            ],
            meta: ['label' => 'Checkout Form']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function orderSummaryNode(): array
    {
        return $this->node(
            type: 'order-summary',
            content: ['title' => 'Order Summary'],
            data: [],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [
                'props.data.cart' => '{{public.ecommerce.cart}}',
            ],
            meta: ['label' => 'Order Summary']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function authNode(): array
    {
        return $this->node(
            type: 'auth',
            content: ['mode' => 'login_register'],
            data: [],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [],
            meta: ['label' => 'Auth']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function accountDashboardNode(): array
    {
        return $this->node(
            type: 'account-dashboard',
            content: ['title' => 'Account Dashboard'],
            data: [],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [
                'props.data.account' => '{{public.ecommerce.account}}',
            ],
            meta: ['label' => 'Account Dashboard']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ordersListNode(): array
    {
        return $this->node(
            type: 'orders-list',
            content: ['title' => 'Orders'],
            data: [],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [
                'props.data.orders' => '{{public.ecommerce.orders}}',
                'props.data.pagination' => '{{public.ecommerce.orders.pagination}}',
            ],
            meta: ['label' => 'Orders List']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function orderDetailNode(): array
    {
        return $this->node(
            type: 'order-detail',
            content: ['title' => 'Order'],
            data: ['id' => null],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [
                'props.data.id' => '{{route.params.id}}',
                'props.data.order' => '{{public.ecommerce.order}}',
            ],
            meta: ['label' => 'Order Detail']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function contactFormNode(): array
    {
        return $this->node(
            type: 'form',
            content: ['title' => 'Contact Form'],
            data: ['form_type' => 'contact'],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [],
            meta: ['label' => 'Form']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function postsListNode(): array
    {
        return $this->node(
            type: 'posts-list',
            content: ['title' => 'Posts'],
            data: [],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [],
            meta: ['label' => 'Posts List']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function postDetailNode(): array
    {
        return $this->node(
            type: 'post-detail',
            content: ['title' => 'Post'],
            data: ['slug' => null],
            style: [],
            advanced: [],
            responsive: [],
            states: [],
            bindings: [
                'props.data.slug' => '{{route.params.slug}}',
            ],
            meta: ['label' => 'Post Detail']
        );
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $style
     * @param  array<string, mixed>  $advanced
     * @param  array<string, mixed>  $responsive
     * @param  array<string, mixed>  $states
     * @param  array<string, mixed>  $bindings
     * @param  array<string, mixed>  $meta
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function node(
        string $type,
        array $content,
        array $data,
        array $style,
        array $advanced,
        array $responsive,
        array $states,
        array $bindings,
        array $meta = [],
        array $children = [],
    ): array {
        $node = [
            'type' => $type,
            'props' => [
                'content' => $content,
                'data' => $data,
                'style' => $style,
                'advanced' => $advanced,
                'responsive' => $responsive,
                'states' => $states,
            ],
            'bindings' => $bindings,
            'meta' => array_merge([
                'schema_version' => 1,
                'source' => 'generated',
            ], $meta),
        ];

        if ($children !== []) {
            $node['children'] = $children;
        }

        return $node;
    }

    private function pageCssForPageSlug(string $slug): string
    {
        return sprintf('/* AI page generation scaffold styles for %s */', $slug);
    }

    private function seoTitleForPage(string $title, string $brandName): string
    {
        return trim($title.' | '.$brandName);
    }

    private function seoDescriptionForPage(string $title, string $brandName): string
    {
        return sprintf('AI-generated builder-ready %s page for %s.', Str::lower($title), $brandName);
    }

    /**
     * @param  array<string, mixed>  $aiInput
     */
    private function resolveBrandName(array $aiInput): string
    {
        $candidates = [
            data_get($aiInput, 'request.user_context.business_name'),
            data_get($aiInput, 'platform_context.site.name'),
            data_get($aiInput, 'platform_context.project.name'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeNullableString($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return 'Webu Site';
    }

    private function localizeTitle(string $title, string $locale): string
    {
        $locale = Str::lower(trim($locale));
        if ($locale === 'ka') {
            return match ($title) {
                'Home' => 'მთავარი',
                'Shop' => 'მაღაზია',
                'Product Detail' => 'პროდუქტის დეტალი',
                'Cart' => 'კალათა',
                'Checkout' => 'გადახდა',
                'Login / Register' => 'შესვლა / რეგისტრაცია',
                'My Account' => 'ჩემი ანგარიში',
                'Orders' => 'შეკვეთები',
                'Order Detail' => 'შეკვეთის დეტალი',
                'Contact' => 'კონტაქტი',
                default => $title,
            };
        }

        return $title;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $aiInput
     * @param  array<string, mixed>  $context
     * @return array{pages: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function buildGenerationReproducibilityMetadata(array $pages, array $aiInput, array $context): array
    {
        $learnedRulesDiagnostics = is_array($context['learned_rules_diagnostics'] ?? null) ? $context['learned_rules_diagnostics'] : [];
        $placementDiagnostics = is_array($context['placement_rules_diagnostics'] ?? null) ? $context['placement_rules_diagnostics'] : [];
        $learnedRuleVersioning = is_array($learnedRulesDiagnostics['versioning'] ?? null) ? $learnedRulesDiagnostics['versioning'] : null;
        $privacyPolicy = is_array($context['privacy_policy'] ?? null) ? $context['privacy_policy'] : [];
        $industryMappingAliases = is_array($context['industry_mapping_aliases'] ?? null) ? $context['industry_mapping_aliases'] : [];

        if (! (bool) data_get($privacyPolicy, 'effective.emit_reproducibility', true)) {
            return [
                'pages' => $pages,
                'summary' => [
                    'schema_version' => self::REPRODUCIBILITY_SCHEMA_VERSION,
                    'version' => self::REPRODUCIBILITY_VERSION,
                    'enabled' => false,
                    'reason' => $this->normalizeNullableString(data_get($privacyPolicy, 'diagnostics.status')) ?? 'privacy_policy_disabled',
                    'policy' => is_array($privacyPolicy['diagnostics'] ?? null) ? $privacyPolicy['diagnostics'] : null,
                    'fingerprint_algorithm' => self::REPRODUCIBILITY_FINGERPRINT_ALGORITHM,
                    'canonical_json_version' => self::REPRODUCIBILITY_CANONICAL_JSON_VERSION,
                    'component_library_spec_aliases' => $industryMappingAliases,
                ],
            ];
        }

        $inputFingerprint = $this->reproducibilityFingerprint($this->buildReproducibilityInputSnapshot($aiInput, $context));

        $pagesWithoutReproducibility = [];
        $pageFingerprints = [];
        foreach (array_values($pages) as $index => $page) {
            if (! is_array($page)) {
                continue;
            }

            $normalizedPage = $this->stripPageReproducibilityForHash($page);
            $pagesWithoutReproducibility[] = $normalizedPage;
            $pageFingerprints[] = [
                'slug' => $this->normalizeNullableString($normalizedPage['slug'] ?? null) ?? 'unknown',
                'page_index' => $index,
                'page_fingerprint' => $this->reproducibilityFingerprint($normalizedPage),
            ];
        }

        $outputFingerprint = $this->reproducibilityFingerprint([
            'version' => self::REPRODUCIBILITY_VERSION,
            'engine' => [
                'page_generation' => self::ENGINE_VERSION,
                'placement_rules_version' => $placementDiagnostics['placement_rules_version'] ?? null,
                'styling_rules_version' => $placementDiagnostics['styling_rules_version'] ?? null,
                'learned_rule_generation_version' => $learnedRulesDiagnostics['generation_version'] ?? null,
            ],
            'context' => [
                'site_family' => $this->normalizeNullableString($context['site_family'] ?? null),
                'template_choice_slug' => $this->normalizeNullableString($context['template_choice_slug'] ?? null),
                'mode' => $this->normalizeNullableString($context['mode'] ?? null),
                'component_library_spec_aliases' => $industryMappingAliases,
            ],
            'input_fingerprint' => $inputFingerprint,
            'learned_rule_versioning' => $learnedRuleVersioning,
            'pages' => $pagesWithoutReproducibility,
        ]);

        $replayKey = 'cms-ai-replay:'.self::REPRODUCIBILITY_VERSION.':'.$this->reproducibilityFingerprint([
            'input' => $inputFingerprint,
            'output' => $outputFingerprint,
            'learned_rules' => [
                'eligible' => data_get($learnedRuleVersioning, 'eligible_rule_set_version'),
                'matched' => data_get($learnedRuleVersioning, 'matched_rule_set_version'),
                'applied' => data_get($learnedRuleVersioning, 'applied_rule_set_version'),
            ],
        ]);

        $appliedRuleEntries = array_values(array_filter(
            (array) ($learnedRulesDiagnostics['applied_rules'] ?? []),
            static fn ($item): bool => is_array($item)
        ));

        $pageFingerprintBySlug = [];
        foreach ($pageFingerprints as $pageFingerprint) {
            if (! is_array($pageFingerprint)) {
                continue;
            }
            $slug = $this->normalizeNullableString($pageFingerprint['slug'] ?? null);
            if ($slug === null) {
                continue;
            }
            $pageFingerprintBySlug[$slug] = $pageFingerprint;
        }

        $annotatedPages = [];
        foreach (array_values($pages) as $index => $page) {
            if (! is_array($page)) {
                continue;
            }

            $slug = $this->normalizeNullableString($page['slug'] ?? null) ?? 'unknown';
            $pageMeta = is_array($page['meta'] ?? null) ? $page['meta'] : [];
            $pageAppliedRuleKeys = [];
            foreach ($appliedRuleEntries as $appliedRule) {
                $appliedPages = array_values(array_filter((array) ($appliedRule['pages'] ?? []), 'is_string'));
                if (in_array($slug, $appliedPages, true)) {
                    $ruleKey = $this->normalizeNullableString($appliedRule['rule_key'] ?? null);
                    if ($ruleKey !== null) {
                        $pageAppliedRuleKeys[] = $ruleKey;
                    }
                }
            }

            $pageFingerprint = data_get($pageFingerprintBySlug, $slug.'.page_fingerprint');
            $pageMeta['reproducibility'] = [
                'schema_version' => self::REPRODUCIBILITY_SCHEMA_VERSION,
                'version' => self::REPRODUCIBILITY_VERSION,
                'enabled' => true,
                'fingerprint_algorithm' => self::REPRODUCIBILITY_FINGERPRINT_ALGORITHM,
                'canonical_json_version' => self::REPRODUCIBILITY_CANONICAL_JSON_VERSION,
                'page_index' => $index,
                'input_fingerprint' => $inputFingerprint,
                'output_fingerprint' => $outputFingerprint,
                'page_fingerprint' => is_string($pageFingerprint) ? $pageFingerprint : $this->reproducibilityFingerprint($this->stripPageReproducibilityForHash($page)),
                'replay_key' => $replayKey,
                'learned_rule_generation_version' => $learnedRulesDiagnostics['generation_version'] ?? null,
                'learned_rule_set_version' => data_get($learnedRuleVersioning, 'applied_rule_set_version'),
                'applied_rule_keys' => array_values(array_unique($pageAppliedRuleKeys)),
            ];

            $page['meta'] = $pageMeta;
            $annotatedPages[] = $page;
        }

        return [
            'pages' => $annotatedPages,
            'summary' => [
                'schema_version' => self::REPRODUCIBILITY_SCHEMA_VERSION,
                'version' => self::REPRODUCIBILITY_VERSION,
                'enabled' => true,
                'fingerprint_algorithm' => self::REPRODUCIBILITY_FINGERPRINT_ALGORITHM,
                'canonical_json_version' => self::REPRODUCIBILITY_CANONICAL_JSON_VERSION,
                'input_fingerprint' => $inputFingerprint,
                'output_fingerprint' => $outputFingerprint,
                'replay_key' => $replayKey,
                'page_fingerprints' => $pageFingerprints,
                'learned_rules' => [
                    'generation_version' => $learnedRulesDiagnostics['generation_version'] ?? null,
                    'versioning' => $learnedRuleVersioning,
                ],
                'component_library_spec_aliases' => $industryMappingAliases,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $aiInput
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildReproducibilityInputSnapshot(array $aiInput, array $context): array
    {
        $request = is_array($aiInput['request'] ?? null) ? $aiInput['request'] : [];

        return [
            'schema_version' => (int) ($aiInput['schema_version'] ?? 1),
            'request' => [
                'mode' => $this->normalizeNullableString($request['mode'] ?? null),
                'prompt' => $this->normalizeNullableString($request['prompt'] ?? null),
                'locale' => $this->normalizeNullableString($request['locale'] ?? null),
                'target' => is_array($request['target'] ?? null) ? $request['target'] : [],
                'constraints' => is_array($request['constraints'] ?? null) ? $request['constraints'] : [],
                'user_context' => is_array($request['user_context'] ?? null) ? $request['user_context'] : [],
            ],
            'platform_context' => [
                'project' => [
                    'id' => $this->normalizeNullableString(data_get($aiInput, 'platform_context.project.id')),
                ],
                'site' => [
                    'id' => $this->normalizeNullableString(data_get($aiInput, 'platform_context.site.id')),
                    'project_type' => $this->normalizeNullableString(data_get($aiInput, 'platform_context.site.theme_settings.project_type')),
                    'locale' => $this->normalizeNullableString(data_get($aiInput, 'platform_context.site.locale')),
                    'theme_preset' => $this->normalizeNullableString(data_get($aiInput, 'platform_context.site.theme_settings.preset')),
                ],
                'template_blueprint' => [
                    'template_slug' => $this->normalizeNullableString(data_get($aiInput, 'platform_context.template_blueprint.template_slug')),
                ],
                'module_registry' => [
                    'module_keys' => $this->normalizeModuleKeysForReproducibility($aiInput),
                ],
            ],
            'generation_context' => [
                'site_family' => $this->normalizeNullableString($context['site_family'] ?? null),
                'template_choice_slug' => $this->normalizeNullableString($context['template_choice_slug'] ?? null),
                'mode' => $this->normalizeNullableString($context['mode'] ?? null),
                'component_library_spec_aliases' => is_array($context['industry_mapping_aliases'] ?? null)
                    ? $context['industry_mapping_aliases']
                    : [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $industryMapping
     * @return array<string, mixed>
     */
    private function buildIndustryMappingAliasSummary(array $industryMapping): array
    {
        $builderMapping = is_array($industryMapping['builder_component_mapping'] ?? null)
            ? $industryMapping['builder_component_mapping']
            : [];

        $canonicalComponentKeys = [];
        foreach ((array) ($builderMapping['component_keys'] ?? []) as $key) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }
            $canonicalComponentKeys[] = trim($key);
        }
        $canonicalComponentKeys = array_values(array_unique($canonicalComponentKeys));

        $sourceSpecComponentKeys = [];
        foreach ((array) ($builderMapping['source_spec_component_keys'] ?? []) as $key) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }
            $sourceSpecComponentKeys[] = trim($key);
        }
        $sourceSpecComponentKeys = array_values(array_unique($sourceSpecComponentKeys));
        sort($sourceSpecComponentKeys);

        return [
            'industry_family' => $this->normalizeNullableString($industryMapping['industry_family'] ?? null),
            'decision_source' => $this->normalizeNullableString($industryMapping['decision_source'] ?? null),
            'canonical_component_key_count' => count($canonicalComponentKeys),
            'canonical_component_keys' => $canonicalComponentKeys,
            'source_spec_component_key_count' => count($sourceSpecComponentKeys),
            'source_spec_component_keys' => $sourceSpecComponentKeys,
            'alias_coverage' => is_array($builderMapping['source_spec_alias_coverage'] ?? null)
                ? $builderMapping['source_spec_alias_coverage']
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $aiInput
     * @return array<int, string>
     */
    private function normalizeModuleKeysForReproducibility(array $aiInput): array
    {
        $modules = data_get($aiInput, 'platform_context.module_registry.modules');
        if (! is_array($modules)) {
            return [];
        }

        $keys = [];
        foreach ($modules as $module) {
            if (! is_array($module)) {
                continue;
            }
            $key = $this->normalizeNullableString($module['key'] ?? null)
                ?? $this->normalizeNullableString($module['slug'] ?? null);
            if ($key === null) {
                continue;
            }
            $keys[] = Str::lower($key);
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private function stripPageReproducibilityForHash(array $page): array
    {
        $copy = $page;
        if (is_array($copy['meta'] ?? null) && array_key_exists('reproducibility', $copy['meta'])) {
            unset($copy['meta']['reproducibility']);
        }

        return $copy;
    }

    private function reproducibilityFingerprint(mixed $value): string
    {
        $canonical = $this->canonicalizeForReproducibilityHash($value);
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            $json = 'null';
        }

        return self::REPRODUCIBILITY_FINGERPRINT_ALGORITHM.':'.hash(self::REPRODUCIBILITY_FINGERPRINT_ALGORITHM, $json);
    }

    private function canonicalizeForReproducibilityHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $result = [];
            foreach ($value as $item) {
                $result[] = $this->canonicalizeForReproducibilityHash($item);
            }

            return $result;
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeForReproducibilityHash($item);
        }

        return $value;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeSlugList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $slug = Str::lower(trim((string) $item));
            if ($slug === '') {
                continue;
            }
            $result[] = $slug;
        }

        return array_values(array_unique($result));
    }

    private function normalizeNonNegativeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (is_numeric($value)) {
            $parsed = (int) $value;
            return $parsed >= 0 ? $parsed : null;
        }

        return null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        if ($haystack === '') {
            return false;
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }
}
