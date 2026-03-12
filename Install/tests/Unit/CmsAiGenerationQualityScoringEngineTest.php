<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsAiGenerationQualityScoringEngineTest extends TestCase
{
    public function test_it_scores_and_ranks_candidates_using_rule_based_quality_dimensions(): void
    {
        $engine = app(CmsAiGenerationQualityScoringEngine::class);

        $good = $this->ecommerceOutputCandidate(
            preset: 'arctic',
            accentColor: '#0ea5e9',
            includeCart: true,
            includeCheckout: true,
            includeHomeCta: true,
            includeCheckoutConversionPriority: true
        );

        $weak = $this->ecommerceOutputCandidate(
            preset: '',
            accentColor: '',
            includeCart: false,
            includeCheckout: false,
            includeHomeCta: false,
            includeCheckoutConversionPriority: false
        );

        $goodScore = $engine->scoreOutput($good);
        $weakScore = $engine->scoreOutput($weak);

        $this->assertTrue($goodScore['ok'], json_encode($goodScore, JSON_PRETTY_PRINT));
        $this->assertTrue($weakScore['ok'], json_encode($weakScore, JSON_PRETTY_PRINT));
        $this->assertGreaterThan((int) $weakScore['score'], (int) $goodScore['score']);
        $this->assertContains($goodScore['verdict'], ['good', 'excellent']);
        $this->assertContains('product', data_get($goodScore, 'summary.page_slugs', []));
        $this->assertTrue((bool) data_get($goodScore, 'summary.ecommerce_signal'));
        $this->assertGreaterThanOrEqual(60, (int) data_get($goodScore, 'dimensions.funnel_readiness.score'));
        $this->assertLessThan((int) data_get($goodScore, 'dimensions.visual_consistency.score'), (int) data_get($weakScore, 'dimensions.visual_consistency.score'));

        $ranking = $engine->rankCandidates([
            ['candidate_id' => 'weak', 'ai_output' => $weak],
            ['candidate_id' => 'good', 'ai_output' => $good],
        ]);

        $this->assertTrue($ranking['ok']);
        $this->assertSame('good', data_get($ranking, 'selected_candidate.candidate_id'));
        $rankedIds = collect($ranking['ranked_candidates'])->pluck('candidate_id')->all();
        $this->assertSame(['good', 'weak'], $rankedIds);
    }

    public function test_it_applies_gate_penalties_and_marks_candidate_ineligible_when_validation_report_fails(): void
    {
        $engine = app(CmsAiGenerationQualityScoringEngine::class);
        $output = $this->ecommerceOutputCandidate(
            preset: 'arctic',
            accentColor: '#0ea5e9',
            includeCart: true,
            includeCheckout: true,
            includeHomeCta: true,
            includeCheckoutConversionPriority: true
        );

        $baseline = $engine->scoreOutput($output);
        $gated = $engine->scoreOutput($output, [
            'validation_report' => ['ok' => false],
            'render_report' => ['ok' => false],
        ]);

        $this->assertTrue($baseline['ok']);
        $this->assertTrue($gated['ok']);
        $this->assertGreaterThan((int) $gated['score'], (int) $baseline['score']);
        $this->assertFalse((bool) data_get($gated, 'summary.eligible'));
        $this->assertTrue((bool) data_get($gated, 'gates.hard_fail'));
        $this->assertSame('ineligible', $gated['verdict']);
        $this->assertSame(55, (int) data_get($gated, 'gates.penalty_points'));
    }

    public function test_it_rejects_schema_invalid_output_payloads_before_quality_scoring(): void
    {
        $engine = app(CmsAiGenerationQualityScoringEngine::class);

        $result = $engine->scoreOutput([
            'schema_version' => 1,
            'theme' => ['theme_settings_patch' => []],
            'pages' => [],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_ai_output', $result['code']);
        $this->assertNull($result['score']);
        $this->assertSame('ineligible', $result['verdict']);
        $this->assertSame(
            'docs/architecture/schemas/cms-ai-generation-output.v1.schema.json',
            data_get($result, 'validation.schema.schema')
        );
    }

    public function test_architecture_doc_documents_rule_based_quality_dimensions_and_candidate_ranking(): void
    {
        $path = base_path('docs/architecture/CMS_AI_GENERATION_QUALITY_SCORING_ENGINE_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Generation Quality Scoring Engine v1', $doc);
        $this->assertStringContainsString('P4-E3-03', $doc);
        $this->assertStringContainsString('CmsAiGenerationQualityScoringEngine', $doc);
        $this->assertStringContainsString('visual consistency score', $doc);
        $this->assertStringContainsString('layout balance score', $doc);
        $this->assertStringContainsString('funnel readiness score', $doc);
        $this->assertStringContainsString('mobile friendliness score', $doc);
        $this->assertStringContainsString('rankCandidates', $doc);
        $this->assertStringContainsString('rule-based baseline', $doc);
    }

    /**
     * @return array<string, mixed>
     */
    private function ecommerceOutputCandidate(
        string $preset,
        string $accentColor,
        bool $includeCart,
        bool $includeCheckout,
        bool $includeHomeCta,
        bool $includeCheckoutConversionPriority
    ): array {
        $pages = [
            [
                'slug' => 'home',
                'title' => 'Home',
                'path' => '/',
                'status' => 'draft',
                'builder_nodes' => [
                    $this->node(
                        'hero-split-image',
                        content: $includeHomeCta ? [
                            'headline' => 'Welcome',
                            'primary_cta' => [
                                'label' => 'Shop Now',
                                'url' => '/shop',
                            ],
                        ] : [
                            'headline' => 'Welcome',
                        ],
                        style: array_filter([
                            'theme_preset' => $preset !== '' ? $preset : null,
                            'accent_color' => $accentColor !== '' ? $accentColor : null,
                        ], fn ($v) => $v !== null)
                    ),
                    $this->node(
                        'ecommerce-product-grid',
                        content: ['title' => 'Featured Products'],
                        style: array_filter([
                            'theme_preset' => $preset !== '' ? $preset : null,
                            'accent_color' => $accentColor !== '' ? $accentColor : null,
                            'grid_columns_desktop' => 4,
                        ], fn ($v) => $v !== null)
                    ),
                ],
                'meta' => ['source' => 'generated'],
            ],
            [
                'slug' => 'product',
                'title' => 'Product',
                'path' => '/product/{slug}',
                'route_pattern' => '/product/{slug}',
                'status' => 'draft',
                'builder_nodes' => [
                    $this->node(
                        'ecommerce-product-detail',
                        content: ['product_slug' => '{{route.params.slug}}'],
                        style: array_filter([
                            'theme_preset' => $preset !== '' ? $preset : null,
                            'accent_color' => $accentColor !== '' ? $accentColor : null,
                        ], fn ($v) => $v !== null),
                        bindings: [
                            'product_slug' => '{{route.params.slug}}',
                            'title' => '{{ecommerce.product.title}}',
                        ]
                    ),
                ],
                'meta' => ['source' => 'generated'],
            ],
        ];

        if ($includeCart) {
            $pages[] = [
                'slug' => 'cart',
                'title' => 'Cart',
                'path' => '/cart',
                'status' => 'draft',
                'builder_nodes' => [
                    $this->node('ecommerce-cart', style: $preset !== '' ? ['theme_preset' => $preset] : []),
                ],
                'meta' => ['source' => 'generated'],
            ];
        }

        if ($includeCheckout) {
            $pages[] = [
                'slug' => 'checkout',
                'title' => 'Checkout',
                'path' => '/checkout',
                'status' => 'draft',
                'builder_nodes' => [
                    $this->node(
                        'ecommerce-checkout',
                        style: array_filter([
                            'theme_preset' => $preset !== '' ? $preset : null,
                            'accent_color' => $accentColor !== '' ? $accentColor : null,
                        ], fn ($v) => $v !== null),
                        advanced: $includeCheckoutConversionPriority ? ['ai_priority' => 'conversion'] : []
                    ),
                ],
                'meta' => ['source' => 'generated'],
            ];
        }

        return [
            'schema_version' => 1,
            'theme' => [
                'theme_settings_patch' => array_filter([
                    'preset' => $preset !== '' ? $preset : null,
                    'theme_tokens' => $accentColor !== '' ? [
                        'version' => 1,
                        'colors' => ['primary' => $accentColor],
                    ] : ['version' => 1],
                ], fn ($v) => $v !== null),
            ],
            'pages' => $pages,
            'header' => [
                'enabled' => true,
                'section_type' => 'webu_header_01',
                'props' => [],
                'meta' => ['source' => 'generated'],
            ],
            'footer' => [
                'enabled' => true,
                'section_type' => 'webu_footer_01',
                'props' => [],
                'meta' => ['source' => 'generated'],
            ],
            'meta' => [
                'generator' => [
                    'kind' => 'ai',
                    'version' => 'v1',
                ],
                'created_at' => '2026-02-24T12:00:00Z',
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
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $style
     * @param  array<string, mixed>  $bindings
     * @param  array<string, mixed>  $advanced
     * @return array<string, mixed>
     */
    private function node(
        string $type,
        array $content = [],
        array $style = [],
        array $bindings = [],
        array $advanced = []
    ): array {
        return [
            'type' => $type,
            'props' => [
                'content' => $content,
                'data' => [],
                'style' => $style,
                'advanced' => $advanced,
                'responsive' => [
                    'mobile' => [
                        'style' => [],
                    ],
                ],
                'states' => [],
            ],
            'bindings' => $bindings,
            'meta' => [
                'schema_version' => 1,
                'source' => 'generated',
            ],
        ];
    }
}
