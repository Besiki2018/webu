<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiComponentPlacementStylingRulesServiceTest extends TestCase
{
    public function test_it_applies_deterministic_placement_and_styling_rules_to_builder_native_pages(): void
    {
        $service = app(CmsAiComponentPlacementStylingRulesService::class);

        $result = $service->applyRules($this->samplePages(), [
            'site_family' => 'ecommerce',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(1, data_get($result, 'diagnostics.placement_rules_version'));
        $this->assertSame(1, data_get($result, 'diagnostics.styling_rules_version'));
        $this->assertSame('ecommerce', data_get($result, 'diagnostics.site_family'));
        $this->assertSame(2, data_get($result, 'diagnostics.pages_processed'));
        $this->assertGreaterThan(0, (int) data_get($result, 'diagnostics.nodes_touched'));

        $pages = $result['pages'];
        $homePage = $this->findPage($pages, 'home');
        $checkoutPage = $this->findPage($pages, 'checkout');

        $this->assertSame(1, data_get($homePage, 'meta.ai_layout_rules.placement_version'));
        $this->assertSame(1, data_get($homePage, 'meta.ai_layout_rules.styling_version'));
        $this->assertStringContainsString('AI placement+styling rules v1 applied: home', (string) ($homePage['page_css'] ?? ''));

        $homeSections = $homePage['builder_nodes'];
        $this->assertSame('hero', data_get($homeSections[0], 'meta.ai_slot'));
        $this->assertSame('catalog', data_get($homeSections[1], 'meta.ai_slot'));
        $this->assertSame('hero', data_get($homeSections[0], 'props.style.variant'));
        $this->assertSame('section_y', data_get($homeSections[0], 'props.style.spacing.padding_y_token'));

        $heroChildren = $homeSections[0]['children'] ?? [];
        $this->assertSame(['heading', 'text', 'button'], array_values(array_map(
            static fn (array $node): string => (string) ($node['type'] ?? ''),
            $heroChildren
        )));
        $this->assertSame('hero_title', data_get($heroChildren[0], 'meta.ai_slot'));
        $this->assertSame('primary', data_get($heroChildren[2], 'props.style.variant'));

        $checkoutSection = $checkoutPage['builder_nodes'][0] ?? null;
        $this->assertIsArray($checkoutSection);
        $this->assertSame('checkout', data_get($checkoutSection, 'meta.ai_slot'));
        $this->assertSame('two-column', data_get($checkoutSection, 'props.style.layout'));
        $this->assertSame('2fr 1fr', data_get($checkoutSection, 'props.style.columns.desktop'));

        $checkoutChildren = $checkoutSection['children'] ?? [];
        $this->assertSame(['checkout-form', 'order-summary'], array_values(array_map(
            static fn (array $node): string => (string) ($node['type'] ?? ''),
            $checkoutChildren
        )));
        $this->assertSame('checkout_form', data_get($checkoutChildren[0], 'meta.ai_slot'));
        $this->assertSame('card', data_get($checkoutChildren[0], 'props.style.surface'));
        $this->assertSame('order_summary', data_get($checkoutChildren[1], 'meta.ai_slot'));
    }

    public function test_architecture_doc_documents_post_processor_scope_and_no_parallel_storage_handoff(): void
    {
        $path = base_path('docs/architecture/CMS_AI_COMPONENT_PLACEMENT_STYLING_RULES_ENGINE_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Component Placement & Styling Rules Engine v1', $doc);
        $this->assertStringContainsString('P4-E2-03', $doc);
        $this->assertStringContainsString('post-processor', $doc);
        $this->assertStringContainsString('builder_nodes[].props.style', $doc);
        $this->assertStringContainsString('pages[].meta', $doc);
        $this->assertStringContainsString('cms-ai-generation-output.v1', $doc);
        $this->assertStringContainsString('cms-canonical-page-node.v1', $doc);
        $this->assertStringContainsString('P4-E2-04', $doc);
        $this->assertStringContainsString('no parallel page storage', $doc);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function samplePages(): array
    {
        return [
            [
                'slug' => 'home',
                'title' => 'Home',
                'path' => '/',
                'status' => 'draft',
                'template_key' => 'home',
                'route_pattern' => null,
                'builder_nodes' => [
                    [
                        'type' => 'section',
                        'props' => [
                            'content' => ['label' => 'Featured Products'],
                            'data' => [],
                            'style' => ['variant' => 'catalog'],
                            'advanced' => [],
                            'responsive' => [],
                            'states' => [],
                        ],
                        'bindings' => [],
                        'meta' => ['schema_version' => 1],
                        'children' => [
                            $this->productsGridNode(),
                            $this->headingNode('Featured'),
                        ],
                    ],
                    [
                        'type' => 'section',
                        'props' => [
                            'content' => ['label' => 'Hero'],
                            'data' => [],
                            'style' => ['variant' => 'hero'],
                            'advanced' => [],
                            'responsive' => [],
                            'states' => [],
                        ],
                        'bindings' => [],
                        'meta' => ['schema_version' => 1],
                        'children' => [
                            $this->buttonNode('Shop'),
                            $this->textNode('Copy'),
                            $this->headingNode('Title'),
                        ],
                    ],
                ],
                'page_css' => '/* base */',
                'seo' => [],
                'meta' => ['required_page' => true, 'source' => 'generated'],
            ],
            [
                'slug' => 'checkout',
                'title' => 'Checkout',
                'path' => '/checkout',
                'status' => 'draft',
                'template_key' => 'checkout',
                'route_pattern' => null,
                'builder_nodes' => [
                    [
                        'type' => 'section',
                        'props' => [
                            'content' => ['label' => 'Checkout'],
                            'data' => [],
                            'style' => [],
                            'advanced' => [],
                            'responsive' => [],
                            'states' => [],
                        ],
                        'bindings' => [],
                        'meta' => ['schema_version' => 1],
                        'children' => [
                            $this->orderSummaryNode(),
                            $this->checkoutFormNode(),
                        ],
                    ],
                ],
                'page_css' => '',
                'seo' => [],
                'meta' => ['required_page' => true, 'source' => 'generated'],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    private function findPage(array $pages, string $slug): array
    {
        foreach ($pages as $page) {
            if (($page['slug'] ?? null) === $slug) {
                return $page;
            }
        }

        $this->fail("Missing page slug {$slug}");
    }

    /**
     * @return array<string, mixed>
     */
    private function headingNode(string $text): array
    {
        return $this->node('heading', ['text' => $text], []);
    }

    /**
     * @return array<string, mixed>
     */
    private function textNode(string $text): array
    {
        return $this->node('text', ['text' => $text], []);
    }

    /**
     * @return array<string, mixed>
     */
    private function buttonNode(string $label): array
    {
        return $this->node('button', ['label' => $label], ['url' => '/shop']);
    }

    /**
     * @return array<string, mixed>
     */
    private function productsGridNode(): array
    {
        return $this->node('products-grid', ['title' => 'Grid'], []);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutFormNode(): array
    {
        return $this->node('checkout-form', ['title' => 'Checkout'], []);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderSummaryNode(): array
    {
        return $this->node('order-summary', ['title' => 'Summary'], []);
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function node(string $type, array $content, array $data): array
    {
        return [
            'type' => $type,
            'props' => [
                'content' => $content,
                'data' => $data,
                'style' => [],
                'advanced' => [],
                'responsive' => [],
                'states' => [],
            ],
            'bindings' => [],
            'meta' => [
                'schema_version' => 1,
                'source' => 'generated',
            ],
        ];
    }
}
