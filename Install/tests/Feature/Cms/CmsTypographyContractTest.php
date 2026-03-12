<?php

namespace Tests\Feature\Cms;

use App\Models\Project;
use App\Models\Site;
use App\Models\SiteCustomFont;
use App\Models\SystemSetting;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsTypographyContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');

        config()->set('cms.typography.default_font_key', 'tbc-contractica');
        config()->set('cms.typography.fonts', [
            [
                'key' => 'tbc-contractica',
                'label' => 'TBC Contractica',
                'stack' => '"TBC Contractica", "Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif',
                'font_faces' => [
                    [
                        'font_family' => 'TBC Contractica',
                        'src_url' => '/fonts/TBCContractica-Regular.woff2',
                        'format' => 'woff2',
                        'font_weight' => 400,
                        'font_style' => 'normal',
                        'font_display' => 'swap',
                    ],
                ],
            ],
            [
                'key' => 'tbc-contractica-alt',
                'label' => 'TBC Contractica Alt',
                'stack' => '"TBC Contractica", "Noto Sans Georgian", "DejaVu Sans", "Segoe UI", system-ui, sans-serif',
                'font_faces' => [
                    [
                        'font_family' => 'TBC Contractica',
                        'src_url' => '/fonts/TBCContractica-Bold.woff2',
                        'format' => 'woff2',
                        'font_weight' => 700,
                        'font_style' => 'normal',
                        'font_display' => 'swap',
                    ],
                ],
            ],
        ]);
    }

    public function test_panel_typography_endpoint_returns_contract_and_allowlist(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createDraftProjectWithSite($owner);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.theme.typography.show', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('typography.font_key', 'tbc-contractica')
            ->assertJsonPath('typography.heading_font_key', 'tbc-contractica')
            ->assertJsonPath('typography.body_font_key', 'tbc-contractica')
            ->assertJsonPath('typography.button_font_key', 'tbc-contractica')
            ->assertJsonCount(2, 'available_fonts');
    }

    public function test_panel_typography_update_rejects_unsupported_font_keys(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createDraftProjectWithSite($owner);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.theme.typography.update', ['site' => $site->id]), [
                'font_key' => 'hacker-font',
            ])
            ->assertStatus(422);
    }

    public function test_panel_typography_update_persists_theme_contract(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createDraftProjectWithSite($owner);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.theme.typography.update', ['site' => $site->id]), [
                'font_key' => 'tbc-contractica-alt',
                'heading_font_key' => 'tbc-contractica',
                'body_font_key' => 'tbc-contractica-alt',
                'button_font_key' => 'tbc-contractica',
            ])
            ->assertOk()
            ->assertJsonPath('typography.font_key', 'tbc-contractica-alt')
            ->assertJsonPath('typography.heading_font_key', 'tbc-contractica')
            ->assertJsonPath('typography.body_font_key', 'tbc-contractica-alt')
            ->assertJsonPath('typography.button_font_key', 'tbc-contractica');

        $site->refresh();
        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $this->assertArrayHasKey('typography', $themeSettings);
        $this->assertSame('tbc-contractica-alt', $themeSettings['typography']['font_key'] ?? null);
        $this->assertSame('tbc-contractica', $themeSettings['typography']['heading_font_key'] ?? null);
        $this->assertSame('tbc-contractica-alt', $themeSettings['typography']['body_font_key'] ?? null);
        $this->assertSame('tbc-contractica', $themeSettings['typography']['button_font_key'] ?? null);
    }

    public function test_panel_can_upload_custom_font_and_select_it_for_typography(): void
    {
        Storage::fake('public');

        $plan = Plan::factory()->withFileStorage()->create();
        $owner = User::factory()->withPlan($plan)->create();
        [, $site] = $this->createDraftProjectWithSite($owner);

        $upload = $this->actingAs($owner)
            ->post(route('panel.sites.theme.fonts.upload', ['site' => $site->id]), [
                'file' => UploadedFile::fake()->create('BrandSans.woff2', 80, 'font/woff2'),
                'label' => 'Brand Sans',
                'font_family' => 'Brand Sans',
                'font_weight' => 500,
                'font_style' => 'normal',
            ]);

        $upload->assertCreated()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('font.source_type', 'custom')
            ->assertJsonPath('font.label', 'Brand Sans')
            ->assertJsonPath('font.font_weight', 500);

        $fontKey = (string) $upload->json('font.key');
        $this->assertNotSame('', $fontKey);

        $customFont = SiteCustomFont::query()
            ->where('site_id', $site->id)
            ->where('key', $fontKey)
            ->first();

        $this->assertNotNull($customFont);
        Storage::disk('public')->assertExists((string) $customFont->storage_path);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.theme.typography.update', ['site' => $site->id]), [
                'font_key' => $fontKey,
                'heading_font_key' => $fontKey,
                'body_font_key' => $fontKey,
                'button_font_key' => $fontKey,
            ])
            ->assertOk()
            ->assertJsonPath('typography.font_key', $fontKey)
            ->assertJsonPath('typography.font_faces.0.font_family', 'Brand Sans')
            ->assertJsonPath('typography.font_faces.0.format', 'woff2');

        $site->refresh();
        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $this->assertSame($fontKey, $themeSettings['typography']['font_key'] ?? null);
    }

    public function test_plan_font_allowlist_limits_available_fonts_and_falls_back_to_allowed_font(): void
    {
        $plan = Plan::factory()->create([
            'allowed_typography_font_keys' => ['tbc-contractica'],
        ]);
        $owner = User::factory()->withPlan($plan)->create();
        [, $site] = $this->createDraftProjectWithSite($owner);

        $site->update([
            'theme_settings' => [
                'typography' => [
                    'font_key' => 'tbc-contractica-alt',
                    'heading_font_key' => 'tbc-contractica-alt',
                    'body_font_key' => 'tbc-contractica-alt',
                    'button_font_key' => 'tbc-contractica-alt',
                ],
            ],
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.theme.typography.show', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonCount(1, 'available_fonts')
            ->assertJsonPath('available_fonts.0.key', 'tbc-contractica')
            ->assertJsonPath('typography.font_key', 'tbc-contractica')
            ->assertJsonPath('typography.heading_font_key', 'tbc-contractica')
            ->assertJsonPath('typography.body_font_key', 'tbc-contractica')
            ->assertJsonPath('typography.button_font_key', 'tbc-contractica')
            ->assertJsonPath('typography_policy.allowed_font_keys.0', 'tbc-contractica');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.theme.typography.update', ['site' => $site->id]), [
                'font_key' => 'tbc-contractica-alt',
                'heading_font_key' => 'tbc-contractica-alt',
                'body_font_key' => 'tbc-contractica-alt',
                'button_font_key' => 'tbc-contractica-alt',
            ])
            ->assertStatus(422);
    }

    public function test_plan_can_disable_custom_font_uploads(): void
    {
        Storage::fake('public');

        $plan = Plan::factory()
            ->withFileStorage()
            ->withCustomFonts(false)
            ->create();

        $owner = User::factory()->withPlan($plan)->create();
        [, $site] = $this->createDraftProjectWithSite($owner);

        $this->actingAs($owner)
            ->post(route('panel.sites.theme.fonts.upload', ['site' => $site->id]), [
                'file' => UploadedFile::fake()->create('BlockedFont.woff2', 50, 'font/woff2'),
                'label' => 'Blocked Font',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'site_entitlement_required')
            ->assertJsonPath('feature', 'typography')
            ->assertJsonPath('reason', 'custom_fonts_not_enabled');
    }

    public function test_upload_rejects_disallowed_custom_font_key_when_plan_allowlist_is_configured(): void
    {
        Storage::fake('public');

        $plan = Plan::factory()
            ->withFileStorage()
            ->withCustomFonts(true)
            ->withAllowedTypographyFontKeys(['tbc-contractica', 'approved-brand'])
            ->create();

        $owner = User::factory()->withPlan($plan)->create();
        [, $site] = $this->createDraftProjectWithSite($owner);

        $this->actingAs($owner)
            ->post(route('panel.sites.theme.fonts.upload', ['site' => $site->id]), [
                'file' => UploadedFile::fake()->create('BrandSans.woff2', 80, 'font/woff2'),
                'label' => 'Brand Sans',
                'font_key' => 'brand-sans',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'site_entitlement_required')
            ->assertJsonPath('feature', 'typography')
            ->assertJsonPath('reason', 'typography_font_key_not_allowed')
            ->assertJsonPath('font_key', 'brand-sans')
            ->assertJsonPath('allowed_font_keys.0', 'tbc-contractica');

        $this->assertDatabaseCount('site_custom_fonts', 0);
    }

    public function test_custom_fonts_are_site_scoped_and_cannot_be_deleted_cross_tenant(): void
    {
        Storage::fake('public');

        $plan = Plan::factory()->withFileStorage()->create();

        $ownerA = User::factory()->withPlan($plan)->create();
        [, $siteA] = $this->createDraftProjectWithSite($ownerA);

        $upload = $this->actingAs($ownerA)
            ->post(route('panel.sites.theme.fonts.upload', ['site' => $siteA->id]), [
                'file' => UploadedFile::fake()->create('TenantA.woff2', 90, 'font/woff2'),
                'label' => 'Tenant A Font',
            ])
            ->assertCreated();

        $fontId = (int) $upload->json('font.custom_font_id');
        $fontKey = (string) $upload->json('font.key');

        $ownerB = User::factory()->withPlan($plan)->create();
        [, $siteB] = $this->createDraftProjectWithSite($ownerB);

        $this->actingAs($ownerB)
            ->getJson(route('panel.sites.theme.typography.show', ['site' => $siteB->id]))
            ->assertOk()
            ->assertJsonMissing(['key' => $fontKey]);

        $this->actingAs($ownerB)
            ->deleteJson(route('panel.sites.theme.fonts.destroy', ['site' => $siteB->id, 'font' => $fontId]))
            ->assertNotFound();

        $this->assertDatabaseHas('site_custom_fonts', [
            'id' => $fontId,
            'site_id' => $siteA->id,
        ]);
    }

    public function test_public_typography_and_runtime_bridge_include_selected_contract(): void
    {
        $owner = User::factory()->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner, 'public');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.theme.typography.update', ['site' => $site->id]), [
                'font_key' => 'tbc-contractica-alt',
                'heading_font_key' => 'tbc-contractica-alt',
                'body_font_key' => 'tbc-contractica',
                'button_font_key' => 'tbc-contractica-alt',
            ])
            ->assertOk();

        $this->getJson(route('public.sites.theme.typography', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('typography.font_key', 'tbc-contractica-alt')
            ->assertJsonPath('typography.heading_font_key', 'tbc-contractica-alt')
            ->assertJsonPath('typography.body_font_key', 'tbc-contractica')
            ->assertJsonPath('typography.button_font_key', 'tbc-contractica-alt')
            ->assertJsonPath('typography.font_faces.0.font_family', 'TBC Contractica')
            ->assertJsonPath('typography.font_faces.0.format', 'woff2');

        $this->getJson(route('public.sites.settings', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('typography.font_key', 'tbc-contractica-alt')
            ->assertJsonPath('theme_token_layers.version', 1)
            ->assertJsonPath('theme_token_layers.effective.theme_tokens.typography.font_key', 'tbc-contractica-alt');

        $this->getJson(route('app.serve', [
            'project' => $project->id,
            'path' => '__cms/bootstrap',
        ]).'?slug=home')
            ->assertOk()
            ->assertJsonPath('typography.font_key', 'tbc-contractica-alt')
            ->assertJsonPath('theme_token_layers.version', 1)
            ->assertJsonPath('theme_token_layers.effective.theme_tokens.typography.font_key', 'tbc-contractica-alt')
            ->assertJsonPath('site.typography.font_key', 'tbc-contractica-alt')
            ->assertJsonPath('meta.endpoints.typography', route('public.sites.theme.typography', ['site' => $site->id]));
    }

    public function test_public_typography_endpoint_hides_private_site_for_non_owner(): void
    {
        $owner = User::factory()->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner, 'private');

        $url = route('public.sites.theme.typography', ['site' => $site->id]);

        $this->getJson($url)->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();

        $this->actingAs($owner)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('typography.font_key', 'tbc-contractica')
            ->assertJsonPath('typography.button_font_key', 'tbc-contractica');
    }

    private function createDraftProjectWithSite(User $owner): array
    {
        $project = Project::factory()->for($owner)->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }

    private function createPublishedProjectWithSite(User $owner, string $visibility): array
    {
        $factory = Project::factory()->for($owner);
        $subdomain = strtolower(Str::random(10));

        if ($visibility === 'private') {
            $factory = $factory->privatePublished($subdomain);
        } else {
            $factory = $factory->published($subdomain);
        }

        $project = $factory->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}
