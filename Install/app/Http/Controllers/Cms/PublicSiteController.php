<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Contracts\CmsPublicSiteServiceContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\EcommerceProduct;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Closure;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicSiteController extends Controller
{
    private const PUBLIC_READ_CACHE_TTL_SECONDS = 60;

    public function __construct(
        protected CmsPublicSiteServiceContract $publicSites
    ) {}

    /**
     * Resolve site by custom domain or platform subdomain.
     */
    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        try {
            $payload = $this->rememberPublicRead(
                $this->cacheKey('resolve', $validated['domain'], $request),
                fn (): array => $this->publicSites->resolve(
                    $validated['domain'],
                    is_string($request->query('locale')) ? $request->query('locale') : null,
                    $request->user()
                )
            );
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    /**
     * Resolve a default public site for a template slug.
     */
    public function defaultSiteForTemplate(string $templateSlug): JsonResponse
    {
        $template = Template::query()->where('slug', $templateSlug)->first();
        if (! $template) {
            return $this->corsJson([
                'error' => 'Template not found.',
            ], 404);
        }

        $site = Site::query()
            ->whereHas('project', function ($query) use ($template): void {
                $query
                    ->where('template_id', $template->id)
                    ->whereNotNull('published_at')
                    ->where('published_visibility', 'public');
            })
            ->latest('updated_at')
            ->first();

        if (! $site) {
            return $this->corsJson([
                'error' => 'No public site found for this template.',
            ], 404);
        }

        return $this->corsJson([
            'template_slug' => $template->slug,
            'site_id' => $site->id,
            'status' => $site->status,
        ]);
    }

    /**
     * Return public global settings for a site.
     */
    public function settings(Request $request, Site $site): JsonResponse
    {
        try {
            $payload = $this->rememberPublicRead(
                $this->cacheKey('settings', $site, $request),
                fn (): array => $this->publicSites->settings(
                    $site,
                    is_string($request->query('locale')) ? $request->query('locale') : null,
                    $request->user()
                )
            );
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    /**
     * Return resolved typography contract for published site theme.
     */
    public function typography(Request $request, Site $site): JsonResponse
    {
        try {
            $payload = $this->rememberPublicRead(
                $this->cacheKey('typography', $site, $request),
                fn (): array => $this->publicSites->typography(
                    $site,
                    is_string($request->query('locale')) ? $request->query('locale') : null,
                    $request->user()
                )
            );
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    /**
     * Return public menu by key.
     */
    public function menu(Request $request, Site $site, string $key): JsonResponse
    {
        try {
            $payload = $this->rememberPublicRead(
                $this->cacheKey('menu', $site, $request, ['key' => $key]),
                fn (): array => $this->publicSites->menu(
                    $site,
                    $key,
                    is_string($request->query('locale')) ? $request->query('locale') : null,
                    $request->user()
                )
            );
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    /**
     * Public lightweight search endpoint for nav search widgets (site/products/posts modes).
     */
    public function search(Request $request, Site $site): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', 'string', 'in:site,products,posts'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        try {
            // Reuse public-site visibility/tenant access rules from the canonical public CMS service.
            $this->publicSites->settings(
                $site,
                is_string($request->query('locale')) ? $request->query('locale') : null,
                $request->user()
            );
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        $query = trim((string) ($validated['q'] ?? ''));
        $mode = (string) ($validated['mode'] ?? 'site');
        $limit = (int) ($validated['limit'] ?? 10);

        $items = $this->rememberPublicRead(
            $this->cacheKey('search', $site, $request, [
                'q' => $query,
                'mode' => $mode,
                'limit' => $limit,
            ]),
            function () use ($site, $query, $mode, $limit): array {
                if ($query === '') {
                    return [];
                }

                return match ($mode) {
                    'products' => $this->searchProducts($site, $query, $limit),
                    'posts' => $this->searchPosts($site, $query, $limit),
                    default => $this->searchPages($site, $query, $limit),
                };
            }
        );

        return $this->corsJson([
            'site_id' => $site->id,
            'query' => $query,
            'mode' => $mode,
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'count' => count($items),
            ],
        ]);
    }

    /**
     * Session-backed storefront customer identity probe for nav account components.
     */
    public function customerMe(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        $response = $this->corsJson($this->publicCustomerMePayload($site, $request->user()));

        return $response
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Session-backed storefront customer login JSON endpoint (site-scoped runtime variant).
     */
    public function customerRegister(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! SystemSetting::get('enable_registration', true)) {
            return $this->corsJson([
                'error' => 'Registration is disabled.',
                'reason' => 'registration_disabled',
            ], 403);
        }

        $minLength = max(6, (int) SystemSetting::get('password_min_length', 8));
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:'.$minLength],
        ]);

        $defaultPlanId = Plan::resolveDefaultPlan()?->id;

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => strtolower((string) $validated['email']),
            'password' => Hash::make((string) $validated['password']),
            'locale' => (string) SystemSetting::get('default_locale', config('app.locale', 'ka')),
            'plan_id' => $defaultPlanId,
            'role' => 'user',
            'status' => 'active',
        ]);

        $tenantId = $site->project?->tenant_id;
        if ($tenantId && Schema::hasTable('customers')) {
            $existing = DB::table('customers')
                ->where('tenant_id', $tenantId)
                ->where('project_id', $site->project_id)
                ->where('email', $user->email)
                ->first();

            if ($existing) {
                DB::table('customers')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $user->name,
                        'password_hash' => Hash::make((string) $validated['password']),
                        'status' => 'active',
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('customers')->insert([
                    'tenant_id' => $tenantId,
                    'project_id' => $site->project_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => null,
                    'password_hash' => Hash::make((string) $validated['password']),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $this->corsJson([
            ...$this->publicCustomerMePayload($site, $user),
            'auth' => [
                'method' => 'register',
                'session' => 'web',
            ],
            'registered' => true,
        ])->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Session-backed storefront customer login JSON endpoint (site-scoped runtime variant).
     */
    public function customerLogin(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ], (bool) ($validated['remember'] ?? false))) {
            return $this->corsJson([
                'error' => 'Invalid credentials.',
                'reason' => 'invalid_credentials',
                'errors' => [
                    'email' => ['These credentials do not match our records.'],
                ],
            ], 422);
        }

        $request->session()->regenerate();

        return $this->corsJson([
            ...$this->publicCustomerMePayload($site, $request->user()),
            'auth' => [
                'method' => 'password',
                'session' => 'web',
            ],
        ])->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Session-backed storefront customer logout JSON endpoint (site-scoped runtime variant).
     */
    public function customerLogout(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->corsJson([
            ...$this->publicCustomerMePayload($site, null),
            'logged_out' => true,
        ])->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Session-backed storefront customer profile update JSON endpoint (site-scoped runtime variant).
     */
    public function customerMeUpdate(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        $user = $request->user();
        if (! $user) {
            return $this->corsJson([
                'error' => 'Authentication required.',
                'reason' => 'customer_auth_required',
            ], 401);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        if (array_key_exists('name', $validated)) {
            $user->name = (string) $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $user->email = is_string($validated['email']) ? $validated['email'] : null;
        }
        $user->save();

        return $this->corsJson([
            ...$this->publicCustomerMePayload($site, $user),
            'updated' => true,
        ])->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Public customer OTP request JSON endpoint (site-scoped runtime variant).
     */
    public function customerOtpRequest(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! $this->isCustomerOtpEnabled()) {
            return $this->corsJson([
                'error' => 'OTP login is disabled.',
                'reason' => 'otp_disabled',
            ], 403);
        }

        if (! Schema::hasTable('otp_requests')) {
            return $this->corsJson([
                'error' => 'OTP service is unavailable.',
            ], 404);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:64'],
        ]);

        $tenantId = $site->project?->tenant_id;
        if (! $tenantId) {
            return $this->corsJson([
                'error' => 'Tenant context is missing for this site.',
            ], 409);
        }

        $ttlMinutes = max(1, (int) SystemSetting::get('otp_ttl_minutes', 5));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes($ttlMinutes);

        $otpRequestId = DB::table('otp_requests')->insertGetId([
            'tenant_id' => $tenantId,
            'project_id' => $site->project_id,
            'phone' => $validated['phone'],
            'code_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'status' => 'pending',
            'ip_hash' => $request->ip() ? hash('sha256', (string) $request->ip()) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'site_id' => $site->id,
            'otp_request' => [
                'id' => (int) $otpRequestId,
                'phone' => (string) $validated['phone'],
                'status' => 'pending',
                'expires_at' => $expiresAt->toISOString(),
            ],
        ];

        if (app()->environment('testing')) {
            $payload['otp_request']['debug_code'] = $code;
        }

        return $this->corsJson($payload, 201);
    }

    /**
     * Public customer OTP verify JSON endpoint (site-scoped runtime variant).
     */
    public function customerOtpVerify(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! $this->isCustomerOtpEnabled()) {
            return $this->corsJson([
                'error' => 'OTP login is disabled.',
                'reason' => 'otp_disabled',
            ], 403);
        }

        if (! Schema::hasTable('otp_requests')) {
            return $this->corsJson([
                'error' => 'OTP service is unavailable.',
            ], 404);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        $tenantId = $site->project?->tenant_id;
        if (! $tenantId) {
            return $this->corsJson([
                'error' => 'Tenant context is missing for this site.',
            ], 409);
        }

        $otpRequest = DB::table('otp_requests')
            ->where('tenant_id', $tenantId)
            ->where('project_id', $site->project_id)
            ->where('phone', $validated['phone'])
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        if (! $otpRequest) {
            return $this->corsJson([
                'error' => 'OTP request not found.',
                'reason' => 'otp_request_not_found',
            ], 404);
        }

        $expiresAt = CarbonImmutable::parse((string) $otpRequest->expires_at);
        if ($expiresAt->isPast()) {
            DB::table('otp_requests')->where('id', $otpRequest->id)->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);

            return $this->corsJson([
                'error' => 'OTP code expired.',
                'reason' => 'otp_expired',
            ], 422);
        }

        if (! Hash::check((string) $validated['code'], (string) $otpRequest->code_hash)) {
            DB::table('otp_requests')->where('id', $otpRequest->id)->update([
                'attempts' => (int) $otpRequest->attempts + 1,
                'updated_at' => now(),
            ]);

            return $this->corsJson([
                'error' => 'Invalid OTP code.',
                'reason' => 'otp_invalid_code',
            ], 422);
        }

        DB::table('otp_requests')->where('id', $otpRequest->id)->update([
            'status' => 'verified',
            'updated_at' => now(),
        ]);

        $customer = null;
        if (Schema::hasTable('customers')) {
            $customerRow = DB::table('customers')
                ->where('tenant_id', $tenantId)
                ->where('project_id', $site->project_id)
                ->where('phone', $validated['phone'])
                ->first();

            if (! $customerRow) {
                $customerId = DB::table('customers')->insertGetId([
                    'tenant_id' => $tenantId,
                    'project_id' => $site->project_id,
                    'name' => 'OTP Customer',
                    'email' => null,
                    'phone' => $validated['phone'],
                    'password_hash' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $customerRow = DB::table('customers')->where('id', $customerId)->first();
            }

            if ($customerRow) {
                $customer = [
                    'id' => (int) $customerRow->id,
                    'name' => (string) $customerRow->name,
                    'email' => is_string($customerRow->email) ? $customerRow->email : null,
                    'phone' => is_string($customerRow->phone) ? $customerRow->phone : null,
                    'status' => (string) $customerRow->status,
                ];
            }
        }

        return $this->corsJson([
            'site_id' => $site->id,
            'verified' => true,
            'auth' => [
                'method' => 'otp',
                'session' => 'none',
            ],
            'customer' => $customer,
        ]);
    }

    /**
     * Public social auth bootstrap JSON endpoint for Google (site-scoped runtime variant).
     */
    public function customerGoogleAuth(Request $request, Site $site): JsonResponse
    {
        return $this->customerSocialAuthBootstrap($request, $site, 'google');
    }

    /**
     * Public social auth bootstrap JSON endpoint for Facebook (site-scoped runtime variant).
     */
    public function customerFacebookAuth(Request $request, Site $site): JsonResponse
    {
        return $this->customerSocialAuthBootstrap($request, $site, 'facebook');
    }

    /**
     * Return published page content by slug.
     */
    public function page(Request $request, Site $site, string $slug): JsonResponse
    {
        $allowDraftPreview = $request->boolean('draft');

        try {
            $payload = $this->rememberPublicRead(
                $this->cacheKey('page', $site, $request, [
                    'slug' => $slug,
                    'draft' => $allowDraftPreview ? 1 : 0,
                ]),
                fn (): array => $this->publicSites->page(
                    $site,
                    $slug,
                    is_string($request->query('locale')) ? $request->query('locale') : null,
                    $request->user(),
                    $allowDraftPreview
                )
            );
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    /**
     * Public blog posts list endpoint for blog/content components.
     */
    public function blogPosts(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('blog_posts')) {
            return $this->corsJson([
                'site_id' => $site->id,
                'items' => [],
            ]);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 12);
        $queryText = trim((string) ($validated['q'] ?? ''));
        $categorySlug = trim((string) ($validated['category'] ?? ''));

        $query = BlogPost::query()
            ->where('site_id', $site->id)
            ->where('status', 'published');

        if ($queryText !== '') {
            $query->where(function ($builder) use ($queryText): void {
                $builder
                    ->where('title', 'like', '%'.$queryText.'%')
                    ->orWhere('slug', 'like', '%'.$queryText.'%')
                    ->orWhere('excerpt', 'like', '%'.$queryText.'%')
                    ->orWhere('content', 'like', '%'.$queryText.'%');
            });
        }

        if ($categorySlug !== '' && Schema::hasTable('post_categories') && Schema::hasTable('post_category_relations')) {
            $query->whereIn('id', function ($subQuery) use ($site, $categorySlug): void {
                $subQuery
                    ->from('post_category_relations')
                    ->select('post_category_relations.post_id')
                    ->join('post_categories', 'post_categories.id', '=', 'post_category_relations.category_id')
                    ->where(function ($scope) use ($site): void {
                        $scope->where('post_categories.site_id', $site->id);
                        if (! empty($site->project_id)) {
                            $scope->orWhere(function ($fallback) use ($site): void {
                                $fallback
                                    ->whereNull('post_categories.site_id')
                                    ->where('post_categories.project_id', $site->project_id);
                            });
                        }
                    })
                    ->where('post_categories.slug', $categorySlug);
            });
        }

        $items = (clone $query)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get(['id', 'title', 'slug', 'excerpt', 'cover_media_id', 'published_at', 'updated_at'])
            ->map(function (BlogPost $post): array {
                return [
                    'id' => (int) $post->id,
                    'title' => (string) $post->title,
                    'slug' => (string) $post->slug,
                    'excerpt' => is_string($post->excerpt) ? $post->excerpt : null,
                    'cover_media_id' => $post->cover_media_id ? (int) $post->cover_media_id : null,
                    'published_at' => $post->published_at?->toISOString(),
                    'updated_at' => $post->updated_at?->toISOString(),
                ];
            })
            ->values()
            ->all();

        return $this->corsJson([
            'site_id' => $site->id,
            'items' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'query' => $queryText !== '' ? $queryText : null,
                'category' => $categorySlug !== '' ? $categorySlug : null,
                'count' => count($items),
            ],
        ]);
    }

    /**
     * Public blog post detail endpoint by slug.
     */
    public function blogPost(Request $request, Site $site, string $slug): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('blog_posts')) {
            return $this->corsJson(['error' => 'Blog post not found.'], 404);
        }

        $post = BlogPost::query()
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            return $this->corsJson(['error' => 'Blog post not found.'], 404);
        }

        $categories = [];
        if (Schema::hasTable('post_categories') && Schema::hasTable('post_category_relations')) {
            $categories = DB::table('post_category_relations')
                ->join('post_categories', 'post_categories.id', '=', 'post_category_relations.category_id')
                ->where('post_category_relations.post_id', $post->id)
                ->orderBy('post_categories.name')
                ->get([
                    'post_categories.id',
                    'post_categories.name',
                    'post_categories.slug',
                    'post_categories.parent_id',
                ])
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'slug' => (string) $row->slug,
                    'parent_id' => $row->parent_id !== null ? (int) $row->parent_id : null,
                ])
                ->all();
        }

        return $this->corsJson([
            'site_id' => $site->id,
            'post' => [
                'id' => (int) $post->id,
                'title' => (string) $post->title,
                'slug' => (string) $post->slug,
                'excerpt' => is_string($post->excerpt) ? $post->excerpt : null,
                'content' => (string) ($post->content ?? ''),
                'cover_media_id' => $post->cover_media_id ? (int) $post->cover_media_id : null,
                'published_at' => $post->published_at?->toISOString(),
                'updated_at' => $post->updated_at?->toISOString(),
                'categories' => $categories,
            ],
        ]);
    }

    /**
     * Public blog categories endpoint.
     */
    public function blogCategories(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('post_categories')) {
            return $this->corsJson([
                'site_id' => $site->id,
                'items' => [],
            ]);
        }

        $query = DB::table('post_categories')
            ->where(function ($builder) use ($site): void {
                $this->applyProjectBackfilledSiteScope($builder, $site, 'post_categories');
            })
            ->orderBy('name');

        $countsByCategory = [];
        if (Schema::hasTable('post_category_relations') && Schema::hasTable('blog_posts')) {
            $countsByCategory = DB::table('post_category_relations')
                ->join('blog_posts', 'blog_posts.id', '=', 'post_category_relations.post_id')
                ->where('blog_posts.site_id', $site->id)
                ->where('blog_posts.status', 'published')
                ->selectRaw('post_category_relations.category_id as category_id, COUNT(*) as aggregate_count')
                ->groupBy('post_category_relations.category_id')
                ->pluck('aggregate_count', 'category_id')
                ->map(fn ($count) => (int) $count)
                ->all();
        }

        $items = $query->get([
            'id',
            'name',
            'slug',
            'parent_id',
            'updated_at',
        ])->map(function ($row) use ($countsByCategory): array {
            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'parent_id' => $row->parent_id !== null ? (int) $row->parent_id : null,
                'posts_count' => (int) ($countsByCategory[$row->id] ?? 0),
                'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
            ];
        })->values()->all();

        return $this->corsJson([
            'site_id' => $site->id,
            'items' => $items,
        ]);
    }

    /**
     * Public portfolio items list endpoint.
     */
    public function portfolioItems(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('portfolio_items')) {
            return $this->corsJson([
                'site_id' => $site->id,
                'items' => [],
            ]);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $queryText = trim((string) ($validated['q'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 24);

        $query = DB::table('portfolio_items')
            ->where(function ($builder) use ($site): void {
                $this->applyProjectBackfilledSiteScope($builder, $site, 'portfolio_items');
            })
            ->where('status', 'published');

        if ($queryText !== '') {
            $query->where(function ($builder) use ($queryText): void {
                $builder
                    ->where('title', 'like', '%'.$queryText.'%')
                    ->orWhere('slug', 'like', '%'.$queryText.'%')
                    ->orWhere('excerpt', 'like', '%'.$queryText.'%')
                    ->orWhere('content_html', 'like', '%'.$queryText.'%');
            });
        }

        $items = $query
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get([
                'id',
                'title',
                'slug',
                'excerpt',
                'cover_media_id',
                'updated_at',
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'slug' => (string) $row->slug,
                'excerpt' => is_string($row->excerpt) ? $row->excerpt : null,
                'cover_media_id' => $row->cover_media_id !== null ? (int) $row->cover_media_id : null,
                'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
            ])
            ->values()
            ->all();

        return $this->corsJson([
            'site_id' => $site->id,
            'items' => $items,
            'meta' => [
                'query' => $queryText !== '' ? $queryText : null,
                'count' => count($items),
            ],
        ]);
    }

    /**
     * Public portfolio item detail endpoint by slug.
     */
    public function portfolioItem(Request $request, Site $site, string $slug): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('portfolio_items')) {
            return $this->corsJson(['error' => 'Portfolio item not found.'], 404);
        }

        $item = DB::table('portfolio_items')
            ->where(function ($builder) use ($site): void {
                $this->applyProjectBackfilledSiteScope($builder, $site, 'portfolio_items');
            })
            ->where('status', 'published')
            ->where('slug', $slug)
            ->first();

        if (! $item) {
            return $this->corsJson(['error' => 'Portfolio item not found.'], 404);
        }

        $images = [];
        if (Schema::hasTable('portfolio_images')) {
            $images = DB::table('portfolio_images')
                ->where('portfolio_item_id', $item->id)
                ->orderBy('sort_order')
                ->get(['media_id', 'sort_order'])
                ->map(fn ($row): array => [
                    'media_id' => (int) $row->media_id,
                    'sort_order' => (int) $row->sort_order,
                ])
                ->all();
        }

        return $this->corsJson([
            'site_id' => $site->id,
            'item' => [
                'id' => (int) $item->id,
                'title' => (string) $item->title,
                'slug' => (string) $item->slug,
                'excerpt' => is_string($item->excerpt) ? $item->excerpt : null,
                'content_html' => is_string($item->content_html) ? $item->content_html : null,
                'cover_media_id' => $item->cover_media_id !== null ? (int) $item->cover_media_id : null,
                'images' => $images,
                'updated_at' => $item->updated_at ? (string) $item->updated_at : null,
            ],
        ]);
    }

    /**
     * Public real-estate properties list endpoint.
     */
    public function properties(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('properties')) {
            return $this->corsJson([
                'site_id' => $site->id,
                'items' => [],
            ]);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 24);
        $queryText = trim((string) ($validated['q'] ?? ''));
        $minPrice = $validated['min_price'] ?? null;
        $maxPrice = $validated['max_price'] ?? null;

        $query = DB::table('properties')
            ->where(function ($builder) use ($site): void {
                $this->applyProjectBackfilledSiteScope($builder, $site, 'properties');
            })
            ->whereIn('status', ['published', 'active']);

        if ($queryText !== '') {
            $query->where(function ($builder) use ($queryText): void {
                $builder
                    ->where('title', 'like', '%'.$queryText.'%')
                    ->orWhere('slug', 'like', '%'.$queryText.'%')
                    ->orWhere('location_text', 'like', '%'.$queryText.'%')
                    ->orWhere('description_html', 'like', '%'.$queryText.'%');
            });
        }

        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        $items = $query
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get([
                'id',
                'title',
                'slug',
                'price',
                'currency',
                'location_text',
                'lat',
                'lng',
                'bedrooms',
                'bathrooms',
                'area_m2',
                'updated_at',
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'slug' => (string) $row->slug,
                'price' => $row->price !== null ? (string) $row->price : null,
                'currency' => is_string($row->currency) ? $row->currency : null,
                'location_text' => is_string($row->location_text) ? $row->location_text : null,
                'lat' => $row->lat !== null ? (float) $row->lat : null,
                'lng' => $row->lng !== null ? (float) $row->lng : null,
                'bedrooms' => $row->bedrooms !== null ? (int) $row->bedrooms : null,
                'bathrooms' => $row->bathrooms !== null ? (int) $row->bathrooms : null,
                'area_m2' => $row->area_m2 !== null ? (float) $row->area_m2 : null,
                'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
            ])
            ->values()
            ->all();

        return $this->corsJson([
            'site_id' => $site->id,
            'items' => $items,
            'meta' => [
                'count' => count($items),
                'query' => $queryText !== '' ? $queryText : null,
            ],
        ]);
    }

    /**
     * Public real-estate property detail endpoint by slug.
     */
    public function property(Request $request, Site $site, string $slug): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('properties')) {
            return $this->corsJson(['error' => 'Property not found.'], 404);
        }

        $property = DB::table('properties')
            ->where(function ($builder) use ($site): void {
                $this->applyProjectBackfilledSiteScope($builder, $site, 'properties');
            })
            ->whereIn('status', ['published', 'active'])
            ->where('slug', $slug)
            ->first();

        if (! $property) {
            return $this->corsJson(['error' => 'Property not found.'], 404);
        }

        $images = [];
        if (Schema::hasTable('property_images')) {
            $images = DB::table('property_images')
                ->where('property_id', $property->id)
                ->orderBy('sort_order')
                ->get(['media_id', 'sort_order'])
                ->map(fn ($row): array => [
                    'media_id' => (int) $row->media_id,
                    'sort_order' => (int) $row->sort_order,
                ])
                ->all();
        }

        return $this->corsJson([
            'site_id' => $site->id,
            'property' => [
                'id' => (int) $property->id,
                'title' => (string) $property->title,
                'slug' => (string) $property->slug,
                'price' => (string) $property->price,
                'currency' => (string) $property->currency,
                'location_text' => (string) $property->location_text,
                'lat' => $property->lat !== null ? (float) $property->lat : null,
                'lng' => $property->lng !== null ? (float) $property->lng : null,
                'bedrooms' => $property->bedrooms !== null ? (int) $property->bedrooms : null,
                'bathrooms' => $property->bathrooms !== null ? (int) $property->bathrooms : null,
                'area_m2' => $property->area_m2 !== null ? (float) $property->area_m2 : null,
                'description_html' => is_string($property->description_html) ? $property->description_html : null,
                'images' => $images,
                'updated_at' => $property->updated_at ? (string) $property->updated_at : null,
            ],
        ]);
    }

    /**
     * Public restaurant menu categories endpoint.
     */
    public function restaurantMenu(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('restaurant_menu_categories')) {
            return $this->corsJson([
                'site_id' => $site->id,
                'categories' => [],
            ]);
        }

        $categories = DB::table('restaurant_menu_categories')
            ->where(function ($builder) use ($site): void {
                $this->applyProjectBackfilledSiteScope($builder, $site, 'restaurant_menu_categories');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order', 'updated_at'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'sort_order' => (int) ($row->sort_order ?? 0),
                'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
            ])
            ->values()
            ->all();

        return $this->corsJson([
            'site_id' => $site->id,
            'categories' => $categories,
        ]);
    }

    /**
     * Public restaurant menu items endpoint (optional category filter).
     */
    public function restaurantMenuItems(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('restaurant_menu_items') || ! Schema::hasTable('restaurant_menu_categories')) {
            return $this->corsJson([
                'site_id' => $site->id,
                'items' => [],
            ]);
        }

        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = DB::table('restaurant_menu_items')
            ->join('restaurant_menu_categories', 'restaurant_menu_categories.id', '=', 'restaurant_menu_items.category_id')
            ->where(function ($builder) use ($site): void {
                $this->applyProjectBackfilledSiteScope($builder, $site, 'restaurant_menu_items');
            })
            ->where('restaurant_menu_items.status', 'active');

        if (! empty($validated['category_id'])) {
            $query->where('restaurant_menu_items.category_id', (int) $validated['category_id']);
        }

        if (! empty($validated['category'])) {
            $categoryValue = trim((string) $validated['category']);
            $query->where(function ($builder) use ($categoryValue): void {
                $builder
                    ->where('restaurant_menu_categories.name', $categoryValue)
                    ->orWhereRaw('LOWER(restaurant_menu_categories.name) = ?', [mb_strtolower($categoryValue)]);
            });
        }

        $items = $query
            ->orderBy('restaurant_menu_categories.sort_order')
            ->orderBy('restaurant_menu_items.name')
            ->get([
                'restaurant_menu_items.id',
                'restaurant_menu_items.category_id',
                'restaurant_menu_items.name',
                'restaurant_menu_items.description',
                'restaurant_menu_items.price',
                'restaurant_menu_items.currency',
                'restaurant_menu_items.media_id',
                'restaurant_menu_items.status',
                'restaurant_menu_categories.name as category_name',
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'category_id' => (int) $row->category_id,
                'category_name' => (string) $row->category_name,
                'name' => (string) $row->name,
                'description' => is_string($row->description) ? $row->description : null,
                'price' => $row->price !== null ? (string) $row->price : null,
                'currency' => is_string($row->currency) ? $row->currency : null,
                'media_id' => $row->media_id !== null ? (int) $row->media_id : null,
                'status' => (string) $row->status,
            ])
            ->values()
            ->all();

        return $this->corsJson([
            'site_id' => $site->id,
            'items' => $items,
        ]);
    }

    /**
     * Public restaurant table-reservation submit endpoint.
     */
    public function restaurantReservations(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('table_reservations')) {
            return $this->corsJson([
                'error' => 'Restaurant reservations are unavailable.',
            ], 404);
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'guests' => ['required', 'integer', 'min:1', 'max:50'],
            'starts_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $tenantId = $site->project?->tenant_id;
        if (! $tenantId) {
            return $this->corsJson([
                'error' => 'Tenant context is missing for this site.',
            ], 409);
        }

        $reservationId = DB::table('table_reservations')->insertGetId([
            'tenant_id' => $tenantId,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'customer_name' => $validated['customer_name'],
            'phone' => $validated['phone'],
            'guests' => (int) $validated['guests'],
            'starts_at' => CarbonImmutable::parse($validated['starts_at'])->toDateTimeString(),
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->corsJson([
            'site_id' => $site->id,
            'reservation' => [
                'id' => (int) $reservationId,
                'status' => 'pending',
                'customer_name' => $validated['customer_name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'guests' => (int) $validated['guests'],
                'starts_at' => CarbonImmutable::parse($validated['starts_at'])->toISOString(),
                'notes' => $validated['notes'] ?? null,
            ],
        ], 201);
    }

    /**
     * Public hotel rooms list endpoint.
     */
    public function rooms(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('rooms')) {
            return $this->corsJson([
                'site_id' => $site->id,
                'rooms' => [],
            ]);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $queryText = trim((string) ($validated['q'] ?? ''));
        $capacity = isset($validated['capacity']) ? (int) $validated['capacity'] : null;
        $limit = (int) ($validated['limit'] ?? 24);

        $query = DB::table('rooms')
            ->where(function ($builder) use ($site): void {
                $this->applyProjectBackfilledSiteScope($builder, $site, 'rooms');
            })
            ->where('status', 'active');

        if ($queryText !== '') {
            $query->where(function ($builder) use ($queryText): void {
                $builder
                    ->where('name', 'like', '%'.$queryText.'%')
                    ->orWhere('room_type', 'like', '%'.$queryText.'%');
            });
        }

        if ($capacity !== null) {
            $query->where('capacity', '>=', $capacity);
        }

        $rooms = $query
            ->orderBy('price_per_night')
            ->limit($limit)
            ->get([
                'id',
                'name',
                'room_type',
                'capacity',
                'price_per_night',
                'currency',
                'status',
                'updated_at',
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'room_type' => (string) $row->room_type,
                'capacity' => (int) $row->capacity,
                'price_per_night' => (string) $row->price_per_night,
                'currency' => (string) $row->currency,
                'status' => (string) $row->status,
                'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
            ])
            ->values()
            ->all();

        return $this->corsJson([
            'site_id' => $site->id,
            'rooms' => $rooms,
        ]);
    }

    /**
     * Public hotel room detail endpoint by numeric id.
     */
    public function roomDetail(Request $request, Site $site, int $id): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('rooms')) {
            return $this->corsJson(['error' => 'Room not found.'], 404);
        }

        $room = DB::table('rooms')
            ->where(function ($builder) use ($site): void {
                $this->applyProjectBackfilledSiteScope($builder, $site, 'rooms');
            })
            ->where('status', 'active')
            ->where('id', $id)
            ->first();

        if (! $room) {
            return $this->corsJson(['error' => 'Room not found.'], 404);
        }

        $images = [];
        if (Schema::hasTable('room_images')) {
            $images = DB::table('room_images')
                ->where('room_id', $room->id)
                ->orderBy('sort_order')
                ->get(['media_id', 'sort_order'])
                ->map(fn ($row): array => [
                    'media_id' => (int) $row->media_id,
                    'sort_order' => (int) $row->sort_order,
                ])
                ->all();
        }

        return $this->corsJson([
            'site_id' => $site->id,
            'room' => [
                'id' => (int) $room->id,
                'name' => (string) $room->name,
                'room_type' => (string) $room->room_type,
                'capacity' => (int) $room->capacity,
                'price_per_night' => (string) $room->price_per_night,
                'currency' => (string) $room->currency,
                'status' => (string) $room->status,
                'images' => $images,
                'updated_at' => $room->updated_at ? (string) $room->updated_at : null,
            ],
        ]);
    }

    /**
     * Public hotel room reservation submit endpoint.
     */
    public function roomReservations(Request $request, Site $site): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! Schema::hasTable('room_reservations') || ! Schema::hasTable('rooms')) {
            return $this->corsJson([
                'error' => 'Room reservations are unavailable.',
            ], 404);
        }

        $validated = $request->validate([
            'room_id' => ['required', 'integer', 'min:1'],
            'checkin_date' => ['required', 'date'],
            'checkout_date' => ['required', 'date'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:64'],
            'guest_email' => ['nullable', 'email', 'max:255'],
        ]);

        $tenantId = $site->project?->tenant_id;
        if (! $tenantId) {
            return $this->corsJson([
                'error' => 'Tenant context is missing for this site.',
            ], 409);
        }

        $room = DB::table('rooms')
            ->where(function ($builder) use ($site): void {
                $this->applyProjectBackfilledSiteScope($builder, $site, 'rooms');
            })
            ->where('status', 'active')
            ->where('id', (int) $validated['room_id'])
            ->first();

        if (! $room) {
            return $this->corsJson(['error' => 'Room not found.'], 404);
        }

        $checkin = CarbonImmutable::parse($validated['checkin_date'])->startOfDay();
        $checkout = CarbonImmutable::parse($validated['checkout_date'])->startOfDay();
        if (! $checkout->greaterThan($checkin)) {
            return $this->corsJson([
                'error' => 'checkout_date must be after checkin_date.',
            ], 422);
        }

        $nights = max(1, $checkin->diffInDays($checkout));
        $total = number_format(((float) $room->price_per_night) * $nights, 2, '.', '');

        $reservationId = DB::table('room_reservations')->insertGetId([
            'tenant_id' => $tenantId,
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'customer_id' => isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            'room_id' => (int) $room->id,
            'checkin_date' => $checkin->toDateString(),
            'checkout_date' => $checkout->toDateString(),
            'status' => 'pending',
            'total_price' => $total,
            'currency' => (string) $room->currency,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->corsJson([
            'site_id' => $site->id,
            'reservation' => [
                'id' => (int) $reservationId,
                'room_id' => (int) $room->id,
                'status' => 'pending',
                'checkin_date' => $checkin->toDateString(),
                'checkout_date' => $checkout->toDateString(),
                'nights' => $nights,
                'total_price' => $total,
                'currency' => (string) $room->currency,
                'guest_name' => $validated['guest_name'] ?? null,
                'guest_phone' => $validated['guest_phone'] ?? null,
                'guest_email' => $validated['guest_email'] ?? null,
            ],
        ], 201);
    }

    /**
     * Serve media asset for a site by stored path.
     */
    public function asset(Request $request, Site $site, string $path): BinaryFileResponse|JsonResponse|\Illuminate\Http\Response
    {
        try {
            $media = $this->publicSites->asset($site, $path, $request->user());
        } catch (CmsDomainException $exception) {
            if ($exception->status() === 404 && str_contains(strtolower($path), 'demo/')) {
                return $this->placeholderSvgResponse();
            }
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->file(
            Storage::disk('public')->path($media->path),
            [
                'Content-Type' => $media->mime,
                'Cache-Control' => 'public, max-age=31536000',
                'Access-Control-Allow-Origin' => '*',
            ]
        );
    }

    private function placeholderSvgResponse(): \Illuminate\Http\Response
    {
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><rect width="120" height="120" fill="#f1f5f9"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#94a3b8" font-size="12" font-family="sans-serif">?</text></svg>';
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * @param  Closure():array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    private function rememberPublicRead(string $key, Closure $resolver): array
    {
        if (app()->environment('testing')) {
            return $resolver();
        }

        return Cache::remember($key, self::PUBLIC_READ_CACHE_TTL_SECONDS, $resolver);
    }

    private function corsJson(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept')
            ->header('Vary', 'Origin');
    }

    private function guardPublicSiteAccess(Request $request, Site $site): ?JsonResponse
    {
        try {
            $this->publicSites->settings(
                $site,
                is_string($request->query('locale')) ? $request->query('locale') : null,
                $request->user()
            );
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return null;
    }

    /**
     * @param  mixed  $user
     * @return array<string, mixed>
     */
    private function publicCustomerMePayload(Site $site, mixed $user): array
    {
        $authenticated = $user !== null;

        return [
            'site_id' => $site->id,
            'authenticated' => $authenticated,
            'customer' => $authenticated ? [
                'id' => (int) $user->id,
                'name' => (string) ($user->name ?? ''),
                'email' => (string) ($user->email ?? ''),
                'email_verified' => method_exists($user, 'hasVerifiedEmail') ? (bool) $user->hasVerifiedEmail() : false,
            ] : null,
            'links' => [
                'login' => '/login',
                'register' => '/register',
                'logout' => '/logout',
                'account' => '/account',
                'orders' => '/orders',
            ],
        ];
    }

    private function isCustomerOtpEnabled(): bool
    {
        foreach (['passwordless_otp_enabled', 'otp_enabled', 'allow_otp'] as $key) {
            if (SystemSetting::get($key, false)) {
                return true;
            }
        }

        return false;
    }

    private function customerSocialAuthBootstrap(Request $request, Site $site, string $provider): JsonResponse
    {
        if ($guard = $this->guardPublicSiteAccess($request, $site)) {
            return $guard;
        }

        if (! in_array($provider, ['google', 'facebook'], true)) {
            return $this->corsJson([
                'error' => 'Unsupported auth provider.',
            ], 404);
        }

        if (! SystemSetting::get("{$provider}_login_enabled", false)) {
            return $this->corsJson([
                'error' => ucfirst($provider).' login is disabled.',
                'reason' => 'social_provider_disabled',
                'provider' => $provider,
            ], 403);
        }

        $clientId = trim((string) SystemSetting::get("{$provider}_client_id", ''));
        $clientSecret = trim((string) SystemSetting::get("{$provider}_client_secret", ''));
        if ($clientId === '' || $clientSecret === '') {
            return $this->corsJson([
                'error' => ucfirst($provider).' login is not configured.',
                'reason' => 'social_provider_not_configured',
                'provider' => $provider,
            ], 409);
        }

        return $this->corsJson([
            'site_id' => $site->id,
            'provider' => $provider,
            'enabled' => true,
            'mode' => 'redirect',
            'redirect_url' => '/auth/'.$provider,
            'callback_url' => '/auth/'.$provider.'/callback',
        ]);
    }

    private function applyProjectBackfilledSiteScope($builder, Site $site, ?string $table = null): void
    {
        $siteColumn = $table ? "{$table}.site_id" : 'site_id';
        $projectColumn = $table ? "{$table}.project_id" : 'project_id';

        $builder->where(function ($scope) use ($site, $siteColumn, $projectColumn): void {
            $scope->where($siteColumn, $site->id);

            if (! empty($site->project_id)) {
                $scope->orWhere(function ($fallback) use ($site, $siteColumn, $projectColumn): void {
                    $fallback
                        ->whereNull($siteColumn)
                        ->where($projectColumn, $site->project_id);
                });
            }
        });
    }

    /**
     * @param  Site|string  $scope
     * @param  array<string, scalar|null>  $extra
     */
    private function cacheKey(string $scopeName, Site|string $scope, Request $request, array $extra = []): string
    {
        $viewer = $request->user()?->id ?? 'guest';
        $locale = is_string($request->query('locale')) ? strtolower(trim($request->query('locale'))) : 'default';

        if ($scope instanceof Site) {
            $siteVersion = $scope->updated_at?->timestamp ?? 0;
            $projectVersion = $scope->project?->updated_at?->timestamp ?? 0;
            $scopeKey = sprintf('site:%s:%s:%s', $scope->id, $siteVersion, $projectVersion);
        } else {
            $scopeKey = 'domain:'.strtolower(trim($scope));
        }

        return 'public-cms:'.$scopeName.':'.$scopeKey.':'.$locale.':'.$viewer.':'.md5((string) json_encode($extra));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchPages(Site $site, string $query, int $limit): array
    {
        return Page::query()
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('title', 'like', '%'.$query.'%')
                    ->orWhere('slug', 'like', '%'.$query.'%')
                    ->orWhere('seo_title', 'like', '%'.$query.'%')
                    ->orWhere('seo_description', 'like', '%'.$query.'%');
            })
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'seo_description'])
            ->map(function (Page $page): array {
                return [
                    'id' => (int) $page->id,
                    'type' => 'page',
                    'title' => (string) $page->title,
                    'slug' => (string) $page->slug,
                    'url' => $page->slug === 'home' ? '/' : '/'.$page->slug,
                    'excerpt' => is_string($page->seo_description) ? $page->seo_description : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchPosts(Site $site, string $query, int $limit): array
    {
        if (! Schema::hasTable('blog_posts')) {
            return [];
        }

        return BlogPost::query()
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('title', 'like', '%'.$query.'%')
                    ->orWhere('slug', 'like', '%'.$query.'%')
                    ->orWhere('excerpt', 'like', '%'.$query.'%')
                    ->orWhere('content', 'like', '%'.$query.'%');
            })
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'excerpt'])
            ->map(function (BlogPost $post): array {
                return [
                    'id' => (int) $post->id,
                    'type' => 'post',
                    'title' => (string) $post->title,
                    'slug' => (string) $post->slug,
                    'url' => '/posts/'.$post->slug,
                    'excerpt' => is_string($post->excerpt) ? $post->excerpt : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchProducts(Site $site, string $query, int $limit): array
    {
        if (! Schema::hasTable('ecommerce_products')) {
            return [];
        }

        return EcommerceProduct::query()
            ->where('site_id', $site->id)
            ->where(function ($builder): void {
                $builder
                    ->where('status', 'published')
                    ->orWhereNotNull('published_at');
            })
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('name', 'like', '%'.$query.'%')
                    ->orWhere('slug', 'like', '%'.$query.'%')
                    ->orWhere('short_description', 'like', '%'.$query.'%')
                    ->orWhere('description', 'like', '%'.$query.'%');
            })
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'short_description', 'price', 'currency'])
            ->map(function (EcommerceProduct $product): array {
                return [
                    'id' => (int) $product->id,
                    'type' => 'product',
                    'title' => (string) $product->name,
                    'slug' => (string) $product->slug,
                    'url' => '/products/'.$product->slug,
                    'excerpt' => is_string($product->short_description) ? $product->short_description : null,
                    'price' => $product->price !== null ? (string) $product->price : null,
                    'currency' => is_string($product->currency) ? $product->currency : null,
                ];
            })
            ->values()
            ->all();
    }
}
