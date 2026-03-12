<?php

namespace App\Services;

use App\Models\BuildCreditUsage;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\EcommerceOrder;
use App\Models\EcommerceProduct;
use App\Models\Media;
use App\Models\AiProvider;
use App\Models\Plan;
use App\Models\Page;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Referral;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdminStatsService
{
    /**
     * Cache duration in seconds (30 minutes for admin dashboard).
     */
    protected const CACHE_TTL = 1800;

    /**
     * Cache key prefix.
     */
    protected const CACHE_PREFIX = 'admin_stats:';

    /**
     * Cache duration for live AI spend control widgets (5 minutes).
     */
    protected const AI_SPEND_CACHE_TTL = 300;

    /**
     * Get all dashboard statistics.
     */
    public function getAllStats(): array
    {
        return [
            'core' => $this->getCoreStats(),
            'pending' => $this->getPendingActions(),
            'subscriptionDistribution' => $this->getSubscriptionDistribution(),
            'revenueByPaymentMethod' => $this->getRevenueByPaymentMethod(),
            'aiUsage' => $this->getAiUsageStats(),
            'aiUsageByProvider' => $this->getAiUsageByProvider(),
            'aiUsageTrend' => $this->getAiUsageTrend(),
            'aiSpendControl' => $this->getAiSpendControlStats(),
            'referralStats' => $this->getReferralStats(),
            'storageStats' => $this->getStorageStats(),
            'cmsModuleStats' => $this->getCmsModuleStats(),
            'firebaseStats' => $this->getFirebaseStats(),
            'trends' => $this->getTrendData(),
            'changes' => $this->getChangeMetrics(),
        ];
    }

    /**
     * Get core KPI statistics.
     */
    public function getCoreStats(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'core', self::CACHE_TTL, function () {
            return [
                'total_users' => User::count(),
                'active_subscriptions' => Subscription::active()->count(),
                'mrr' => $this->calculateMrr(),
                'revenue_mtd' => $this->calculateRevenueMtd(),
                'total_projects' => Project::count(),
            ];
        });
    }

    /**
     * Calculate Monthly Recurring Revenue.
     */
    protected function calculateMrr(): float
    {
        return (float) Subscription::active()
            ->with('plan')
            ->get()
            ->sum(function ($subscription) {
                $plan = $subscription->plan;
                if (! $plan) {
                    return 0;
                }

                return match ($plan->billing_period) {
                    'yearly' => $plan->price / 12,
                    'lifetime' => 0, // Lifetime doesn't contribute to MRR
                    default => $plan->price, // monthly
                };
            });
    }

    /**
     * Calculate revenue month-to-date.
     */
    protected function calculateRevenueMtd(): float
    {
        return (float) Transaction::completed()
            ->thisMonth()
            ->where('amount', '>', 0) // Exclude refunds
            ->sum('amount');
    }

    /**
     * Get pending actions requiring admin attention.
     */
    public function getPendingActions(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'pending', self::CACHE_TTL, function () {
            return [
                'awaiting_approval' => Subscription::awaitingApproval()->count(),
                'awaiting_approval_amount' => (float) Subscription::awaitingApproval()->sum('amount'),
                'failed_transactions' => Transaction::failed()->thisMonth()->count(),
                'expiring_soon' => Subscription::expiringSoon(7)->count(),
                'pending_referrals' => Referral::pending()->count(),
            ];
        });
    }

    /**
     * Get subscription distribution by plan.
     */
    public function getSubscriptionDistribution(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'subscription_dist', self::CACHE_TTL, function () {
            $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'];

            return Plan::active()
                ->withCount(['subscriptions' => function ($query) {
                    $query->where('status', Subscription::STATUS_ACTIVE);
                }])
                ->get()
                ->map(fn ($plan, $index) => [
                    'name' => $plan->name,
                    'count' => $plan->subscriptions_count,
                    'color' => $colors[$index % count($colors)],
                ])
                ->toArray();
        });
    }

    /**
     * Get revenue distribution by payment method.
     */
    public function getRevenueByPaymentMethod(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'revenue_method', self::CACHE_TTL, function () {
            return Transaction::completed()
                ->thisMonth()
                ->where('amount', '>', 0)
                ->select('payment_method', DB::raw('SUM(amount) as total'))
                ->groupBy('payment_method')
                ->get()
                ->map(fn ($row) => [
                    'method' => $this->formatPaymentMethod($row->payment_method),
                    'amount' => (float) $row->total,
                ])
                ->toArray();
        });
    }

    /**
     * Get AI usage statistics for current month.
     */
    public function getAiUsageStats(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'ai_usage', self::CACHE_TTL, function () {
            $stats = BuildCreditUsage::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->selectRaw('
                    SUM(total_tokens) as total_tokens,
                    SUM(estimated_cost) as estimated_cost,
                    COUNT(*) as request_count,
                    COUNT(DISTINCT user_id) as unique_users
                ')
                ->first();

            $ownKeyUsers = BuildCreditUsage::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->where('used_own_api_key', true)
                ->distinct('user_id')
                ->count('user_id');

            $platformUsers = BuildCreditUsage::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->where('used_own_api_key', false)
                ->distinct('user_id')
                ->count('user_id');

            return [
                'total_tokens' => (int) ($stats->total_tokens ?? 0),
                'estimated_cost' => (float) ($stats->estimated_cost ?? 0),
                'request_count' => (int) ($stats->request_count ?? 0),
                'unique_users' => (int) ($stats->unique_users ?? 0),
                'own_key_users' => $ownKeyUsers,
                'platform_users' => $platformUsers,
            ];
        });
    }

    /**
     * Get AI usage by provider for current month.
     */
    public function getAiUsageByProvider(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'ai_by_provider', self::CACHE_TTL, function () {
            return BuildCreditUsage::whereYear('build_credit_usage.created_at', now()->year)
                ->whereMonth('build_credit_usage.created_at', now()->month)
                ->join('ai_providers', 'build_credit_usage.ai_provider_id', '=', 'ai_providers.id')
                ->select(
                    'ai_providers.name as provider_name',
                    DB::raw('SUM(build_credit_usage.total_tokens) as tokens'),
                    DB::raw('SUM(build_credit_usage.estimated_cost) as cost')
                )
                ->groupBy('ai_providers.id', 'ai_providers.name')
                ->get()
                ->map(fn ($row) => [
                    'provider' => $row->provider_name,
                    'tokens' => (int) $row->tokens,
                    'cost' => (float) $row->cost,
                ])
                ->toArray();
        });
    }

    /**
     * Get AI usage trend for the last N days.
     */
    public function getAiUsageTrend(int $days = 30): array
    {
        return Cache::remember(self::CACHE_PREFIX."ai_trend_{$days}", self::CACHE_TTL, function () use ($days) {
            $startDate = now()->subDays($days)->startOfDay();

            $usage = BuildCreditUsage::where('created_at', '>=', $startDate)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total_tokens) as tokens'),
                    DB::raw('SUM(estimated_cost) as cost')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            // Fill in missing days with zeros
            $result = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $dayData = $usage->get($date);
                $result[] = [
                    'date' => $date,
                    'tokens' => (int) ($dayData->tokens ?? 0),
                    'cost' => (float) ($dayData->cost ?? 0),
                ];
            }

            return $result;
        });
    }

    /**
     * Detailed AI spend control payload for admin:
     * - Available platform tokens / credit health
     * - Connected providers and token limits
     * - Cost breakdown by site/project
     * - Recent per-request usage events (when / what / where / cost)
     */
    public function getAiSpendControlStats(int $recentLimit = 120): array
    {
        return Cache::remember(
            self::CACHE_PREFIX."ai_spend_control_{$recentLimit}",
            self::AI_SPEND_CACHE_TTL,
            function () use ($recentLimit) {
                $providers = AiProvider::query()
                    ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                    ->orderBy('name')
                    ->get();

                $activeProviders = $providers->where('status', 'active');

                $availability = [
                    'platform_remaining_tokens' => (int) User::query()
                        ->where('role', '!=', 'admin')
                        ->sum('build_credits'),
                    'platform_overage_tokens' => (int) User::query()
                        ->where('role', '!=', 'admin')
                        ->sum('build_credit_overage_balance'),
                    'users_with_token_balance' => (int) User::query()
                        ->where('role', '!=', 'admin')
                        ->where('build_credits', '>', 0)
                        ->count(),
                    'users_with_unlimited_tokens' => (int) User::query()
                        ->where('role', '!=', 'admin')
                        ->whereHas('plan', fn ($q) => $q->where('monthly_build_credits', -1))
                        ->count(),
                    'users_using_own_keys' => (int) BuildCreditUsage::query()
                        ->where('used_own_api_key', true)
                        ->distinct('user_id')
                        ->count('user_id'),
                    'active_providers' => (int) $activeProviders->count(),
                    'connected_providers' => (int) $activeProviders
                        ->filter(fn (AiProvider $provider) => $provider->has_credentials)
                        ->count(),
                ];

                $providerUsageThisMonth = BuildCreditUsage::query()
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->select(
                        'ai_provider_id',
                        DB::raw('SUM(total_tokens) as total_tokens'),
                        DB::raw('SUM(estimated_cost) as total_cost'),
                        DB::raw('COUNT(*) as request_count')
                    )
                    ->groupBy('ai_provider_id')
                    ->get()
                    ->keyBy('ai_provider_id');

                $providerLimits = $providers->map(function (AiProvider $provider) use ($providerUsageThisMonth) {
                    $config = is_array($provider->config) ? $provider->config : [];
                    $defaultModel = $provider->getDefaultModel();
                    $pricing = $provider->getModelPricing($defaultModel);
                    $usage = $providerUsageThisMonth->get($provider->id);

                    return [
                        'provider_id' => $provider->id,
                        'provider' => $provider->name,
                        'status' => $provider->status,
                        'has_credentials' => (bool) $provider->has_credentials,
                        'default_model' => $defaultModel,
                        'available_models_count' => is_array($provider->available_models) ? count($provider->available_models) : 0,
                        'max_tokens_per_request' => (int) ($config['max_tokens'] ?? 8192),
                        'summarizer_max_tokens' => (int) ($config['summarizer_max_tokens'] ?? 1500),
                        'input_cost_per_1m' => (float) ($pricing['input'] ?? 0),
                        'output_cost_per_1m' => (float) ($pricing['output'] ?? 0),
                        'month_tokens' => (int) ($usage->total_tokens ?? 0),
                        'month_cost' => (float) ($usage->total_cost ?? 0),
                        'month_requests' => (int) ($usage->request_count ?? 0),
                    ];
                })->values()->toArray();

                $siteSpend = BuildCreditUsage::query()
                    ->leftJoin('projects', 'build_credit_usage.project_id', '=', 'projects.id')
                    ->leftJoin('sites', 'projects.id', '=', 'sites.project_id')
                    ->leftJoin('users', 'projects.user_id', '=', 'users.id')
                    ->select(
                        'build_credit_usage.project_id',
                        'projects.name as project_name',
                        'sites.id as site_id',
                        'sites.name as site_name',
                        'sites.primary_domain',
                        'sites.subdomain',
                        'users.id as owner_id',
                        'users.name as owner_name',
                        'users.email as owner_email',
                        DB::raw('SUM(build_credit_usage.prompt_tokens) as prompt_tokens'),
                        DB::raw('SUM(build_credit_usage.completion_tokens) as completion_tokens'),
                        DB::raw('SUM(build_credit_usage.total_tokens) as total_tokens'),
                        DB::raw('SUM(build_credit_usage.estimated_cost) as total_cost'),
                        DB::raw('COUNT(*) as request_count'),
                        DB::raw('MAX(build_credit_usage.created_at) as last_used_at')
                    )
                    ->groupBy(
                        'build_credit_usage.project_id',
                        'projects.name',
                        'sites.id',
                        'sites.name',
                        'sites.primary_domain',
                        'sites.subdomain',
                        'users.id',
                        'users.name',
                        'users.email'
                    )
                    ->get()
                    ->map(function ($row) {
                        $requestCount = (int) ($row->request_count ?? 0);
                        $totalCost = (float) ($row->total_cost ?? 0);
                        $totalTokens = (int) ($row->total_tokens ?? 0);

                        return [
                            'project_id' => $row->project_id,
                            'project_name' => $row->project_name ?? 'Unknown Project',
                            'site_id' => $row->site_id,
                            'site_name' => $row->site_name,
                            'primary_domain' => $row->primary_domain,
                            'subdomain' => $row->subdomain,
                            'owner_id' => $row->owner_id,
                            'owner_name' => $row->owner_name ?? 'Unknown',
                            'owner_email' => $row->owner_email,
                            'prompt_tokens' => (int) ($row->prompt_tokens ?? 0),
                            'completion_tokens' => (int) ($row->completion_tokens ?? 0),
                            'total_tokens' => $totalTokens,
                            'total_cost' => $totalCost,
                            'request_count' => $requestCount,
                            'avg_tokens_per_request' => $requestCount > 0 ? (int) round($totalTokens / $requestCount) : 0,
                            'avg_cost_per_request' => $requestCount > 0 ? round($totalCost / $requestCount, 6) : 0.0,
                            'last_used_at' => $row->last_used_at
                                ? Carbon::parse($row->last_used_at)->toISOString()
                                : null,
                        ];
                    })
                    ->sortByDesc('total_cost')
                    ->values()
                    ->take(100)
                    ->toArray();

                $recentUsage = BuildCreditUsage::query()
                    ->leftJoin('users', 'build_credit_usage.user_id', '=', 'users.id')
                    ->leftJoin('projects', 'build_credit_usage.project_id', '=', 'projects.id')
                    ->leftJoin('sites', 'projects.id', '=', 'sites.project_id')
                    ->leftJoin('ai_providers', 'build_credit_usage.ai_provider_id', '=', 'ai_providers.id')
                    ->select(
                        'build_credit_usage.id',
                        'build_credit_usage.builder_event_id',
                        'build_credit_usage.action',
                        'build_credit_usage.model',
                        'build_credit_usage.prompt_tokens',
                        'build_credit_usage.completion_tokens',
                        'build_credit_usage.total_tokens',
                        'build_credit_usage.estimated_cost',
                        'build_credit_usage.used_own_api_key',
                        'build_credit_usage.created_at',
                        'users.id as user_id',
                        'users.name as user_name',
                        'users.email as user_email',
                        'projects.id as project_id',
                        'projects.name as project_name',
                        'sites.id as site_id',
                        'sites.name as site_name',
                        'sites.primary_domain',
                        'sites.subdomain',
                        'ai_providers.name as provider_name'
                    )
                    ->orderByDesc('build_credit_usage.created_at')
                    ->limit($recentLimit)
                    ->get()
                    ->map(fn ($row) => [
                        'id' => (int) $row->id,
                        'builder_event_id' => $row->builder_event_id,
                        'action' => $row->action ?? 'unknown',
                        'model' => $row->model,
                        'prompt_tokens' => (int) ($row->prompt_tokens ?? 0),
                        'completion_tokens' => (int) ($row->completion_tokens ?? 0),
                        'total_tokens' => (int) ($row->total_tokens ?? 0),
                        'estimated_cost' => (float) ($row->estimated_cost ?? 0),
                        'used_own_api_key' => (bool) $row->used_own_api_key,
                        'created_at' => $row->created_at
                            ? Carbon::parse($row->created_at)->toISOString()
                            : null,
                        'user_id' => $row->user_id,
                        'user_name' => $row->user_name ?? 'Unknown',
                        'user_email' => $row->user_email,
                        'project_id' => $row->project_id,
                        'project_name' => $row->project_name,
                        'site_id' => $row->site_id,
                        'site_name' => $row->site_name,
                        'primary_domain' => $row->primary_domain,
                        'subdomain' => $row->subdomain,
                        'provider' => $row->provider_name ?? 'System',
                    ])
                    ->toArray();

                return [
                    'availability' => $availability,
                    'provider_limits' => $providerLimits,
                    'site_spend' => $siteSpend,
                    'recent_usage' => $recentUsage,
                ];
            }
        );
    }

    /**
     * Get referral statistics.
     */
    public function getReferralStats(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'referrals', self::CACHE_TTL, function () {
            $credited = Referral::credited();
            $converted = Referral::converted();

            return [
                'total' => Referral::count(),
                'converted' => $converted->count(),
                'credited' => $credited->count(),
                'commission_paid' => (float) Referral::credited()->sum('commission_amount'),
                'pending_earnings' => (float) Referral::converted()->sum('commission_amount'),
            ];
        });
    }

    /**
     * Get storage usage statistics.
     */
    public function getStorageStats(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'storage', self::CACHE_TTL, function () {
            $totalStorageBytes = (int) Project::sum('storage_used_bytes');
            $totalFiles = ProjectFile::count();
            $projectsWithFiles = Project::where('storage_used_bytes', '>', 0)->count();

            // Get top 5 users by storage usage
            $topUsers = User::select('users.id', 'users.name', 'users.email')
                ->selectRaw('COALESCE(SUM(projects.storage_used_bytes), 0) as total_storage')
                ->leftJoin('projects', 'users.id', '=', 'projects.user_id')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->having('total_storage', '>', 0)
                ->orderByDesc('total_storage')
                ->limit(5)
                ->get()
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'storage_bytes' => (int) $user->total_storage,
                ])
                ->toArray();

            // Storage by file type
            $storageByType = ProjectFile::selectRaw("
                CASE
                    WHEN mime_type LIKE 'image/%' THEN 'Images'
                    WHEN mime_type LIKE 'video/%' THEN 'Videos'
                    WHEN mime_type LIKE 'audio/%' THEN 'Audio'
                    WHEN mime_type = 'application/pdf' THEN 'PDFs'
                    ELSE 'Other'
                END as file_type,
                SUM(size) as total_size,
                COUNT(*) as file_count
            ")
                ->groupBy(DB::raw("
                    CASE
                        WHEN mime_type LIKE 'image/%' THEN 'Images'
                        WHEN mime_type LIKE 'video/%' THEN 'Videos'
                        WHEN mime_type LIKE 'audio/%' THEN 'Audio'
                        WHEN mime_type = 'application/pdf' THEN 'PDFs'
                        ELSE 'Other'
                    END
                "))
                ->get()
                ->map(fn ($row) => [
                    'type' => $row->file_type,
                    'size_bytes' => (int) $row->total_size,
                    'count' => (int) $row->file_count,
                ])
                ->toArray();

            return [
                'total_storage_bytes' => $totalStorageBytes,
                'total_files' => $totalFiles,
                'projects_with_files' => $projectsWithFiles,
                'top_users' => $topUsers,
                'storage_by_type' => $storageByType,
            ];
        });
    }

    /**
     * Get CMS / commerce / booking module overview statistics.
     */
    public function getCmsModuleStats(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'cms_modules', self::CACHE_TTL, function () {
            return [
                'sites_total' => $this->safeTableCount('sites', fn () => Site::query()->count()),
                'pages_total' => $this->safeTableCount('pages', fn () => Page::query()->count()),
                'media_assets_total' => $this->safeTableCount('media', fn () => Media::query()->count()),
                'media_assets_bytes' => $this->safeTableCount('media', fn () => (int) Media::query()->sum('size')),
                'blog_posts_total' => $this->safeTableCount('blog_posts', fn () => BlogPost::query()->count()),
                'booking_services_total' => $this->safeTableCount('booking_services', fn () => BookingService::query()->count()),
                'bookings_total' => $this->safeTableCount('bookings', fn () => Booking::query()->count()),
                'ecommerce_products_total' => $this->safeTableCount('ecommerce_products', fn () => EcommerceProduct::query()->count()),
                'ecommerce_orders_total' => $this->safeTableCount('ecommerce_orders', fn () => EcommerceOrder::query()->count()),
            ];
        });
    }

    /**
     * Get Firebase health and usage statistics.
     */
    public function getFirebaseStats(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'firebase', self::CACHE_TTL, function () {
            // Check system Firebase configuration
            $firebaseService = app(FirebaseService::class);
            $firebaseAdminService = app(FirebaseAdminService::class);

            $systemConfigured = $firebaseService->isSystemConfigured();
            $adminSdkConfigured = $firebaseAdminService->isConfigured();

            // Test connections (only if configured)
            $systemStatus = ['connected' => false, 'error' => null];
            $adminStatus = ['connected' => false, 'error' => null];

            if ($systemConfigured) {
                try {
                    $result = $firebaseService->testConnection($firebaseService->getSystemConfig());
                    $systemStatus = [
                        'connected' => $result['success'],
                        'error' => $result['error'] ?? null,
                    ];
                } catch (\Exception $e) {
                    $systemStatus['error'] = $e->getMessage();
                    Log::warning('Firebase system health check failed', ['error' => $e->getMessage()]);
                }
            }

            if ($adminSdkConfigured) {
                try {
                    $result = $firebaseAdminService->testConnection();
                    $adminStatus = [
                        'connected' => $result['success'],
                        'error' => $result['error'] ?? null,
                    ];
                } catch (\Exception $e) {
                    $adminStatus['error'] = $e->getMessage();
                    Log::warning('Firebase Admin SDK health check failed', ['error' => $e->getMessage()]);
                }
            }

            // Get project Firebase usage stats
            $projectsUsingFirebase = Project::where('uses_system_firebase', true)
                ->orWhereNotNull('firebase_config')
                ->count();

            $projectsWithCustomFirebase = Project::whereNotNull('firebase_config')
                ->where('uses_system_firebase', false)
                ->count();

            $projectsWithAdminSdk = Project::whereNotNull('firebase_admin_service_account')->count();

            return [
                'system_configured' => $systemConfigured,
                'system_status' => $systemStatus,
                'admin_sdk_configured' => $adminSdkConfigured,
                'admin_sdk_status' => $adminStatus,
                'projects_using_firebase' => $projectsUsingFirebase,
                'projects_with_custom_firebase' => $projectsWithCustomFirebase,
                'projects_with_admin_sdk' => $projectsWithAdminSdk,
            ];
        });
    }

    /**
     * Get recent users.
     */
    public function getRecentUsers(int $limit = 5): array
    {
        return User::latest()
            ->take($limit)
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->format('Y-m-d H:i'),
            ])
            ->toArray();
    }

    /**
     * Get recent transactions.
     */
    public function getRecentTransactions(int $limit = 5): array
    {
        return Transaction::with('user:id,name')
            ->latest('transaction_date')
            ->take($limit)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->transaction_id,
                'user' => $t->user?->name ?? 'Unknown',
                'amount' => (float) $t->amount,
                'status' => $t->status,
                'created_at' => $t->transaction_date?->format('Y-m-d H:i') ?? '',
            ])
            ->toArray();
    }

    /**
     * Get trend data for the last N days.
     */
    public function getTrendData(int $days = 30): array
    {
        return Cache::remember(self::CACHE_PREFIX."trends_{$days}", self::CACHE_TTL, function () use ($days) {
            $startDate = now()->subDays($days)->startOfDay();

            return [
                'revenue' => $this->getDailyRevenueTrend($startDate, $days),
                'users' => $this->getDailyUserTrend($startDate, $days),
                'projects' => $this->getDailyProjectTrend($startDate, $days),
            ];
        });
    }

    /**
     * Get daily revenue trend.
     */
    protected function getDailyRevenueTrend(Carbon $startDate, int $days): array
    {
        $revenue = Transaction::completed()
            ->where('transaction_date', '>=', $startDate)
            ->where('amount', '>', 0)
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                DB::raw('SUM(amount) as value')
            )
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->fillMissingDays($revenue, $days);
    }

    /**
     * Get daily user signup trend.
     */
    protected function getDailyUserTrend(Carbon $startDate, int $days): array
    {
        $users = User::where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as value')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->fillMissingDays($users, $days);
    }

    /**
     * Get daily project creation trend.
     */
    protected function getDailyProjectTrend(Carbon $startDate, int $days): array
    {
        $projects = Project::where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as value')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return $this->fillMissingDays($projects, $days);
    }

    /**
     * Fill missing days with zeros.
     */
    protected function fillMissingDays($data, int $days): array
    {
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayData = $data->get($date);
            $result[] = [
                'date' => $date,
                'value' => (float) ($dayData->value ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Get change metrics (month-over-month comparisons).
     */
    public function getChangeMetrics(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'changes', self::CACHE_TTL, function () {
            return [
                'users' => $this->getUserGrowthChange(),
                'subscriptions' => $this->getSubscriptionChange(),
                'revenue' => $this->getRevenueChange(),
                'projects' => $this->getProjectsChange(),
            ];
        });
    }

    /**
     * Calculate user growth change.
     */
    public function getUserGrowthChange(): array
    {
        $thisMonth = User::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $lastMonth = User::whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        return $this->calculateChange($thisMonth, $lastMonth);
    }

    /**
     * Calculate subscription change.
     */
    protected function getSubscriptionChange(): array
    {
        $thisMonth = Subscription::active()
            ->whereYear('starts_at', now()->year)
            ->whereMonth('starts_at', now()->month)
            ->count();

        $lastMonth = Subscription::active()
            ->whereYear('starts_at', now()->subMonth()->year)
            ->whereMonth('starts_at', now()->subMonth()->month)
            ->count();

        return $this->calculateChange($thisMonth, $lastMonth);
    }

    /**
     * Calculate revenue change.
     */
    protected function getRevenueChange(): array
    {
        $thisMonth = Transaction::completed()
            ->thisMonth()
            ->where('amount', '>', 0)
            ->sum('amount');

        $lastMonth = Transaction::completed()
            ->whereYear('transaction_date', now()->subMonth()->year)
            ->whereMonth('transaction_date', now()->subMonth()->month)
            ->where('amount', '>', 0)
            ->sum('amount');

        return $this->calculateChange($thisMonth, $lastMonth);
    }

    /**
     * Calculate projects change.
     */
    protected function getProjectsChange(): array
    {
        $thisMonth = Project::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $lastMonth = Project::whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        return $this->calculateChange($thisMonth, $lastMonth);
    }

    /**
     * Calculate percentage change between two values.
     */
    protected function calculateChange(float $current, float $previous): array
    {
        if ($previous == 0) {
            return [
                'value' => $current > 0 ? 100 : 0,
                'trend' => $current > 0 ? 'up' : 'neutral',
            ];
        }

        $change = (($current - $previous) / $previous) * 100;

        return [
            'value' => (int) abs(round($change)),
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
        ];
    }

    /**
     * Clear all cached stats.
     */
    public function clearCache(): void
    {
        $keys = [
            'core',
            'pending',
            'subscription_dist',
            'revenue_method',
            'ai_usage',
            'ai_by_provider',
            'ai_trend_30',
            'ai_spend_control_120',
            'referrals',
            'storage',
            'cms_modules',
            'firebase',
            'trends_30',
            'changes',
        ];

        foreach ($keys as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }

    /**
     * Format payment method for display.
     */
    protected function formatPaymentMethod(?string $method): string
    {
        return match ($method) {
            Transaction::PAYMENT_PAYPAL => 'PayPal',
            Transaction::PAYMENT_BANK_TRANSFER => 'Bank Transfer',
            Transaction::PAYMENT_MANUAL => 'Manual',
            default => ucfirst($method ?? 'Unknown'),
        };
    }

    protected function safeTableCount(string $table, callable $resolver): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        try {
            $value = $resolver();

            return (int) $value;
        } catch (\Throwable $e) {
            Log::warning('Admin stats module count failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
