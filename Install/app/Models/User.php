<?php

namespace App\Models;

use App\Traits\ConditionallyVerifiesEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use ConditionallyVerifiesEmail, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'status',
        'locale',
        'plan_id',
        'build_credits',
        'build_credit_overage_balance',
        'credits_reset_at',
        'referral_credit_balance',
        'referred_by_user_id',
        'referral_code_id_used',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'build_credits' => 'integer',
            'build_credit_overage_balance' => 'integer',
            'credits_reset_at' => 'datetime',
            'referral_credit_balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (! empty($user->locale)) {
                return;
            }

            $user->locale = (string) SystemSetting::get(
                'default_locale',
                config('app.locale', 'ka')
            );
        });
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the user's subscription plan.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function sharedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_shares')
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function tenantProfiles(): HasMany
    {
        return $this->hasMany(TenantUser::class, 'platform_user_id');
    }

    public function createdTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'created_by_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Platform admins bypass commercial feature and quota restrictions.
     */
    public function hasAdminBypass(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Get all subscriptions for this user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the active subscription for this user.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', Subscription::billableStatuses())
            ->latest();
    }

    /**
     * Get all transactions for this user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Check if user has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->whereIn('status', Subscription::billableStatuses())
            ->exists();
    }

    /**
     * Check if user has a pending subscription (awaiting approval).
     */
    public function hasPendingSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', Subscription::STATUS_PENDING)
            ->exists();
    }

    /**
     * Get the current plan through active subscription.
     * Falls back to direct plan relationship if no subscription.
     */
    public function getCurrentPlan(): ?Plan
    {
        $activeSubscription = $this->activeSubscription;

        if ($activeSubscription) {
            return $activeSubscription->plan;
        }

        if ($this->plan) {
            return $this->plan;
        }

        return Plan::resolveDefaultPlan();
    }

    /**
     * Get user consents.
     */
    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    /**
     * Get data export requests.
     */
    public function dataExportRequests(): HasMany
    {
        return $this->hasMany(DataExportRequest::class);
    }

    /**
     * Get account deletion requests.
     */
    public function accountDeletionRequests(): HasMany
    {
        return $this->hasMany(AccountDeletionRequest::class);
    }

    /**
     * Get audit logs for this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function operationLogs(): HasMany
    {
        return $this->hasMany(OperationLog::class);
    }

    public function bookingRoleAssignments(): HasMany
    {
        return $this->hasMany(BookingStaffRoleAssignment::class);
    }

    /**
     * Get user's AI settings.
     */
    public function aiSettings(): HasOne
    {
        return $this->hasOne(UserAiSettings::class);
    }

    /**
     * Get build credit usage records.
     */
    public function buildCreditUsage(): HasMany
    {
        return $this->hasMany(BuildCreditUsage::class);
    }

    // ============================================
    // Build Credits Methods
    // ============================================

    /**
     * Check if user has unlimited build credits.
     */
    public function hasUnlimitedCredits(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        return $plan && $plan->hasUnlimitedBuildCredits();
    }

    /**
     * Check if user has enough build credits.
     */
    public function hasBuildCredits(int $required = 1): bool
    {
        if ($this->hasUnlimitedCredits()) {
            return true;
        }

        return $this->build_credits >= $required;
    }

    /**
     * Deduct build credits from the user.
     */
    public function deductBuildCredits(int $amount): bool
    {
        if ($this->hasUnlimitedCredits()) {
            return true;
        }

        $amount = max(0, $amount);
        if ($amount === 0) {
            return true;
        }

        $this->refresh();

        if ($this->build_credits < $amount) {
            return false;
        }

        $overageBalance = max(0, (int) ($this->build_credit_overage_balance ?? 0));
        $monthlyPool = max(0, (int) $this->build_credits - $overageBalance);
        $deductFromMonthly = min($monthlyPool, $amount);
        $remaining = $amount - $deductFromMonthly;
        $deductFromOverage = min($overageBalance, $remaining);

        $this->forceFill([
            'build_credits' => max(0, (int) $this->build_credits - $amount),
            'build_credit_overage_balance' => max(0, $overageBalance - $deductFromOverage),
        ])->save();

        return true;
    }

    /**
     * Get remaining build credits.
     */
    public function getRemainingBuildCredits(): int
    {
        if ($this->hasUnlimitedCredits()) {
            return PHP_INT_MAX;
        }

        return max(0, $this->build_credits);
    }

    /**
     * Get monthly build credit allocation from plan.
     */
    public function getMonthlyBuildCreditsAllocation(): int
    {
        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return 0;
        }

        return $plan->getMonthlyBuildCredits();
    }

    /**
     * Refill build credits based on plan (for monthly reset).
     */
    public function refillBuildCredits(): void
    {
        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return;
        }

        if ($plan->hasUnlimitedBuildCredits()) {
            return;
        }

        $overageBalance = max(0, (int) ($this->build_credit_overage_balance ?? 0));

        $this->update([
            'build_credits' => $plan->getMonthlyBuildCredits() + $overageBalance,
            'credits_reset_at' => now(),
        ]);
    }

    // ============================================
    // User AI API Key Methods
    // ============================================

    /**
     * Check if user's plan allows using their own AI API key.
     */
    public function canUseOwnAiApiKey(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        return $plan?->allowsUserAiApiKey() ?? false;
    }

    /**
     * Check if user is currently using their own API key.
     */
    public function isUsingOwnAiApiKey(): bool
    {
        if (! $this->canUseOwnAiApiKey()) {
            return false;
        }

        $settings = $this->aiSettings;

        if (! $settings || $settings->preferred_provider === 'system') {
            return false;
        }

        return $settings->hasApiKeyFor($settings->preferred_provider);
    }

    /**
     * Get user's AI API key for a specific provider.
     */
    public function getAiApiKey(string $provider): ?string
    {
        if (! $this->canUseOwnAiApiKey()) {
            return null;
        }

        return $this->aiSettings?->getApiKeyFor($provider);
    }

    /**
     * Get user's preferred AI provider.
     */
    public function getPreferredAiProvider(): string
    {
        return $this->aiSettings?->preferred_provider ?? 'system';
    }

    /**
     * Get user's preferred AI model.
     */
    public function getPreferredAiModel(): ?string
    {
        return $this->aiSettings?->preferred_model;
    }

    // ============================================
    // Project Limit Methods
    // ============================================

    /**
     * Get the maximum number of projects allowed for this user.
     */
    public function getMaxProjects(): ?int
    {
        if ($this->hasAdminBypass()) {
            return null;
        }

        $plan = $this->getCurrentPlan();

        return $plan ? $plan->getMaxProjects() : 0;
    }

    /**
     * Check if user has unlimited projects.
     */
    public function hasUnlimitedProjects(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        return $plan && $plan->hasUnlimitedProjects();
    }

    /**
     * Check if user can create more projects.
     */
    public function canCreateMoreProjects(): bool
    {
        if ($this->hasUnlimitedProjects()) {
            return true;
        }

        $max = $this->getMaxProjects();

        if ($max === null || $max === 0) {
            return false;
        }

        $currentCount = $this->projects()->count();

        return $currentCount < $max;
    }

    /**
     * Get remaining project slots.
     */
    public function getRemainingProjectSlots(): int
    {
        if ($this->hasUnlimitedProjects()) {
            return -1;  // -1 represents unlimited
        }

        $max = $this->getMaxProjects();

        if ($max === null || $max === 0) {
            return 0;
        }

        $currentCount = $this->projects()->count();

        return max(0, $max - $currentCount);
    }

    // ============================================
    // Subdomain Methods
    // ============================================

    /**
     * Check if user's plan allows subdomain publishing.
     */
    public function canUseSubdomains(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        return $plan && $plan->subdomainsEnabled();
    }

    /**
     * Check if user can create more subdomains.
     */
    public function canCreateMoreSubdomains(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        if (! $this->canUseSubdomains()) {
            return false;
        }

        $plan = $this->getCurrentPlan();

        if ($plan->hasUnlimitedSubdomains()) {
            return true;
        }

        $max = $plan->getMaxSubdomains();

        if ($max === null || $max === 0) {
            return false;
        }

        $currentCount = $this->projects()->whereNotNull('subdomain')->count();

        return $currentCount < $max;
    }

    /**
     * Check if user's plan allows private visibility.
     */
    public function canUsePrivateVisibility(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        return $plan && $plan->allowsPrivateVisibility();
    }

    /**
     * Get subdomain usage stats.
     */
    public function getSubdomainUsage(): array
    {
        if ($this->hasAdminBypass()) {
            return [
                'used' => $this->projects()->whereNotNull('subdomain')->count(),
                'limit' => null,
                'unlimited' => true,
                'remaining' => -1,
            ];
        }

        $plan = $this->getCurrentPlan();
        $used = $this->projects()->whereNotNull('subdomain')->count();

        if (! $plan || ! $plan->subdomainsEnabled()) {
            return [
                'used' => $used,
                'limit' => 0,
                'unlimited' => false,
                'remaining' => 0,
            ];
        }

        $unlimited = $plan->hasUnlimitedSubdomains();
        $limit = $unlimited ? null : ($plan->getMaxSubdomains() ?? 0);

        return [
            'used' => $used,
            'limit' => $limit,
            'unlimited' => $unlimited,
            'remaining' => $unlimited ? -1 : max(0, ($limit ?? 0) - $used),
        ];
    }

    // ============================================
    // Custom Domain Methods
    // ============================================

    /**
     * Check if user's plan allows custom domain publishing.
     */
    public function canUseCustomDomains(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        return $plan && $plan->customDomainsEnabled();
    }

    /**
     * Check if user can create more custom domains.
     */
    public function canCreateMoreCustomDomains(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        if (! $this->canUseCustomDomains()) {
            return false;
        }

        $plan = $this->getCurrentPlan();

        if ($plan->hasUnlimitedCustomDomains()) {
            return true;
        }

        $max = $plan->getMaxCustomDomains();

        if ($max === null || $max === 0) {
            return false;
        }

        $currentCount = $this->projects()->whereNotNull('custom_domain')->count();

        return $currentCount < $max;
    }

    /**
     * Get custom domain usage stats.
     */
    public function getCustomDomainUsage(): array
    {
        if ($this->hasAdminBypass()) {
            return [
                'used' => $this->projects()->whereNotNull('custom_domain')->count(),
                'limit' => null,
                'unlimited' => true,
                'remaining' => -1,
            ];
        }

        $plan = $this->getCurrentPlan();
        $used = $this->projects()->whereNotNull('custom_domain')->count();

        if (! $plan || ! $plan->customDomainsEnabled()) {
            return [
                'used' => $used,
                'limit' => 0,
                'unlimited' => false,
                'remaining' => 0,
            ];
        }

        $unlimited = $plan->hasUnlimitedCustomDomains();
        $limit = $unlimited ? null : ($plan->getMaxCustomDomains() ?? 0);

        return [
            'used' => $used,
            'limit' => $limit,
            'unlimited' => $unlimited,
            'remaining' => $unlimited ? -1 : max(0, ($limit ?? 0) - $used),
        ];
    }

    // ============================================
    // Referral Relationships
    // ============================================

    /**
     * Get the user's referral code.
     */
    public function referralCode(): HasOne
    {
        return $this->hasOne(ReferralCode::class);
    }

    /**
     * Get the user who referred this user.
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    /**
     * Get referrals made by this user (as referrer).
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /**
     * Get referral credit transactions.
     */
    public function referralCreditTransactions(): HasMany
    {
        return $this->hasMany(ReferralCreditTransaction::class);
    }

    // ============================================
    // Referral Credit Methods
    // ============================================

    /**
     * Get or create a referral code for this user.
     */
    public function getOrCreateReferralCode(): ReferralCode
    {
        return $this->referralCode ?? ReferralCode::create(['user_id' => $this->id]);
    }

    /**
     * Check if user has enough referral credits.
     */
    public function hasReferralCredits(float $amount = 0.01): bool
    {
        return $this->referral_credit_balance >= $amount;
    }

    /**
     * Add referral credits to the user.
     */
    public function addReferralCredits(float $amount): void
    {
        $this->increment('referral_credit_balance', $amount);
    }

    /**
     * Deduct referral credits from the user.
     */
    public function deductReferralCredits(float $amount): bool
    {
        if ($this->referral_credit_balance < $amount) {
            return false;
        }

        $this->decrement('referral_credit_balance', $amount);

        return true;
    }

    /**
     * Get the referral for this user (as referee).
     */
    public function referral(): HasOne
    {
        return $this->hasOne(Referral::class, 'referee_id');
    }

    // ============================================
    // Firebase Methods
    // ============================================

    /**
     * Check if user's plan allows Firebase database.
     */
    public function canUseFirebase(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        return $plan && $plan->firebaseEnabled();
    }

    /**
     * Check if user's plan allows using their own Firebase config.
     */
    public function canUseOwnFirebaseConfig(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        return $plan && $plan->allowsUserFirebaseConfig();
    }

    // ============================================
    // File Storage Methods
    // ============================================

    /**
     * Check if user's plan allows file storage.
     */
    public function canUseFileStorage(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        return $plan && $plan->fileStorageEnabled();
    }

    /**
     * Check if user's plan allows booking prepayment.
     */
    public function canUseBookingPrepayment(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        return $plan && $plan->bookingPrepaymentEnabled();
    }

    /**
     * Check if owner's plan allows ecommerce module.
     * Legacy accounts without an attached plan are treated as unrestricted.
     */
    public function canUseEcommerce(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        return $plan->ecommerceEnabled();
    }

    /**
     * Check if owner's plan allows booking module.
     * Legacy accounts without an attached plan are treated as unrestricted.
     */
    public function canUseBooking(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        return $plan->bookingEnabled();
    }

    /**
     * Check if owner's plan allows online checkout payments.
     * Legacy accounts without an attached plan are treated as unrestricted.
     */
    public function canUseOnlinePayments(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        return $plan->onlinePaymentsEnabled();
    }

    /**
     * Check if owner's plan allows installment checkout payments.
     * Legacy accounts without an attached plan are treated as unrestricted.
     */
    public function canUseInstallments(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        return $plan->installmentsEnabled();
    }

    /**
     * Check if owner's plan allows storefront shipping methods.
     * Legacy accounts without an attached plan are treated as unrestricted.
     */
    public function canUseShipping(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        return $plan->shippingEnabled();
    }

    /**
     * Check if owner's plan allows uploading tenant custom fonts.
     * Legacy accounts without an attached plan are treated as unrestricted.
     */
    public function canUseCustomFonts(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        return $plan->customFontsEnabled();
    }

    /**
     * Check if owner's plan enables advanced ecommerce inventory module.
     */
    public function canUseEcommerceInventory(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        if (! $plan->ecommerceEnabled()) {
            return false;
        }

        return $plan->addonEnabled('inventory', false);
    }

    /**
     * Check if owner's plan enables advanced ecommerce accounting module.
     */
    public function canUseEcommerceAccounting(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        if (! $plan->ecommerceEnabled()) {
            return false;
        }

        return $plan->addonEnabled('accounting', false);
    }

    /**
     * Check if owner's plan enables advanced ecommerce RS integration module.
     */
    public function canUseEcommerceRsIntegration(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        if (! $plan->ecommerceEnabled()) {
            return false;
        }

        return $plan->addonEnabled('rs-integration', false);
    }

    /**
     * Check if owner's plan enables booking team scheduling module.
     */
    public function canUseBookingTeamScheduling(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        if (! $plan->bookingEnabled()) {
            return false;
        }

        return $plan->addonEnabled('booking-team-scheduling', false);
    }

    /**
     * Check if owner's plan enables booking finance module.
     */
    public function canUseBookingFinance(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        if (! $plan->bookingEnabled()) {
            return false;
        }

        return $plan->addonEnabled('booking-finance', false);
    }

    /**
     * Check if owner's plan enables advanced booking calendar module.
     */
    public function canUseBookingAdvancedCalendar(): bool
    {
        if ($this->hasAdminBypass()) {
            return true;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan) {
            return true;
        }

        if (! $plan->bookingEnabled()) {
            return false;
        }

        return $plan->addonEnabled('booking-advanced-calendar', false);
    }

    /**
     * Get maximum storage in MB.
     */
    public function getMaxStorageMb(): int
    {
        if ($this->hasAdminBypass()) {
            return 1024 * 1024;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan || ! $plan->fileStorageEnabled()) {
            return 0;
        }

        return $plan->getMaxStorageMb() ?? 0;
    }

    /**
     * Get total storage used across all projects in bytes.
     */
    public function getTotalStorageUsedBytes(): int
    {
        return (int) $this->projects()->sum('storage_used_bytes');
    }

    /**
     * Get remaining storage in bytes.
     * Returns -1 for unlimited storage.
     */
    public function getRemainingStorageBytes(): int
    {
        if ($this->hasAdminBypass()) {
            return -1;
        }

        $plan = $this->getCurrentPlan();

        if (! $plan || ! $plan->fileStorageEnabled()) {
            return 0;
        }

        if ($plan->hasUnlimitedStorage()) {
            return -1;
        }

        $maxBytes = ($plan->getMaxStorageMb() ?? 0) * 1024 * 1024;
        $usedBytes = $this->getTotalStorageUsedBytes();

        return max(0, $maxBytes - $usedBytes);
    }

    /**
     * Get storage usage stats.
     */
    public function getStorageUsage(): array
    {
        if ($this->hasAdminBypass()) {
            $usedBytes = $this->getTotalStorageUsedBytes();
            $usedMb = (int) round($usedBytes / (1024 * 1024));

            return [
                'used_bytes' => $usedBytes,
                'used_mb' => $usedMb,
                'limit_mb' => null,
                'unlimited' => true,
                'remaining_bytes' => -1,
                'percentage' => 0,
            ];
        }

        $plan = $this->getCurrentPlan();
        $usedBytes = $this->getTotalStorageUsedBytes();
        $usedMb = (int) round($usedBytes / (1024 * 1024));

        if (! $plan || ! $plan->fileStorageEnabled()) {
            return [
                'used_bytes' => $usedBytes,
                'used_mb' => $usedMb,
                'limit_mb' => 0,
                'unlimited' => false,
                'remaining_bytes' => 0,
                'percentage' => 0,
            ];
        }

        $unlimited = $plan->hasUnlimitedStorage();
        $limitMb = $unlimited ? null : ($plan->getMaxStorageMb() ?? 0);
        $limitBytes = $limitMb ? $limitMb * 1024 * 1024 : 0;

        return [
            'used_bytes' => $usedBytes,
            'used_mb' => $usedMb,
            'limit_mb' => $limitMb,
            'unlimited' => $unlimited,
            'remaining_bytes' => $unlimited ? -1 : max(0, $limitBytes - $usedBytes),
            'percentage' => $unlimited ? 0 : ($limitBytes > 0 ? (int) round(($usedBytes / $limitBytes) * 100) : 0),
        ];
    }

    // ============================================
    // Locale Methods
    // ============================================

    /**
     * Get the user's preferred language.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'locale', 'code');
    }

    /**
     * Get the user's locale, falling back to system default.
     */
    public function getLocale(): string
    {
        return $this->locale ?? SystemSetting::get('default_locale', config('app.locale', 'ka'));
    }

    /**
     * Check if the user's locale is RTL.
     */
    public function isRtl(): bool
    {
        return $this->language?->is_rtl ?? false;
    }
}
