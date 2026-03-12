<?php

namespace App\Cms\Services;

use App\Models\Site;
use App\Models\SystemSetting;
use Illuminate\Support\Arr;

class CmsProjectTypeModuleFeatureFlagService
{
    public const FLAG_ENABLED = 'cms_project_type_module_flags_enabled';

    public const FLAG_MATRIX = 'cms_project_type_module_flags_matrix';

    public const FLAG_DEFAULT_POLICY = 'cms_project_type_module_flags_default_policy';

    public const MATRIX_VERSION = 1;

    public function __construct(
        protected CmsSiteVisibilityService $siteVisibility
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function featureFlags(): array
    {
        $defaultPolicy = strtolower((string) SystemSetting::get(self::FLAG_DEFAULT_POLICY, 'allow'));
        if (! in_array($defaultPolicy, ['allow', 'deny'], true)) {
            $defaultPolicy = 'allow';
        }

        $matrixOverride = SystemSetting::get(self::FLAG_MATRIX, []);
        if (! is_array($matrixOverride)) {
            $matrixOverride = [];
        }

        return [
            'enabled' => (bool) SystemSetting::get(self::FLAG_ENABLED, false),
            'default_policy' => $defaultPolicy,
            'matrix_override' => $matrixOverride,
            'matrix_version' => self::MATRIX_VERSION,
        ];
    }

    /**
     * Evaluate per-project-type module visibility gate.
     *
     * @return array<string, mixed>
     */
    public function evaluateModule(Site $site, string $moduleKey, array $options = []): array
    {
        $flags = $this->featureFlags();
        if (is_array($options['flag_overrides'] ?? null)) {
            $flags = array_merge($flags, $options['flag_overrides']);
            $flags['enabled'] = (bool) ($flags['enabled'] ?? false);
            $defaultPolicy = strtolower((string) ($flags['default_policy'] ?? 'allow'));
            $flags['default_policy'] = in_array($defaultPolicy, ['allow', 'deny'], true) ? $defaultPolicy : 'allow';
            if (! is_array($flags['matrix_override'] ?? null)) {
                $flags['matrix_override'] = [];
            }
        }

        $projectType = $this->resolveProjectType($site);
        $matrix = $this->matrix($flags);
        $typeRules = is_array($matrix['types'][$projectType['key']] ?? null)
            ? $matrix['types'][$projectType['key']]
            : ['allow' => [], 'deny' => []];

        $defaultAllowed = (($matrix['default_policy'] ?? 'allow') === 'allow');

        if (! ($flags['enabled'] ?? false)) {
            return [
                'ok' => true,
                'framework_enabled' => false,
                'project_type' => $projectType,
                'module_key' => $moduleKey,
                'allowed' => true,
                'reason' => null,
                'rule' => [
                    'source' => 'disabled',
                    'match' => null,
                    'default_policy' => $matrix['default_policy'] ?? 'allow',
                    'matrix_version' => self::MATRIX_VERSION,
                ],
            ];
        }

        $denyList = $this->normalizeStringList($typeRules['deny'] ?? []);
        $allowList = $this->normalizeStringList($typeRules['allow'] ?? []);
        $wildcardDeny = in_array('*', $denyList, true);
        $wildcardAllow = in_array('*', $allowList, true);

        $match = null;
        $allowed = $defaultAllowed;

        if ($wildcardDeny || in_array($moduleKey, $denyList, true)) {
            $allowed = false;
            $match = 'deny';
        } elseif ($wildcardAllow || in_array($moduleKey, $allowList, true)) {
            $allowed = true;
            $match = 'allow';
        }

        return [
            'ok' => true,
            'framework_enabled' => true,
            'project_type' => $projectType,
            'module_key' => $moduleKey,
            'allowed' => $allowed,
            'reason' => $allowed
                ? null
                : 'Module is disabled for project type ['.$projectType['key'].'].',
            'rule' => [
                'source' => 'matrix',
                'match' => $match,
                'default_policy' => $matrix['default_policy'] ?? 'allow',
                'matrix_version' => self::MATRIX_VERSION,
            ],
        ];
    }

    /**
     * Resolve current project type using future-safe precedence.
     *
     * @return array{key:string,source:string}
     */
    public function resolveProjectType(Site $site): array
    {
        $configuredProjectType = $this->resolveConfiguredProjectType($site);
        if ($configuredProjectType !== null) {
            return $configuredProjectType;
        }

        $contentProjectType = $this->siteVisibility->detectedProjectType($site);
        if ($contentProjectType !== null) {
            return $contentProjectType;
        }

        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $moduleOverrides = Arr::get($themeSettings, 'modules', []);
        if (is_array($moduleOverrides)) {
            if ((bool) ($moduleOverrides[CmsModuleRegistryService::MODULE_ECOMMERCE] ?? false)) {
                return ['key' => 'ecommerce', 'source' => 'site.theme_settings.modules'];
            }
            if ((bool) ($moduleOverrides[CmsModuleRegistryService::MODULE_BOOKING] ?? false)) {
                return ['key' => 'booking', 'source' => 'site.theme_settings.modules'];
            }
        }

        $templateProjectType = $this->resolveTemplateCategoryProjectType($site);
        if ($templateProjectType !== null) {
            return $templateProjectType;
        }

        return ['key' => 'custom', 'source' => 'fallback'];
    }

    /**
     * Resolve explicit project-type declarations only.
     *
     * @return array{key: string, source: string}|null
     */
    public function resolveConfiguredProjectType(Site $site): ?array
    {
        $project = $site->project;

        $projectType = $this->normalizeProjectType((string) ($project?->getAttribute('type') ?? ''));
        if ($projectType !== null) {
            return ['key' => $projectType, 'source' => 'project.type'];
        }

        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $themeProjectType = $this->normalizeProjectType((string) Arr::get($themeSettings, 'project_type', ''));
        if ($themeProjectType !== null) {
            return ['key' => $themeProjectType, 'source' => 'site.theme_settings.project_type'];
        }

        return null;
    }

    /**
     * Resolve template-derived project type.
     *
     * @return array{key: string, source: string}|null
     */
    public function resolveTemplateCategoryProjectType(Site $site): ?array
    {
        $project = $site->project;
        $templateCategory = strtolower(trim((string) ($project?->template?->category ?? '')));
        $mappedTemplateType = $this->mapTemplateCategoryToProjectType($templateCategory);
        if ($mappedTemplateType === null) {
            return null;
        }

        return ['key' => $mappedTemplateType, 'source' => 'project.template.category'];
    }

    /**
     * Resolve configured or template-declared project type without content inference.
     *
     * @return array{key: string, source: string}|null
     */
    public function resolveDeclaredProjectType(Site $site): ?array
    {
        return $this->resolveConfiguredProjectType($site)
            ?? $this->resolveTemplateCategoryProjectType($site);
    }

    /**
     * @param  array<string, mixed>  $flags
     * @return array<string, mixed>
     */
    private function matrix(array $flags): array
    {
        $base = [
            'default_policy' => $flags['default_policy'] ?? 'allow',
            'types' => $this->baselineMatrixTypes(),
        ];

        $override = is_array($flags['matrix_override'] ?? null) ? $flags['matrix_override'] : [];
        $overrideTypes = is_array($override['types'] ?? null) ? $override['types'] : [];

        foreach ($overrideTypes as $typeKey => $rules) {
            if (! is_string($typeKey) || ! is_array($rules)) {
                continue;
            }

            $normalizedTypeKey = $this->normalizeProjectType($typeKey);
            if ($normalizedTypeKey === null) {
                continue;
            }

            $base['types'][$normalizedTypeKey] = [
                'allow' => $this->normalizeStringList($rules['allow'] ?? ($base['types'][$normalizedTypeKey]['allow'] ?? [])),
                'deny' => $this->normalizeStringList($rules['deny'] ?? ($base['types'][$normalizedTypeKey]['deny'] ?? [])),
            ];
        }

        if (isset($override['default_policy']) && is_string($override['default_policy'])) {
            $defaultPolicy = strtolower(trim($override['default_policy']));
            if (in_array($defaultPolicy, ['allow', 'deny'], true)) {
                $base['default_policy'] = $defaultPolicy;
            }
        }

        return $base;
    }

    /**
     * @return array<string, array{allow: array<int, string>, deny: array<int, string>}>
     */
    private function baselineMatrixTypes(): array
    {
        $commerceCore = [
            CmsModuleRegistryService::MODULE_ECOMMERCE,
            CmsModuleRegistryService::MODULE_PAYMENTS,
            CmsModuleRegistryService::MODULE_SHIPPING,
            CmsModuleRegistryService::MODULE_ECOMMERCE_INVENTORY,
            CmsModuleRegistryService::MODULE_ECOMMERCE_ACCOUNTING,
            CmsModuleRegistryService::MODULE_ECOMMERCE_RS,
        ];

        $bookingCore = [
            CmsModuleRegistryService::MODULE_BOOKING,
            CmsModuleRegistryService::MODULE_PAYMENTS,
            CmsModuleRegistryService::MODULE_BOOKING_TEAM_SCHEDULING,
            CmsModuleRegistryService::MODULE_BOOKING_FINANCE,
            CmsModuleRegistryService::MODULE_BOOKING_ADVANCED_CALENDAR,
        ];

        return [
            'ecommerce' => [
                'allow' => $commerceCore,
                'deny' => [
                    CmsModuleRegistryService::MODULE_BOOKING,
                    CmsModuleRegistryService::MODULE_BOOKING_TEAM_SCHEDULING,
                    CmsModuleRegistryService::MODULE_BOOKING_FINANCE,
                    CmsModuleRegistryService::MODULE_BOOKING_ADVANCED_CALENDAR,
                    CmsModuleRegistryService::MODULE_PORTFOLIO,
                    CmsModuleRegistryService::MODULE_REAL_ESTATE,
                    CmsModuleRegistryService::MODULE_RESTAURANT,
                    CmsModuleRegistryService::MODULE_HOTEL,
                ],
            ],
            'booking' => [
                'allow' => $bookingCore,
                'deny' => [
                    CmsModuleRegistryService::MODULE_ECOMMERCE,
                    CmsModuleRegistryService::MODULE_SHIPPING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_INVENTORY,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_ACCOUNTING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_RS,
                    CmsModuleRegistryService::MODULE_PORTFOLIO,
                    CmsModuleRegistryService::MODULE_REAL_ESTATE,
                    CmsModuleRegistryService::MODULE_RESTAURANT,
                    CmsModuleRegistryService::MODULE_HOTEL,
                ],
            ],
            'service' => [
                'allow' => [
                    CmsModuleRegistryService::MODULE_BOOKING,
                    CmsModuleRegistryService::MODULE_PAYMENTS,
                ],
                'deny' => [
                    CmsModuleRegistryService::MODULE_ECOMMERCE,
                    CmsModuleRegistryService::MODULE_SHIPPING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_INVENTORY,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_ACCOUNTING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_RS,
                    CmsModuleRegistryService::MODULE_PORTFOLIO,
                    CmsModuleRegistryService::MODULE_REAL_ESTATE,
                    CmsModuleRegistryService::MODULE_RESTAURANT,
                    CmsModuleRegistryService::MODULE_HOTEL,
                ],
            ],
            'portfolio' => [
                'allow' => [
                    CmsModuleRegistryService::MODULE_PORTFOLIO,
                ],
                'deny' => [
                    CmsModuleRegistryService::MODULE_ECOMMERCE,
                    CmsModuleRegistryService::MODULE_BOOKING,
                    CmsModuleRegistryService::MODULE_SHIPPING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_INVENTORY,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_ACCOUNTING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_RS,
                    CmsModuleRegistryService::MODULE_BOOKING_TEAM_SCHEDULING,
                    CmsModuleRegistryService::MODULE_BOOKING_FINANCE,
                    CmsModuleRegistryService::MODULE_BOOKING_ADVANCED_CALENDAR,
                    CmsModuleRegistryService::MODULE_REAL_ESTATE,
                    CmsModuleRegistryService::MODULE_RESTAURANT,
                    CmsModuleRegistryService::MODULE_HOTEL,
                ],
            ],
            'company' => [
                'allow' => [],
                'deny' => [
                    CmsModuleRegistryService::MODULE_ECOMMERCE,
                    CmsModuleRegistryService::MODULE_BOOKING,
                    CmsModuleRegistryService::MODULE_SHIPPING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_INVENTORY,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_ACCOUNTING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_RS,
                    CmsModuleRegistryService::MODULE_BOOKING_TEAM_SCHEDULING,
                    CmsModuleRegistryService::MODULE_BOOKING_FINANCE,
                    CmsModuleRegistryService::MODULE_BOOKING_ADVANCED_CALENDAR,
                    CmsModuleRegistryService::MODULE_PORTFOLIO,
                    CmsModuleRegistryService::MODULE_REAL_ESTATE,
                    CmsModuleRegistryService::MODULE_RESTAURANT,
                    CmsModuleRegistryService::MODULE_HOTEL,
                ],
            ],
            'restaurant' => [
                'allow' => [
                    CmsModuleRegistryService::MODULE_RESTAURANT,
                    CmsModuleRegistryService::MODULE_BOOKING,
                    CmsModuleRegistryService::MODULE_PAYMENTS,
                ],
                'deny' => [
                    CmsModuleRegistryService::MODULE_ECOMMERCE_INVENTORY,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_ACCOUNTING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_RS,
                    CmsModuleRegistryService::MODULE_PORTFOLIO,
                    CmsModuleRegistryService::MODULE_REAL_ESTATE,
                    CmsModuleRegistryService::MODULE_HOTEL,
                ],
            ],
            'hotel' => [
                'allow' => [
                    CmsModuleRegistryService::MODULE_HOTEL,
                    CmsModuleRegistryService::MODULE_BOOKING,
                    CmsModuleRegistryService::MODULE_PAYMENTS,
                ],
                'deny' => [
                    CmsModuleRegistryService::MODULE_ECOMMERCE,
                    CmsModuleRegistryService::MODULE_SHIPPING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_INVENTORY,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_ACCOUNTING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_RS,
                    CmsModuleRegistryService::MODULE_PORTFOLIO,
                    CmsModuleRegistryService::MODULE_REAL_ESTATE,
                    CmsModuleRegistryService::MODULE_RESTAURANT,
                ],
            ],
            'education' => [
                'allow' => [
                    CmsModuleRegistryService::MODULE_PAYMENTS,
                ],
                'deny' => [
                    CmsModuleRegistryService::MODULE_ECOMMERCE_INVENTORY,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_ACCOUNTING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_RS,
                    CmsModuleRegistryService::MODULE_BOOKING_TEAM_SCHEDULING,
                    CmsModuleRegistryService::MODULE_BOOKING_FINANCE,
                    CmsModuleRegistryService::MODULE_BOOKING_ADVANCED_CALENDAR,
                    CmsModuleRegistryService::MODULE_PORTFOLIO,
                    CmsModuleRegistryService::MODULE_REAL_ESTATE,
                    CmsModuleRegistryService::MODULE_RESTAURANT,
                    CmsModuleRegistryService::MODULE_HOTEL,
                ],
            ],
            'real_estate' => [
                'allow' => [
                    CmsModuleRegistryService::MODULE_PAYMENTS,
                    CmsModuleRegistryService::MODULE_REAL_ESTATE,
                ],
                'deny' => [
                    CmsModuleRegistryService::MODULE_ECOMMERCE_INVENTORY,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_ACCOUNTING,
                    CmsModuleRegistryService::MODULE_ECOMMERCE_RS,
                    CmsModuleRegistryService::MODULE_PORTFOLIO,
                    CmsModuleRegistryService::MODULE_BOOKING_TEAM_SCHEDULING,
                    CmsModuleRegistryService::MODULE_BOOKING_FINANCE,
                    CmsModuleRegistryService::MODULE_BOOKING_ADVANCED_CALENDAR,
                    CmsModuleRegistryService::MODULE_RESTAURANT,
                    CmsModuleRegistryService::MODULE_HOTEL,
                ],
            ],
            'custom' => [
                'allow' => [],
                'deny' => [],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $candidate = trim($value);
            if ($candidate === '') {
                continue;
            }

            if (! in_array($candidate, $normalized, true)) {
                $normalized[] = $candidate;
            }
        }

        return $normalized;
    }

    private function normalizeProjectType(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace([' ', '-'], '_', $normalized);
        $aliases = [
            'e_commerce' => 'ecommerce',
            'store' => 'ecommerce',
            'shop' => 'ecommerce',
            'booking_service' => 'booking',
            'services' => 'service',
            'realestate' => 'real_estate',
            'real_estate' => 'real_estate',
            'corporate' => 'company',
            'business' => 'company',
            'landing' => 'company',
            'web' => 'company',
        ];

        if (isset($aliases[$normalized])) {
            $normalized = $aliases[$normalized];
        }

        $allowed = [
            'ecommerce',
            'service',
            'booking',
            'portfolio',
            'company',
            'restaurant',
            'hotel',
            'education',
            'real_estate',
            'custom',
        ];

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }

    private function mapTemplateCategoryToProjectType(string $templateCategory): ?string
    {
        if ($templateCategory === '') {
            return null;
        }

        return $this->normalizeProjectType($templateCategory);
    }
}
