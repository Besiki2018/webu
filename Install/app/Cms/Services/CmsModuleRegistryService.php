<?php

namespace App\Cms\Services;

use App\Cms\Contracts\CmsModuleRegistryServiceContract;
use App\Models\Site;
use App\Models\User;
use App\Services\DomainSettingService;
use App\Services\EntitlementService;
use Illuminate\Support\Arr;

class CmsModuleRegistryService implements CmsModuleRegistryServiceContract
{
    public const MODULE_CMS_PAGES = 'cms_pages';

    public const MODULE_CMS_MENUS = 'cms_menus';

    public const MODULE_CMS_SETTINGS = 'cms_settings';

    public const MODULE_MEDIA_LIBRARY = 'media_library';

    public const MODULE_DOMAINS = 'domains';

    public const MODULE_DATABASE = 'database';

    public const MODULE_FORMS = 'forms';

    public const MODULE_NOTIFICATIONS = 'notifications';

    public const MODULE_PORTFOLIO = 'portfolio';

    public const MODULE_REAL_ESTATE = 'real_estate';

    public const MODULE_RESTAURANT = 'restaurant';

    public const MODULE_HOTEL = 'hotel';

    public const MODULE_ECOMMERCE = 'ecommerce';

    public const MODULE_BOOKING = 'booking';

    public const MODULE_PAYMENTS = 'payments';

    public const MODULE_SHIPPING = 'shipping';

    public const MODULE_ECOMMERCE_INVENTORY = 'ecommerce_inventory';

    public const MODULE_ECOMMERCE_ACCOUNTING = 'ecommerce_accounting';

    public const MODULE_ECOMMERCE_RS = 'ecommerce_rs';

    public const MODULE_BOOKING_TEAM_SCHEDULING = 'booking_team_scheduling';

    public const MODULE_BOOKING_FINANCE = 'booking_finance';

    public const MODULE_BOOKING_ADVANCED_CALENDAR = 'booking_advanced_calendar';

    /**
     * @return array<int, array<string, mixed>>
     */
    private function registry(): array
    {
        return [
            [
                'key' => self::MODULE_CMS_PAGES,
                'label' => 'CMS Pages',
                'group' => 'cms',
                'implemented' => true,
                'default_requested' => true,
            ],
            [
                'key' => self::MODULE_CMS_MENUS,
                'label' => 'CMS Menus',
                'group' => 'cms',
                'implemented' => true,
                'default_requested' => true,
            ],
            [
                'key' => self::MODULE_CMS_SETTINGS,
                'label' => 'CMS Settings',
                'group' => 'cms',
                'implemented' => true,
                'default_requested' => true,
            ],
            [
                'key' => self::MODULE_MEDIA_LIBRARY,
                'label' => 'Media Library',
                'group' => 'cms',
                'implemented' => true,
                'default_requested' => true,
                'feature' => EntitlementService::FEATURE_FILE_STORAGE,
            ],
            [
                'key' => self::MODULE_DOMAINS,
                'label' => 'Domain Management',
                'group' => 'publishing',
                'implemented' => true,
                'default_requested' => true,
                'feature_any' => [
                    EntitlementService::FEATURE_SUBDOMAINS,
                    EntitlementService::FEATURE_CUSTOM_DOMAINS,
                ],
                'global_any' => [
                    'subdomains',
                    'custom_domains',
                ],
            ],
            [
                'key' => self::MODULE_DATABASE,
                'label' => 'Database',
                'group' => 'integrations',
                'implemented' => true,
                'default_requested' => true,
                'feature' => EntitlementService::FEATURE_FIREBASE,
            ],
            [
                'key' => self::MODULE_FORMS,
                'label' => 'Forms & Leads',
                'group' => 'engagement',
                'implemented' => true,
                'default_requested' => true,
            ],
            [
                'key' => self::MODULE_NOTIFICATIONS,
                'label' => 'Notifications',
                'group' => 'engagement',
                'implemented' => true,
                'default_requested' => true,
            ],
            [
                'key' => self::MODULE_PORTFOLIO,
                'label' => 'Portfolio',
                'group' => 'content_verticals',
                'implemented' => true,
                'default_requested' => false,
            ],
            [
                'key' => self::MODULE_REAL_ESTATE,
                'label' => 'Real Estate',
                'group' => 'content_verticals',
                'implemented' => true,
                'default_requested' => false,
            ],
            [
                'key' => self::MODULE_RESTAURANT,
                'label' => 'Restaurant',
                'group' => 'content_verticals',
                'implemented' => true,
                'default_requested' => false,
            ],
            [
                'key' => self::MODULE_HOTEL,
                'label' => 'Hotel',
                'group' => 'content_verticals',
                'implemented' => true,
                'default_requested' => false,
            ],
            [
                'key' => self::MODULE_ECOMMERCE,
                'label' => 'Ecommerce',
                'group' => 'commerce',
                'implemented' => true,
                'default_requested' => false,
                'feature' => EntitlementService::FEATURE_ECOMMERCE,
            ],
            [
                'key' => self::MODULE_BOOKING,
                'label' => 'Booking',
                'group' => 'booking',
                'implemented' => true,
                'default_requested' => false,
                'feature' => EntitlementService::FEATURE_BOOKING,
            ],
            [
                'key' => self::MODULE_PAYMENTS,
                'label' => 'Payments',
                'group' => 'commerce',
                'implemented' => true,
                'default_requested' => false,
            ],
            [
                'key' => self::MODULE_SHIPPING,
                'label' => 'Shipping',
                'group' => 'commerce',
                'implemented' => true,
                'default_requested' => false,
                'feature' => EntitlementService::FEATURE_SHIPPING ?? null,
            ],
            [
                'key' => self::MODULE_ECOMMERCE_INVENTORY,
                'label' => 'Inventory',
                'group' => 'commerce_advanced',
                'implemented' => true,
                'default_requested' => false,
                'feature' => EntitlementService::FEATURE_ECOMMERCE_INVENTORY,
            ],
            [
                'key' => self::MODULE_ECOMMERCE_ACCOUNTING,
                'label' => 'Accounting',
                'group' => 'commerce_advanced',
                'implemented' => true,
                'default_requested' => false,
                'feature' => EntitlementService::FEATURE_ECOMMERCE_ACCOUNTING,
            ],
            [
                'key' => self::MODULE_ECOMMERCE_RS,
                'label' => 'RS Integration',
                'group' => 'commerce_advanced',
                'implemented' => true,
                'default_requested' => false,
                'feature' => EntitlementService::FEATURE_ECOMMERCE_RS,
            ],
            [
                'key' => self::MODULE_BOOKING_TEAM_SCHEDULING,
                'label' => 'Booking Team Scheduling',
                'group' => 'booking_advanced',
                'implemented' => true,
                'default_requested' => false,
                'feature' => EntitlementService::FEATURE_BOOKING_TEAM_SCHEDULING,
            ],
            [
                'key' => self::MODULE_BOOKING_FINANCE,
                'label' => 'Booking Finance',
                'group' => 'booking_advanced',
                'implemented' => true,
                'default_requested' => false,
                'feature' => EntitlementService::FEATURE_BOOKING_FINANCE,
            ],
            [
                'key' => self::MODULE_BOOKING_ADVANCED_CALENDAR,
                'label' => 'Booking Advanced Calendar',
                'group' => 'booking_advanced',
                'implemented' => true,
                'default_requested' => false,
                'feature' => EntitlementService::FEATURE_BOOKING_ADVANCED_CALENDAR,
            ],
        ];
    }

    public function __construct(
        protected EntitlementService $entitlements,
        protected DomainSettingService $domains,
        protected CmsProjectTypeModuleFeatureFlagService $projectTypeFlags,
        protected CmsSiteVisibilityService $siteVisibility
    ) {}

    public function modules(Site $site, ?User $user = null): array
    {
        $featureMatrix = $this->entitlements->getForUser($user)['features'] ?? [];
        $globalFlags = [
            'subdomains' => $this->domains->isSubdomainsEnabled(),
            'custom_domains' => $this->domains->isCustomDomainsEnabled(),
        ];

        $moduleItems = [];
        foreach ($this->registry() as $definition) {
            $moduleItems[] = $this->resolveModuleState($site, $definition, $featureMatrix, $globalFlags);
        }

        $summary = [
            'total' => count($moduleItems),
            'available' => count(array_filter($moduleItems, fn (array $item): bool => (bool) ($item['available'] ?? false))),
            'disabled' => count(array_filter($moduleItems, fn (array $item): bool => ! (bool) ($item['enabled'] ?? false))),
            'not_entitled' => count(array_filter($moduleItems, fn (array $item): bool => ! (bool) ($item['entitled'] ?? false))),
            'blocked_by_project_type' => count(array_filter($moduleItems, fn (array $item): bool => ! (bool) ($item['project_type_allowed'] ?? true))),
        ];

        $projectType = $this->projectTypeFlags->resolveProjectType($site);
        $projectTypeFlags = $this->projectTypeFlags->featureFlags();

        return [
            'site_id' => $site->id,
            'project_id' => $site->project_id,
            'project_type' => $projectType,
            'project_type_flags' => [
                'enabled' => (bool) ($projectTypeFlags['enabled'] ?? false),
                'default_policy' => (string) ($projectTypeFlags['default_policy'] ?? 'allow'),
                'matrix_version' => (int) ($projectTypeFlags['matrix_version'] ?? CmsProjectTypeModuleFeatureFlagService::MATRIX_VERSION),
            ],
            'modules' => $moduleItems,
            'summary' => $summary,
        ];
    }

    public function entitlements(Site $site, ?User $user = null): array
    {
        $modulesPayload = $this->modules($site, $user);
        $plan = $user?->getCurrentPlan();

        $moduleAvailability = [];
        $moduleReasons = [];

        foreach ($modulesPayload['modules'] as $module) {
            $key = (string) ($module['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $moduleAvailability[$key] = (bool) ($module['available'] ?? false);
            $moduleReasons[$key] = $module['reason'] ?? null;
        }

        return [
            'site_id' => $site->id,
            'project_id' => $site->project_id,
            'project_type' => $modulesPayload['project_type'] ?? null,
            'features' => $this->entitlements->getForUser($user)['features'] ?? [],
            'modules' => $moduleAvailability,
            'reasons' => $moduleReasons,
            'project_type_flags' => $modulesPayload['project_type_flags'] ?? null,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, bool>  $featureMatrix
     * @param  array<string, bool>  $globalFlags
     * @return array<string, mixed>
     */
    private function resolveModuleState(
        Site $site,
        array $definition,
        array $featureMatrix,
        array $globalFlags
    ): array {
        $key = (string) ($definition['key'] ?? '');
        $implemented = (bool) ($definition['implemented'] ?? false);
        $requested = $this->isRequestedBySite($site, $key, (bool) ($definition['default_requested'] ?? false));
        $globallyEnabled = $this->isGloballyEnabled($definition, $globalFlags);
        $entitled = $this->isEntitled($definition, $featureMatrix);
        $projectTypeGate = $this->projectTypeFlags->evaluateModule($site, $key);
        $projectTypeAllowed = (bool) ($projectTypeGate['allowed'] ?? true);
        $enabled = $implemented && $requested && $globallyEnabled && $projectTypeAllowed;
        $available = $enabled && $entitled;

        return [
            'key' => $key,
            'label' => (string) ($definition['label'] ?? $key),
            'group' => (string) ($definition['group'] ?? 'general'),
            'implemented' => $implemented,
            'requested' => $requested,
            'globally_enabled' => $globallyEnabled,
            'entitled' => $entitled,
            'project_type_allowed' => $projectTypeAllowed,
            'project_type_gate' => [
                'framework_enabled' => (bool) ($projectTypeGate['framework_enabled'] ?? false),
                'project_type' => $projectTypeGate['project_type'] ?? null,
                'reason' => $projectTypeGate['reason'] ?? null,
                'rule' => $projectTypeGate['rule'] ?? null,
            ],
            'enabled' => $enabled,
            'available' => $available,
            'reason' => $this->reasonFor($implemented, $requested, $globallyEnabled, $entitled, $projectTypeGate),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, bool>  $featureMatrix
     */
    private function isEntitled(array $definition, array $featureMatrix): bool
    {
        $requiredFeature = $definition['feature'] ?? null;
        if (is_string($requiredFeature)) {
            return (bool) ($featureMatrix[$requiredFeature] ?? false);
        }

        $requiredAny = $definition['feature_any'] ?? null;
        if (is_array($requiredAny) && $requiredAny !== []) {
            foreach ($requiredAny as $feature) {
                if (is_string($feature) && (bool) ($featureMatrix[$feature] ?? false)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, bool>  $globalFlags
     */
    private function isGloballyEnabled(array $definition, array $globalFlags): bool
    {
        $requiredAny = $definition['global_any'] ?? null;
        if (! is_array($requiredAny) || $requiredAny === []) {
            return true;
        }

        foreach ($requiredAny as $flag) {
            if (is_string($flag) && (bool) ($globalFlags[$flag] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private function isRequestedBySite(Site $site, string $moduleKey, bool $defaultRequested): bool
    {
        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $moduleOverrides = Arr::get($themeSettings, 'modules', []);

        if (is_array($moduleOverrides) && array_key_exists($moduleKey, $moduleOverrides)) {
            if ((bool) $moduleOverrides[$moduleKey] === false) {
                return false;
            }
        }

        $contentDrivenRequested = $this->resolveContentDrivenRequestedState($site, $moduleKey);
        if ($contentDrivenRequested !== null) {
            return $contentDrivenRequested;
        }

        if (is_array($moduleOverrides) && array_key_exists($moduleKey, $moduleOverrides)) {
            return (bool) $moduleOverrides[$moduleKey];
        }

        $templateModuleFlags = $this->templateModuleFlags($site);
        if (array_key_exists($moduleKey, $templateModuleFlags)) {
            return (bool) $templateModuleFlags[$moduleKey];
        }

        $templateCategory = strtolower((string) ($site->project?->template?->category ?? ''));
        if ($templateCategory === 'ecommerce' && in_array($moduleKey, [
            self::MODULE_ECOMMERCE,
            self::MODULE_PAYMENTS,
            self::MODULE_SHIPPING,
        ], true)) {
            return true;
        }

        if ($templateCategory === 'booking' && $moduleKey === self::MODULE_BOOKING) {
            return true;
        }

        if ($templateCategory === 'portfolio' && $moduleKey === self::MODULE_PORTFOLIO) {
            return true;
        }

        if (in_array($templateCategory, ['real_estate', 'realestate'], true) && $moduleKey === self::MODULE_REAL_ESTATE) {
            return true;
        }

        if ($templateCategory === 'restaurant' && $moduleKey === self::MODULE_RESTAURANT) {
            return true;
        }

        if ($templateCategory === 'hotel' && $moduleKey === self::MODULE_HOTEL) {
            return true;
        }

        return $defaultRequested;
    }

    private function resolveContentDrivenRequestedState(Site $site, string $moduleKey): ?bool
    {
        $contentDrivenModules = [
            self::MODULE_ECOMMERCE,
            self::MODULE_BOOKING,
            self::MODULE_PAYMENTS,
            self::MODULE_SHIPPING,
            self::MODULE_ECOMMERCE_INVENTORY,
            self::MODULE_ECOMMERCE_ACCOUNTING,
            self::MODULE_ECOMMERCE_RS,
            self::MODULE_BOOKING_TEAM_SCHEDULING,
            self::MODULE_BOOKING_FINANCE,
            self::MODULE_BOOKING_ADVANCED_CALENDAR,
            self::MODULE_PORTFOLIO,
            self::MODULE_REAL_ESTATE,
            self::MODULE_RESTAURANT,
            self::MODULE_HOTEL,
        ];

        if (! in_array($moduleKey, $contentDrivenModules, true)) {
            return null;
        }

        if (! $this->siteVisibility->hasStructuredContent($site)) {
            return null;
        }

        $capabilities = $this->siteVisibility->capabilities($site);
        $matchesContent = match ($moduleKey) {
            self::MODULE_ECOMMERCE => (bool) ($capabilities['ecommerce'] ?? false),
            self::MODULE_BOOKING => (bool) ($capabilities['booking'] ?? false),
            self::MODULE_PAYMENTS => (bool) ($capabilities['payments'] ?? false),
            self::MODULE_SHIPPING => (bool) ($capabilities['shipping'] ?? false),
            self::MODULE_ECOMMERCE_INVENTORY => (bool) ($capabilities['ecommerce_inventory'] ?? false),
            self::MODULE_ECOMMERCE_ACCOUNTING => (bool) ($capabilities['ecommerce_accounting'] ?? false),
            self::MODULE_ECOMMERCE_RS => (bool) ($capabilities['ecommerce_rs'] ?? false),
            self::MODULE_BOOKING_TEAM_SCHEDULING => (bool) ($capabilities['booking_team_scheduling'] ?? false),
            self::MODULE_BOOKING_FINANCE => (bool) ($capabilities['booking_finance'] ?? false),
            self::MODULE_BOOKING_ADVANCED_CALENDAR => (bool) ($capabilities['booking_advanced_calendar'] ?? false),
            self::MODULE_PORTFOLIO => (bool) ($capabilities['portfolio'] ?? false),
            self::MODULE_REAL_ESTATE => (bool) ($capabilities['real_estate'] ?? false),
            self::MODULE_RESTAURANT => (bool) ($capabilities['restaurant'] ?? false),
            self::MODULE_HOTEL => (bool) ($capabilities['hotel'] ?? false),
            default => false,
        };

        return $matchesContent;
    }

    /**
     * @return array<string, bool>
     */
    private function templateModuleFlags(Site $site): array
    {
        $metadata = $site->project?->template?->metadata;
        if (! is_array($metadata)) {
            return [];
        }

        $moduleFlags = Arr::get($metadata, 'module_flags', []);
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

    private function reasonFor(
        bool $implemented,
        bool $requested,
        bool $globallyEnabled,
        bool $entitled,
        array $projectTypeGate = []
    ): ?string {
        if (! $implemented) {
            return 'Module is not implemented yet.';
        }

        if (! $requested) {
            return 'Module is not enabled for this site.';
        }

        if (! $globallyEnabled) {
            return 'Module is disabled by platform settings.';
        }

        if (! (bool) ($projectTypeGate['allowed'] ?? true)) {
            return is_string($projectTypeGate['reason'] ?? null)
                ? (string) $projectTypeGate['reason']
                : 'Module is disabled for this project type.';
        }

        if (! $entitled) {
            return 'Current plan does not include this module.';
        }

        return null;
    }
}
