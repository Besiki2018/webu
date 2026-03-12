<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class LegacyReferenceArchiveEcommerceFullIntegrationThemeSiteSettingsReconciliationAr01SyncTest extends TestCase
{
    public function test_ar_01_legacy_theme_site_settings_reconciliation_audit_locks_tokens_presets_and_template_storage_truthfully(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_THEME_SITE_SETTINGS_RECONCILIATION_AUDIT_AR_01_2026_02_25.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $webRoutesPath = base_path('routes/web.php');
        $panelSiteControllerPath = base_path('app/Http/Controllers/Cms/PanelSiteController.php');
        $panelBuilderControllerPath = base_path('app/Http/Controllers/Cms/PanelBuilderController.php');
        $panelSiteServicePath = base_path('app/Cms/Services/CmsPanelSiteService.php');
        $themeLayerResolverPath = base_path('app/Services/CmsThemeTokenLayerResolver.php');
        $themeTokenValidatorPath = base_path('app/Services/CmsThemeTokenValueValidator.php');
        $siteProvisioningPath = base_path('app/Services/SiteProvisioningService.php');
        $templateImportServicePath = base_path('app/Services/TemplateImportService.php');
        $themePresetsConfigPath = base_path('config/theme-presets.php');

        $siteModelPath = base_path('app/Models/Site.php');
        $pageModelPath = base_path('app/Models/Page.php');
        $pageRevisionModelPath = base_path('app/Models/PageRevision.php');
        $coreCmsMigrationPath = base_path('database/migrations/2026_02_20_050000_create_cms_core_tables.php');

        $themeLayerResolverTestPath = base_path('tests/Unit/CmsThemeTokenLayerResolverTest.php');
        $themeTokenValidatorTestPath = base_path('tests/Unit/CmsThemeTokenValueValidatorTest.php');
        $cmsTypographyContractTestPath = base_path('tests/Feature/Cms/CmsTypographyContractTest.php');
        $cmsLocalizationTestPath = base_path('tests/Feature/Cms/CmsPanelLocalizationManagementTest.php');
        $manualBuilderModeTestPath = base_path('tests/Feature/Cms/ManualBuilderModeTest.php');
        $storefrontTemplatesContractPath = base_path('resources/js/Pages/Project/__tests__/CmsStorefrontPageTemplates.contract.test.ts');
        $reusablePresetsContractPath = base_path('resources/js/Pages/Project/__tests__/CmsReusableStylePresetsParity.contract.test.ts');
        $templateStorefrontE2eFlowTestPath = base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $cmsPath,
            $webRoutesPath,
            $panelSiteControllerPath,
            $panelBuilderControllerPath,
            $panelSiteServicePath,
            $themeLayerResolverPath,
            $themeTokenValidatorPath,
            $siteProvisioningPath,
            $templateImportServicePath,
            $themePresetsConfigPath,
            $siteModelPath,
            $pageModelPath,
            $pageRevisionModelPath,
            $coreCmsMigrationPath,
            $themeLayerResolverTestPath,
            $themeTokenValidatorTestPath,
            $cmsTypographyContractTestPath,
            $cmsLocalizationTestPath,
            $manualBuilderModeTestPath,
            $storefrontTemplatesContractPath,
            $reusablePresetsContractPath,
            $templateStorefrontE2eFlowTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $cms = File::get($cmsPath);
        $webRoutes = File::get($webRoutesPath);
        $panelSiteController = File::get($panelSiteControllerPath);
        $panelBuilderController = File::get($panelBuilderControllerPath);
        $panelSiteService = File::get($panelSiteServicePath);
        $themeLayerResolver = File::get($themeLayerResolverPath);
        $themeTokenValidator = File::get($themeTokenValidatorPath);
        $siteProvisioning = File::get($siteProvisioningPath);
        $templateImportService = File::get($templateImportServicePath);
        $themePresetsConfig = File::get($themePresetsConfigPath);
        $siteModel = File::get($siteModelPath);
        $pageModel = File::get($pageModelPath);
        $pageRevisionModel = File::get($pageRevisionModelPath);
        $coreCmsMigration = File::get($coreCmsMigrationPath);
        $themeLayerResolverTest = File::get($themeLayerResolverTestPath);
        $themeTokenValidatorTest = File::get($themeTokenValidatorTestPath);
        $cmsTypographyContractTest = File::get($cmsTypographyContractTestPath);
        $cmsLocalizationTest = File::get($cmsLocalizationTestPath);
        $manualBuilderModeTest = File::get($manualBuilderModeTestPath);
        $storefrontTemplatesContract = File::get($storefrontTemplatesContractPath);
        $reusablePresetsContract = File::get($reusablePresetsContractPath);
        $templateStorefrontE2eFlowTest = File::get($templateStorefrontE2eFlowTestPath);

        foreach ([
            '1.1 Theme Structure',
            'Create a Theme entity per store:',
            'tokens (colors, typography, spacing, radii, shadows)',
            'component_presets (default styles for Button, Card, Input, etc.)',
            'page_templates (Home, Product listing, Product detail, Cart, Checkout, Account, Orders)',
            'theme_settings(store_id, tokens_json, presets_json, templates_json)',
            '1.2 Theme Tokens (must exist)',
            'Colors: primary, secondary, accent, background, surface, text, muted, success, warning, danger',
            'Typography: font families, H1-H6, body, small, button',
            'Spacing scale: 0..64 (or similar)',
            'Radius presets: none/sm/md/lg/pill',
            'Shadow presets: sm/md/lg',
            'Breakpoints: desktop/tablet/mobile',
            '1.3 Theme Builder UI',
            'Color palette manager',
            'Typography presets manager',
            'Spacing/radius/shadow presets',
            'Apply preset to selected element',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '### AR — Legacy Reference Archive (Pre-CODEX-PROMPT Ecommerce Full Integration Spec)',
            '- `AR-01` (`DONE`, `P0`)',
            'LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_THEME_SITE_SETTINGS_RECONCILIATION_AUDIT_AR_01_2026_02_25.md',
            'LegacyReferenceArchiveEcommerceFullIntegrationThemeSiteSettingsReconciliationAr01SyncTest.php',
            '`✅` `AR-01` legacy theme/site-settings scope reconciled with current split model',
            '`✅` template storage path verified as equivalent split implementation',
            '`⚠️` source exact `Theme` entity + `theme_settings(store_id, tokens_json, presets_json, templates_json)` table shape is not implemented as-is',
            '`⚠️` source exact UI (`Typography presets manager` for H1-H6/body/small/button, broad color token manager) is partially covered',
            '`🧪` AR-01 reconciliation sync lock added (legacy archive theme/site-settings closure state)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Scope',
            '## Closure Rationale (Why `AR-01` Can Be `DONE`)',
            '## Theme Structure Reconciliation Matrix',
            'theme_settings(store_id, tokens_json, presets_json, templates_json)',
            '## Theme Tokens Parity Matrix (`1.2 Theme Tokens (must exist)`)',
            '`colors`',
            '`typography`',
            '`spacing`',
            '`radii`',
            '`shadows`',
            '`breakpoints`',
            '## Site Settings UI Capability Matrix (`1.3 Theme Builder UI`)',
            'Color palette manager',
            'Typography presets manager',
            'Spacing/radius/shadow presets',
            'Apply preset to selected element',
            '`advanced.component_presets`',
            '## Template Storage Path Verification / Gap-Tasking',
            '`templates.metadata.default_pages`',
            '`pages` + `page_revisions`',
            '`AR-03`',
            '## DoD Verdict (`AR-01`)',
            'Conclusion: `AR-01` is `DONE`.',
            '## Follow-up Mapping (Non-blocking for `AR-01` Closure)',
            '`RS-00-02`',
            '`AR-02`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'CANONICAL_STOREFRONT_PAGE_TEMPLATE_PRESETS',
            "key: 'home'",
            "key: 'product-listing'",
            "key: 'product-detail'",
            "key: 'cart'",
            "key: 'checkout'",
            "key: 'account'",
            "key: 'orders-list'",
            "key: 'order-detail'",
            "const handleSiteSettingsSave = async () => {",
            "const handleTypographySave = async () => {",
            'await axios.put(`/panel/sites/${site.id}/settings`, {',
            'await axios.put(`/panel/sites/${site.id}/theme/typography`, {',
            "CardTitle>{t('Theme presets & tokens')}",
            "CardTitle>{t('Typography & Layout')}",
            "CardTitle>{t('Branding')}",
            "Canonical Radii",
            "Canonical Spacing",
            "Canonical Shadows",
            "Canonical Breakpoints",
            "component_presets: {",
            "title: 'Advanced: Button Preset (Token-backed)'",
            "title: 'Advanced: Card Preset (Token-backed)'",
            "title: 'Advanced: Input Preset (Token-backed)'",
            'function applyGeneralFoundationComponentStylePresetsPreview(',
            "'data-webu-builder-component-presets'",
            'theme_token_layers',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            "Route::get('/settings'",
            "->name('panel.sites.settings.show')",
            "Route::put('/settings'",
            "->name('panel.sites.settings.update')",
            "Route::get('/theme/typography'",
            "->name('panel.sites.theme.typography.show')",
            "Route::put('/theme/typography'",
            "->name('panel.sites.theme.typography.update')",
            "Route::post('/theme/fonts'",
            "->name('panel.sites.theme.fonts.upload')",
            "Route::get('/{site}/theme/typography'",
            "->name('public.sites.theme.typography')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            'class PanelSiteController extends Controller',
            'public function settings(Request $request, Site $site): JsonResponse',
            'public function updateSettings(Request $request, Site $site): JsonResponse',
            'public function typography(Site $site): JsonResponse',
            'public function updateTypography(Request $request, Site $site): JsonResponse',
            'public function uploadCustomFont(Request $request, Site $site): JsonResponse',
        ] as $needle) {
            $this->assertStringContainsString($needle, $panelSiteController);
        }

        foreach ([
            'class CmsPanelSiteService implements CmsPanelSiteServiceContract',
            '\'theme_token_layers\' => $this->themeTokenLayers->resolveForSite($site)',
            '$this->themeTokenValidator->assertValidThemeSettings($nextThemeSettings);',
            'private function normalizeCanonicalThemeTokenSettings(array $themeSettings): array',
            '$next[\'theme_tokens\'] = $themeTokens;',
        ] as $needle) {
            $this->assertStringContainsString($needle, $panelSiteService);
        }

        foreach ([
            'class CmsThemeTokenLayerResolver',
            'Build canonical theme token layering payload from current Webu storage.',
            "'layer_order' => ['template_defaults', 'preset_defaults', 'site_overrides']",
            'private function resolvePresetSelection(array $siteThemeSettings, ?Project $project): array',
            'private function buildPresetDefaultsLayer(string $presetKey): array',
            'private function buildTemplateDefaultsLayer(?Template $template): array',
        ] as $needle) {
            $this->assertStringContainsString($needle, $themeLayerResolver);
        }

        foreach ([
            'class CmsThemeTokenValueValidator',
            "'theme_tokens.colors'",
            "'theme_tokens.radii'",
            "'theme_tokens.spacing'",
            "'theme_tokens.breakpoints'",
            "'theme_tokens.shadows'",
            'Invalid canonical theme token values.',
            "'theme_token_validation_failed'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $themeTokenValidator);
        }

        foreach ([
            'public function applyTemplate(Request $request, Site $site): JsonResponse',
            "'theme_preset' => ['nullable', 'string'",
            '$project->update($payload);',
            '$this->siteProvisioning->provisionForProject($project->fresh());',
            'public function updateStyles(Request $request, Site $site): JsonResponse',
            "'theme_settings' => ['nullable', 'array']",
            '\'theme_settings\' => $site->fresh()->theme_settings ?? [],',
        ] as $needle) {
            $this->assertStringContainsString($needle, $panelBuilderController);
        }

        foreach ([
            'Arr::get($metadata, \'default_pages\', [])',
            'Arr::get($metadata, \'default_sections\', [])',
            '$this->repository->createRevision($site, $page, [',
            '\'content_json\' => $this->bindContentSections($pageConfig[\'content\'])',
            'private function resolvePageBlueprints(Site $site, Project $project): array',
        ] as $needle) {
            $this->assertStringContainsString($needle, $siteProvisioning);
        }

        foreach ([
            'function readGeneralComponentPresetSelections(value) {',
            'function applyGeneralComponentStylePresetsRuntime(container, advancedProps) {',
            "'data-webu-runtime-component-presets'",
            "'button:' + componentPresetSelections.button + ';card:' + componentPresetSelections.card + ';input:' + componentPresetSelections.input",
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateImportService);
        }

        foreach ([
            "'default' => [",
            "'arctic' => [",
            "'summer' => [",
            "'preview_colors' =>",
            "'light' => [",
            "'dark' => [",
            "'radius' =>",
        ] as $needle) {
            $this->assertStringContainsString($needle, $themePresetsConfig);
        }

        foreach ([
            "'theme_settings'",
            "'theme_settings' => 'array'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $siteModel);
        }
        foreach ([
            "Schema::create('sites'",
            "->json('theme_settings')->nullable();",
            "Schema::create('pages'",
            "Schema::create('page_revisions'",
            "->json('content_json');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $coreCmsMigration);
        }
        foreach ([
            'class Page extends Model',
            "'seo_title'",
            'public function revisions(): HasMany',
        ] as $needle) {
            $this->assertStringContainsString($needle, $pageModel);
        }
        foreach ([
            'class PageRevision extends Model',
            "'content_json' => 'array'",
            'public function page(): BelongsTo',
        ] as $needle) {
            $this->assertStringContainsString($needle, $pageRevisionModel);
        }

        foreach ([
            'test_it_builds_canonical_layers_with_template_preset_and_site_precedence',
            "'theme_preset.requested_key'",
            "'effective.theme_tokens.colors.primary'",
            "'effective_theme_settings.preset'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $themeLayerResolverTest);
        }

        foreach ([
            'test_it_accepts_valid_canonical_theme_token_groups',
            'test_it_reports_invalid_token_shapes_and_values',
            'theme_tokens.colors.modes.tablet',
            'theme_tokens.spacing.md',
            'theme_token_validation_failed',
        ] as $needle) {
            $this->assertStringContainsString($needle, $themeTokenValidatorTest);
        }

        foreach ([
            'test_panel_typography_endpoint_returns_contract_and_allowlist',
            "route('panel.sites.theme.typography.show'",
            'test_panel_typography_update_persists_theme_contract',
            "route('panel.sites.theme.typography.update'",
            'test_public_typography_and_runtime_bridge_include_selected_contract',
            "route('public.sites.theme.typography'",
            "'theme_token_layers.effective.theme_tokens.typography.font_key'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsTypographyContractTest);
        }

        foreach ([
            'test_panel_settings_update_rejects_invalid_canonical_theme_token_payload',
            "route('panel.sites.settings.update'",
            "'theme_token_validation_failed'",
            'theme_tokens.colors.modes.tablet',
            'theme_tokens.spacing.md',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsLocalizationTest);
        }

        foreach ([
            'test_manual_builder_templates_endpoint_returns_plan_available_templates',
            "route('panel.sites.builder.templates'",
            'test_manual_builder_can_apply_template_and_reset_pages',
            "route('panel.sites.builder.templates.apply'",
            'test_manual_builder_sections_and_styles_endpoints_mutate_site_state',
            "route('panel.sites.builder.styles.update'",
            "'theme_settings' => [",
        ] as $needle) {
            $this->assertStringContainsString($needle, $manualBuilderModeTest);
        }

        foreach ([
            'CMS storefront page template presets contracts',
            'CANONICAL_STOREFRONT_PAGE_TEMPLATE_PRESETS',
            "key: 'checkout'",
            "key: 'account'",
            "key: 'orders-list'",
            'handleCreatePageTemplatePresetChange',
            'templateSectionsPayload',
        ] as $needle) {
            $this->assertStringContainsString($needle, $storefrontTemplatesContract);
        }

        foreach ([
            'CMS reusable style presets parity contracts',
            'advanced component_presets',
            'data-webu-builder-component-presets',
            'data-webu-runtime-component-presets',
            'applyGeneralComponentStylePresetsRuntime',
        ] as $needle) {
            $this->assertStringContainsString($needle, $reusablePresetsContract);
        }

        foreach ([
            'ensurePublishedPage($site, $owner, \'checkout\'',
            'ensurePublishedPage($site, $owner, \'account\'',
            'ensurePublishedPage($site, $owner, \'orders\'',
            "'/checkout'",
            "'/account/orders'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateStorefrontE2eFlowTest);
        }
    }
}
