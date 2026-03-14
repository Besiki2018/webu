<?php

namespace Tests\Feature\Cms;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SiteProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetProviderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_asset_search_merges_and_ranks_results_from_multiple_providers(): void
    {
        config()->set('services.unsplash.access_key', 'test-unsplash');
        config()->set('services.unsplash.secret_key', 'test-unsplash-secret');
        config()->set('services.pexels.key', 'test-pexels');
        config()->set('services.freepik.key', 'test-freepik');

        Http::fake([
            'https://api.unsplash.com/search/photos*' => Http::response([
                'results' => [[
                    'id' => 'un-1',
                    'alt_description' => 'modern veterinary clinic reception',
                    'width' => 2400,
                    'height' => 1600,
                    'color' => '#dbeafe',
                    'urls' => [
                        'small' => 'https://images.unsplash.com/un-1-small',
                        'full' => 'https://images.unsplash.com/un-1-full',
                    ],
                    'links' => [
                        'download_location' => 'https://api.unsplash.com/photos/un-1/download',
                    ],
                    'user' => [
                        'name' => 'Unsplash Author',
                        'links' => [
                            'html' => 'https://unsplash.com/@author',
                        ],
                    ],
                ]],
            ], 200),
            'https://api.pexels.com/v1/search*' => Http::response([
                'photos' => [[
                    'id' => 22,
                    'alt' => 'vet clinic waiting room',
                    'width' => 1600,
                    'height' => 1100,
                    'avg_color' => '#e2e8f0',
                    'src' => [
                        'medium' => 'https://images.pexels.com/photos/22/medium.jpeg',
                        'large' => 'https://images.pexels.com/photos/22/large.jpeg',
                        'original' => 'https://images.pexels.com/photos/22/original.jpeg',
                    ],
                    'photographer' => 'Pexels Author',
                ]],
            ], 200),
            'https://api.freepik.com/v1/resources*' => Http::response([
                'data' => [[
                    'id' => 'fp-1',
                    'title' => 'veterinary illustration',
                    'content_type' => 'vector',
                    'author' => [
                        'name' => 'Freepik Author',
                    ],
                    'image' => [
                        'source' => [
                            'url' => 'https://img.freepik.com/free-vector/vet-illustration.jpg',
                            'width' => 1200,
                            'height' => 1200,
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson('/api/assets/search', [
            'query' => 'veterinary clinic',
            'limit' => 6,
            'orientation' => 'landscape',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('query', 'veterinary clinic');

        $results = $response->json('results');

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertSame('unsplash', $results[0]['provider']);
        $this->assertSame(['unsplash', 'pexels', 'freepik'], array_column($results, 'provider'));
    }

    public function test_asset_search_falls_back_to_remaining_providers_when_one_provider_is_misconfigured(): void
    {
        config()->set('services.unsplash.access_key', null);
        config()->set('services.unsplash.secret_key', null);
        config()->set('services.pexels.key', 'test-pexels');
        config()->set('services.freepik.key', 'test-freepik');

        Http::fake([
            'https://api.pexels.com/v1/search*' => Http::response([
                'photos' => [[
                    'id' => 22,
                    'alt' => 'vet clinic waiting room',
                    'width' => 1600,
                    'height' => 1100,
                    'src' => [
                        'medium' => 'https://images.pexels.com/photos/22/medium.jpeg',
                        'large' => 'https://images.pexels.com/photos/22/large.jpeg',
                        'original' => 'https://images.pexels.com/photos/22/original.jpeg',
                    ],
                    'photographer' => 'Pexels Author',
                ]],
            ], 200),
            'https://api.freepik.com/v1/resources*' => Http::response([
                'data' => [[
                    'id' => 'fp-1',
                    'title' => 'vet illustration',
                    'author' => [
                        'name' => 'Freepik Author',
                    ],
                    'image' => [
                        'source' => [
                            'url' => 'https://img.freepik.com/free-vector/vet-illustration.jpg',
                            'width' => 1200,
                            'height' => 1200,
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->postJson('/api/assets/search', [
            'query' => 'veterinary clinic',
            'limit' => 6,
        ]);

        $response->assertOk();
        $this->assertSame(['pexels', 'freepik'], array_column($response->json('results'), 'provider'));
    }

    public function test_asset_search_returns_a_clear_error_when_no_provider_is_configured(): void
    {
        config()->set('services.unsplash.access_key', null);
        config()->set('services.unsplash.secret_key', null);
        config()->set('services.pexels.key', null);
        config()->set('services.freepik.key', null);

        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->postJson('/api/assets/search', [
            'query' => 'veterinary clinic',
            'limit' => 6,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Unsplash API key not configured (services.unsplash.access_key).');
    }

    public function test_asset_import_downloads_and_stores_image_with_stock_provenance(): void
    {
        Storage::fake('public');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X8XQAAAAASUVORK5CYII=');
        Http::fake([
            'https://images.pexels.com/*' => Http::response($png ?: '', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $user = User::factory()->create(['role' => 'admin']);
        $project = Project::factory()->for($user)->create();
        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));

        $response = $this->actingAs($user)->postJson('/api/assets/import', [
            'provider' => 'pexels',
            'image_id' => 'pexels-asset-1',
            'download_url' => 'https://images.pexels.com/photos/123/example.png',
            'project_id' => (string) $project->id,
            'title' => 'Vet clinic lobby',
            'author' => 'Pexels Author',
            'license' => 'Pexels License',
            'section_local_id' => 'hero-1',
            'component_key' => 'webu_general_hero_01',
            'prop_path' => 'image',
            'page_slug' => 'home',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('media.site_id', (string) $site->id)
            ->assertJsonPath('media.meta_json.stock_provider', 'pexels')
            ->assertJsonPath('media.meta_json.stock_image_id', 'pexels-asset-1')
            ->assertJsonPath('media.meta_json.section_local_id', 'hero-1')
            ->assertJsonPath('media.meta_json.prop_path', 'image');

        $path = (string) $response->json('media.path');
        Storage::disk('public')->assertExists($path);

        $this->assertStringStartsWith("projects/{$project->id}/assets/images/stock-pexels-asset-1-", $path);
    }
}
