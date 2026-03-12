<?php

namespace Tests\Feature\Cms;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CmsAiGenerationPayloadValidationCommandTest extends TestCase
{
    public function test_command_validates_input_payload_file_successfully(): void
    {
        $path = $this->writeTempJson('ai-input-valid.json', $this->validInputPayload());

        $this->artisan('cms:ai-validate-payload', [
            'contract' => 'input',
            'file' => $path,
        ])->assertExitCode(0);
    }

    public function test_command_returns_failure_and_json_report_for_invalid_output_payload(): void
    {
        $path = $this->writeTempJson('ai-output-invalid.json', [
            'schema_version' => 2,
        ]);

        $this->artisan('cms:ai-validate-payload', [
            'contract' => 'output',
            'file' => $path,
            '--json' => true,
        ])->assertExitCode(1);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeTempJson(string $filename, array $payload): string
    {
        $dir = storage_path('framework/testing/cms-ai-validator');
        File::ensureDirectoryExists($dir);

        $path = $dir.'/'.$filename;
        File::put($path, (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function validInputPayload(): array
    {
        return [
            'schema_version' => 1,
            'request' => [
                'mode' => 'generate_site',
                'prompt' => 'Generate a storefront homepage and catalog structure.',
                'locale' => 'en',
                'target' => [
                    'route_scope' => 'site',
                ],
            ],
            'platform_context' => [
                'project' => [
                    'id' => 1,
                    'name' => 'Demo Project',
                ],
                'site' => [
                    'id' => 1,
                    'name' => 'Demo Site',
                    'status' => 'draft',
                    'locale' => 'en',
                    'theme_settings' => ['colors' => ['primary' => '#000']],
                ],
                'template_blueprint' => [
                    'template_id' => 1,
                    'template_slug' => 'webu-shop-01',
                    'default_pages' => [],
                    'default_sections' => ['home' => []],
                ],
                'site_settings_snapshot' => [
                    'site' => [
                        'id' => 1,
                        'project_id' => 1,
                        'name' => 'Demo Site',
                        'status' => 'draft',
                        'locale' => 'en',
                        'theme_settings' => ['colors' => ['primary' => '#000']],
                    ],
                    'typography' => ['base_font_family' => 'Inter'],
                    'global_settings' => [
                        'logo_media_id' => null,
                        'logo_asset_url' => null,
                        'contact_json' => [],
                        'social_links_json' => [],
                        'analytics_ids_json' => [],
                    ],
                ],
                'section_library' => [],
                'module_registry' => [
                    'site_id' => 1,
                    'project_id' => 1,
                    'modules' => [],
                    'summary' => [
                        'total' => 0,
                        'available' => 0,
                        'disabled' => 0,
                        'not_entitled' => 0,
                    ],
                ],
                'module_entitlements' => [
                    'site_id' => 1,
                    'project_id' => 1,
                    'features' => ['cms' => true],
                    'modules' => ['ecommerce' => true],
                    'reasons' => ['ecommerce' => null],
                    'plan' => null,
                ],
            ],
            'meta' => [
                'request_id' => 'req_test_command',
                'created_at' => '2026-02-24T10:00:00Z',
                'source' => 'internal_tool',
            ],
        ];
    }
}
