<?php

namespace Tests\Feature\Cms;

use App\Models\Media;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsPublicAssetsCachingBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_public_site_asset_route_serves_long_cache_cors_and_media_mime_headers(): void
    {
        $owner = User::factory()->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner);

        $relativePath = 'sites/'.$site->id.'/images/logo.webp';
        Storage::disk('public')->put($relativePath, 'RIFF....WEBP');

        $media = Media::query()->create([
            'site_id' => (string) $site->id,
            'path' => $relativePath,
            'mime' => 'image/webp',
            'size' => 12,
            'meta_json' => ['kind' => 'logo'],
        ]);

        $response = $this->get(route('public.sites.assets', ['site' => $site->id, 'path' => $media->path]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(User $owner): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}

