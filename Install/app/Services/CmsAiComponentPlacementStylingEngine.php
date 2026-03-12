<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsAiComponentPlacementStylingEngine
{
    public const RULESET_VERSION = 1;

    public function __construct(
        protected CmsAiSchemaValidationService $schemaValidator
    ) {}

    /**
     * Apply deterministic component placement and styling rules to AI-generated page fragments.
     *
     * @param  array<string, mixed>  $input
     * @param  array<int, array<string, mixed>>  $pagesOutput
     * @param  array<string, mixed>  $themeOutput
     * @return array<string, mixed>
     */
    public function applyToPagesOutput(array $input, array $pagesOutput, array $themeOutput = []): array
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

        if (! is_array($pagesOutput) || ! array_is_list($pagesOutput)) {
            return [
                'ok' => false,
                'code' => 'invalid_pages_output',
                'errors' => [[
                    'code' => 'invalid_type',
                    'path' => '$.pages_output',
                    'message' => 'pages_output must be a list of page fragments.',
                    'expected' => 'array',
                    'actual' => is_array($pagesOutput) ? 'object' : get_debug_type($pagesOutput),
                ]],
                'warnings' => [],
                'validation' => [
                    'input' => $this->compactValidationReport($inputValidation),
                ],
                'pages_output' => [],
            ];
        }

        $themeContext = $this->extractThemeContext($themeOutput);

        $warnings = [];
        $refinedPages = [];
        $pageRuleDecisions = [];

        foreach ($pagesOutput as $pageIndex => $page) {
            if (! is_array($page)) {
                $warnings[] = "Skipped non-object page fragment at index {$pageIndex}.";
                continue;
            }

            $refined = $this->applyRulesToPage($page, $pageIndex, $themeContext);
            $warnings = array_merge($warnings, $refined['warnings']);
            $refinedPages[] = $refined['page'];
            $pageRuleDecisions[] = $refined['decision'];
        }

        $canonicalNodesValidation = $this->validateCanonicalNodeScaffolds($refinedPages);
        $outputValidation = $this->schemaValidator->validateOutputPayload($this->minimalOutputEnvelope($refinedPages, $themeOutput));

        $errors = [];
        if (! ($outputValidation['valid'] ?? false)) {
            $errors = array_merge($errors, is_array($outputValidation['errors'] ?? null) ? $outputValidation['errors'] : []);
        }
        if (! ($canonicalNodesValidation['valid'] ?? false)) {
            $errors = array_merge($errors, $canonicalNodesValidation['errors']);
        }

        return [
            'ok' => $errors === [],
            'warnings' => array_values(array_unique(array_filter($warnings, fn ($value) => is_string($value) && trim($value) !== ''))),
            'errors' => $errors,
            'pages_output' => $refinedPages,
            'decisions' => [
                'ruleset_version' => self::RULESET_VERSION,
                'theme_context' => $themeContext,
                'page_rules' => $pageRuleDecisions,
            ],
            'validation' => [
                'input' => $this->compactValidationReport($inputValidation),
                'output_pages' => $this->compactValidationReport($outputValidation),
                'canonical_nodes' => $canonicalNodesValidation,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $themeContext
     * @return array{
     *   page: array<string, mixed>,
     *   warnings: array<int, string>,
     *   decision: array<string, mixed>
     * }
     */
    private function applyRulesToPage(array $page, int $pageIndex, array $themeContext): array
    {
        $warnings = [];
        $slug = strtolower(trim((string) ($page['slug'] ?? 'page-'.($pageIndex + 1))));
        $pageType = $this->resolvePageType($slug, (string) ($page['route_pattern'] ?? ''));
        $nodes = is_array($page['builder_nodes'] ?? null) && array_is_list($page['builder_nodes']) ? $page['builder_nodes'] : [];

        $rankedNodes = [];
        foreach ($nodes as $nodeIndex => $node) {
            if (! is_array($node)) {
                $warnings[] = "Skipped non-object node on page [{$slug}] at index {$nodeIndex}.";
                continue;
            }

            $rank = $this->rankNodeForPageType($node, $pageType);
            $rankedNodes[] = [
                'original_index' => $nodeIndex,
                'rank' => $rank,
                'slot' => $this->slotNameForRank($rank),
                'node' => $node,
            ];
        }

        usort($rankedNodes, function (array $a, array $b): int {
            $rankCompare = ($a['rank'] <=> $b['rank']);
            if ($rankCompare !== 0) {
                return $rankCompare;
            }

            return (($a['original_index'] ?? 0) <=> ($b['original_index'] ?? 0));
        });

        $styledNodes = [];
        foreach (array_values($rankedNodes) as $position => $ranked) {
            $styledNodes[] = $this->applyRulesToNode(
                node: $ranked['node'],
                pageSlug: $slug,
                pageType: $pageType,
                position: $position,
                totalNodes: count($rankedNodes),
                slot: (string) ($ranked['slot'] ?? 'content'),
                themeContext: $themeContext,
                warnings: $warnings
            );
        }

        $page = $this->mergePageCssPlacementMarkers($page, $slug, $pageType, $themeContext);
        $page['builder_nodes'] = $styledNodes;

        return [
            'warnings' => $warnings,
            'page' => $page,
            'decision' => [
                'slug' => $slug,
                'page_type' => $pageType,
                'node_count' => count($styledNodes),
                'sorted' => count($styledNodes) > 1,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $themeContext
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function applyRulesToNode(
        array $node,
        string $pageSlug,
        string $pageType,
        int $position,
        int $totalNodes,
        string $slot,
        array $themeContext,
        array &$warnings,
    ): array {
        $nodeType = strtolower(trim((string) ($node['type'] ?? '')));
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $props = $this->ensureCanonicalPropGroups($props);

        $style = is_array($props['style'] ?? null) ? $props['style'] : [];
        $advanced = is_array($props['advanced'] ?? null) ? $props['advanced'] : [];
        $bindings = is_array($node['bindings'] ?? null) ? $node['bindings'] : [];
        $content = is_array($props['content'] ?? null) ? $props['content'] : [];
        $data = is_array($props['data'] ?? null) ? $props['data'] : [];

        $style = array_merge(
            $style,
            $this->baseStyleRulesForNode($pageType, $position, $totalNodes, $slot),
            $this->pageSpecificStyleRules($pageType, $nodeType, $slot),
            $this->themeStyleRules($themeContext)
        );

        if ($this->nodeLooksLikeProductGrid($nodeType, $pageType)) {
            $data = array_merge([
                'query' => [
                    'resource' => 'ecommerce.products',
                    'collection' => $pageType === 'shop' ? 'all-products' : 'featured',
                    'limit' => $pageType === 'home' ? 8 : 24,
                ],
            ], $data);
            $content['collection'] = $content['collection'] ?? ($pageType === 'shop' ? 'all-products' : 'featured');
        }

        if ($this->nodeLooksLikeProductDetail($nodeType, $pageType)) {
            if (! isset($bindings['product_slug'])) {
                $bindings['product_slug'] = '{{route.params.slug}}';
                $warnings[] = "Added product_slug route binding to product detail node [{$nodeType}] on page [{$pageSlug}].";
            }
            if (! isset($content['product_slug'])) {
                $content['product_slug'] = '{{route.params.slug}}';
            }
            $data = array_merge([
                'query' => [
                    'resource' => 'ecommerce.product',
                    'by' => 'slug',
                    'binding' => '{{route.params.slug}}',
                ],
            ], $data);
        }

        if ($this->nodeLooksLikeCart($nodeType, $pageType)) {
            $data = array_merge([
                'query' => [
                    'resource' => 'ecommerce.cart',
                ],
            ], $data);
        }

        if ($this->nodeLooksLikeCheckout($nodeType, $pageType)) {
            $data = array_merge([
                'query' => [
                    'resource' => 'ecommerce.checkout',
                ],
            ], $data);
        }

        if ($this->nodeLooksLikeOrdersList($nodeType, $pageSlug, $pageType)) {
            $data = array_merge([
                'query' => [
                    'resource' => 'ecommerce.orders',
                    'scope' => 'account',
                ],
            ], $data);
        }

        if ($this->nodeLooksLikeOrderDetail($nodeType, $pageSlug)) {
            if (! isset($bindings['order_id'])) {
                $bindings['order_id'] = '{{route.params.id}}';
                $warnings[] = "Added order_id route binding to order detail node [{$nodeType}] on page [{$pageSlug}].";
            }

            $data = array_merge([
                'query' => [
                    'resource' => 'ecommerce.order',
                    'by' => 'id',
                    'binding' => '{{route.params.id}}',
                ],
            ], $data);
        }

        if ($this->nodeLooksLikeAccount($nodeType, $pageSlug, $pageType)) {
            $data = array_merge([
                'query' => [
                    'resource' => 'ecommerce.account',
                ],
            ], $data);
        }

        if ($this->nodeLooksLikeAuth($nodeType, $pageSlug, $pageType)) {
            $data = array_merge([
                'query' => [
                    'resource' => 'auth.session',
                ],
            ], $data);
        }

        if (in_array($pageType, ['cart', 'checkout'], true)) {
            $advanced = array_merge($advanced, [
                'ai_priority' => 'conversion',
                'attributes' => array_merge(
                    is_array($advanced['attributes'] ?? null) ? $advanced['attributes'] : [],
                    ['data-webu-ai-priority' => 'conversion']
                ),
            ]);
        }

        $props['content'] = $content;
        $props['data'] = $data;
        $props['style'] = $style;
        $props['advanced'] = $advanced;

        $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
        $meta['ai_placement'] = [
            'ruleset_version' => self::RULESET_VERSION,
            'page_type' => $pageType,
            'slot' => $slot,
            'position' => $position,
        ];
        $meta['ai_styling'] = [
            'theme_preset' => $themeContext['preset'],
            'theme_source' => $themeContext['source'],
            'accent_color' => $themeContext['primary_color'],
        ];

        $node['props'] = $props;
        $node['bindings'] = $bindings;
        $node['meta'] = $meta;

        return $node;
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $themeContext
     * @return array<string, mixed>
     */
    private function mergePageCssPlacementMarkers(array $page, string $slug, string $pageType, array $themeContext): array
    {
        $existingCss = trim((string) ($page['page_css'] ?? ''));
        $markerLines = [
            sprintf('/* webu-ai-placement:v%d page=%s type=%s */', self::RULESET_VERSION, $slug, $pageType),
            sprintf('/* webu-ai-theme preset=%s source=%s */', (string) ($themeContext['preset'] ?? 'default'), (string) ($themeContext['source'] ?? 'unknown')),
        ];

        if (($themeContext['primary_color'] ?? null) !== null) {
            $markerLines[] = sprintf(':root{--webu-ai-accent:%s;}', (string) $themeContext['primary_color']);
        }

        $markerCss = implode("\n", $markerLines);

        if ($existingCss === '') {
            $page['page_css'] = $markerCss;

            return $page;
        }

        if (! str_contains($existingCss, 'webu-ai-placement:v')) {
            $page['page_css'] = $markerCss."\n".$existingCss;
        }

        return $page;
    }

    /**
     * @param  array<string, mixed>  $themeOutput
     * @return array{preset:string,source:string,primary_color:?string,radius_base:?string}
     */
    private function extractThemeContext(array $themeOutput): array
    {
        $themeSettingsPatch = is_array($themeOutput['theme_settings_patch'] ?? null)
            ? $themeOutput['theme_settings_patch']
            : [];

        $preset = strtolower(trim((string) ($themeSettingsPatch['preset'] ?? 'default')));
        if ($preset === '') {
            $preset = 'default';
        }

        $source = trim((string) data_get($themeOutput, 'meta.source', 'unknown'));
        if ($source === '') {
            $source = 'unknown';
        }

        $primaryColor = $this->normalizeColorString(
            data_get($themeSettingsPatch, 'colors.primary')
                ?? data_get($themeSettingsPatch, 'theme_tokens.colors.primary')
        );

        $radiusBase = $this->normalizeCssTokenString(data_get($themeSettingsPatch, 'theme_tokens.radii.base'));

        return [
            'preset' => $preset,
            'source' => $source,
            'primary_color' => $primaryColor,
            'radius_base' => $radiusBase,
        ];
    }

    private function resolvePageType(string $slug, string $routePattern): string
    {
        $slug = strtolower(trim($slug));
        $routePattern = strtolower(trim($routePattern));

        return match (true) {
            in_array($slug, ['home'], true) => 'home',
            in_array($slug, ['shop', 'products'], true) => 'shop',
            in_array($slug, ['product', 'product-detail'], true) || str_contains($routePattern, '/product/{slug}') => 'product',
            in_array($slug, ['cart'], true) => 'cart',
            in_array($slug, ['checkout'], true) => 'checkout',
            in_array($slug, ['account', 'orders', 'login', 'login-register'], true) || str_contains($routePattern, '/account/') => 'account',
            in_array($slug, ['contact'], true) => 'contact',
            in_array($slug, ['about'], true) => 'about',
            default => 'content',
        };
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function rankNodeForPageType(array $node, string $pageType): int
    {
        $type = strtolower(trim((string) ($node['type'] ?? '')));

        $baseRank = 50;
        if ($this->nodeLooksLikeHero($type)) {
            $baseRank = 10;
        } elseif ($this->nodeLooksLikeProductDetail($type, $pageType)) {
            $baseRank = 15;
        } elseif ($this->nodeLooksLikeProductGrid($type, $pageType)) {
            $baseRank = 20;
        } elseif ($this->nodeLooksLikeCheckout($type, $pageType)) {
            $baseRank = 15;
        } elseif ($this->nodeLooksLikeCart($type, $pageType)) {
            $baseRank = 15;
        } elseif ($this->nodeLooksLikeFaq($type)) {
            $baseRank = 70;
        } elseif ($this->nodeLooksLikeContact($type)) {
            $baseRank = 80;
        }

        return match ($pageType) {
            'shop' => $this->nodeLooksLikeProductGrid($type, $pageType) ? 10 : $baseRank + 10,
            'product' => $this->nodeLooksLikeProductDetail($type, $pageType) ? 10 : $baseRank + 10,
            'cart' => $this->nodeLooksLikeCart($type, $pageType) ? 10 : $baseRank + 15,
            'checkout' => $this->nodeLooksLikeCheckout($type, $pageType) ? 10 : $baseRank + 15,
            default => $baseRank,
        };
    }

    private function slotNameForRank(int $rank): string
    {
        return match (true) {
            $rank <= 15 => 'hero_or_primary',
            $rank <= 25 => 'catalog_or_main',
            $rank <= 60 => 'content',
            $rank <= 75 => 'supporting',
            default => 'footer_supporting',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function baseStyleRulesForNode(string $pageType, int $position, int $totalNodes, string $slot): array
    {
        $rules = [
            'layout_role' => $slot,
            'section_spacing_y' => $position === 0 ? '72px' : '56px',
            'container_max_width' => in_array($pageType, ['cart', 'checkout'], true) ? '1200px' : '1280px',
            'content_width' => in_array($pageType, ['about', 'contact', 'account'], true) ? 'narrow' : 'normal',
        ];

        if ($position === 0) {
            $rules['margin_top'] = '0px';
        }

        if ($position === ($totalNodes - 1)) {
            $rules['margin_bottom'] = '0px';
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function pageSpecificStyleRules(string $pageType, string $nodeType, string $slot): array
    {
        if ($this->nodeLooksLikeHero($nodeType)) {
            return [
                'text_align' => $pageType === 'home' ? 'center' : 'left',
                'section_spacing_y' => '88px',
                'surface_variant' => 'hero',
            ];
        }

        if ($this->nodeLooksLikeProductGrid($nodeType, $pageType)) {
            return [
                'grid_columns_desktop' => $pageType === 'home' ? 4 : 3,
                'grid_columns_tablet' => 2,
                'grid_columns_mobile' => 1,
                'grid_gap' => '24px',
                'surface_variant' => $slot === 'catalog_or_main' ? 'catalog' : 'default',
            ];
        }

        if ($this->nodeLooksLikeProductDetail($nodeType, $pageType)) {
            return [
                'layout_mode' => 'two-column',
                'column_gap' => '32px',
                'sticky_summary' => true,
                'surface_variant' => 'product-detail',
            ];
        }

        if ($this->nodeLooksLikeCart($nodeType, $pageType) || $this->nodeLooksLikeCheckout($nodeType, $pageType)) {
            return [
                'layout_mode' => 'checkout-stack',
                'column_gap' => '24px',
                'surface_variant' => 'conversion',
                'container_max_width' => '1200px',
            ];
        }

        if ($this->nodeLooksLikeFaq($nodeType)) {
            return [
                'content_width' => 'narrow',
                'surface_variant' => 'faq',
            ];
        }

        if ($this->nodeLooksLikeContact($nodeType)) {
            return [
                'content_width' => 'narrow',
                'surface_variant' => 'contact',
            ];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $themeContext
     * @return array<string, mixed>
     */
    private function themeStyleRules(array $themeContext): array
    {
        $rules = [
            'theme_preset' => (string) ($themeContext['preset'] ?? 'default'),
        ];

        if (is_string($themeContext['primary_color'] ?? null) && trim((string) $themeContext['primary_color']) !== '') {
            $rules['accent_color'] = (string) $themeContext['primary_color'];
        }

        if (is_string($themeContext['radius_base'] ?? null) && trim((string) $themeContext['radius_base']) !== '') {
            $rules['border_radius_base'] = (string) $themeContext['radius_base'];
        }

        return $rules;
    }

    private function nodeLooksLikeHero(string $nodeType): bool
    {
        return str_contains($nodeType, 'hero');
    }

    private function nodeLooksLikeProductGrid(string $nodeType, string $pageType): bool
    {
        return str_contains($nodeType, 'product_grid')
            || str_contains($nodeType, 'product-grid')
            || ($pageType === 'shop' && str_contains($nodeType, 'product'));
    }

    private function nodeLooksLikeProductDetail(string $nodeType, string $pageType): bool
    {
        return str_contains($nodeType, 'product_detail')
            || str_contains($nodeType, 'product-detail')
            || ($pageType === 'product' && str_contains($nodeType, 'product'));
    }

    private function nodeLooksLikeCart(string $nodeType, string $pageType): bool
    {
        return str_contains($nodeType, 'cart') || $pageType === 'cart';
    }

    private function nodeLooksLikeCheckout(string $nodeType, string $pageType): bool
    {
        return str_contains($nodeType, 'checkout') || $pageType === 'checkout';
    }

    private function nodeLooksLikeFaq(string $nodeType): bool
    {
        return str_contains($nodeType, 'faq');
    }

    private function nodeLooksLikeContact(string $nodeType): bool
    {
        return str_contains($nodeType, 'contact');
    }

    private function nodeLooksLikeOrdersList(string $nodeType, string $pageSlug, string $pageType): bool
    {
        return str_contains($nodeType, 'orders')
            || str_contains($nodeType, 'order-list')
            || ($pageType === 'account' && in_array($pageSlug, ['orders'], true));
    }

    private function nodeLooksLikeOrderDetail(string $nodeType, string $pageSlug): bool
    {
        return str_contains($nodeType, 'order_detail')
            || str_contains($nodeType, 'order-detail')
            || in_array($pageSlug, ['order-detail', 'order'], true);
    }

    private function nodeLooksLikeAccount(string $nodeType, string $pageSlug, string $pageType): bool
    {
        return str_contains($nodeType, 'account')
            || ($pageType === 'account' && $pageSlug === 'account');
    }

    private function nodeLooksLikeAuth(string $nodeType, string $pageSlug, string $pageType): bool
    {
        return str_contains($nodeType, 'auth')
            || str_contains($nodeType, 'login')
            || ($pageType === 'account' && in_array($pageSlug, ['login', 'login-register'], true));
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function ensureCanonicalPropGroups(array $props): array
    {
        foreach (['content', 'data', 'style', 'advanced', 'responsive', 'states'] as $group) {
            if (! isset($props[$group]) || ! is_array($props[$group])) {
                $props[$group] = [];
            }
        }

        return $props;
    }

    private function normalizeColorString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(strtolower((string) $value));
        if ($value === '') {
            return null;
        }

        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value) === 1 ? $value : null;
    }

    private function normalizeCssTokenString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array{valid: bool, error_count: int, errors: array<int, array<string, mixed>>}
     */
    private function validateCanonicalNodeScaffolds(array $pages): array
    {
        $errors = [];

        foreach ($pages as $pageIndex => $page) {
            $nodes = is_array($page['builder_nodes'] ?? null) ? $page['builder_nodes'] : [];
            foreach ($nodes as $nodeIndex => $node) {
                if (! is_array($node)) {
                    $errors[] = $this->error('invalid_type', '$.pages['.$pageIndex.'].builder_nodes['.$nodeIndex.']', 'Node must be object.', 'object', get_debug_type($node));
                    continue;
                }

                $props = is_array($node['props'] ?? null) ? $node['props'] : null;
                if ($props === null) {
                    $errors[] = $this->error('missing_props', '$.pages['.$pageIndex.'].builder_nodes['.$nodeIndex.'].props', 'Node props are required.', 'object', 'missing');
                    continue;
                }

                foreach (['content', 'data', 'style', 'advanced', 'responsive', 'states'] as $groupKey) {
                    if (! isset($props[$groupKey]) || ! is_array($props[$groupKey])) {
                        $errors[] = $this->error(
                            'invalid_props_group',
                            '$.pages['.$pageIndex.'].builder_nodes['.$nodeIndex.'].props.'.$groupKey,
                            'Canonical props group must remain object after placement/styling rules.',
                            'object',
                            get_debug_type($props[$groupKey] ?? null)
                        );
                    }
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
     * @param  array<string, mixed>  $themeOutput
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    private function minimalOutputEnvelope(array $pages, array $themeOutput = []): array
    {
        $theme = $themeOutput !== [] ? $themeOutput : ['theme_settings_patch' => []];

        if (! is_array($theme['theme_settings_patch'] ?? null)) {
            $theme['theme_settings_patch'] = [];
        }

        return [
            'schema_version' => 1,
            'theme' => $theme,
            'pages' => $pages,
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
     * @return array<string, mixed>
     */
    private function error(string $code, string $path, string $message, mixed $expected, mixed $actual): array
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
