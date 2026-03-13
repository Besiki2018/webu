<?php

namespace App\Services\Builder;

use App\Cms\Contracts\CmsModuleRegistryServiceContract;
use App\Models\Builder;
use App\Models\Project;
use App\Models\Site;
use App\Models\Template;
use App\Services\CmsTypographyService;
use App\Services\FirebaseService;
use App\Services\SiteProvisioningService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class BuilderProjectContextService
{
    public function buildSessionPayload(
        Builder $builder,
        Project $project,
        string $prompt,
        array $history,
        bool $isCompacted,
        array $aiConfig,
        ?string $templateUrl = null,
        ?string $templateId = null,
        ?array $retrievalContext = null,
    ): array {
        $projectLocale = $this->resolveProjectLocale($project);
        $templateContext = $this->resolveTemplateContext($project, $templateId);
        $payload = [
            'goal' => $this->buildGoalWithQualityGuardrails($prompt, $projectLocale),
            'goal_original' => $prompt,
            'locale' => $projectLocale,
            'max_iterations' => $builder->max_iterations ?? 20,
            'history' => $history,
            'is_compacted' => $isCompacted,
            'config' => $aiConfig,
            'workspace_id' => $project->id,
            'webhook_url' => route('builder.webhook'),
            'quality_contract' => $this->buildQualityContract($projectLocale),
        ];

        $templateConfig = [];
        if ($templateUrl) {
            $templateConfig['url'] = $templateUrl;
        }
        if ($templateContext !== null) {
            $templateConfig['template_id'] = (string) $templateContext['id'];
            $templateConfig['template_slug'] = $templateContext['slug'];
            $templateConfig['template_name'] = $templateContext['name'];
            $templateConfig['template_category'] = $templateContext['category'];
            $templateConfig['template_version'] = $templateContext['version'];
            $templateConfig['locale'] = $projectLocale;
            $templateConfig['module_flags'] = $templateContext['module_flags'];
            $templateConfig['default_pages'] = $templateContext['default_pages'];
            $templateConfig['default_sections'] = $templateContext['default_sections'];
            $templateConfig['typography_tokens'] = $templateContext['typography_tokens'];
        } elseif ($templateId) {
            $templateConfig['template_id'] = $templateId;
        }
        if (! empty($templateConfig)) {
            $payload['template'] = $templateConfig;
        }

        if ($templateContext !== null) {
            $payload['template_contract'] = [
                'id' => (string) $templateContext['id'],
                'slug' => $templateContext['slug'],
                'name' => $templateContext['name'],
                'category' => $templateContext['category'],
                'version' => $templateContext['version'],
                'locale' => $projectLocale,
                'module_flags' => $templateContext['module_flags'],
                'default_pages' => $templateContext['default_pages'],
                'default_sections' => $templateContext['default_sections'],
                'typography_tokens' => $templateContext['typography_tokens'],
            ];
            $payload['module_flags'] = $templateContext['module_flags'];
        } else {
            $payload['module_flags'] = [];
        }

        $laravelUrl = config('app.url');
        if ($laravelUrl) {
            $payload['laravel_url'] = $laravelUrl;
        }

        $payload['project_capabilities'] = $this->buildProjectCapabilities(
            $project,
            $templateContext,
            $projectLocale
        );

        if (! empty($payload['project_capabilities']['cms'])) {
            $payload['cms_contract'] = $payload['project_capabilities']['cms'];
        }

        if (is_array($retrievalContext) && $retrievalContext !== []) {
            $payload['retrieval_context'] = $retrievalContext;
        }

        $themePreset = $this->buildThemePreset($project);
        if ($themePreset !== null) {
            $payload['theme_preset'] = $themePreset;
        }

        return $payload;
    }

    public function buildMetaTags(Project $project): string
    {
        $title = htmlspecialchars(
            $project->published_title ?? $project->name ?? 'Webby Project',
            ENT_QUOTES,
            'UTF-8'
        );

        $tags = [];

        if (! empty($project->published_description)) {
            $description = htmlspecialchars($project->published_description, ENT_QUOTES, 'UTF-8');
            $tags[] = sprintf('<meta name="description" content="%s">', $description);
        }

        $tags[] = sprintf('<meta property="og:title" content="%s">', $title);
        $tags[] = '<meta property="og:type" content="website">';

        if (! empty($project->published_description)) {
            $description = htmlspecialchars($project->published_description, ENT_QUOTES, 'UTF-8');
            $tags[] = sprintf('<meta property="og:description" content="%s">', $description);
        }

        if ($project->share_image) {
            $imageUrl = asset('storage/'.$project->share_image);
            $tags[] = sprintf(
                '<meta property="og:image" content="%s">',
                htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8')
            );
        }

        $publishedUrl = $project->getPublishedUrl();
        if ($project->isPublished() && is_string($publishedUrl) && $publishedUrl !== '') {
            $tags[] = sprintf(
                '<meta property="og:url" content="%s">',
                htmlspecialchars($publishedUrl, ENT_QUOTES, 'UTF-8')
            );
        }

        $tags[] = '<meta name="twitter:card" content="summary_large_image">';
        $tags[] = sprintf('<meta name="twitter:title" content="%s">', $title);

        if (! empty($project->published_description)) {
            $description = htmlspecialchars($project->published_description, ENT_QUOTES, 'UTF-8');
            $tags[] = sprintf('<meta name="twitter:description" content="%s">', $description);
        }

        if ($project->share_image) {
            $imageUrl = asset('storage/'.$project->share_image);
            $tags[] = sprintf(
                '<meta name="twitter:image" content="%s">',
                htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8')
            );
        }

        return implode("\n    ", $tags);
    }

    public function buildRuntimeAppConfig(Project $project): array
    {
        $user = $project->user;
        $plan = $user?->getCurrentPlan();
        $site = $this->resolveProjectSite($project);
        $apiBaseUrl = $this->resolveRuntimeApiBaseUrl();

        $config = [
            'apiUrl' => config('app.url'),
            'projectId' => $project->id,
            'apiToken' => $project->api_token,
        ];

        if ($plan?->firebaseEnabled()) {
            $firebaseConfig = app(FirebaseService::class)->getConfig($project);
            if ($firebaseConfig) {
                $config['firebase'] = $firebaseConfig;
                $config['firebasePrefix'] = $project->getFirebaseCollectionPrefix();
            }
        }

        $config['cms'] = [
            'enabled' => $site !== null,
            'site_id' => $site?->id,
            'default_locale' => $site?->locale ?? 'ka',
            'typography' => $site ? app(CmsTypographyService::class)->resolveTypography($site->theme_settings ?? [], $site) : null,
            'bridge_path' => '__cms/bootstrap',
            'api_base_url' => $apiBaseUrl,
            'resolve_url' => $apiBaseUrl.'/public/sites/resolve',
            'settings_url' => $site ? $apiBaseUrl."/public/sites/{$site->id}/settings" : null,
            'typography_url' => $site ? $apiBaseUrl."/public/sites/{$site->id}/theme/typography" : null,
            'telemetry_url' => $site ? $apiBaseUrl."/public/sites/{$site->id}/cms/telemetry" : null,
            'header_menu_url' => $site ? $apiBaseUrl."/public/sites/{$site->id}/menu/header" : null,
            'footer_menu_url' => $site ? $apiBaseUrl."/public/sites/{$site->id}/menu/footer" : null,
            'search_url' => $site ? $apiBaseUrl."/public/sites/{$site->id}/search" : null,
            'customer_me_url' => $site ? $apiBaseUrl."/public/sites/{$site->id}/customers/me" : null,
            'form_submit_url_pattern' => $site ? $apiBaseUrl."/public/sites/{$site->id}/forms/{key}/submit" : null,
            'page_url_pattern' => $site ? $apiBaseUrl."/public/sites/{$site->id}/pages/{slug}" : null,
        ];
        $config['ecommerce'] = $this->buildRuntimeEcommerceConfig($project, $site, $apiBaseUrl);
        $config['booking'] = $this->buildRuntimeBookingConfig($project, $site, $apiBaseUrl);

        return $config;
    }

    public function buildRuntimeEcommerceConfig(Project $project, ?Site $site, string $apiBaseUrl): array
    {
        $normalizedBaseUrl = rtrim($apiBaseUrl, '/');
        $siteId = $site?->id;
        $ecommerceEnabled = $this->isEcommerceModuleAvailableForSite($project, $site);
        $publicPrefix = ($normalizedBaseUrl !== '' && $siteId)
            ? "{$normalizedBaseUrl}/public/sites/{$siteId}/ecommerce"
            : null;

        return [
            'enabled' => $ecommerceEnabled && $publicPrefix !== null,
            'site_id' => $siteId,
            'api_base_url' => $normalizedBaseUrl,
            'products_url' => $publicPrefix ? "{$publicPrefix}/products" : null,
            'product_url_pattern' => $publicPrefix ? "{$publicPrefix}/products/{slug}" : null,
            'payment_options_url' => $publicPrefix ? "{$publicPrefix}/payment-options" : null,
            'create_cart_url' => $publicPrefix ? "{$publicPrefix}/carts" : null,
            'cart_url_pattern' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}" : null,
            'cart_items_url_pattern' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/items" : null,
            'cart_item_url_pattern' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/items/{item_id}" : null,
            'coupon_url_pattern' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/coupon" : null,
            'shipping_options_url_pattern' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/shipping/options" : null,
            'shipping_update_url_pattern' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/shipping" : null,
            'checkout_validate_url_pattern' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/checkout/validate" : null,
            'shipment_tracking_url' => $publicPrefix ? "{$publicPrefix}/shipments/track" : null,
            'checkout_url_pattern' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/checkout" : null,
            'customer_orders_url' => $publicPrefix ? "{$publicPrefix}/customer-orders" : null,
            'customer_order_url_pattern' => $publicPrefix ? "{$publicPrefix}/customer-orders/{order_id}" : null,
            'payment_start_url_pattern' => $publicPrefix ? "{$publicPrefix}/orders/{order_id}/payments/start" : null,
            'customer_me_url' => ($normalizedBaseUrl !== '' && $siteId)
                ? "{$normalizedBaseUrl}/public/sites/{$siteId}/customers/me"
                : null,
            'customer_login_url' => ($normalizedBaseUrl !== '' && $siteId)
                ? "{$normalizedBaseUrl}/public/sites/{$siteId}/customers/login"
                : null,
            'customer_register_url' => ($normalizedBaseUrl !== '' && $siteId)
                ? "{$normalizedBaseUrl}/public/sites/{$siteId}/customers/register"
                : null,
            'customer_logout_url' => ($normalizedBaseUrl !== '' && $siteId)
                ? "{$normalizedBaseUrl}/public/sites/{$siteId}/customers/logout"
                : null,
            'customer_me_update_url' => ($normalizedBaseUrl !== '' && $siteId)
                ? "{$normalizedBaseUrl}/public/sites/{$siteId}/customers/me"
                : null,
            'auth_otp_request_url' => ($normalizedBaseUrl !== '' && $siteId)
                ? "{$normalizedBaseUrl}/public/sites/{$siteId}/auth/otp/request"
                : null,
            'auth_otp_verify_url' => ($normalizedBaseUrl !== '' && $siteId)
                ? "{$normalizedBaseUrl}/public/sites/{$siteId}/auth/otp/verify"
                : null,
            'auth_google_url' => ($normalizedBaseUrl !== '' && $siteId)
                ? "{$normalizedBaseUrl}/public/sites/{$siteId}/auth/google"
                : null,
            'auth_facebook_url' => ($normalizedBaseUrl !== '' && $siteId)
                ? "{$normalizedBaseUrl}/public/sites/{$siteId}/auth/facebook"
                : null,
            'cart_storage_key' => $siteId ? "webby:ecommerce:cart:{$siteId}" : null,
            'events' => [
                'cart_updated' => 'webby:ecommerce:cart-updated',
            ],
            'widgets' => [
                'products_selector' => '[data-webby-ecommerce-products]',
                'search_selector' => '[data-webby-ecommerce-search]',
                'categories_selector' => '[data-webby-ecommerce-categories]',
                'product_detail_selector' => '[data-webby-ecommerce-product-detail]',
                'product_gallery_selector' => '[data-webby-ecommerce-product-gallery]',
                'coupon_selector' => '[data-webby-ecommerce-coupon]',
                'checkout_form_selector' => '[data-webby-ecommerce-checkout-form]',
                'order_summary_selector' => '[data-webby-ecommerce-order-summary]',
                'shipping_selector' => '[data-webby-ecommerce-shipping-selector]',
                'payment_selector' => '[data-webby-ecommerce-payment-selector]',
                'orders_list_selector' => '[data-webby-ecommerce-orders-list]',
                'order_detail_selector' => '[data-webby-ecommerce-order-detail]',
                'cart_selector' => '[data-webby-ecommerce-cart]',
                'auth_selector' => '[data-webby-ecommerce-auth]',
                'account_profile_selector' => '[data-webby-ecommerce-account-profile]',
                'account_security_selector' => '[data-webby-ecommerce-account-security]',
            ],
        ];
    }

    public function buildRuntimeBookingConfig(Project $project, ?Site $site, string $apiBaseUrl): array
    {
        $normalizedBaseUrl = rtrim($apiBaseUrl, '/');
        $siteId = $site?->id;
        $bookingEnabled = $this->isBookingModuleAvailableForSite($project, $site);
        $prepaymentEnabled = $this->isBookingPrepaymentEnabledForSite($project, $site);
        $publicPrefix = ($normalizedBaseUrl !== '' && $siteId)
            ? "{$normalizedBaseUrl}/public/sites/{$siteId}/booking"
            : null;

        return [
            'enabled' => $bookingEnabled && $publicPrefix !== null,
            'prepayment_enabled' => $prepaymentEnabled,
            'site_id' => $siteId,
            'api_base_url' => $normalizedBaseUrl,
            'services_url' => $publicPrefix ? "{$publicPrefix}/services" : null,
            'service_detail_url_pattern' => $publicPrefix ? "{$publicPrefix}/services/{slug}" : null,
            'staff_url' => $publicPrefix ? "{$publicPrefix}/staff" : null,
            'slots_url' => $publicPrefix ? "{$publicPrefix}/slots" : null,
            'calendar_url' => $publicPrefix ? "{$publicPrefix}/calendar" : null,
            'create_booking_url' => $publicPrefix ? "{$publicPrefix}/bookings" : null,
            'my_bookings_url' => $publicPrefix ? "{$publicPrefix}/bookings/my" : null,
            'booking_url_pattern' => $publicPrefix ? "{$publicPrefix}/bookings/{booking_id}" : null,
            'pricing_url' => $publicPrefix ? "{$publicPrefix}/services" : null,
            'faq_url' => $publicPrefix ? "{$publicPrefix}/services" : null,
            'events' => [
                'booking_created' => 'webby:booking:created',
            ],
            'widgets' => [
                'booking_selector' => '[data-webby-booking-widget]',
                'services_selector' => '[data-webby-booking-services]',
                'service_detail_selector' => '[data-webby-booking-service-detail]',
                'pricing_table_selector' => '[data-webby-booking-pricing-table]',
                'faq_selector' => '[data-webby-booking-faq]',
                'form_selector' => '[data-webby-booking-form]',
                'slots_selector' => '[data-webby-booking-slots]',
                'calendar_selector' => '[data-webby-booking-calendar]',
                'staff_selector' => '[data-webby-booking-staff]',
                'manage_selector' => '[data-webby-booking-manage]',
            ],
        ];
    }

    public function resolveProjectSite(Project $project): ?Site
    {
        if (! Schema::hasTable('sites')) {
            return null;
        }

        if ($project->relationLoaded('site') && $project->site) {
            return $project->site;
        }

        $site = $project->site()->first();
        if ($site) {
            return $site;
        }

        try {
            return app(SiteProvisioningService::class)->provisionForProject($project);
        } catch (\Throwable) {
            return null;
        }
    }

    public function resolveProjectLocale(Project $project): string
    {
        $site = $this->resolveProjectSite($project);
        $siteLocale = trim((string) ($site?->locale ?? ''));
        if ($siteLocale !== '') {
            return $siteLocale;
        }

        $userLocale = trim((string) ($project->user?->locale ?? ''));
        if ($userLocale !== '') {
            return $userLocale;
        }

        $configuredLocale = trim((string) config('app.locale', 'ka'));
        if ($configuredLocale !== '') {
            return $configuredLocale;
        }

        return 'ka';
    }

    protected function buildGoalWithQualityGuardrails(string $prompt, string $locale): string
    {
        $normalizedPrompt = trim($prompt);
        $languageName = $this->resolveLocaleLanguageName($locale);

        $requirements = [
            'Deliver a complete, production-ready website build.',
            "Write all visible content in {$languageName} unless the user explicitly requests another language.",
            'For chat replies, match the language of the user\'s latest message exactly.',
            'Keep design clean and minimal; avoid decorative gradients/animations unless explicitly requested.',
            'Maintain consistent typography, spacing, and component proportions across all sections.',
            'Ensure responsive behavior for mobile, tablet, and desktop.',
            'Use semantic HTML and accessible color contrast.',
            'Do not leave TODOs, placeholders, broken assets, or dead links.',
        ];

        return $normalizedPrompt."\n\nQUALITY REQUIREMENTS:\n- ".implode("\n- ", $requirements);
    }

    protected function buildQualityContract(string $locale): array
    {
        return [
            'language' => $this->resolveLocaleLanguageName($locale),
            'style' => [
                'direction' => 'minimal',
                'avoid_unrequested_decorative_effects' => true,
                'consistent_spacing' => true,
                'consistent_typography' => true,
            ],
            'delivery' => [
                'production_ready' => true,
                'responsive_required' => ['mobile', 'tablet', 'desktop'],
                'no_placeholders' => true,
                'no_broken_assets' => true,
                'no_dead_links' => true,
            ],
            'accessibility' => [
                'semantic_html' => true,
                'contrast_legibility' => true,
            ],
        ];
    }

    protected function resolveLocaleLanguageName(string $locale): string
    {
        return match (strtolower(trim($locale))) {
            'ka', 'ka-ge' => 'Georgian',
            'en', 'en-us', 'en-gb' => 'English',
            default => 'the project locale',
        };
    }

    protected function buildProjectCapabilities(
        Project $project,
        ?array $templateContext = null,
        ?string $projectLocale = null
    ): array {
        $user = $project->user;
        $plan = $user?->getCurrentPlan();
        $site = $this->resolveProjectSite($project);
        $resolvedLocale = $projectLocale ?: $this->resolveProjectLocale($project);
        $apiBaseUrl = $this->resolveRuntimeApiBaseUrl();
        $ecommerce = $this->buildRuntimeEcommerceConfig($project, $site, $apiBaseUrl);
        $booking = $this->buildRuntimeBookingConfig($project, $site, $apiBaseUrl);

        $firebaseEnabled = false;
        if ($plan?->firebaseEnabled()) {
            $firebaseConfig = $project->getFirebaseConfig();
            $firebaseEnabled = $firebaseConfig !== null;
        }

        return [
            'firebase' => [
                'enabled' => $firebaseEnabled,
                'collection_prefix' => $project->getFirebaseCollectionPrefix(),
            ],
            'storage' => [
                'enabled' => $plan?->fileStorageEnabled() ?? false,
                'max_file_size_mb' => $plan?->getMaxFileSizeMb() ?? 10,
                'allowed_file_types' => $plan?->allowed_file_types ?? [],
            ],
            'cms' => [
                'enabled' => $site !== null,
                'site_id' => $site?->id,
                'locale' => $resolvedLocale,
                'resolve_by_domain' => true,
                'content_contract' => [
                    'settings' => 'GET /public/sites/{site_id}/settings',
                    'typography' => 'GET /public/sites/{site_id}/theme/typography',
                    'menu' => 'GET /public/sites/{site_id}/menu/{key}',
                    'page' => 'GET /public/sites/{site_id}/pages/{slug}',
                ],
                'runtime_bridge' => [
                    'bootstrap_path' => '__cms/bootstrap',
                    'query' => ['slug', 'locale'],
                ],
                'integration_notes' => [
                    'Frontend should resolve site by current domain/subdomain.',
                    'Current page slug should be fetched from published CMS endpoint.',
                    'Sections must be rendered from revision.content_json and never hardcoded.',
                    'Typography must be applied from CMS contract (font_key + heading/body/button mappings).',
                    'Template typography_tokens map should drive data-webby-typography attributes (heading/body/button).',
                    'Listen for window "webby:cms-ready" event for hydrated payload.',
                ],
            ],
            'ecommerce' => [
                'enabled' => (bool) ($ecommerce['enabled'] ?? false),
                'site_id' => $ecommerce['site_id'] ?? null,
                'storefront_contract' => [
                    'payment_options' => 'GET /public/sites/{site_id}/ecommerce/payment-options',
                    'products' => 'GET /public/sites/{site_id}/ecommerce/products',
                    'product' => 'GET /public/sites/{site_id}/ecommerce/products/{slug}',
                    'create_cart' => 'POST /public/sites/{site_id}/ecommerce/carts',
                    'add_cart_item' => 'POST /public/sites/{site_id}/ecommerce/carts/{cart_id}/items',
                    'update_cart_item' => 'PUT /public/sites/{site_id}/ecommerce/carts/{cart_id}/items/{item_id}',
                    'remove_cart_item' => 'DELETE /public/sites/{site_id}/ecommerce/carts/{cart_id}/items/{item_id}',
                    'shipping_options' => 'POST /public/sites/{site_id}/ecommerce/carts/{cart_id}/shipping/options',
                    'shipping_update' => 'PUT /public/sites/{site_id}/ecommerce/carts/{cart_id}/shipping',
                    'shipment_tracking' => 'GET /public/sites/{site_id}/ecommerce/shipments/track',
                    'checkout' => 'POST /public/sites/{site_id}/ecommerce/carts/{cart_id}/checkout',
                    'payment_start' => 'POST /public/sites/{site_id}/ecommerce/orders/{order_id}/payments/start',
                ],
                'widget_runtime' => [
                    'global_helper' => 'window.WebbyEcommerce',
                    'events' => [
                        'cart_updated' => 'webby:ecommerce:cart-updated',
                    ],
                ],
            ],
            'booking' => [
                'enabled' => (bool) ($booking['enabled'] ?? false),
                'site_id' => $booking['site_id'] ?? null,
                'prepayment' => [
                    'enabled' => (bool) ($booking['prepayment_enabled'] ?? false),
                ],
                'storefront_contract' => [
                    'services' => 'GET /public/sites/{site_id}/booking/services',
                    'slots' => 'GET /public/sites/{site_id}/booking/slots',
                    'create_booking' => 'POST /public/sites/{site_id}/booking/bookings',
                ],
                'widget_runtime' => [
                    'global_helper' => 'window.WebbyBooking',
                    'events' => [
                        'booking_created' => 'webby:booking:created',
                    ],
                ],
            ],
            'template' => [
                'selected' => $templateContext ? [
                    'id' => (string) $templateContext['id'],
                    'slug' => $templateContext['slug'],
                    'name' => $templateContext['name'],
                    'category' => $templateContext['category'],
                    'version' => $templateContext['version'],
                    'module_flags' => $templateContext['module_flags'],
                    'default_pages' => $templateContext['default_pages'],
                    'default_sections' => $templateContext['default_sections'],
                    'typography_tokens' => $templateContext['typography_tokens'],
                ] : null,
            ],
            'modules' => [
                'requested' => $templateContext['module_flags'] ?? [],
            ],
            'localization' => [
                'default_locale' => $resolvedLocale,
                'site_locale' => $site?->locale,
                'user_locale' => $user?->locale,
                'supported_locales' => ['ka', 'en'],
            ],
        ];
    }

    protected function buildThemePreset(Project $project): ?array
    {
        $presetId = $project->theme_preset;
        if (! $presetId) {
            return null;
        }

        $preset = config("theme-presets.{$presetId}");
        if (! $preset) {
            return null;
        }

        return [
            'id' => $presetId,
            'name' => $preset['name'],
            'description' => $preset['description'],
            'light' => $preset['light'],
            'dark' => $preset['dark'],
        ];
    }

    protected function resolveTemplateContext(Project $project, ?string $templateId): ?array
    {
        $template = null;

        if ($templateId !== null && $templateId !== '') {
            $template = Template::query()->find($templateId);
        }

        if (! $template && $project->relationLoaded('template') && $project->template) {
            $template = $project->template;
        }

        if (! $template && $project->template_id) {
            $template = Template::query()->find($project->template_id);
        }

        if (! $template) {
            return null;
        }

        $metadata = is_array($template->metadata) ? $template->metadata : [];

        return [
            'id' => $template->id,
            'slug' => (string) ($template->slug ?? 'default'),
            'name' => (string) ($template->name ?? 'Default'),
            'category' => (string) ($template->category ?? 'default'),
            'version' => (string) ($template->version ?? '1.0.0'),
            'module_flags' => $this->normalizeModuleFlags(Arr::get($metadata, 'module_flags', [])),
            'default_pages' => $this->normalizeDefaultPages(Arr::get($metadata, 'default_pages', [])),
            'default_sections' => $this->normalizeDefaultSections(Arr::get($metadata, 'default_sections', [])),
            'typography_tokens' => $this->normalizeTypographyTokens(Arr::get($metadata, 'typography_tokens', [])),
        ];
    }

    protected function normalizeModuleFlags(mixed $moduleFlags): array
    {
        if (! is_array($moduleFlags)) {
            return [];
        }

        $normalized = [];
        foreach ($moduleFlags as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalized[$key] = (bool) $value;
        }

        return $normalized;
    }

    protected function normalizeDefaultPages(mixed $defaultPages): array
    {
        if (! is_array($defaultPages)) {
            return [];
        }

        return array_values($defaultPages);
    }

    protected function normalizeDefaultSections(mixed $defaultSections): array
    {
        if (! is_array($defaultSections)) {
            return [];
        }

        $normalized = [];
        foreach ($defaultSections as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    protected function normalizeTypographyTokens(mixed $tokens): array
    {
        $defaults = [
            'heading' => 'heading',
            'body' => 'body',
            'button' => 'body',
        ];

        if (! is_array($tokens)) {
            return $defaults;
        }

        $allowed = [
            'base' => true,
            'heading' => true,
            'body' => true,
            'button' => true,
        ];

        $normalized = $defaults;

        foreach (['heading', 'body', 'button'] as $key) {
            $candidate = $tokens[$key] ?? null;
            if (! is_string($candidate)) {
                continue;
            }

            $value = trim(strtolower($candidate));
            if ($value === '' || ! isset($allowed[$value])) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    protected function isEcommerceModuleAvailableForSite(Project $project, ?Site $site): bool
    {
        if (! $site) {
            return false;
        }

        try {
            $site->loadMissing(['project.user', 'project.template']);
            $owner = $site->project?->user ?? $project->user;
            $modulesPayload = app(CmsModuleRegistryServiceContract::class)->modules($site, $owner);

            foreach ($modulesPayload['modules'] ?? [] as $module) {
                if (($module['key'] ?? null) !== 'ecommerce') {
                    continue;
                }

                return (bool) ($module['available'] ?? false);
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    protected function isBookingPrepaymentEnabledForSite(Project $project, ?Site $site): bool
    {
        if (! $site) {
            return false;
        }

        try {
            $site->loadMissing('project.user.plan');
            $owner = $site->project?->user ?? $project->user;

            return (bool) $owner?->canUseBookingPrepayment();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function isBookingModuleAvailableForSite(Project $project, ?Site $site): bool
    {
        if (! $site) {
            return false;
        }

        try {
            $site->loadMissing(['project.user', 'project.template']);
            $owner = $site->project?->user ?? $project->user;
            $modulesPayload = app(CmsModuleRegistryServiceContract::class)->modules($site, $owner);

            foreach ($modulesPayload['modules'] ?? [] as $module) {
                if (($module['key'] ?? null) !== 'booking') {
                    continue;
                }

                return (bool) ($module['available'] ?? false);
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    protected function resolveRuntimeApiBaseUrl(): string
    {
        try {
            $request = request();
            if ($request !== null) {
                $origin = trim((string) $request->getSchemeAndHttpHost());
                if ($origin !== '') {
                    return rtrim($origin, '/');
                }
            }
        } catch (\Throwable) {
            // Ignore missing request context and fall back to configured app URL.
        }

        $configured = trim((string) config('app.url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return '';
    }
}
