<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class Plan extends Model
{
    use HasFactory;

    protected static ?array $landingPageSelectColumns = null;

    /**
     * The attributes that are mass assignable.
     *
     * Note: The 'features' field is for display purposes only (marketing/UI).
     * It shows what features are included in the plan but does not enforce
     * any functional restrictions. Actual limits are enforced by dedicated
     * fields like max_projects, monthly_build_credits, etc.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_period',
        'features',
        'is_active',
        'is_popular',
        'sort_order',
        'ai_provider_id',
        'fallback_ai_provider_ids',
        'builder_id',
        'monthly_build_credits',
        'allow_user_ai_api_key',
        'max_projects',
        'enable_subdomains',
        'max_subdomains_per_user',
        'allow_private_visibility',
        'enable_custom_domains',
        'max_custom_domains_per_user',
        'enable_firebase',
        'allow_user_firebase_config',
        'enable_file_storage',
        'enable_booking_prepayment',
        'enable_ecommerce',
        'enable_booking',
        'max_products',
        'max_monthly_orders',
        'max_monthly_bookings',
        'enable_online_payments',
        'enable_installments',
        'allowed_payment_providers',
        'allowed_installment_providers',
        'enable_shipping',
        'allowed_courier_providers',
        'enable_custom_fonts',
        'allowed_typography_font_keys',
        'max_storage_mb',
        'max_file_size_mb',
        'allowed_file_types',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'fallback_ai_provider_ids' => 'array',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'monthly_build_credits' => 'integer',
            'allow_user_ai_api_key' => 'boolean',
            'max_projects' => 'integer',
            'enable_subdomains' => 'boolean',
            'max_subdomains_per_user' => 'integer',
            'allow_private_visibility' => 'boolean',
            'enable_custom_domains' => 'boolean',
            'max_custom_domains_per_user' => 'integer',
            'enable_firebase' => 'boolean',
            'allow_user_firebase_config' => 'boolean',
            'enable_file_storage' => 'boolean',
            'enable_booking_prepayment' => 'boolean',
            'enable_ecommerce' => 'boolean',
            'enable_booking' => 'boolean',
            'max_products' => 'integer',
            'max_monthly_orders' => 'integer',
            'max_monthly_bookings' => 'integer',
            'enable_online_payments' => 'boolean',
            'enable_installments' => 'boolean',
            'allowed_payment_providers' => 'array',
            'allowed_installment_providers' => 'array',
            'enable_shipping' => 'boolean',
            'allowed_courier_providers' => 'array',
            'enable_custom_fonts' => 'boolean',
            'allowed_typography_font_keys' => 'array',
            'max_storage_mb' => 'integer',
            'max_file_size_mb' => 'integer',
            'allowed_file_types' => 'array',
        ];
    }

    /**
     * Get all users on this plan.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Scope: Active plans only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Columns required by landing/pricing views, filtered against the live schema.
     */
    public static function landingPageSelectColumns(): array
    {
        if (self::$landingPageSelectColumns !== null) {
            return self::$landingPageSelectColumns;
        }

        $available = array_flip(Schema::getColumnListing((new static())->getTable()));
        $desired = [
            'id',
            'name',
            'slug',
            'description',
            'price',
            'billing_period',
            'features',
            'is_popular',
            'max_projects',
            'monthly_build_credits',
            'allow_user_ai_api_key',
            'enable_subdomains',
            'max_subdomains_per_user',
            'allow_private_visibility',
            'enable_custom_domains',
            'max_custom_domains_per_user',
            'enable_firebase',
            'enable_file_storage',
            'max_storage_mb',
        ];

        return self::$landingPageSelectColumns = array_values(array_filter(
            $desired,
            static fn (string $column): bool => isset($available[$column])
        ));
    }

    /**
     * Resolve platform default plan using settings with safe fallbacks.
     */
    public static function resolveDefaultPlan(): ?self
    {
        $defaultId = (int) SystemSetting::get('default_plan_id', 0);
        if ($defaultId > 0) {
            $configured = self::query()
                ->where('is_active', true)
                ->find($defaultId);

            if ($configured) {
                return $configured;
            }
        }

        $free = self::query()
            ->where('is_active', true)
            ->where('slug', 'free')
            ->first();
        if ($free) {
            return $free;
        }

        return self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * Get all subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get count of active subscribers.
     */
    public function getActiveSubscribersCountAttribute(): int
    {
        return $this->subscriptions()
            ->whereIn('status', Subscription::billableStatuses())
            ->count();
    }

    /**
     * Get all transactions for this plan (via subscriptions).
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all versioned pricing snapshots for this plan.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class);
    }

    /**
     * Get the currently active pricing version for this plan.
     */
    public function activeVersion(): HasOne
    {
        return $this->hasOne(PlanVersion::class)
            ->where('status', PlanVersion::STATUS_ACTIVE)
            ->latestOfMany('version_number');
    }

    /**
     * Resolve currently active module add-ons keyed by code.
     *
     * @return array<string, ModuleAddon>
     */
    public function getActiveAddonMap(): array
    {
        $version = $this->relationLoaded('activeVersion')
            ? $this->getRelation('activeVersion')
            : $this->activeVersion()->with('moduleAddons')->first();

        if (! $version) {
            return [];
        }

        $version->loadMissing('moduleAddons');

        $map = [];
        foreach ($version->moduleAddons as $addon) {
            $code = strtolower(trim((string) $addon->code));
            if ($code === '') {
                continue;
            }

            $map[$code] = $addon;
        }

        return $map;
    }

    /**
     * Check whether a module add-on is enabled in active pricing version.
     */
    public function addonEnabled(string $code, bool $fallback = false): bool
    {
        $normalized = strtolower(trim($code));
        if ($normalized === '') {
            return $fallback;
        }

        $addon = $this->getActiveAddonMap()[$normalized] ?? null;
        if (! $addon) {
            return $fallback;
        }

        return (bool) $addon->is_active;
    }

    /**
     * Get the primary AI provider for this plan.
     */
    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    /**
     * Get the primary builder for this plan.
     */
    public function builder(): BelongsTo
    {
        return $this->belongsTo(Builder::class);
    }

    /**
     * Get the templates available for this plan.
     */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(Template::class, 'plan_template');
    }

    /**
     * Get an active AI provider for this plan, with fallback support.
     * Returns the primary provider if active, otherwise tries fallbacks in order,
     * then the system default provider from settings.
     * Returns null if no provider can be resolved.
     */
    public function getAiProviderWithFallbacks(): ?AiProvider
    {
        // Try primary provider
        if ($this->aiProvider && $this->aiProvider->status === 'active') {
            return $this->aiProvider;
        }

        // Try fallback providers in order
        if (! empty($this->fallback_ai_provider_ids)) {
            foreach ($this->fallback_ai_provider_ids as $providerId) {
                $provider = AiProvider::find($providerId);
                if ($provider && $provider->status === 'active') {
                    return $provider;
                }
            }
        }

        // Try system default from settings
        $defaultId = SystemSetting::get('default_ai_provider_id');
        if ($defaultId) {
            $provider = AiProvider::find($defaultId);
            if ($provider && $provider->status === 'active') {
                return $provider;
            }
        }

        // Legacy/local fallback used by integrations settings.
        $internalProviderId = SystemSetting::get('internal_ai_provider_id');
        if ($internalProviderId) {
            $provider = AiProvider::find($internalProviderId);
            if ($provider && $provider->status === 'active') {
                return $provider;
            }
        }

        // Final fallback: first active provider to keep runtime resilient.
        $provider = AiProvider::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
        if ($provider) {
            return $provider;
        }

        return null;
    }

    /**
     * Get an active builder for this plan.
     * Returns the primary builder if active, otherwise falls back to
     * the system default builder.
     * Returns null if no builder can be resolved.
     */
    public function getBuilderWithFallbacks(): ?Builder
    {
        // Try primary builder
        if ($this->builder && $this->builder->status === 'active') {
            return $this->builder;
        }

        // Try system default from settings
        $defaultId = SystemSetting::get('default_builder_id');
        if ($defaultId) {
            $builder = Builder::find($defaultId);
            if ($builder && $builder->status === 'active') {
                return $builder;
            }
        }

        // Final fallback: first active builder to avoid hard lock when setting is missing.
        $builder = Builder::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
        if ($builder) {
            return $builder;
        }

        return null;
    }

    /**
     * Get the AI provider description for display.
     */
    public function getAiProviderDescriptionAttribute(): string
    {
        if ($this->aiProvider) {
            $fallbackCount = count($this->fallback_ai_provider_ids ?? []);
            if ($fallbackCount > 0) {
                return "{$this->aiProvider->name} (+{$fallbackCount} ".trans_choice('fallback|fallbacks', $fallbackCount).')';
            }

            return $this->aiProvider->name;
        }

        return __('System Default');
    }

    /**
     * Get the builder description for display.
     */
    public function getBuilderDescriptionAttribute(): string
    {
        return $this->builder?->name ?? __('System Default');
    }

    // ============================================
    // Build Credits Methods
    // ============================================

    /**
     * Get monthly build credits allocation.
     */
    public function getMonthlyBuildCredits(): int
    {
        return $this->monthly_build_credits ?? 0;
    }

    /**
     * Check if plan has unlimited build credits.
     */
    public function hasUnlimitedBuildCredits(): bool
    {
        return $this->monthly_build_credits === -1;
    }

    /**
     * Get human-readable build credits description.
     */
    public function getBuildCreditsDescriptionAttribute(): string
    {
        if ($this->hasUnlimitedBuildCredits()) {
            return 'Unlimited';
        }

        $credits = $this->monthly_build_credits ?? 0;

        if ($credits === 0) {
            return 'No credits';
        }

        if ($credits >= 1000000) {
            return number_format($credits / 1000000, 1).'M tokens/month';
        }

        if ($credits >= 1000) {
            return number_format($credits / 1000, 1).'K tokens/month';
        }

        return number_format($credits).' tokens/month';
    }

    // ============================================
    // User AI API Key Methods
    // ============================================

    /**
     * Check if plan allows users to use their own AI API key.
     */
    public function allowsUserAiApiKey(): bool
    {
        return $this->allow_user_ai_api_key ?? false;
    }

    // ============================================
    // Project Limit Methods
    // ============================================

    /**
     * Get the maximum number of projects allowed for this plan.
     */
    public function getMaxProjects(): ?int
    {
        return $this->max_projects;
    }

    /**
     * Check if plan has unlimited projects.
     */
    public function hasUnlimitedProjects(): bool
    {
        return $this->max_projects === null;
    }

    /**
     * Get human-readable project limit description.
     */
    public function getProjectLimitDescriptionAttribute(): string
    {
        if ($this->hasUnlimitedProjects()) {
            return 'Unlimited projects';
        }

        $limit = $this->max_projects ?? 0;

        if ($limit === 0) {
            return 'No projects included';
        }

        return $limit === 1 ? '1 project' : "{$limit} projects";
    }

    // ============================================
    // Subdomain Methods
    // ============================================

    /**
     * Check if plan allows subdomain publishing.
     */
    public function subdomainsEnabled(): bool
    {
        return $this->enable_subdomains ?? false;
    }

    /**
     * Get maximum subdomains per user.
     */
    public function getMaxSubdomains(): ?int
    {
        return $this->max_subdomains_per_user;
    }

    /**
     * Check if plan has unlimited subdomains.
     */
    public function hasUnlimitedSubdomains(): bool
    {
        return $this->enable_subdomains && $this->max_subdomains_per_user === null;
    }

    /**
     * Check if plan allows private visibility.
     */
    public function allowsPrivateVisibility(): bool
    {
        return $this->allow_private_visibility ?? false;
    }

    /**
     * Get human-readable subdomain limit description.
     */
    public function getSubdomainLimitDescriptionAttribute(): string
    {
        if (! $this->subdomainsEnabled()) {
            return 'Subdomains disabled';
        }

        if ($this->hasUnlimitedSubdomains()) {
            return 'Unlimited subdomains';
        }

        $limit = $this->max_subdomains_per_user ?? 0;

        return $limit === 1 ? '1 subdomain' : "{$limit} subdomains";
    }

    // ============================================
    // Custom Domain Methods
    // ============================================

    /**
     * Check if plan allows custom domain publishing.
     */
    public function customDomainsEnabled(): bool
    {
        return $this->enable_custom_domains ?? false;
    }

    /**
     * Get maximum custom domains per user.
     */
    public function getMaxCustomDomains(): ?int
    {
        return $this->max_custom_domains_per_user;
    }

    /**
     * Check if plan has unlimited custom domains.
     */
    public function hasUnlimitedCustomDomains(): bool
    {
        return $this->customDomainsEnabled() && $this->max_custom_domains_per_user === null;
    }

    /**
     * Get human-readable custom domain limit description.
     */
    public function getCustomDomainLimitDescriptionAttribute(): string
    {
        if (! $this->customDomainsEnabled()) {
            return 'Custom domains disabled';
        }

        if ($this->hasUnlimitedCustomDomains()) {
            return 'Unlimited custom domains';
        }

        $limit = $this->max_custom_domains_per_user ?? 0;

        return $limit === 1 ? '1 custom domain' : "{$limit} custom domains";
    }

    // ============================================
    // Firebase Methods
    // ============================================

    /**
     * Check if plan allows Firebase database.
     */
    public function firebaseEnabled(): bool
    {
        return $this->enable_firebase ?? false;
    }

    /**
     * Check if plan allows users to use their own Firebase config.
     */
    public function allowsUserFirebaseConfig(): bool
    {
        return $this->firebaseEnabled() && ($this->allow_user_firebase_config ?? false);
    }

    /**
     * Get human-readable Firebase description.
     */
    public function getFirebaseDescriptionAttribute(): string
    {
        if (! $this->firebaseEnabled()) {
            return 'Firebase disabled';
        }

        if ($this->allowsUserFirebaseConfig()) {
            return 'System Firebase (custom config allowed)';
        }

        return 'System Firebase';
    }

    // ============================================
    // File Storage Methods
    // ============================================

    /**
     * Check if plan allows file storage.
     */
    public function fileStorageEnabled(): bool
    {
        return $this->enable_file_storage ?? false;
    }

    /**
     * Check if plan allows booking prepayments.
     */
    public function bookingPrepaymentEnabled(): bool
    {
        return $this->enable_booking_prepayment ?? false;
    }

    /**
     * Check if plan allows ecommerce module.
     */
    public function ecommerceEnabled(): bool
    {
        return $this->enable_ecommerce ?? true;
    }

    /**
     * Check if plan allows booking module.
     */
    public function bookingEnabled(): bool
    {
        return $this->enable_booking ?? true;
    }

    /**
     * Check if plan allows online gateway payments on site checkout.
     */
    public function onlinePaymentsEnabled(): bool
    {
        return $this->enable_online_payments ?? true;
    }

    /**
     * Check if plan allows installment checkout flow.
     */
    public function installmentsEnabled(): bool
    {
        if (! $this->onlinePaymentsEnabled()) {
            return false;
        }

        return $this->enable_installments ?? true;
    }

    /**
     * Get allowed online payment provider slugs for this plan.
     * Null means all providers are allowed.
     */
    public function getAllowedPaymentProviders(): ?array
    {
        return $this->normalizeProviderAllowlist($this->allowed_payment_providers);
    }

    /**
     * Get allowed installment provider slugs for this plan.
     * Null means all installment-capable providers are allowed.
     */
    public function getAllowedInstallmentProviders(): ?array
    {
        return $this->normalizeProviderAllowlist($this->allowed_installment_providers);
    }

    /**
     * Check whether the plan allows the given online payment provider.
     */
    public function allowsPaymentProvider(string $provider): bool
    {
        if (! $this->onlinePaymentsEnabled()) {
            return false;
        }

        $allowlist = $this->getAllowedPaymentProviders();
        if ($allowlist === null) {
            return true;
        }

        $normalized = $this->normalizeProviderSlug($provider);

        return $normalized !== '' && in_array($normalized, $allowlist, true);
    }

    /**
     * Check whether the plan allows installment flow for the given provider.
     */
    public function allowsInstallmentProvider(string $provider): bool
    {
        if (! $this->installmentsEnabled()) {
            return false;
        }

        $allowlist = $this->getAllowedInstallmentProviders();
        if ($allowlist === null) {
            return true;
        }

        $normalized = $this->normalizeProviderSlug($provider);

        return $normalized !== '' && in_array($normalized, $allowlist, true);
    }

    /**
     * Check if plan allows shipping methods on storefront checkout.
     */
    public function shippingEnabled(): bool
    {
        return $this->enable_shipping ?? true;
    }

    /**
     * Get allowed courier provider slugs for this plan.
     * Null means all couriers are allowed.
     */
    public function getAllowedCourierProviders(): ?array
    {
        return $this->normalizeProviderAllowlist($this->allowed_courier_providers);
    }

    /**
     * Check whether the plan allows the given courier provider.
     */
    public function allowsCourierProvider(string $provider): bool
    {
        if (! $this->shippingEnabled()) {
            return false;
        }

        $allowlist = $this->getAllowedCourierProviders();
        if ($allowlist === null) {
            return true;
        }

        $normalized = $this->normalizeProviderSlug($provider);

        return $normalized !== '' && in_array($normalized, $allowlist, true);
    }

    /**
     * Check if plan allows tenant custom font uploads.
     */
    public function customFontsEnabled(): bool
    {
        return $this->enable_custom_fonts ?? true;
    }

    /**
     * Get allowed typography font keys for this plan.
     * Null means all registered fonts are allowed.
     *
     * @return array<int, string>|null
     */
    public function getAllowedTypographyFontKeys(): ?array
    {
        return $this->normalizeProviderAllowlist($this->allowed_typography_font_keys);
    }

    /**
     * Check whether the plan allows the given typography font key.
     */
    public function allowsTypographyFontKey(string $fontKey): bool
    {
        $allowlist = $this->getAllowedTypographyFontKeys();
        if ($allowlist === null) {
            return true;
        }

        $normalized = $this->normalizeProviderSlug($fontKey);

        return $normalized !== '' && in_array($normalized, $allowlist, true);
    }

    /**
     * Get max products allowed for this plan.
     */
    public function getMaxProducts(): ?int
    {
        return $this->max_products;
    }

    /**
     * Get max monthly orders allowed for this plan.
     */
    public function getMaxMonthlyOrders(): ?int
    {
        return $this->max_monthly_orders;
    }

    /**
     * Get max monthly bookings allowed for this plan.
     */
    public function getMaxMonthlyBookings(): ?int
    {
        return $this->max_monthly_bookings;
    }

    /**
     * Get maximum storage in MB.
     */
    public function getMaxStorageMb(): ?int
    {
        return $this->max_storage_mb;
    }

    /**
     * Check if plan has unlimited storage.
     */
    public function hasUnlimitedStorage(): bool
    {
        return $this->fileStorageEnabled() && $this->max_storage_mb === null;
    }

    /**
     * Get maximum file size in MB.
     */
    public function getMaxFileSizeMb(): int
    {
        return $this->max_file_size_mb ?? 10;
    }

    /**
     * Get allowed file types.
     */
    public function getAllowedFileTypes(): ?array
    {
        return $this->allowed_file_types;
    }

    /**
     * Get human-readable storage limit description.
     */
    public function getStorageLimitDescriptionAttribute(): string
    {
        if (! $this->fileStorageEnabled()) {
            return 'File storage disabled';
        }

        if ($this->hasUnlimitedStorage()) {
            return 'Unlimited storage';
        }

        $mb = $this->max_storage_mb ?? 0;

        if ($mb >= 1024) {
            $gb = $mb / 1024;

            return number_format($gb, $gb == (int) $gb ? 0 : 1).' GB storage';
        }

        return "{$mb} MB storage";
    }

    /**
     * @param  array<int, mixed>|null  $providers
     * @return array<int, string>|null
     */
    private function normalizeProviderAllowlist(?array $providers): ?array
    {
        if (! is_array($providers)) {
            return null;
        }

        $normalized = [];

        foreach ($providers as $provider) {
            $slug = $this->normalizeProviderSlug((string) $provider);
            if ($slug === '' || in_array($slug, $normalized, true)) {
                continue;
            }
            $normalized[] = $slug;
        }

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeProviderSlug(string $provider): string
    {
        $normalized = strtolower(trim($provider));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized) ?? '';

        return trim($normalized, '-._');
    }
}
