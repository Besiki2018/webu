<?php

namespace Tests\Feature\Cms;

use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsPanelLocalizationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_panel_can_save_locale_specific_page_revisions(): void
    {
        [$owner, $site] = $this->createOwnerAndSite();
        $page = $site->pages()->where('slug', 'home')->firstOrFail();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.revisions.store', ['site' => $site->id, 'page' => $page->id]), [
                'locale' => 'ka',
                'content_json' => [
                    'sections' => [
                        ['type' => 'webu_hero_01', 'props' => ['headline' => 'მთავარი KA']],
                    ],
                ],
            ])->assertCreated();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.revisions.store', ['site' => $site->id, 'page' => $page->id]), [
                'locale' => 'en',
                'content_json' => [
                    'sections' => [
                        ['type' => 'webu_hero_01', 'props' => ['headline' => 'Home EN']],
                    ],
                ],
            ])->assertCreated();

        $this->actingAs($owner)
            ->getJson(route('panel.sites.pages.show', ['site' => $site->id, 'page' => $page->id]).'?locale=ka')
            ->assertOk()
            ->assertJsonPath('latest_revision.content_json.sections.0.props.headline', 'მთავარი KA');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.pages.show', ['site' => $site->id, 'page' => $page->id]).'?locale=en')
            ->assertOk()
            ->assertJsonPath('latest_revision.content_json.sections.0.props.headline', 'Home EN');
    }

    public function test_panel_can_save_locale_specific_menu_items(): void
    {
        [$owner, $site] = $this->createOwnerAndSite();

        $this->actingAs($owner)
            ->putJson(route('panel.sites.menus.update', ['site' => $site->id, 'key' => 'header']), [
                'locale' => 'ka',
                'items_json' => [
                    ['id' => 'ka-1', 'label' => 'მთავარი', 'url' => '/'],
                ],
            ])->assertOk();

        $this->actingAs($owner)
            ->putJson(route('panel.sites.menus.update', ['site' => $site->id, 'key' => 'header']), [
                'locale' => 'en',
                'items_json' => [
                    ['id' => 'en-1', 'label' => 'Home', 'url' => '/'],
                ],
            ])->assertOk();

        $this->actingAs($owner)
            ->getJson(route('panel.sites.menus.show', ['site' => $site->id, 'key' => 'header']).'?locale=ka')
            ->assertOk()
            ->assertJsonPath('items_json.0.label', 'მთავარი');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.menus.show', ['site' => $site->id, 'key' => 'header']).'?locale=en')
            ->assertOk()
            ->assertJsonPath('items_json.0.label', 'Home');
    }

    public function test_panel_settings_exposes_available_locales_and_ui_dictionary_by_locale(): void
    {
        [$owner, $site] = $this->createOwnerAndSite();

        $this->actingAs($owner)
            ->putJson(route('panel.sites.settings.update', ['site' => $site->id]), [
                'locale' => 'ka',
                'available_locales' => ['ka', 'en', 'de'],
                'translation_locale' => 'en',
                'ui_translations' => [
                    'checkout' => 'Checkout',
                    'add_to_cart' => 'Add to cart',
                ],
                'contact_json' => [
                    'email' => 'en@example.test',
                ],
            ])->assertOk();

        $settingsEnResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.settings.show', ['site' => $site->id]).'?locale=en')
            ->assertOk()
            ->assertJsonPath('global_settings.contact_json.email', 'en@example.test')
            ->assertJsonPath('localization.ui_translations.checkout', 'Checkout');

        $this->assertContains('ka', $settingsEnResponse->json('localization.available_locales', []));
        $this->assertContains('en', $settingsEnResponse->json('localization.available_locales', []));
        $this->assertContains('de', $settingsEnResponse->json('localization.available_locales', []));

        $settingsDeResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.settings.show', ['site' => $site->id]).'?locale=de')
            ->assertOk()
            ->assertJsonPath('localization.resolved_locale', 'de');

        $this->assertContains('de', $settingsDeResponse->json('localization.available_locales', []));
    }

    public function test_panel_settings_update_rejects_invalid_canonical_theme_token_payload(): void
    {
        [$owner, $site] = $this->createOwnerAndSite();

        $response = $this->actingAs($owner)
            ->putJson(route('panel.sites.settings.update', ['site' => $site->id]), [
                'theme_settings' => [
                    'theme_tokens' => [
                        'version' => 1,
                        'colors' => [
                            'modes' => [
                                'tablet' => [
                                    'background' => '#fff',
                                ],
                            ],
                        ],
                        'spacing' => [
                            'md' => ['nested' => 'bad'],
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'theme_token_validation_failed')
            ->assertJsonPath('theme_token_validation.valid', false);

        $paths = collect($response->json('theme_token_validation.errors', []))
            ->pluck('path')
            ->all();

        $this->assertContains('theme_tokens.colors.modes.tablet', $paths);
        $this->assertContains('theme_tokens.spacing.md', $paths);
    }

    /**
     * @return array{0: User, 1: Site}
     */
    private function createOwnerAndSite(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(8)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$owner, $site];
    }
}
