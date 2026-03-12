<?php

namespace Tests\Feature\Admin;

use App\Models\SectionLibrary;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminSectionLibraryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_admin_can_view_sections_library_with_filters(): void
    {
        $admin = User::factory()->admin()->create();

        $hero = SectionLibrary::query()->create([
            'key' => 'hero_test_section',
            'category' => 'marketing',
            'schema_json' => [
                'type' => 'object',
                'properties' => [
                    'headline' => ['type' => 'string'],
                ],
            ],
            'enabled' => true,
        ]);

        SectionLibrary::query()->create([
            'key' => 'faq_disabled_section',
            'category' => 'content',
            'schema_json' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                ],
            ],
            'enabled' => false,
        ]);

        $response = $this->actingAs($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('admin.cms-sections', [
                'search' => 'hero',
                'status' => 'enabled',
            ]))
            ->assertOk();

        $this->assertSame('Admin/CmsSections', $response->json('component'));
        $this->assertCount(1, $response->json('props.sections'));
        $this->assertSame($hero->key, $response->json('props.sections.0.key'));
    }

    public function test_admin_can_upload_section_using_json_file(): void
    {
        $admin = User::factory()->admin()->create();

        $schema = json_encode([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'items' => ['type' => 'array'],
            ],
            '_meta' => [
                'label' => 'Uploaded Section',
                'design_variant' => 'custom/uploaded',
                'backend_updatable' => true,
                'bindings' => [
                    'title' => 'content.title',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $file = UploadedFile::fake()->createWithContent('section-schema.json', $schema);

        $this->actingAs($admin)
            ->post(route('admin.cms-sections.store'), [
                'key' => 'uploaded_section',
                'category' => 'business',
                'schema_file' => $file,
                'enabled' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sections_library', [
            'key' => 'uploaded_section',
            'category' => 'business',
            'enabled' => 1,
        ]);
    }

    public function test_admin_can_update_delete_and_import_default_section_pack(): void
    {
        $admin = User::factory()->admin()->create();

        $section = SectionLibrary::query()->create([
            'key' => 'custom_section',
            'category' => 'general',
            'schema_json' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                ],
            ],
            'enabled' => true,
        ]);

        $updatedSchema = json_encode([
            'type' => 'object',
            'properties' => [
                'headline' => ['type' => 'string'],
                'subtitle' => ['type' => 'string'],
            ],
            '_meta' => [
                'label' => 'Updated Custom Section',
                'design_variant' => 'custom/v2',
                'backend_updatable' => true,
                'bindings' => [
                    'headline' => 'content.headline',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->actingAs($admin)
            ->put(route('admin.cms-sections.update', $section->id), [
                'category' => 'marketing',
                'enabled' => false,
                'schema_json' => $updatedSchema,
            ])
            ->assertRedirect();

        $section->refresh();
        $this->assertSame('marketing', $section->category);
        $this->assertFalse((bool) $section->enabled);
        $this->assertSame('Updated Custom Section', data_get($section->schema_json, '_meta.label'));

        $this->actingAs($admin)
            ->post(route('admin.cms-sections.import-defaults'))
            ->assertRedirect();

        $this->assertGreaterThanOrEqual(30, SectionLibrary::query()->count());

        $this->actingAs($admin)
            ->delete(route('admin.cms-sections.destroy', $section->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('sections_library', [
            'id' => $section->id,
        ]);
    }

    public function test_non_admin_cannot_access_section_library_admin_routes(): void
    {
        $user = User::factory()->create();
        $section = SectionLibrary::query()->create([
            'key' => 'locked_section',
            'category' => 'content',
            'schema_json' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                ],
            ],
            'enabled' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.cms-sections'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.cms-sections.store'), [
                'key' => 'blocked_section',
                'category' => 'content',
                'schema_json' => '{}',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.cms-sections.update', $section->id), [
                'category' => 'marketing',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.cms-sections.import-defaults'))
            ->assertForbidden();
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);
        $version = (string) ($middleware->version(Request::create('/')) ?? '');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
