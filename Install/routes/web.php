<?php

use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\Admin\AdminCronjobController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminLanguageController;
use App\Http\Controllers\Admin\AdminOperationLogController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminPricingCatalogController;
use App\Http\Controllers\Admin\AdminProjectController;
use App\Http\Controllers\Admin\AdminReferralController;
use App\Http\Controllers\Admin\AdminSectionLibraryController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminTransactionController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AiProviderController;
use App\Http\Controllers\Admin\BuilderController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\AdminTemplateController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminComponentLibraryController;
use App\Http\Controllers\Admin\AdminUniversalCmsController;
use App\Http\Controllers\Admin\BugfixerController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppPreviewController;
use App\Http\Controllers\BuildCreditController;
use App\Http\Controllers\BuilderProxyController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Booking\PanelBookingController as PanelBookingController;
use App\Http\Controllers\Booking\PanelFinanceController as PanelBookingFinanceController;
use App\Http\Controllers\Booking\PublicBookingController as PublicBookingController;
use App\Http\Controllers\Booking\PanelServiceController as PanelBookingServiceController;
use App\Http\Controllers\Booking\PanelStaffController as PanelBookingStaffController;
use App\Http\Controllers\Cms\PanelMediaController;
use App\Http\Controllers\Cms\PanelBlogPostController;
use App\Http\Controllers\Cms\PanelLearningController;
use App\Http\Controllers\Cms\PanelMenuController;
use App\Http\Controllers\Cms\PanelModuleController;
use App\Http\Controllers\Cms\PanelNotificationController;
use App\Http\Controllers\Cms\PanelPageController;
use App\Http\Controllers\Cms\PanelTelemetryController;
use App\Http\Controllers\Cms\PanelBuilderController;
use App\Http\Controllers\Cms\PanelFormController;
use App\Http\Controllers\Cms\PanelSiteController;
use App\Http\Controllers\Cms\PublicSiteController;
use App\Http\Controllers\Cms\PublicFormController;
use App\Http\Controllers\Cms\PublicTelemetryController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\CreateController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Api\AiLayoutGeneratorController;
use App\Http\Controllers\Api\ReadyTemplatesController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\AiLayoutPlaygroundController;
use App\Http\Controllers\DesignSystemController;
use App\Http\Controllers\DesignTestsController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\Ecommerce\PanelCategoryController as PanelEcommerceCategoryController;
use App\Http\Controllers\Ecommerce\PanelAccountingController as PanelEcommerceAccountingController;
use App\Http\Controllers\Ecommerce\PanelAttributeController as PanelEcommerceAttributeController;
use App\Http\Controllers\Ecommerce\PanelDiscountController as PanelEcommerceDiscountController;
use App\Http\Controllers\Ecommerce\PanelGatewayController as PanelEcommerceGatewayController;
use App\Http\Controllers\Ecommerce\PanelInventoryController as PanelEcommerceInventoryController;
use App\Http\Controllers\Ecommerce\PanelOrderController as PanelEcommerceOrderController;
use App\Http\Controllers\Ecommerce\PanelProductController as PanelEcommerceProductController;
use App\Http\Controllers\Ecommerce\PanelRsController as PanelEcommerceRsController;
use App\Http\Controllers\Ecommerce\PanelRsSyncController as PanelEcommerceRsSyncController;
use App\Http\Controllers\Ecommerce\PanelShipmentController as PanelEcommerceShipmentController;
use App\Http\Controllers\Ecommerce\PanelShippingController as PanelEcommerceShippingController;
use App\Http\Controllers\Ecommerce\PublicStorefrontController as PublicEcommerceStorefrontController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\StorageServeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GenerateWebsiteController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RequirementCollectionController;
use App\Http\Controllers\TemplatePackController;
use App\Http\Controllers\ProjectCmsController;
use App\Http\Controllers\ProjectCustomDomainController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\ProjectFirebaseController;
use App\Http\Controllers\ProjectGenerationStatusController;
use App\Http\Controllers\ProjectBuilderRetrievalController;
use App\Http\Controllers\ProjectPublishController;
use App\Http\Controllers\ProjectSettingsController;
use App\Http\Controllers\ProjectWorkspaceController;
use App\Http\Controllers\ProjectAiContentPatchController;
use App\Http\Controllers\UnifiedWebuSiteAgentController;
use App\Http\Controllers\ProjectAiProjectEditController;
use App\Http\Controllers\ProjectGenerateSectionContentController;
use App\Http\Controllers\ProjectAiToolsController;
use App\Http\Controllers\ProjectComponentGeneratorController;
use App\Http\Controllers\ProjectSitePlannerController;
use App\Http\Controllers\ProjectThemeAssetController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ReferralTrackingController;
use App\Http\Controllers\BuilderPreviewController;
use App\Http\Controllers\TemplateLiveDemoController;
use App\Http\Controllers\UpgradeController;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\BuildCreditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Installation Routes (Before any DB-dependent routes)
|--------------------------------------------------------------------------
*/
Route::prefix('install')->middleware('not-installed')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('install');

    Route::middleware(\App\Http\Middleware\InstallationGuard::class)->group(function () {
        Route::get('requirements', [InstallController::class, 'requirements'])->name('install.requirements');
        Route::get('permissions', [InstallController::class, 'permissions'])->name('install.permissions');
        Route::get('database', [InstallController::class, 'database'])->name('install.database');
        Route::post('database', [InstallController::class, 'storeDatabase'])->name('install.database.store');
        Route::get('admin', [InstallController::class, 'admin'])->name('install.admin');
        Route::post('admin', [InstallController::class, 'storeAdmin'])->name('install.admin.store');
    });

    Route::get('completed', [InstallController::class, 'completed'])->name('install.completed');
});

/*
|--------------------------------------------------------------------------
| Upgrade Routes
|--------------------------------------------------------------------------
*/
Route::prefix('upgrade')->middleware('installed')->group(function () {
    Route::get('/', [UpgradeController::class, 'index'])->name('upgrade');
    Route::post('/', [UpgradeController::class, 'run'])->name('upgrade.run');
    Route::get('completed', [UpgradeController::class, 'completed'])->name('upgrade.completed');
});

/*
|--------------------------------------------------------------------------
| Documentation Route (Demo Mode Only)
|--------------------------------------------------------------------------
*/
if (config('app.env') === 'local' && config('app.demo')) {
    Route::get('documentation/{path?}', [DocumentationController::class, 'show'])
        ->where('path', '.*')
        ->name('documentation');
}

/*
|--------------------------------------------------------------------------
| Application Routes (require installation)
|--------------------------------------------------------------------------
*/
Route::middleware('installed')->group(function () {

    // Application up (replaces framework's closure-based /up for Ziggy compatibility)
    Route::get('/up', [HealthController::class, 'up']);

    // Storage file serving (replaces framework's closure-based storage route for Ziggy compatibility)
    Route::get('/storage/{path}', StorageServeController::class.'@__invoke')->where('path', '.*')->name('storage.local');

    // Design Guard PART 8 — Visual Test Generation (generated sites + scores; score < threshold → log)
    Route::get('/design-tests', [DesignTestsController::class, 'index'])
        ->middleware(['auth', 'verified', 'admin'])
        ->name('design-tests.index');

    // Component Playground — dev UI to inspect design-system components; not in sitemap, demo only
    Route::get('/design-system', [DesignSystemController::class, 'index'])
        ->name('design-system');

    // AI Layout Playground — prompt → layout JSON → render; auth required for Create project
    Route::get('/ai-layout-playground', [AiLayoutPlaygroundController::class, 'index'])
        ->middleware(['auth', 'verified'])
        ->name('ai-layout-playground');

    // Health endpoints for runtime monitoring
    Route::get('/health', [HealthController::class, 'index'])->name('health');
    Route::get('/health/details', [HealthController::class, 'details'])->name('health.details');
    Route::get('/health/metrics', [HealthController::class, 'metrics'])->name('health.metrics');

    // Webu Builder JSON preview (e.g. Ekka demo-8 conversion)
    Route::get('/builder-preview/ekka-demo-8', [BuilderPreviewController::class, 'ekkaDemo8'])->name('builder-preview.ekka-demo-8');

    // Static live demo pages for template previews (SPA fallback enabled)
    Route::get('/template-demos/{templateSlug}/{path?}', [TemplateLiveDemoController::class, 'show'])
        ->where('path', '.*')
        ->name('template-demos.show');

    // Fallback route for imported themes when `public/themes/{slug}` files are missing.
    // If files exist, the web server serves them directly and this route is bypassed.
    Route::get('/themes/{templateSlug}/{path?}', [TemplateLiveDemoController::class, 'show'])
        ->where('path', '.*')
        ->name('themes.live-fallback');

    Route::get('/', [LandingController::class, 'index'])->name('welcome');

    Route::get('/landing/ai-content', [CreateController::class, 'landingAiContent'])
        ->name('landing.ai-content');

    // Legal pages
    Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
    Route::get('/cookies', [LegalController::class, 'cookies'])->name('cookies');

    Route::get('/create', [CreateController::class, 'index'])
        ->middleware(['auth', 'verified'])
        ->name('create');

    Route::get('/create/ai-content', [CreateController::class, 'aiContent'])
        ->middleware(['auth', 'verified'])
        ->name('create.ai-content');

    // Project chat routes
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/project/{project}', [ChatController::class, 'show'])->name('chat');
        Route::get('/project/{project}/generation-status', ProjectGenerationStatusController::class)
            ->name('project.generation.status');
        Route::post('/project/send', [ChatController::class, 'send'])->name('chat.send');
        Route::post('/panel/projects/{project}/chat-propose-patch', [ChatController::class, 'proposePatch'])->name('panel.projects.chat-propose-patch');
        Route::post('/panel/projects/{project}/chat-apply-patch', [ChatController::class, 'applyPatch'])->name('panel.projects.chat-apply-patch');
        Route::post('/panel/projects/{project}/chat-append', [ChatController::class, 'appendChatEntries'])->name('panel.projects.chat-append');
        Route::post('/panel/projects/{project}/chat-reply', [ChatController::class, 'chatReply'])->name('panel.projects.chat-reply');
        Route::post('/panel/projects/{project}/chat-patch-rollback', [ChatController::class, 'rollbackLastPatch'])->name('panel.projects.chat-patch-rollback');
        Route::get('/project/{project}/suggestions', [ChatController::class, 'suggestions'])->name('chat.suggestions');
    });

    // Project Settings
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/project/{project}/assets/{path}', [ProjectThemeAssetController::class, 'serve'])
            ->where('path', '.*')
            ->name('project.assets.serve');
        Route::get('/project/{project}/settings', [ProjectSettingsController::class, 'show'])->name('project.settings');
        Route::get('/project/{project}/cms', [ProjectCmsController::class, 'show'])->name('project.cms');
        Route::get('/panel/projects/{project}/builder/retrieval-preview', [ProjectBuilderRetrievalController::class, 'preview'])
            ->name('panel.projects.builder.retrieval-preview');
        Route::post('/panel/projects/{project}/builder/compose-draft', [ProjectBuilderRetrievalController::class, 'compose'])
            ->name('panel.projects.builder.compose-draft');
        Route::get('/panel/projects/{project}/builder/component-registry', [ProjectCmsController::class, 'componentRegistry'])
            ->name('panel.projects.builder.component-registry');
        Route::post('/panel/projects/{project}/ai-content-patch', [ProjectAiContentPatchController::class, 'store'])
            ->name('panel.projects.ai-content-patch');
        Route::get('/panel/projects/{project}/ai-site-editor/analyze', [ProjectAiContentPatchController::class, 'analyze'])
            ->name('panel.projects.ai-site-editor.analyze');
        Route::post('/panel/projects/{project}/ai-site-editor/execute', [ProjectAiContentPatchController::class, 'executeFromChangeSet'])
            ->name('panel.projects.ai-site-editor.execute');
        Route::post('/panel/projects/{project}/ai-revisions/rollback', [ProjectAiContentPatchController::class, 'rollback'])
            ->name('panel.projects.ai-revisions.rollback');
        Route::post('/panel/projects/{project}/ai-interpret-command', [ProjectAiContentPatchController::class, 'interpretCommand'])
            ->name('panel.projects.ai-interpret-command');
        Route::post('/panel/projects/{project}/ai-project-edit', [ProjectAiProjectEditController::class, 'store'])
            ->name('panel.projects.ai-project-edit');
        Route::post('/panel/projects/{project}/unified-agent/edit', [UnifiedWebuSiteAgentController::class, 'edit'])
            ->name('panel.projects.unified-agent.edit');
        Route::post('/panel/projects/{project}/generate-section-content', [ProjectGenerateSectionContentController::class, 'store'])
            ->name('panel.projects.generate-section-content');
        // Real project codebase (AI-editable workspace)
        Route::post('/panel/projects/{project}/workspace/initialize', [ProjectWorkspaceController::class, 'initialize'])->name('panel.projects.workspace.initialize');
        Route::post('/panel/projects/{project}/workspace/regenerate', [ProjectWorkspaceController::class, 'regenerate'])->name('panel.projects.workspace.regenerate');
        Route::get('/panel/projects/{project}/workspace/structure', [ProjectWorkspaceController::class, 'structure'])->name('panel.projects.workspace.structure');
        Route::get('/panel/projects/{project}/workspace/files', [ProjectWorkspaceController::class, 'listFiles'])->name('panel.projects.workspace.files');
        Route::get('/panel/projects/{project}/workspace/parsed-pages', [ProjectWorkspaceController::class, 'parsedPages'])->name('panel.projects.workspace.parsed-pages');
        Route::get('/panel/projects/{project}/workspace/file', [ProjectWorkspaceController::class, 'readFile'])->name('panel.projects.workspace.file.read');
        Route::post('/panel/projects/{project}/workspace/file', [ProjectWorkspaceController::class, 'writeFile'])->name('panel.projects.workspace.file.write');
        Route::delete('/panel/projects/{project}/workspace/file', [ProjectWorkspaceController::class, 'deleteFile'])->name('panel.projects.workspace.file.delete');
        Route::post('/panel/projects/{project}/ai-tools/execute', [ProjectAiToolsController::class, 'execute'])->name('panel.projects.ai-tools.execute');
        Route::post('/panel/projects/{project}/ai/site-plan', [ProjectSitePlannerController::class, 'store'])->name('panel.projects.ai.site-plan');
        Route::post('/panel/projects/{project}/ai/generate-component', [ProjectComponentGeneratorController::class, 'store'])->name('panel.projects.ai.generate-component');
        Route::put('/project/{project}/settings/general', [ProjectSettingsController::class, 'updateGeneral']);
        Route::put('/project/{project}/settings/knowledge', [ProjectSettingsController::class, 'updateKnowledge']);
        Route::get('/project/{project}/operation-logs', [ProjectSettingsController::class, 'operationLogs'])->name('project.operation-logs');
        Route::put('/project/{project}/theme', [ProjectSettingsController::class, 'updateTheme'])->name('project.theme.update');
        Route::post('/project/{project}/settings/share-image', [ProjectSettingsController::class, 'uploadShareImage']);
        Route::delete('/project/{project}/settings/share-image', [ProjectSettingsController::class, 'deleteShareImage']);
        Route::post('/project/{project}/thumbnail', [ProjectSettingsController::class, 'uploadThumbnail']);
        // API Token management
        Route::post('/project/{project}/api-token', [ProjectSettingsController::class, 'generateApiToken']);
        Route::post('/project/{project}/api-token/regenerate', [ProjectSettingsController::class, 'regenerateApiToken']);
        Route::delete('/project/{project}/api-token', [ProjectSettingsController::class, 'revokeApiToken']);
        // Requirement collection & questionnaire (e-commerce store setup)
        Route::get('/project/{project}/requirements', [RequirementCollectionController::class, 'show'])->name('project.requirements');
        Route::post('/panel/projects/{project}/requirement-step', [RequirementCollectionController::class, 'step'])->name('panel.projects.requirement-step');
        Route::post('/panel/projects/{project}/generate-from-config', [RequirementCollectionController::class, 'generateFromConfig'])->name('panel.projects.generate-from-config');
        Route::get('/panel/projects/{project}/questionnaire/state', [RequirementCollectionController::class, 'questionnaireState'])->name('panel.projects.questionnaire.state');
        Route::post('/panel/projects/{project}/questionnaire/answer', [RequirementCollectionController::class, 'questionnaireAnswer'])->name('panel.projects.questionnaire.answer');
    });

    // Publishing
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::post('/api/subdomain/check-availability', [ProjectPublishController::class, 'checkAvailability'])
            ->middleware('entitlement:subdomains');
        Route::post('/project/{project}/publish', [ProjectPublishController::class, 'publish'])
            ->middleware(['entitlement:subdomains', 'subscription.enforced:publish']);
        Route::post('/project/{project}/publish/retry', [ProjectPublishController::class, 'retry'])
            ->middleware(['entitlement:subdomains', 'subscription.enforced:publish']);
        Route::post('/project/{project}/unpublish', [ProjectPublishController::class, 'unpublish']);
    });

    // Custom Domain routes
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::post('/api/domain/check-availability', [ProjectCustomDomainController::class, 'checkAvailability'])
            ->middleware('entitlement:custom_domains');
        Route::post('/project/{project}/domain', [ProjectCustomDomainController::class, 'store'])
            ->middleware(['entitlement:custom_domains', 'subscription.enforced:publish'])
            ->name('project.domain.store');
        Route::post('/project/{project}/domain/verify', [ProjectCustomDomainController::class, 'verify'])
            ->middleware(['entitlement:custom_domains', 'subscription.enforced:publish'])
            ->name('project.domain.verify');
        Route::get('/project/{project}/domain/instructions', [ProjectCustomDomainController::class, 'instructions'])
            ->middleware('entitlement:custom_domains')
            ->name('project.domain.instructions');
        Route::delete('/project/{project}/domain', [ProjectCustomDomainController::class, 'destroy'])
            ->middleware('entitlement:custom_domains')
            ->name('project.domain.destroy');
    });

    // Projects routes
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::post('/projects/generate-website', GenerateWebsiteController::class.'@__invoke')->name('projects.generate-website');
        Route::post('/api/cheap-brief', \App\Http\Controllers\Api\CheapBriefController::class.'@__invoke')->name('api.cheap-brief');
        Route::post('/projects/from-ready-template', [ProjectController::class, 'storeFromReadyTemplate'])->name('projects.store-from-ready-template');
        Route::post('/projects/from-template-json', [ProjectController::class, 'storeFromTemplateJson'])->name('projects.store-from-template-json');
        Route::get('/api/ready-templates', [ReadyTemplatesController::class, 'list'])->name('api.ready-templates.list');
        // AI Layout Generator — prompt→input, generate layout JSON, theme tokens, create project
        Route::post('/api/ai-layout/prompt-to-input', [AiLayoutGeneratorController::class, 'promptToInput'])->name('api.ai-layout.prompt-to-input');
        Route::post('/api/ai-layout/generate', [AiLayoutGeneratorController::class, 'generate'])->name('api.ai-layout.generate');
        Route::post('/api/ai-layout/generate-theme', [AiLayoutGeneratorController::class, 'generateTheme'])->name('api.ai-layout.generate-theme');
        Route::post('/api/ai-layout/create-project', [AiLayoutGeneratorController::class, 'createProject'])->name('api.ai-layout.create-project');
        Route::get('/api/ai-layout/presets', [AiLayoutGeneratorController::class, 'presets'])->name('api.ai-layout.presets');
        Route::get('/api/ai-layout/component-registry', [AiLayoutGeneratorController::class, 'componentRegistry'])->name('api.ai-layout.component-registry');
        Route::get('/project/{project}/export-template', [ProjectController::class, 'exportTemplate'])->name('project.export-template');
        Route::get('/project/{project}/export-template-pack', [TemplatePackController::class, 'exportProject'])->name('project.export-template-pack');
        Route::get('/projects/trash', [ProjectController::class, 'trash'])->name('projects.trash');
        Route::post('/projects/{project}/toggle-star', [ProjectController::class, 'toggleStar'])->name('projects.toggle-star');
        Route::post('/projects/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('projects.duplicate');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->withTrashed()->name('projects.restore');
        Route::delete('/projects/{project}/force-delete', [ProjectController::class, 'forceDelete'])->withTrashed()->name('projects.force-delete');
    });

    // File Manager routes
    Route::middleware(['auth', 'verified', 'entitlement:file_storage'])->group(function () {
        Route::get('/file-manager', [FileManagerController::class, 'index'])->name('file-manager.index');
    });

    // Database (Firebase) routes
    Route::middleware(['auth', 'verified', 'entitlement:firebase'])->group(function () {
        Route::get('/database', [DatabaseController::class, 'index'])->name('database.index');
        Route::get('/firebase/collections', [\App\Http\Controllers\FirebaseCollectionController::class, 'index'])->name('firebase.collections');
    });

    // Project Files routes
    Route::middleware(['auth', 'verified', 'entitlement:file_storage'])->group(function () {
        Route::get('/project/{project}/files', [ProjectFileController::class, 'index'])->name('project.files.index');
        Route::post('/project/{project}/files', [ProjectFileController::class, 'store'])->name('project.files.store');
        Route::get('/project/{project}/files/{file}', [ProjectFileController::class, 'show'])->name('project.file.serve');
        Route::delete('/project/{project}/files/{file}', [ProjectFileController::class, 'destroy'])->name('project.files.destroy');
        Route::get('/project/{project}/files-usage', [ProjectFileController::class, 'usage'])->name('project.files.usage');
    });

    // Project Firebase routes
    Route::middleware(['auth', 'verified', 'entitlement:firebase'])->group(function () {
        Route::get('/project/{project}/firebase/config', [ProjectFirebaseController::class, 'getConfig'])->name('project.firebase.config');
        Route::put('/project/{project}/firebase/config', [ProjectFirebaseController::class, 'updateConfig'])->name('project.firebase.config.update');
        Route::delete('/project/{project}/firebase/config', [ProjectFirebaseController::class, 'resetConfig'])->name('project.firebase.config.reset');
        Route::get('/project/{project}/firebase/rules', [ProjectFirebaseController::class, 'generateRules'])->name('project.firebase.rules');
        Route::post('/project/{project}/firebase/test', [ProjectFirebaseController::class, 'testConnection'])->name('project.firebase.test');

        // Project Firebase Admin SDK routes
        Route::get('/project/{project}/firebase/admin-sdk', [ProjectFirebaseController::class, 'getAdminSdkStatus'])->name('project.firebase.admin-sdk.status');
        Route::post('/project/{project}/firebase/admin-sdk', [ProjectFirebaseController::class, 'uploadAdminSdk'])->name('project.firebase.admin-sdk.upload');
        Route::post('/project/{project}/firebase/admin-sdk/test', [ProjectFirebaseController::class, 'testAdminSdk'])->name('project.firebase.admin-sdk.test');
        Route::delete('/project/{project}/firebase/admin-sdk', [ProjectFirebaseController::class, 'deleteAdminSdk'])->name('project.firebase.admin-sdk.delete');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/consents', [ProfileController::class, 'updateConsents'])->name('profile.consents');
        Route::put('/profile/ai-settings', [ProfileController::class, 'updateAiSettings'])
            ->middleware('entitlement:own_ai_api_key')
            ->name('profile.ai-settings.update');
        Route::post('/profile/ai-settings/test', [ProfileController::class, 'testAiKey'])
            ->middleware('entitlement:own_ai_api_key')
            ->name('profile.ai-settings.test');
        Route::post('/profile/ai-settings/remove-key', [ProfileController::class, 'removeAiKey'])
            ->middleware('entitlement:own_ai_api_key')
            ->name('profile.ai-settings.remove-key');
        Route::put('/profile/sound-settings', [ProfileController::class, 'updateSoundSettings'])->name('profile.sound-settings.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Cookie Consent
        Route::post('/cookie-consent', [CookieConsentController::class, 'store'])->name('cookie-consent.store');

        // Data Export (GDPR)
        Route::post('/user/data-export', [DataExportController::class, 'request'])->name('data-export.request');
        Route::get('/data-export/download/{token}', [DataExportController::class, 'download'])->name('data-export.download');

        // Account Deletion (GDPR)
        Route::post('/account/request-deletion', [AccountDeletionController::class, 'request'])->name('account.request-deletion');
    });

    // Public route for cancelling account deletion (via email link)
    Route::get('/account/cancel-deletion/{token}', [AccountDeletionController::class, 'cancel'])->name('account.cancel-deletion');

    // Locale change (works for guests and authenticated users)
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

    // Public CMS endpoints (read-only for published site content)
    Route::get('/public/templates/{templateSlug}/default-site', [PublicSiteController::class, 'defaultSiteForTemplate'])
        ->name('public.templates.default-site');

    Route::prefix('public/sites')->group(function () {
        Route::get('/resolve', [PublicSiteController::class, 'resolve'])->name('public.sites.resolve');
        Route::get('/{site}/settings', [PublicSiteController::class, 'settings'])->name('public.sites.settings');
        Route::get('/{site}/theme/typography', [PublicSiteController::class, 'typography'])->name('public.sites.theme.typography');
        Route::get('/{site}/menu/{key}', [PublicSiteController::class, 'menu'])->name('public.sites.menu');
        Route::get('/{site}/search', [PublicSiteController::class, 'search'])->name('public.sites.search');
        Route::get('/{site}/customers/me', [PublicSiteController::class, 'customerMe'])->name('public.sites.customers.me');
        Route::put('/{site}/customers/me', [PublicSiteController::class, 'customerMeUpdate'])
            ->name('public.sites.customers.me.update');
        Route::post('/{site}/customers/register', [PublicSiteController::class, 'customerRegister'])
            ->middleware('throttle:20,1')
            ->name('public.sites.customers.register');
        Route::post('/{site}/customers/login', [PublicSiteController::class, 'customerLogin'])
            ->middleware('throttle:30,1')
            ->name('public.sites.customers.login');
        Route::post('/{site}/customers/logout', [PublicSiteController::class, 'customerLogout'])
            ->middleware('throttle:40,1')
            ->name('public.sites.customers.logout');
        Route::post('/{site}/auth/otp/request', [PublicSiteController::class, 'customerOtpRequest'])
            ->middleware('throttle:30,1')
            ->name('public.sites.auth.otp.request');
        Route::post('/{site}/auth/otp/verify', [PublicSiteController::class, 'customerOtpVerify'])
            ->middleware('throttle:30,1')
            ->name('public.sites.auth.otp.verify');
        Route::post('/{site}/auth/google', [PublicSiteController::class, 'customerGoogleAuth'])
            ->middleware('throttle:30,1')
            ->name('public.sites.auth.google');
        Route::post('/{site}/auth/facebook', [PublicSiteController::class, 'customerFacebookAuth'])
            ->middleware('throttle:30,1')
            ->name('public.sites.auth.facebook');
        Route::get('/{site}/posts', [PublicSiteController::class, 'blogPosts'])->name('public.sites.posts.index');
        Route::get('/{site}/posts/{slug}', [PublicSiteController::class, 'blogPost'])->name('public.sites.posts.show');
        Route::get('/{site}/post-categories', [PublicSiteController::class, 'blogCategories'])->name('public.sites.post-categories.index');
        Route::get('/{site}/portfolio', [PublicSiteController::class, 'portfolioItems'])->name('public.sites.portfolio.index');
        Route::get('/{site}/portfolio/{slug}', [PublicSiteController::class, 'portfolioItem'])->name('public.sites.portfolio.show');
        Route::get('/{site}/properties', [PublicSiteController::class, 'properties'])->name('public.sites.properties.index');
        Route::get('/{site}/properties/{slug}', [PublicSiteController::class, 'property'])->name('public.sites.properties.show');
        Route::get('/{site}/restaurant/menu', [PublicSiteController::class, 'restaurantMenu'])->name('public.sites.restaurant.menu');
        Route::get('/{site}/restaurant/menu/items', [PublicSiteController::class, 'restaurantMenuItems'])->name('public.sites.restaurant.menu-items');
        Route::post('/{site}/restaurant/reservations', [PublicSiteController::class, 'restaurantReservations'])
            ->middleware('throttle:public-booking')
            ->name('public.sites.restaurant.reservations.store');
        Route::get('/{site}/rooms', [PublicSiteController::class, 'rooms'])->name('public.sites.rooms.index');
        Route::get('/{site}/rooms/{id}', [PublicSiteController::class, 'roomDetail'])->name('public.sites.rooms.show');
        Route::post('/{site}/room-reservations', [PublicSiteController::class, 'roomReservations'])
            ->middleware('throttle:public-booking')
            ->name('public.sites.room-reservations.store');
        Route::get('/{site}/pages/{slug}', [PublicSiteController::class, 'page'])->name('public.sites.page');
        Route::options('/{site}/cms/telemetry', [PublicTelemetryController::class, 'options'])->name('public.sites.cms.telemetry.options');
        Route::post('/{site}/cms/telemetry', [PublicTelemetryController::class, 'store'])->name('public.sites.cms.telemetry.store');
        // Public Forms / Leads endpoints (F2 universal base module)
        Route::get('/{site}/forms/{key}', [PublicFormController::class, 'show'])->name('public.sites.forms.show');
        Route::post('/{site}/forms/{key}/submit', [PublicFormController::class, 'submit'])
            ->middleware('throttle:public-form-submit')
            ->name('public.sites.forms.submit');
        Route::get('/{site}/assets/{path}', [PublicSiteController::class, 'asset'])
            ->where('path', '.*')
            ->name('public.sites.assets');

        // Public Ecommerce storefront endpoints (F3)
        Route::middleware('public.api.observability')->group(function (): void {
            Route::get('/{site}/ecommerce/payment-options', [PublicEcommerceStorefrontController::class, 'paymentOptions'])->name('public.sites.ecommerce.payment.options');
            Route::get('/{site}/ecommerce/products', [PublicEcommerceStorefrontController::class, 'products'])->name('public.sites.ecommerce.products.index');
            Route::get('/{site}/ecommerce/products/{slug}', [PublicEcommerceStorefrontController::class, 'product'])->name('public.sites.ecommerce.products.show');
            Route::post('/{site}/ecommerce/carts', [PublicEcommerceStorefrontController::class, 'createCart'])->name('public.sites.ecommerce.carts.store');
            Route::get('/{site}/ecommerce/carts/{cart}', [PublicEcommerceStorefrontController::class, 'cart'])->name('public.sites.ecommerce.carts.show');
            Route::post('/{site}/ecommerce/carts/{cart}/items', [PublicEcommerceStorefrontController::class, 'addCartItem'])->name('public.sites.ecommerce.carts.items.store');
            Route::put('/{site}/ecommerce/carts/{cart}/items/{item}', [PublicEcommerceStorefrontController::class, 'updateCartItem'])->name('public.sites.ecommerce.carts.items.update');
            Route::delete('/{site}/ecommerce/carts/{cart}/items/{item}', [PublicEcommerceStorefrontController::class, 'removeCartItem'])->name('public.sites.ecommerce.carts.items.destroy');
            Route::post('/{site}/ecommerce/carts/{cart}/coupon', [PublicEcommerceStorefrontController::class, 'applyCoupon'])->name('public.sites.ecommerce.carts.coupon.apply');
            Route::delete('/{site}/ecommerce/carts/{cart}/coupon', [PublicEcommerceStorefrontController::class, 'removeCoupon'])->name('public.sites.ecommerce.carts.coupon.remove');
            Route::post('/{site}/ecommerce/carts/{cart}/shipping/options', [PublicEcommerceStorefrontController::class, 'shippingOptions'])->name('public.sites.ecommerce.carts.shipping.options');
            Route::put('/{site}/ecommerce/carts/{cart}/shipping', [PublicEcommerceStorefrontController::class, 'updateShipping'])->name('public.sites.ecommerce.carts.shipping.update');
            Route::post('/{site}/ecommerce/carts/{cart}/checkout/validate', [PublicEcommerceStorefrontController::class, 'checkoutValidate'])
                ->name('public.sites.ecommerce.carts.checkout.validate');
            Route::post('/{site}/ecommerce/carts/{cart}/checkout', [PublicEcommerceStorefrontController::class, 'checkout'])
                ->middleware('throttle:public-checkout')
                ->name('public.sites.ecommerce.carts.checkout');
            Route::get('/{site}/ecommerce/customer-orders', [PublicEcommerceStorefrontController::class, 'customerOrders'])
                ->name('public.sites.ecommerce.customer_orders.index');
            Route::get('/{site}/ecommerce/customer-orders/{order}', [PublicEcommerceStorefrontController::class, 'customerOrder'])
                ->name('public.sites.ecommerce.customer_orders.show');
            Route::post('/{site}/ecommerce/orders/{order}/payments/start', [PublicEcommerceStorefrontController::class, 'startPayment'])->name('public.sites.ecommerce.orders.payment.start');
            Route::get('/{site}/ecommerce/shipments/track', [PublicEcommerceStorefrontController::class, 'trackShipment'])->name('public.sites.ecommerce.shipments.track');
        });

        // Public Booking storefront endpoints (G3)
        Route::get('/{site}/booking/services', [PublicBookingController::class, 'services'])->name('public.sites.booking.services');
        Route::get('/{site}/booking/services/{slug}', [PublicBookingController::class, 'service'])->name('public.sites.booking.services.show');
        Route::get('/{site}/booking/staff', [PublicBookingController::class, 'staff'])->name('public.sites.booking.staff');
        Route::get('/{site}/booking/slots', [PublicBookingController::class, 'slots'])->name('public.sites.booking.slots');
        Route::get('/{site}/booking/calendar', [PublicBookingController::class, 'calendar'])->name('public.sites.booking.calendar');
        Route::post('/{site}/booking/bookings', [PublicBookingController::class, 'createBooking'])
            ->middleware('throttle:public-booking')
            ->name('public.sites.booking.bookings.store');
        Route::get('/{site}/booking/bookings/my', [PublicBookingController::class, 'myBookings'])->name('public.sites.booking.bookings.my');
        Route::get('/{site}/booking/bookings/{booking}', [PublicBookingController::class, 'booking'])->name('public.sites.booking.bookings.show');
        Route::put('/{site}/booking/bookings/{booking}', [PublicBookingController::class, 'updateBooking'])
            ->middleware('throttle:public-booking')
            ->name('public.sites.booking.bookings.update');
    });

    // Panel CMS endpoints (write/read management API)
    Route::middleware(['auth', 'verified', 'tenant.route.scope'])->prefix('panel/sites/{site}')->group(function () {
        // Pages
        Route::get('/pages', [PanelPageController::class, 'index'])->name('panel.sites.pages.index');
        Route::get('/pages/{page}', [PanelPageController::class, 'show'])->name('panel.sites.pages.show');
        Route::get('/pages/{page}/cms-hydrate', [PanelPageController::class, 'hydrateFromCms'])->name('panel.sites.pages.cms-hydrate');
        Route::post('/pages', [PanelPageController::class, 'store'])->name('panel.sites.pages.store');
        Route::put('/pages/{page}', [PanelPageController::class, 'update'])->name('panel.sites.pages.update');
        Route::delete('/pages/{page}', [PanelPageController::class, 'destroy'])->name('panel.sites.pages.destroy');
        Route::post('/pages/{page}/revisions', [PanelPageController::class, 'storeRevision'])->name('panel.sites.pages.revisions.store');
        Route::post('/pages/{page}/publish', [PanelPageController::class, 'publish'])->name('panel.sites.pages.publish');

        // Blog posts
        Route::get('/blog-posts', [PanelBlogPostController::class, 'index'])->name('panel.sites.blog-posts.index');
        Route::get('/blog-posts/{blogPost}', [PanelBlogPostController::class, 'show'])->name('panel.sites.blog-posts.show');
        Route::post('/blog-posts', [PanelBlogPostController::class, 'store'])->name('panel.sites.blog-posts.store');
        Route::put('/blog-posts/{blogPost}', [PanelBlogPostController::class, 'update'])->name('panel.sites.blog-posts.update');
        Route::delete('/blog-posts/{blogPost}', [PanelBlogPostController::class, 'destroy'])->name('panel.sites.blog-posts.destroy');

        // Site settings
        Route::get('/settings', [PanelSiteController::class, 'settings'])->name('panel.sites.settings.show');
        Route::put('/settings', [PanelSiteController::class, 'updateSettings'])->name('panel.sites.settings.update');
        Route::get('/theme/typography', [PanelSiteController::class, 'typography'])->name('panel.sites.theme.typography.show');
        Route::put('/theme/typography', [PanelSiteController::class, 'updateTypography'])->name('panel.sites.theme.typography.update');
        Route::post('/theme/fonts', [PanelSiteController::class, 'uploadCustomFont'])
            ->middleware('entitlement:file_storage')
            ->name('panel.sites.theme.fonts.upload');
        Route::delete('/theme/fonts/{font}', [PanelSiteController::class, 'deleteCustomFont'])
            ->name('panel.sites.theme.fonts.destroy');

        // Menus
        Route::get('/menus', [PanelMenuController::class, 'index'])->name('panel.sites.menus.index');
        Route::post('/menus', [PanelMenuController::class, 'store'])->name('panel.sites.menus.store');
        Route::get('/menus/{key}', [PanelMenuController::class, 'show'])->name('panel.sites.menus.show');
        Route::put('/menus/{key}', [PanelMenuController::class, 'update'])->name('panel.sites.menus.update');
        Route::delete('/menus/{key}', [PanelMenuController::class, 'destroy'])->name('panel.sites.menus.destroy');

        // Forms + leads (F2 universal base module)
        Route::get('/forms', [PanelFormController::class, 'index'])->name('panel.sites.forms.index');
        Route::get('/forms/{form}', [PanelFormController::class, 'show'])->name('panel.sites.forms.show');
        Route::post('/forms', [PanelFormController::class, 'store'])->name('panel.sites.forms.store');
        Route::put('/forms/{form}', [PanelFormController::class, 'update'])->name('panel.sites.forms.update');
        Route::delete('/forms/{form}', [PanelFormController::class, 'destroy'])->name('panel.sites.forms.destroy');
        Route::get('/form-leads', [PanelFormController::class, 'leads'])->name('panel.sites.form-leads.index');
        Route::put('/form-leads/{lead}/status', [PanelFormController::class, 'updateLeadStatus'])->name('panel.sites.form-leads.status.update');

        // Notifications templates + logs (F2 universal base module)
        Route::get('/notification-templates', [PanelNotificationController::class, 'templates'])->name('panel.sites.notification-templates.index');
        Route::get('/notification-templates/{template}', [PanelNotificationController::class, 'showTemplate'])->name('panel.sites.notification-templates.show');
        Route::post('/notification-templates', [PanelNotificationController::class, 'storeTemplate'])->name('panel.sites.notification-templates.store');
        Route::put('/notification-templates/{template}', [PanelNotificationController::class, 'updateTemplate'])->name('panel.sites.notification-templates.update');
        Route::delete('/notification-templates/{template}', [PanelNotificationController::class, 'destroyTemplate'])->name('panel.sites.notification-templates.destroy');
        Route::get('/notification-logs', [PanelNotificationController::class, 'logs'])->name('panel.sites.notification-logs.index');
        Route::post('/notification-logs/preview-dispatch', [PanelNotificationController::class, 'previewDispatch'])->name('panel.sites.notification-logs.preview-dispatch');

        // Media
        Route::get('/media', [PanelMediaController::class, 'index'])->name('panel.sites.media.index');
        Route::post('/media/upload', [PanelMediaController::class, 'upload'])
            ->middleware('entitlement:file_storage')
            ->name('panel.sites.media.upload');
        Route::put('/media/{media}', [PanelMediaController::class, 'update'])
            ->name('panel.sites.media.update');
        Route::delete('/media/{media}', [PanelMediaController::class, 'destroy'])
            ->name('panel.sites.media.destroy');

        // Module registry + entitlement matrix
        Route::get('/modules', [PanelModuleController::class, 'index'])->name('panel.sites.modules.index');
        Route::get('/entitlements', [PanelModuleController::class, 'entitlements'])->name('panel.sites.entitlements.show');
        Route::post('/cms/telemetry', [PanelTelemetryController::class, 'store'])->name('panel.sites.cms.telemetry.store');
        Route::get('/cms/learning/rules', [PanelLearningController::class, 'rules'])->name('panel.sites.cms.learning.rules.index');
        Route::get('/cms/learning/rules/{rule}', [PanelLearningController::class, 'showRule'])->name('panel.sites.cms.learning.rules.show');
        Route::put('/cms/learning/rules/{rule}/disable', [PanelLearningController::class, 'disableRule'])->name('panel.sites.cms.learning.rules.disable');
        Route::get('/cms/learning/experiments', [PanelLearningController::class, 'experiments'])->name('panel.sites.cms.learning.experiments.index');
        Route::get('/cms/learning/experiments/{experiment}', [PanelLearningController::class, 'showExperiment'])->name('panel.sites.cms.learning.experiments.show');
        Route::put('/cms/learning/experiments/{experiment}/disable', [PanelLearningController::class, 'disableExperiment'])->name('panel.sites.cms.learning.experiments.disable');

        // No-AI manual builder contract
        Route::get('/builder/templates', [PanelBuilderController::class, 'templates'])->name('panel.sites.builder.templates');
        Route::post('/builder/templates/apply', [PanelBuilderController::class, 'applyTemplate'])->name('panel.sites.builder.templates.apply');
        Route::post('/builder/sections', [PanelBuilderController::class, 'mutateSections'])->name('panel.sites.builder.sections.mutate');
        Route::put('/builder/styles', [PanelBuilderController::class, 'updateStyles'])->name('panel.sites.builder.styles.update');

        // Ecommerce (F1 backend core)
        Route::middleware(['site.entitlement:ecommerce', 'subscription.enforced:modules'])->group(function () {
            Route::get('/ecommerce/categories', [PanelEcommerceCategoryController::class, 'index'])->name('panel.sites.ecommerce.categories.index');
            Route::post('/ecommerce/categories', [PanelEcommerceCategoryController::class, 'store'])->name('panel.sites.ecommerce.categories.store');
            Route::put('/ecommerce/categories/{category}', [PanelEcommerceCategoryController::class, 'update'])->name('panel.sites.ecommerce.categories.update');
            Route::delete('/ecommerce/categories/{category}', [PanelEcommerceCategoryController::class, 'destroy'])->name('panel.sites.ecommerce.categories.destroy');

            Route::get('/ecommerce/attributes', [PanelEcommerceAttributeController::class, 'index'])->name('panel.sites.ecommerce.attributes.index');
            Route::post('/ecommerce/attributes', [PanelEcommerceAttributeController::class, 'store'])->name('panel.sites.ecommerce.attributes.store');
            Route::put('/ecommerce/attributes/{attribute}', [PanelEcommerceAttributeController::class, 'update'])->name('panel.sites.ecommerce.attributes.update');
            Route::delete('/ecommerce/attributes/{attribute}', [PanelEcommerceAttributeController::class, 'destroy'])->name('panel.sites.ecommerce.attributes.destroy');
            Route::get('/ecommerce/attribute-values', [PanelEcommerceAttributeController::class, 'valuesIndex'])->name('panel.sites.ecommerce.attribute-values.index');
            Route::post('/ecommerce/attribute-values', [PanelEcommerceAttributeController::class, 'valuesStore'])->name('panel.sites.ecommerce.attribute-values.store');
            Route::put('/ecommerce/attribute-values/{attributeValue}', [PanelEcommerceAttributeController::class, 'valuesUpdate'])->name('panel.sites.ecommerce.attribute-values.update');
            Route::delete('/ecommerce/attribute-values/{attributeValue}', [PanelEcommerceAttributeController::class, 'valuesDestroy'])->name('panel.sites.ecommerce.attribute-values.destroy');

            Route::get('/ecommerce/products', [PanelEcommerceProductController::class, 'index'])->name('panel.sites.ecommerce.products.index');
            Route::get('/ecommerce/products/{product}', [PanelEcommerceProductController::class, 'show'])->name('panel.sites.ecommerce.products.show');
            Route::post('/ecommerce/products', [PanelEcommerceProductController::class, 'store'])->name('panel.sites.ecommerce.products.store');
            Route::put('/ecommerce/products/{product}', [PanelEcommerceProductController::class, 'update'])->name('panel.sites.ecommerce.products.update');
            Route::delete('/ecommerce/products/{product}', [PanelEcommerceProductController::class, 'destroy'])->name('panel.sites.ecommerce.products.destroy');

            Route::get('/ecommerce/discounts', [PanelEcommerceDiscountController::class, 'index'])->name('panel.sites.ecommerce.discounts.index');
            Route::post('/ecommerce/discounts', [PanelEcommerceDiscountController::class, 'store'])->name('panel.sites.ecommerce.discounts.store');
            Route::put('/ecommerce/discounts/{discount}', [PanelEcommerceDiscountController::class, 'update'])->name('panel.sites.ecommerce.discounts.update');
            Route::delete('/ecommerce/discounts/{discount}', [PanelEcommerceDiscountController::class, 'destroy'])->name('panel.sites.ecommerce.discounts.destroy');
            Route::post('/ecommerce/discounts/bulk-apply', [PanelEcommerceDiscountController::class, 'bulkApply'])->name('panel.sites.ecommerce.discounts.bulk-apply');

            Route::middleware('site.entitlement:ecommerce_inventory')->group(function () {
                Route::get('/ecommerce/inventory', [PanelEcommerceInventoryController::class, 'index'])->name('panel.sites.ecommerce.inventory.index');
                Route::post('/ecommerce/inventory/locations', [PanelEcommerceInventoryController::class, 'storeLocation'])->name('panel.sites.ecommerce.inventory.locations.store');
                Route::put('/ecommerce/inventory/locations/{location}', [PanelEcommerceInventoryController::class, 'updateLocation'])->name('panel.sites.ecommerce.inventory.locations.update');
                Route::put('/ecommerce/inventory/items/{inventoryItem}', [PanelEcommerceInventoryController::class, 'updateItemSettings'])->name('panel.sites.ecommerce.inventory.items.update');
                Route::post('/ecommerce/inventory/items/{inventoryItem}/adjust', [PanelEcommerceInventoryController::class, 'adjustItem'])->name('panel.sites.ecommerce.inventory.items.adjust');
                Route::post('/ecommerce/inventory/items/{inventoryItem}/stocktake', [PanelEcommerceInventoryController::class, 'stocktakeItem'])->name('panel.sites.ecommerce.inventory.items.stocktake');
            });

            Route::get('/ecommerce/orders', [PanelEcommerceOrderController::class, 'index'])->name('panel.sites.ecommerce.orders.index');
            Route::get('/ecommerce/orders/{order}', [PanelEcommerceOrderController::class, 'show'])->name('panel.sites.ecommerce.orders.show');
            Route::put('/ecommerce/orders/{order}', [PanelEcommerceOrderController::class, 'update'])->name('panel.sites.ecommerce.orders.update');
            Route::delete('/ecommerce/orders/{order}', [PanelEcommerceOrderController::class, 'destroy'])->name('panel.sites.ecommerce.orders.destroy');
            Route::get('/ecommerce/orders/{order}/shipments', [PanelEcommerceShipmentController::class, 'index'])->name('panel.sites.ecommerce.orders.shipments.index');
            Route::post('/ecommerce/orders/{order}/shipments', [PanelEcommerceShipmentController::class, 'store'])->name('panel.sites.ecommerce.orders.shipments.store');
            Route::post('/ecommerce/orders/{order}/shipments/{shipment}/refresh-tracking', [PanelEcommerceShipmentController::class, 'refreshTracking'])->name('panel.sites.ecommerce.orders.shipments.refresh');
            Route::post('/ecommerce/orders/{order}/shipments/{shipment}/cancel', [PanelEcommerceShipmentController::class, 'cancel'])->name('panel.sites.ecommerce.orders.shipments.cancel');

            Route::middleware('site.entitlement:ecommerce_accounting')->group(function () {
                Route::post('/ecommerce/orders/{order}/returns', [PanelEcommerceAccountingController::class, 'recordReturn'])->name('panel.sites.ecommerce.orders.returns.store');
                Route::get('/ecommerce/accounting/entries', [PanelEcommerceAccountingController::class, 'entries'])->name('panel.sites.ecommerce.accounting.entries');
                Route::get('/ecommerce/accounting/reconciliation', [PanelEcommerceAccountingController::class, 'reconciliation'])->name('panel.sites.ecommerce.accounting.reconciliation');
            });

            Route::middleware('site.entitlement:ecommerce_rs')->group(function () {
                Route::post('/ecommerce/orders/{order}/rs/export', [PanelEcommerceRsController::class, 'generate'])->name('panel.sites.ecommerce.orders.rs.export');
                Route::get('/ecommerce/rs/readiness', [PanelEcommerceRsController::class, 'summary'])->name('panel.sites.ecommerce.rs.readiness');
                Route::get('/ecommerce/rs/exports', [PanelEcommerceRsController::class, 'index'])->name('panel.sites.ecommerce.rs.exports.index');
                Route::get('/ecommerce/rs/exports/{export}', [PanelEcommerceRsController::class, 'show'])->name('panel.sites.ecommerce.rs.exports.show');
                Route::post('/ecommerce/rs/exports/{export}/sync', [PanelEcommerceRsSyncController::class, 'queue'])->name('panel.sites.ecommerce.rs.exports.sync');
                Route::get('/ecommerce/rs/syncs', [PanelEcommerceRsSyncController::class, 'index'])->name('panel.sites.ecommerce.rs.syncs.index');
                Route::get('/ecommerce/rs/syncs/{sync}', [PanelEcommerceRsSyncController::class, 'show'])->name('panel.sites.ecommerce.rs.syncs.show');
                Route::post('/ecommerce/rs/syncs/{sync}/retry', [PanelEcommerceRsSyncController::class, 'retry'])->name('panel.sites.ecommerce.rs.syncs.retry');
            });

            Route::get('/ecommerce/payment-gateways', [PanelEcommerceGatewayController::class, 'index'])->name('panel.sites.ecommerce.payment-gateways.index');
            Route::put('/ecommerce/payment-gateways/{provider}', [PanelEcommerceGatewayController::class, 'update'])->name('panel.sites.ecommerce.payment-gateways.update');
            Route::middleware('site.entitlement:shipping')->group(function () {
                Route::get('/ecommerce/shipping/couriers', [PanelEcommerceShippingController::class, 'index'])->name('panel.sites.ecommerce.shipping.couriers.index');
                Route::put('/ecommerce/shipping/couriers/{courier}', [PanelEcommerceShippingController::class, 'update'])->name('panel.sites.ecommerce.shipping.couriers.update');
            });
        });

        // Booking (G2 panel core)
        Route::middleware(['site.entitlement:booking', 'subscription.enforced:modules'])->group(function () {
            Route::get('/booking/services', [PanelBookingServiceController::class, 'index'])->name('panel.sites.booking.services.index');
            Route::post('/booking/services', [PanelBookingServiceController::class, 'store'])->name('panel.sites.booking.services.store');
            Route::put('/booking/services/{service}', [PanelBookingServiceController::class, 'update'])->name('panel.sites.booking.services.update');
            Route::delete('/booking/services/{service}', [PanelBookingServiceController::class, 'destroy'])->name('panel.sites.booking.services.destroy');

            Route::get('/booking/staff', [PanelBookingStaffController::class, 'index'])->name('panel.sites.booking.staff.index');
            Route::post('/booking/staff', [PanelBookingStaffController::class, 'store'])->name('panel.sites.booking.staff.store');
            Route::put('/booking/staff/{staffResource}', [PanelBookingStaffController::class, 'update'])->name('panel.sites.booking.staff.update');
            Route::delete('/booking/staff/{staffResource}', [PanelBookingStaffController::class, 'destroy'])->name('panel.sites.booking.staff.destroy');

            Route::middleware('site.entitlement:booking_team_scheduling')->group(function () {
                Route::get('/booking/staff/{staffResource}/work-schedules', [PanelBookingStaffController::class, 'indexWorkSchedules'])->name('panel.sites.booking.staff.work-schedules.index');
                Route::put('/booking/staff/{staffResource}/work-schedules', [PanelBookingStaffController::class, 'syncWorkSchedules'])->name('panel.sites.booking.staff.work-schedules.sync');
                Route::get('/booking/staff/{staffResource}/time-off', [PanelBookingStaffController::class, 'indexTimeOff'])->name('panel.sites.booking.staff.time-off.index');
                Route::post('/booking/staff/{staffResource}/time-off', [PanelBookingStaffController::class, 'storeTimeOff'])->name('panel.sites.booking.staff.time-off.store');
                Route::put('/booking/staff/{staffResource}/time-off/{timeOff}', [PanelBookingStaffController::class, 'updateTimeOff'])->name('panel.sites.booking.staff.time-off.update');
                Route::delete('/booking/staff/{staffResource}/time-off/{timeOff}', [PanelBookingStaffController::class, 'destroyTimeOff'])->name('panel.sites.booking.staff.time-off.destroy');
            });

            Route::get('/booking/calendar', [PanelBookingController::class, 'calendar'])->name('panel.sites.booking.calendar');
            Route::get('/booking/calendar/advanced', [PanelBookingController::class, 'calendar'])
                ->middleware('site.entitlement:booking_advanced_calendar')
                ->name('panel.sites.booking.calendar.advanced');
            Route::get('/booking/customers/search', [PanelBookingController::class, 'searchCustomers'])->name('panel.sites.booking.customers.search');
            Route::get('/booking/bookings', [PanelBookingController::class, 'index'])->name('panel.sites.booking.bookings.index');
            Route::post('/booking/bookings', [PanelBookingController::class, 'store'])->name('panel.sites.booking.bookings.store');
            Route::post('/booking/bookings/{booking}/status', [PanelBookingController::class, 'updateStatus'])->name('panel.sites.booking.bookings.status');
            Route::post('/booking/bookings/{booking}/reschedule', [PanelBookingController::class, 'reschedule'])->name('panel.sites.booking.bookings.reschedule');
            Route::post('/booking/bookings/{booking}/cancel', [PanelBookingController::class, 'cancel'])->name('panel.sites.booking.bookings.cancel');
            Route::get('/booking/bookings/{booking}', [PanelBookingController::class, 'show'])->name('panel.sites.booking.bookings.show');

            Route::middleware('site.entitlement:booking_finance')->group(function () {
                Route::get('/booking/finance/invoices', [PanelBookingFinanceController::class, 'invoices'])->name('panel.sites.booking.finance.invoices.index');
                Route::post('/booking/bookings/{booking}/finance/invoices', [PanelBookingFinanceController::class, 'issueInvoice'])->name('panel.sites.booking.finance.invoices.store');
                Route::get('/booking/finance/payments', [PanelBookingFinanceController::class, 'payments'])->name('panel.sites.booking.finance.payments.index');
                Route::post('/booking/bookings/{booking}/finance/payments', [PanelBookingFinanceController::class, 'recordPayment'])->name('panel.sites.booking.finance.payments.store');
                Route::get('/booking/finance/refunds', [PanelBookingFinanceController::class, 'refunds'])->name('panel.sites.booking.finance.refunds.index');
                Route::post('/booking/bookings/{booking}/finance/refunds', [PanelBookingFinanceController::class, 'recordRefund'])->name('panel.sites.booking.finance.refunds.store');
                Route::get('/booking/finance/ledger', [PanelBookingFinanceController::class, 'ledger'])->name('panel.sites.booking.finance.ledger.index');
                Route::get('/booking/finance/reports', [PanelBookingFinanceController::class, 'reports'])->name('panel.sites.booking.finance.reports');
                Route::get('/booking/finance/reconciliation', [PanelBookingFinanceController::class, 'reconciliation'])->name('panel.sites.booking.finance.reconciliation');
            });
        });
    });

    // User Billing Routes
    Route::middleware(['auth', 'verified'])->prefix('billing')->group(function () {
        Route::get('/', [\App\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
        Route::get('/plans', [\App\Http\Controllers\BillingController::class, 'plans'])->name('billing.plans');
        Route::get('/invoice/{transaction}', [\App\Http\Controllers\BillingController::class, 'downloadInvoice'])->name('billing.invoice');
        Route::post('/cancel', [\App\Http\Controllers\BillingController::class, 'cancelSubscription'])->name('billing.cancel');
        Route::get('/referral', [ReferralController::class, 'index'])->name('billing.referral');
        Route::get('/usage', [BuildCreditController::class, 'index'])->name('billing.usage');
        Route::get('/usage/stats', [BuildCreditController::class, 'stats'])->name('billing.usage.stats');
        Route::get('/usage/packs', [BuildCreditController::class, 'packs'])->name('billing.usage.packs');
        Route::post('/usage/purchase', [BuildCreditController::class, 'purchase'])->name('billing.usage.purchase');
        Route::post('/plans/{plan}/price-preview', [\App\Http\Controllers\BillingController::class, 'pricePreview'])->name('billing.plans.preview');
        Route::post('/plans/{plan}/proration-preview', [\App\Http\Controllers\BillingController::class, 'prorationPreview'])->name('billing.plans.proration-preview');
        Route::post('/plans/{plan}/change', [\App\Http\Controllers\BillingController::class, 'changePlan'])->name('billing.plans.change');
    });

    // Admin Routes
    Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('admin.index');

        Route::get('overview', [AdminController::class, 'overview'])->name('admin.overview');
        Route::post('refresh-stats', [AdminController::class, 'refreshStats'])->name('admin.refresh-stats');

        // Tenants (multi-tenant isolation)
        Route::get('tenants', [AdminTenantController::class, 'index'])->name('admin.tenants.index');
        Route::get('tenants/{tenant}', [AdminTenantController::class, 'show'])->name('admin.tenants.show');
        Route::get('tenants/{tenant}/export', [AdminTenantController::class, 'exportTenant'])->name('admin.tenants.export');
        Route::put('tenants/update-status', [AdminTenantController::class, 'updateStatus'])->name('admin.tenants.update-status');
        Route::post('tenants/delete-website-dry-run', [AdminTenantController::class, 'deleteWebsiteDryRun'])->name('admin.tenants.delete-website-dry-run');
        Route::delete('tenants/delete-website', [AdminTenantController::class, 'deleteWebsite'])->name('admin.tenants.delete-website');
        Route::post('tenants/delete-tenant-dry-run', [AdminTenantController::class, 'deleteTenantDryRun'])->name('admin.tenants.delete-tenant-dry-run');
        Route::delete('tenants/delete-tenant', [AdminTenantController::class, 'deleteTenant'])->name('admin.tenants.delete-tenant');
        Route::get('tenants/export-website', [AdminTenantController::class, 'exportWebsite'])->name('admin.tenants.export-website');

        // User Management
        Route::get('users', [AdminUserController::class, 'index'])->name('admin.users');
        Route::get('users/search', [AdminUserController::class, 'search'])->name('admin.users.search');
        Route::post('users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');

        // Project Management
        Route::get('projects', [AdminProjectController::class, 'index'])->name('admin.projects');
        Route::post('projects', [AdminProjectController::class, 'store'])->name('admin.projects.store');
        Route::put('projects/{project}', [AdminProjectController::class, 'update'])->withTrashed()->name('admin.projects.update');
        Route::post('projects/{project}/access-as-admin', [AdminProjectController::class, 'accessAsAdmin'])->name('admin.projects.access-as-admin');
        Route::get('projects/{project}/access-audits', [AdminProjectController::class, 'accessAudits'])->name('admin.projects.access-audits');
        Route::post('projects/{project}/sql-export', [AdminProjectController::class, 'sqlExport'])->name('admin.projects.sql-export');
        Route::post('projects/{project}/sql-restore-dry-run', [AdminProjectController::class, 'sqlRestoreDryRun'])->name('admin.projects.sql-restore-dry-run');
        Route::get('projects/{project}/dedicated-db', [AdminProjectController::class, 'dedicatedDbStatus'])->name('admin.projects.dedicated-db.status');
        Route::put('projects/{project}/dedicated-db', [AdminProjectController::class, 'provisionDedicatedDb'])->name('admin.projects.dedicated-db.provision');
        Route::delete('projects/{project}/dedicated-db', [AdminProjectController::class, 'disableDedicatedDb'])->name('admin.projects.dedicated-db.disable');

        // Booking Oversight
        Route::get('bookings', [AdminBookingController::class, 'index'])->name('admin.bookings');

        // CMS Section Library Management
        Route::get('cms-sections', [AdminSectionLibraryController::class, 'index'])->name('admin.cms-sections');
        Route::post('cms-sections', [AdminSectionLibraryController::class, 'store'])->name('admin.cms-sections.store');
        Route::put('cms-sections/{section}', [AdminSectionLibraryController::class, 'update'])->name('admin.cms-sections.update');
        Route::delete('cms-sections/{section}', [AdminSectionLibraryController::class, 'destroy'])->name('admin.cms-sections.destroy');
        Route::post('cms-sections/import-defaults', [AdminSectionLibraryController::class, 'importDefaults'])->name('admin.cms-sections.import-defaults');

        // Universal CMS (Websites → Pages → Sections)
        Route::get('websites', [AdminUniversalCmsController::class, 'index'])->name('admin.universal-cms.index');
        Route::post('websites/sync', [AdminUniversalCmsController::class, 'syncFromSites'])->name('admin.universal-cms.sync');
        Route::middleware('tenant.from.website')->group(function () {
            Route::get('websites/{website}/pages', [AdminUniversalCmsController::class, 'pages'])->name('admin.universal-cms.pages');
            Route::get('websites/{website}/pages/{websitePage}/edit', [AdminUniversalCmsController::class, 'editPage'])->name('admin.universal-cms.page-edit');
            Route::get('websites/{website}/pages/{websitePage}/sections/{section}/edit', [AdminUniversalCmsController::class, 'editSection'])->name('admin.universal-cms.section-edit');
            Route::put('websites/{website}/pages/{websitePage}/sections/{section}', [AdminUniversalCmsController::class, 'updateSection'])->name('admin.universal-cms.section-update');
            Route::post('websites/{website}/revisions/undo', [AdminUniversalCmsController::class, 'undoRevisions'])->name('admin.universal-cms.revisions.undo');
            Route::get('websites/{website}/media', [AdminUniversalCmsController::class, 'mediaIndex'])->name('admin.universal-cms.media.index');
            Route::get('websites/{website}/media-library', [AdminUniversalCmsController::class, 'mediaLibrary'])->name('admin.universal-cms.media.library');
            Route::post('websites/{website}/media', [AdminUniversalCmsController::class, 'mediaUpload'])->name('admin.universal-cms.media.upload');
            Route::delete('websites/{website}/media', [AdminUniversalCmsController::class, 'mediaDestroy'])->name('admin.universal-cms.media.destroy');
            Route::post('websites/{website}/pages', [AdminUniversalCmsController::class, 'storePage'])->name('admin.universal-cms.pages.store');
            Route::put('websites/{website}/pages/{websitePage}', [AdminUniversalCmsController::class, 'updatePage'])->name('admin.universal-cms.pages.update');
            Route::delete('websites/{website}/pages/{websitePage}', [AdminUniversalCmsController::class, 'destroyPage'])->name('admin.universal-cms.pages.destroy');
            Route::post('websites/{website}/pages/reorder', [AdminUniversalCmsController::class, 'reorderPages'])->name('admin.universal-cms.pages.reorder');
        });
        Route::post('websites/cleanup', [AdminUniversalCmsController::class, 'cleanup'])->name('admin.universal-cms.cleanup');

        // Subscriptions
        Route::get('subscriptions', [AdminSubscriptionController::class, 'index'])->name('admin.subscriptions');
        Route::post('subscriptions', [AdminSubscriptionController::class, 'store'])->name('admin.subscriptions.store');
        Route::get('subscriptions/{subscription}', [AdminSubscriptionController::class, 'show'])->name('admin.subscriptions.show');
        Route::post('subscriptions/{subscription}/cancel', [AdminSubscriptionController::class, 'cancel'])->name('admin.subscriptions.cancel');
        Route::post('subscriptions/{subscription}/extend', [AdminSubscriptionController::class, 'extend'])->name('admin.subscriptions.extend');
        Route::post('subscriptions/{subscription}/approve', [AdminSubscriptionController::class, 'approve'])->name('admin.subscriptions.approve');

        // Transactions
        Route::get('transactions', [AdminTransactionController::class, 'index'])->name('admin.transactions');
        Route::get('transactions/{transaction}', [AdminTransactionController::class, 'show'])->name('admin.transactions.show');
        Route::post('transactions/{transaction}/approve', [AdminTransactionController::class, 'approve'])->name('admin.transactions.approve');
        Route::post('transactions/{transaction}/reject', [AdminTransactionController::class, 'reject'])->name('admin.transactions.reject');
        Route::post('transactions/{transaction}/refund', [AdminTransactionController::class, 'refund'])->name('admin.transactions.refund');
        Route::post('transactions/adjustment', [AdminTransactionController::class, 'adjustment'])->name('admin.transactions.adjustment');

        // Referrals
        Route::get('referrals', [AdminReferralController::class, 'index'])->name('admin.referrals');

        // Plans
        Route::get('plans', [AdminPlanController::class, 'index'])->name('admin.plans');
        Route::get('plans/create', [AdminPlanController::class, 'create'])->name('admin.plans.create');
        Route::post('plans', [AdminPlanController::class, 'store'])->name('admin.plans.store');
        Route::get('plans/{plan}/edit', [AdminPlanController::class, 'edit'])->name('admin.plans.edit');
        Route::put('plans/{plan}', [AdminPlanController::class, 'update'])->name('admin.plans.update');
        Route::delete('plans/{plan}', [AdminPlanController::class, 'destroy'])->name('admin.plans.destroy');
        Route::post('plans/{plan}/toggle-status', [AdminPlanController::class, 'toggleStatus'])->name('admin.plans.toggle-status');
        Route::post('plans/reorder', [AdminPlanController::class, 'reorder'])->name('admin.plans.reorder');
        Route::get('plans/{plan}/pricing-catalog', [AdminPricingCatalogController::class, 'show'])->name('admin.plans.pricing-catalog.show');
        Route::post('plans/{plan}/pricing-catalog/versions', [AdminPricingCatalogController::class, 'storeVersion'])->name('admin.plans.pricing-catalog.versions.store');
        Route::post('plans/{plan}/pricing-catalog/versions/{version}/activate', [AdminPricingCatalogController::class, 'activateVersion'])->name('admin.plans.pricing-catalog.versions.activate');
        Route::post('plans/{plan}/pricing-catalog/versions/{version}/addons', [AdminPricingCatalogController::class, 'upsertAddon'])->name('admin.plans.pricing-catalog.addons.upsert');
        Route::delete('plans/{plan}/pricing-catalog/versions/{version}/addons/{addon}', [AdminPricingCatalogController::class, 'destroyAddon'])->name('admin.plans.pricing-catalog.addons.destroy');
        Route::post('plans/{plan}/pricing-catalog/versions/{version}/rules', [AdminPricingCatalogController::class, 'upsertRule'])->name('admin.plans.pricing-catalog.rules.upsert');
        Route::delete('plans/{plan}/pricing-catalog/versions/{version}/rules/{rule}', [AdminPricingCatalogController::class, 'destroyRule'])->name('admin.plans.pricing-catalog.rules.destroy');
        Route::post('plans/{plan}/pricing-catalog/preview', [AdminPricingCatalogController::class, 'preview'])->name('admin.plans.pricing-catalog.preview');

        // Plugins
        Route::get('plugins', [PluginController::class, 'index'])->name('admin.plugins');
        Route::post('plugins/upload', [PluginController::class, 'upload'])->name('admin.plugins.upload');
        Route::post('plugins/{slug}/install', [PluginController::class, 'install'])->name('admin.plugins.install');
        Route::post('plugins/{slug}/configure', [PluginController::class, 'configure'])->name('admin.plugins.configure');
        Route::post('plugins/{slug}/toggle', [PluginController::class, 'toggle'])->name('admin.plugins.toggle');
        Route::delete('plugins/{slug}', [PluginController::class, 'uninstall'])->name('admin.plugins.uninstall');
        Route::get('plugins/{slug}/config-schema', [PluginController::class, 'getConfigSchema'])->name('admin.plugins.config-schema');

        // Languages
        Route::get('languages', [AdminLanguageController::class, 'index'])->name('admin.languages');
        Route::post('languages', [AdminLanguageController::class, 'store'])->name('admin.languages.store');
        Route::put('languages/{language}', [AdminLanguageController::class, 'update'])->name('admin.languages.update');
        Route::delete('languages/{language}', [AdminLanguageController::class, 'destroy'])->name('admin.languages.destroy');
        Route::post('languages/{language}/toggle-status', [AdminLanguageController::class, 'toggleStatus'])->name('admin.languages.toggle-status');
        Route::post('languages/{language}/set-default', [AdminLanguageController::class, 'setDefault'])->name('admin.languages.set-default');
        Route::post('languages/reorder', [AdminLanguageController::class, 'reorder'])->name('admin.languages.reorder');

        // Cronjobs
        Route::get('cronjobs', [AdminCronjobController::class, 'index'])->name('admin.cronjobs');
        Route::get('cronjobs/logs', [AdminCronjobController::class, 'logs'])->name('admin.cronjobs.logs');
        Route::post('cronjobs/trigger', [AdminCronjobController::class, 'trigger'])->name('admin.cronjobs.trigger');

        // Operation Logs
        Route::get('operation-logs', [AdminOperationLogController::class, 'index'])->name('admin.operation-logs');
        Route::get('operation-logs/data', [AdminOperationLogController::class, 'data'])->name('admin.operation-logs.data');

        // Settings
        Route::get('settings', [SettingsController::class, 'index'])->name('admin.settings');
        Route::put('settings/general', [SettingsController::class, 'updateGeneral'])->name('admin.settings.general');
        Route::post('settings/branding', [SettingsController::class, 'uploadBranding'])->name('admin.settings.branding');
        Route::delete('settings/branding', [SettingsController::class, 'deleteBranding'])->name('admin.settings.branding.delete');
        Route::put('settings/plans', [SettingsController::class, 'updatePlans'])->name('admin.settings.plans');
        Route::put('settings/auth', [SettingsController::class, 'updateAuth'])->name('admin.settings.auth');
        Route::put('settings/email', [SettingsController::class, 'updateEmail'])->name('admin.settings.email');
        Route::post('settings/email/test', [SettingsController::class, 'testEmail'])->name('admin.settings.email.test');
        Route::put('settings/gdpr', [SettingsController::class, 'updateGdpr'])->name('admin.settings.gdpr');
        Route::put('settings/integrations', [SettingsController::class, 'updateIntegrations'])->name('admin.settings.integrations');
        Route::post('settings/broadcast/test', [SettingsController::class, 'testBroadcast'])->name('admin.settings.broadcast.test');
        Route::post('settings/firebase/test', [SettingsController::class, 'testFirebase'])->name('admin.settings.firebase.test');
        Route::put('settings/referral', [SettingsController::class, 'updateReferral'])->name('admin.settings.referral');
        Route::put('settings/domains', [SettingsController::class, 'updateDomains'])->name('admin.settings.domains');
        Route::get('settings/currency-compatibility/{currency}', [SettingsController::class, 'checkCurrencyCompatibility'])->name('admin.settings.currency-compatibility');

        // Firebase Admin SDK
        Route::post('settings/firebase-admin', [SettingsController::class, 'uploadFirebaseAdmin'])->name('admin.settings.firebase-admin.upload');
        Route::post('settings/firebase-admin/test', [SettingsController::class, 'testFirebaseAdmin'])->name('admin.settings.firebase-admin.test');
        Route::delete('settings/firebase-admin', [SettingsController::class, 'deleteFirebaseAdmin'])->name('admin.settings.firebase-admin.delete');

        // Builder Management
        Route::get('ai-builders', [BuilderController::class, 'index'])->name('admin.ai-builders');
        Route::post('ai-builders', [BuilderController::class, 'store'])->name('admin.ai-builders.store');
        Route::put('ai-builders/{builder}', [BuilderController::class, 'update'])->name('admin.ai-builders.update');
        Route::delete('ai-builders/{builder}', [BuilderController::class, 'destroy'])->name('admin.ai-builders.destroy');
        Route::get('ai-builders/{builder}/details', [BuilderController::class, 'getDetails'])->name('admin.ai-builders.details');
        Route::post('ai-builders/generate-key', [BuilderController::class, 'generateKey'])->name('admin.ai-builders.generate-key');

        // AI Providers
        Route::get('ai-providers', [AiProviderController::class, 'index'])->name('admin.ai-providers');
        Route::post('ai-providers', [AiProviderController::class, 'store'])->name('admin.ai-providers.store');
        Route::put('ai-providers/{aiProvider}', [AiProviderController::class, 'update'])->name('admin.ai-providers.update');
        Route::delete('ai-providers/{aiProvider}', [AiProviderController::class, 'destroy'])->name('admin.ai-providers.destroy');
        Route::post('ai-providers/{aiProvider}/test', [AiProviderController::class, 'testConnection'])->name('admin.ai-providers.test');

        // Templates (Webu component–based e.g. ecommerce)
        Route::redirect('template', 'templates', 301);
        Route::get('templates', [AdminTemplateController::class, 'index'])->name('admin.templates');
        Route::get('ai-templates', [AdminTemplateController::class, 'index'])->name('admin.ai-templates');
        Route::post('ai-templates', [AdminTemplateController::class, 'store'])->name('admin.ai-templates.store');
        Route::put('ai-templates/{template}', [AdminTemplateController::class, 'update'])->name('admin.ai-templates.update');
        Route::delete('ai-templates/{template}', [AdminTemplateController::class, 'destroy'])->name('admin.ai-templates.destroy');
        Route::get('ai-templates/{template}/metadata', [AdminTemplateController::class, 'metadata'])->name('admin.ai-templates.metadata');
        Route::get('ai-templates/{template}/demo', [AdminTemplateController::class, 'demo'])->name('admin.ai-templates.demo');
        Route::get('ai-templates/{template}/demo-data', [AdminTemplateController::class, 'demoData'])->name('admin.ai-templates.demo-data');
        Route::get('ai-templates/{template}/live-demo', [AdminTemplateController::class, 'liveDemo'])->name('admin.ai-templates.live-demo');
        Route::get('ai-templates/{template}/live-admin', [AdminTemplateController::class, 'liveAdmin'])->name('admin.ai-templates.live-admin');
        Route::get('ai-templates/{template}/live-builder', [AdminTemplateController::class, 'liveBuilder'])->name('admin.ai-templates.live-builder');
        Route::get('ai-templates/{template}/export-pack', [TemplatePackController::class, 'exportTemplate'])->name('admin.ai-templates.export-pack');

        // Component Library (CMS Section Library visual catalog — admin only, under Templates)
        Route::get('component-library', [AdminComponentLibraryController::class, 'index'])->name('admin.component-library.index');
        Route::get('component-library/preview/{key}', [AdminComponentLibraryController::class, 'preview'])->name('admin.component-library.preview');

        Route::post('templates/import-pack-preview', [TemplatePackController::class, 'importPreview'])->name('admin.templates.import-pack-preview');
        Route::post('templates/import-pack', [TemplatePackController::class, 'import'])->name('admin.templates.import-pack');

        // Landing Builder
        Route::get('landing-builder', [\App\Http\Controllers\Admin\LandingBuilderController::class, 'index'])->name('admin.landing-builder.index');
        Route::get('landing-builder/preview', [\App\Http\Controllers\Admin\LandingBuilderController::class, 'preview'])->name('admin.landing-builder.preview');
        Route::post('landing-builder/reorder', [\App\Http\Controllers\Admin\LandingBuilderController::class, 'reorder'])->name('admin.landing-builder.reorder');
        Route::put('landing-builder/sections/{section}', [\App\Http\Controllers\Admin\LandingBuilderController::class, 'updateSection'])->name('admin.landing-builder.section.update');
        Route::put('landing-builder/sections/{section}/content', [\App\Http\Controllers\Admin\LandingBuilderController::class, 'updateContent'])->name('admin.landing-builder.section.content');
        Route::put('landing-builder/sections/{section}/items', [\App\Http\Controllers\Admin\LandingBuilderController::class, 'updateItems'])->name('admin.landing-builder.section.items');
        Route::post('landing-builder/media', [\App\Http\Controllers\Admin\LandingBuilderController::class, 'uploadMedia'])->name('admin.landing-builder.media.upload');
        Route::delete('landing-builder/media', [\App\Http\Controllers\Admin\LandingBuilderController::class, 'deleteMedia'])->name('admin.landing-builder.media.delete');

        // QA / Bugfixer (Tab 9: /admin/qa → same as bugfixer dashboard)
        Route::get('qa', [BugfixerController::class, 'redirectToBugfixer'])->name('admin.qa');
        Route::get('bugfixer', [BugfixerController::class, 'index'])->name('admin.bugfixer');
        Route::put('bugfixer/settings', [BugfixerController::class, 'updateSettings'])->name('admin.bugfixer.settings.update');
        Route::get('bugfixer/{bugId}/repro-pack', [BugfixerController::class, 'downloadReproPack'])->name('admin.bugfixer.repro-pack');
        Route::get('bugfixer/{bugId}/patch', [BugfixerController::class, 'downloadPatch'])->name('admin.bugfixer.patch');
        Route::get('bugfixer/{bugId}/ticket', [BugfixerController::class, 'downloadTicket'])->name('admin.bugfixer.ticket');
        Route::get('bugfixer/{bugId}/verify/{step}', [BugfixerController::class, 'downloadVerifyLog'])->name('admin.bugfixer.verify-log');
        Route::get('bugfixer/{bugId}', [BugfixerController::class, 'show'])->name('admin.bugfixer.show');
        Route::post('bugfixer/run', [BugfixerController::class, 'runAutoFix'])->name('admin.bugfixer.run');
    });

    // Payment Gateway Routes (webhooks don't require auth)
    Route::post('payment-gateways/{plugin}/webhook', [PaymentGatewayController::class, 'webhook'])->name('payment.webhook');
    Route::get('payment-gateways/callback', [PaymentGatewayController::class, 'callback'])->name('payment.callback');

    // Authenticated payment routes
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::post('payment/initiate', [PaymentGatewayController::class, 'initiatePayment'])->name('payment.initiate');
        Route::get('payment/gateways', [PaymentGatewayController::class, 'getAvailableGateways'])->name('payment.gateways');
    });

    // Builder Proxy Routes
    Route::middleware(['auth', 'verified'])->prefix('builder')->group(function () {
        Route::middleware('throttle:builder-operations')->group(function () {
            Route::get('available', [BuilderProxyController::class, 'getAvailableBuilders'])->name('builder.available');

            // Project-specific builder routes
            Route::post('projects/{project}/start', [BuilderProxyController::class, 'startBuild'])->name('builder.start');
            Route::post('projects/{project}/chat', [BuilderProxyController::class, 'chat'])->name('builder.chat');
            Route::post('projects/{project}/cancel', [BuilderProxyController::class, 'cancel'])->name('builder.cancel');
            Route::post('projects/{project}/complete', [BuilderProxyController::class, 'completeBuild'])->name('builder.complete');
            Route::post('projects/{project}/download', [BuilderProxyController::class, 'downloadOutput'])->name('builder.download');
            Route::get('projects/{project}/files', [BuilderProxyController::class, 'getFiles'])->name('builder.files');
            Route::get('projects/{project}/file', [BuilderProxyController::class, 'getFile'])->name('builder.file');
            Route::put('projects/{project}/file', [BuilderProxyController::class, 'updateFile'])->name('builder.file.update');
            Route::post('projects/{project}/build', [BuilderProxyController::class, 'triggerBuild'])->name('builder.build');
            Route::post('projects/{project}/build/retry', [BuilderProxyController::class, 'retryBuild'])->name('builder.build.retry');
            Route::get('projects/{project}/build', [BuilderProxyController::class, 'redirectBuildToChat']);
            Route::get('projects/{project}/suggestions', [BuilderProxyController::class, 'getSuggestions'])->name('builder.suggestions');
            Route::get('projects/{project}/health', [BuilderProxyController::class, 'checkBuilderHealth'])->name('builder.health');
        });

        Route::get('projects/{project}/status', [\App\Http\Controllers\BuilderStatusController::class, 'getStatus'])
            ->middleware('throttle:builder-status')
            ->name('builder.status');
    });

    // Preview Routes - serve built project previews (with inspector injection)
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/preview/{project}/exists', [PreviewController::class, 'exists'])->name('preview.exists');
        Route::get('/preview/{project}/{path?}', [PreviewController::class, 'serve'])
            ->where('path', '.*')
            ->name('preview.serve');
    });

    // App Preview Routes - serve clean preview files (no inspector)
    // Access controlled by project visibility settings
    Route::get('/app/{project}/{path?}', [AppPreviewController::class, 'serve'])
        ->where('path', '.*')
        ->name('app.serve');

    // Referral Tracking (Public)
    Route::get('/r/{codeOrSlug}', [ReferralTrackingController::class, 'track'])->name('referral.track');

    // Referral Actions (Authenticated)
    Route::middleware(['auth', 'verified'])->prefix('referral')->group(function () {
        Route::post('/generate-code', [ReferralController::class, 'generateCode'])->name('referral.generate-code');
        Route::put('/update-slug', [ReferralController::class, 'updateSlug'])->name('referral.update-slug');
        Route::get('/share-data', [ReferralController::class, 'getShareData'])->name('referral.share-data');
    });

    // User Notifications (Authenticated)
    Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('api.notifications.index');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
            ->name('api.notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
            ->name('api.notifications.read-all');
    });

    // Auth routes (also require installation)
    require __DIR__.'/auth.php';

    // Fallback: ensures the web middleware group (including domain middlewares)
    // runs for ALL unmatched request paths. Without this, paths like /style.css
    // on a project subdomain would 404 before the middleware can intercept them.
    // Using Route::fallback() instead of Route::any('{path?}') so it doesn't
    // shadow API routes, broadcasting/auth, or other framework-registered routes.
    Route::fallback(function () {
        abort(404);
    });

}); // End of 'installed' middleware group
