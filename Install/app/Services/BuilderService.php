<?php

namespace App\Services;

use App\Services\Builder\BuilderProjectContextService;
use App\Models\AiProvider;
use App\Models\Builder;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BuilderService
{
    public function __construct(
        protected BuilderProjectContextService $projectContextService,
    ) {}

    /**
     * Currently active AI provider for this request.
     */
    protected ?AiProvider $aiProvider = null;

    /**
     * Whether the current user is using their own API key.
     */
    protected bool $usingOwnKey = false;

    /**
     * Get AI configuration from the user's plan's AI Provider.
     * This is the primary method to retrieve AI config.
     * Supports user's own API keys if their plan allows it.
     */
    public function getAiConfigForUser(User $user): array
    {
        $this->usingOwnKey = false;

        // Check if user is using their own API key
        if ($user->isUsingOwnAiApiKey()) {
            return $this->getAiConfigFromUserKey($user);
        }

        if ($user->hasAdminBypass()) {
            $this->aiProvider = $this->resolveSystemAiProvider();
        } else {
            $plan = $user->getCurrentPlan();

            if ($plan) {
                $this->aiProvider = $plan->getAiProviderWithFallbacks();
            }
        }

        if (! $this->aiProvider) {
            throw new \Exception('No AI provider configured. Please add an AI provider in admin settings.');
        }

        // Record usage
        $this->aiProvider->recordUsage();

        return $this->aiProvider->toAiConfig();
    }

    protected function resolveSystemAiProvider(): ?AiProvider
    {
        $defaultId = SystemSetting::get('default_ai_provider_id');
        if ($defaultId) {
            $provider = AiProvider::find($defaultId);
            if ($provider && $provider->status === 'active') {
                return $provider;
            }
        }

        $internalProviderId = SystemSetting::get('internal_ai_provider_id');
        if ($internalProviderId) {
            $provider = AiProvider::find($internalProviderId);
            if ($provider && $provider->status === 'active') {
                return $provider;
            }
        }

        return AiProvider::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
    }

    /**
     * Get AI configuration from user's own API key.
     */
    protected function getAiConfigFromUserKey(User $user): array
    {
        $this->usingOwnKey = true;
        $settings = $user->aiSettings;

        if (! $settings) {
            throw new \Exception('User AI settings not configured.');
        }

        $provider = $settings->preferred_provider;
        $apiKey = $settings->getApiKeyFor($provider);
        $model = $settings->preferred_model;

        if (empty($apiKey)) {
            throw new \Exception("No API key configured for {$provider}.");
        }

        // Get default base URL for the provider type
        $baseUrl = AiProvider::DEFAULT_BASE_URLS[$provider] ?? '';

        // If no preferred model, use the first default for the provider type
        if (empty($model)) {
            $model = AiProvider::DEFAULT_MODELS[$provider][0] ?? 'gpt-5.2';
        }

        return [
            'provider' => $provider,
            'agent' => [
                'api_key' => $apiKey,
                'base_url' => $baseUrl,
                'model' => $model,
                'max_tokens' => 8192,
                'provider_type' => $provider,
            ],
            'summarizer' => [
                'api_key' => $apiKey,
                'base_url' => $baseUrl,
                'model' => $model, // Use same model as agent for consistency
                'max_tokens' => 1500, // Default for user's own key
                'provider_type' => $provider,
            ],
            'suggestions' => [
                'api_key' => $apiKey,
                'base_url' => $baseUrl,
                'model' => $model, // Use same model as agent for consistency
                'provider_type' => $provider,
            ],
        ];
    }

    /**
     * Get AI configuration from the currently set provider.
     * Used for subsequent calls after initial setup.
     */
    public function getAiConfig(): array
    {
        if ($this->aiProvider) {
            return $this->aiProvider->toAiConfig();
        }

        // Fallback to system default provider from settings
        $defaultId = SystemSetting::get('default_ai_provider_id');
        if ($defaultId) {
            $this->aiProvider = AiProvider::find($defaultId);
            if ($this->aiProvider && $this->aiProvider->status !== 'active') {
                $this->aiProvider = null;
            }
        }

        if (! $this->aiProvider) {
            throw new \Exception('No AI provider configured.');
        }

        return $this->aiProvider->toAiConfig();
    }

    /**
     * Get Pusher/Reverb configuration for direct streaming to frontend.
     * Returns null if broadcasting is not configured.
     * Both Pusher and Reverb use the same payload key ("pusher") since
     * the Go builder uses the Pusher SDK for both.
     */
    protected function getPusherConfigForBuilder(): ?array
    {
        $settings = SystemSetting::getGroup('integrations');
        $driver = $settings['broadcast_driver'] ?? 'pusher';

        if ($driver === 'reverb') {
            if (empty($settings['reverb_app_id']) ||
                empty($settings['reverb_key']) ||
                empty($settings['reverb_secret']) ||
                empty($settings['reverb_host'])) {
                return null;
            }

            $host = $settings['reverb_host'];
            $port = $settings['reverb_port'] ?? 8080;
            $scheme = $settings['reverb_scheme'] ?? 'http';

            return [
                'app_id' => $settings['reverb_app_id'],
                'key' => $settings['reverb_key'],
                'secret' => $settings['reverb_secret'],
                'host' => $host.':'.$port,
                'scheme' => $scheme,
            ];
        }

        // Pusher (default)
        if (empty($settings['pusher_app_id']) ||
            empty($settings['pusher_key']) ||
            empty($settings['pusher_secret'])) {
            return null;
        }

        return [
            'app_id' => $settings['pusher_app_id'],
            'key' => $settings['pusher_key'],
            'secret' => $settings['pusher_secret'],
            'cluster' => $settings['pusher_cluster'] ?? 'mt1',
        ];
    }

    /**
     * Start a new agent session on a builder.
     *
     * @param  Builder  $builder  The builder server to use
     * @param  Project  $project  The project being built
     * @param  string  $prompt  The user's prompt/goal
     * @param  array  $history  Previous conversation history (deprecated, use historyData)
     * @param  string|null  $templateUrl  Optional template URL
     * @param  string|null  $templateId  Optional template ID from Laravel
     * @param  array|null  $aiConfig  Optional AI config (if null, uses current provider)
     * @param  array|null  $historyData  Optimized history data from getHistoryForBuilderOptimized()
     * @param  array<string, mixed>|null  $retrievalContext  Internal retrieval provenance context
     */
    public function startSession(
        Builder $builder,
        Project $project,
        string $prompt,
        array $history = [],
        ?string $templateUrl = null,
        ?string $templateId = null,
        ?array $aiConfig = null,
        ?array $historyData = null,
        ?array $retrievalContext = null
    ): array {
        // Use optimized history data if provided, otherwise fall back to legacy history
        $historyToSend = $history;
        $isCompacted = false;
        if ($historyData !== null) {
            $historyToSend = $historyData['history'] ?? [];
            $isCompacted = $historyData['is_compacted'] ?? false;
        }

        $payload = $this->projectContextService->buildSessionPayload(
            $builder,
            $project,
            $prompt,
            $historyToSend,
            $isCompacted,
            $aiConfig ?? $this->getAiConfig(),
            $templateUrl,
            $templateId,
            $retrievalContext
        );

        $pusherConfig = $this->getPusherConfigForBuilder();
        if ($pusherConfig !== null) {
            $payload['pusher'] = $pusherConfig;
        }

        $timeout = 30;

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['X-Server-Key' => $builder->server_key])
                ->post("{$builder->full_url}/api/run", $payload);
        } catch (ConnectionException $e) {
            throw new \Exception(
                'Builder service is offline or unreachable. Start it with "composer dev" (or run "bash scripts/start-local-builder.sh").'
            );
        }

        if (! $response->successful()) {
            throw new \Exception('Failed to start session: '.$response->body());
        }

        $builder->update(['last_triggered_at' => now()]);

        return $response->json();
    }

    /**
     * Get session status from builder.
     */
    public function getSessionStatus(Builder $builder, string $sessionId): array
    {
        $response = Http::timeout(10)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->get("{$builder->full_url}/api/status/{$sessionId}");

        if (! $response->successful()) {
            throw new \Exception('Failed to get session status');
        }

        return $response->json();
    }

    /**
     * Send a chat message to continue the session.
     *
     * @param  array  $history  Previous conversation history (deprecated, use historyData)
     * @param  array|null  $historyData  Optimized history data from getHistoryForBuilderOptimized()
     */
    public function sendMessage(Builder $builder, string $sessionId, string $message, array $history = [], ?array $historyData = null): array
    {
        // Use optimized history data if provided, otherwise fall back to legacy history
        $historyToSend = $history;
        $isCompacted = false;
        if ($historyData !== null) {
            $historyToSend = $historyData['history'] ?? [];
            $isCompacted = $historyData['is_compacted'] ?? false;
        }

        $response = Http::timeout(30)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->post("{$builder->full_url}/api/chat/{$sessionId}", [
                'message' => $message,
                'history' => $historyToSend,
                'is_compacted' => $isCompacted,
                'config' => $this->getAiConfig(),
            ]);

        if (! $response->successful()) {
            throw new \Exception('Failed to send message: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Cancel a running session.
     */
    public function cancelSession(Builder $builder, string $sessionId): bool
    {
        $response = Http::timeout(10)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->post("{$builder->full_url}/api/stop/{$sessionId}");

        return $response->successful();
    }

    /**
     * Mark session as complete and decrement builder counter.
     */
    public function completeSession(Builder $builder): void
    {
        // Legacy installations may not have builder session counters.
        // Keep completion idempotent and avoid hard failure in that case.
        if (method_exists($builder, 'decrementSessionCount')) {
            $builder->decrementSessionCount();

            return;
        }

        $builder->update([
            'last_triggered_at' => now(),
        ]);
    }

    /**
     * Fetch build output from builder and store locally.
     */
    public function fetchBuildOutput(Builder $builder, string $workspaceId, Project $project): string
    {
        $response = Http::timeout(120)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->get("{$builder->full_url}/api/build-output-workspace/{$workspaceId}");

        if (! $response->successful()) {
            throw new \Exception('Failed to fetch build output: '.$response->body());
        }

        // Use local storage with builds path
        $disk = 'local';
        $basePath = 'builds';
        $path = "{$basePath}/{$project->id}/{$workspaceId}.zip";

        Storage::disk($disk)->put($path, $response->body());

        return $path;
    }

    /**
     * Get workspace files from builder.
     */
    public function getWorkspaceFiles(Builder $builder, string $workspaceId): array
    {
        $response = Http::timeout(10)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->get("{$builder->full_url}/api/files-workspace/{$workspaceId}");

        if (! $response->successful()) {
            throw new \Exception('Failed to get workspace files');
        }

        return $response->json();
    }

    /**
     * Get a specific file from workspace.
     */
    public function getFile(Builder $builder, string $workspaceId, string $path): array
    {
        $response = Http::timeout(10)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->get("{$builder->full_url}/api/file-workspace/{$workspaceId}", ['path' => $path]);

        if (! $response->successful()) {
            throw new \Exception('Failed to get file');
        }

        return $response->json();
    }

    /**
     * Update a file in workspace.
     */
    public function updateFile(Builder $builder, string $workspaceId, string $path, string $content): bool
    {
        $response = Http::timeout(10)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->put("{$builder->full_url}/api/file-workspace/{$workspaceId}", [
                'path' => $path,
                'content' => $content,
            ]);

        return $response->successful();
    }

    /**
     * Trigger a build on the builder and download the output.
     */
    public function triggerBuild(Builder $builder, string $workspaceId, int|string|null $projectId = null): array
    {
        $response = Http::timeout(300)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->post("{$builder->full_url}/api/build-workspace/{$workspaceId}");

        $buildTriggerUnsupported = $response->status() === 404;
        $responseBody = (string) $response->body();
        $distFolderMissing = str_contains(strtolower($responseBody), 'dist folder not found');

        if (! $response->successful() && ! $buildTriggerUnsupported) {
            // Some lightweight/default workspaces do not produce a dist folder.
            // In that case, mirror workspace files directly into preview to keep
            // preview/build UX functional instead of hard failing.
            if ($distFolderMissing && $projectId && $this->copyWorkspaceToPreview($workspaceId, $projectId)) {
                return [
                    'success' => true,
                    'triggered' => true,
                    'preview_url' => "/preview/{$projectId}",
                    'message' => 'Build fallback applied from workspace files.',
                    'warning' => $responseBody,
                ];
            }

            $result = [
                'success' => false,
                'error' => 'Build failed: '.$responseBody,
                'triggered' => true,
            ];

            if ($projectId && Storage::disk('local')->exists("previews/{$projectId}/index.html")) {
                $result['preview_url'] = "/preview/{$projectId}";
            }

            return $result;
        }

        $result = $buildTriggerUnsupported
            ? [
                'success' => true,
                'triggered' => false,
                'message' => 'Build trigger endpoint is unavailable on this builder version. Attempting to use latest workspace output.',
            ]
            : ($response->json() ?? ['success' => true]);

        // If project ID provided, download and extract build output
        if ($projectId && ($result['success'] ?? false)) {
            try {
                $this->downloadAndExtractBuildOutput($builder, $workspaceId, $projectId);
                $result['preview_url'] = "/preview/{$projectId}";
            } catch (\Exception $e) {
                if ($buildTriggerUnsupported && str_contains($e->getMessage(), 'Build output not found')) {
                    $result['success'] = false;
                    $result['error'] = 'Build output is not available yet for this workspace.';

                    if (Storage::disk('local')->exists("previews/{$projectId}/index.html")) {
                        $result['preview_url'] = "/preview/{$projectId}";
                    }

                    return $result;
                }

                throw $e;
            }
        }

        return $result;
    }

    protected function copyWorkspaceToPreview(string $workspaceId, int|string $projectId): bool
    {
        $workspaceCandidates = [
            storage_path("workspaces/{$workspaceId}"),
            Storage::disk('local')->path("workspaces/{$workspaceId}"),
        ];

        $workspaceRoot = null;
        foreach ($workspaceCandidates as $candidate) {
            if (is_dir($candidate)) {
                $workspaceRoot = $candidate;
                break;
            }
        }

        if (! is_string($workspaceRoot) || $workspaceRoot === '') {
            return false;
        }

        $sourceRoot = is_dir($workspaceRoot.'/dist')
            ? $workspaceRoot.'/dist'
            : $workspaceRoot;

        $previewRelativePath = "previews/{$projectId}";
        $previewRoot = Storage::disk('local')->path($previewRelativePath);

        File::deleteDirectory($previewRoot);
        File::ensureDirectoryExists($previewRoot, 0775, true);
        Storage::disk('local')->deleteDirectory("published/{$projectId}");

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = ltrim(str_replace($sourceRoot, '', $sourcePath), DIRECTORY_SEPARATOR);

            if ($relativePath === '') {
                continue;
            }

            if (str_starts_with($relativePath, 'node_modules'.DIRECTORY_SEPARATOR)
                || str_starts_with($relativePath, '.git'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $targetPath = $previewRoot.DIRECTORY_SEPARATOR.$relativePath;

            if ($item->isDir()) {
                File::ensureDirectoryExists($targetPath, 0775, true);
                continue;
            }

            File::ensureDirectoryExists(dirname($targetPath), 0775, true);
            File::copy($sourcePath, $targetPath);
        }

        if (! is_file($previewRoot.DIRECTORY_SEPARATOR.'index.html')) {
            return false;
        }

        $project = Project::find($projectId);
        if ($project) {
            $this->injectAppConfig($project, $previewRelativePath);
        }

        return true;
    }

    /**
     * Download build output from builder and extract to preview storage.
     */
    protected function downloadAndExtractBuildOutput(Builder $builder, string $workspaceId, int|string $projectId): void
    {
        $response = Http::timeout(60)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->get("{$builder->full_url}/api/build-output-workspace/{$workspaceId}");

        if (! $response->successful()) {
            throw new \Exception('Failed to download build output: '.$response->body());
        }

        // Create preview directory and clear published cache
        $previewPath = "previews/{$projectId}";
        Storage::disk('local')->deleteDirectory($previewPath);
        Storage::disk('local')->makeDirectory($previewPath);
        Storage::disk('local')->deleteDirectory("published/{$projectId}");

        // Extract zip to preview directory
        $zipPath = Storage::disk('local')->path("temp/{$workspaceId}.zip");
        Storage::disk('local')->makeDirectory('temp');
        file_put_contents($zipPath, $response->body());

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) === true) {
            $zip->extractTo(Storage::disk('local')->path($previewPath));
            $zip->close();
        }

        // Inject app config into index.html
        $project = Project::find($projectId);
        if ($project) {
            $this->injectAppConfig($project, $previewPath);
        }

        // Clean up temp file
        @unlink($zipPath);
    }

    /**
     * Get AI suggestions for next steps.
     */
    public function getSuggestions(Builder $builder, string $sessionId): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->get("{$builder->full_url}/api/suggestions/{$sessionId}");

        if (! $response->successful()) {
            return ['suggestions' => []];
        }

        return $response->json();
    }

    /**
     * Build project capabilities payload for the Go builder agent.
     * This tells the agent what dynamic features are available for the project.
     */
    /**
     * Inject meta tags and __APP_CONFIG__ into the built index.html.
     * This provides SEO meta tags and runtime configuration for Firebase, storage API, etc.
     */
    protected function injectAppConfig(Project $project, string $previewDir): void
    {
        $indexPath = Storage::disk('local')->path("{$previewDir}/index.html");

        if (! file_exists($indexPath)) {
            return;
        }

        $html = file_get_contents($indexPath);

        // 1. Replace title tag
        $title = htmlspecialchars(
            $project->published_title ?? $project->name ?? 'Webby Project',
            ENT_QUOTES,
            'UTF-8'
        );
        $html = preg_replace('/<title>.*?<\/title>/i', "<title>{$title}</title>", $html);

        $metaTags = $this->projectContextService->buildMetaTags($project);
        $config = $this->projectContextService->buildRuntimeAppConfig($project);

        $script = sprintf(
            '<script>window.__APP_CONFIG__ = %s;</script>',
            json_encode(
                $config,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            )
        );

        $cmsRuntimeScript = sprintf(
            '<script id="webby-cms-runtime">%s</script>',
            $this->buildCmsRuntimeScript()
        );
        $ecommerceRuntimeScript = sprintf(
            '<script id="webby-ecommerce-runtime">%s</script>',
            $this->buildEcommerceRuntimeScript()
        );
        $bookingRuntimeScript = sprintf(
            '<script id="webby-booking-runtime">%s</script>',
            $this->buildBookingRuntimeScript()
        );

        // 4. Inject meta tags + script before </head>
        $injection = $metaTags."\n    ".$script."\n    ".$cmsRuntimeScript."\n    ".$ecommerceRuntimeScript."\n    ".$bookingRuntimeScript;
        $html = str_replace('</head>', $injection."\n</head>", $html);

        // Note: Inspector script is now injected on-the-fly by PreviewController
        // to keep stored files clean for /app and subdomain routes

        file_put_contents($indexPath, $html);
    }

    /**
     * Build runtime ecommerce contract for generated frontend.
     *
     * @return array<string, mixed>
     */
    public function runtimeEcommerceConfig(Project $project, ?Site $site, string $apiBaseUrl): array
    {
        return $this->projectContextService->buildRuntimeEcommerceConfig($project, $site, $apiBaseUrl);
    }

    public function ecommerceRuntimeScript(): string
    {
        return $this->buildEcommerceRuntimeScript();
    }

    protected function buildEcommerceRuntimeScript(): string
    {
        return <<<'JS'
(function () {
    'use strict';

    var appConfig = window.__APP_CONFIG__ || {};
    var ecommerce = appConfig.ecommerce || {};

    if (!ecommerce.enabled) {
        return;
    }

    var cartStorageKey = ecommerce.cart_storage_key || null;
    var cartUpdatedEventName = (ecommerce.events && ecommerce.events.cart_updated) || 'webby:ecommerce:cart-updated';
    var cachedCart = null;
    var customerStateEventName = 'webby:ecommerce:customer-state-updated';
    var customerMePromise = null;
    var customerMeSnapshot = null;

    function asObject(value) {
        return value && typeof value === 'object' ? value : {};
    }

    function toQuery(params) {
        if (!params || typeof params !== 'object') {
            return '';
        }

        var searchParams = new URLSearchParams();
        Object.keys(params).forEach(function (key) {
            var value = params[key];
            if (value === null || value === undefined || value === '') {
                return;
            }

            searchParams.set(key, String(value));
        });

        var encoded = searchParams.toString();

        return encoded ? ('?' + encoded) : '';
    }

    function isDraftPreviewRequest() {
        return typeof document !== 'undefined'
            && document.location
            && typeof document.location.search === 'string'
            && /(?:^|[?&])draft=1(?:&|$)/.test(document.location.search);
    }

    function appendDraftPreviewUrl(url) {
        if (!isDraftPreviewRequest() || typeof url !== 'string' || url === '') {
            return url;
        }

        if (/(?:^|[?&])draft=1(?:&|$)/.test(url)) {
            return url;
        }

        return url + (url.indexOf('?') === -1 ? '?draft=1' : '&draft=1');
    }

    function template(pattern, params) {
        if (typeof pattern !== 'string' || pattern.length === 0) {
            throw new Error('URL pattern is missing');
        }

        return pattern.replace(/\{([a-z0-9_]+)\}/gi, function (_all, key) {
            var value = params[key];
            if (value === null || value === undefined || value === '') {
                throw new Error('Missing URL token: ' + key);
            }

            return encodeURIComponent(String(value));
        });
    }

    function parseResponse(response) {
        return response
            .text()
            .then(function (body) {
                var payload = {};
                if (body && body.trim() !== '') {
                    try {
                        payload = JSON.parse(body);
                    } catch (_error) {
                        payload = { message: body };
                    }
                }

                if (!response.ok) {
                    var message = (payload && (payload.error || payload.message)) || ('HTTP_' + response.status);
                    var error = new Error(message);
                    error.status = response.status;
                    error.payload = payload;
                    throw error;
                }

                return payload;
            });
    }

    function jsonRequest(url, method, body) {
        var init = {
            method: method || 'GET',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        };

        if (body !== undefined) {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(body);
        }

        return fetch(appendDraftPreviewUrl(url), init).then(parseResponse);
    }

    function getStoredCartId() {
        if (!cartStorageKey) {
            return null;
        }

        try {
            var value = window.localStorage.getItem(cartStorageKey);
            return value && value.trim() !== '' ? value : null;
        } catch (_error) {
            return null;
        }
    }

    function setStoredCartId(cartId) {
        if (!cartStorageKey || !cartId) {
            return;
        }

        try {
            window.localStorage.setItem(cartStorageKey, String(cartId));
        } catch (_error) {
            // ignore storage errors
        }
    }

    function clearStoredCartId() {
        if (!cartStorageKey) {
            return;
        }

        try {
            window.localStorage.removeItem(cartStorageKey);
        } catch (_error) {
            // ignore storage errors
        }
    }

    function emitCartUpdated(cart) {
        cachedCart = cart || null;
        window.dispatchEvent(new CustomEvent(cartUpdatedEventName, { detail: cart || null }));
        document.dispatchEvent(new CustomEvent(cartUpdatedEventName, { detail: cart || null }));
    }

    function guestCustomerPayload() {
        return {
            authenticated: false,
            customer: null,
            links: {
                login: '/login',
                register: '/register',
                logout: '/logout',
                account: '/account',
                orders: '/orders',
            },
        };
    }

    function emitCustomerState(payload) {
        var normalized = asObject(payload);
        customerMeSnapshot = normalized;
        window.dispatchEvent(new CustomEvent(customerStateEventName, { detail: normalized }));
        document.dispatchEvent(new CustomEvent(customerStateEventName, { detail: normalized }));
    }

    function resolveCartId(preferredId) {
        if (preferredId) {
            return Promise.resolve(String(preferredId));
        }

        var stored = getStoredCartId();
        if (stored) {
            return Promise.resolve(stored);
        }

        return createCart({}).then(function (payload) {
            return payload && payload.cart ? payload.cart.id : null;
        });
    }

    function normalizeCatalogListQuery(params) {
        var input = asObject(params);
        var query = {};

        var search = input.search;
        if ((search === null || search === undefined || search === '') && input.q !== null && input.q !== undefined && input.q !== '') {
            search = input.q;
        }

        var category = input.category_slug;
        if ((category === null || category === undefined || category === '') && input.category !== null && input.category !== undefined && input.category !== '') {
            category = input.category;
        }

        var limit = input.limit;
        if ((limit === null || limit === undefined || limit === '') && input.per_page !== null && input.per_page !== undefined && input.per_page !== '') {
            limit = input.per_page;
        }

        var offset = input.offset;
        if ((offset === null || offset === undefined || offset === '') && input.page !== null && input.page !== undefined && input.page !== '') {
            var pageForOffset = parseInt(input.page, 10);
            var limitForOffset = parseInt(limit !== null && limit !== undefined && limit !== '' ? limit : 24, 10);
            if (!Number.isFinite(pageForOffset) || pageForOffset < 1) {
                pageForOffset = 1;
            }
            if (!Number.isFinite(limitForOffset) || limitForOffset < 1) {
                limitForOffset = 24;
            }
            offset = (pageForOffset - 1) * limitForOffset;
        }

        if (search !== null && search !== undefined && search !== '') {
            query.search = search;
            query.q = search;
        }
        if (category !== null && category !== undefined && category !== '') {
            query.category_slug = category;
            query.category = category;
        }
        if (limit !== null && limit !== undefined && limit !== '') {
            query.limit = limit;
            query.per_page = limit;
        }
        if (offset !== null && offset !== undefined && offset !== '') {
            query.offset = offset;
            if (query.limit !== undefined) {
                var parsedLimit = parseInt(query.limit, 10);
                var parsedOffset = parseInt(offset, 10);
                if (Number.isFinite(parsedLimit) && parsedLimit > 0 && Number.isFinite(parsedOffset) && parsedOffset >= 0) {
                    query.page = Math.floor(parsedOffset / parsedLimit) + 1;
                }
            }
        } else if (input.page !== null && input.page !== undefined && input.page !== '') {
            query.page = input.page;
        }

        return query;
    }

    function listProducts(params) {
        var baseUrl = ecommerce.products_url;
        if (!baseUrl) {
            return Promise.reject(new Error('Products endpoint is missing'));
        }

        return jsonRequest(baseUrl + toQuery(normalizeCatalogListQuery(params)), 'GET');
    }

    function listCategories(params) {
        var opts = asObject(params);
        var query = asObject(opts.query || opts);

        if (query.limit === undefined && query.per_page === undefined) {
            query.limit = opts.limit || 100;
        }
        if (query.offset === undefined && query.page === undefined) {
            query.offset = 0;
        }

        return listProducts(query).then(function (response) {
            var products = Array.isArray(response && response.products) ? response.products : [];
            var buckets = Object.create(null);
            var categories = [];

            products.forEach(function (product) {
                var row = asObject(product);
                var category = asObject(row.category);
                var slug = typeof category.slug === 'string' ? category.slug.trim() : '';
                if (slug === '') {
                    return;
                }

                if (!buckets[slug]) {
                    buckets[slug] = {
                        id: category.id !== undefined ? category.id : null,
                        name: (typeof category.name === 'string' && category.name.trim() !== '') ? category.name.trim() : slug,
                        slug: slug,
                        count: 0,
                        url: '/shop?category=' + encodeURIComponent(slug),
                    };
                    categories.push(buckets[slug]);
                }

                buckets[slug].count += 1;
            });

            categories.sort(function (a, b) {
                return String(a.name || '').localeCompare(String(b.name || ''));
            });

            return {
                site_id: response && response.site_id ? response.site_id : (ecommerce.site_id || null),
                categories: categories,
                meta: {
                    source: 'products_derived',
                    products_scanned: products.length,
                    partial: !!(response && response.pagination && response.pagination.has_more),
                },
            };
        });
    }

    function getProduct(slug) {
        var url = template(ecommerce.product_url_pattern, { slug: slug });

        return jsonRequest(url, 'GET');
    }

    function createCart(payload) {
        var endpoint = ecommerce.create_cart_url;
        if (!endpoint) {
            return Promise.reject(new Error('Create cart endpoint is missing'));
        }

        return jsonRequest(endpoint, 'POST', asObject(payload)).then(function (response) {
            if (response && response.cart && response.cart.id) {
                setStoredCartId(response.cart.id);
                emitCartUpdated(response.cart);
            }

            return response;
        });
    }

    function getCart(cartId) {
        return resolveCartId(cartId).then(function (resolvedCartId) {
            if (!resolvedCartId) {
                throw new Error('Cart id is missing');
            }

            var url = template(ecommerce.cart_url_pattern, { cart_id: resolvedCartId });
            return jsonRequest(url, 'GET').then(function (response) {
                if (response && response.cart && response.cart.id) {
                    setStoredCartId(response.cart.id);
                    emitCartUpdated(response.cart);
                }

                return response;
            });
        });
    }

    function addCartItem(cartId, payload) {
        return resolveCartId(cartId).then(function (resolvedCartId) {
            if (!resolvedCartId) {
                throw new Error('Cart id is missing');
            }

            var url = template(ecommerce.cart_items_url_pattern, { cart_id: resolvedCartId });
            return jsonRequest(url, 'POST', asObject(payload)).then(function (response) {
                if (response && response.cart && response.cart.id) {
                    setStoredCartId(response.cart.id);
                    emitCartUpdated(response.cart);
                }

                return response;
            });
        });
    }

    function updateCartItem(cartId, itemId, payload) {
        return resolveCartId(cartId).then(function (resolvedCartId) {
            if (!resolvedCartId) {
                throw new Error('Cart id is missing');
            }

            var url = template(ecommerce.cart_item_url_pattern, {
                cart_id: resolvedCartId,
                item_id: itemId,
            });

            return jsonRequest(url, 'PUT', asObject(payload)).then(function (response) {
                if (response && response.cart && response.cart.id) {
                    setStoredCartId(response.cart.id);
                    emitCartUpdated(response.cart);
                }

                return response;
            });
        });
    }

    function removeCartItem(cartId, itemId) {
        return resolveCartId(cartId).then(function (resolvedCartId) {
            if (!resolvedCartId) {
                throw new Error('Cart id is missing');
            }

            var url = template(ecommerce.cart_item_url_pattern, {
                cart_id: resolvedCartId,
                item_id: itemId,
            });

            return jsonRequest(url, 'DELETE').then(function (response) {
                if (response && response.cart && response.cart.id) {
                    setStoredCartId(response.cart.id);
                    emitCartUpdated(response.cart);
                }

                return response;
            });
        });
    }

    function applyCoupon(cartId, payload) {
        return resolveCartId(cartId).then(function (resolvedCartId) {
            if (!resolvedCartId) {
                throw new Error('Cart id is missing');
            }

            var endpoint = template(ecommerce.coupon_url_pattern, { cart_id: resolvedCartId });
            return jsonRequest(endpoint, 'POST', asObject(payload)).then(function (response) {
                if (response && response.cart && response.cart.id) {
                    setStoredCartId(response.cart.id);
                    emitCartUpdated(response.cart);
                }

                return response;
            });
        });
    }

    function removeCoupon(cartId, payload) {
        return resolveCartId(cartId).then(function (resolvedCartId) {
            if (!resolvedCartId) {
                throw new Error('Cart id is missing');
            }

            var endpoint = template(ecommerce.coupon_url_pattern, { cart_id: resolvedCartId });
            var body = payload === undefined ? undefined : asObject(payload);

            return jsonRequest(endpoint, 'DELETE', body).then(function (response) {
                if (response && response.cart && response.cart.id) {
                    setStoredCartId(response.cart.id);
                    emitCartUpdated(response.cart);
                }

                return response;
            });
        });
    }

    function getShippingOptions(cartId, payload) {
        return resolveCartId(cartId).then(function (resolvedCartId) {
            if (!resolvedCartId) {
                throw new Error('Cart id is missing');
            }

            var url = template(ecommerce.shipping_options_url_pattern, { cart_id: resolvedCartId });
            return jsonRequest(url, 'POST', asObject(payload));
        });
    }

    function updateShipping(cartId, payload) {
        return resolveCartId(cartId).then(function (resolvedCartId) {
            if (!resolvedCartId) {
                throw new Error('Cart id is missing');
            }

            var url = template(ecommerce.shipping_update_url_pattern, { cart_id: resolvedCartId });
            return jsonRequest(url, 'PUT', asObject(payload)).then(function (response) {
                if (response && response.cart && response.cart.id) {
                    setStoredCartId(response.cart.id);
                    emitCartUpdated(response.cart);
                }

                return response;
            });
        });
    }

    function checkout(cartId, payload) {
        return resolveCartId(cartId).then(function (resolvedCartId) {
            if (!resolvedCartId) {
                throw new Error('Cart id is missing');
            }

            var url = template(ecommerce.checkout_url_pattern, { cart_id: resolvedCartId });
            return jsonRequest(url, 'POST', asObject(payload)).then(function (response) {
                clearStoredCartId();
                emitCartUpdated(null);
                return response;
            });
        });
    }

    function validateCheckout(cartId, payload) {
        return resolveCartId(cartId).then(function (resolvedCartId) {
            if (!resolvedCartId) {
                throw new Error('Cart id is missing');
            }

            var url = template(ecommerce.checkout_validate_url_pattern, { cart_id: resolvedCartId });
            return jsonRequest(url, 'POST', asObject(payload));
        });
    }

    function startPayment(orderId, payload) {
        var url = template(ecommerce.payment_start_url_pattern, { order_id: orderId });

        return jsonRequest(url, 'POST', asObject(payload));
    }

    function getPaymentOptions() {
        var endpoint = ecommerce.payment_options_url;
        if (!endpoint) {
            return Promise.resolve({
                providers: [
                    {
                        slug: 'manual',
                        modes: ['full'],
                        supports_installment: false,
                    },
                ],
            });
        }

        return jsonRequest(endpoint, 'GET');
    }

    function getOrders(params) {
        var endpoint = ecommerce.customer_orders_url;
        if (!endpoint) {
            return Promise.reject(new Error('Customer orders endpoint is missing'));
        }

        return jsonRequest(endpoint + toQuery(asObject(params)), 'GET');
    }

    function getOrder(orderId) {
        var url = template(ecommerce.customer_order_url_pattern, { order_id: orderId });
        return jsonRequest(url, 'GET');
    }

    function trackShipment(payload) {
        var endpoint = ecommerce.shipment_tracking_url;
        if (!endpoint) {
            throw new Error('Shipment tracking endpoint is not configured');
        }

        var query = asObject(payload);
        return jsonRequest(endpoint + toQuery(query), 'GET');
    }

    function customersMe(forceRefresh) {
        if (!forceRefresh && customerMeSnapshot) {
            return Promise.resolve(customerMeSnapshot);
        }

        if (customerMePromise) {
            return customerMePromise;
        }

        var endpoint = ecommerce.customer_me_url;
        if (!endpoint) {
            var fallback = guestCustomerPayload();
            emitCustomerState(fallback);
            return Promise.resolve(fallback);
        }

        customerMePromise = jsonRequest(endpoint, 'GET')
            .then(function (payload) {
                var normalized = asObject(payload);
                emitCustomerState(normalized);
                return normalized;
            })
            .catch(function (error) {
                if (error && error.status === 401) {
                    var guest = guestCustomerPayload();
                    emitCustomerState(guest);
                    return guest;
                }

                throw error;
            })
            .finally(function () {
                customerMePromise = null;
            });

        return customerMePromise;
    }

    function customerLogin(payload) {
        var endpoint = ecommerce.customer_login_url;
        if (!endpoint) {
            return Promise.reject(new Error('Customer login endpoint is missing'));
        }

        return jsonRequest(endpoint, 'POST', asObject(payload)).then(function (response) {
            emitCustomerState(response);
            return response;
        });
    }

    function customerRegister(payload) {
        var endpoint = ecommerce.customer_register_url;
        if (!endpoint) {
            return Promise.reject(new Error('Customer register endpoint is missing'));
        }

        return jsonRequest(endpoint, 'POST', asObject(payload)).then(function (response) {
            emitCustomerState(response);
            return response;
        });
    }

    function customerLogout(payload) {
        var endpoint = ecommerce.customer_logout_url;
        if (!endpoint) {
            var fallback = guestCustomerPayload();
            emitCustomerState(fallback);
            return Promise.resolve(fallback);
        }

        return jsonRequest(endpoint, 'POST', asObject(payload)).then(function (response) {
            emitCustomerState(response);
            return response;
        });
    }

    function customerMeUpdate(payload) {
        var endpoint = ecommerce.customer_me_update_url;
        if (!endpoint) {
            return Promise.reject(new Error('Customer profile update endpoint is missing'));
        }

        return jsonRequest(endpoint, 'PUT', asObject(payload)).then(function (response) {
            emitCustomerState(response);
            return response;
        });
    }

    function customerOtpRequest(payload) {
        var endpoint = ecommerce.auth_otp_request_url;
        if (!endpoint) {
            return Promise.reject(new Error('OTP request endpoint is missing'));
        }

        return jsonRequest(endpoint, 'POST', asObject(payload));
    }

    function customerOtpVerify(payload) {
        var endpoint = ecommerce.auth_otp_verify_url;
        if (!endpoint) {
            return Promise.reject(new Error('OTP verify endpoint is missing'));
        }

        return jsonRequest(endpoint, 'POST', asObject(payload));
    }

    function customerSocialAuthStart(provider, payload) {
        var normalizedProvider = String(provider || '').toLowerCase();
        var endpoint = null;

        if (normalizedProvider === 'google') {
            endpoint = ecommerce.auth_google_url;
        } else if (normalizedProvider === 'facebook') {
            endpoint = ecommerce.auth_facebook_url;
        }

        if (!endpoint) {
            return Promise.reject(new Error('Social auth endpoint is missing for provider: ' + normalizedProvider));
        }

        return jsonRequest(endpoint, 'POST', asObject(payload));
    }

    function currencyAmount(value, currency) {
        var numeric = Number.parseFloat(String(value || 0));
        if (!Number.isFinite(numeric)) {
            numeric = 0;
        }

        var normalizedCurrency = String(currency || 'GEL').toUpperCase();
        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: normalizedCurrency,
                maximumFractionDigits: 2,
            }).format(numeric);
        } catch (_error) {
            return numeric.toFixed(2) + ' ' + normalizedCurrency;
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function resolveProductSlugForWidget(container, options) {
        var opts = asObject(options);
        var candidates = [
            container && container.getAttribute ? container.getAttribute('data-product-slug') : null,
            container && container.getAttribute ? container.getAttribute('data-slug') : null,
            opts.productSlug,
            opts.slug,
        ];

        for (var i = 0; i < candidates.length; i += 1) {
            if (typeof candidates[i] === 'string' && candidates[i].trim() !== '') {
                return candidates[i].trim();
            }
        }

        try {
            var path = String((window.location && window.location.pathname) || '');
            var parts = path.split('/').filter(function (part) {
                return part && part.trim() !== '';
            });
            if (parts.length > 0) {
                return parts[parts.length - 1];
            }
        } catch (_error) {
            // ignore path parsing errors
        }

        return null;
    }

    function resolveOrderIdForWidget(container, options) {
        var opts = asObject(options);
        var candidates = [
            container && container.getAttribute ? container.getAttribute('data-order-id') : null,
            opts.orderId,
            opts.id,
        ];

        for (var i = 0; i < candidates.length; i += 1) {
            var numeric = Number.parseInt(String(candidates[i] || ''), 10);
            if (Number.isFinite(numeric) && numeric > 0) {
                return numeric;
            }
        }

        try {
            var path = String((window.location && window.location.pathname) || '');
            var parts = path.split('/').filter(function (part) {
                return part && part.trim() !== '';
            });
            if (parts.length > 0) {
                var last = Number.parseInt(parts[parts.length - 1], 10);
                if (Number.isFinite(last) && last > 0) {
                    return last;
                }
            }
        } catch (_error) {
            // ignore path parsing errors
        }

        return null;
    }

    function mountProductsWidget(container, options) {
        if (!container) {
            return;
        }

        var opts = asObject(options);
        var query = {
            search: container.getAttribute('data-search') || opts.search || null,
            category_slug: container.getAttribute('data-category') || opts.categorySlug || null,
            limit: container.getAttribute('data-limit') || opts.limit || null,
        };

        var ctaLabel = String(container.getAttribute('data-cta-label') || opts.ctaLabel || 'Add to cart');
        if (!ctaLabel || ctaLabel.trim() === '') {
            ctaLabel = 'Add to cart';
        }

        container.innerHTML = '<div style="padding:12px;font-size:14px;color:#64748b">Loading products...</div>';

        listProducts(query)
            .then(function (response) {
                var products = Array.isArray(response.products) ? response.products : [];
                if (products.length === 0) {
                    container.innerHTML = '<div style="padding:12px;font-size:14px;color:#64748b">No products found.</div>';
                    return;
                }

                var cards = products.map(function (product, index) {
                    var row = asObject(product);
                    var nextRow = asObject(products[(index + 1) % products.length]);
                    var category = asObject(row.category);
                    var productTitle = String(row.name || row.slug || 'Product');
                    var productSlug = typeof row.slug === 'string' ? row.slug : '';
                    var productUrl = productSlug !== '' && typeof ecommerce.product_url_pattern === 'string'
                        ? appendDraftPreviewUrl(template(ecommerce.product_url_pattern, { slug: productSlug }))
                        : String(row.product_url || '#');
                    var categoryLabel = typeof category.name === 'string' && category.name.trim() !== ''
                        ? category.name
                        : (typeof row.sku === 'string' && row.sku.trim() !== '' ? row.sku : 'Collection');
                    var primaryImage = typeof row.primary_image_url === 'string' ? row.primary_image_url : '';
                    var secondaryImageCandidate = typeof nextRow.primary_image_url === 'string' ? nextRow.primary_image_url : '';
                    var secondaryImage = secondaryImageCandidate !== '' ? secondaryImageCandidate : primaryImage;
                    var priceText = currencyAmount(row.price, row.currency || 'GEL');
                    var compareAmount = Number.parseFloat(String(row.compare_at_price || '0'));
                    var priceAmount = Number.parseFloat(String(row.price || '0'));
                    var compareText = Number.isFinite(compareAmount) && compareAmount > 0
                        ? currencyAmount(compareAmount, row.currency || 'GEL')
                        : '';
                    var salePercent = Number.isFinite(compareAmount) && Number.isFinite(priceAmount) && compareAmount > priceAmount && priceAmount > 0
                        ? Math.round(((compareAmount - priceAmount) / compareAmount) * 100)
                        : 0;
                    var summaryText = String(row.short_description || row.sku || '');

                    return ''
                        + '<article data-webu-role="ecom-card">'
                        + '<div data-webu-role="ecom-card-media">'
                        + '<div data-webu-role="ecom-card-badge-stack">'
                        + (index === 0 ? '<span data-webu-role="ecom-card-badge-hot">Hot</span>' : '')
                        + (salePercent > 0
                            ? '<span data-webu-role="ecom-card-badge-sale">' + escapeHtml(String(salePercent)) + '%</span>'
                            : (compareText !== '' ? '<span data-webu-role="ecom-card-badge-sale">Sale</span>' : ''))
                        + '</div>'
                        + '<a data-webu-role="ecom-card-image-link" href="' + escapeHtml(productUrl) + '">'
                        + (primaryImage !== ''
                            ? '<img data-webu-role="ecom-card-image" src="' + escapeHtml(primaryImage) + '" alt="' + escapeHtml(productTitle) + '" />'
                            : '<div data-webu-role="ecom-card-image"></div>')
                        + (secondaryImage !== ''
                            ? '<img data-webu-role="ecom-card-image-secondary" src="' + escapeHtml(secondaryImage) + '" alt="' + escapeHtml(productTitle) + '" />'
                            : '')
                        + '</a>'
                        + '<div data-webu-role="ecom-card-actions" aria-hidden="true">'
                        + '<button type="button" data-webu-role="ecom-card-action" tabindex="-1">Q</button>'
                        + '<button type="button" data-webu-role="ecom-card-action" tabindex="-1">W</button>'
                        + '<button type="button" data-webu-role="ecom-card-action" tabindex="-1">C</button>'
                        + '</div>'
                        + '<div data-webu-role="ecom-card-hover-cta">'
                        + '<button type="button" data-webu-role="ecom-card-cta" data-webby-add-to-cart="' + escapeHtml(String(row.id || '0')) + '"><span>' + escapeHtml(ctaLabel) + '</span></button>'
                        + '</div>'
                        + '</div>'
                        + '<div data-webu-role="ecom-card-content">'
                        + '<div data-webu-role="ecom-card-category">' + escapeHtml(categoryLabel) + '</div>'
                        + '<h3 data-webu-role="ecom-card-title"><a href="' + escapeHtml(productUrl) + '">' + escapeHtml(productTitle) + '</a></h3>'
                        + '<div data-webu-role="ecom-card-price-line">'
                        + '<span data-webu-role="ecom-card-price">' + escapeHtml(priceText) + '</span>'
                        + (compareText !== '' ? '<span data-webu-role="ecom-card-price-old">' + escapeHtml(compareText) + '</span>' : '')
                        + '</div>'
                        + '<p data-webu-role="ecom-card-desc">' + escapeHtml(summaryText) + '</p>'
                        + '</div>'
                        + '</article>';
                });

                container.innerHTML = '<div data-webu-role="ecom-grid">' + cards.join('') + '</div>';

                var buttons = container.querySelectorAll('button[data-webby-add-to-cart]');
                buttons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        var productId = Number.parseInt(button.getAttribute('data-webby-add-to-cart') || '0', 10);
                        if (!Number.isFinite(productId) || productId <= 0) {
                            return;
                        }

                        button.disabled = true;
                        addCartItem(null, {
                            product_id: productId,
                            quantity: 1,
                        }).catch(function (error) {
                            console.warn('[webby-ecommerce-runtime] add to cart failed', error);
                        }).finally(function () {
                            button.disabled = false;
                        });
                    });
                });
            })
            .catch(function (error) {
                container.innerHTML = '<div style="padding:12px;font-size:14px;color:#b91c1c">Failed to load products.</div>';
                console.warn('[webby-ecommerce-runtime] products widget failed', error);
            });
    }

    function setCatalogWidgetState(container, key, state) {
        if (!container) {
            return;
        }

        container.setAttribute(key, state);
    }

    function mountSearchWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-search-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-search-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-search-state', 'idle');

        var opts = asObject(options);
        var bar = container.querySelector('[data-webu-role="ecom-search-bar"]');
        if (!bar) {
            bar = document.createElement('div');
            bar.setAttribute('data-webu-role', 'ecom-search-bar');
            container.appendChild(bar);
        }

        var input = bar.querySelector('[data-webu-role="ecom-search-input"]');
        if (!input) {
            input = document.createElement('input');
            input.setAttribute('data-webu-role', 'ecom-search-input');
            input.setAttribute('placeholder', 'Search products...');
            bar.appendChild(input);
        }

        var button = bar.querySelector('[data-webu-role="ecom-search-button"]');
        if (!button) {
            button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('data-webu-role', 'ecom-search-button');
            button.textContent = 'Search';
            bar.appendChild(button);
        }

        var dropdown = container.querySelector('[data-webu-role="ecom-search-dropdown"]');
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.setAttribute('data-webu-role', 'ecom-search-dropdown');
            container.appendChild(dropdown);
        }

        var renderResults = function (response) {
            var products = Array.isArray(response && response.products) ? response.products : [];
            dropdown.innerHTML = '';

            if (products.length === 0) {
                dropdown.innerHTML = '<div data-webu-role="ecom-search-result-empty" style="padding:8px;color:#64748b;font-size:13px;">No products found.</div>';
                setCatalogWidgetState(container, 'data-webby-ecommerce-search-state', 'empty');
                return;
            }

            products.slice(0, 8).forEach(function (product) {
                var row = asObject(product);
                var item = document.createElement('a');
                item.setAttribute('data-webu-role', 'ecom-search-result');
                item.setAttribute('href', typeof row.slug === 'string' && row.slug !== '' && typeof ecommerce.product_url_pattern === 'string'
                    ? appendDraftPreviewUrl(template(ecommerce.product_url_pattern, { slug: row.slug }))
                    : (row.product_url || '#'));
                item.style.display = 'grid';
                item.style.gridTemplateColumns = '1fr auto';
                item.style.gap = '8px';
                item.style.padding = '8px';
                item.style.textDecoration = 'none';
                item.style.borderBottom = '1px solid #e2e8f0';

                var title = document.createElement('span');
                title.setAttribute('data-webu-role', 'ecom-search-result-title');
                title.textContent = String(row.name || row.slug || 'Product');
                title.style.color = '#0f172a';

                var price = document.createElement('span');
                price.setAttribute('data-webu-role', 'ecom-search-result-price');
                price.textContent = currencyAmount(row.price, row.currency || 'GEL');
                price.style.color = '#475569';
                price.style.fontSize = '12px';

                item.appendChild(title);
                item.appendChild(price);
                dropdown.appendChild(item);
            });

            setCatalogWidgetState(container, 'data-webby-ecommerce-search-state', 'ready');
        };

        var runSearch = function () {
            var query = (typeof input.value === 'string' ? input.value.trim() : '');
            if (query === '') {
                dropdown.innerHTML = '';
                setCatalogWidgetState(container, 'data-webby-ecommerce-search-state', 'idle');
                return;
            }

            setCatalogWidgetState(container, 'data-webby-ecommerce-search-state', 'loading');
            dropdown.innerHTML = '<div data-webu-role="ecom-search-result-loading" style="padding:8px;color:#64748b;font-size:13px;">Searching...</div>';

            listProducts({
                q: query,
                category: container.getAttribute('data-category') || opts.category || null,
                per_page: container.getAttribute('data-per-page') || opts.perPage || 8,
                page: 1,
            })
                .then(renderResults)
                .catch(function (error) {
                    dropdown.innerHTML = '<div data-webu-role="ecom-search-result-error" style="padding:8px;color:#b91c1c;font-size:13px;">Search unavailable.</div>';
                    setCatalogWidgetState(container, 'data-webby-ecommerce-search-state', 'error');
                    console.warn('[webby-ecommerce-runtime] search widget failed', error);
                });
        };

        button.addEventListener('click', function () {
            runSearch();
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                runSearch();
            }
        });

        var initialQuery = container.getAttribute('data-q') || container.getAttribute('data-search') || '';
        if (typeof initialQuery === 'string' && initialQuery.trim() !== '') {
            input.value = initialQuery.trim();
            runSearch();
        }
    }

    function mountCategoriesWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-categories-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-categories-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-categories-state', 'loading');

        var opts = asObject(options);
        var list = container.querySelector('[data-webu-role="ecom-category-items"]');
        if (!list) {
            list = document.createElement('div');
            list.setAttribute('data-webu-role', 'ecom-category-items');
            container.appendChild(list);
        }

        list.innerHTML = '<div style="padding:8px;color:#64748b;font-size:13px;">Loading categories...</div>';

        listCategories({
            per_page: container.getAttribute('data-per-page') || opts.perPage || 100,
            page: 1,
        })
            .then(function (response) {
                var categories = Array.isArray(response && response.categories) ? response.categories : [];
                var showCountsAttr = (container.getAttribute('data-show-counts') || '').toLowerCase();
                var showCounts = ! (showCountsAttr === '0' || showCountsAttr === 'false' || showCountsAttr === 'no');

                if (categories.length === 0) {
                    list.innerHTML = '<div style="padding:8px;color:#64748b;font-size:13px;">No categories found.</div>';
                    setCatalogWidgetState(container, 'data-webby-ecommerce-categories-state', 'empty');
                    return;
                }

                list.innerHTML = '';
                list.style.display = 'grid';
                list.style.gap = '8px';
                list.style.gridTemplateColumns = 'repeat(auto-fill,minmax(140px,1fr))';

                categories.slice(0, 24).forEach(function (category) {
                    var row = asObject(category);
                    var link = document.createElement('a');
                    link.setAttribute('data-webu-role', 'ecom-category-item');
                    link.setAttribute('href', typeof row.slug === 'string' && row.slug !== '' ? ('/shop?category=' + encodeURIComponent(row.slug)) : '#');
                    link.style.display = 'flex';
                    link.style.justifyContent = 'space-between';
                    link.style.alignItems = 'center';
                    link.style.gap = '8px';
                    link.style.padding = '8px 10px';
                    link.style.border = '1px solid #e2e8f0';
                    link.style.borderRadius = '999px';
                    link.style.textDecoration = 'none';

                    var label = document.createElement('span');
                    label.setAttribute('data-webu-role', 'ecom-category-label');
                    label.textContent = String(row.name || row.slug || 'Category');
                    label.style.color = '#0f172a';

                    var count = document.createElement('span');
                    count.setAttribute('data-webu-role', 'ecom-category-count');
                    count.textContent = String(Number.isFinite(Number(row.count)) ? Number(row.count) : 0);
                    count.style.display = showCounts ? '' : 'none';
                    count.style.color = '#64748b';
                    count.style.fontSize = '12px';

                    link.appendChild(label);
                    link.appendChild(count);
                    list.appendChild(link);
                });

                setCatalogWidgetState(container, 'data-webby-ecommerce-categories-state', 'ready');
            })
            .catch(function (error) {
                list.innerHTML = '<div style="padding:8px;color:#b91c1c;font-size:13px;">Categories unavailable.</div>';
                setCatalogWidgetState(container, 'data-webby-ecommerce-categories-state', 'error');
                console.warn('[webby-ecommerce-runtime] categories widget failed', error);
            });
    }

    function mountProductGalleryWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-product-gallery-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-product-gallery-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-product-gallery-state', 'loading');

        var slug = resolveProductSlugForWidget(container, options);
        if (!slug) {
            container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Product slug is missing.</div>';
            setCatalogWidgetState(container, 'data-webby-ecommerce-product-gallery-state', 'missing-slug');
            return;
        }

        container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Loading gallery...</div>';

        getProduct(slug)
            .then(function (response) {
                var product = asObject(response && response.product);
                var images = Array.isArray(product.images) ? product.images : [];
                var fallbackImage = product.primary_image_url
                    ? [{ url: product.primary_image_url, alt_text: product.name || 'Product image' }]
                    : [];
                var galleryImages = images.length > 0 ? images : fallbackImage;

                if (galleryImages.length === 0) {
                    container.innerHTML = '<div style="height:220px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:13px;">No images</div>';
                    setCatalogWidgetState(container, 'data-webby-ecommerce-product-gallery-state', 'empty');
                    return;
                }

                var lead = asObject(galleryImages[0]);
                var leadSrc = lead.url || lead.path || '';
                var thumbs = galleryImages.slice(0, 6).map(function (image, index) {
                    var row = asObject(image);
                    var src = row.url || row.path || '';
                    if (!src) {
                        return '';
                    }

                    return ''
                        + '<button type="button" data-webu-role="ecom-gallery-thumb" data-webu-index="' + String(index) + '" style="border:1px solid #e2e8f0;background:#fff;border-radius:8px;padding:2px;cursor:pointer;">'
                        + '<img src="' + escapeHtml(src) + '" alt="' + escapeHtml(row.alt_text || ('Thumb ' + (index + 1))) + '" style="width:52px;height:52px;object-fit:cover;border-radius:6px;display:block;" />'
                        + '</button>';
                }).join('');

                container.innerHTML = ''
                    + '<div data-webu-role="ecom-gallery-stage" style="display:grid;gap:10px;">'
                    + '<div style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;background:#fff;">'
                    + '<img data-webu-role="ecom-gallery-stage-image" src="' + escapeHtml(leadSrc) + '" alt="' + escapeHtml(lead.alt_text || product.name || 'Product image') + '" style="width:100%;height:320px;object-fit:cover;display:block;" />'
                    + '</div>'
                    + '<div data-webu-role="ecom-gallery-thumbs" style="display:flex;flex-wrap:wrap;gap:8px;">' + thumbs + '</div>'
                    + '</div>';

                var stageImage = container.querySelector('[data-webu-role="ecom-gallery-stage-image"]');
                var thumbButtons = container.querySelectorAll('[data-webu-role="ecom-gallery-thumb"]');
                thumbButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        var index = Number.parseInt(button.getAttribute('data-webu-index') || '0', 10);
                        var next = asObject(galleryImages[index] || galleryImages[0] || {});
                        var nextSrc = next.url || next.path || '';
                        if (!stageImage || !nextSrc) {
                            return;
                        }

                        stageImage.setAttribute('src', nextSrc);
                        stageImage.setAttribute('alt', String(next.alt_text || product.name || 'Product image'));
                    });
                });

                setCatalogWidgetState(container, 'data-webby-ecommerce-product-gallery-state', 'ready');
            })
            .catch(function (error) {
                container.innerHTML = '<div style="padding:12px;font-size:13px;color:#b91c1c">Failed to load product gallery.</div>';
                setCatalogWidgetState(container, 'data-webby-ecommerce-product-gallery-state', 'error');
                console.warn('[webby-ecommerce-runtime] product gallery widget failed', error);
            });
    }

    function mountProductDetailWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-product-detail-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-product-detail-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-product-detail-state', 'loading');

        var opts = asObject(options);
        var slug = resolveProductSlugForWidget(container, opts);
        if (!slug) {
            container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Product slug is missing.</div>';
            setCatalogWidgetState(container, 'data-webby-ecommerce-product-detail-state', 'missing-slug');
            return;
        }

        container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Loading product...</div>';

        getProduct(slug)
            .then(function (response) {
                var product = asObject(response && response.product);
                if (!product || !product.id) {
                    throw new Error('Product payload missing');
                }

                var variants = Array.isArray(product.variants) ? product.variants : [];
                var stockText = product.stock_tracking
                    ? ('Stock: ' + String(product.stock_quantity || 0))
                    : 'Stock available';
                var skuText = product.sku ? ('SKU: ' + String(product.sku)) : '';

                var variantOptions = variants.map(function (variant) {
                    var row = asObject(variant);
                    return '<option value="' + escapeHtml(row.id) + '">' + escapeHtml(row.name || row.sku || ('Variant ' + row.id)) + '</option>';
                }).join('');

                container.innerHTML = ''
                    + '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:grid;gap:10px;background:#fff;">'
                    + '<h2 style="margin:0;font-size:20px;line-height:1.25;color:#0f172a;">' + escapeHtml(product.name || 'Product') + '</h2>'
                    + '<div style="display:flex;gap:12px;flex-wrap:wrap;font-size:13px;color:#64748b;"><span>' + escapeHtml(stockText) + '</span><span>' + escapeHtml(skuText) + '</span></div>'
                    + '<div style="font-weight:700;color:#0f172a;font-size:18px;">' + escapeHtml(currencyAmount(product.price, product.currency || 'GEL')) + '</div>'
                    + '<p style="margin:0;font-size:14px;color:#475569;">' + escapeHtml(product.short_description || product.description || '') + '</p>'
                    + (variantOptions !== '' ? '<label style="display:grid;gap:6px;font-size:13px;color:#334155;">Variant<select data-webu-role="ecom-pdp-variant" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;">' + variantOptions + '</select></label>' : '')
                    + '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">'
                    + '<input data-webu-role="ecom-pdp-qty" type="number" min="1" value="' + escapeHtml(String(opts.quantity || 1)) + '" style="width:90px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;" />'
                    + '<button type="button" data-webu-role="ecom-pdp-add" style="border:0;border-radius:8px;background:#0f172a;color:#fff;padding:9px 12px;cursor:pointer;">Add to cart</button>'
                    + '<span data-webu-role="ecom-pdp-status" style="font-size:12px;color:#64748b;"></span>'
                    + '</div>'
                    + '</div>';

                var qtyInput = container.querySelector('[data-webu-role="ecom-pdp-qty"]');
                var variantSelect = container.querySelector('[data-webu-role="ecom-pdp-variant"]');
                var addButton = container.querySelector('[data-webu-role="ecom-pdp-add"]');
                var status = container.querySelector('[data-webu-role="ecom-pdp-status"]');

                if (addButton) {
                    addButton.addEventListener('click', function () {
                        var quantity = Number.parseInt((qtyInput && qtyInput.value) || '1', 10);
                        if (!Number.isFinite(quantity) || quantity < 1) {
                            quantity = 1;
                        }

                        var payload = {
                            product_id: product.id,
                            quantity: quantity,
                        };

                        if (variantSelect && variantSelect.value) {
                            var variantId = Number.parseInt(variantSelect.value, 10);
                            if (Number.isFinite(variantId) && variantId > 0) {
                                payload.variant_id = variantId;
                            }
                        }

                        addButton.disabled = true;
                        if (status) {
                            status.textContent = 'Adding...';
                            status.style.color = '#64748b';
                        }

                        addCartItem(null, payload)
                            .then(function () {
                                if (status) {
                                    status.textContent = 'Added to cart';
                                    status.style.color = '#166534';
                                }
                            })
                            .catch(function (error) {
                                if (status) {
                                    status.textContent = 'Add failed';
                                    status.style.color = '#b91c1c';
                                }
                                console.warn('[webby-ecommerce-runtime] product detail add to cart failed', error);
                            })
                            .finally(function () {
                                addButton.disabled = false;
                            });
                    });
                }

                setCatalogWidgetState(container, 'data-webby-ecommerce-product-detail-state', 'ready');
            })
            .catch(function (error) {
                container.innerHTML = '<div style="padding:12px;font-size:13px;color:#b91c1c">Failed to load product detail.</div>';
                setCatalogWidgetState(container, 'data-webby-ecommerce-product-detail-state', 'error');
                console.warn('[webby-ecommerce-runtime] product detail widget failed', error);
            });
    }

    function mountCouponWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-coupon-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-coupon-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-coupon-state', 'idle');

        var opts = asObject(options);
        var form = container.tagName && String(container.tagName).toLowerCase() === 'form'
            ? container
            : container.querySelector('form');
        if (!form) {
            form = document.createElement('form');
            container.appendChild(form);
        }

        var input = form.querySelector('[data-webu-role="ecom-coupon-input"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'text';
            input.setAttribute('data-webu-role', 'ecom-coupon-input');
            input.placeholder = container.getAttribute('data-placeholder') || opts.placeholder || 'Coupon code';
            input.style.padding = '8px';
            input.style.border = '1px solid #cbd5e1';
            input.style.borderRadius = '8px';
            input.style.minWidth = '180px';
            form.appendChild(input);
        }

        var applyButton = form.querySelector('[data-webu-role="ecom-coupon-apply"]');
        if (!applyButton) {
            applyButton = document.createElement('button');
            applyButton.type = 'submit';
            applyButton.setAttribute('data-webu-role', 'ecom-coupon-apply');
            applyButton.textContent = container.getAttribute('data-apply-label') || opts.applyLabel || 'Apply';
            applyButton.style.border = '0';
            applyButton.style.borderRadius = '8px';
            applyButton.style.background = '#0f172a';
            applyButton.style.color = '#fff';
            applyButton.style.padding = '8px 10px';
            form.appendChild(applyButton);
        }

        var removeButton = container.querySelector('[data-webu-role="ecom-coupon-remove"]');
        if (!removeButton) {
            removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.setAttribute('data-webu-role', 'ecom-coupon-remove');
            removeButton.textContent = container.getAttribute('data-remove-label') || opts.removeLabel || 'Remove';
            removeButton.style.border = '0';
            removeButton.style.background = 'transparent';
            removeButton.style.color = '#b91c1c';
            removeButton.style.padding = '8px 6px';
            removeButton.style.cursor = 'pointer';
            container.appendChild(removeButton);
        }

        var status = container.querySelector('[data-webu-role="ecom-coupon-status"]');
        if (!status) {
            status = document.createElement('div');
            status.setAttribute('data-webu-role', 'ecom-coupon-status');
            status.style.fontSize = '12px';
            status.style.color = '#64748b';
            status.style.marginTop = '8px';
            container.appendChild(status);
        }

        form.style.display = 'flex';
        form.style.flexWrap = 'wrap';
        form.style.gap = '8px';
        form.style.alignItems = 'center';

        var renderCouponState = function (cart) {
            var coupon = asObject(cart && cart.coupon);
            var hasCoupon = !!(coupon && coupon.code);

            if (hasCoupon) {
                status.textContent = 'Applied: ' + String(coupon.code);
                status.style.color = '#166534';
                removeButton.style.display = '';
                if (!input.value && coupon.code) {
                    input.value = String(coupon.code);
                }
                setCatalogWidgetState(container, 'data-webby-ecommerce-coupon-state', 'applied');
                return;
            }

            status.textContent = 'No coupon applied';
            status.style.color = '#64748b';
            removeButton.style.display = 'none';
            setCatalogWidgetState(container, 'data-webby-ecommerce-coupon-state', 'ready');
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var code = typeof input.value === 'string' ? input.value.trim() : '';
            if (code === '') {
                status.textContent = 'Enter coupon code';
                status.style.color = '#b91c1c';
                setCatalogWidgetState(container, 'data-webby-ecommerce-coupon-state', 'error');
                return;
            }

            applyButton.disabled = true;
            removeButton.disabled = true;
            status.textContent = 'Applying...';
            status.style.color = '#64748b';
            setCatalogWidgetState(container, 'data-webby-ecommerce-coupon-state', 'loading');

            applyCoupon(null, { code: code })
                .then(function (response) {
                    renderCouponState(response && response.cart ? response.cart : null);
                })
                .catch(function (error) {
                    status.textContent = 'Failed to apply coupon';
                    status.style.color = '#b91c1c';
                    setCatalogWidgetState(container, 'data-webby-ecommerce-coupon-state', 'error');
                    console.warn('[webby-ecommerce-runtime] coupon apply failed', error);
                })
                .finally(function () {
                    applyButton.disabled = false;
                    removeButton.disabled = false;
                });
        });

        removeButton.addEventListener('click', function () {
            applyButton.disabled = true;
            removeButton.disabled = true;
            status.textContent = 'Removing...';
            status.style.color = '#64748b';
            setCatalogWidgetState(container, 'data-webby-ecommerce-coupon-state', 'loading');

            removeCoupon(null)
                .then(function (response) {
                    input.value = '';
                    renderCouponState(response && response.cart ? response.cart : null);
                })
                .catch(function (error) {
                    status.textContent = 'Failed to remove coupon';
                    status.style.color = '#b91c1c';
                    setCatalogWidgetState(container, 'data-webby-ecommerce-coupon-state', 'error');
                    console.warn('[webby-ecommerce-runtime] coupon remove failed', error);
                })
                .finally(function () {
                    applyButton.disabled = false;
                    removeButton.disabled = false;
                });
        });

        window.addEventListener(cartUpdatedEventName, function (event) {
            renderCouponState(event && event.detail ? event.detail : null);
        });

        renderCouponState(cachedCart);
    }

    function mountCheckoutFormWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-checkout-form-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-checkout-form-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-checkout-form-state', 'idle');

        var opts = asObject(options);
        var defaultEmail = container.getAttribute('data-customer-email') || opts.customerEmail || '';
        var defaultName = container.getAttribute('data-customer-name') || opts.customerName || '';
        var defaultCountry = container.getAttribute('data-country-code') || opts.countryCode || 'GE';
        var defaultCity = container.getAttribute('data-city') || opts.city || '';
        var defaultAddress = container.getAttribute('data-address') || opts.address || '';

        container.innerHTML = ''
            + '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;display:grid;gap:10px;background:#fff;">'
            + '<div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">'
            + '<label style="display:grid;gap:4px;font-size:12px;color:#334155;">Email'
            + '<input data-webu-role="ecom-checkout-email" type="email" value="' + escapeHtml(defaultEmail) + '" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;" />'
            + '</label>'
            + '<label style="display:grid;gap:4px;font-size:12px;color:#334155;">Name'
            + '<input data-webu-role="ecom-checkout-name" type="text" value="' + escapeHtml(defaultName) + '" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;" />'
            + '</label>'
            + '</div>'
            + '<div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));">'
            + '<label style="display:grid;gap:4px;font-size:12px;color:#334155;">Country'
            + '<input data-webu-role="ecom-checkout-country" type="text" value="' + escapeHtml(defaultCountry) + '" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;" />'
            + '</label>'
            + '<label style="display:grid;gap:4px;font-size:12px;color:#334155;">City'
            + '<input data-webu-role="ecom-checkout-city" type="text" value="' + escapeHtml(defaultCity) + '" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;" />'
            + '</label>'
            + '</div>'
            + '<label style="display:grid;gap:4px;font-size:12px;color:#334155;">Address'
            + '<input data-webu-role="ecom-checkout-address" type="text" value="' + escapeHtml(defaultAddress) + '" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;" />'
            + '</label>'
            + '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
            + '<button type="button" data-webu-role="ecom-checkout-validate" style="border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:8px 10px;cursor:pointer;">Validate</button>'
            + '<button type="button" data-webu-role="ecom-checkout-submit" style="border:0;background:#0f172a;color:#fff;border-radius:8px;padding:8px 10px;cursor:pointer;">Place order</button>'
            + '</div>'
            + '<div data-webu-role="ecom-checkout-form-status" style="font-size:12px;color:#64748b;"></div>'
            + '</div>';

        var emailInput = container.querySelector('[data-webu-role="ecom-checkout-email"]');
        var nameInput = container.querySelector('[data-webu-role="ecom-checkout-name"]');
        var countryInput = container.querySelector('[data-webu-role="ecom-checkout-country"]');
        var cityInput = container.querySelector('[data-webu-role="ecom-checkout-city"]');
        var addressInput = container.querySelector('[data-webu-role="ecom-checkout-address"]');
        var validateButton = container.querySelector('[data-webu-role="ecom-checkout-validate"]');
        var submitButton = container.querySelector('[data-webu-role="ecom-checkout-submit"]');
        var statusNode = container.querySelector('[data-webu-role="ecom-checkout-form-status"]');

        var setStatus = function (text, tone) {
            if (!statusNode) {
                return;
            }

            statusNode.textContent = text || '';
            statusNode.style.color = tone === 'error'
                ? '#b91c1c'
                : (tone === 'success' ? '#166534' : '#64748b');
        };

        var payloadFromInputs = function () {
            var countryCode = countryInput && typeof countryInput.value === 'string' ? countryInput.value.trim() : '';
            var city = cityInput && typeof cityInput.value === 'string' ? cityInput.value.trim() : '';
            var address = addressInput && typeof addressInput.value === 'string' ? addressInput.value.trim() : '';

            return {
                customer_email: emailInput && typeof emailInput.value === 'string' ? emailInput.value.trim() : null,
                customer_name: nameInput && typeof nameInput.value === 'string' ? nameInput.value.trim() : null,
                shipping_address_json: {
                    country_code: countryCode || 'GE',
                    city: city,
                    address: address,
                },
            };
        };

        var toggleBusy = function (busy) {
            if (validateButton) {
                validateButton.disabled = !!busy;
            }
            if (submitButton) {
                submitButton.disabled = !!busy;
            }
        };

        var runValidate = function () {
            setCatalogWidgetState(container, 'data-webby-ecommerce-checkout-form-state', 'loading');
            setStatus('Validating checkout...', 'muted');
            toggleBusy(true);

            return validateCheckout(null, payloadFromInputs())
                .then(function (response) {
                    setCatalogWidgetState(container, 'data-webby-ecommerce-checkout-form-state', 'validated');
                    var providers = Array.isArray(response && response.payments && response.payments.providers)
                        ? response.payments.providers.length
                        : 0;
                    setStatus('Validation passed. Payment options: ' + String(providers), 'success');
                    return response;
                })
                .catch(function (error) {
                    setCatalogWidgetState(container, 'data-webby-ecommerce-checkout-form-state', 'error');
                    setStatus((error && error.message) || 'Checkout validation failed.', 'error');
                    throw error;
                })
                .finally(function () {
                    toggleBusy(false);
                });
        };

        var runCheckout = function () {
            setCatalogWidgetState(container, 'data-webby-ecommerce-checkout-form-state', 'loading');
            setStatus('Placing order...', 'muted');
            toggleBusy(true);

            return checkout(null, payloadFromInputs())
                .then(function (response) {
                    var order = asObject(response && response.order);
                    if (order.id) {
                        container.setAttribute('data-last-order-id', String(order.id));
                        container.setAttribute('data-order-id', String(order.id));
                    }
                    setCatalogWidgetState(container, 'data-webby-ecommerce-checkout-form-state', 'submitted');
                    setStatus(order.order_number ? ('Order created: ' + String(order.order_number)) : 'Order created.', 'success');
                    return response;
                })
                .catch(function (error) {
                    setCatalogWidgetState(container, 'data-webby-ecommerce-checkout-form-state', 'error');
                    setStatus((error && error.message) || 'Checkout failed.', 'error');
                    throw error;
                })
                .finally(function () {
                    toggleBusy(false);
                });
        };

        if (validateButton) {
            validateButton.addEventListener('click', function () {
                runValidate().catch(function () {});
            });
        }

        if (submitButton) {
            submitButton.addEventListener('click', function () {
                runCheckout().catch(function () {});
            });
        }
    }

    function mountOrderSummaryWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-order-summary-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-order-summary-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-order-summary-state', 'idle');

        var renderSummary = function (cart) {
            var row = asObject(cart);
            if (!row || !row.id) {
                container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Cart totals unavailable.</div>';
                setCatalogWidgetState(container, 'data-webby-ecommerce-order-summary-state', 'empty');
                return;
            }

            container.innerHTML = ''
                + '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;display:grid;gap:6px;background:#fff;">'
                + '<div style="display:flex;justify-content:space-between;gap:12px;font-size:13px;color:#475569;"><span>Subtotal</span><strong>' + escapeHtml(currencyAmount(row.subtotal, row.currency)) + '</strong></div>'
                + '<div style="display:flex;justify-content:space-between;gap:12px;font-size:13px;color:#475569;"><span>Shipping</span><strong>' + escapeHtml(currencyAmount(row.shipping_total, row.currency)) + '</strong></div>'
                + '<div style="display:flex;justify-content:space-between;gap:12px;font-size:13px;color:#475569;"><span>Tax</span><strong>' + escapeHtml(currencyAmount(row.tax_total, row.currency)) + '</strong></div>'
                + '<div style="display:flex;justify-content:space-between;gap:12px;font-size:13px;color:#475569;"><span>Discount</span><strong>' + escapeHtml(currencyAmount(row.discount_total, row.currency)) + '</strong></div>'
                + '<div style="display:flex;justify-content:space-between;gap:12px;font-size:14px;color:#0f172a;padding-top:4px;border-top:1px solid #e2e8f0;"><span>Total</span><strong>' + escapeHtml(currencyAmount(row.grand_total, row.currency)) + '</strong></div>'
                + '</div>';

            setCatalogWidgetState(container, 'data-webby-ecommerce-order-summary-state', 'ready');
        };

        var loadSummary = function () {
            setCatalogWidgetState(container, 'data-webby-ecommerce-order-summary-state', 'loading');
            var cartId = container.getAttribute('data-cart-id') || null;

            resolveCartId(cartId)
                .then(function (resolvedCartId) {
                    if (!resolvedCartId) {
                        renderSummary(null);
                        return null;
                    }

                    return getCart(resolvedCartId).then(function (response) {
                        renderSummary(response && response.cart ? response.cart : null);
                    });
                })
                .catch(function (error) {
                    container.innerHTML = '<div style="padding:12px;font-size:13px;color:#b91c1c">Failed to load order summary.</div>';
                    setCatalogWidgetState(container, 'data-webby-ecommerce-order-summary-state', 'error');
                    console.warn('[webby-ecommerce-runtime] order summary widget failed', error);
                });
        };

        window.addEventListener(cartUpdatedEventName, function (event) {
            renderSummary(event && event.detail ? event.detail : null);
        });

        loadSummary();
    }

    function mountShippingSelectorWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-shipping-selector-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-shipping-selector-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-shipping-selector-state', 'idle');

        var opts = asObject(options);
        var defaultCountry = container.getAttribute('data-country-code') || opts.countryCode || 'GE';
        var defaultCity = container.getAttribute('data-city') || opts.city || '';
        var defaultAddress = container.getAttribute('data-address') || opts.address || '';

        container.innerHTML = ''
            + '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;display:grid;gap:10px;background:#fff;">'
            + '<div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));">'
            + '<input data-webu-role="ecom-shipping-country" type="text" value="' + escapeHtml(defaultCountry) + '" placeholder="Country" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;" />'
            + '<input data-webu-role="ecom-shipping-city" type="text" value="' + escapeHtml(defaultCity) + '" placeholder="City" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;" />'
            + '</div>'
            + '<input data-webu-role="ecom-shipping-address" type="text" value="' + escapeHtml(defaultAddress) + '" placeholder="Address" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;" />'
            + '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
            + '<button type="button" data-webu-role="ecom-shipping-load" style="border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:8px 10px;cursor:pointer;">Load methods</button>'
            + '<button type="button" data-webu-role="ecom-shipping-apply" style="border:0;background:#0f172a;color:#fff;border-radius:8px;padding:8px 10px;cursor:pointer;">Apply</button>'
            + '</div>'
            + '<select data-webu-role="ecom-shipping-rates" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;"><option value="">No rates loaded</option></select>'
            + '<div data-webu-role="ecom-shipping-status" style="font-size:12px;color:#64748b;"></div>'
            + '</div>';

        var countryInput = container.querySelector('[data-webu-role="ecom-shipping-country"]');
        var cityInput = container.querySelector('[data-webu-role="ecom-shipping-city"]');
        var addressInput = container.querySelector('[data-webu-role="ecom-shipping-address"]');
        var loadButton = container.querySelector('[data-webu-role="ecom-shipping-load"]');
        var applyButton = container.querySelector('[data-webu-role="ecom-shipping-apply"]');
        var rateSelect = container.querySelector('[data-webu-role="ecom-shipping-rates"]');
        var statusNode = container.querySelector('[data-webu-role="ecom-shipping-status"]');
        var availableRates = [];

        var setStatus = function (text, tone) {
            if (!statusNode) {
                return;
            }
            statusNode.textContent = text || '';
            statusNode.style.color = tone === 'error'
                ? '#b91c1c'
                : (tone === 'success' ? '#166534' : '#64748b');
        };

        var shippingAddressPayload = function () {
            return {
                country_code: countryInput && countryInput.value ? String(countryInput.value).trim() : 'GE',
                city: cityInput && cityInput.value ? String(cityInput.value).trim() : '',
                address: addressInput && addressInput.value ? String(addressInput.value).trim() : '',
            };
        };

        var setButtonsBusy = function (busy) {
            if (loadButton) {
                loadButton.disabled = !!busy;
            }
            if (applyButton) {
                applyButton.disabled = !!busy;
            }
        };

        var renderRates = function (providers) {
            availableRates = [];
            if (!rateSelect) {
                return;
            }

            var rows = Array.isArray(providers) ? providers : [];
            rows.forEach(function (providerRow) {
                var provider = asObject(providerRow);
                var rates = Array.isArray(provider.rates) ? provider.rates : [];
                rates.forEach(function (rateRow) {
                    var rate = asObject(rateRow);
                    availableRates.push({
                        provider: provider.provider || provider.slug || '',
                        rate_id: rate.rate_id || '',
                        label: rate.label || rate.rate_id || 'Rate',
                        amount: rate.amount || '0.00',
                        currency: rate.currency || 'GEL',
                    });
                });
            });

            if (availableRates.length === 0) {
                rateSelect.innerHTML = '<option value="">No shipping methods available</option>';
                setCatalogWidgetState(container, 'data-webby-ecommerce-shipping-selector-state', 'empty');
                return;
            }

            rateSelect.innerHTML = availableRates.map(function (row, index) {
                var label = String(row.label) + ' (' + currencyAmount(row.amount, row.currency) + ')';
                return '<option value="' + escapeHtml(String(index)) + '">' + escapeHtml(label) + '</option>';
            }).join('');

            setCatalogWidgetState(container, 'data-webby-ecommerce-shipping-selector-state', 'ready');
        };

        var loadRates = function () {
            setCatalogWidgetState(container, 'data-webby-ecommerce-shipping-selector-state', 'loading');
            setStatus('Loading shipping methods...', 'muted');
            setButtonsBusy(true);

            getShippingOptions(null, {
                shipping_address_json: shippingAddressPayload(),
            })
                .then(function (response) {
                    var shipping = asObject(response && response.shipping);
                    renderRates(shipping.providers);
                    setStatus('Shipping methods loaded.', 'success');
                })
                .catch(function (error) {
                    renderRates([]);
                    setCatalogWidgetState(container, 'data-webby-ecommerce-shipping-selector-state', 'error');
                    setStatus((error && error.message) || 'Failed to load shipping methods.', 'error');
                    console.warn('[webby-ecommerce-runtime] shipping selector widget failed', error);
                })
                .finally(function () {
                    setButtonsBusy(false);
                });
        };

        var applySelectedRate = function () {
            var index = rateSelect ? Number.parseInt(rateSelect.value || '', 10) : NaN;
            var selected = Number.isFinite(index) ? asObject(availableRates[index]) : {};
            if (!selected.provider || !selected.rate_id) {
                setStatus('Select a shipping method first.', 'error');
                setCatalogWidgetState(container, 'data-webby-ecommerce-shipping-selector-state', 'error');
                return;
            }

            setCatalogWidgetState(container, 'data-webby-ecommerce-shipping-selector-state', 'loading');
            setStatus('Applying shipping method...', 'muted');
            setButtonsBusy(true);

            updateShipping(null, {
                shipping_provider: selected.provider,
                shipping_rate_id: selected.rate_id,
                shipping_address_json: shippingAddressPayload(),
            })
                .then(function (response) {
                    setCatalogWidgetState(container, 'data-webby-ecommerce-shipping-selector-state', 'applied');
                    var cart = asObject(response && response.cart);
                    setStatus('Applied. Cart total: ' + currencyAmount(cart.grand_total, cart.currency), 'success');
                })
                .catch(function (error) {
                    setCatalogWidgetState(container, 'data-webby-ecommerce-shipping-selector-state', 'error');
                    setStatus((error && error.message) || 'Failed to apply shipping method.', 'error');
                    console.warn('[webby-ecommerce-runtime] shipping selector apply failed', error);
                })
                .finally(function () {
                    setButtonsBusy(false);
                });
        };

        if (loadButton) {
            loadButton.addEventListener('click', function () {
                loadRates();
            });
        }
        if (applyButton) {
            applyButton.addEventListener('click', function () {
                applySelectedRate();
            });
        }
    }

    function mountPaymentSelectorWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-payment-selector-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-payment-selector-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-payment-selector-state', 'loading');

        var opts = asObject(options);
        var defaultProvider = container.getAttribute('data-provider') || opts.provider || '';
        var defaultMethod = container.getAttribute('data-method') || opts.method || '';

        container.innerHTML = ''
            + '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;display:grid;gap:10px;background:#fff;">'
            + '<select data-webu-role="ecom-payment-provider" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;"><option value=\"\">Loading payment methods...</option></select>'
            + '<select data-webu-role="ecom-payment-mode" style="padding:8px;border:1px solid #cbd5e1;border-radius:8px;"><option value=\"full\">full</option></select>'
            + '<div data-webu-role="ecom-payment-status" style="font-size:12px;color:#64748b;"></div>'
            + '</div>';

        var providerSelect = container.querySelector('[data-webu-role="ecom-payment-provider"]');
        var modeSelect = container.querySelector('[data-webu-role="ecom-payment-mode"]');
        var statusNode = container.querySelector('[data-webu-role="ecom-payment-status"]');
        var providers = [];

        var setStatus = function (text, tone) {
            if (!statusNode) {
                return;
            }
            statusNode.textContent = text || '';
            statusNode.style.color = tone === 'error'
                ? '#b91c1c'
                : (tone === 'success' ? '#166534' : '#64748b');
        };

        var updateModes = function () {
            if (!providerSelect || !modeSelect) {
                return;
            }
            var selectedProvider = providerSelect.value || '';
            var row = providers.filter(function (provider) {
                return String(provider.slug || '') === selectedProvider;
            })[0] || null;
            var modes = Array.isArray(row && row.modes) && row.modes.length > 0 ? row.modes : ['full'];
            modeSelect.innerHTML = modes.map(function (mode) {
                return '<option value="' + escapeHtml(String(mode)) + '">' + escapeHtml(String(mode)) + '</option>';
            }).join('');
            if (defaultMethod && Array.prototype.some.call(modeSelect.options, function (option) { return option.value === defaultMethod; })) {
                modeSelect.value = defaultMethod;
            }
            container.setAttribute('data-selected-provider', selectedProvider);
            container.setAttribute('data-selected-method', modeSelect.value || 'full');
        };

        if (providerSelect) {
            providerSelect.addEventListener('change', function () {
                updateModes();
            });
        }

        if (modeSelect) {
            modeSelect.addEventListener('change', function () {
                container.setAttribute('data-selected-method', modeSelect.value || 'full');
            });
        }

        getPaymentOptions()
            .then(function (response) {
                providers = Array.isArray(response && response.providers) ? response.providers : [];

                if (!providerSelect) {
                    return;
                }

                if (providers.length === 0) {
                    providerSelect.innerHTML = '<option value=\"\">No payment methods</option>';
                    setCatalogWidgetState(container, 'data-webby-ecommerce-payment-selector-state', 'empty');
                    setStatus('No payment methods available.', 'error');
                    return;
                }

                providerSelect.innerHTML = providers.map(function (provider) {
                    var row = asObject(provider);
                    var label = row.name || row.slug || 'Provider';
                    return '<option value="' + escapeHtml(String(row.slug || '')) + '">' + escapeHtml(String(label)) + '</option>';
                }).join('');

                if (defaultProvider && Array.prototype.some.call(providerSelect.options, function (option) { return option.value === defaultProvider; })) {
                    providerSelect.value = defaultProvider;
                }

                updateModes();
                setCatalogWidgetState(container, 'data-webby-ecommerce-payment-selector-state', 'ready');
                setStatus('Payment methods loaded.', 'success');
            })
            .catch(function (error) {
                if (providerSelect) {
                    providerSelect.innerHTML = '<option value=\"\">Unavailable</option>';
                }
                setCatalogWidgetState(container, 'data-webby-ecommerce-payment-selector-state', 'error');
                setStatus((error && error.message) || 'Failed to load payment methods.', 'error');
                console.warn('[webby-ecommerce-runtime] payment selector widget failed', error);
            });
    }

    function mountOrdersListWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-orders-list-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-orders-list-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-orders-list-state', 'loading');

        var opts = asObject(options);
        var perPage = container.getAttribute('data-per-page') || opts.perPage || 10;
        var page = container.getAttribute('data-page') || opts.page || 1;

        container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Loading orders...</div>';

        getOrders({
            per_page: perPage,
            page: page,
        })
            .then(function (response) {
                var orders = Array.isArray(response && response.orders) ? response.orders : [];
                if (orders.length === 0) {
                    container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">No orders found.</div>';
                    setCatalogWidgetState(container, 'data-webby-ecommerce-orders-list-state', 'empty');
                    return;
                }

                var rows = orders.map(function (order) {
                    var row = asObject(order);
                    return ''
                        + '<a data-webu-role="ecom-order-list-item" href="' + escapeHtml(String(row.id || '#')) + '" style="display:grid;grid-template-columns:1fr auto;gap:8px;text-decoration:none;border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#fff;">'
                        + '<div style="display:grid;gap:4px;">'
                        + '<strong style="font-size:13px;color:#0f172a;">' + escapeHtml(String(row.order_number || ('Order #' + (row.id || '')))) + '</strong>'
                        + '<span style="font-size:12px;color:#64748b;">' + escapeHtml(String(row.status || 'unknown')) + '</span>'
                        + '</div>'
                        + '<strong style="font-size:13px;color:#0f172a;">' + escapeHtml(currencyAmount(row.grand_total, row.currency || 'GEL')) + '</strong>'
                        + '</a>';
                }).join('');

                container.innerHTML = '<div style="display:grid;gap:8px;">' + rows + '</div>';
                setCatalogWidgetState(container, 'data-webby-ecommerce-orders-list-state', 'ready');
            })
            .catch(function (error) {
                var isAuthError = error && (error.status === 401 || (error.payload && error.payload.reason === 'customer_auth_required'));
                container.innerHTML = '<div style="padding:12px;font-size:13px;color:' + (isAuthError ? '#64748b' : '#b91c1c') + ';">'
                    + escapeHtml(isAuthError ? 'Login required to view orders.' : 'Failed to load orders.')
                    + '</div>';
                setCatalogWidgetState(container, 'data-webby-ecommerce-orders-list-state', isAuthError ? 'auth-required' : 'error');
                if (!isAuthError) {
                    console.warn('[webby-ecommerce-runtime] orders list widget failed', error);
                }
            });
    }

    function mountOrderDetailWidget(container, options) {
        if (!container || container.getAttribute('data-webby-ecommerce-order-detail-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-order-detail-bound', '1');
        setCatalogWidgetState(container, 'data-webby-ecommerce-order-detail-state', 'loading');

        var orderId = resolveOrderIdForWidget(container, options);
        if (!orderId) {
            container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Order id is missing.</div>';
            setCatalogWidgetState(container, 'data-webby-ecommerce-order-detail-state', 'missing-order-id');
            return;
        }

        container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Loading order...</div>';

        getOrder(orderId)
            .then(function (response) {
                var order = asObject(response && response.order);
                if (!order || !order.id) {
                    throw new Error('Order payload missing');
                }

                var items = Array.isArray(order.items) ? order.items : [];
                var itemsMarkup = items.length === 0
                    ? '<div style="font-size:12px;color:#64748b;">No order items.</div>'
                    : items.map(function (item) {
                        var row = asObject(item);
                        return ''
                            + '<div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9;">'
                            + '<span style="font-size:12px;color:#0f172a;">' + escapeHtml(String(row.name || 'Item')) + ' × ' + escapeHtml(String(row.quantity || 1)) + '</span>'
                            + '<strong style="font-size:12px;color:#0f172a;">' + escapeHtml(currencyAmount(row.line_total, order.currency || 'GEL')) + '</strong>'
                            + '</div>';
                    }).join('');

                container.innerHTML = ''
                    + '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;display:grid;gap:10px;background:#fff;">'
                    + '<div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">'
                    + '<strong style="font-size:14px;color:#0f172a;">' + escapeHtml(String(order.order_number || ('Order #' + order.id))) + '</strong>'
                    + '<span style="font-size:12px;color:#64748b;">' + escapeHtml(String(order.status || 'unknown')) + '</span>'
                    + '</div>'
                    + '<div style="font-size:12px;color:#475569;">Customer: ' + escapeHtml(String(order.customer_email || order.customer_name || '')) + '</div>'
                    + '<div style="display:grid;gap:4px;">' + itemsMarkup + '</div>'
                    + '<div style="display:flex;justify-content:space-between;gap:8px;border-top:1px solid #e2e8f0;padding-top:8px;">'
                    + '<span style="font-size:13px;color:#475569;">Total</span>'
                    + '<strong style="font-size:13px;color:#0f172a;">' + escapeHtml(currencyAmount(order.grand_total, order.currency || 'GEL')) + '</strong>'
                    + '</div>'
                    + '</div>';

                setCatalogWidgetState(container, 'data-webby-ecommerce-order-detail-state', 'ready');
            })
            .catch(function (error) {
                var isAuthError = error && (error.status === 401 || (error.payload && error.payload.reason === 'customer_auth_required'));
                var isNotFound = error && error.status === 404;
                var message = isAuthError
                    ? 'Login required to view order.'
                    : (isNotFound ? 'Order not found.' : 'Failed to load order.');
                container.innerHTML = '<div style="padding:12px;font-size:13px;color:' + (isAuthError || isNotFound ? '#64748b' : '#b91c1c') + ';">'
                    + escapeHtml(message)
                    + '</div>';
                setCatalogWidgetState(container, 'data-webby-ecommerce-order-detail-state', isAuthError ? 'auth-required' : (isNotFound ? 'not-found' : 'error'));
                if (!isAuthError && !isNotFound) {
                    console.warn('[webby-ecommerce-runtime] order detail widget failed', error);
                }
            });
    }

    function renderCartWidget(container) {
        if (!container) {
            return;
        }

        var cartId = getStoredCartId();
        if (!cartId) {
            container.innerHTML = '<div style="padding:12px;font-size:14px;color:#64748b">Cart is empty.</div>';
            return;
        }

        container.innerHTML = '<div style="padding:12px;font-size:14px;color:#64748b">Loading cart...</div>';
        getCart(cartId)
            .then(function (response) {
                var cart = response.cart || null;
                if (!cart || !Array.isArray(cart.items) || cart.items.length === 0) {
                    container.innerHTML = '<div style="padding:12px;font-size:14px;color:#64748b">Cart is empty.</div>';
                    return;
                }

                var itemsMarkup = cart.items.map(function (item) {
                    return ''
                        + '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid #e2e8f0;">'
                        + '<div>'
                        + '<p style="margin:0;font-size:14px;color:#0f172a">' + escapeHtml(item.name) + '</p>'
                        + '<p style="margin:0;font-size:12px;color:#64748b">Qty: ' + escapeHtml(item.quantity) + '</p>'
                        + '</div>'
                        + '<div style="display:flex;align-items:center;gap:8px;">'
                        + '<span style="font-size:13px;color:#0f172a">' + escapeHtml(currencyAmount(item.line_total, cart.currency)) + '</span>'
                        + '<button type="button" data-webby-remove-cart-item="' + escapeHtml(item.id) + '" style="border:0;background:transparent;color:#b91c1c;cursor:pointer;font-size:12px;">Remove</button>'
                        + '</div>'
                        + '</div>';
                }).join('');

                container.innerHTML = ''
                    + '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;">'
                    + '<div>' + itemsMarkup + '</div>'
                    + '<div style="display:flex;justify-content:space-between;padding-top:10px;font-size:13px;color:#475569;"><span>Subtotal</span><strong>' + escapeHtml(currencyAmount(cart.subtotal, cart.currency)) + '</strong></div>'
                    + '<div style="display:flex;justify-content:space-between;padding-top:4px;font-size:13px;color:#475569;"><span>Total</span><strong>' + escapeHtml(currencyAmount(cart.grand_total, cart.currency)) + '</strong></div>'
                    + '</div>';

                var removeButtons = container.querySelectorAll('[data-webby-remove-cart-item]');
                removeButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        var itemId = Number.parseInt(button.getAttribute('data-webby-remove-cart-item') || '0', 10);
                        if (!Number.isFinite(itemId) || itemId <= 0) {
                            return;
                        }

                        button.disabled = true;
                        removeCartItem(cart.id, itemId)
                            .catch(function (error) {
                                console.warn('[webby-ecommerce-runtime] remove item failed', error);
                            })
                            .finally(function () {
                                button.disabled = false;
                            });
                    });
                });
            })
            .catch(function (error) {
                container.innerHTML = '<div style="padding:12px;font-size:14px;color:#b91c1c">Failed to load cart.</div>';
                console.warn('[webby-ecommerce-runtime] cart widget failed', error);
            });
    }

    function authErrorMessage(error, fallback) {
        var payload = asObject(error && error.payload);
        if (typeof payload.error === 'string' && payload.error.trim() !== '') {
            return payload.error.trim();
        }
        if (typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message.trim();
        }

        var errors = asObject(payload.errors);
        var keys = Object.keys(errors);
        if (keys.length > 0) {
            var first = errors[keys[0]];
            if (Array.isArray(first) && first.length > 0 && typeof first[0] === 'string') {
                return first[0];
            }
        }

        if (error && typeof error.message === 'string' && error.message.trim() !== '') {
            return error.message.trim();
        }

        return fallback || 'Request failed.';
    }

    function buildStorePageUrl(slug, extraParams) {
        var current = new URL(window.location.href);
        if (slug && String(slug).trim() !== '') {
            current.searchParams.set('slug', String(slug).trim());
        }

        var extras = asObject(extraParams);
        Object.keys(extras).forEach(function (key) {
            var value = extras[key];
            if (value === null || value === undefined || value === '') {
                return;
            }
            current.searchParams.set(key, String(value));
        });

        return current.pathname + current.search + current.hash;
    }

    function mountAuthWidget(container) {
        if (!container || container.getAttribute('data-webby-ecommerce-auth-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-auth-bound', '1');
        container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Loading account...</div>';

        var renderSignedIn = function (payload) {
            var customer = asObject(payload && payload.customer);
            var displayName = String(customer.name || '').trim() || String(customer.email || '').trim() || 'Customer';

            container.innerHTML = ''
                + '<div style="border:1px solid #e2e8f0;border-radius:14px;padding:14px;background:#fff;display:grid;gap:10px;">'
                + '<div>'
                + '<div style="font-size:12px;color:#64748b;">Signed in</div>'
                + '<div style="font-size:16px;font-weight:700;color:#0f172a;">' + escapeHtml(displayName) + '</div>'
                + '<div style="font-size:13px;color:#475569;">' + escapeHtml(String(customer.email || '')) + '</div>'
                + '</div>'
                + '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
                + '<button type="button" data-webu-role="ecom-auth-go-orders" style="border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:10px;padding:8px 12px;cursor:pointer;">Orders</button>'
                + '<button type="button" data-webu-role="ecom-auth-logout" style="border:0;background:#0f172a;color:#fff;border-radius:10px;padding:8px 12px;cursor:pointer;">Logout</button>'
                + '</div>'
                + '<div data-webu-role="ecom-auth-status" style="font-size:12px;color:#64748b;"></div>'
                + '</div>';

            var ordersButton = container.querySelector('[data-webu-role="ecom-auth-go-orders"]');
            if (ordersButton) {
                ordersButton.addEventListener('click', function () {
                    window.location.href = buildStorePageUrl('orders');
                });
            }

            var logoutButton = container.querySelector('[data-webu-role="ecom-auth-logout"]');
            var statusNode = container.querySelector('[data-webu-role="ecom-auth-status"]');
            if (logoutButton) {
                logoutButton.addEventListener('click', function () {
                    logoutButton.disabled = true;
                    if (statusNode) {
                        statusNode.textContent = 'Signing out...';
                        statusNode.style.color = '#64748b';
                    }
                    customerLogout({})
                        .then(function () {
                            if (statusNode) {
                                statusNode.textContent = 'Signed out.';
                                statusNode.style.color = '#166534';
                            }
                            renderGuest();
                        })
                        .catch(function (error) {
                            if (statusNode) {
                                statusNode.textContent = authErrorMessage(error, 'Logout failed.');
                                statusNode.style.color = '#b91c1c';
                            }
                        })
                        .finally(function () {
                            logoutButton.disabled = false;
                        });
                });
            }
        };

        var renderGuest = function () {
            var registerEnabled = !!ecommerce.customer_register_url;
            container.innerHTML = ''
                + '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">'
                + '<form data-webu-role="ecom-auth-login-form" style="border:1px solid #e2e8f0;border-radius:14px;padding:14px;background:#fff;display:grid;gap:8px;">'
                + '<div style="font-weight:700;color:#0f172a;">Login</div>'
                + '<input data-webu-role="ecom-auth-login-email" type="email" placeholder="Email" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;" required />'
                + '<input data-webu-role="ecom-auth-login-password" type="password" placeholder="Password" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;" required />'
                + '<label style="font-size:12px;color:#64748b;display:flex;gap:6px;align-items:center;"><input data-webu-role="ecom-auth-login-remember" type="checkbox" /> Remember me</label>'
                + '<button type="submit" style="border:0;background:#0f172a;color:#fff;border-radius:10px;padding:10px;cursor:pointer;">Sign in</button>'
                + '</form>'
                + '<form data-webu-role="ecom-auth-register-form" style="border:1px solid #e2e8f0;border-radius:14px;padding:14px;background:#fff;display:grid;gap:8px;' + (registerEnabled ? '' : 'opacity:.55;pointer-events:none;') + '">'
                + '<div style="font-weight:700;color:#0f172a;">Register</div>'
                + '<input data-webu-role="ecom-auth-register-name" type="text" placeholder="Full name" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;" required />'
                + '<input data-webu-role="ecom-auth-register-email" type="email" placeholder="Email" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;" required />'
                + '<input data-webu-role="ecom-auth-register-password" type="password" placeholder="Password" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;" required />'
                + '<input data-webu-role="ecom-auth-register-password-confirmation" type="password" placeholder="Confirm password" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;" required />'
                + '<button type="submit" style="border:1px solid #0f172a;background:#fff;color:#0f172a;border-radius:10px;padding:10px;cursor:pointer;">Create account</button>'
                + '</form>'
                + '</div>'
                + '<div data-webu-role="ecom-auth-status" style="margin-top:10px;font-size:12px;color:#64748b;">'
                + (registerEnabled ? '' : 'Registration is currently disabled.')
                + '</div>';

            var statusNode = container.querySelector('[data-webu-role="ecom-auth-status"]');
            var setStatus = function (text, tone) {
                if (!statusNode) {
                    return;
                }
                statusNode.textContent = text || '';
                statusNode.style.color = tone === 'error'
                    ? '#b91c1c'
                    : (tone === 'success' ? '#166534' : '#64748b');
            };

            var loginForm = container.querySelector('[data-webu-role="ecom-auth-login-form"]');
            if (loginForm) {
                loginForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    var emailInput = container.querySelector('[data-webu-role="ecom-auth-login-email"]');
                    var passwordInput = container.querySelector('[data-webu-role="ecom-auth-login-password"]');
                    var rememberInput = container.querySelector('[data-webu-role="ecom-auth-login-remember"]');
                    var payload = {
                        email: emailInput ? String(emailInput.value || '').trim() : '',
                        password: passwordInput ? String(passwordInput.value || '') : '',
                        remember: !!(rememberInput && rememberInput.checked),
                    };
                    setStatus('Signing in...', 'muted');
                    customerLogin(payload)
                        .then(function (response) {
                            setStatus('Signed in successfully.', 'success');
                            renderSignedIn(response);
                        })
                        .catch(function (error) {
                            setStatus(authErrorMessage(error, 'Login failed.'), 'error');
                        });
                });
            }

            var registerForm = container.querySelector('[data-webu-role="ecom-auth-register-form"]');
            if (registerForm && registerEnabled) {
                registerForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    var nameInput = container.querySelector('[data-webu-role="ecom-auth-register-name"]');
                    var emailInput = container.querySelector('[data-webu-role="ecom-auth-register-email"]');
                    var passwordInput = container.querySelector('[data-webu-role="ecom-auth-register-password"]');
                    var confirmationInput = container.querySelector('[data-webu-role="ecom-auth-register-password-confirmation"]');

                    var payload = {
                        name: nameInput ? String(nameInput.value || '').trim() : '',
                        email: emailInput ? String(emailInput.value || '').trim() : '',
                        password: passwordInput ? String(passwordInput.value || '') : '',
                        password_confirmation: confirmationInput ? String(confirmationInput.value || '') : '',
                    };

                    setStatus('Creating account...', 'muted');
                    customerRegister(payload)
                        .then(function (response) {
                            setStatus('Account created.', 'success');
                            renderSignedIn(response);
                        })
                        .catch(function (error) {
                            setStatus(authErrorMessage(error, 'Registration failed.'), 'error');
                        });
                });
            }
        };

        customersMe()
            .then(function (payload) {
                if (payload && payload.authenticated) {
                    renderSignedIn(payload);
                    return;
                }
                renderGuest();
            })
            .catch(function (error) {
                setRuntimeError(container, error, 'Failed to load auth');
            });
    }

    function mountAccountProfileWidget(container) {
        if (!container || container.getAttribute('data-webby-ecommerce-account-profile-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-account-profile-bound', '1');
        container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Loading profile...</div>';

        var renderGuest = function () {
            container.innerHTML = ''
                + '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;background:#fff;">'
                + '<div style="font-size:13px;color:#64748b;">Please sign in to edit profile.</div>'
                + '<button type="button" data-webu-role="ecom-profile-go-login" style="margin-top:10px;border:0;background:#0f172a;color:#fff;border-radius:10px;padding:8px 12px;cursor:pointer;">Login</button>'
                + '</div>';
            var loginButton = container.querySelector('[data-webu-role="ecom-profile-go-login"]');
            if (loginButton) {
                loginButton.addEventListener('click', function () {
                    window.location.href = buildStorePageUrl('login');
                });
            }
        };

        var renderProfile = function (payload) {
            var customer = asObject(payload && payload.customer);
            container.innerHTML = ''
                + '<form data-webu-role="ecom-profile-form" style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;background:#fff;display:grid;gap:8px;">'
                + '<label style="display:grid;gap:4px;font-size:12px;color:#64748b;">Name'
                + '<input data-webu-role="ecom-profile-name" type="text" value="' + escapeHtml(String(customer.name || '')) + '" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;" />'
                + '</label>'
                + '<label style="display:grid;gap:4px;font-size:12px;color:#64748b;">Email'
                + '<input data-webu-role="ecom-profile-email" type="email" value="' + escapeHtml(String(customer.email || '')) + '" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;" />'
                + '</label>'
                + '<button type="submit" style="border:0;background:#0f172a;color:#fff;border-radius:10px;padding:9px 12px;cursor:pointer;">Save</button>'
                + '<div data-webu-role="ecom-profile-status" style="font-size:12px;color:#64748b;"></div>'
                + '</form>';

            var form = container.querySelector('[data-webu-role="ecom-profile-form"]');
            var statusNode = container.querySelector('[data-webu-role="ecom-profile-status"]');
            if (!form) {
                return;
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var nameInput = container.querySelector('[data-webu-role="ecom-profile-name"]');
                var emailInput = container.querySelector('[data-webu-role="ecom-profile-email"]');
                if (statusNode) {
                    statusNode.textContent = 'Saving...';
                    statusNode.style.color = '#64748b';
                }

                customerMeUpdate({
                    name: nameInput ? String(nameInput.value || '').trim() : '',
                    email: emailInput ? String(emailInput.value || '').trim() : '',
                })
                    .then(function (response) {
                        if (statusNode) {
                            statusNode.textContent = 'Saved.';
                            statusNode.style.color = '#166534';
                        }
                        emitCustomerState(response);
                    })
                    .catch(function (error) {
                        if (statusNode) {
                            statusNode.textContent = authErrorMessage(error, 'Save failed.');
                            statusNode.style.color = '#b91c1c';
                        }
                    });
            });
        };

        customersMe()
            .then(function (payload) {
                if (!payload || !payload.authenticated) {
                    renderGuest();
                    return;
                }
                renderProfile(payload);
            })
            .catch(function (error) {
                setRuntimeError(container, error, 'Failed to load profile');
            });
    }

    function mountAccountSecurityWidget(container) {
        if (!container || container.getAttribute('data-webby-ecommerce-account-security-bound') === '1') {
            return;
        }

        container.setAttribute('data-webby-ecommerce-account-security-bound', '1');
        container.innerHTML = '<div style="padding:12px;font-size:13px;color:#64748b">Loading security...</div>';

        customersMe()
            .then(function (payload) {
                if (!payload || !payload.authenticated) {
                    container.innerHTML = '<div style="padding:12px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;font-size:13px;color:#64748b;">Sign in required.</div>';
                    return;
                }

                container.innerHTML = ''
                    + '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px;background:#fff;display:grid;gap:10px;">'
                    + '<div style="font-size:13px;color:#475569;">Session active for <strong style="color:#0f172a;">' + escapeHtml(String(payload.customer && payload.customer.email ? payload.customer.email : 'customer')) + '</strong></div>'
                    + '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
                    + '<button type="button" data-webu-role="ecom-security-logout" style="border:0;background:#0f172a;color:#fff;border-radius:10px;padding:8px 12px;cursor:pointer;">Logout</button>'
                    + '</div>'
                    + '<div data-webu-role="ecom-security-status" style="font-size:12px;color:#64748b;"></div>'
                    + '</div>';

                var logoutButton = container.querySelector('[data-webu-role="ecom-security-logout"]');
                var statusNode = container.querySelector('[data-webu-role="ecom-security-status"]');
                if (!logoutButton) {
                    return;
                }

                logoutButton.addEventListener('click', function () {
                    logoutButton.disabled = true;
                    if (statusNode) {
                        statusNode.textContent = 'Signing out...';
                        statusNode.style.color = '#64748b';
                    }
                    customerLogout({})
                        .then(function () {
                            if (statusNode) {
                                statusNode.textContent = 'Signed out.';
                                statusNode.style.color = '#166534';
                            }
                            container.innerHTML = '<div style="padding:12px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;font-size:13px;color:#64748b;">Signed out successfully.</div>';
                        })
                        .catch(function (error) {
                            if (statusNode) {
                                statusNode.textContent = authErrorMessage(error, 'Logout failed.');
                                statusNode.style.color = '#b91c1c';
                            }
                        })
                        .finally(function () {
                            logoutButton.disabled = false;
                        });
                });
            })
            .catch(function (error) {
                setRuntimeError(container, error, 'Failed to load security state');
            });
    }

    function mountWidgets() {
        var productsSelector = (ecommerce.widgets && ecommerce.widgets.products_selector) || '[data-webby-ecommerce-products]';
        var searchSelector = (ecommerce.widgets && ecommerce.widgets.search_selector) || '[data-webby-ecommerce-search]';
        var categoriesSelector = (ecommerce.widgets && ecommerce.widgets.categories_selector) || '[data-webby-ecommerce-categories]';
        var productDetailSelector = (ecommerce.widgets && ecommerce.widgets.product_detail_selector) || '[data-webby-ecommerce-product-detail]';
        var productGallerySelector = (ecommerce.widgets && ecommerce.widgets.product_gallery_selector) || '[data-webby-ecommerce-product-gallery]';
        var couponSelector = (ecommerce.widgets && ecommerce.widgets.coupon_selector) || '[data-webby-ecommerce-coupon]';
        var checkoutFormSelector = (ecommerce.widgets && ecommerce.widgets.checkout_form_selector) || '[data-webby-ecommerce-checkout-form]';
        var orderSummarySelector = (ecommerce.widgets && ecommerce.widgets.order_summary_selector) || '[data-webby-ecommerce-order-summary]';
        var shippingSelector = (ecommerce.widgets && ecommerce.widgets.shipping_selector) || '[data-webby-ecommerce-shipping-selector]';
        var paymentSelector = (ecommerce.widgets && ecommerce.widgets.payment_selector) || '[data-webby-ecommerce-payment-selector]';
        var ordersListSelector = (ecommerce.widgets && ecommerce.widgets.orders_list_selector) || '[data-webby-ecommerce-orders-list]';
        var orderDetailSelector = (ecommerce.widgets && ecommerce.widgets.order_detail_selector) || '[data-webby-ecommerce-order-detail]';
        var cartSelector = (ecommerce.widgets && ecommerce.widgets.cart_selector) || '[data-webby-ecommerce-cart]';
        var authSelector = (ecommerce.widgets && ecommerce.widgets.auth_selector) || '[data-webby-ecommerce-auth]';
        var accountProfileSelector = (ecommerce.widgets && ecommerce.widgets.account_profile_selector) || '[data-webby-ecommerce-account-profile]';
        var accountSecuritySelector = (ecommerce.widgets && ecommerce.widgets.account_security_selector) || '[data-webby-ecommerce-account-security]';

        document.querySelectorAll(productsSelector).forEach(function (node) {
            mountProductsWidget(node, {});
        });

        document.querySelectorAll(searchSelector).forEach(function (node) {
            mountSearchWidget(node, {});
        });

        document.querySelectorAll(categoriesSelector).forEach(function (node) {
            mountCategoriesWidget(node, {});
        });

        document.querySelectorAll(productDetailSelector).forEach(function (node) {
            mountProductDetailWidget(node, {});
        });

        document.querySelectorAll(productGallerySelector).forEach(function (node) {
            mountProductGalleryWidget(node, {});
        });

        document.querySelectorAll(couponSelector).forEach(function (node) {
            mountCouponWidget(node, {});
        });

        document.querySelectorAll(checkoutFormSelector).forEach(function (node) {
            mountCheckoutFormWidget(node, {});
        });

        document.querySelectorAll(orderSummarySelector).forEach(function (node) {
            mountOrderSummaryWidget(node, {});
        });

        document.querySelectorAll(shippingSelector).forEach(function (node) {
            mountShippingSelectorWidget(node, {});
        });

        document.querySelectorAll(paymentSelector).forEach(function (node) {
            mountPaymentSelectorWidget(node, {});
        });

        document.querySelectorAll(ordersListSelector).forEach(function (node) {
            mountOrdersListWidget(node, {});
        });

        document.querySelectorAll(orderDetailSelector).forEach(function (node) {
            mountOrderDetailWidget(node, {});
        });

        document.querySelectorAll(cartSelector).forEach(function (node) {
            renderCartWidget(node);
        });

        document.querySelectorAll(authSelector).forEach(function (node) {
            mountAuthWidget(node);
        });

        document.querySelectorAll(accountProfileSelector).forEach(function (node) {
            mountAccountProfileWidget(node);
        });

        document.querySelectorAll(accountSecuritySelector).forEach(function (node) {
            mountAccountSecurityWidget(node);
        });
    }

    window.WebbyEcommerce = {
        getConfig: function () {
            return ecommerce;
        },
        getCartId: function () {
            return getStoredCartId();
        },
        clearCart: function () {
            clearStoredCartId();
            cachedCart = null;
            emitCartUpdated(null);
        },
        getCachedCart: function () {
            return cachedCart;
        },
        listProducts: listProducts,
        listCategories: listCategories,
        getProduct: getProduct,
        createCart: createCart,
        getCart: getCart,
        addCartItem: addCartItem,
        updateCartItem: updateCartItem,
        removeCartItem: removeCartItem,
        applyCoupon: applyCoupon,
        removeCoupon: removeCoupon,
        getShippingOptions: getShippingOptions,
        updateShipping: updateShipping,
        checkout: checkout,
        validateCheckout: validateCheckout,
        getPaymentOptions: getPaymentOptions,
        getOrders: getOrders,
        getOrder: getOrder,
        getCustomerMe: customersMe,
        loginCustomer: customerLogin,
        registerCustomer: customerRegister,
        logoutCustomer: customerLogout,
        updateCustomerMe: customerMeUpdate,
        requestOtp: customerOtpRequest,
        verifyOtp: customerOtpVerify,
        startGoogleAuth: function (payload) { return customerSocialAuthStart('google', payload); },
        startFacebookAuth: function (payload) { return customerSocialAuthStart('facebook', payload); },
        trackShipment: trackShipment,
        startPayment: startPayment,
        onCartUpdated: function (callback) {
            if (typeof callback !== 'function') {
                return function () {};
            }

            var handler = function (event) {
                callback(event.detail || null);
            };

            window.addEventListener(cartUpdatedEventName, handler);

            if (cachedCart) {
                callback(cachedCart);
            }

            return function () {
                window.removeEventListener(cartUpdatedEventName, handler);
            };
        },
        onCustomerStateUpdated: function (callback) {
            if (typeof callback !== 'function') {
                return function () {};
            }

            var handler = function (event) {
                callback(asObject(event && event.detail));
            };

            window.addEventListener(customerStateEventName, handler);

            if (customerMeSnapshot) {
                callback(customerMeSnapshot);
            }

            return function () {
                window.removeEventListener(customerStateEventName, handler);
            };
        },
        mountProductsWidget: mountProductsWidget,
        mountSearchWidget: mountSearchWidget,
        mountCategoriesWidget: mountCategoriesWidget,
        mountProductDetailWidget: mountProductDetailWidget,
        mountProductGalleryWidget: mountProductGalleryWidget,
        mountCouponWidget: mountCouponWidget,
        mountCheckoutFormWidget: mountCheckoutFormWidget,
        mountOrderSummaryWidget: mountOrderSummaryWidget,
        mountShippingSelectorWidget: mountShippingSelectorWidget,
        mountPaymentSelectorWidget: mountPaymentSelectorWidget,
        mountOrdersListWidget: mountOrdersListWidget,
        mountOrderDetailWidget: mountOrderDetailWidget,
        mountCartWidget: renderCartWidget,
        mountAuthWidget: mountAuthWidget,
        mountAccountProfileWidget: mountAccountProfileWidget,
        mountAccountSecurityWidget: mountAccountSecurityWidget,
    };

    document.addEventListener('DOMContentLoaded', function () {
        mountWidgets();
    });
})();
JS;
    }

    protected function buildBookingRuntimeScript(): string
    {
        return <<<'JS'
(function () {
    'use strict';

    var appConfig = window.__APP_CONFIG__ || {};
    var booking = appConfig.booking || {};

    if (!booking.enabled) {
        return;
    }

    var bookingCreatedEventName = (booking.events && booking.events.booking_created) || 'webby:booking:created';
    var latestCreatedBooking = null;

    function asObject(value) {
        return value && typeof value === 'object' ? value : {};
    }

    function toQuery(params) {
        if (!params || typeof params !== 'object') {
            return '';
        }

        var searchParams = new URLSearchParams();
        Object.keys(params).forEach(function (key) {
            var value = params[key];
            if (value === null || value === undefined || value === '') {
                return;
            }

            searchParams.set(key, String(value));
        });

        var encoded = searchParams.toString();

        return encoded ? ('?' + encoded) : '';
    }

    function parseResponse(response) {
        return response
            .text()
            .then(function (body) {
                var payload = {};
                if (body && body.trim() !== '') {
                    try {
                        payload = JSON.parse(body);
                    } catch (_error) {
                        payload = { message: body };
                    }
                }

                if (!response.ok) {
                    var message = (payload && (payload.error || payload.message)) || ('HTTP_' + response.status);
                    var error = new Error(message);
                    error.status = response.status;
                    error.payload = payload;
                    throw error;
                }

                return payload;
            });
    }

    function jsonRequest(url, method, body) {
        var init = {
            method: method || 'GET',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        };

        if (body !== undefined) {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(body);
        }

        return fetch(url, init).then(parseResponse);
    }

    function listServices(params) {
        var endpoint = booking.services_url;
        if (!endpoint) {
            return Promise.reject(new Error('Booking services endpoint is missing'));
        }

        return jsonRequest(endpoint + toQuery(asObject(params)), 'GET');
    }

    function getSlots(params) {
        var endpoint = booking.slots_url;
        if (!endpoint) {
            return Promise.reject(new Error('Booking slots endpoint is missing'));
        }

        return jsonRequest(endpoint + toQuery(asObject(params)), 'GET');
    }

    function emitBookingCreated(bookingPayload) {
        latestCreatedBooking = bookingPayload || null;
        window.dispatchEvent(new CustomEvent(bookingCreatedEventName, { detail: bookingPayload || null }));
        document.dispatchEvent(new CustomEvent(bookingCreatedEventName, { detail: bookingPayload || null }));
    }

    function createBooking(payload) {
        var endpoint = booking.create_booking_url;
        if (!endpoint) {
            return Promise.reject(new Error('Create booking endpoint is missing'));
        }

        return jsonRequest(endpoint, 'POST', asObject(payload)).then(function (response) {
            emitBookingCreated(response && response.booking ? response.booking : null);

            return response;
        });
    }

    function resolveLocalTimezone() {
        try {
            var timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (timezone) {
                return timezone;
            }
        } catch (_error) {
            // ignore
        }

        return 'UTC';
    }

    function currentDateValue() {
        var now = new Date();
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var day = String(now.getDate()).padStart(2, '0');

        return now.getFullYear() + '-' + month + '-' + day;
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatTimeRange(slot) {
        var startsAt = slot && slot.starts_at ? new Date(slot.starts_at) : null;
        var endsAt = slot && slot.ends_at ? new Date(slot.ends_at) : null;

        if (!startsAt || Number.isNaN(startsAt.getTime()) || !endsAt || Number.isNaN(endsAt.getTime())) {
            return slot && slot.starts_at ? String(slot.starts_at) : 'Unknown';
        }

        var options = { hour: '2-digit', minute: '2-digit' };

        return startsAt.toLocaleTimeString([], options) + ' - ' + endsAt.toLocaleTimeString([], options);
    }

    function mountWidget(container, options) {
        if (!container) {
            return;
        }

        var widgetOptions = asObject(options);
        var preferredServiceId = widgetOptions.service_id || container.getAttribute('data-webby-booking-service-id') || '';
        var timezone = widgetOptions.timezone
            || container.getAttribute('data-webby-booking-timezone')
            || resolveLocalTimezone();
        var preferredDate = widgetOptions.date
            || container.getAttribute('data-webby-booking-date')
            || currentDateValue();
        var prepaymentMode = widgetOptions.prepayment_mode
            || container.getAttribute('data-webby-booking-prepayment')
            || 'none';

        container.innerHTML = ''
            + '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:10px;">'
            + '<div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">'
            + '<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#334155;">Service'
            + '<select data-webby-booking-service style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;background:#fff;color:#0f172a;"></select>'
            + '</label>'
            + '<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#334155;">Date'
            + '<input type="date" data-webby-booking-date style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;background:#fff;color:#0f172a;" />'
            + '</label>'
            + '</div>'
            + '<div data-webby-booking-slots style="display:flex;flex-wrap:wrap;gap:8px;"></div>'
            + '<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#334155;">Name'
            + '<input type="text" data-webby-booking-customer-name style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;background:#fff;color:#0f172a;" />'
            + '</label>'
            + '<div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">'
            + '<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#334155;">Email'
            + '<input type="email" data-webby-booking-customer-email style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;background:#fff;color:#0f172a;" />'
            + '</label>'
            + '<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#334155;">Phone'
            + '<input type="text" data-webby-booking-customer-phone style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;background:#fff;color:#0f172a;" />'
            + '</label>'
            + '</div>'
            + '<label style="display:flex;flex-direction:column;gap:4px;font-size:12px;color:#334155;">Notes'
            + '<textarea data-webby-booking-customer-notes rows="3" style="border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;background:#fff;color:#0f172a;resize:vertical;"></textarea>'
            + '</label>'
            + '<button type="button" data-webby-booking-submit style="height:38px;border:0;border-radius:8px;background:#0f172a;color:#fff;font-size:13px;cursor:pointer;">Book now</button>'
            + '<div data-webby-booking-message style="min-height:20px;font-size:13px;color:#64748b;"></div>'
            + '</div>';

        var serviceSelect = container.querySelector('[data-webby-booking-service]');
        var dateInput = container.querySelector('[data-webby-booking-date]');
        var slotsContainer = container.querySelector('[data-webby-booking-slots]');
        var submitButton = container.querySelector('[data-webby-booking-submit]');
        var nameInput = container.querySelector('[data-webby-booking-customer-name]');
        var emailInput = container.querySelector('[data-webby-booking-customer-email]');
        var phoneInput = container.querySelector('[data-webby-booking-customer-phone]');
        var notesInput = container.querySelector('[data-webby-booking-customer-notes]');
        var messageNode = container.querySelector('[data-webby-booking-message]');

        var selectedSlot = null;
        var currentSlots = [];
        var servicesById = {};

        function setMessage(text, isError) {
            if (!messageNode) {
                return;
            }

            messageNode.textContent = text || '';
            messageNode.style.color = isError ? '#b91c1c' : '#64748b';
        }

        function renderSlots(slots) {
            currentSlots = Array.isArray(slots) ? slots : [];
            selectedSlot = currentSlots[0] || null;

            if (!slotsContainer) {
                return;
            }

            if (currentSlots.length === 0) {
                slotsContainer.innerHTML = '<div style="font-size:13px;color:#64748b;">No available slots for selected date.</div>';

                return;
            }

            slotsContainer.innerHTML = '';

            currentSlots.forEach(function (slot, index) {
                var button = document.createElement('button');
                button.type = 'button';
                button.textContent = formatTimeRange(slot);
                button.style.border = '1px solid #cbd5e1';
                button.style.borderRadius = '999px';
                button.style.padding = '6px 10px';
                button.style.fontSize = '12px';
                button.style.background = index === 0 ? '#0f172a' : '#fff';
                button.style.color = index === 0 ? '#fff' : '#0f172a';
                button.style.cursor = 'pointer';
                button.setAttribute('data-webby-booking-slot-index', String(index));
                button.addEventListener('click', function () {
                    selectedSlot = slot;
                    slotsContainer.querySelectorAll('[data-webby-booking-slot-index]').forEach(function (node) {
                        var active = node === button;
                        node.style.background = active ? '#0f172a' : '#fff';
                        node.style.color = active ? '#fff' : '#0f172a';
                    });
                });
                slotsContainer.appendChild(button);
            });
        }

        function loadSlots() {
            var serviceId = serviceSelect ? Number.parseInt(serviceSelect.value || '0', 10) : 0;
            var dateValue = dateInput ? dateInput.value : '';

            if (!Number.isFinite(serviceId) || serviceId <= 0 || !dateValue) {
                renderSlots([]);

                return Promise.resolve([]);
            }

            setMessage('Loading slots...', false);

            return getSlots({
                service_id: serviceId,
                date: dateValue,
                timezone: timezone,
            }).then(function (payload) {
                renderSlots(payload && payload.slots ? payload.slots : []);
                setMessage('', false);

                return payload;
            }).catch(function (error) {
                renderSlots([]);
                setMessage((error && error.message) || 'Failed to load slots.', true);
                throw error;
            });
        }

        function loadServices() {
            if (!serviceSelect) {
                return Promise.resolve([]);
            }

            serviceSelect.innerHTML = '<option value="">Loading...</option>';

            return listServices({}).then(function (payload) {
                var services = payload && Array.isArray(payload.services) ? payload.services : [];
                servicesById = {};
                services.forEach(function (service) {
                    if (!service || service.id === undefined || service.id === null) {
                        return;
                    }

                    servicesById[String(service.id)] = service;
                });

                if (services.length === 0) {
                    serviceSelect.innerHTML = '<option value="">No services</option>';
                    renderSlots([]);
                    setMessage('No active booking services found.', true);

                    return services;
                }

                serviceSelect.innerHTML = services.map(function (service) {
                    var suffix = service.price ? (' - ' + escapeHtml(service.price + ' ' + (service.currency || ''))) : '';
                    return '<option value="' + escapeHtml(service.id) + '">' + escapeHtml(service.name) + suffix + '</option>';
                }).join('');

                if (preferredServiceId) {
                    var hasPreferred = Array.prototype.some.call(serviceSelect.options, function (option) {
                        return String(option.value) === String(preferredServiceId);
                    });
                    if (hasPreferred) {
                        serviceSelect.value = String(preferredServiceId);
                    }
                }

                return loadSlots().then(function () {
                    return services;
                });
            }).catch(function (error) {
                serviceSelect.innerHTML = '<option value="">Unavailable</option>';
                setMessage((error && error.message) || 'Failed to load services.', true);
                throw error;
            });
        }

        if (dateInput) {
            dateInput.value = preferredDate;
            dateInput.addEventListener('change', function () {
                loadSlots().catch(function () {});
            });
        }

        if (serviceSelect) {
            serviceSelect.addEventListener('change', function () {
                loadSlots().catch(function () {});
            });
        }

        if (submitButton) {
            submitButton.addEventListener('click', function () {
                if (!selectedSlot) {
                    setMessage('Please choose an available slot.', true);

                    return;
                }

                var serviceId = serviceSelect ? Number.parseInt(serviceSelect.value || '0', 10) : 0;
                if (!Number.isFinite(serviceId) || serviceId <= 0) {
                    setMessage('Please select a service.', true);

                    return;
                }

                submitButton.disabled = true;
                setMessage('Submitting booking...', false);

                var selectedService = servicesById[String(serviceId)] || null;
                var createPayload = {
                    service_id: serviceId,
                    starts_at: selectedSlot.starts_at,
                    ends_at: selectedSlot.ends_at,
                    duration_minutes: selectedSlot.duration_minutes,
                    timezone: selectedSlot.timezone || timezone,
                    customer_name: nameInput ? nameInput.value : null,
                    customer_email: emailInput ? emailInput.value : null,
                    customer_phone: phoneInput ? phoneInput.value : null,
                    customer_notes: notesInput ? notesInput.value : null,
                };

                var shouldChargePrepayment = booking.prepayment_enabled
                    && prepaymentMode === 'full'
                    && selectedService
                    && selectedService.prepayment_available
                    && selectedService.price !== null
                    && selectedService.price !== undefined
                    && selectedService.price !== '';

                if (shouldChargePrepayment) {
                    createPayload.prepayment_amount = selectedService.price;
                    createPayload.prepayment_currency = selectedService.currency || 'GEL';
                }

                createBooking(createPayload).then(function (response) {
                    var bookingPayload = response && response.booking ? response.booking : null;
                    var bookingNumber = bookingPayload && bookingPayload.booking_number ? bookingPayload.booking_number : null;
                    setMessage(bookingNumber ? ('Booking created: ' + bookingNumber) : 'Booking created successfully.', false);

                    if (widgetOptions && typeof widgetOptions.onCreated === 'function') {
                        widgetOptions.onCreated(bookingPayload);
                    }

                    return loadSlots();
                }).catch(function (error) {
                    setMessage((error && error.message) || 'Failed to create booking.', true);
                }).finally(function () {
                    submitButton.disabled = false;
                });
            });
        }

        loadServices().catch(function () {});
    }

    function replacePattern(pattern, params) {
        var output = String(pattern || '');
        var data = asObject(params);
        Object.keys(data).forEach(function (key) {
            output = output.replace(new RegExp('\\{' + key + '\\}', 'g'), encodeURIComponent(String(data[key])));
        });

        return output;
    }

    function getService(slug) {
        var endpointPattern = booking.service_detail_url_pattern;
        if (!endpointPattern) {
            return Promise.reject(new Error('Booking service detail endpoint is missing'));
        }

        var normalizedSlug = String(slug || '').trim();
        if (!normalizedSlug) {
            return Promise.reject(new Error('Booking service slug is required'));
        }

        return jsonRequest(replacePattern(endpointPattern, { slug: normalizedSlug }), 'GET');
    }

    function listStaff(params) {
        var endpoint = booking.staff_url;
        if (!endpoint) {
            return Promise.reject(new Error('Booking staff endpoint is missing'));
        }

        return jsonRequest(endpoint + toQuery(asObject(params)), 'GET');
    }

    function getCalendar(params) {
        var endpoint = booking.calendar_url;
        if (!endpoint) {
            return Promise.reject(new Error('Booking calendar endpoint is missing'));
        }

        return jsonRequest(endpoint + toQuery(asObject(params)), 'GET');
    }

    function getBookings(params) {
        var endpoint = booking.my_bookings_url;
        if (!endpoint) {
            return Promise.reject(new Error('Booking my bookings endpoint is missing'));
        }

        return jsonRequest(endpoint + toQuery(asObject(params)), 'GET');
    }

    function showBooking(bookingId) {
        var endpointPattern = booking.booking_url_pattern;
        if (!endpointPattern) {
            return Promise.reject(new Error('Booking detail endpoint is missing'));
        }

        return jsonRequest(replacePattern(endpointPattern, { booking_id: bookingId }), 'GET');
    }

    function updateBooking(bookingId, payload) {
        var endpointPattern = booking.booking_url_pattern;
        if (!endpointPattern) {
            return Promise.reject(new Error('Booking update endpoint is missing'));
        }

        return jsonRequest(replacePattern(endpointPattern, { booking_id: bookingId }), 'PUT', asObject(payload));
    }

    function rescheduleBooking(bookingId, payload) {
        var data = asObject(payload);
        data.action = 'reschedule';
        return updateBooking(bookingId, data);
    }

    function cancelBooking(bookingId, payload) {
        var data = asObject(payload);
        data.action = 'cancel';
        return updateBooking(bookingId, data);
    }

    function mountServicesWidget(container, options) {
        if (!container) {
            return;
        }

        container.setAttribute('data-webby-booking-runtime', 'services');
        container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading services...</div>';

        listServices(asObject(options)).then(function (payload) {
            var services = Array.isArray(payload && payload.services) ? payload.services : [];
            if (services.length === 0) {
                container.innerHTML = '<div style="font-size:13px;color:#64748b;">No services found.</div>';
                return;
            }

            container.innerHTML = services.map(function (service) {
                return ''
                    + '<div style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;">'
                    + '<div style="font-weight:600;">' + escapeHtml(service.name || '') + '</div>'
                    + '<div style="font-size:12px;color:#64748b;">' + escapeHtml(service.description || '') + '</div>'
                    + '<div style="font-size:12px;color:#0f172a;margin-top:4px;">' + escapeHtml((service.price || '') + ' ' + (service.currency || '')) + '</div>'
                    + '</div>';
            }).join('');
        }).catch(function (error) {
            container.innerHTML = '<div style="font-size:13px;color:#b91c1c;">' + escapeHtml((error && error.message) || 'Failed to load services') + '</div>';
        });
    }

    function mountServiceDetailWidget(container, options) {
        if (!container) {
            return;
        }

        var widgetOptions = asObject(options);
        var slug = widgetOptions.slug || container.getAttribute('data-webby-booking-service-slug') || '';
        container.setAttribute('data-webby-booking-runtime', 'service-detail');

        if (!slug) {
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Missing service slug.</div>';
            return;
        }

        container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading service...</div>';

        getService(slug).then(function (payload) {
            var service = asObject(payload && payload.service);
            container.innerHTML = ''
                + '<div style="padding:12px;border:1px solid #e2e8f0;border-radius:12px;">'
                + '<h3 style="margin:0 0 6px 0;font-size:16px;">' + escapeHtml(service.name || slug) + '</h3>'
                + '<div style="font-size:13px;color:#475569;">' + escapeHtml(service.description || '') + '</div>'
                + '<div style="font-size:12px;color:#334155;margin-top:8px;">'
                + 'Duration: ' + escapeHtml(service.duration_minutes || '') + ' min'
                + ' • Price: ' + escapeHtml((service.price || '') + ' ' + (service.currency || ''))
                + '</div>'
                + '</div>';
        }).catch(function (error) {
            container.innerHTML = '<div style="font-size:13px;color:#b91c1c;">' + escapeHtml((error && error.message) || 'Failed to load service') + '</div>';
        });
    }

    function mountPricingTableWidget(container, options) {
        if (!container) {
            return;
        }

        container.setAttribute('data-webby-booking-runtime', 'pricing-table');
        mountServicesWidget(container, options);
    }

    function mountFaqWidget(container, options) {
        if (!container) {
            return;
        }

        container.setAttribute('data-webby-booking-runtime', 'faq');
        container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading FAQ...</div>';

        listServices(asObject(options)).then(function (payload) {
            var services = Array.isArray(payload && payload.services) ? payload.services : [];
            container.innerHTML = services.map(function (service) {
                return ''
                    + '<details style="border:1px solid #e2e8f0;border-radius:10px;padding:8px 10px;margin-bottom:8px;">'
                    + '<summary style="cursor:pointer;font-weight:600;">' + escapeHtml(service.name || 'Service') + '</summary>'
                    + '<div style="margin-top:6px;font-size:13px;color:#475569;">' + escapeHtml(service.description || 'No FAQ entry configured.') + '</div>'
                    + '</details>';
            }).join('') || '<div style="font-size:13px;color:#64748b;">No FAQ items.</div>';
        }).catch(function (error) {
            container.innerHTML = '<div style="font-size:13px;color:#b91c1c;">' + escapeHtml((error && error.message) || 'Failed to load FAQ') + '</div>';
        });
    }

    function mountSlotsWidget(container, options) {
        if (!container) {
            return;
        }

        var widgetOptions = asObject(options);
        var serviceId = widgetOptions.service_id || container.getAttribute('data-webby-booking-service-id');
        var date = widgetOptions.date || container.getAttribute('data-webby-booking-date');
        var timezone = widgetOptions.timezone || container.getAttribute('data-webby-booking-timezone');
        var staffResourceId = widgetOptions.staff_resource_id || container.getAttribute('data-webby-booking-staff-resource-id');

        if (!serviceId || !date) {
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Set service/date to load slots.</div>';
            return;
        }

        container.setAttribute('data-webby-booking-runtime', 'slots');
        container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading slots...</div>';

        getSlots({
            service_id: serviceId,
            staff_resource_id: staffResourceId || undefined,
            date: date,
            timezone: timezone || undefined,
        }).then(function (payload) {
            var slots = Array.isArray(payload && payload.slots) ? payload.slots : [];
            container.innerHTML = slots.map(function (slot) {
                return '<span style="display:inline-flex;border:1px solid #cbd5e1;border-radius:999px;padding:6px 10px;margin:4px;font-size:12px;">'
                    + escapeHtml(formatTimeRange(slot))
                    + '</span>';
            }).join('') || '<div style="font-size:13px;color:#64748b;">No slots.</div>';
        }).catch(function (error) {
            container.innerHTML = '<div style="font-size:13px;color:#b91c1c;">' + escapeHtml((error && error.message) || 'Failed to load slots') + '</div>';
        });
    }

    function mountCalendarWidget(container, options) {
        if (!container) {
            return;
        }

        var widgetOptions = asObject(options);
        container.setAttribute('data-webby-booking-runtime', 'calendar');
        container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading calendar...</div>';

        getCalendar({
            from: widgetOptions.from || container.getAttribute('data-webby-booking-from') || undefined,
            to: widgetOptions.to || container.getAttribute('data-webby-booking-to') || undefined,
            staff_resource_id: widgetOptions.staff_resource_id || container.getAttribute('data-webby-booking-staff-resource-id') || undefined,
        }).then(function (payload) {
            var events = Array.isArray(payload && payload.events) ? payload.events : [];
            container.innerHTML = ''
                + '<div style="font-size:12px;color:#64748b;margin-bottom:8px;">Events: ' + escapeHtml(events.length) + '</div>'
                + (events.map(function (event) {
                    return '<div style="padding:8px 10px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;">'
                        + '<div style="font-weight:600;">' + escapeHtml((event.customer_name || 'Booking') + '') + '</div>'
                        + '<div style="font-size:12px;color:#475569;">' + escapeHtml((event.starts_at || '') + ' → ' + (event.ends_at || '')) + '</div>'
                        + '</div>';
                }).join('') || '<div style="font-size:13px;color:#64748b;">No calendar events.</div>');
        }).catch(function (error) {
            container.innerHTML = '<div style="font-size:13px;color:#b91c1c;">' + escapeHtml((error && error.message) || 'Failed to load calendar') + '</div>';
        });
    }

    function mountStaffWidget(container, options) {
        if (!container) {
            return;
        }

        container.setAttribute('data-webby-booking-runtime', 'staff');
        container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading staff...</div>';

        listStaff(asObject(options)).then(function (payload) {
            var staff = Array.isArray(payload && payload.staff) ? payload.staff : [];
            container.innerHTML = staff.map(function (row) {
                return '<div style="padding:8px 10px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;">'
                    + '<div style="font-weight:600;">' + escapeHtml(row.name || '') + '</div>'
                    + '<div style="font-size:12px;color:#64748b;">' + escapeHtml(row.email || row.phone || row.slug || '') + '</div>'
                    + '</div>';
            }).join('') || '<div style="font-size:13px;color:#64748b;">No staff found.</div>';
        }).catch(function (error) {
            container.innerHTML = '<div style="font-size:13px;color:#b91c1c;">' + escapeHtml((error && error.message) || 'Failed to load staff') + '</div>';
        });
    }

    function mountManageWidget(container, options) {
        if (!container) {
            return;
        }

        container.setAttribute('data-webby-booking-runtime', 'manage');
        container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading bookings...</div>';

        getBookings(asObject(options)).then(function (payload) {
            var bookings = Array.isArray(payload && payload.bookings) ? payload.bookings : [];
            container.innerHTML = bookings.map(function (row) {
                return '<div style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;">'
                    + '<div style="font-weight:600;">' + escapeHtml(row.booking_number || ('#' + row.id)) + '</div>'
                    + '<div style="font-size:12px;color:#475569;">' + escapeHtml((row.starts_at || '') + ' • ' + (row.status || '')) + '</div>'
                    + '</div>';
            }).join('') || '<div style="font-size:13px;color:#64748b;">No bookings found.</div>';
        }).catch(function (error) {
            container.innerHTML = '<div style="font-size:13px;color:#b91c1c;">' + escapeHtml((error && error.message) || 'Failed to load bookings') + '</div>';
        });
    }

    function mountWidgets() {
        var selector = (booking.widgets && booking.widgets.booking_selector) || '[data-webby-booking-widget]';
        var servicesSelector = (booking.widgets && booking.widgets.services_selector) || '[data-webby-booking-services]';
        var serviceDetailSelector = (booking.widgets && booking.widgets.service_detail_selector) || '[data-webby-booking-service-detail]';
        var pricingTableSelector = (booking.widgets && booking.widgets.pricing_table_selector) || '[data-webby-booking-pricing-table]';
        var faqSelector = (booking.widgets && booking.widgets.faq_selector) || '[data-webby-booking-faq]';
        var formSelector = (booking.widgets && booking.widgets.form_selector) || '[data-webby-booking-form]';
        var slotsSelector = (booking.widgets && booking.widgets.slots_selector) || '[data-webby-booking-slots]';
        var calendarSelector = (booking.widgets && booking.widgets.calendar_selector) || '[data-webby-booking-calendar]';
        var staffSelector = (booking.widgets && booking.widgets.staff_selector) || '[data-webby-booking-staff]';
        var manageSelector = (booking.widgets && booking.widgets.manage_selector) || '[data-webby-booking-manage]';

        document.querySelectorAll(selector).forEach(function (node) {
            mountWidget(node, {});
        });
        document.querySelectorAll(formSelector).forEach(function (node) {
            mountWidget(node, {});
        });
        document.querySelectorAll(servicesSelector).forEach(function (node) {
            mountServicesWidget(node, {});
        });
        document.querySelectorAll(serviceDetailSelector).forEach(function (node) {
            mountServiceDetailWidget(node, {});
        });
        document.querySelectorAll(pricingTableSelector).forEach(function (node) {
            mountPricingTableWidget(node, {});
        });
        document.querySelectorAll(faqSelector).forEach(function (node) {
            mountFaqWidget(node, {});
        });
        document.querySelectorAll(slotsSelector).forEach(function (node) {
            mountSlotsWidget(node, {});
        });
        document.querySelectorAll(calendarSelector).forEach(function (node) {
            mountCalendarWidget(node, {});
        });
        document.querySelectorAll(staffSelector).forEach(function (node) {
            mountStaffWidget(node, {});
        });
        document.querySelectorAll(manageSelector).forEach(function (node) {
            mountManageWidget(node, {});
        });
    }

    window.WebbyBooking = {
        getConfig: function () {
            return booking;
        },
        getLatestBooking: function () {
            return latestCreatedBooking;
        },
        listServices: listServices,
        getService: getService,
        listStaff: listStaff,
        getSlots: getSlots,
        getCalendar: getCalendar,
        createBooking: createBooking,
        getBookings: getBookings,
        showBooking: showBooking,
        updateBooking: updateBooking,
        rescheduleBooking: rescheduleBooking,
        cancelBooking: cancelBooking,
        mountWidget: mountWidget,
        mountBookingFormWidget: mountWidget,
        mountServicesWidget: mountServicesWidget,
        mountServiceDetailWidget: mountServiceDetailWidget,
        mountPricingTableWidget: mountPricingTableWidget,
        mountFaqWidget: mountFaqWidget,
        mountSlotsWidget: mountSlotsWidget,
        mountCalendarWidget: mountCalendarWidget,
        mountStaffWidget: mountStaffWidget,
        mountManageWidget: mountManageWidget,
        onBookingCreated: function (callback) {
            if (typeof callback !== 'function') {
                return function () {};
            }

            var handler = function (event) {
                callback(event.detail || null);
            };

            window.addEventListener(bookingCreatedEventName, handler);

            if (latestCreatedBooking) {
                callback(latestCreatedBooking);
            }

            return function () {
                window.removeEventListener(bookingCreatedEventName, handler);
            };
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        mountWidgets();
    });
})();
JS;
    }

    protected function buildCmsRuntimeScript(): string
    {
        return <<<'JS'
(function () {
    'use strict';

    var config = window.__APP_CONFIG__ || {};
    var cms = config.cms || {};

    if (!cms.enabled) {
        return;
    }

    function safeDecodeSegment(value) {
        try {
            return decodeURIComponent(String(value || ''));
        } catch (_error) {
            return String(value || '');
        }
    }

    function normalizeLeafSlug(value) {
        var normalized = safeDecodeSegment(value).trim().toLowerCase();
        if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(normalized)) {
            return '';
        }

        return normalized;
    }

    function normalizePathSegments(pathname, projectId) {
        var path = String(pathname || '/').split('#')[0].split('?')[0];
        var segments = path.split('/').filter(function (segment) { return segment.length > 0; });

        if (projectId && (segments[0] === 'app' || segments[0] === 'preview') && segments[1] === String(projectId)) {
            segments = segments.slice(2);
        }

        return segments.map(function (segment) {
            return safeDecodeSegment(segment).trim();
        }).filter(function (segment) {
            return segment.length > 0;
        });
    }

    function toSlug(pathname, projectId) {
        return resolveCmsRoute(pathname, projectId).slug;
    }

    function resolveCmsRoute(pathname, projectId) {
        var segments = normalizePathSegments(pathname, projectId);
        var lowerSegments = segments.map(function (segment) {
            return String(segment).toLowerCase();
        });
        var routePath = '/' + segments.map(function (segment) {
            return encodeURIComponent(segment);
        }).join('/');
        if (routePath === '//') {
            routePath = '/';
        }

        var route = {
            slug: 'home',
            requested_slug: 'home',
            route_path: routePath === '/' ? '/' : routePath,
            params: {},
        };

        if (segments.length === 0) {
            return route;
        }

        var first = lowerSegments[0] || '';
        var second = lowerSegments[1] || '';
        var third = lowerSegments[2] || '';
        var secondRaw = segments[1] || '';
        var thirdRaw = segments[2] || '';
        var lastLeaf = normalizeLeafSlug(segments[segments.length - 1] || '');

        var setSlug = function (slug) {
            route.slug = slug;
            route.requested_slug = slug;
            return route;
        };

        if (first === 'product' || first === 'products') {
            if (segments.length >= 2) {
                route.params.product_slug = secondRaw;
                route.params.slug = secondRaw;
            }
            return setSlug('product');
        }

        if (first === 'category' || first === 'categories') {
            if (segments.length >= 2) {
                route.params.category_slug = secondRaw;
                route.params.slug = secondRaw;
            }
            return setSlug('shop');
        }

        if (first === 'shop') {
            if ((second === 'category' || second === 'categories') && segments.length >= 3) {
                route.params.category_slug = thirdRaw;
                route.params.slug = thirdRaw;
            } else if (segments.length === 2 && second && second !== 'search' && second !== 'filter') {
                route.params.category_slug = secondRaw;
                route.params.slug = secondRaw;
            }
            return setSlug('shop');
        }

        if (first === 'cart') {
            return setSlug('cart');
        }

        if (first === 'checkout') {
            return setSlug('checkout');
        }

        if (first === 'login' || first === 'register' || first === 'auth') {
            route.params.auth_mode = first;
            return setSlug('login');
        }

        if (first === 'account') {
            if (second === '' || second === 'dashboard' || second === 'profile') {
                return setSlug('account');
            }

            if (second === 'login' || second === 'register') {
                route.params.auth_mode = second;
                return setSlug('login');
            }

            if (second === 'orders') {
                if (segments.length >= 3) {
                    route.params.order_id = thirdRaw;
                    route.params.id = thirdRaw;
                    return setSlug('order');
                }

                return setSlug('orders');
            }

            return setSlug('account');
        }

        if (first === 'orders') {
            if (segments.length >= 2) {
                route.params.order_id = secondRaw;
                route.params.id = secondRaw;
                return setSlug('order');
            }

            return setSlug('orders');
        }

        if (lastLeaf) {
            route.slug = lastLeaf;
            route.requested_slug = lastLeaf;
            return route;
        }

        return route;
    }

    function normalizeRouteParamsForQuery(params) {
        if (!params || typeof params !== 'object') {
            return {};
        }

        var normalized = {};
        Object.keys(params).forEach(function (key) {
            if (!Object.prototype.hasOwnProperty.call(params, key)) {
                return;
            }

            var value = params[key];
            if (value === null || value === undefined) {
                return;
            }

            if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
                normalized[String(key)] = String(value);
            }
        });

        return normalized;
    }

    function routeSignature(routeInfo) {
        var route = routeInfo && typeof routeInfo === 'object' ? routeInfo : { slug: 'home', params: {} };
        var params = normalizeRouteParamsForQuery(route.params);
        var keys = Object.keys(params).sort();
        var encodedPairs = keys.map(function (key) {
            return key + '=' + params[key];
        });

        return [route.slug || 'home', route.route_path || '/', encodedPairs.join('&')].join('|');
    }

    function jsonFetch(url) {
        return fetch(url, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP_' + response.status);
            }

            return response.json();
        });
    }

    function jsonPost(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload || {}),
        }).then(function (response) {
            return response.text().then(function (raw) {
                var parsed = {};
                if (raw && raw.trim() !== '') {
                    try {
                        parsed = JSON.parse(raw);
                    } catch (_error) {
                        parsed = { raw: raw };
                    }
                }

                if (!response.ok) {
                    var error = new Error('HTTP_' + response.status);
                    error.status = response.status;
                    error.payload = parsed;
                    throw error;
                }

                return parsed;
            });
        });
    }

    function asObject(value) {
        return value && typeof value === 'object' ? value : {};
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function textValue(value, fallback) {
        var normalized = typeof value === 'string' ? value.trim() : '';
        return normalized !== '' ? normalized : (fallback || '');
    }

    function numberValue(value, fallback) {
        var parsed = Number.parseFloat(String(value));
        return Number.isFinite(parsed) ? parsed : (fallback || 0);
    }

    function formatCurrencyAmount(value, currency) {
        var numeric = numberValue(value, 0);
        var normalizedCurrency = textValue(currency, 'GEL').toUpperCase();

        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: normalizedCurrency,
                maximumFractionDigits: 2,
            }).format(numeric);
        } catch (_error) {
            return numeric.toFixed(2) + ' ' + normalizedCurrency;
        }
    }

    function ensureRoleNode(root, selector, tagName, role) {
        var existing = root.querySelector(selector);
        if (existing) {
            return existing;
        }

        var node = document.createElement(tagName);
        if (role) {
            node.setAttribute('data-webu-role', role);
        }
        root.appendChild(node);

        return node;
    }

    function cmsSearchUrl() {
        if (typeof cms.search_url === 'string' && cms.search_url.trim() !== '') {
            return cms.search_url;
        }

        var base = String(cms.api_base_url || '').replace(/\/+$/, '');
        var siteId = cms.site_id ? String(cms.site_id) : '';
        if (!base || !siteId) {
            return '';
        }

        return base + '/public/sites/' + encodeURIComponent(siteId) + '/search';
    }

    function cmsCustomerMeUrl() {
        if (typeof cms.customer_me_url === 'string' && cms.customer_me_url.trim() !== '') {
            return cms.customer_me_url;
        }

        var base = String(cms.api_base_url || '').replace(/\/+$/, '');
        var siteId = cms.site_id ? String(cms.site_id) : '';
        if (!base || !siteId) {
            return '';
        }

        return base + '/public/sites/' + encodeURIComponent(siteId) + '/customers' + '/me';
    }

    function cmsFormSubmitUrl(formKey) {
        var key = textValue(formKey, '');
        if (key === '') {
            return '';
        }

        if (typeof cms.form_submit_url_pattern === 'string' && cms.form_submit_url_pattern.indexOf('{key}') !== -1) {
            return cms.form_submit_url_pattern.replace('{key}', encodeURIComponent(key));
        }

        var base = String(cms.api_base_url || '').replace(/\/+$/, '');
        var siteId = cms.site_id ? String(cms.site_id) : '';
        if (!base || !siteId) {
            return '';
        }

        return base + '/public/sites/' + encodeURIComponent(siteId) + '/forms/' + encodeURIComponent(key) + '/submit';
    }

    function normalizeSearchMode(value) {
        var normalized = textValue(value, 'site').toLowerCase();
        if (normalized === 'products' || normalized === 'posts') {
            return normalized;
        }

        return 'site';
    }

    function normalizeMenuItems(payload) {
        var menu = asObject(payload);
        var items = Array.isArray(menu.items_json) ? menu.items_json : [];

        return items.map(function (item, index) {
            var row = asObject(item);
            var label = textValue(row.label, 'Menu ' + (index + 1));
            var url = textValue(row.url, '#');

            return {
                label: label,
                url: url,
            };
        });
    }

    function getCmsPayload() {
        return asObject(window.__WEBBY_CMS__ || null);
    }

    function renderNavLogoWidget(node, payload) {
        if (!node) {
            return;
        }

        var data = asObject(payload);
        var site = asObject(data.site);
        var globalSettings = asObject(data.global_settings);
        var brandName = textValue(site.name, 'Webu');
        var logoUrl = textValue(asObject(globalSettings).logo_asset_url, '');

        if (node.tagName === 'A' && !node.getAttribute('href')) {
            node.setAttribute('href', '/');
        }

        var nameNode = node.querySelector('[data-webu-role="nav-logo-name"]');
        var markNode = node.querySelector('[data-webu-role="nav-logo-mark"]');
        var textWrap = node.querySelector('[data-webu-role="nav-logo-text-wrap"]');

        if (!nameNode && !markNode && !textWrap) {
            node.textContent = brandName;
            return;
        }

        if (nameNode) {
            nameNode.textContent = brandName;
        }

        if (markNode) {
            markNode.textContent = brandName.charAt(0).toUpperCase() || 'W';
        }

        if (logoUrl && node.tagName === 'A') {
            node.setAttribute('title', brandName);
        }
    }

    function renderNavMenuWidget(node, payload) {
        if (!node) {
            return;
        }

        var data = asObject(payload);
        var menus = asObject(data.menus);
        var requestedKey = textValue(node.getAttribute('data-menu-key') || node.getAttribute('data-webby-menu-key'), 'header').toLowerCase();
        var menuPayload = menus[requestedKey] || menus.header || menus.footer || null;
        var items = normalizeMenuItems(menuPayload);

        var list = node.querySelector('[data-webu-role="nav-menu-list"]');
        if (!list) {
            list = document.createElement('div');
            list.setAttribute('data-webu-role', 'nav-menu-list');
            node.appendChild(list);
        }

        list.innerHTML = '';

        if (items.length === 0) {
            list.innerHTML = '<span data-webu-role="nav-menu-item">' + escapeHtml('No menu items') + '</span>';
            return;
        }

        items.slice(0, 12).forEach(function (item) {
            var link = document.createElement('a');
            link.setAttribute('data-webu-role', 'nav-menu-item');
            link.setAttribute('href', textValue(item.url, '#'));
            link.textContent = textValue(item.label, 'Menu');
            list.appendChild(link);
        });
    }

    function renderSearchResultRows(resultsNode, items) {
        if (!resultsNode) {
            return;
        }

        resultsNode.innerHTML = '';
        var rows = Array.isArray(items) ? items : [];

        if (rows.length === 0) {
            var empty = document.createElement('div');
            empty.setAttribute('data-webu-role', 'nav-search-result');
            empty.textContent = 'No results';
            resultsNode.appendChild(empty);
            return;
        }

        rows.slice(0, 10).forEach(function (item) {
            var row = asObject(item);
            var node = document.createElement('a');
            node.setAttribute('data-webu-role', 'nav-search-result');
            node.setAttribute('href', textValue(row.url, '#'));
            node.textContent = textValue(row.title, row.slug || 'Result');
            resultsNode.appendChild(node);
        });
    }

    function bindNavSearchWidget(node) {
        if (!node || node.getAttribute('data-webby-nav-search-bound') === '1') {
            return;
        }

        node.setAttribute('data-webby-nav-search-bound', '1');

        var form = ensureRoleNode(node, '[data-webu-role="nav-search-form"]', 'div', 'nav-search-form');
        var input = form.querySelector('[data-webu-role="nav-search-input"]');
        if (!input) {
            input = document.createElement('input');
            input.setAttribute('data-webu-role', 'nav-search-input');
            input.setAttribute('placeholder', 'Search...');
            form.appendChild(input);
        }
        var button = form.querySelector('[data-webu-role="nav-search-button"]');
        if (!button) {
            button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('data-webu-role', 'nav-search-button');
            button.textContent = 'Search';
            form.appendChild(button);
        }
        var contextNode = form.querySelector('[data-webu-role="nav-search-context"]');
        if (!contextNode) {
            contextNode = document.createElement('span');
            contextNode.setAttribute('data-webu-role', 'nav-search-context');
            contextNode.textContent = 'site';
            form.appendChild(contextNode);
        }
        var results = ensureRoleNode(node, '[data-webu-role="nav-search-results"]', 'div', 'nav-search-results');

        var runSearch = function () {
            var endpoint = cmsSearchUrl();
            if (!endpoint) {
                renderSearchResultRows(results, []);
                return;
            }

            var query = textValue(input.value, '');
            var mode = normalizeSearchMode(node.getAttribute('data-search-mode') || node.getAttribute('data-mode') || contextNode.textContent);
            contextNode.textContent = mode;

            if (query === '') {
                renderSearchResultRows(results, []);
                return;
            }

            var searchParams = new URLSearchParams();
            searchParams.set('q', query);
            searchParams.set('mode', mode);

            results.innerHTML = '<div data-webu-role="nav-search-result">Loading...</div>';

            jsonFetch(endpoint + '?' + searchParams.toString())
                .then(function (response) {
                    renderSearchResultRows(results, asObject(response).items);
                })
                .catch(function (error) {
                    results.innerHTML = '<div data-webu-role="nav-search-result">Search unavailable</div>';
                    console.warn('[webby-cms-runtime] nav search failed', error);
                });
        };

        button.addEventListener('click', function () {
            runSearch();
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                runSearch();
            }
        });
    }

    var customerMePromise = null;
    var customerMeSnapshot = null;

    function fetchCustomerMe() {
        if (customerMeSnapshot) {
            return Promise.resolve(customerMeSnapshot);
        }
        if (customerMePromise) {
            return customerMePromise;
        }

        var endpoint = cmsCustomerMeUrl();
        if (!endpoint) {
            return Promise.resolve({
                authenticated: false,
                customer: null,
                links: { login: '/login', account: '/account' },
            });
        }

        customerMePromise = jsonFetch(endpoint)
            .then(function (payload) {
                customerMeSnapshot = asObject(payload);
                return customerMeSnapshot;
            })
            .catch(function (error) {
                console.warn('[webby-cms-runtime] customer me lookup failed', error);
                return {
                    authenticated: false,
                    customer: null,
                    links: { login: '/login', account: '/account' },
                };
            })
            .finally(function () {
                customerMePromise = null;
            });

        return customerMePromise;
    }

    function renderNavAccountWidget(node) {
        if (!node) {
            return;
        }

        fetchCustomerMe().then(function (payload) {
            var data = asObject(payload);
            var links = asObject(data.links);
            var customer = asObject(data.customer);
            var authenticated = !!data.authenticated;

            var labelNode = node.querySelector('[data-webu-role="nav-account-label"]');
            var badgeNode = node.querySelector('[data-webu-role="nav-account-badge"]');

            if (node.tagName === 'A') {
                node.setAttribute('href', authenticated ? textValue(links.account, '/account') : textValue(links.login, '/login'));
            }

            if (labelNode) {
                labelNode.textContent = authenticated
                    ? textValue(customer.name, textValue(customer.email, 'My Account'))
                    : 'Login';
            } else if (node.childElementCount === 0) {
                node.textContent = authenticated
                    ? textValue(customer.name, textValue(customer.email, 'My Account'))
                    : 'Login';
            }

            if (badgeNode) {
                badgeNode.style.display = 'none';
            }

            node.setAttribute('data-webby-nav-account-state', authenticated ? 'customer' : 'guest');
        });
    }

    function renderFooterWidget(node, payload) {
        if (!node) {
            return;
        }

        var data = asObject(payload);
        var menus = asObject(data.menus);
        var footerMenu = menus.footer || menus.header || null;
        var items = normalizeMenuItems(footerMenu);

        var linksGrid = node.querySelector('[data-webu-role="footer-link-columns"]');
        if (!linksGrid) {
            linksGrid = document.createElement('div');
            linksGrid.setAttribute('data-webu-role', 'footer-link-columns');
            node.appendChild(linksGrid);
        }
        linksGrid.innerHTML = '';

        if (items.length === 0) {
            var empty = document.createElement('div');
            empty.setAttribute('data-webu-role', 'footer-link-column');
            empty.textContent = 'Footer links';
            linksGrid.appendChild(empty);
        } else {
            var chunkSize = Math.max(1, Math.ceil(items.length / 3));
            for (var offset = 0; offset < items.length; offset += chunkSize) {
                var column = document.createElement('div');
                column.setAttribute('data-webu-role', 'footer-link-column');
                items.slice(offset, offset + chunkSize).forEach(function (item) {
                    var link = document.createElement('a');
                    link.setAttribute('data-webu-role', 'footer-link-item');
                    link.setAttribute('href', textValue(item.url, '#'));
                    link.textContent = textValue(item.label, 'Link');
                    column.appendChild(link);
                });
                linksGrid.appendChild(column);
            }
        }

        var socialWrap = node.querySelector('[data-webu-role="footer-socials"]');
        if (socialWrap) {
            var globalSettings = asObject(data.global_settings);
            var socialLinks = asObject(globalSettings.social_links_json);
            var socialEntries = Object.keys(socialLinks).filter(function (key) {
                return textValue(socialLinks[key], '') !== '';
            });
            if (socialEntries.length > 0) {
                socialWrap.innerHTML = '';
                socialEntries.slice(0, 8).forEach(function (key) {
                    var link = document.createElement('a');
                    link.setAttribute('data-webu-role', 'footer-social-link');
                    link.setAttribute('href', textValue(socialLinks[key], '#'));
                    link.textContent = key;
                    socialWrap.appendChild(link);
                });
            }
        }
    }

    function formSubmitHelperNode(node) {
        if (!node) {
            return null;
        }

        return node.querySelector('[data-webu-role="form-submit-helper"]');
    }

    function formSubmitButtonNode(node) {
        if (!node) {
            return null;
        }

        if (node.tagName === 'BUTTON') {
            return node;
        }

        return node.querySelector('button,[type="submit"],[data-webu-role="form-submit-button"]');
    }

    function formSubmitSpinnerNode(node) {
        if (!node) {
            return null;
        }

        return node.querySelector('[data-webu-role="form-submit-spinner"]');
    }

    function setStandaloneFormSubmitState(node, state, message) {
        if (!node) {
            return;
        }

        var normalizedState = textValue(state, 'idle').toLowerCase();
        if (['idle', 'loading', 'success', 'error'].indexOf(normalizedState) === -1) {
            normalizedState = 'idle';
        }

        node.setAttribute('data-webby-form-submit-state', normalizedState);

        var helper = formSubmitHelperNode(node);
        if (helper) {
            var text = typeof message === 'string' ? message.trim() : '';
            helper.textContent = text;
            helper.style.display = text === '' ? 'none' : '';
        }

        var spinner = formSubmitSpinnerNode(node);
        if (spinner) {
            spinner.style.display = normalizedState === 'loading' ? 'inline' : 'none';
        }
    }

    function formFieldsFromDom(form) {
        if (!(form instanceof HTMLFormElement)) {
            return {};
        }

        var data = new FormData(form);
        var fields = {};

        data.forEach(function (value, key) {
            if (!key) {
                return;
            }

            if (typeof value !== 'string') {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(fields, key)) {
                return;
            }

            fields[key] = value;
        });

        return fields;
    }

    function resolveStandaloneFormTarget(node) {
        if (!node) {
            return null;
        }

        var selector = textValue(node.getAttribute('data-webby-form-target') || node.getAttribute('data-form-target'), '');
        if (selector !== '') {
            try {
                var selected = document.querySelector(selector);
                if (selected instanceof HTMLFormElement) {
                    return selected;
                }
            } catch (_error) {}
        }

        var closestForm = node.closest('form');
        return closestForm instanceof HTMLFormElement ? closestForm : null;
    }

    function resolveStandaloneFormSubmitEndpoint(node, form) {
        var explicit = textValue(
            (node && (node.getAttribute('data-webby-form-endpoint') || node.getAttribute('data-form-endpoint'))) || '',
            ''
        );
        if (explicit !== '') {
            return explicit;
        }

        if (form instanceof HTMLFormElement) {
            var formEndpoint = textValue(
                form.getAttribute('data-webby-form-endpoint') || form.getAttribute('data-form-endpoint') || form.getAttribute('action'),
                ''
            );
            if (formEndpoint !== '') {
                return formEndpoint;
            }

            var formKey = textValue(form.getAttribute('data-webby-form-key') || form.getAttribute('data-form-key'), '');
            if (formKey !== '') {
                return cmsFormSubmitUrl(formKey);
            }
        }

        var nodeKey = textValue((node && (node.getAttribute('data-webby-form-key') || node.getAttribute('data-form-key'))) || '', '');
        if (nodeKey !== '') {
            return cmsFormSubmitUrl(nodeKey);
        }

        return '';
    }

    function formSubmitContext(node) {
        var payload = getCmsPayload();
        var route = asObject(payload.route);
        var host = asObject(payload.site);
        var componentNode = node ? node.closest('[data-webu-section], [data-webu-id], [data-webu-block-id]') : null;

        return {
            page_slug: textValue(route.slug, ''),
            page_url: textValue(window.location.href, ''),
            referrer: textValue(document.referrer, ''),
            component_id: textValue(
                componentNode && (
                    componentNode.getAttribute('data-webu-id')
                    || componentNode.getAttribute('data-webu-block-id')
                    || componentNode.getAttribute('data-webu-section')
                ),
                ''
            ),
            site_id: textValue(host.id, ''),
        };
    }

    function bindStandaloneFormSubmitWidget(node) {
        if (!node || node.getAttribute('data-webby-form-submit-bound') === '1') {
            return;
        }

        node.setAttribute('data-webby-form-submit-bound', '1');
        setStandaloneFormSubmitState(node, 'idle', '');

        var button = formSubmitButtonNode(node);
        if (!button) {
            return;
        }

        button.addEventListener('click', function (event) {
            var form = resolveStandaloneFormTarget(node);
            var endpoint = resolveStandaloneFormSubmitEndpoint(node, form);

            if (endpoint === '') {
                if (form && typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                window.dispatchEvent(new CustomEvent('webby:form-submit:click', {
                    detail: { node: node, endpoint: null },
                }));
                return;
            }

            event.preventDefault();

            var fields = formFieldsFromDom(form);
            var payload = {
                fields: fields,
                context: formSubmitContext(node),
            };

            var priorDisabled = !!button.disabled;
            button.disabled = true;
            setStandaloneFormSubmitState(node, 'loading', '');

            jsonPost(endpoint, payload)
                .then(function (response) {
                    var responsePayload = asObject(response);
                    var message = textValue(responsePayload.message, 'Submitted');
                    setStandaloneFormSubmitState(node, 'success', message);
                    window.dispatchEvent(new CustomEvent('webby:form-submit:success', {
                        detail: {
                            node: node,
                            endpoint: endpoint,
                            response: responsePayload,
                        },
                    }));
                })
                .catch(function (error) {
                    var payload = asObject(error && error.payload);
                    var message = textValue(payload.error, 'Submission failed');
                    setStandaloneFormSubmitState(node, 'error', message);
                    window.dispatchEvent(new CustomEvent('webby:form-submit:error', {
                        detail: {
                            node: node,
                            endpoint: endpoint,
                            status: error && typeof error.status === 'number' ? error.status : null,
                            error: payload,
                        },
                    }));
                    console.warn('[webby-cms-runtime] form submit failed', error);
                })
                .finally(function () {
                    button.disabled = priorDisabled;
                });
        });
    }

    function mountFormsRuntime(_payload) {
        document.querySelectorAll('[data-webby-form-submit]').forEach(function (node) {
            bindStandaloneFormSubmitWidget(node);
        });
    }

    var cartIconRuntimeBound = false;

    function cartItemCount(cart) {
        var payload = asObject(cart);
        var items = Array.isArray(payload.items) ? payload.items : [];
        return items.reduce(function (sum, item) {
            var row = asObject(item);
            var qty = parseInt(row.quantity, 10);
            return sum + (Number.isFinite(qty) ? qty : 0);
        }, 0);
    }

    function renderCartIconWidgets(cart) {
        document.querySelectorAll('[data-webby-ecommerce-cart-icon]').forEach(function (node) {
            var count = cart ? cartItemCount(cart) : 0;
            var currency = cart && cart.currency ? cart.currency : 'GEL';
            var totalValue = cart && (cart.grand_total !== undefined && cart.grand_total !== null)
                ? cart.grand_total
                : (cart && cart.subtotal !== undefined ? cart.subtotal : 0);

            if (node.tagName === 'A' && !node.getAttribute('href')) {
                node.setAttribute('href', '/cart');
            }

            var totalNode = node.querySelector('[data-webu-role="ecom-cart-icon-total"]');
            if (!totalNode) {
                totalNode = document.createElement('span');
                totalNode.setAttribute('data-webu-role', 'ecom-cart-icon-total');
                node.appendChild(totalNode);
            }
            totalNode.textContent = formatCurrencyAmount(totalValue, currency);

            var badgeNode = node.querySelector('[data-webu-role="ecom-cart-icon-badge"]');
            if (!badgeNode) {
                badgeNode = document.createElement('span');
                badgeNode.setAttribute('data-webu-role', 'ecom-cart-icon-badge');
                node.appendChild(badgeNode);
            }
            badgeNode.textContent = String(count);
            badgeNode.style.display = count > 0 ? 'inline-flex' : 'none';
        });
    }

    function bindCartIconRuntime() {
        if (cartIconRuntimeBound) {
            return;
        }
        cartIconRuntimeBound = true;

        var eventName = (config.ecommerce && config.ecommerce.events && config.ecommerce.events.cart_updated)
            ? config.ecommerce.events.cart_updated
            : 'webby:ecommerce:cart-updated';

        window.addEventListener(eventName, function (event) {
            renderCartIconWidgets(event && event.detail ? event.detail : null);
        });

        if (window.WebbyEcommerce && typeof window.WebbyEcommerce.onCartUpdated === 'function') {
            window.WebbyEcommerce.onCartUpdated(function (cart) {
                renderCartIconWidgets(cart || null);
            });

            if (typeof window.WebbyEcommerce.getCachedCart === 'function') {
                renderCartIconWidgets(window.WebbyEcommerce.getCachedCart() || null);
            } else {
                renderCartIconWidgets(null);
            }
            return;
        }

        renderCartIconWidgets(null);
    }

    function mountNavFooterRuntime(payload) {
        var data = asObject(payload);

        document.querySelectorAll('[data-webby-nav-logo]').forEach(function (node) {
            renderNavLogoWidget(node, data);
        });
        document.querySelectorAll('[data-webby-nav-menu]').forEach(function (node) {
            renderNavMenuWidget(node, data);
        });
        document.querySelectorAll('[data-webby-nav-search]').forEach(function (node) {
            bindNavSearchWidget(node);
        });
        document.querySelectorAll('[data-webby-nav-account-icon]').forEach(function (node) {
            renderNavAccountWidget(node);
        });
        document.querySelectorAll('[data-webby-footer-layout]').forEach(function (node) {
            renderFooterWidget(node, data);
        });

        bindCartIconRuntime();
    }

    var runtimeTelemetrySessionId = 'cmsrt-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);

    function telemetryEndpointUrl() {
        if (typeof cms !== 'object' || !cms) {
            return '';
        }

        var explicit = typeof cms.telemetry_url === 'string' ? cms.telemetry_url.trim() : '';
        if (explicit) {
            return explicit;
        }

        var base = String(cms.api_base_url || '').replace(/\/+$/, '');
        var siteId = cms.site_id ? String(cms.site_id) : '';
        if (!base || !siteId) {
            return '';
        }

        return base + '/public/sites/' + encodeURIComponent(siteId) + '/cms/telemetry';
    }

    function postRuntimeTelemetry(eventName, routeInfo, meta) {
        var url = telemetryEndpointUrl();
        if (!url || typeof eventName !== 'string' || eventName.trim() === '') {
            return;
        }

        var route = routeInfo && typeof routeInfo === 'object' ? routeInfo : {};
        var routeParams = normalizeRouteParamsForQuery(route.params);
        var body = {
            schema_version: 'cms.telemetry.event.v1',
            source: 'runtime',
            session_id: runtimeTelemetrySessionId,
            route: {
                path: typeof route.route_path === 'string' ? route.route_path : (window.location.pathname || '/'),
                slug: typeof route.slug === 'string' ? route.slug : null,
                params: routeParams,
            },
            context: {
                surface: 'cms_runtime',
                host: window.location.host || null,
            },
            events: [
                {
                    name: eventName.trim(),
                    at: new Date().toISOString(),
                    page_slug: typeof route.slug === 'string' ? route.slug : null,
                    meta: meta && typeof meta === 'object' ? meta : {},
                },
            ],
        };

        fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'omit',
            keepalive: true,
            body: JSON.stringify(body),
        }).catch(function () {});
    }

    function normalizeHostValue(host) {
        return String(host || '')
            .trim()
            .toLowerCase()
            .replace(/^https?:\\/\\//, '')
            .replace(/:\\d+$/, '')
            .replace(/\\/+$/, '');
    }

    function isLoopbackHost(host) {
        var normalized = normalizeHostValue(host);
        return normalized === 'localhost'
            || normalized === '127.0.0.1'
            || normalized === '0.0.0.0'
            || normalized === '::1'
            || normalized === '[::1]';
    }

    function bridgeUrl(routeInfo, locale) {
        var base;
        try {
            base = new URL(cms.bridge_path || '__cms/bootstrap', document.baseURI);
        } catch (_error) {
            return null;
        }

        var route = routeInfo && typeof routeInfo === 'object' ? routeInfo : { slug: 'home', params: {} };
        base.searchParams.set('slug', route.slug || 'home');
        if (locale) {
            base.searchParams.set('locale', locale);
        }
        var queryParams = normalizeRouteParamsForQuery(route.params);
        Object.keys(queryParams).forEach(function (key) {
            base.searchParams.set(key, queryParams[key]);
        });
        if (typeof document !== 'undefined' && document.location && document.location.search && document.location.search.indexOf('draft=1') !== -1) {
            base.searchParams.set('draft', '1');
        }

        return base.toString();
    }

    function fetchViaBridge(routeInfo, locale) {
        var url = bridgeUrl(routeInfo, locale);
        if (!url) {
            return Promise.reject(new Error('BRIDGE_URL_INVALID'));
        }

        return jsonFetch(url);
    }

    function fetchViaPublicApi(routeInfo, locale) {
        var base = String(cms.api_base_url || '').replace(/\/+$/, '');
        if (!base) {
            return Promise.reject(new Error('PUBLIC_BASE_MISSING'));
        }
        var route = routeInfo && typeof routeInfo === 'object' ? routeInfo : { slug: 'home', params: {} };
        var slug = route.slug || 'home';
        var routeParams = normalizeRouteParamsForQuery(route.params);

        var resolveUrl = cms.resolve_url || (base + '/public/sites/resolve');
        var domain = window.location.host;
        var configuredSiteId = cms.site_id ? String(cms.site_id) : '';
        var resolvePromise;
        if (configuredSiteId && isLoopbackHost(window.location.hostname)) {
            resolvePromise = Promise.resolve({ site_id: configuredSiteId });
        } else {
            resolvePromise = jsonFetch(resolveUrl + '?domain=' + encodeURIComponent(domain))
                .catch(function (error) {
                    if (configuredSiteId) {
                        return { site_id: configuredSiteId };
                    }
                    throw error;
                });
        }

        return resolvePromise
            .then(function (resolved) {
                var siteId = resolved.site_id || cms.site_id;
                if (!siteId) {
                    throw new Error('SITE_ID_MISSING');
                }

                var queryString = function (includeRouteParams) {
                    var query = new URLSearchParams();
                    if (locale) {
                        query.set('locale', locale);
                    }
                    if (includeRouteParams) {
                        Object.keys(routeParams).forEach(function (key) {
                            query.set(key, routeParams[key]);
                        });
                    }
                    var encoded = query.toString();
                    return encoded ? ('?' + encoded) : '';
                };

                var settingsUrl = base + '/public/sites/' + encodeURIComponent(siteId) + '/settings' + queryString(false);
                var typographyUrl = base + '/public/sites/' + encodeURIComponent(siteId) + '/theme/typography' + queryString(false);
                var pageUrl = base + '/public/sites/' + encodeURIComponent(siteId) + '/pages/' + encodeURIComponent(slug) + queryString(true);

                return Promise.allSettled([
                    jsonFetch(settingsUrl),
                    jsonFetch(typographyUrl),
                    jsonFetch(pageUrl),
                ]).then(function (parts) {
                    var getValue = function (part) {
                        return part && part.status === 'fulfilled' ? part.value : null;
                    };

                    var settingsData = getValue(parts[0]);
                    var typographyData = getValue(parts[1]);
                    var page = getValue(parts[2]);
                    var normalizeSite = function (settings) {
                        if (!settings) {
                            return null;
                        }

                        return {
                            id: settings.site_id || siteId,
                            name: settings.name || null,
                            locale: settings.locale || (locale || cms.default_locale || 'ka'),
                            status: settings.status || 'published',
                            primary_domain: settings.primary_domain || null,
                            subdomain: settings.subdomain || null,
                            theme_settings: settings.theme_settings || {},
                            typography: settings.typography || null,
                            updated_at: settings.updated_at || null,
                        };
                    };
                    var normalizeMenuKey = function (value, fallback) {
                        if (typeof value !== 'string') {
                            return fallback;
                        }

                        var normalized = String(value).trim().toLowerCase();
                        if (!/^[a-z0-9_-]{1,64}$/.test(normalized)) {
                            return fallback;
                        }

                        return normalized;
                    };
                    var normalizeMenu = function (menu, key) {
                        return {
                            key: key,
                            items_json: menu && Array.isArray(menu.items_json) ? menu.items_json : [],
                            updated_at: menu ? (menu.updated_at || null) : null,
                        };
                    };
                    var themeSettings = settingsData && settingsData.theme_settings && typeof settingsData.theme_settings === 'object'
                        ? settingsData.theme_settings
                        : {};
                    var layoutSettings = themeSettings.layout && typeof themeSettings.layout === 'object'
                        ? themeSettings.layout
                        : {};
                    var headerMenuKey = normalizeMenuKey(layoutSettings.header_menu_key, 'header');
                    var menuKeys = [headerMenuKey, 'header'].filter(function (value, index, source) {
                        return source.indexOf(value) === index;
                    });
                    var menuPromises = menuKeys.map(function (menuKey) {
                        var menuUrl = base + '/public/sites/' + encodeURIComponent(siteId) + '/menu/' + encodeURIComponent(menuKey) + queryString(false);
                        return jsonFetch(menuUrl)
                            .then(function (menuPayload) {
                                return normalizeMenu(menuPayload, menuKey);
                            })
                            .catch(function () {
                                return normalizeMenu(null, menuKey);
                            });
                    });

                    var buildPayload = function (pagePayload, resolvedSlug) {
                        return Promise.all(menuPromises).then(function (menuList) {
                            var menusMap = {};
                            menuList.forEach(function (menuPayload) {
                                if (!menuPayload || !menuPayload.key) {
                                    return;
                                }
                                menusMap[menuPayload.key] = menuPayload;
                            });

                            return {
                                project_id: config.projectId || null,
                                site_id: siteId,
                                resolved_domain: domain,
                                slug: resolvedSlug,
                                requested_slug: route.requested_slug || slug,
                                locale: locale || cms.default_locale || 'ka',
                                route: {
                                    slug: resolvedSlug,
                                    requested_slug: route.requested_slug || slug,
                                    locale: locale || cms.default_locale || 'ka',
                                    domain: domain,
                                    params: routeParams,
                                },
                                site: normalizeSite(settingsData),
                                typography: (typographyData && typographyData.typography) || (settingsData ? settingsData.typography : null),
                                global_settings: settingsData ? settingsData.global_settings : null,
                                menus: menusMap,
                                page: pagePayload ? pagePayload.page : null,
                                revision: pagePayload ? pagePayload.revision : null,
                                meta: {
                                    source: 'public-cms-api',
                                    generated_at: new Date().toISOString(),
                                },
                            };
                        });
                    };

                    if (!page && slug !== 'home') {
                        return jsonFetch(base + '/public/sites/' + encodeURIComponent(siteId) + '/pages/home' + queryString(true))
                            .then(function (fallbackPage) {
                                return buildPayload(fallbackPage, 'home');
                            });
                    }

                    return buildPayload(page, slug);
                });
            });
    }

    function resolveTypography(payload) {
        if (!payload || typeof payload !== 'object') {
            return null;
        }

        if (payload.typography && typeof payload.typography === 'object') {
            return payload.typography;
        }

        if (payload.site && payload.site.typography && typeof payload.site.typography === 'object') {
            return payload.site.typography;
        }

        var themeSettings = payload.site && payload.site.theme_settings && typeof payload.site.theme_settings === 'object'
            ? payload.site.theme_settings
            : null;

        if (themeSettings && themeSettings.typography && typeof themeSettings.typography === 'object') {
            return themeSettings.typography;
        }

        return null;
    }

    function sanitizeCssFontFamily(value) {
        if (typeof value !== 'string') {
            return '';
        }

        return value.replace(/["'`;{}]/g, '').trim();
    }

    function sanitizeCssFontStyle(value) {
        if (typeof value !== 'string') {
            return 'normal';
        }

        var normalized = value.trim().toLowerCase();
        if (normalized === 'italic' || normalized === 'oblique') {
            return normalized;
        }

        return 'normal';
    }

    function sanitizeCssFontDisplay(value) {
        if (typeof value !== 'string') {
            return 'swap';
        }

        var normalized = value.trim().toLowerCase();
        if (normalized === 'auto' || normalized === 'block' || normalized === 'swap' || normalized === 'fallback' || normalized === 'optional') {
            return normalized;
        }

        return 'swap';
    }

    function sanitizeCssFontWeight(value) {
        var weight = parseInt(value, 10);
        var allowed = [100, 200, 300, 400, 500, 600, 700, 800, 900];

        if (allowed.indexOf(weight) === -1) {
            return '400';
        }

        return String(weight);
    }

    function sanitizeCssFontFormat(value) {
        if (typeof value !== 'string') {
            return 'woff2';
        }

        var normalized = value.trim().toLowerCase();
        if (normalized === 'woff2' || normalized === 'woff' || normalized === 'truetype' || normalized === 'opentype') {
            return normalized;
        }

        return 'woff2';
    }

    function sanitizeCssUrl(value) {
        if (typeof value !== 'string') {
            return '';
        }

        var url = value.trim();
        if (url === '') {
            return '';
        }

        if (!/^https?:\/\//i.test(url) && url.charAt(0) !== '/') {
            return '';
        }

        return url.replace(/"/g, '%22');
    }

    function buildFontFaceRules(typography) {
        if (!typography || !Array.isArray(typography.font_faces)) {
            return '';
        }

        var rules = [];
        var dedupe = Object.create(null);

        typography.font_faces.forEach(function (face) {
            if (!face || typeof face !== 'object') {
                return;
            }

            var family = sanitizeCssFontFamily(face.font_family);
            var srcUrl = sanitizeCssUrl(face.src_url);
            if (!family || !srcUrl) {
                return;
            }

            var format = sanitizeCssFontFormat(face.format);
            var weight = sanitizeCssFontWeight(face.font_weight);
            var style = sanitizeCssFontStyle(face.font_style);
            var display = sanitizeCssFontDisplay(face.font_display);
            var signature = [family, srcUrl, format, weight, style, display].join('|');

            if (dedupe[signature]) {
                return;
            }

            dedupe[signature] = true;
            rules.push(
                '@font-face{font-family:"' + family + '";src:url("' + srcUrl + '") format("' + format + '");font-style:' + style + ';font-weight:' + weight + ';font-display:' + display + ';}'
            );
        });

        return rules.join('');
    }

    function applyTypography(payload) {
        var typography = resolveTypography(payload);
        if (!typography) {
            return;
        }

        var baseStack = typeof typography.font_stack === 'string' && typography.font_stack.trim() !== ''
            ? typography.font_stack.trim()
            : null;
        var headingStack = typeof typography.heading_font_stack === 'string' && typography.heading_font_stack.trim() !== ''
            ? typography.heading_font_stack.trim()
            : baseStack;
        var bodyStack = typeof typography.body_font_stack === 'string' && typography.body_font_stack.trim() !== ''
            ? typography.body_font_stack.trim()
            : baseStack;
        var buttonStack = typeof typography.button_font_stack === 'string' && typography.button_font_stack.trim() !== ''
            ? typography.button_font_stack.trim()
            : (bodyStack || baseStack);

        if (!baseStack) {
            return;
        }

        var root = document.documentElement;
        root.style.setProperty('--webby-font-base', baseStack);
        root.style.setProperty('--webby-font-heading', headingStack || baseStack);
        root.style.setProperty('--webby-font-body', bodyStack || baseStack);
        root.style.setProperty('--webby-font-button', buttonStack || bodyStack || baseStack);

        var styleTag = document.getElementById('webby-cms-typography-style');
        if (!styleTag) {
            styleTag = document.createElement('style');
            styleTag.id = 'webby-cms-typography-style';
            document.head.appendChild(styleTag);
        }

        styleTag.textContent = buildFontFaceRules(typography)
            + 'html, body { font-family: var(--webby-font-body, var(--webby-font-base)) !important; }'
            + 'h1, h2, h3, h4, h5, h6, [data-webby-typography="heading"] { font-family: var(--webby-font-heading, var(--webby-font-base)) !important; }'
            + 'p, span, li, a, input, textarea, select, label, [data-webby-typography="body"] { font-family: var(--webby-font-body, var(--webby-font-base)) !important; }'
            + 'button, input[type="button"], input[type="submit"], input[type="reset"], [data-webby-typography="button"] { font-family: var(--webby-font-button, var(--webby-font-body, var(--webby-font-base))) !important; }';
    }

    function cmsPublicBaseUrl() {
        var base = String(cms.api_base_url || '').replace(/\/+$/, '');
        var siteId = cms.site_id ? String(cms.site_id) : '';
        if (!base || !siteId) {
            return '';
        }

        return base + '/public/sites/' + encodeURIComponent(siteId);
    }

    function cmsPublicJson(path) {
        var base = cmsPublicBaseUrl();
        if (!base) {
            return Promise.reject(new Error('CMS_PUBLIC_BASE_MISSING'));
        }

        return jsonFetch(base + path);
    }

    function cmsPublicJsonPost(path, payload) {
        var base = cmsPublicBaseUrl();
        if (!base) {
            return Promise.reject(new Error('CMS_PUBLIC_BASE_MISSING'));
        }

        return jsonPost(base + path, payload);
    }

    function cmsPublicJsonPut(path, payload) {
        var base = cmsPublicBaseUrl();
        if (!base) {
            return Promise.reject(new Error('CMS_PUBLIC_BASE_MISSING'));
        }

        return fetch(base + path, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload || {}),
        }).then(function (response) {
            return response.text().then(function (raw) {
                var parsed = {};
                if (raw && raw.trim() !== '') {
                    try {
                        parsed = JSON.parse(raw);
                    } catch (_error) {
                        parsed = { raw: raw };
                    }
                }

                if (!response.ok) {
                    var error = new Error('HTTP_' + response.status);
                    error.status = response.status;
                    error.payload = parsed;
                    throw error;
                }

                return parsed;
            });
        });
    }

    function routeParamValue(name, fallback) {
        var state = window.__WEBBY_CMS__ || null;
        var params = state && state.route && state.route.params ? state.route.params : {};
        if (params && Object.prototype.hasOwnProperty.call(params, name) && params[name]) {
            return String(params[name]);
        }

        return fallback || '';
    }

    function setRuntimeError(container, error, fallbackMessage) {
        if (!container) {
            return;
        }

        var message = (error && (error.message || (error.payload && error.payload.error))) || fallbackMessage || 'Request failed';
        container.innerHTML = '<div style="font-size:13px;color:#b91c1c;">' + escapeHtml(message) + '</div>';
    }

    function renderSimpleRows(container, rows, formatter, emptyText) {
        if (!container) {
            return;
        }

        var items = Array.isArray(rows) ? rows : [];
        if (items.length === 0) {
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">' + escapeHtml(emptyText || 'No items') + '</div>';
            return;
        }

        container.innerHTML = items.map(function (row) {
            return formatter(row);
        }).join('');
    }

    function initVerticalPublicHelpers() {
        var portfolio_selector = '[data-webby-portfolio-projects]';
        var portfolioDetailSelector = '[data-webby-portfolio-project-detail]';
        var portfolioGallerySelector = '[data-webby-portfolio-gallery]';
        var blog_selector = '[data-webby-blog-post-list]';
        var blogPostDetailSelector = '[data-webby-blog-post-detail]';
        var blogCategorySelector = '[data-webby-blog-category-list]';
        var realestate_selector = '[data-webby-realestate-properties]';
        var realestateDetailSelector = '[data-webby-realestate-property-detail]';
        var realestateMapSelector = '[data-webby-realestate-map]';
        var restaurant_selector = '[data-webby-restaurant-menu-items]';
        var restaurantCategoriesSelector = '[data-webby-restaurant-menu-categories]';
        var restaurantReservationSelector = '[data-webby-restaurant-reservation-form]';
        var hotel_selector = '[data-webby-hotel-rooms]';
        var hotelRoomSelector = '[data-webby-hotel-room]';
        var hotelAvailabilitySelector = '[data-webby-hotel-availability]';
        var hotelReservationSelector = '[data-webby-hotel-reservation-form]';

        function mountPortfolioProjectsWidget(container) {
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading portfolio...</div>';
            cmsPublicJson('/portfolio').then(function (payload) {
                renderSimpleRows(container, payload && payload.items, function (row) {
                    return '<div data-webby-portfolio-runtime style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;">'
                        + '<div style="font-weight:600;">' + escapeHtml(row.title || row.slug || '') + '</div>'
                        + '<div style="font-size:12px;color:#64748b;">' + escapeHtml(row.excerpt || '') + '</div>'
                        + '</div>';
                }, 'No portfolio projects');
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load portfolio');
            });
        }

        function mountPortfolioProjectDetailWidget(container) {
            var slug = container.getAttribute('data-webby-portfolio-slug') || routeParamValue('slug');
            if (!slug) {
                container.innerHTML = '<div style="font-size:13px;color:#64748b;">Missing portfolio slug.</div>';
                return;
            }

            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading project...</div>';
            cmsPublicJson('/portfolio/' + encodeURIComponent(slug)).then(function (payload) {
                var item = asObject(payload && payload.item);
                container.innerHTML = '<div data-webby-portfolio-runtime style="padding:12px;border:1px solid #e2e8f0;border-radius:12px;">'
                    + '<h3 style="margin:0 0 6px 0;">' + escapeHtml(item.title || slug) + '</h3>'
                    + '<div style="font-size:13px;color:#475569;">' + escapeHtml(item.excerpt || '') + '</div>'
                    + '</div>';
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load project');
            });
        }

        function mountPortfolioGalleryWidget(container, options) {
            var widgetOptions = asObject(options);
            var slug = widgetOptions.slug || container.getAttribute('data-webby-portfolio-slug') || routeParamValue('slug');
            if (!slug) {
                container.innerHTML = '<div style="font-size:13px;color:#64748b;">Missing portfolio slug.</div>';
                return;
            }

            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading gallery...</div>';
            cmsPublicJson('/portfolio/' + encodeURIComponent(slug)).then(function (payload) {
                var item = asObject(payload && payload.item);
                var images = Array.isArray(item.images) ? item.images : [];
                if (images.length === 0) {
                    container.innerHTML = '<div style="font-size:13px;color:#64748b;">No gallery images</div>';
                    return;
                }

                var rawLayout = String(widgetOptions.layout || container.getAttribute('data-webby-portfolio-gallery-layout') || 'masonry').trim().toLowerCase();
                var layout = rawLayout === 'slider' ? 'slider' : 'masonry';
                var lightboxValue = widgetOptions.lightbox;
                if (lightboxValue === undefined || lightboxValue === null || lightboxValue === '') {
                    lightboxValue = container.getAttribute('data-webby-portfolio-gallery-lightbox');
                }
                if (rawLayout === 'lightbox') {
                    lightboxValue = true;
                }
                var lightboxEnabled = false;
                if (typeof lightboxValue === 'boolean') {
                    lightboxEnabled = lightboxValue;
                } else if (lightboxValue !== undefined && lightboxValue !== null) {
                    var normalizedLightbox = String(lightboxValue).trim().toLowerCase();
                    lightboxEnabled = normalizedLightbox === '1' || normalizedLightbox === 'true' || normalizedLightbox === 'yes' || normalizedLightbox === 'on';
                }

                var wrapperAttr = layout === 'slider' ? 'data-webby-portfolio-gallery-slider' : 'data-webby-portfolio-gallery-grid';
                var wrapperStyle = layout === 'slider'
                    ? 'display:flex;overflow:auto;gap:8px;padding:4px 0;scroll-snap-type:x mandatory;'
                    : 'display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;';
                var itemStyle = layout === 'slider'
                    ? 'display:flex;align-items:center;justify-content:center;min-width:180px;height:96px;flex:0 0 auto;scroll-snap-align:start;border:1px dashed #cbd5e1;border-radius:10px;padding:6px;font-size:12px;color:#64748b;background:#f8fafc;'
                    : 'display:flex;align-items:center;justify-content:center;min-width:110px;height:80px;border:1px dashed #cbd5e1;border-radius:10px;padding:6px;font-size:12px;color:#64748b;background:#f8fafc;';
                var itemsHtml = images.map(function (row, index) {
                    var mediaId = escapeHtml(row && row.media_id ? row.media_id : String(index + 1));
                    var triggerAttrs = lightboxEnabled
                        ? (' data-webby-portfolio-gallery-lightbox-trigger data-media-id="' + mediaId + '"')
                        : '';
                    return '<button type="button"' + triggerAttrs + ' style="' + itemStyle + '">'
                        + 'Media #' + mediaId + '</button>';
                }).join('');
                var lightboxHtml = lightboxEnabled
                    ? ''
                        + '<div data-webby-portfolio-gallery-lightbox style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);z-index:9999;padding:20px;">'
                        + '<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:14px;">'
                        + '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">'
                        + '<strong style="font-size:14px;">Portfolio Media</strong>'
                        + '<button type="button" data-webby-portfolio-lightbox-close style="border:1px solid #cbd5e1;border-radius:8px;padding:4px 8px;background:#fff;">Close</button>'
                        + '</div>'
                        + '<div data-webby-portfolio-lightbox-body style="margin-top:12px;font-size:13px;color:#475569;">Select an item</div>'
                        + '</div>'
                        + '</div>'
                    : '';

                container.innerHTML = '<div ' + wrapperAttr + ' data-webby-portfolio-runtime style="' + wrapperStyle + '">' + itemsHtml + '</div>' + lightboxHtml;

                if (!lightboxEnabled) {
                    return;
                }

                var overlay = container.querySelector('[data-webby-portfolio-gallery-lightbox]');
                var overlayBody = container.querySelector('[data-webby-portfolio-lightbox-body]');
                var overlayClose = container.querySelector('[data-webby-portfolio-lightbox-close]');

                if (overlayClose && overlay) {
                    overlayClose.addEventListener('click', function () {
                        overlay.style.display = 'none';
                    });
                }

                container.querySelectorAll('[data-webby-portfolio-gallery-lightbox-trigger]').forEach(function (node) {
                    node.addEventListener('click', function () {
                        if (!overlay || !overlayBody) {
                            return;
                        }
                        var mediaId = node.getAttribute('data-media-id') || '';
                        overlayBody.textContent = 'Lightbox preview for media #' + mediaId;
                        overlay.style.display = 'block';
                    });
                });
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load gallery');
            });
        }

        function mountBlogPostListWidget(container) {
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading posts...</div>';
            cmsPublicJson('/posts').then(function (payload) {
                renderSimpleRows(container, payload && payload.items, function (row) {
                    return '<div data-webby-blog-runtime style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;">'
                        + '<div style="font-weight:600;">' + escapeHtml(row.title || row.slug || '') + '</div>'
                        + '<div style="font-size:12px;color:#64748b;">' + escapeHtml(row.excerpt || '') + '</div>'
                        + '</div>';
                }, 'No posts');
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load posts');
            });
        }

        function mountBlogPostDetailWidget(container) {
            var slug = container.getAttribute('data-webby-blog-post-slug') || routeParamValue('slug');
            if (!slug) {
                container.innerHTML = '<div style="font-size:13px;color:#64748b;">Missing post slug.</div>';
                return;
            }

            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading post...</div>';
            cmsPublicJson('/posts/' + encodeURIComponent(slug)).then(function (payload) {
                var post = asObject(payload && payload.post);
                container.innerHTML = '<article data-webby-blog-runtime style="padding:12px;border:1px solid #e2e8f0;border-radius:12px;">'
                    + '<h3 style="margin:0 0 6px 0;">' + escapeHtml(post.title || slug) + '</h3>'
                    + '<div style="font-size:13px;color:#475569;">' + escapeHtml(post.excerpt || '') + '</div>'
                    + '</article>';
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load post');
            });
        }

        function mountBlogCategoryListWidget(container) {
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading categories...</div>';
            cmsPublicJson('/post-categories').then(function (payload) {
                renderSimpleRows(container, payload && payload.items, function (row) {
                    return '<div data-webby-blog-runtime style="display:inline-flex;border:1px solid #e2e8f0;border-radius:999px;padding:6px 10px;margin:4px;font-size:12px;">'
                        + escapeHtml((row.name || row.slug || '') + ' (' + (row.posts_count || 0) + ')') + '</div>';
                }, 'No categories');
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load categories');
            });
        }

        function realEstateListProperties(params) {
            var query = new URLSearchParams();
            var data = asObject(params);
            Object.keys(data).forEach(function (key) {
                if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                    query.set(key, String(data[key]));
                }
            });
            return cmsPublicJson('/properties' + (query.toString() ? ('?' + query.toString()) : ''));
        }

        function normalizeRealEstateMapProvider(value) {
            var provider = String(value || '').trim().toLowerCase();
            return provider === 'mapbox' ? 'mapbox' : 'google';
        }

        function mountRealEstatePropertiesWidget(container, options) {
            var widgetOptions = asObject(options);
            var filters = {};
            ['q', 'min_price', 'max_price', 'limit'].forEach(function (key) {
                if (widgetOptions[key] !== undefined && widgetOptions[key] !== null && widgetOptions[key] !== '') {
                    filters[key] = widgetOptions[key];
                    return;
                }
                var attr = container.getAttribute('data-webby-realestate-' + key.replace(/_/g, '-'));
                if (attr !== null && attr !== '') {
                    filters[key] = attr;
                }
            });
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading properties...</div>';
            realEstateListProperties(filters).then(function (payload) {
                renderSimpleRows(container, payload && payload.items, function (row) {
                    return '<div data-webby-realestate-runtime data-webby-realestate-row style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;">'
                        + '<div style="font-weight:600;">' + escapeHtml(row.title || row.slug || '') + '</div>'
                        + '<div style="font-size:12px;color:#64748b;">' + escapeHtml(row.location_text || '') + '</div>'
                        + '<div style="font-size:12px;color:#0f172a;">' + escapeHtml(formatCurrencyAmount(row.price || 0, row.currency || 'USD')) + '</div>'
                        + '</div>';
                }, 'No properties');
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load properties');
            });
        }

        function mountRealEstatePropertyDetailWidget(container) {
            var slug = container.getAttribute('data-webby-realestate-slug') || routeParamValue('slug');
            if (!slug) {
                container.innerHTML = '<div style="font-size:13px;color:#64748b;">Missing property slug.</div>';
                return;
            }

            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading property...</div>';
            cmsPublicJson('/properties/' + encodeURIComponent(slug)).then(function (payload) {
                var property = asObject(payload && payload.property);
                container.innerHTML = '<div style="padding:12px;border:1px solid #e2e8f0;border-radius:12px;">'
                    + '<h3 style="margin:0 0 6px 0;">' + escapeHtml(property.title || slug) + '</h3>'
                    + '<div style="font-size:12px;color:#64748b;">' + escapeHtml(property.location_text || '') + '</div>'
                    + '<div style="font-size:13px;color:#0f172a;margin-top:6px;">' + escapeHtml(formatCurrencyAmount(property.price || 0, property.currency || 'USD')) + '</div>'
                    + '</div>';
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load property');
            });
        }

        function mountRealEstateMapWidget(container, options) {
            var widgetOptions = asObject(options);
            var providerValue = widgetOptions.provider;
            if (providerValue === undefined || providerValue === null || providerValue === '') {
                providerValue = container.getAttribute('data-webby-realestate-map-provider');
            }
            var provider = normalizeRealEstateMapProvider(providerValue || 'google');
            var filters = {};
            ['q', 'min_price', 'max_price', 'limit'].forEach(function (key) {
                if (widgetOptions[key] !== undefined && widgetOptions[key] !== null && widgetOptions[key] !== '') {
                    filters[key] = widgetOptions[key];
                    return;
                }
                var attr = container.getAttribute('data-webby-realestate-' + key.replace(/_/g, '-'));
                if (attr !== null && attr !== '') {
                    filters[key] = attr;
                }
            });
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading map markers...</div>';
            realEstateListProperties(filters).then(function (payload) {
                var items = Array.isArray(payload && payload.items) ? payload.items : [];
                if (items.length === 0) {
                    container.innerHTML = '<div data-webby-realestate-map-provider data-provider="' + escapeHtml(provider) + '" style="font-size:13px;color:#64748b;">No map markers</div>';
                    return;
                }
                var providerLabel = '<div data-webby-realestate-map-provider data-provider="' + escapeHtml(provider) + '" style="font-size:12px;color:#475569;margin-bottom:8px;">Map provider: ' + escapeHtml(provider) + '</div>';
                var rowsHtml = items.map(function (row) {
                    var lat = row && row.lat !== undefined && row.lat !== null ? row.lat : '-';
                    var lng = row && row.lng !== undefined && row.lng !== null ? row.lng : '-';
                    return '<div data-webby-realestate-map-marker data-provider="' + escapeHtml(provider) + '" data-lat="' + escapeHtml(lat) + '" data-lng="' + escapeHtml(lng) + '" style="padding:8px 10px;border:1px dashed #cbd5e1;border-radius:10px;margin-bottom:8px;font-size:12px;">'
                        + escapeHtml((row.title || row.slug || 'Property') + ' • ' + (lat + ', ' + lng)) + '</div>';
                }).join('');
                container.innerHTML = '<div data-webby-realestate-runtime data-webby-realestate-map-runtime>' + providerLabel + rowsHtml + '</div>';
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load map markers');
            });
        }

        function restaurantListMenuItems(params) {
            var query = new URLSearchParams();
            var data = asObject(params);
            ['category', 'category_id'].forEach(function (key) {
                if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                    query.set(key, String(data[key]));
                }
            });
            return cmsPublicJson('/restaurant/menu/items' + (query.toString() ? ('?' + query.toString()) : ''));
        }

        function mountRestaurantMenuCategoriesWidget(container) {
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading menu categories...</div>';
            cmsPublicJson('/restaurant/menu').then(function (payload) {
                renderSimpleRows(container, payload && payload.categories, function (row) {
                    return '<div style="display:inline-flex;border:1px solid #e2e8f0;border-radius:999px;padding:6px 10px;margin:4px;font-size:12px;">'
                        + escapeHtml(row.name || '') + '</div>';
                }, 'No categories');
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load menu categories');
            });
        }

        function mountRestaurantMenuItemsWidget(container, options) {
            var widgetOptions = asObject(options);
            var filters = {};
            ['category', 'category_id'].forEach(function (key) {
                if (widgetOptions[key] !== undefined && widgetOptions[key] !== null && widgetOptions[key] !== '') {
                    filters[key] = widgetOptions[key];
                    return;
                }
                var attr = container.getAttribute('data-webby-restaurant-' + key.replace(/_/g, '-'));
                if (attr !== null && attr !== '') {
                    filters[key] = attr;
                }
            });
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading menu items...</div>';
            restaurantListMenuItems(filters).then(function (payload) {
                renderSimpleRows(container, payload && payload.items, function (row) {
                    return '<div style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;">'
                        + '<div style="font-weight:600;">' + escapeHtml(row.name || '') + '</div>'
                        + '<div style="font-size:12px;color:#64748b;">' + escapeHtml(row.category_name || '') + '</div>'
                        + '<div style="font-size:12px;color:#0f172a;">' + escapeHtml((row.price || '') + ' ' + (row.currency || '')) + '</div>'
                        + '</div>';
                }, 'No menu items');
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load menu items');
            });
        }

        function mountRestaurantReservationWidget(container) {
            container.innerHTML = ''
                + '<div style="display:grid;gap:8px;">'
                + '<input data-webby-restaurant-name placeholder="Name" style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;" />'
                + '<input data-webby-restaurant-phone placeholder="Phone" style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;" />'
                + '<input data-webby-restaurant-email placeholder="Email" style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;" />'
                + '<input data-webby-restaurant-guests type="number" min="1" value="2" style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;" />'
                + '<input data-webby-restaurant-starts-at type="datetime-local" style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;" />'
                + '<button type="button" data-webby-restaurant-submit style="height:38px;border:0;border-radius:8px;background:#0f172a;color:#fff;cursor:pointer;">Reserve Table</button>'
                + '<div data-webby-restaurant-message style="min-height:18px;font-size:12px;color:#64748b;"></div>'
                + '</div>';

            var button = container.querySelector('[data-webby-restaurant-submit]');
            var message = container.querySelector('[data-webby-restaurant-message]');
            if (!button) {
                return;
            }

            button.addEventListener('click', function () {
                var payload = {
                    customer_name: (container.querySelector('[data-webby-restaurant-name]') || {}).value || '',
                    phone: (container.querySelector('[data-webby-restaurant-phone]') || {}).value || '',
                    email: (container.querySelector('[data-webby-restaurant-email]') || {}).value || '',
                    guests: Number.parseInt(((container.querySelector('[data-webby-restaurant-guests]') || {}).value || '2'), 10),
                    starts_at: (container.querySelector('[data-webby-restaurant-starts-at]') || {}).value || '',
                };
                if (message) {
                    message.textContent = 'Submitting...';
                    message.style.color = '#64748b';
                }
                cmsPublicJsonPost('/restaurant/reservations', payload).then(function (response) {
                    if (message) {
                        var reservationId = response && response.reservation ? response.reservation.id : '';
                        message.textContent = 'Reservation created #' + escapeHtml(reservationId);
                        message.style.color = '#0f766e';
                    }
                }).catch(function (error) {
                    if (message) {
                        message.textContent = ((error && error.payload && error.payload.error) || (error && error.message) || 'Reservation failed');
                        message.style.color = '#b91c1c';
                    }
                });
            });
        }

        function hotelListRooms(params) {
            var query = new URLSearchParams();
            var data = asObject(params);
            ['q', 'capacity', 'limit'].forEach(function (key) {
                if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                    query.set(key, String(data[key]));
                }
            });
            return cmsPublicJson('/rooms' + (query.toString() ? ('?' + query.toString()) : ''));
        }

        function mountHotelRoomsWidget(container, options) {
            var widgetOptions = asObject(options);
            var filters = {};
            ['q', 'capacity', 'limit'].forEach(function (key) {
                if (widgetOptions[key] !== undefined && widgetOptions[key] !== null && widgetOptions[key] !== '') {
                    filters[key] = widgetOptions[key];
                    return;
                }
                var attr = container.getAttribute('data-webby-hotel-' + key.replace(/_/g, '-'));
                if (attr !== null && attr !== '') {
                    filters[key] = attr;
                }
            });
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading rooms...</div>';
            hotelListRooms(filters).then(function (payload) {
                renderSimpleRows(container, payload && payload.rooms, function (row) {
                    return '<div style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;">'
                        + '<div style="font-weight:600;">' + escapeHtml(row.name || '') + '</div>'
                        + '<div style="font-size:12px;color:#64748b;">' + escapeHtml((row.room_type || '') + ' • capacity ' + (row.capacity || '')) + '</div>'
                        + '<div style="font-size:12px;color:#0f172a;">' + escapeHtml((row.price_per_night || '') + ' ' + (row.currency || '')) + '</div>'
                        + '</div>';
                }, 'No rooms');
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load rooms');
            });
        }

        function mountHotelRoomWidget(container) {
            var roomId = container.getAttribute('data-webby-hotel-room-id') || routeParamValue('id');
            if (!roomId) {
                container.innerHTML = '<div style="font-size:13px;color:#64748b;">Missing room id.</div>';
                return;
            }

            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading room...</div>';
            cmsPublicJson('/rooms/' + encodeURIComponent(roomId)).then(function (payload) {
                var room = asObject(payload && payload.room);
                container.innerHTML = '<div style="padding:12px;border:1px solid #e2e8f0;border-radius:12px;">'
                    + '<h3 style="margin:0 0 6px 0;">' + escapeHtml(room.name || ('Room #' + roomId)) + '</h3>'
                    + '<div style="font-size:12px;color:#64748b;">' + escapeHtml((room.room_type || '') + ' • capacity ' + (room.capacity || '')) + '</div>'
                    + '</div>';
            }).catch(function (error) {
                setRuntimeError(container, error, 'Failed to load room');
            });
        }

        function mountHotelAvailabilityWidget(container) {
            container.innerHTML = '<div style="font-size:13px;color:#64748b;">Availability uses room and reservation APIs.</div>';
        }

        function mountHotelReservationWidget(container) {
            container.innerHTML = ''
                + '<div style="display:grid;gap:8px;">'
                + '<input data-webby-hotel-room-id-input placeholder="Room ID" style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;" />'
                + '<input data-webby-hotel-checkin type="date" style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;" />'
                + '<input data-webby-hotel-checkout type="date" style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;" />'
                + '<button type="button" data-webby-hotel-submit style="height:38px;border:0;border-radius:8px;background:#0f172a;color:#fff;cursor:pointer;">Reserve Room</button>'
                + '<div data-webby-hotel-message style="min-height:18px;font-size:12px;color:#64748b;"></div>'
                + '</div>';

            var button = container.querySelector('[data-webby-hotel-submit]');
            var message = container.querySelector('[data-webby-hotel-message]');
            if (!button) {
                return;
            }

            button.addEventListener('click', function () {
                var payload = {
                    room_id: Number.parseInt(((container.querySelector('[data-webby-hotel-room-id-input]') || {}).value || '0'), 10),
                    checkin_date: (container.querySelector('[data-webby-hotel-checkin]') || {}).value || '',
                    checkout_date: (container.querySelector('[data-webby-hotel-checkout]') || {}).value || '',
                };
                if (message) {
                    message.textContent = 'Submitting...';
                    message.style.color = '#64748b';
                }
                cmsPublicJsonPost('/room-reservations', payload).then(function (response) {
                    if (message) {
                        var reservationId = response && response.reservation ? response.reservation.id : '';
                        message.textContent = 'Room reservation created #' + escapeHtml(reservationId);
                        message.style.color = '#0f766e';
                    }
                }).catch(function (error) {
                    if (message) {
                        message.textContent = ((error && error.payload && error.payload.error) || (error && error.message) || 'Reservation failed');
                        message.style.color = '#b91c1c';
                    }
                });
            });
        }

        window.WebbyPortfolio = window.WebbyPortfolio || {};
        window.WebbyPortfolio.listProjects = function () { return cmsPublicJson('/portfolio'); };
        window.WebbyPortfolio.getProject = function (slug) { return cmsPublicJson('/portfolio/' + encodeURIComponent(String(slug || ''))); };
        window.WebbyPortfolio.mountProjectsWidget = mountPortfolioProjectsWidget;
        window.WebbyPortfolio.mountProjectDetailWidget = mountPortfolioProjectDetailWidget;
        window.WebbyPortfolio.mountGalleryWidget = mountPortfolioGalleryWidget;

        window.WebbyBlog = window.WebbyBlog || {};
        window.WebbyBlog.listPosts = function (params) {
            var query = new URLSearchParams();
            var data = asObject(params);
            Object.keys(data).forEach(function (key) { if (data[key] !== undefined && data[key] !== null && data[key] !== '') { query.set(key, String(data[key])); } });
            return cmsPublicJson('/posts' + (query.toString() ? ('?' + query.toString()) : ''));
        };
        window.WebbyBlog.getPost = function (slug) { return cmsPublicJson('/posts/' + encodeURIComponent(String(slug || ''))); };
        window.WebbyBlog.listCategories = function () { return cmsPublicJson('/post-categories'); };
        window.WebbyBlog.mountPostListWidget = mountBlogPostListWidget;
        window.WebbyBlog.mountPostDetailWidget = mountBlogPostDetailWidget;
        window.WebbyBlog.mountCategoryListWidget = mountBlogCategoryListWidget;

        window.WebbyRealEstate = window.WebbyRealEstate || {};
        window.WebbyRealEstate.listProperties = function (params) { return realEstateListProperties(params); };
        window.WebbyRealEstate.getProperty = function (slug) { return cmsPublicJson('/properties/' + encodeURIComponent(String(slug || ''))); };
        window.WebbyRealEstate.mountPropertiesWidget = mountRealEstatePropertiesWidget;
        window.WebbyRealEstate.mountPropertyDetailWidget = mountRealEstatePropertyDetailWidget;
        window.WebbyRealEstate.mountMapWidget = mountRealEstateMapWidget;

        window.WebbyRestaurant = window.WebbyRestaurant || {};
        window.WebbyRestaurant.listMenuCategories = function () { return cmsPublicJson('/restaurant/menu'); };
        window.WebbyRestaurant.listMenuItems = function (params) { return restaurantListMenuItems(params); };
        window.WebbyRestaurant.createReservation = function (payload) { return cmsPublicJsonPost('/restaurant/reservations', payload); };
        window.WebbyRestaurant.mountMenuCategoriesWidget = mountRestaurantMenuCategoriesWidget;
        window.WebbyRestaurant.mountMenuItemsWidget = mountRestaurantMenuItemsWidget;
        window.WebbyRestaurant.mountReservationFormWidget = mountRestaurantReservationWidget;

        window.WebbyHotel = window.WebbyHotel || {};
        window.WebbyHotel.listRooms = function (params) { return hotelListRooms(params); };
        window.WebbyHotel.getRoom = function (id) { return cmsPublicJson('/rooms/' + encodeURIComponent(String(id || ''))); };
        window.WebbyHotel.createReservation = function (payload) { return cmsPublicJsonPost('/room-reservations', payload); };
        window.WebbyHotel.mountRoomsWidget = mountHotelRoomsWidget;
        window.WebbyHotel.mountRoomWidget = mountHotelRoomWidget;
        window.WebbyHotel.mountAvailabilityWidget = mountHotelAvailabilityWidget;
        window.WebbyHotel.mountReservationFormWidget = mountHotelReservationWidget;

        if (window.WebbyEcommerce && typeof window.WebbyEcommerce === 'object') {
            function customersMe() {
                return cmsPublicJson('/customers/me');
            }

            function customerLogin(payload) {
                return cmsPublicJsonPost('/customers/login', payload);
            }

            function customerMeUpdate(payload) {
                return cmsPublicJsonPut('/customers/me', payload);
            }

            function customerOtpRequest(payload) {
                return cmsPublicJsonPost('/auth/otp/request', payload);
            }

            function customerOtpVerify(payload) {
                return cmsPublicJsonPost('/auth/otp/verify', payload);
            }

            function customerSocialAuthStart(provider, payload) {
                return cmsPublicJsonPost('/auth/' + encodeURIComponent(String(provider || '')), payload);
            }

            function mountAuthWidget(container) {
                if (!container) { return; }
                container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading auth...</div>';
                customersMe().then(function (payload) {
                    var authenticated = !!(payload && payload.authenticated);
                    var customer = payload && payload.customer ? payload.customer : null;
                    container.innerHTML = authenticated
                        ? '<div style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;">Signed in as ' + escapeHtml(customer && customer.email ? customer.email : 'customer') + '</div>'
                        : '<div style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;">Guest mode. <a href="/login">Login</a></div>';
                }).catch(function (error) {
                    setRuntimeError(container, error, 'Failed to load auth state');
                });
            }

            function mountAccountProfileWidget(container) {
                if (!container) { return; }
                container.innerHTML = '<div style="font-size:13px;color:#64748b;">Loading profile...</div>';
                customersMe().then(function (payload) {
                    var customer = payload && payload.customer ? payload.customer : null;
                    container.innerHTML = '<div style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;">'
                        + '<div style="font-weight:600;">' + escapeHtml(customer && customer.name ? customer.name : 'Guest') + '</div>'
                        + '<div style="font-size:12px;color:#64748b;">' + escapeHtml(customer && customer.email ? customer.email : '') + '</div>'
                        + '</div>';
                }).catch(function (error) {
                    setRuntimeError(container, error, 'Failed to load profile');
                });
            }

            function mountAccountSecurityWidget(container) {
                if (!container) { return; }
                container.innerHTML = '<div style="padding:10px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;color:#475569;">Account security settings follow platform auth/session routes.</div>';
            }

            window.WebbyEcommerce.mountAuthWidget = mountAuthWidget;
            window.WebbyEcommerce.mountAccountProfileWidget = mountAccountProfileWidget;
            window.WebbyEcommerce.mountAccountSecurityWidget = mountAccountSecurityWidget;
            window.WebbyEcommerce.getCustomerMe = customersMe;
            window.WebbyEcommerce.loginCustomer = customerLogin;
            window.WebbyEcommerce.updateCustomerMe = customerMeUpdate;
            window.WebbyEcommerce.requestOtp = customerOtpRequest;
            window.WebbyEcommerce.verifyOtp = customerOtpVerify;
            window.WebbyEcommerce.startGoogleAuth = function (payload) { return customerSocialAuthStart('google', payload); };
            window.WebbyEcommerce.startFacebookAuth = function (payload) { return customerSocialAuthStart('facebook', payload); };
        }

        return {
            mountAll: function () {
                document.querySelectorAll(portfolio_selector).forEach(function (node) { mountPortfolioProjectsWidget(node); });
                document.querySelectorAll(portfolioDetailSelector).forEach(function (node) { mountPortfolioProjectDetailWidget(node); });
                document.querySelectorAll(portfolioGallerySelector).forEach(function (node) { mountPortfolioGalleryWidget(node); });
                document.querySelectorAll(blog_selector).forEach(function (node) { mountBlogPostListWidget(node); });
                document.querySelectorAll(blogPostDetailSelector).forEach(function (node) { mountBlogPostDetailWidget(node); });
                document.querySelectorAll(blogCategorySelector).forEach(function (node) { mountBlogCategoryListWidget(node); });
                document.querySelectorAll(realestate_selector).forEach(function (node) { mountRealEstatePropertiesWidget(node); });
                document.querySelectorAll(realestateDetailSelector).forEach(function (node) { mountRealEstatePropertyDetailWidget(node); });
                document.querySelectorAll(realestateMapSelector).forEach(function (node) { mountRealEstateMapWidget(node); });
                document.querySelectorAll(restaurantCategoriesSelector).forEach(function (node) { mountRestaurantMenuCategoriesWidget(node); });
                document.querySelectorAll(restaurant_selector).forEach(function (node) { mountRestaurantMenuItemsWidget(node); });
                document.querySelectorAll(restaurantReservationSelector).forEach(function (node) { mountRestaurantReservationWidget(node); });
                document.querySelectorAll(hotel_selector).forEach(function (node) { mountHotelRoomsWidget(node); });
                document.querySelectorAll(hotelRoomSelector).forEach(function (node) { mountHotelRoomWidget(node); });
                document.querySelectorAll(hotelAvailabilitySelector).forEach(function (node) { mountHotelAvailabilityWidget(node); });
                document.querySelectorAll(hotelReservationSelector).forEach(function (node) { mountHotelReservationWidget(node); });
                document.querySelectorAll('[data-webby-ecommerce-auth]').forEach(function (node) {
                    if (window.WebbyEcommerce && typeof window.WebbyEcommerce.mountAuthWidget === 'function') {
                        window.WebbyEcommerce.mountAuthWidget(node);
                    }
                });
                document.querySelectorAll('[data-webby-ecommerce-account-profile]').forEach(function (node) {
                    if (window.WebbyEcommerce && typeof window.WebbyEcommerce.mountAccountProfileWidget === 'function') {
                        window.WebbyEcommerce.mountAccountProfileWidget(node);
                    }
                });
                document.querySelectorAll('[data-webby-ecommerce-account-security]').forEach(function (node) {
                    if (window.WebbyEcommerce && typeof window.WebbyEcommerce.mountAccountSecurityWidget === 'function') {
                        window.WebbyEcommerce.mountAccountSecurityWidget(node);
                    }
                });
            },
        };
    }

    var extendedRuntime = initVerticalPublicHelpers();

    function mountExtendedRuntimeWidgets() {
        if (extendedRuntime && typeof extendedRuntime.mountAll === 'function') {
            extendedRuntime.mountAll();
        }
    }

    function emit(payload) {
        applyTypography(payload);
        window.__WEBBY_CMS__ = payload;
        mountNavFooterRuntime(payload);
        mountFormsRuntime(payload);
        mountExtendedRuntimeWidgets();
        window.dispatchEvent(new CustomEvent('webby:cms-ready', { detail: payload }));
        document.dispatchEvent(new CustomEvent('webby:cms-ready', { detail: payload }));

        if (payload && payload.page && payload.page.seo_title) {
            document.title = payload.page.seo_title;
        }

        postRuntimeTelemetry(
            'cms_runtime.route_hydrated',
            payload && payload.route ? payload.route : null,
            {
                source: payload && payload.meta && payload.meta.source ? payload.meta.source : null,
                requested_slug: payload && payload.requested_slug ? payload.requested_slug : null,
            }
        );
    }

    var lastRouteSignature = null;

    function hydrate() {
        var routeInfo = resolveCmsRoute(window.location.pathname, config.projectId || null);
        var routeKey = routeSignature(routeInfo);
        if (routeKey === lastRouteSignature) {
            return Promise.resolve(window.__WEBBY_CMS__ || null);
        }

        lastRouteSignature = routeKey;
        var locale = cms.default_locale || 'ka';

        return fetchViaBridge(routeInfo, locale)
            .then(function (payload) {
                emit(payload);
                return payload;
            })
            .catch(function () {
                return fetchViaPublicApi(routeInfo, locale).then(function (payload) {
                    emit(payload);
                    return payload;
                });
            })
            .catch(function (error) {
                postRuntimeTelemetry('cms_runtime.hydrate_failed', routeInfo, {
                    error_code: error && error.message ? String(error.message) : 'unknown',
                });
                console.warn('[webby-cms-runtime] bootstrap failed', error);
                throw error;
            });
    }

    var originalPushState = window.history.pushState;
    var originalReplaceState = window.history.replaceState;

    window.history.pushState = function () {
        var result = originalPushState.apply(window.history, arguments);
        setTimeout(function () {
            hydrate().catch(function () {});
        }, 0);

        return result;
    };

    window.history.replaceState = function () {
        var result = originalReplaceState.apply(window.history, arguments);
        setTimeout(function () {
            hydrate().catch(function () {});
        }, 0);

        return result;
    };

    window.addEventListener('popstate', function () {
        hydrate().catch(function () {});
    });

    window.WebbyCms = {
        getState: function () {
            return window.__WEBBY_CMS__ || null;
        },
        refresh: function () {
            lastRouteSignature = null;
            return hydrate();
        },
        mountNavFooterRuntime: function () {
            mountNavFooterRuntime(window.__WEBBY_CMS__ || null);
        },
        mountFormsRuntime: function () {
            mountFormsRuntime(window.__WEBBY_CMS__ || null);
        },
        onReady: function (callback) {
            if (typeof callback !== 'function') {
                return function () {};
            }

            var handler = function (event) {
                callback(event.detail);
            };

            window.addEventListener('webby:cms-ready', handler);

            if (window.__WEBBY_CMS__) {
                callback(window.__WEBBY_CMS__);
            }

            return function () {
                window.removeEventListener('webby:cms-ready', handler);
            };
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        mountNavFooterRuntime(window.__WEBBY_CMS__ || null);
        mountFormsRuntime(window.__WEBBY_CMS__ || null);
        mountExtendedRuntimeWidgets();
    });

    hydrate().catch(function () {});
})();
JS;
    }

    /**
     * List all workspace IDs on a builder.
     *
     * @return string[]
     */
    public function listWorkspaces(Builder $builder): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->get("{$builder->full_url}/api/workspaces");

        if (! $response->successful()) {
            throw new \Exception("Failed to list workspaces on builder {$builder->name}: ".$response->body());
        }

        return $response->json('workspace_ids') ?? [];
    }

    /**
     * Request bulk deletion of workspaces from a builder.
     *
     * @param  string[]  $workspaceIds
     * @return array{deleted: int, not_found: int, skipped: int, failed: int, results: array}
     */
    public function cleanupWorkspaces(Builder $builder, array $workspaceIds): array
    {
        $response = Http::timeout(120)
            ->withHeaders(['X-Server-Key' => $builder->server_key])
            ->post("{$builder->full_url}/api/cleanup-workspaces", [
                'workspace_ids' => $workspaceIds,
            ]);

        if (! $response->successful()) {
            throw new \Exception("Failed to cleanup workspaces on builder {$builder->name}: ".$response->body());
        }

        return $response->json();
    }

    /**
     * Apply theme preset to workspace files via Go builder.
     * This writes CSS variables directly to the project's src/index.css file.
     */
    public function applyThemeToWorkspace(Builder $builder, Project $project, string $presetId): bool
    {
        $preset = config("theme-presets.{$presetId}");
        if (! $preset) {
            return false;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['X-Server-Key' => $builder->server_key])
                ->put("{$builder->full_url}/api/theme-workspace/{$project->id}", [
                    'light' => $preset['light'],
                    'dark' => $preset['dark'],
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to apply theme to workspace', [
                'project_id' => $project->id,
                'preset' => $presetId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
