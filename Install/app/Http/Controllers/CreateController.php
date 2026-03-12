<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Services\BroadcastService;
use App\Services\InternalAiService;
use App\Services\ReadyTemplatesService;
use App\Support\CreateTemplateCatalogVisibility;
use App\Support\OwnedTemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CreateController extends Controller
{
    private const PENDING_REDIRECT_URL_SESSION_KEY = 'create_pending_redirect_url';

    private const PENDING_REDIRECT_AT_SESSION_KEY = 'create_pending_redirect_at';

    public function __construct(
        protected InternalAiService $internalAiService,
        protected BroadcastService $broadcastService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        try {
            $recentProjects = $user->projects()
                ->orderByDesc('last_viewed_at')
                ->limit(5)
                ->get();
            $starredProjects = $user->projects()->where('is_starred', true)->get();
            $sharedProjects = $user->sharedProjects()->with('user:id,name,avatar')->get();
        } catch (\Throwable $e) {
            $recentProjects = collect([]);
            $starredProjects = collect([]);
            $sharedProjects = collect([]);
        }

        try {
            $availableTemplates = $user->hasAdminBypass()
                ? Template::query()->get()
                : Template::forPlan($user->getCurrentPlan())->get();
            $templates = $this->focusTemplateCatalog($availableTemplates);
        } catch (\Throwable $e) {
            $templates = collect([]);
        }

        try {
            $isBroadcastConfigured = $this->broadcastService->isConfigured();
            $broadcastErrorMessage = $this->broadcastService->getErrorMessage();
        } catch (\Throwable $e) {
            $isBroadcastConfigured = false;
            $broadcastErrorMessage = $e->getMessage();
        }

        try {
            $buildCreditService = app(\App\Services\BuildCreditService::class);
            $canBuildResult = $buildCreditService->canPerformBuild($user);
        } catch (\Throwable $e) {
            $canBuildResult = ['allowed' => true, 'reason' => null];
        }

        $firstName = trim(explode(' ', $user->name ?? 'User')[0]) ?: 'User';
        $locale = app()->getLocale();
        $staticGreetings = InternalAiService::getStaticGreetings($locale);
        $greeting = count($staticGreetings) > 0
            ? str_replace('{name}', $firstName, $staticGreetings[random_int(0, count($staticGreetings) - 1)])
            : 'What do you want to build, '.$firstName.'?';

        try {
            $readyTemplates = app(ReadyTemplatesService::class)->list();
        } catch (\Throwable $e) {
            $readyTemplates = [];
        }

        $pendingRedirectUrl = $this->resolvePendingRedirectUrl($request);

        return Inertia::render('Create', [
            'user' => $user->only('id', 'name', 'email', 'avatar', 'role'),
            'recentProjects' => $recentProjects,
            'starredProjects' => $starredProjects,
            'sharedProjects' => $sharedProjects,
            'templates' => $templates,
            'readyTemplates' => $readyTemplates,
            'isPusherConfigured' => $isBroadcastConfigured,
            'broadcastErrorMessage' => $broadcastErrorMessage,
            'canCreateProject' => $canBuildResult['allowed'],
            'cannotCreateReason' => $canBuildResult['reason'],
            'suggestions' => InternalAiService::getStaticSuggestions($locale),
            'typingPrompts' => InternalAiService::getStaticTypingPrompts($locale),
            'greeting' => $greeting,
            'firstName' => $firstName,
            'pendingRedirectUrl' => $pendingRedirectUrl,
        ]);
    }

    private function resolvePendingRedirectUrl(Request $request): ?string
    {
        $url = $request->session()->get(self::PENDING_REDIRECT_URL_SESSION_KEY);
        $storedAt = $request->session()->get(self::PENDING_REDIRECT_AT_SESSION_KEY);

        if (! is_string($url) || trim($url) === '' || ! is_string($storedAt) || trim($storedAt) === '') {
            return null;
        }

        try {
            $timestamp = Carbon::parse($storedAt);
        } catch (\Throwable) {
            $request->session()->forget([
                self::PENDING_REDIRECT_URL_SESSION_KEY,
                self::PENDING_REDIRECT_AT_SESSION_KEY,
            ]);

            return null;
        }

        if ($timestamp->lt(now()->subMinutes(5))) {
            $request->session()->forget([
                self::PENDING_REDIRECT_URL_SESSION_KEY,
                self::PENDING_REDIRECT_AT_SESSION_KEY,
            ]);

            return null;
        }

        return $url;
    }

    /**
     * @param  Collection<int, Template>  $templates
     * @return Collection<int, Template>
     */
    private function focusTemplateCatalog(Collection $templates): Collection
    {
        if ($templates->isEmpty()) {
            return $templates;
        }

        $templates = $templates
            ->filter(static fn (Template $template): bool => CreateTemplateCatalogVisibility::allowsTemplate($template))
            ->values();

        if ($templates->isEmpty()) {
            return $templates;
        }

        $priorityBySlug = array_flip(OwnedTemplateCatalog::slugs());

        $focused = $templates
            ->filter(fn (Template $template): bool => array_key_exists((string) $template->slug, $priorityBySlug))
            ->sortBy(fn (Template $template): int => (int) $priorityBySlug[(string) $template->slug])
            ->values();

        return $focused->isNotEmpty() ? $focused : $templates->values();
    }

    /**
     * Fetch AI-powered content asynchronously.
     */
    public function aiContent(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $firstName = explode(' ', $user->name)[0];
        $locale = app()->getLocale();

        // Use locale dictionaries for consistent translated UI content.
        $suggestions = InternalAiService::getStaticSuggestions($locale);
        $typingPrompts = InternalAiService::getStaticTypingPrompts($locale);
        $greetings = InternalAiService::getStaticGreetings($locale);

        // Pick a random greeting and replace {name}
        $randomIndex = random_int(0, count($greetings) - 1);
        $greeting = str_replace('{name}', $firstName, $greetings[$randomIndex]);

        return response()->json([
            'suggestions' => $suggestions,
            'typingPrompts' => $typingPrompts,
            'greeting' => $greeting,
        ]);
    }

    /**
     * Fetch AI-powered content for landing page (public, no auth).
     */
    public function landingAiContent(): \Illuminate\Http\JsonResponse
    {
        $locale = app()->getLocale();
        $suggestions = InternalAiService::getStaticSuggestions($locale);
        $typingPrompts = InternalAiService::getStaticTypingPrompts($locale);
        $headlines = InternalAiService::getStaticHeroHeadlines($locale);
        $subtitles = InternalAiService::getStaticHeroSubtitles($locale);

        return response()->json([
            'suggestions' => $suggestions,
            'typingPrompts' => $typingPrompts,
            'headline' => $headlines[0] ?? null,
            'subtitle' => $subtitles[0] ?? null,
        ]);
    }
}
