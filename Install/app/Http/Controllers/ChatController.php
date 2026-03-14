<?php

namespace App\Http\Controllers;

use App\Cms\Contracts\CmsModuleRegistryServiceContract;
use App\Cms\Support\LocalizedCmsPayload;
use App\Http\Controllers\Concerns\BuildsProjectGenerationPayload;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\ProjectGenerationRun;
use App\Models\SystemSetting;
use App\Models\Site;
use App\Services\ChatApplyPatchService;
use App\Services\ChatPatchRollbackService;
use App\Services\CmsComponentLibraryCatalogService;
use App\Services\FirebaseService;
use App\Services\InternalAiService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\ProjectWorkspace\WorkspaceSectionRegistryService;
use App\Services\SiteProvisioningService;
use App\Support\CmsSectionLocalId;
use App\Support\SubdomainHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    use BuildsProjectGenerationPayload;

    public function __construct(
        protected InternalAiService $internalAi,
        protected FirebaseService $firebaseService,
        protected SiteProvisioningService $siteProvisioningService,
        protected CmsModuleRegistryServiceContract $moduleRegistry,
        protected LocalizedCmsPayload $localizedPayload,
        protected ChatApplyPatchService $chatApplyPatch,
        protected ChatPatchRollbackService $chatPatchRollback,
        protected CmsComponentLibraryCatalogService $catalogService,
        protected WorkspaceSectionRegistryService $workspaceSectionRegistry,
        protected ProjectWorkspaceService $projectWorkspace
    ) {}

    /**
     * Display the chat page for a project.
     */
    public function show(Request $request, Project $project): Response|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        $this->authorize('view', $project);

        $project->loadMissing(['latestGenerationRun', 'site', 'template']);
        $project->update(['last_viewed_at' => now()]);

        // Get broadcast settings from database
        $integrationSettings = SystemSetting::getGroup('integrations');
        $driver = $integrationSettings['broadcast_driver'] ?? 'pusher';

        if ($driver === 'reverb' && ! empty($integrationSettings['reverb_key'])) {
            $pusherConfig = [
                'provider' => 'reverb',
                'key' => $integrationSettings['reverb_key'],
                'host' => $integrationSettings['reverb_host'] ?? '',
                'port' => (int) ($integrationSettings['reverb_port'] ?? 8080),
                'scheme' => $integrationSettings['reverb_scheme'] ?? 'http',
            ];
        } else {
            $pusherConfig = [
                'provider' => 'pusher',
                'key' => $integrationSettings['pusher_key'] ?? '',
                'cluster' => $integrationSettings['pusher_cluster'] ?? 'mt1',
            ];
        }

        // Check if preview exists for this project
        $generationRun = $project->latestGenerationRun;
        $generationPayload = $this->buildGenerationPayload($generationRun, $project, $this->projectWorkspace);
        if ($this->generationRequiresCompletionGate($generationPayload)) {
            return redirect()->route('project.generation', [
                'project' => $project,
            ]);
        }
        $previewExists = Storage::disk('local')->exists("previews/{$project->id}");
        $previewUrl = $previewExists ? "/preview/{$project->id}" : null;
        $hasActiveSession = ! empty($project->build_session_id) && ! empty($project->builder_id);
        // Only reconnect if build is currently running (not idle, completed, failed, or cancelled)
        $canReconnect = $hasActiveSession && $project->build_status === 'building';

        $user = request()->user();
        $site = $this->siteProvisioningService->provisionForProject($project);
        // Same catalog as /admin/component-library — Georgian labels, same components
        $catalog = $this->catalogService->buildCatalog();
        $builderLibraryItems = [];
        foreach ($catalog as $item) {
            if (! ($item['enabled'] ?? true)) {
                continue;
            }
            $builderLibraryItems[] = [
                'key' => (string) $item['key'],
                'category' => (string) $item['category'],
                'category_label' => (string) ($item['category_label'] ?? $item['category']),
                'label' => (string) ($item['label'] ?? $item['key']),
            ];
        }
        foreach ($this->workspaceSectionRegistry->builderItems($project) as $item) {
            $builderLibraryItems[] = $item;
        }
        $builderLibraryItems = array_values(array_reduce($builderLibraryItems, static function (array $carry, array $item): array {
            $key = trim((string) ($item['key'] ?? ''));
            if ($key !== '' && ! isset($carry[$key])) {
                $carry[$key] = $item;
            }

            return $carry;
        }, []));
        $generatedPages = $site
            ? $this->resolveGeneratedPageSnapshots($site)
            : [];
        $hasGeneratedPages = count($generatedPages) > 0;
        $templateSlug = $hasGeneratedPages
            ? $this->resolveCmsPreviewTemplateSlug($generatedPages, 'home')
            : trim((string) optional($project->template)->slug);
        if ($templateSlug === '') {
            $templateSlug = 'default';
        }
        $cmsPreviewUrl = null;
        if ($site) {
            // live_design=1: use latest app.css and no payload cache so component-library design changes react immediately
            $cmsPreviewUrl = sprintf(
                '/themes/%s?site=%s&slug=home&locale=%s&draft=1&live_design=1',
                rawurlencode($templateSlug),
                rawurlencode((string) $site->id),
                rawurlencode((string) ($site->locale ?: 'ka'))
            );
        }
        $baseDomain = SystemSetting::get('domain_base_domain', config('app.base_domain', 'example.com'));
        $modulesPayload = $site
            ? $this->moduleRegistry->modules($site, $user)
            : null;

        // Firebase settings
        $firebaseSettings = null;
        if ($user->canUseFirebase()) {
            $firebaseSettings = [
                'enabled' => true,
                'canUseOwnConfig' => $user->canUseOwnFirebaseConfig(),
                'usesSystemFirebase' => $project->uses_system_firebase,
                'customConfig' => $project->uses_system_firebase ? null : $project->firebase_config,
                'systemConfigured' => $this->firebaseService->isSystemConfigured(),
                'collectionPrefix' => $project->getFirebaseCollectionPrefix(),
            ];
        }

        // Storage settings
        $storageSettings = null;
        if ($user->canUseFileStorage()) {
            $storageUsage = $user->getStorageUsage();
            $storageSettings = [
                'enabled' => true,
                'usedBytes' => $project->storage_used_bytes ?? 0,
                'limitMb' => $storageUsage['limit_mb'],
                'unlimited' => (bool) ($storageUsage['unlimited'] ?? false),
            ];
        }

        return Inertia::render('Chat', [
            'project' => [
                ...$project->only('id', 'name', 'initial_prompt'),
                'has_history' => ! empty($project->conversation_history),
                'conversation_history' => $project->conversation_history ?? [],
                'preview_url' => $previewUrl,
                'cms_preview_url' => $cmsPreviewUrl,
                'has_active_session' => $hasActiveSession,
                'build_session_id' => $project->build_session_id,
                'build_status' => $project->build_status,
                'can_reconnect' => $canReconnect,
                'build_started_at' => $project->build_started_at?->toIso8601String(),
                'project_generation_version' => data_get($generationPayload, 'project_generation_version'),
                'source_generation_type' => data_get($generationPayload, 'source_generation_type', $project->template_id ? 'template' : 'new'),
                'preview_build_id' => data_get($generationPayload, 'preview_build_id'),
                'draft_source_id' => null,
                'generation' => $generationPayload,
                // Publishing fields
                'subdomain' => $project->subdomain,
                'published_title' => $project->published_title,
                'published_description' => $project->published_description,
                'published_visibility' => $project->published_visibility ?? 'public',
                'published_at' => $project->published_at?->toIso8601String(),
                // Settings fields
                'custom_instructions' => $project->custom_instructions,
                'theme_preset' => $project->theme_preset,
                'share_image' => $project->share_image,
                'api_token' => $project->api_token,
            ],
            'user' => $user->only('id', 'name', 'email', 'avatar', 'role'),
            'pusherConfig' => $pusherConfig,
            'soundSettings' => $user->aiSettings?->getSoundSettings() ?? [
                'enabled' => false,
                'style' => 'minimal',
                'volume' => 50,
            ],
            // Publishing props
            'baseDomain' => $baseDomain,
            'canUseSubdomains' => SystemSetting::get('domain_enable_subdomains', false) && $user->canUseSubdomains(),
            'canCreateMoreSubdomains' => SystemSetting::get('domain_enable_subdomains', false) && $user->canCreateMoreSubdomains(),
            'canUsePrivateVisibility' => $user->canUsePrivateVisibility(),
            'suggestedSubdomain' => $project->subdomain ?? SubdomainHelper::generateFromString($project->name),
            'subdomainUsage' => $user->getSubdomainUsage(),
            'firebase' => $firebaseSettings,
            'storage' => $storageSettings,
            'moduleRegistry' => $modulesPayload,
            'builderLibraryItems' => $builderLibraryItems,
            'generatedPage' => $generatedPages[0] ?? $this->emptyGeneratedPageSnapshot(),
            'generatedPages' => $generatedPages,
            'buildCredits' => [
                'remaining' => $user->getRemainingBuildCredits(),
                'monthlyLimit' => $user->getMonthlyBuildCreditsAllocation(),
                'isUnlimited' => $user->hasUnlimitedCredits(),
                'usingOwnKey' => $user->isUsingOwnAiApiKey(),
            ],
        ]);
    }

    /**
     * @return array{
     *   page_id: int|null,
     *   revision_id: int|null,
     *   slug: string|null,
     *   title: string|null,
     *   revision_source: string|null,
     *   sections: array<int, array{type: string, props: array<string, mixed>, localId: string}>
     * }
     */
    private function resolveGeneratedPageSnapshots(Site $site): array
    {
        $siteLocale = $this->localizedPayload->normalizeLocale($site->locale, 'ka');

        return $site->pages()
            ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page): array => $this->buildGeneratedPageSnapshot($page, $siteLocale))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   page_id: int|null,
     *   revision_id: int|null,
     *   slug: string|null,
     *   title: string|null,
     *   revision_source: string|null,
     *   sections: array<int, array{type: string, props: array<string, mixed>, localId: string}>
     * }
     */
    private function buildGeneratedPageSnapshot(Page $page, string $siteLocale): array
    {
        /** @var PageRevision|null $latestRevision */
        $latestRevision = $page->revisions()
            ->latest('version')
            ->first();

        /** @var PageRevision|null $publishedRevision */
        $publishedRevision = $page->revisions()
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->first();

        $revision = $latestRevision ?? $publishedRevision;
        $revisionSource = $latestRevision
            ? 'latest'
            : ($publishedRevision ? 'published' : null);
        $content = $this->resolveGeneratedRevisionContent($revision, $siteLocale);
        $rawSections = is_array($content['sections'] ?? null)
            ? $content['sections']
            : [];

        $sections = array_values(array_filter(array_map(static function ($section, $index): ?array {
            if (! is_array($section)) {
                return null;
            }

            $type = trim((string) ($section['type'] ?? ''));
            $props = is_array($section['props'] ?? null)
                ? $section['props']
                : [];
            $localId = CmsSectionLocalId::resolve($section, is_int($index) ? $index : 0);

            if ($type === '') {
                return null;
            }

            return [
                'type' => $type,
                'props' => $props,
                'localId' => $localId,
            ];
        }, $rawSections, array_keys($rawSections))));

        return [
            'page_id' => $page->id,
            'revision_id' => $revision?->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'revision_source' => $revisionSource,
            'sections' => $sections,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveGeneratedRevisionContent(?PageRevision $revision, string $siteLocale): array
    {
        if (! $revision || ! is_array($revision->content_json)) {
            return ['sections' => []];
        }

        $resolved = $this->localizedPayload->resolve($revision->content_json, $siteLocale, $siteLocale);

        return is_array($resolved['content'] ?? null)
            ? $resolved['content']
            : ['sections' => []];
    }

    /**
     * @return array{
     *   page_id: int|null,
     *   revision_id: int|null,
     *   slug: string|null,
     *   title: string|null,
     *   revision_source: string|null,
     *   sections: array<int, array{type: string, props: array<string, mixed>, localId: string}>
     * }
     */
    private function emptyGeneratedPageSnapshot(): array
    {
        return [
            'page_id' => null,
            'revision_id' => null,
            'slug' => null,
            'title' => null,
            'revision_source' => null,
            'sections' => [],
        ];
    }

    /**
     * @param  array<int, array{slug?: string|null, sections?: array<int, array{type?: string}>}>  $generatedPages
     */
    private function resolveCmsPreviewTemplateSlug(array $generatedPages, string $previewPageSlug = 'home'): string
    {
        $normalizedPreviewSlug = trim(strtolower($previewPageSlug));
        $previewPage = collect($generatedPages)
            ->first(fn (array $page): bool => trim(strtolower((string) ($page['slug'] ?? ''))) === $normalizedPreviewSlug);

        if (! is_array($previewPage)) {
            $previewPage = $generatedPages[0] ?? null;
        }

        $sections = is_array($previewPage['sections'] ?? null) ? $previewPage['sections'] : [];
        foreach ($sections as $section) {
            $type = strtolower(trim((string) ($section['type'] ?? '')));
            if ($type !== '' && str_starts_with($type, 'webu_ecom_')) {
                return 'ecommerce';
            }
        }

        return 'default';
    }

    /**
     * Process a chat message and return a dummy AI response.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $responses = [
            "I'm a dummy AI assistant. I received your message and I'm here to help!",
            "That's an interesting question! I'm just a placeholder for now, but the real AI will be much smarter.",
            "I understand what you're asking. This is a demo response to show how the chat interface works.",
            "Great question! In the future, I'll be able to provide more helpful answers.",
            "Thanks for your message! I'm a dummy AI, but I'm doing my best to be helpful.",
            "I see what you mean. Once the real AI is integrated, you'll get much better responses.",
            'Interesting! As a placeholder AI, I can only provide sample responses like this one.',
            'Got it! This dummy response demonstrates the chat functionality is working correctly.',
        ];

        return response()->json([
            'message' => $responses[array_rand($responses)],
        ]);
    }

    /**
     * Append chat messages to project conversation history (so all correspondence is persisted).
     * Used after AI site editor or chat-apply-patch to save user + assistant messages.
     */
    public function appendChatEntries(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'entries' => ['required', 'array', 'max:20'],
            'entries.*.role' => ['required', 'string', 'in:user,assistant,action'],
            'entries.*.content' => ['required', 'string', 'max:100000'],
            'entries.*.category' => ['nullable', 'string', 'max:120'],
            'entries.*.thinking_duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $entries = array_map(function ($e) {
            $out = ['role' => $e['role'], 'content' => $e['content']];
            if (isset($e['category'])) {
                $out['category'] = $e['category'];
            }
            if (isset($e['thinking_duration'])) {
                $out['thinking_duration'] = (int) $e['thinking_duration'];
            }
            return $out;
        }, $validated['entries']);

        $project->appendMessages($entries);

        return response()->json(['success' => true]);
    }

    /**
     * Generate a natural-language AI reply after a site edit (so the chat feels like a conversation, not auto-responses).
     */
    public function chatReply(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'user_message' => ['required', 'string', 'max:5000'],
            'action_summary' => ['nullable', 'array'],
            'action_summary.*' => ['string', 'max:500'],
            'locale' => ['nullable', 'string', 'max:20'],
        ]);

        $reply = $this->internalAi->generateSiteEditReply(
            $validated['user_message'],
            array_values($validated['action_summary'] ?? []),
            $validated['locale'] ?? null
        );

        return response()->json([
            'reply' => $reply,
        ]);
    }

    /**
     * Propose a patch from a message (Instruction → patch flow). No apply; returns summary for diff preview.
     */
    public function proposePatch(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $result = $this->chatApplyPatch->propose($project, $validated['message']);
        } catch (\Throwable $e) {
            Log::warning('chat_patch_propose_failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            $result = ['proposed' => false];
        }

        return response()->json($result);
    }

    /**
     * Apply a chat edit intent as a JSON patch (theme_preset or add_section).
     * PART 7 — AI Chat Editing: "Make it darker" → theme preset; "Add best sellers" → section.
     */
    public function applyPatch(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $result = $this->chatApplyPatch->apply($project, $validated['message']);
        } catch (\Throwable $e) {
            Log::warning('chat_patch_apply_failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            $result = ['applied' => false];
        }

        return response()->json($result);
    }

    /**
     * Rollback the last applied chat patch (theme or section). PART 6 — Director for Chat Editing.
     */
    public function rollbackLastPatch(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $result = $this->chatPatchRollback->rollbackLastPatch($project);

        return response()->json($result);
    }

    /**
     * Get AI-generated suggestions for the chat. Content is in the site/app locale.
     */
    public function suggestions(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $locale = request()->input('locale') ?? app()->getLocale();

        $suggestions = $this->internalAi->getChatSuggestions(
            $project->conversation_history ?? [],
            3,
            $locale
        );

        return response()->json(['suggestions' => $suggestions ?? []]);
    }
}
