<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ChecksDemoMode;
use App\Models\AiProvider;
use App\Models\Builder;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DomainSettingService;
use App\Services\PricingCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminPlanController extends Controller
{
    use ChecksDemoMode;

    public function __construct(
        protected PricingCatalogService $pricingCatalog
    ) {}

    /**
     * Display a listing of plans.
     */
    public function index(Request $request)
    {
        $query = Plan::with(['aiProvider', 'builder'])
            ->withCount(['subscriptions as active_subscribers_count' => function ($query) {
                $query->whereIn('status', Subscription::billableStatuses());
            }])->orderBy('sort_order');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $plans = $query->get()->map(function ($plan) {
            $plan->ai_provider_description = $plan->ai_provider_description;
            $plan->builder_description = $plan->builder_description;

            return $plan;
        });

        // Get stats
        $stats = [
            'total_plans' => Plan::count(),
            'active_plans' => Plan::where('is_active', true)->count(),
            'total_subscribers' => Subscription::query()
                ->whereIn('status', Subscription::billableStatuses())
                ->count(),
        ];

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new plan.
     */
    public function create()
    {
        $aiProviders = AiProvider::active()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_default']);

        $builders = Builder::active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $domainSettings = app(DomainSettingService::class);

        return Inertia::render('Admin/Plans/Create', [
            'aiProviders' => $aiProviders,
            'builders' => $builders,
            'planModules' => $this->resolvePlanModules(),
            'domainSettings' => [
                'subdomainsEnabled' => $domainSettings->isSubdomainsEnabled(),
                'customDomainsEnabled' => $domainSettings->isCustomDomainsEnabled(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified plan.
     */
    public function edit(Plan $plan)
    {
        $plan->load(['aiProvider', 'builder']);

        $aiProviders = AiProvider::active()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_default']);

        $builders = Builder::active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $domainSettings = app(DomainSettingService::class);

        return Inertia::render('Admin/Plans/Edit', [
            'plan' => $plan,
            'aiProviders' => $aiProviders,
            'builders' => $builders,
            'planModules' => $this->resolvePlanModules($plan),
            'domainSettings' => [
                'subdomainsEnabled' => $domainSettings->isSubdomainsEnabled(),
                'customDomainsEnabled' => $domainSettings->isCustomDomainsEnabled(),
            ],
        ]);
    }

    /**
     * Store a newly created plan.
     */
    public function store(Request $request)
    {
        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'billing_period' => 'required|in:monthly,yearly,lifetime',
            'features' => 'nullable|array',
            'features.*.name' => 'required|string|max:255',
            'features.*.included' => 'required|boolean',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'ai_provider_id' => 'nullable|exists:ai_providers,id',
            'fallback_ai_provider_ids' => 'nullable|array',
            'fallback_ai_provider_ids.*' => 'exists:ai_providers,id',
            'builder_id' => 'nullable|exists:builders,id',
            'monthly_build_credits' => 'nullable|integer|min:-1',
            'allow_user_ai_api_key' => 'boolean',
            'max_projects' => 'nullable|integer|min:0',
            // Subdomain settings
            'enable_subdomains' => 'boolean',
            'max_subdomains_per_user' => 'nullable|integer|min:0',
            'allow_private_visibility' => 'boolean',
            // Firebase settings
            'enable_firebase' => 'boolean',
            'allow_user_firebase_config' => 'boolean',
            // File storage settings
            'enable_file_storage' => 'boolean',
            'enable_booking_prepayment' => 'boolean',
            'enable_ecommerce' => 'boolean',
            'enable_booking' => 'boolean',
            'max_products' => 'nullable|integer|min:0',
            'max_monthly_orders' => 'nullable|integer|min:0',
            'max_monthly_bookings' => 'nullable|integer|min:0',
            'enable_online_payments' => 'boolean',
            'enable_installments' => 'boolean',
            'allowed_payment_providers' => 'nullable|array',
            'allowed_payment_providers.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'allowed_installment_providers' => 'nullable|array',
            'allowed_installment_providers.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'enable_shipping' => 'boolean',
            'allowed_courier_providers' => 'nullable|array',
            'allowed_courier_providers.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'enable_custom_fonts' => 'boolean',
            'allowed_typography_font_keys' => 'nullable|array',
            'allowed_typography_font_keys.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'module_addons' => 'nullable|array',
            'module_addons.*.code' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'module_addons.*.name' => 'nullable|string|max:255',
            'module_addons.*.addon_group' => 'nullable|string|max:120',
            'module_addons.*.is_active' => 'boolean',
            'module_addons.*.sort_order' => 'nullable|integer|min:0',
            'max_storage_mb' => 'nullable|integer|min:0',
            'max_file_size_mb' => 'nullable|integer|min:1|max:500',
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string',
        ]);

        $paymentConfig = $this->resolvePaymentFeatureConfig($validated);
        $shippingConfig = $this->resolveShippingFeatureConfig($validated);
        $typographyConfig = $this->resolveTypographyFeatureConfig($validated);

        $plan = Plan::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'billing_period' => $validated['billing_period'],
            'features' => $validated['features'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
            'is_popular' => $validated['is_popular'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'ai_provider_id' => $validated['ai_provider_id'] ?? null,
            'fallback_ai_provider_ids' => $validated['fallback_ai_provider_ids'] ?? null,
            'builder_id' => $validated['builder_id'] ?? null,
            'monthly_build_credits' => $validated['monthly_build_credits'] ?? 0,
            'allow_user_ai_api_key' => $validated['allow_user_ai_api_key'] ?? false,
            'max_projects' => $validated['max_projects'] ?? null,
            // Subdomain settings
            'enable_subdomains' => $validated['enable_subdomains'] ?? false,
            'max_subdomains_per_user' => $validated['max_subdomains_per_user'] ?? null,
            'allow_private_visibility' => $validated['allow_private_visibility'] ?? false,
            // Firebase settings
            'enable_firebase' => $validated['enable_firebase'] ?? false,
            'allow_user_firebase_config' => $validated['allow_user_firebase_config'] ?? false,
            // File storage settings
            'enable_file_storage' => $validated['enable_file_storage'] ?? false,
            'enable_booking_prepayment' => $validated['enable_booking_prepayment'] ?? false,
            'enable_ecommerce' => $validated['enable_ecommerce'] ?? true,
            'enable_booking' => $validated['enable_booking'] ?? true,
            'max_products' => $validated['max_products'] ?? null,
            'max_monthly_orders' => $validated['max_monthly_orders'] ?? null,
            'max_monthly_bookings' => $validated['max_monthly_bookings'] ?? null,
            'enable_online_payments' => $paymentConfig['enable_online_payments'],
            'enable_installments' => $paymentConfig['enable_installments'],
            'allowed_payment_providers' => $paymentConfig['allowed_payment_providers'],
            'allowed_installment_providers' => $paymentConfig['allowed_installment_providers'],
            'enable_shipping' => $shippingConfig['enable_shipping'],
            'allowed_courier_providers' => $shippingConfig['allowed_courier_providers'],
            'enable_custom_fonts' => $typographyConfig['enable_custom_fonts'],
            'allowed_typography_font_keys' => $typographyConfig['allowed_typography_font_keys'],
            'max_storage_mb' => $validated['max_storage_mb'] ?? null,
            'max_file_size_mb' => $validated['max_file_size_mb'] ?? 10,
            'allowed_file_types' => $validated['allowed_file_types'] ?? null,
        ]);

        /** @var User|null $actor */
        $actor = $request->user();
        $activeVersion = $this->pricingCatalog->ensurePlanHasInitialVersion($plan, $actor);
        $this->syncModuleAddons($plan, $activeVersion, $validated['module_addons'] ?? [], $actor);

        return redirect()->route('admin.plans')->with('success', 'Plan created successfully.');
    }

    /**
     * Update the specified plan.
     */
    public function update(Request $request, Plan $plan)
    {
        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'billing_period' => 'required|in:monthly,yearly,lifetime',
            'features' => 'nullable|array',
            'features.*.name' => 'required|string|max:255',
            'features.*.included' => 'required|boolean',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'ai_provider_id' => 'nullable|exists:ai_providers,id',
            'fallback_ai_provider_ids' => 'nullable|array',
            'fallback_ai_provider_ids.*' => 'exists:ai_providers,id',
            'builder_id' => 'nullable|exists:builders,id',
            'monthly_build_credits' => 'nullable|integer|min:-1',
            'allow_user_ai_api_key' => 'boolean',
            'max_projects' => 'nullable|integer|min:0',
            // Subdomain settings
            'enable_subdomains' => 'boolean',
            'max_subdomains_per_user' => 'nullable|integer|min:0',
            'allow_private_visibility' => 'boolean',
            // Firebase settings
            'enable_firebase' => 'boolean',
            'allow_user_firebase_config' => 'boolean',
            // File storage settings
            'enable_file_storage' => 'boolean',
            'enable_booking_prepayment' => 'boolean',
            'enable_ecommerce' => 'boolean',
            'enable_booking' => 'boolean',
            'max_products' => 'nullable|integer|min:0',
            'max_monthly_orders' => 'nullable|integer|min:0',
            'max_monthly_bookings' => 'nullable|integer|min:0',
            'enable_online_payments' => 'boolean',
            'enable_installments' => 'boolean',
            'allowed_payment_providers' => 'nullable|array',
            'allowed_payment_providers.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'allowed_installment_providers' => 'nullable|array',
            'allowed_installment_providers.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'enable_shipping' => 'boolean',
            'allowed_courier_providers' => 'nullable|array',
            'allowed_courier_providers.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'enable_custom_fonts' => 'boolean',
            'allowed_typography_font_keys' => 'nullable|array',
            'allowed_typography_font_keys.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'module_addons' => 'nullable|array',
            'module_addons.*.code' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'module_addons.*.name' => 'nullable|string|max:255',
            'module_addons.*.addon_group' => 'nullable|string|max:120',
            'module_addons.*.is_active' => 'boolean',
            'module_addons.*.sort_order' => 'nullable|integer|min:0',
            'max_storage_mb' => 'nullable|integer|min:0',
            'max_file_size_mb' => 'nullable|integer|min:1|max:500',
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string',
        ]);

        $paymentConfig = $this->resolvePaymentFeatureConfig($validated, $plan);
        $shippingConfig = $this->resolveShippingFeatureConfig($validated, $plan);
        $typographyConfig = $this->resolveTypographyFeatureConfig($validated, $plan);

        /** @var User|null $actor */
        $actor = $request->user();
        $this->pricingCatalog->ensurePlanHasInitialVersion($plan, $actor);

        $plan->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'billing_period' => $validated['billing_period'],
            'features' => $validated['features'] ?? [],
            'is_active' => $validated['is_active'] ?? $plan->is_active,
            'is_popular' => $validated['is_popular'] ?? false,
            'sort_order' => $validated['sort_order'] ?? $plan->sort_order,
            'ai_provider_id' => $validated['ai_provider_id'] ?? null,
            'fallback_ai_provider_ids' => $validated['fallback_ai_provider_ids'] ?? null,
            'builder_id' => $validated['builder_id'] ?? null,
            'monthly_build_credits' => $validated['monthly_build_credits'] ?? $plan->monthly_build_credits,
            'allow_user_ai_api_key' => $validated['allow_user_ai_api_key'] ?? $plan->allow_user_ai_api_key,
            'max_projects' => $validated['max_projects'] ?? null,
            // Subdomain settings
            'enable_subdomains' => $validated['enable_subdomains'] ?? $plan->enable_subdomains,
            'max_subdomains_per_user' => $validated['max_subdomains_per_user'] ?? $plan->max_subdomains_per_user,
            'allow_private_visibility' => $validated['allow_private_visibility'] ?? $plan->allow_private_visibility,
            // Firebase settings
            'enable_firebase' => $validated['enable_firebase'] ?? $plan->enable_firebase,
            'allow_user_firebase_config' => $validated['allow_user_firebase_config'] ?? $plan->allow_user_firebase_config,
            // File storage settings
            'enable_file_storage' => $validated['enable_file_storage'] ?? $plan->enable_file_storage,
            'enable_booking_prepayment' => $validated['enable_booking_prepayment'] ?? $plan->enable_booking_prepayment,
            'enable_ecommerce' => $validated['enable_ecommerce'] ?? $plan->enable_ecommerce,
            'enable_booking' => $validated['enable_booking'] ?? $plan->enable_booking,
            'max_products' => $validated['max_products'] ?? $plan->max_products,
            'max_monthly_orders' => $validated['max_monthly_orders'] ?? $plan->max_monthly_orders,
            'max_monthly_bookings' => $validated['max_monthly_bookings'] ?? $plan->max_monthly_bookings,
            'enable_online_payments' => $paymentConfig['enable_online_payments'],
            'enable_installments' => $paymentConfig['enable_installments'],
            'allowed_payment_providers' => $paymentConfig['allowed_payment_providers'],
            'allowed_installment_providers' => $paymentConfig['allowed_installment_providers'],
            'enable_shipping' => $shippingConfig['enable_shipping'],
            'allowed_courier_providers' => $shippingConfig['allowed_courier_providers'],
            'enable_custom_fonts' => $typographyConfig['enable_custom_fonts'],
            'allowed_typography_font_keys' => $typographyConfig['allowed_typography_font_keys'],
            'max_storage_mb' => $validated['max_storage_mb'] ?? $plan->max_storage_mb,
            'max_file_size_mb' => $validated['max_file_size_mb'] ?? $plan->max_file_size_mb,
            'allowed_file_types' => $validated['allowed_file_types'] ?? $plan->allowed_file_types,
        ]);

        $activeVersion = $this->pricingCatalog->syncActiveVersionFromPlanSnapshot(
            $plan->refresh(),
            $actor,
            'admin_plan_update'
        );
        $this->syncModuleAddons($plan->refresh(), $activeVersion, $validated['module_addons'] ?? [], $actor);

        return redirect()->route('admin.plans')->with('success', 'Plan updated successfully.');
    }

    /**
     * Remove the specified plan.
     */
    public function destroy(Plan $plan)
    {
        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        // Check if plan has active subscriptions
        $activeSubscriptions = $plan->subscriptions()
            ->whereIn('status', Subscription::billableStatuses())
            ->count();

        if ($activeSubscriptions > 0) {
            return back()->withErrors([
                'plan' => "Cannot delete plan with {$activeSubscriptions} active subscription(s). Please migrate users to another plan first.",
            ]);
        }

        $plan->delete();

        return back()->with('success', 'Plan deleted successfully.');
    }

    /**
     * Toggle the active status of a plan.
     */
    public function toggleStatus(Plan $plan)
    {
        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $plan->update([
            'is_active' => ! $plan->is_active,
        ]);

        $status = $plan->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Plan {$status} successfully.");
    }

    /**
     * Reorder plans.
     */
    public function reorder(Request $request)
    {
        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $request->validate([
            'plans' => 'required|array',
            'plans.*.id' => 'required|exists:plans,id',
            'plans.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->plans as $planData) {
            Plan::where('id', $planData['id'])->update([
                'sort_order' => $planData['sort_order'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Resolve plan-editable module matrix with current active states.
     *
     * @return array<int, array{
     *   code: string,
     *   label: string,
     *   description: string,
     *   group: string,
     *   plan_field: string|null,
     *   addon_code: string|null,
     *   default_active: bool,
     *   sort_order: int,
     *   is_active: bool
     * }>
     */
    private function resolvePlanModules(?Plan $plan = null): array
    {
        $definitions = $this->planModuleDefinitions();
        $addonStates = [];

        if ($plan) {
            $activeVersion = $this->pricingCatalog
                ->ensurePlanHasInitialVersion($plan)
                ->loadMissing('moduleAddons');

            foreach ($activeVersion->moduleAddons as $addon) {
                $code = $this->normalizeProviderSlug((string) $addon->code);
                if ($code === '') {
                    continue;
                }

                $addonStates[$code] = (bool) $addon->is_active;
            }
        }

        $modules = [];
        foreach ($definitions as $index => $definition) {
            $isActive = (bool) ($definition['default_active'] ?? false);
            $planField = $definition['plan_field'] ?? null;
            $addonCode = $definition['addon_code'] ?? null;

            if ($plan && is_string($planField) && $planField !== '') {
                $isActive = (bool) data_get($plan, $planField, $isActive);
            }

            if (is_string($addonCode) && $addonCode !== '') {
                $normalizedAddonCode = $this->normalizeProviderSlug($addonCode);
                if (array_key_exists($normalizedAddonCode, $addonStates)) {
                    $addonState = (bool) $addonStates[$normalizedAddonCode];
                    $isActive = is_string($planField) && $planField !== ''
                        ? ($isActive && $addonState)
                        : $addonState;
                }
            }

            $modules[] = [
                'code' => (string) $definition['code'],
                'label' => (string) $definition['label'],
                'description' => (string) $definition['description'],
                'group' => (string) $definition['group'],
                'plan_field' => is_string($planField) ? $planField : null,
                'addon_code' => is_string($addonCode) ? $addonCode : null,
                'default_active' => (bool) ($definition['default_active'] ?? false),
                'sort_order' => $index,
                'is_active' => $isActive,
            ];
        }

        return $modules;
    }

    /**
     * @return array<int, array{
     *   code: string,
     *   label: string,
     *   description: string,
     *   group: string,
     *   plan_field?: string|null,
     *   addon_code?: string|null,
     *   default_active?: bool
     * }>
     */
    private function planModuleDefinitions(): array
    {
        return [
            [
                'code' => 'subdomains',
                'label' => 'Subdomains',
                'description' => 'Subdomain publishing',
                'group' => 'publishing',
                'plan_field' => 'enable_subdomains',
                'default_active' => false,
            ],
            [
                'code' => 'custom-domains',
                'label' => 'Custom Domains',
                'description' => 'Custom domain publishing',
                'group' => 'publishing',
                'plan_field' => 'enable_custom_domains',
                'default_active' => false,
            ],
            [
                'code' => 'private-visibility',
                'label' => 'Private Visibility',
                'description' => 'Private project visibility',
                'group' => 'publishing',
                'plan_field' => 'allow_private_visibility',
                'default_active' => false,
            ],
            [
                'code' => 'firebase',
                'label' => 'Database',
                'description' => 'Firebase database module',
                'group' => 'integrations',
                'plan_field' => 'enable_firebase',
                'default_active' => false,
            ],
            [
                'code' => 'firebase-custom-config',
                'label' => 'Custom Firebase Config',
                'description' => 'Tenant Firebase configuration',
                'group' => 'integrations',
                'plan_field' => 'allow_user_firebase_config',
                'default_active' => false,
            ],
            [
                'code' => 'file-storage',
                'label' => 'File Storage',
                'description' => 'Media and file uploads',
                'group' => 'cms',
                'plan_field' => 'enable_file_storage',
                'default_active' => false,
            ],
            [
                'code' => 'own-ai-api-key',
                'label' => 'Own AI API Key',
                'description' => 'User-owned AI provider keys',
                'group' => 'ai',
                'plan_field' => 'allow_user_ai_api_key',
                'default_active' => false,
            ],
            [
                'code' => 'ecommerce',
                'label' => 'Ecommerce',
                'description' => 'Storefront ecommerce module',
                'group' => 'commerce',
                'plan_field' => 'enable_ecommerce',
                'addon_code' => 'ecommerce',
                'default_active' => true,
            ],
            [
                'code' => 'payments-installments',
                'label' => 'Payments',
                'description' => 'Online payments and installments',
                'group' => 'commerce',
                'plan_field' => 'enable_online_payments',
                'addon_code' => 'payments-installments',
                'default_active' => true,
            ],
            [
                'code' => 'shipping',
                'label' => 'Shipping',
                'description' => 'Courier and shipping methods',
                'group' => 'commerce',
                'plan_field' => 'enable_shipping',
                'addon_code' => 'shipping',
                'default_active' => true,
            ],
            [
                'code' => 'booking',
                'label' => 'Booking',
                'description' => 'Booking module',
                'group' => 'booking',
                'plan_field' => 'enable_booking',
                'addon_code' => 'booking',
                'default_active' => true,
            ],
            [
                'code' => 'booking-prepayment',
                'label' => 'Booking Prepayment',
                'description' => 'Booking prepayment flow',
                'group' => 'booking',
                'plan_field' => 'enable_booking_prepayment',
                'default_active' => false,
            ],
            [
                'code' => 'custom-fonts',
                'label' => 'Custom Fonts',
                'description' => 'Custom typography uploads',
                'group' => 'design',
                'plan_field' => 'enable_custom_fonts',
                'default_active' => true,
            ],
            [
                'code' => 'inventory',
                'label' => 'Inventory',
                'description' => 'Advanced ecommerce inventory',
                'group' => 'commerce_advanced',
                'addon_code' => 'inventory',
                'default_active' => false,
            ],
            [
                'code' => 'accounting',
                'label' => 'Accounting',
                'description' => 'Advanced ecommerce accounting',
                'group' => 'commerce_advanced',
                'addon_code' => 'accounting',
                'default_active' => false,
            ],
            [
                'code' => 'rs-integration',
                'label' => 'RS Integration',
                'description' => 'Advanced ecommerce RS connector',
                'group' => 'commerce_advanced',
                'addon_code' => 'rs-integration',
                'default_active' => false,
            ],
            [
                'code' => 'booking-team-scheduling',
                'label' => 'Booking Team Scheduling',
                'description' => 'Advanced booking team scheduling',
                'group' => 'booking_advanced',
                'addon_code' => 'booking-team-scheduling',
                'default_active' => false,
            ],
            [
                'code' => 'booking-finance',
                'label' => 'Booking Finance',
                'description' => 'Advanced booking finance module',
                'group' => 'booking_advanced',
                'addon_code' => 'booking-finance',
                'default_active' => false,
            ],
            [
                'code' => 'booking-advanced-calendar',
                'label' => 'Booking Advanced Calendar',
                'description' => 'Advanced booking calendar features',
                'group' => 'booking_advanced',
                'addon_code' => 'booking-advanced-calendar',
                'default_active' => false,
            ],
        ];
    }

    /**
     * Persist module add-on activation states into active pricing version.
     *
     * @param  array<int, array<string, mixed>>  $moduleAddons
     */
    private function syncModuleAddons(Plan $plan, PlanVersion $version, array $moduleAddons, ?User $actor): void
    {
        if (! $actor || $moduleAddons === []) {
            return;
        }

        $seenCodes = [];
        foreach ($moduleAddons as $index => $moduleAddon) {
            $rawCode = (string) ($moduleAddon['code'] ?? '');
            $code = $this->normalizeProviderSlug($rawCode);
            if ($code === '' || in_array($code, $seenCodes, true)) {
                continue;
            }
            $seenCodes[] = $code;

            $name = trim((string) ($moduleAddon['name'] ?? ''));
            if ($name === '') {
                $name = Str::of($code)->replace(['-', '_', '.'], ' ')->title()->value();
            }

            $addonGroup = trim((string) ($moduleAddon['addon_group'] ?? 'module'));
            if ($addonGroup === '') {
                $addonGroup = 'module';
            }

            $this->pricingCatalog->upsertAddon(
                $plan,
                $version,
                [
                    'code' => $code,
                    'name' => $name,
                    'addon_group' => $addonGroup,
                    'pricing_mode' => 'fixed',
                    'amount' => 0,
                    'currency' => 'USD',
                    'is_active' => (bool) ($moduleAddon['is_active'] ?? false),
                    'sort_order' => is_numeric($moduleAddon['sort_order'] ?? null)
                        ? (int) $moduleAddon['sort_order']
                        : $index,
                ],
                $actor
            );
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *   enable_online_payments: bool,
     *   enable_installments: bool,
     *   allowed_payment_providers: array<int, string>|null,
     *   allowed_installment_providers: array<int, string>|null
     * }
     */
    private function resolvePaymentFeatureConfig(array $validated, ?Plan $existingPlan = null): array
    {
        $ecommerceEnabled = (bool) (
            $validated['enable_ecommerce']
                ?? $existingPlan?->enable_ecommerce
                ?? true
        );

        $onlinePaymentsEnabled = (bool) (
            $validated['enable_online_payments']
                ?? $existingPlan?->enable_online_payments
                ?? true
        );

        $installmentsEnabled = (bool) (
            $validated['enable_installments']
                ?? $existingPlan?->enable_installments
                ?? true
        );

        $allowedPaymentProviders = array_key_exists('allowed_payment_providers', $validated)
            ? $this->normalizeProviderAllowlist($validated['allowed_payment_providers'] ?? null)
            : $this->normalizeProviderAllowlist($existingPlan?->allowed_payment_providers);

        $allowedInstallmentProviders = array_key_exists('allowed_installment_providers', $validated)
            ? $this->normalizeProviderAllowlist($validated['allowed_installment_providers'] ?? null)
            : $this->normalizeProviderAllowlist($existingPlan?->allowed_installment_providers);

        if (! $onlinePaymentsEnabled) {
            $installmentsEnabled = false;
            $allowedPaymentProviders = null;
            $allowedInstallmentProviders = null;
        }

        if (! $installmentsEnabled) {
            $allowedInstallmentProviders = null;
        }

        if (! $ecommerceEnabled) {
            $onlinePaymentsEnabled = false;
            $installmentsEnabled = false;
            $allowedPaymentProviders = null;
            $allowedInstallmentProviders = null;
        }

        if ($allowedInstallmentProviders !== null && $allowedPaymentProviders !== null) {
            $allowedInstallmentProviders = array_values(array_filter(
                $allowedInstallmentProviders,
                static fn (string $slug): bool => in_array($slug, $allowedPaymentProviders, true)
            ));

            if ($allowedInstallmentProviders === []) {
                $allowedInstallmentProviders = null;
            }
        }

        return [
            'enable_online_payments' => $onlinePaymentsEnabled,
            'enable_installments' => $installmentsEnabled,
            'allowed_payment_providers' => $allowedPaymentProviders,
            'allowed_installment_providers' => $allowedInstallmentProviders,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *   enable_shipping: bool,
     *   allowed_courier_providers: array<int, string>|null
     * }
     */
    private function resolveShippingFeatureConfig(array $validated, ?Plan $existingPlan = null): array
    {
        $ecommerceEnabled = (bool) (
            $validated['enable_ecommerce']
                ?? $existingPlan?->enable_ecommerce
                ?? true
        );

        $shippingEnabled = (bool) (
            $validated['enable_shipping']
                ?? $existingPlan?->enable_shipping
                ?? true
        );

        $allowedCourierProviders = array_key_exists('allowed_courier_providers', $validated)
            ? $this->normalizeProviderAllowlist($validated['allowed_courier_providers'] ?? null)
            : $this->normalizeProviderAllowlist($existingPlan?->allowed_courier_providers);

        if (! $shippingEnabled) {
            $allowedCourierProviders = null;
        }

        if (! $ecommerceEnabled) {
            $shippingEnabled = false;
            $allowedCourierProviders = null;
        }

        return [
            'enable_shipping' => $shippingEnabled,
            'allowed_courier_providers' => $allowedCourierProviders,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *   enable_custom_fonts: bool,
     *   allowed_typography_font_keys: array<int, string>|null
     * }
     */
    private function resolveTypographyFeatureConfig(array $validated, ?Plan $existingPlan = null): array
    {
        $customFontsEnabled = (bool) (
            $validated['enable_custom_fonts']
                ?? $existingPlan?->enable_custom_fonts
                ?? true
        );

        $allowedTypographyFontKeys = array_key_exists('allowed_typography_font_keys', $validated)
            ? $this->normalizeProviderAllowlist($validated['allowed_typography_font_keys'] ?? null)
            : $this->normalizeProviderAllowlist($existingPlan?->allowed_typography_font_keys);

        return [
            'enable_custom_fonts' => $customFontsEnabled,
            'allowed_typography_font_keys' => $allowedTypographyFontKeys,
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeProviderAllowlist(mixed $providers): ?array
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
