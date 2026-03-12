<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsAiComponentPlacementStylingRulesService
{
    public const PLACEMENT_RULES_VERSION = 1;

    public const STYLING_RULES_VERSION = 1;

    /**
     * Apply deterministic component placement and styling rules onto builder-native pages.
     *
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function applyRules(array $pages, array $context = []): array
    {
        $siteFamily = is_string($context['site_family'] ?? null) ? (string) $context['site_family'] : 'business';
        $warnings = [];
        $nodesTouched = 0;
        $processedPages = [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                $warnings[] = 'Skipped non-object page artifact while applying placement/styling rules.';
                continue;
            }

            $result = $this->applyRulesToPage($page, $siteFamily);
            $processedPages[] = $result['page'];
            $nodesTouched += (int) $result['nodes_touched'];
            foreach ($result['warnings'] as $warning) {
                $warnings[] = $warning;
            }
        }

        return [
            'valid' => true,
            'pages' => $processedPages,
            'warnings' => $warnings,
            'diagnostics' => [
                'placement_rules_version' => self::PLACEMENT_RULES_VERSION,
                'styling_rules_version' => self::STYLING_RULES_VERSION,
                'site_family' => $siteFamily,
                'pages_processed' => count($processedPages),
                'nodes_touched' => $nodesTouched,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array{page: array<string,mixed>, nodes_touched: int, warnings: array<int,string>}
     */
    private function applyRulesToPage(array $page, string $siteFamily): array
    {
        $slug = is_string($page['slug'] ?? null) ? (string) $page['slug'] : 'page';
        $builderNodes = is_array($page['builder_nodes'] ?? null) ? $page['builder_nodes'] : [];
        $warnings = [];
        $nodesTouched = 0;

        if ($builderNodes === []) {
            $warnings[] = sprintf('Page "%s" has no builder_nodes; placement/styling rules skipped.', $slug);
        }

        $processedNodes = [];
        foreach ($builderNodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $result = $this->applyRulesToNode(
                node: $node,
                pageSlug: $slug,
                siteFamily: $siteFamily,
                depth: 0,
                parentType: null,
                sectionRole: null
            );

            $processedNodes[] = $result['node'];
            $nodesTouched += (int) $result['nodes_touched'];
        }

        $processedNodes = $this->applyTopLevelPlacementOrdering($processedNodes, $slug);

        $pageMeta = is_array($page['meta'] ?? null) ? $page['meta'] : [];
        $pageMeta['source'] = $pageMeta['source'] ?? 'generated';
        $pageMeta['ai_layout_rules'] = [
            'placement_version' => self::PLACEMENT_RULES_VERSION,
            'styling_version' => self::STYLING_RULES_VERSION,
            'site_family' => $siteFamily,
        ];

        $page['builder_nodes'] = $processedNodes;
        $page['meta'] = $pageMeta;
        $page['page_css'] = $this->appendPageCssRuleMarker((string) ($page['page_css'] ?? ''), $slug);

        return [
            'page' => $page,
            'nodes_touched' => $nodesTouched,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{node: array<string,mixed>, nodes_touched: int}
     */
    private function applyRulesToNode(
        array $node,
        string $pageSlug,
        string $siteFamily,
        int $depth,
        ?string $parentType,
        ?string $sectionRole
    ): array {
        $nodesTouched = 0;
        $type = is_string($node['type'] ?? null) ? (string) $node['type'] : 'unknown';

        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $props = [
            'content' => is_array($props['content'] ?? null) ? $props['content'] : [],
            'data' => is_array($props['data'] ?? null) ? $props['data'] : [],
            'style' => is_array($props['style'] ?? null) ? $props['style'] : [],
            'advanced' => is_array($props['advanced'] ?? null) ? $props['advanced'] : [],
            'responsive' => is_array($props['responsive'] ?? null) ? $props['responsive'] : [],
            'states' => is_array($props['states'] ?? null) ? $props['states'] : [],
        ];

        $meta = is_array($node['meta'] ?? null) ? $node['meta'] : [];
        $meta['schema_version'] = is_int($meta['schema_version'] ?? null) ? $meta['schema_version'] : 1;
        $meta['source'] = $meta['source'] ?? 'generated';

        $resolvedSectionRole = $sectionRole;

        if ($type === 'section') {
            $resolvedSectionRole = $this->resolveSectionRole($node, $pageSlug);
            $props['style'] = $this->mergeRecursive(
                $props['style'],
                $this->sectionStyleRules($pageSlug, $siteFamily, $resolvedSectionRole, $depth)
            );
            $meta['ai_slot'] = $resolvedSectionRole ?? 'section';
            $meta['ai_rules'] = [
                'placement_version' => self::PLACEMENT_RULES_VERSION,
                'styling_version' => self::STYLING_RULES_VERSION,
                'type' => 'section',
            ];
            $nodesTouched++;
        } else {
            $props['style'] = $this->mergeRecursive(
                $props['style'],
                $this->componentStyleRules($type, $pageSlug, $siteFamily, $resolvedSectionRole, $parentType)
            );

            $meta['ai_slot'] = $this->componentSlotForType($type, $resolvedSectionRole);
            $meta['ai_rules'] = [
                'placement_version' => self::PLACEMENT_RULES_VERSION,
                'styling_version' => self::STYLING_RULES_VERSION,
                'type' => 'component',
            ];
            $nodesTouched++;
        }

        $node['props'] = $props;
        $node['meta'] = $meta;

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if ($children !== []) {
            if ($type === 'section') {
                $children = $this->applySectionChildOrdering($children, $pageSlug, $resolvedSectionRole);
            }

            $nextChildren = [];
            foreach ($children as $child) {
                if (! is_array($child)) {
                    continue;
                }

                $childResult = $this->applyRulesToNode(
                    node: $child,
                    pageSlug: $pageSlug,
                    siteFamily: $siteFamily,
                    depth: $depth + 1,
                    parentType: $type,
                    sectionRole: $resolvedSectionRole
                );
                $nextChildren[] = $childResult['node'];
                $nodesTouched += (int) $childResult['nodes_touched'];
            }

            $node['children'] = $nextChildren;
        }

        return [
            'node' => $node,
            'nodes_touched' => $nodesTouched,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function applyTopLevelPlacementOrdering(array $nodes, string $pageSlug): array
    {
        if ($nodes === []) {
            return $nodes;
        }

        $priorityMap = match ($pageSlug) {
            'home' => [
                'hero' => 10,
                'catalog' => 20,
                'contact' => 90,
            ],
            'checkout' => [
                'checkout' => 10,
                'summary' => 20,
            ],
            default => [],
        };

        if ($priorityMap === []) {
            return $nodes;
        }

        $indexed = [];
        foreach ($nodes as $index => $node) {
            $slot = is_array($node['meta'] ?? null) ? ($node['meta']['ai_slot'] ?? null) : null;
            $indexed[] = [
                'index' => $index,
                'priority' => is_string($slot) && array_key_exists($slot, $priorityMap) ? $priorityMap[$slot] : 100 + $index,
                'node' => $node,
            ];
        }

        usort($indexed, static function (array $a, array $b): int {
            if ($a['priority'] === $b['priority']) {
                return $a['index'] <=> $b['index'];
            }

            return $a['priority'] <=> $b['priority'];
        });

        return array_values(array_map(static fn (array $row): array => $row['node'], $indexed));
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array<string, mixed>>
     */
    private function applySectionChildOrdering(array $children, string $pageSlug, ?string $sectionRole): array
    {
        $priorityByType = match ($sectionRole) {
            'hero' => ['heading' => 10, 'text' => 20, 'button' => 30],
            'catalog' => ['heading' => 10, 'products-grid' => 20, 'posts-list' => 20],
            'product' => ['product-detail' => 10, 'button' => 20, 'text' => 30],
            'cart' => ['heading' => 10, 'cart-summary' => 20, 'button' => 30],
            'checkout' => ['checkout-form' => 10, 'order-summary' => 20],
            'contact' => ['heading' => 10, 'text' => 20, 'form' => 30],
            'orders' => ['heading' => 10, 'orders-list' => 20],
            'order-detail' => ['heading' => 10, 'order-detail' => 20],
            'account' => ['heading' => 10, 'account-dashboard' => 20],
            'auth' => ['auth' => 10],
            default => [],
        };

        // Page slug fallback for ambiguous section labels.
        if ($priorityByType === [] && $pageSlug === 'checkout') {
            $priorityByType = ['checkout-form' => 10, 'order-summary' => 20];
        }

        if ($priorityByType === []) {
            return $children;
        }

        $rows = [];
        foreach ($children as $index => $child) {
            $type = is_array($child) ? (string) ($child['type'] ?? '') : '';
            $rows[] = [
                'index' => $index,
                'priority' => $priorityByType[$type] ?? (100 + $index),
                'child' => $child,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            if ($a['priority'] === $b['priority']) {
                return $a['index'] <=> $b['index'];
            }

            return $a['priority'] <=> $b['priority'];
        });

        return array_values(array_map(static fn (array $row): array => $row['child'], $rows));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function resolveSectionRole(array $node, string $pageSlug): ?string
    {
        $style = is_array(data_get($node, 'props.style')) ? data_get($node, 'props.style') : [];
        $variant = is_string($style['variant'] ?? null) ? Str::lower((string) $style['variant']) : null;
        if ($variant === 'hero') {
            return 'hero';
        }
        if ($variant === 'catalog') {
            return 'catalog';
        }

        $label = Str::lower((string) data_get($node, 'props.content.label', ''));

        if ($label !== '') {
            if (str_contains($label, 'hero')) {
                return 'hero';
            }
            if (str_contains($label, 'catalog') || str_contains($label, 'featured products') || str_contains($label, 'blog listing')) {
                return 'catalog';
            }
            if (str_contains($label, 'product detail')) {
                return 'product';
            }
            if ($label === 'cart') {
                return 'cart';
            }
            if (str_contains($label, 'checkout')) {
                return 'checkout';
            }
            if (str_contains($label, 'contact')) {
                return 'contact';
            }
            if (str_contains($label, 'orders list')) {
                return 'orders';
            }
            if (str_contains($label, 'order detail')) {
                return 'order-detail';
            }
            if (str_contains($label, 'account')) {
                return 'account';
            }
            if ($label === 'auth') {
                return 'auth';
            }
        }

        return match ($pageSlug) {
            'home' => 'hero',
            'shop', 'blog' => 'catalog',
            'product', 'post' => 'product',
            'cart' => 'cart',
            'checkout' => 'checkout',
            'contact' => 'contact',
            'orders' => 'orders',
            'order' => 'order-detail',
            'account' => 'account',
            'login' => 'auth',
            default => 'section',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionStyleRules(string $pageSlug, string $siteFamily, ?string $sectionRole, int $depth): array
    {
        $base = [
            'layout' => $sectionRole === 'checkout' ? 'two-column' : 'stack',
            'spacing' => [
                'padding_y_token' => $depth === 0 ? 'section_y' : 'stack_gap',
                'gap_token' => 'stack_gap',
            ],
            'container' => [
                'padding_x_token' => 'container_x',
                'width' => 'content',
            ],
        ];

        if ($sectionRole === 'hero') {
            $base = $this->mergeRecursive($base, [
                'variant' => 'hero',
                'surface' => 'muted',
                'align' => 'start',
                'cta_placement' => 'inline_after_copy',
            ]);
        }

        if ($sectionRole === 'catalog') {
            $base = $this->mergeRecursive($base, [
                'variant' => 'catalog',
                'surface' => 'transparent',
            ]);
        }

        if ($sectionRole === 'checkout') {
            $base = $this->mergeRecursive($base, [
                'columns' => [
                    'desktop' => '2fr 1fr',
                    'tablet' => '1fr',
                    'mobile' => '1fr',
                ],
                'surface' => 'transparent',
            ]);
        }

        if (in_array($sectionRole, ['cart', 'product', 'order-detail', 'account'], true)) {
            $base = $this->mergeRecursive($base, [
                'surface' => 'transparent',
                'layout_hint' => 'detail_stack',
            ]);
        }

        if ($siteFamily === 'ecommerce' && $pageSlug === 'home' && $sectionRole === 'catalog') {
            $base = $this->mergeRecursive($base, [
                'density' => 'comfortable',
            ]);
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function componentStyleRules(
        string $type,
        string $pageSlug,
        string $siteFamily,
        ?string $sectionRole,
        ?string $parentType
    ): array {
        if ($type === 'heading') {
            return [
                'typography' => [
                    'role' => $sectionRole === 'hero' ? 'hero_title' : 'section_title',
                    'max_width' => $sectionRole === 'hero' ? '20ch' : '40ch',
                ],
                'spacing' => [
                    'margin_bottom_token' => 'stack_gap',
                ],
            ];
        }

        if ($type === 'text') {
            return [
                'typography' => [
                    'role' => 'body',
                    'max_width' => $sectionRole === 'hero' ? '52ch' : '64ch',
                ],
                'spacing' => [
                    'margin_bottom_token' => 'stack_gap',
                ],
            ];
        }

        if ($type === 'button') {
            $isPrimaryCta = in_array($pageSlug, ['home', 'product', 'cart'], true) || $sectionRole === 'hero';

            return [
                'variant' => $isPrimaryCta ? 'primary' : 'secondary',
                'size' => $isPrimaryCta ? 'lg' : 'md',
                'radii' => [
                    'token' => 'button',
                ],
                'shadow' => [
                    'token' => $isPrimaryCta ? 'elevated' : 'card',
                ],
                'layout' => [
                    'self_align' => 'start',
                ],
            ];
        }

        if ($type === 'products-grid') {
            return [
                'grid' => [
                    'columns' => [
                        'desktop' => 4,
                        'tablet' => 2,
                        'mobile' => 1,
                    ],
                    'gap_token' => 'stack_gap',
                ],
                'surface' => 'transparent',
                'density' => $siteFamily === 'ecommerce' ? 'comfortable' : 'compact',
            ];
        }

        if (in_array($type, ['product-detail', 'cart-summary', 'checkout-form', 'order-summary', 'orders-list', 'order-detail', 'auth', 'account-dashboard', 'form', 'posts-list', 'post-detail'], true)) {
            return [
                'surface' => 'card',
                'padding' => [
                    'all_token' => 'stack_gap',
                ],
                'radii' => [
                    'token' => 'card',
                ],
                'shadow' => [
                    'token' => 'card',
                ],
            ];
        }

        if ($parentType === 'section') {
            return [
                'layout' => [
                    'width' => 'auto',
                ],
            ];
        }

        return [];
    }

    private function componentSlotForType(string $type, ?string $sectionRole): string
    {
        return match ($type) {
            'heading' => $sectionRole === 'hero' ? 'hero_title' : 'heading',
            'text' => $sectionRole === 'hero' ? 'hero_copy' : 'text',
            'button' => in_array($sectionRole, ['hero', 'cart', 'product'], true) ? 'primary_cta' : 'button',
            'products-grid' => 'catalog_grid',
            'product-detail' => 'product_detail',
            'cart-summary' => 'cart_summary',
            'checkout-form' => 'checkout_form',
            'order-summary' => 'order_summary',
            'orders-list' => 'orders_list',
            'order-detail' => 'order_detail',
            'auth' => 'auth_form',
            'account-dashboard' => 'account_dashboard',
            'form' => 'contact_form',
            'posts-list' => 'posts_list',
            'post-detail' => 'post_detail',
            default => $type,
        };
    }

    private function appendPageCssRuleMarker(string $pageCss, string $slug): string
    {
        $trimmed = trim($pageCss);
        $marker = sprintf('/* AI placement+styling rules v1 applied: %s */', $slug);
        if ($trimmed === '') {
            return $marker;
        }

        if (str_contains($trimmed, $marker)) {
            return $trimmed;
        }

        return $trimmed."\n".$marker;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function mergeRecursive(array $base, array $patch): array
    {
        foreach ($patch as $key => $value) {
            if (
                array_key_exists($key, $base)
                && is_array($base[$key])
                && is_array($value)
                && ! array_is_list($base[$key])
                && ! array_is_list($value)
            ) {
                /** @var array<string, mixed> $baseValue */
                $baseValue = $base[$key];
                /** @var array<string, mixed> $patchValue */
                $patchValue = $value;
                $base[$key] = $this->mergeRecursive($baseValue, $patchValue);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
