<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Services\TemplatePackExportService;
use App\Services\TemplatePackImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TemplatePackExportImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive required for template pack tests.');
        }
    }

    public function test_export_template_produces_expected_zip_structure(): void
    {
        $template = Template::create([
            'slug' => 'test-export-pack',
            'name' => 'Test Export Pack',
            'description' => 'For test',
            'category' => 'ecommerce',
            'version' => '1.0.0',
            'is_system' => false,
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => [['key' => 'hero', 'props' => []]]],
                    ['slug' => 'shop', 'title' => 'Shop', 'sections' => [['key' => 'product_grid', 'props' => []]]],
                    ['slug' => 'product', 'title' => 'Product', 'sections' => []],
                    ['slug' => 'cart', 'title' => 'Cart', 'sections' => []],
                    ['slug' => 'checkout', 'title' => 'Checkout', 'sections' => []],
                    ['slug' => 'contact', 'title' => 'Contact', 'sections' => []],
                ],
            ],
        ]);

        $service = app(TemplatePackExportService::class);
        $path = $service->exportTemplate($template);

        $this->assertFileExists($path);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $root = config('template_pack_export.zip_root', 'webu-template-pack');
        $this->assertNotFalse($zip->locateName($root.'/manifest.json'));
        $this->assertNotFalse($zip->locateName($root.'/layout/pages.json'));
        $this->assertNotFalse($zip->locateName($root.'/layout/theme.tokens.json'));
        $this->assertNotFalse($zip->locateName($root.'/presentation/css/tokens.css'));

        $zip->close();
        @unlink($path);
    }

    public function test_import_valid_pack_creates_template(): void
    {
        $template = Template::create([
            'slug' => 'test-import-source',
            'name' => 'Test Import Source',
            'description' => 'For test',
            'category' => 'ecommerce',
            'version' => '1.0.0',
            'is_system' => false,
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => [['key' => 'hero']]],
                    ['slug' => 'shop', 'title' => 'Shop', 'sections' => [['key' => 'product_grid']]],
                    ['slug' => 'product', 'title' => 'Product', 'sections' => []],
                    ['slug' => 'cart', 'title' => 'Cart', 'sections' => []],
                    ['slug' => 'checkout', 'title' => 'Checkout', 'sections' => []],
                    ['slug' => 'contact', 'title' => 'Contact', 'sections' => []],
                ],
            ],
        ]);

        $exportService = app(TemplatePackExportService::class);
        $exportPath = $exportService->exportTemplate($template);
        $this->assertFileExists($exportPath);

        $upload = new UploadedFile(
            $exportPath,
            'webu-template-pack.zip',
            'application/zip',
            null,
            true
        );

        $importService = app(TemplatePackImportService::class);
        $result = $importService->import($upload, 'Imported Test', 'imported-test-pack');

        $this->assertArrayHasKey('template', $result);
        $this->assertInstanceOf(Template::class, $result['template']);
        $this->assertSame('imported-test-pack', $result['template']->slug);
        $this->assertSame('Imported Test', $result['template']->name);

        $meta = $result['template']->metadata;
        $this->assertIsArray($meta);
        $this->assertArrayHasKey('default_pages', $meta);
        $this->assertNotEmpty($meta['default_pages']);

        @unlink($exportPath);
    }
}
