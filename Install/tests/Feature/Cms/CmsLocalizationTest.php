<?php

namespace Tests\Feature\Cms;

use App\Models\GlobalSetting;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_public_page_endpoint_uses_ka_as_primary_and_en_as_fallback(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();
        $this->setHomePageContent($site, [
            'locales' => [
                'ka' => ['sections' => [['type' => 'hero', 'props' => ['headline' => 'home-ka']]]],
                'en' => ['sections' => [['type' => 'hero', 'props' => ['headline' => 'home-en']]]],
            ],
        ]);

        $url = route('public.sites.page', ['site' => $site->id, 'slug' => 'home']);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('locale', 'ka')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'home-ka');

        $this->getJson("{$url}?locale=en")
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'home-en');

        $this->getJson("{$url}?locale=fr")
            ->assertOk()
            ->assertJsonPath('locale', 'ka')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'home-ka');

        $this->setHomePageContent($site, [
            'locales' => [
                'en' => ['sections' => [['type' => 'hero', 'props' => ['headline' => 'home-en-only']]]],
            ],
        ]);

        $this->getJson("{$url}?locale=fr")
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'home-en-only');
    }

    public function test_public_menu_endpoint_applies_ka_en_fallback_policy(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();

        $site->menus()->updateOrCreate(
            ['key' => 'header'],
            [
                'items_json' => [
                    'locales' => [
                        'ka' => [['label' => 'menu-ka', 'url' => '/']],
                        'en' => [['label' => 'menu-en', 'url' => '/']],
                    ],
                ],
            ]
        );

        $url = route('public.sites.menu', ['site' => $site->id, 'key' => 'header']);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('locale', 'ka')
            ->assertJsonPath('items_json.0.label', 'menu-ka');

        $this->getJson("{$url}?locale=en")
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('items_json.0.label', 'menu-en');

        $this->getJson("{$url}?locale=de")
            ->assertOk()
            ->assertJsonPath('locale', 'ka')
            ->assertJsonPath('items_json.0.label', 'menu-ka');
    }

    public function test_public_settings_endpoint_localizes_contact_payload_and_fallbacks(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();

        $global = GlobalSetting::firstOrCreate(['site_id' => $site->id]);
        $global->update([
            'contact_json' => [
                'locales' => [
                    'ka' => ['title' => 'contact-ka', 'email' => 'ka@example.com'],
                    'en' => ['title' => 'contact-en', 'email' => 'en@example.com'],
                ],
            ],
        ]);

        $url = route('public.sites.settings', ['site' => $site->id]);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('locale', 'ka')
            ->assertJsonPath('global_settings.contact_json.title', 'contact-ka');

        $this->getJson("{$url}?locale=en")
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('global_settings.contact_json.title', 'contact-en');

        $this->getJson("{$url}?locale=es")
            ->assertOk()
            ->assertJsonPath('locale', 'ka')
            ->assertJsonPath('global_settings.contact_json.title', 'contact-ka');

        $global->update([
            'contact_json' => [
                'locales' => [
                    'en' => ['title' => 'contact-en-only', 'email' => 'en-only@example.com'],
                ],
            ],
        ]);

        $this->getJson("{$url}?locale=es")
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('global_settings.contact_json.title', 'contact-en-only');
    }

    public function test_runtime_bridge_returns_localized_page_payload_with_fallback(): void
    {
        [$project, $site] = $this->createPublishedProjectWithSite();

        $this->setHomePageContent($site, [
            'locales' => [
                'ka' => ['sections' => [['type' => 'hero', 'props' => ['headline' => 'bridge-ka']]]],
                'en' => ['sections' => [['type' => 'hero', 'props' => ['headline' => 'bridge-en']]]],
            ],
        ]);

        $bridgeUrl = route('app.serve', [
            'project' => $project->id,
            'path' => '__cms/bootstrap',
        ]);

        $this->getJson("{$bridgeUrl}?slug=home&locale=en")
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'bridge-en')
            ->assertJsonPath('meta.requested_locale', 'en')
            ->assertJsonPath('meta.resolved_locale', 'en')
            ->assertJsonPath('meta.fallback_locale', 'ka');

        $this->setHomePageContent($site, [
            'locales' => [
                'en' => ['sections' => [['type' => 'hero', 'props' => ['headline' => 'bridge-en-only']]]],
            ],
        ]);

        $this->getJson("{$bridgeUrl}?slug=home&locale=fr")
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'bridge-en-only')
            ->assertJsonPath('meta.requested_locale', 'fr')
            ->assertJsonPath('meta.resolved_locale', 'en');
    }

    private function createPublishedProjectWithSite(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }

    private function setHomePageContent(Site $site, array $payload): void
    {
        $page = $site->pages()->where('slug', 'home')->firstOrFail();
        $page->update(['status' => 'published']);

        $revision = $page->revisions()
            ->where('site_id', $site->id)
            ->latest('version')
            ->firstOrFail();

        $revision->update([
            'content_json' => $payload,
            'published_at' => now(),
        ]);
    }
}
