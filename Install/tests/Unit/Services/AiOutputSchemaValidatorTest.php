<?php

namespace Tests\Unit\Services;

use App\Services\CmsSectionBindingService;
use App\Services\AiOutputSchemaValidator;
use PHPUnit\Framework\TestCase;

class AiOutputSchemaValidatorTest extends TestCase
{
    public function test_accepts_structured_sections_with_no_raw_html(): void
    {
        $validator = new AiOutputSchemaValidator([]);
        $output = [
            'sections' => [
                ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'Welcome', 'subtitle' => 'Sub']],
                ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Products', 'products_per_page' => 8]],
            ],
        ];
        $errors = $validator->validate($output);
        $this->assertSame([], $errors);
    }

    public function test_rejects_raw_html_in_props(): void
    {
        $validator = new AiOutputSchemaValidator([]);
        $output = [
            'sections' => [
                ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'OK', 'body' => '<script>alert(1)</script>']],
            ],
        ];
        $errors = $validator->validate($output);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Raw HTML', implode(' ', $errors));
    }

    public function test_rejects_raw_html_in_content_string(): void
    {
        $validator = new AiOutputSchemaValidator([]);
        $output = [
            'sections' => [
                ['key' => 'unknown', 'props' => ['html' => '<div class="custom"><p>Long content here that looks like HTML markup in the document body for testing.</p></div>']],
            ],
        ];
        $errors = $validator->validate($output);
        $this->assertNotEmpty($errors);
    }

    public function test_rejects_unknown_section_key_when_allowed_list_configured(): void
    {
        $validator = new AiOutputSchemaValidator(['webu_general_heading_01', 'webu_ecom_product_grid_01']);
        $output = [
            'sections' => [
                ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'OK']],
                ['key' => 'custom_raw_html_section', 'props' => []],
            ],
        ];
        $errors = $validator->validate($output);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not in the allowed', implode(' ', $errors));
    }

    public function test_accepts_only_allowed_section_keys_when_configured(): void
    {
        $validator = new AiOutputSchemaValidator(['webu_general_heading_01', 'webu_ecom_product_grid_01']);
        $output = [
            'sections' => [
                ['key' => 'webu_general_heading_01', 'props' => ['headline' => 'OK']],
                ['key' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Products']],
            ],
        ];
        $errors = $validator->validate($output);
        $this->assertSame([], $errors);
    }

    public function test_accepts_sections_that_resolve_to_registered_library_binding(): void
    {
        $bindings = $this->createMock(CmsSectionBindingService::class);
        $bindings->expects($this->once())
            ->method('resolveBinding')
            ->with('hero')
            ->willReturn([
                'source' => 'sections_library',
                'section_key' => 'hero_split_image',
            ]);

        $validator = new AiOutputSchemaValidator(['webu_general_heading_01'], $bindings);
        $output = [
            'sections' => [
                [
                    'key' => 'hero',
                    'binding' => ['section_key' => 'hero_split_image'],
                    'props' => ['headline' => 'Welcome'],
                ],
            ],
        ];

        $errors = $validator->validate($output);
        $this->assertSame([], $errors);
    }
}
