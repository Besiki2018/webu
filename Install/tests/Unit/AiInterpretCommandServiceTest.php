<?php

namespace Tests\Unit;

use App\Services\AiInterpretCommandService;
use App\Services\InternalAiService;
use Mockery;
use Tests\TestCase;

class AiInterpretCommandServiceTest extends TestCase
{
    public function test_simple_header_design_request_switches_to_next_global_variant_without_ai_call(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldNotReceive('isConfigured');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret('ჰედერის დიზიანი შემიცვალე', [
            'locale' => 'ka',
            'theme' => [
                'layout' => [
                    'header_section_key' => 'webu_header_01',
                    'header_props' => [
                        'layout_variant' => 'header-1',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('updateGlobalComponent', $result['change_set']['operations'][0]['op'] ?? null);
        $this->assertSame('header', $result['change_set']['operations'][0]['component'] ?? null);
        $this->assertSame('header-2', $result['change_set']['operations'][0]['patch']['layout_variant'] ?? null);
        $this->assertSame('ჰედერის დიზაინი შევცვალე', $result['change_set']['summary'][0] ?? null);
    }

    public function test_specific_header_content_request_stays_on_ai_path(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldReceive('isConfigured')->once()->andReturn(false);

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret('შეცვალე ჰედერის დიზაინი და იმეილი', [
            'locale' => 'ka',
            'theme' => [
                'layout' => [
                    'header_section_key' => 'webu_header_01',
                    'header_props' => [
                        'layout_variant' => 'header-1',
                    ],
                ],
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'AI provider is not configured. Configure in Admin Settings → Integrations.',
            $result['error'] ?? null
        );
    }

    public function test_exact_translation_request_updates_matching_global_header_text_without_general_ai_plan(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldReceive('isConfigured')->once()->andReturn(true);
        $internalAi->shouldReceive('complete')
            ->once()
            ->andReturn('["შემოდგომის კოლექცია. ახალი სეზონი. ახალი ხედვა. შეიძინე ახლავე!","დეტალები"]');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret(implode("\n", [
            'Autumn Collection. A New Season. A New Perspective. Buy Now!',
            'SHOP NOW',
            'ეს თარგმნე ქართულად',
        ]), [
            'locale' => 'ka',
            'theme' => [
                'layout' => [
                    'header_props' => [
                        'top_strip_text' => 'Autumn Collection. A New Season. A New Perspective. Buy Now!',
                        'announcement_cta_label' => 'SHOP NOW',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('updateGlobalComponent', $result['change_set']['operations'][0]['op'] ?? null);
        $this->assertSame('header', $result['change_set']['operations'][0]['component'] ?? null);
        $this->assertSame(
            'შემოდგომის კოლექცია. ახალი სეზონი. ახალი ხედვა. შეიძინე ახლავე!',
            $result['change_set']['operations'][0]['patch']['top_strip_text'] ?? null
        );
        $this->assertSame(
            'დეტალები',
            $result['change_set']['operations'][0]['patch']['announcement_cta_label'] ?? null
        );
        $this->assertSame('ტექსტი ქართულად ვთარგმნე', $result['change_set']['summary'][0] ?? null);
    }

    public function test_duplicate_text_cleanup_request_clears_extra_matching_titles_without_ai_call(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldNotReceive('isConfigured');
        $internalAi->shouldNotReceive('complete');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret('Featured products ეს წაშალე მხოლოდ ერტი დატოვე', [
            'sections' => [
                [
                    'id' => 'products-1',
                    'type' => 'webu_ecom_product_grid_01',
                    'props' => ['title' => 'Featured products'],
                ],
                [
                    'id' => 'products-2',
                    'type' => 'webu_ecom_product_grid_01',
                    'props' => ['title' => 'Featured products'],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['change_set']['operations'] ?? []);
        $this->assertSame('updateText', $result['change_set']['operations'][0]['op'] ?? null);
        $this->assertSame('products-2', $result['change_set']['operations'][0]['sectionId'] ?? null);
        $this->assertSame('title', $result['change_set']['operations'][0]['path'] ?? null);
        $this->assertSame('', $result['change_set']['operations'][0]['value'] ?? null);
        $this->assertSame('დუბლირებული ტექსტი მოვაშორე', $result['change_set']['summary'][0] ?? null);
    }

    public function test_simple_replace_request_updates_exact_button_text_and_shop_link_without_general_ai_plan(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldNotReceive('isConfigured');
        $internalAi->shouldNotReceive('complete');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret('SHOP NOW ის ნაცვლად დეტალები დაწერე და მაღაზიის გვერდზე გადადიოდეს', [
            'locale' => 'ka',
            'sections' => [
                [
                    'id' => 'hero-1',
                    'type' => 'hero',
                    'props' => [
                        'primary_cta' => [
                            'label' => 'SHOP NOW',
                            'url' => '#',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['change_set']['operations'] ?? []);
        $this->assertSame('updateText', $result['change_set']['operations'][0]['op'] ?? null);
        $this->assertSame('primary_cta.label', $result['change_set']['operations'][0]['path'] ?? null);
        $this->assertSame('დეტალები', $result['change_set']['operations'][0]['value'] ?? null);
        $this->assertSame('updateText', $result['change_set']['operations'][1]['op'] ?? null);
        $this->assertSame('primary_cta.url', $result['change_set']['operations'][1]['path'] ?? null);
        $this->assertSame('/shop', $result['change_set']['operations'][1]['value'] ?? null);
        $this->assertSame('ტექსტი განვაახლე', $result['change_set']['summary'][0] ?? null);
    }

    public function test_inline_write_instruction_prefers_visible_editable_heading_field(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldNotReceive('isConfigured');
        $internalAi->shouldNotReceive('complete');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret('ბრენდები აქ დამიწერ ონლაინ მაღაზია', [
            'locale' => 'ka',
            'sections' => [
                [
                    'id' => 'heading-1',
                    'type' => 'webu_general_heading_01',
                    'editable_fields' => ['headline', 'color', 'background_color'],
                    'props' => [
                        'title' => 'Exclusive Finance Apps',
                        'headline' => 'ბრენდები',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('updateText', $result['change_set']['operations'][0]['op'] ?? null);
        $this->assertSame('heading-1', $result['change_set']['operations'][0]['sectionId'] ?? null);
        $this->assertSame('headline', $result['change_set']['operations'][0]['path'] ?? null);
        $this->assertSame('ონლაინ მაღაზია', $result['change_set']['operations'][0]['value'] ?? null);
    }

    public function test_explicit_write_after_translate_request_prefers_replacement_over_translation(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldNotReceive('isConfigured');
        $internalAi->shouldNotReceive('complete');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret('Exclusive Finance Apps ეს თარგმნე დააწერე შენი მაღაზია', [
            'locale' => 'ka',
            'sections' => [
                [
                    'id' => 'heading-1',
                    'type' => 'webu_general_heading_01',
                    'editable_fields' => ['headline', 'color', 'background_color'],
                    'props' => [
                        'title' => 'Exclusive Finance Apps',
                        'headline' => 'ბრენდები',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('updateText', $result['change_set']['operations'][0]['op'] ?? null);
        $this->assertSame('headline', $result['change_set']['operations'][0]['path'] ?? null);
        $this->assertSame('შენი მაღაზია', $result['change_set']['operations'][0]['value'] ?? null);
        $this->assertSame('ტექსტი განვაახლე', $result['change_set']['summary'][0] ?? null);
    }

    /** @dataProvider georgianColloquialPhrasesProvider */
    public function test_georgian_colloquial_and_builder_phrases_resolve_deterministically(string $prompt, array $pageContext, string $expectedOp): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldNotReceive('isConfigured');
        $internalAi->shouldNotReceive('complete');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret($prompt, $pageContext);

        $this->assertTrue($result['success'], 'Expected success for: '.$prompt);
        $this->assertSame($expectedOp, $result['change_set']['operations'][0]['op'] ?? null);
    }

    public static function georgianColloquialPhrasesProvider(): array
    {
        $themeWithHeader = [
            'theme' => [
                'layout' => [
                    'header_section_key' => 'webu_header_01',
                    'header_props' => ['layout_variant' => 'header-1'],
                ],
            ],
        ];
        $themeWithFooter = [
            'theme' => [
                'layout' => [
                    'footer_section_key' => 'webu_footer_01',
                    'footer_props' => ['layout_variant' => 'footer-1'],
                ],
            ],
        ];

        return [
            'ჰედერის დიზიანი შემიცვალე (typo დიზიან)' => [
                'ჰედერის დიზიანი შემიცვალე',
                array_merge($themeWithHeader, ['locale' => 'ka']),
                'updateGlobalComponent',
            ],
            'ფუტერის დიზაინი შეცვალე' => [
                'ფუტერის დიზაინი შეცვალე',
                array_merge($themeWithFooter, ['locale' => 'ka']),
                'updateGlobalComponent',
            ],
            'ჰედერის დიზაინი შევცვალე' => [
                'ჰედერის დიზაინი შევცვალე',
                array_merge($themeWithHeader, ['locale' => 'ka']),
                'updateGlobalComponent',
            ],
            'ფუტერში მარტო ეს დატოვე - footer design' => [
                'ფუტერის დიზაინი შეცვალე',
                array_merge($themeWithFooter, ['locale' => 'ka']),
                'updateGlobalComponent',
            ],
        ];
    }

    public function test_georgian_inline_write_აქ_დამიწერე(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldNotReceive('isConfigured');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret('ბრენდები აქ დამიწერ ონლაინ მაღაზია', [
            'locale' => 'ka',
            'sections' => [
                [
                    'id' => 'heading-1',
                    'type' => 'webu_general_heading_01',
                    'editable_fields' => ['headline', 'color'],
                    'props' => ['headline' => 'ბრენდები', 'title' => 'Brands'],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('updateText', $result['change_set']['operations'][0]['op'] ?? null);
        $this->assertSame('headline', $result['change_set']['operations'][0]['path'] ?? null);
        $this->assertSame('ონლაინ მაღაზია', $result['change_set']['operations'][0]['value'] ?? null);
    }

    public function test_georgian_ერტი_duplicate_cleanup(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldNotReceive('isConfigured');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret('Featured products ეს წაშალე მხოლოდ ერტი დატოვე', [
            'sections' => [
                ['id' => 'p1', 'type' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Featured products']],
                ['id' => 'p2', 'type' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Featured products']],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('updateText', $result['change_set']['operations'][0]['op'] ?? null);
    }

    public function test_georgian_მაღაზიის_გვერდზე_shop_link(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldNotReceive('isConfigured');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret('SHOP NOW ის ნაცვლად დეტალები დაწერე და მაღაზიის გვერდზე გადადიოდეს', [
            'locale' => 'ka',
            'sections' => [
                [
                    'id' => 'hero-1',
                    'type' => 'hero',
                    'props' => [
                        'primary_cta' => ['label' => 'SHOP NOW', 'url' => '#'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['change_set']['operations'] ?? []);
        $this->assertSame('updateText', $result['change_set']['operations'][0]['op'] ?? null);
        $this->assertSame('primary_cta.label', $result['change_set']['operations'][0]['path'] ?? null);
        $this->assertSame('დეტალები', $result['change_set']['operations'][0]['value'] ?? null);
        $this->assertSame('/shop', $result['change_set']['operations'][1]['value'] ?? null);
    }

    public function test_general_ai_prompt_defaults_to_georgian_for_romanized_georgian_commands(): void
    {
        $internalAi = Mockery::mock(InternalAiService::class);
        $internalAi->shouldReceive('isConfigured')->once()->andReturn(true);
        $internalAi->shouldReceive('complete')
            ->once()
            ->withArgs(function (string $prompt, int $maxTokens): bool {
                $this->assertSame(4000, $maxTokens);
                $this->assertStringContainsString("The user's primary working language is Georgian.", $prompt);
                $this->assertStringContainsString('Treat minor Georgian spelling mistakes, colloquial phrasing, and romanized Georgian hints as valid Georgian commands.', $prompt);
                $this->assertStringContainsString('Locale: ka', $prompt);

                return true;
            })
            ->andReturn('{"operations":[],"summary":["შეჯამება"]}');

        $service = new AiInterpretCommandService($internalAi);

        $result = $service->interpret('qartulad shecvale am seqciis diziani', [
            'locale' => 'en',
            'sections' => [
                [
                    'id' => 'section-1',
                    'type' => 'hero',
                    'props' => [
                        'headline' => 'Sample',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('შეჯამება', $result['change_set']['summary'][0] ?? null);
    }
}
