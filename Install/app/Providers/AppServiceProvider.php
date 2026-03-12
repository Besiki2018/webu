<?php

namespace App\Providers;

use App\Contracts\TenantProjectRouteScopeValidatorContract;
use App\Booking\Contracts\BookingCollisionServiceContract;
use App\Booking\Contracts\BookingAuthorizationServiceContract;
use App\Booking\Contracts\BookingFinanceServiceContract;
use App\Booking\Contracts\BookingPanelServiceContract;
use App\Booking\Contracts\BookingPublicServiceContract;
use App\Booking\Services\BookingAuthorizationService;
use App\Booking\Services\BookingCollisionService;
use App\Booking\Services\BookingFinanceService;
use App\Booking\Services\BookingPanelService;
use App\Booking\Services\BookingPublicService;
use App\Cms\Contracts\CmsPanelMediaServiceContract;
use App\Cms\Contracts\CmsPanelBlogPostServiceContract;
use App\Cms\Contracts\CmsPanelMenuServiceContract;
use App\Cms\Contracts\CmsPanelPageServiceContract;
use App\Cms\Contracts\CmsPanelSiteServiceContract;
use App\Cms\Contracts\CmsPublicSiteServiceContract;
use App\Cms\Contracts\CmsModuleRegistryServiceContract;
use App\Cms\Contracts\CmsRepositoryContract;
use App\Cms\Services\CmsModuleRegistryService;
use App\Cms\Repositories\EloquentCmsRepository;
use App\Cms\Services\CmsPanelMediaService;
use App\Cms\Services\CmsPanelBlogPostService;
use App\Cms\Services\CmsPanelMenuService;
use App\Cms\Services\CmsPanelPageService;
use App\Cms\Services\CmsPanelSiteService;
use App\Cms\Services\CmsPublicSiteService;
use App\Ecommerce\Contracts\EcommercePanelCategoryServiceContract;
use App\Ecommerce\Contracts\EcommerceAccountingServiceContract;
use App\Ecommerce\Contracts\EcommerceCourierConfigServiceContract;
use App\Ecommerce\Contracts\EcommerceGatewayConfigServiceContract;
use App\Ecommerce\Contracts\EcommerceInventoryServiceContract;
use App\Ecommerce\Contracts\EcommercePanelOrderServiceContract;
use App\Ecommerce\Contracts\EcommercePanelInventoryServiceContract;
use App\Ecommerce\Contracts\EcommercePanelProductServiceContract;
use App\Ecommerce\Contracts\EcommercePaymentWebhookServiceContract;
use App\Ecommerce\Contracts\EcommercePublicStorefrontServiceContract;
use App\Ecommerce\Contracts\EcommerceRsConnectorContract;
use App\Ecommerce\Contracts\EcommerceRepositoryContract;
use App\Ecommerce\Contracts\EcommerceRsReadinessServiceContract;
use App\Ecommerce\Contracts\EcommerceRsSyncServiceContract;
use App\Ecommerce\Contracts\EcommerceShipmentServiceContract;
use App\Ecommerce\Repositories\EloquentEcommerceRepository;
use App\Ecommerce\Services\EcommercePanelCategoryService;
use App\Ecommerce\Services\EcommerceAccountingService;
use App\Ecommerce\Services\EcommerceCourierConfigService;
use App\Ecommerce\Services\EcommerceGatewayConfigService;
use App\Ecommerce\Services\EcommerceInventoryService;
use App\Ecommerce\Services\EcommercePanelOrderService;
use App\Ecommerce\Services\EcommercePanelInventoryService;
use App\Ecommerce\Services\EcommercePanelProductService;
use App\Ecommerce\Services\EcommercePaymentWebhookService;
use App\Ecommerce\Services\EcommercePublicStorefrontService;
use App\Ecommerce\Services\EcommerceRsReadinessService;
use App\Ecommerce\Services\EcommerceRsSkeletonConnector;
use App\Ecommerce\Services\EcommerceRsSyncService;
use App\Ecommerce\Services\EcommerceShipmentService;
use App\Events\Builder\BuilderCompleteEvent;
use App\Events\Builder\BuilderErrorEvent;
use App\Events\Builder\BuilderStatusEvent;
use App\Listeners\SyncProjectBuildStatus;
use App\Listeners\TrackBuildCreditUsage;
use App\Models\EcommerceOrder;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\EcommerceOrderObserver;
use App\Services\AiOutputSchemaValidator;
use App\Services\CmsSectionBindingService;
use App\Services\ComponentVariantRegistry;
use App\Services\ModuleBindingsResolver;
use App\Services\TenantProjectRouteScopeValidatorService;
use App\Support\EnvValidator;
use App\Support\TenancyContext;
use App\Support\TenantContext;
use App\Support\TenantScopeGuard;
use App\Observers\ProjectObserver;
use App\Observers\TransactionObserver;
use App\Observers\UserObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
        $this->app->scoped(TenancyContext::class, fn () => new TenancyContext);
        $this->app->bind(TenantScopeGuard::class, fn ($app) => new TenantScopeGuard($app->make(TenancyContext::class)));
        $this->app->bind(TenantProjectRouteScopeValidatorContract::class, TenantProjectRouteScopeValidatorService::class);
        $this->app->bind(BookingAuthorizationServiceContract::class, BookingAuthorizationService::class);
        $this->app->bind(BookingCollisionServiceContract::class, BookingCollisionService::class);
        $this->app->bind(BookingFinanceServiceContract::class, BookingFinanceService::class);
        $this->app->bind(BookingPanelServiceContract::class, BookingPanelService::class);
        $this->app->bind(BookingPublicServiceContract::class, BookingPublicService::class);
        $this->app->singleton(CmsRepositoryContract::class, EloquentCmsRepository::class);
        $this->app->bind(CmsPanelPageServiceContract::class, CmsPanelPageService::class);
        $this->app->bind(CmsPanelSiteServiceContract::class, CmsPanelSiteService::class);
        $this->app->bind(CmsPanelMenuServiceContract::class, CmsPanelMenuService::class);
        $this->app->bind(CmsPanelMediaServiceContract::class, CmsPanelMediaService::class);
        $this->app->bind(CmsPanelBlogPostServiceContract::class, CmsPanelBlogPostService::class);
        $this->app->bind(CmsPublicSiteServiceContract::class, CmsPublicSiteService::class);
        $this->app->bind(CmsModuleRegistryServiceContract::class, CmsModuleRegistryService::class);
        $this->app->singleton(EcommerceRepositoryContract::class, EloquentEcommerceRepository::class);
        $this->app->bind(EcommerceAccountingServiceContract::class, EcommerceAccountingService::class);
        $this->app->bind(EcommerceGatewayConfigServiceContract::class, EcommerceGatewayConfigService::class);
        $this->app->bind(EcommerceCourierConfigServiceContract::class, EcommerceCourierConfigService::class);
        $this->app->bind(EcommerceInventoryServiceContract::class, EcommerceInventoryService::class);
        $this->app->bind(EcommercePanelCategoryServiceContract::class, EcommercePanelCategoryService::class);
        $this->app->bind(EcommercePanelInventoryServiceContract::class, EcommercePanelInventoryService::class);
        $this->app->bind(EcommercePanelProductServiceContract::class, EcommercePanelProductService::class);
        $this->app->bind(EcommercePanelOrderServiceContract::class, EcommercePanelOrderService::class);
        $this->app->bind(EcommercePaymentWebhookServiceContract::class, EcommercePaymentWebhookService::class);
        $this->app->bind(EcommerceRsReadinessServiceContract::class, EcommerceRsReadinessService::class);
        $this->app->bind(EcommerceRsConnectorContract::class, EcommerceRsSkeletonConnector::class);
        $this->app->bind(EcommerceRsSyncServiceContract::class, EcommerceRsSyncService::class);
        $this->app->bind(EcommerceShipmentServiceContract::class, EcommerceShipmentService::class);
        $this->app->bind(EcommercePublicStorefrontServiceContract::class, EcommercePublicStorefrontService::class);
        $this->app->singleton(ComponentVariantRegistry::class, fn () => new ComponentVariantRegistry(config('component-variants', [])));
        $this->app->singleton(AiOutputSchemaValidator::class, fn ($app) => new AiOutputSchemaValidator(
            config('component-variants.allowed_section_keys') ?: config('builder-component-registry.component_ids', []),
            $app->make(CmsSectionBindingService::class),
        ));
        $this->app->bind(ModuleBindingsResolver::class, ModuleBindingsResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Allow /up during maintenance (we serve it via HealthController::up; framework health route was disabled for Ziggy)
        PreventRequestsDuringMaintenance::except(['up']);

        // Fail fast on missing critical env in web/worker (skip in console for migrate/key:generate)
        if (! app()->runningInConsole()) {
            EnvValidator::validate();
        }

        Vite::prefetch(concurrency: 3);

        // Register custom JSON translation loader for lang/{locale}/*.json files
        $this->registerJsonTranslations();

        // Register model observers
        Project::observe(ProjectObserver::class);
        EcommerceOrder::observe(EcommerceOrderObserver::class);
        Transaction::observe(TransactionObserver::class);
        User::observe(UserObserver::class);
        $this->configureRateLimiters();

        // Register event listeners
        Event::listen(BuilderCompleteEvent::class, TrackBuildCreditUsage::class);
        Event::listen(BuilderCompleteEvent::class, [SyncProjectBuildStatus::class, 'handleComplete']);
        Event::listen(BuilderStatusEvent::class, SyncProjectBuildStatus::class);
        Event::listen(BuilderErrorEvent::class, [SyncProjectBuildStatus::class, 'handleError']);

        // Only configure dynamic settings if the database is available
        try {
            if (! Schema::hasTable('system_settings')) {
                return;
            }

            $this->configureSessionTimeout();
            $this->configureSessionDomain();
            $this->configureMailSettings();
            $this->configureSocialiteProviders();
            $this->configureFirebaseSettings();
        } catch (\Exception $e) {
            // Database not available yet (fresh install)
            return;
        }
    }

    /**
     * Apply dynamic session timeout from settings.
     */
    protected function configureSessionTimeout(): void
    {
        try {
            $timeout = SystemSetting::get('session_timeout', 120);
            config(['session.lifetime' => (int) $timeout]);
        } catch (\Exception $e) {
            // Ignore if settings table doesn't exist yet
        }
    }

    /**
     * Apply dynamic session cookie domain for cross-subdomain auth.
     * When subdomains are enabled, set cookie domain to .baseDomain
     * so auth works on dashboard.domain.com, app.domain.com, etc.
     */
    protected function configureSessionDomain(): void
    {
        try {
            if (SystemSetting::get('domain_enable_subdomains', false)) {
                $baseDomain = SystemSetting::get('domain_base_domain', '');
                if (! empty($baseDomain)) {
                    $domain = ltrim($baseDomain, '.');

                    // Only set the wildcard session domain when the request
                    // host actually matches the base domain. Otherwise the
                    // browser scopes the cookie to a domain that doesn't
                    // match the current host, causing 419 CSRF errors.
                    $host = request()->getHost();
                    if ($host === $domain || str_ends_with($host, ".{$domain}")) {
                        config(['session.domain' => ".{$domain}"]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore if settings table doesn't exist yet
        }
    }

    /**
     * Apply dynamic mail configuration from settings.
     */
    protected function configureMailSettings(): void
    {
        try {
            $settings = SystemSetting::getGroup('email');

            if (empty($settings)) {
                return;
            }

            // Only apply if SMTP settings are configured
            if (! empty($settings['smtp_host'])) {
                config([
                    'mail.default' => $settings['mail_mailer'] ?? 'smtp',
                    'mail.mailers.smtp.host' => $settings['smtp_host'] ?? '',
                    'mail.mailers.smtp.port' => (int) ($settings['smtp_port'] ?? 587),
                    'mail.mailers.smtp.username' => $settings['smtp_username'] ?? null,
                    'mail.mailers.smtp.password' => $settings['smtp_password'] ?? null,
                    'mail.mailers.smtp.encryption' => ($settings['smtp_encryption'] ?? 'tls') === 'none'
                        ? null
                        : ($settings['smtp_encryption'] ?? 'tls'),
                ]);
            }

            // Apply from address settings
            if (! empty($settings['mail_from_address'])) {
                config([
                    'mail.from.address' => $settings['mail_from_address'],
                    'mail.from.name' => $settings['mail_from_name'] ?? config('app.name'),
                ]);
            }
        } catch (\Exception $e) {
            // Ignore if settings table doesn't exist yet
        }
    }

    /**
     * Configure Socialite providers from SystemSettings.
     */
    protected function configureSocialiteProviders(): void
    {
        try {
            $providers = ['google', 'facebook', 'github'];

            foreach ($providers as $provider) {
                $enabled = SystemSetting::get("{$provider}_login_enabled", false);

                if ($enabled) {
                    $clientId = SystemSetting::get("{$provider}_client_id", '');
                    $clientSecret = SystemSetting::get("{$provider}_client_secret", '');

                    if ($clientId && $clientSecret) {
                        config([
                            "services.{$provider}.client_id" => $clientId,
                            "services.{$provider}.client_secret" => $clientSecret,
                            "services.{$provider}.redirect" => url("/auth/{$provider}/callback"),
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore if settings table doesn't exist yet
        }
    }

    /**
     * Configure Firebase settings from database.
     */
    protected function configureFirebaseSettings(): void
    {
        try {
            $settings = SystemSetting::getGroup('integrations');

            // Only override config if Firebase project ID is set in database
            if (! empty($settings['firebase_system_project_id'])) {
                config([
                    'services.firebase.system_api_key' => $settings['firebase_system_api_key'] ?? config('services.firebase.system_api_key'),
                    'services.firebase.system_project_id' => $settings['firebase_system_project_id'] ?? config('services.firebase.system_project_id'),
                    'services.firebase.system_auth_domain' => $settings['firebase_system_auth_domain'] ?? config('services.firebase.system_auth_domain'),
                    'services.firebase.system_storage_bucket' => $settings['firebase_system_storage_bucket'] ?? config('services.firebase.system_storage_bucket'),
                    'services.firebase.system_messaging_sender_id' => $settings['firebase_system_messaging_sender_id'] ?? config('services.firebase.system_messaging_sender_id'),
                    'services.firebase.system_app_id' => $settings['firebase_system_app_id'] ?? config('services.firebase.system_app_id'),
                ]);
            }
        } catch (\Exception $e) {
            // Ignore if settings table doesn't exist yet
        }
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('auth-actions', function (Request $request): array {
            return [
                Limit::perMinute(12)->by($request->ip()),
                Limit::perMinute(6)->by(strtolower((string) ($request->input('email') ?? 'guest')).'|'.$request->ip()),
            ];
        });

        RateLimiter::for('builder-operations', function (Request $request): array {
            $userKey = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            $projectKey = (string) ($request->route('project')?->id ?? $request->route('project') ?? 'none');

            return [
                Limit::perMinute(120)->by("builder-operation-user:{$userKey}"),
                Limit::perMinute(45)->by("builder-operation-project:{$projectKey}|{$userKey}"),
            ];
        });

        RateLimiter::for('builder-status', function (Request $request): array {
            $userKey = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            $projectKey = (string) ($request->route('project')?->id ?? $request->route('project') ?? 'none');

            // Quick status polling is a DB-only read path used as the fallback chat transport.
            // Keep it effectively unthrottled so status reconciliation cannot block start/chat routes.
            if ($request->boolean('quick')) {
                return [
                    Limit::none()->by("builder-status-quick-user:{$userKey}"),
                ];
            }

            return [
                Limit::perMinute(240)->by("builder-status-user:{$userKey}"),
                Limit::perMinute(120)->by("builder-status-project:{$projectKey}|{$userKey}"),
            ];
        });

        RateLimiter::for('public-checkout', function (Request $request): array {
            $siteKey = (string) ($request->route('site')?->id ?? $request->route('site') ?? 'none');
            $ip = $request->ip();

            return [
                Limit::perMinute(30)->by("checkout:{$siteKey}|{$ip}"),
                Limit::perMinute(10)->by("checkout-tight:{$siteKey}|{$ip}"),
            ];
        });

        RateLimiter::for('public-booking', function (Request $request): array {
            $siteKey = (string) ($request->route('site')?->id ?? $request->route('site') ?? 'none');
            $ip = $request->ip();

            return [
                Limit::perMinute(40)->by("booking:{$siteKey}|{$ip}"),
                Limit::perMinute(12)->by("booking-tight:{$siteKey}|{$ip}"),
            ];
        });

        RateLimiter::for('public-form-submit', function (Request $request): array {
            $siteKey = (string) ($request->route('site')?->id ?? $request->route('site') ?? 'none');
            $key = strtolower(trim((string) ($request->route('key') ?? 'unknown')));
            $ip = $request->ip();

            return [
                Limit::perMinute(60)->by("form-submit:{$siteKey}|{$key}|{$ip}"),
                Limit::perMinute(15)->by("form-submit-tight:{$siteKey}|{$key}|{$ip}"),
            ];
        });
    }

    /**
     * Register JSON translations from lang/{locale}/*.json files.
     * Merges all JSON files in each locale directory into the translator.
     */
    protected function registerJsonTranslations(): void
    {
        $langPath = lang_path();

        // Override the translation loader to merge JSON files from locale subdirectories
        $this->app->extend('translation.loader', function ($loader, $app) use ($langPath) {
            return new class($app['files'], $langPath) extends \Illuminate\Translation\FileLoader
            {
                protected string $langPath;

                public function __construct($files, $langPath)
                {
                    parent::__construct($files, $langPath);
                    $this->langPath = $langPath;
                }

                /**
                 * Load JSON translations, merging all JSON files from locale directories.
                 */
                public function load($locale, $group, $namespace = null)
                {
                    // For JSON translations (group is '*')
                    if ($group === '*' && $namespace === '*') {
                        return $this->loadMergedJsonPaths($locale);
                    }

                    return parent::load($locale, $group, $namespace);
                }

                /**
                 * Load JSON translations from locale directory, merging all JSON files.
                 */
                protected function loadMergedJsonPaths($locale): array
                {
                    $translations = [];

                    // First, load the default lang/{locale}.json if it exists
                    $defaultPath = "{$this->langPath}/{$locale}.json";
                    if ($this->files->exists($defaultPath)) {
                        $decoded = json_decode($this->files->get($defaultPath), true);
                        if (is_array($decoded)) {
                            $translations = array_merge($translations, $decoded);
                        }
                    }

                    // Then, load all JSON files from lang/{locale}/ directory
                    $directory = "{$this->langPath}/{$locale}";
                    if (is_dir($directory)) {
                        $files = glob("{$directory}/*.json");
                        foreach ($files as $file) {
                            $decoded = json_decode($this->files->get($file), true);
                            if (is_array($decoded)) {
                                $translations = array_merge($translations, $decoded);
                            }
                        }
                    }

                    return $translations;
                }
            };
        });
    }
}
