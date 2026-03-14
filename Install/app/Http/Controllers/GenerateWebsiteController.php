<?php

namespace App\Http\Controllers;

use App\Jobs\RunProjectGeneration;
use App\Models\ProjectGenerationRun;
use App\Services\AiWebsiteGeneration\GenerateWebsiteProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Ultimate AI website generation: CMS-first, single flow.
 * Creates Website + Project + Site + pages/sections and redirects into the code-first chat workspace.
 */
class GenerateWebsiteController extends Controller
{
    private const PENDING_REDIRECT_URL_SESSION_KEY = 'create_pending_redirect_url';

    private const PENDING_REDIRECT_AT_SESSION_KEY = 'create_pending_redirect_at';

    public function __invoke(Request $request): RedirectResponse|SymfonyResponse
    {
        if (! (bool) config('webu_v2.flags.code_first_initial_generation', true)) {
            return back()->withErrors([
                'prompt' => __('Code-first generation is currently disabled. Use the classic project creation flow.'),
            ]);
        }

        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'language' => 'nullable|string|in:ka,en,both',
            'style' => 'nullable|string|in:modern,minimal,luxury,playful,corporate',
            'websiteType' => 'nullable|string|in:business,ecommerce,portfolio,booking',
        ], [
            'prompt.required' => __('Please describe the website you want to create.'),
            'prompt.max' => __('Description is too long. Please shorten it.'),
        ]);

        $user = $request->user();
        if (! $user) {
            return back()->withErrors(['prompt' => __('Please sign in to generate a website.')]);
        }

        if (! $user->canCreateMoreProjects()) {
            return back()->withErrors([
                'prompt' => __('You have reached your project limit. Upgrade your plan to create more.'),
            ]);
        }

        $buildCreditService = app(\App\Services\BuildCreditService::class);
        $canBuild = $buildCreditService->canPerformBuild($user);
        if (! $canBuild['allowed']) {
            return back()->withErrors([
                'prompt' => $canBuild['reason'],
            ]);
        }

        $hasActiveGeneration = ProjectGenerationRun::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ProjectGenerationRun::activeStatuses())
            ->exists();

        if ($hasActiveGeneration) {
            return back()->withErrors([
                'prompt' => __('You already have a website generation in progress. Wait for it to finish before starting a new one.'),
            ]);
        }

        $ultraCheapMode = $user->aiSettings?->isUltraCheapMode() ?? true;

        $prompt = trim((string) $validated['prompt']);
        $project = app(GenerateWebsiteProjectService::class)->createProjectShell($user->id, $prompt);

        $generationRun = ProjectGenerationRun::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => ProjectGenerationRun::STATUS_QUEUED,
            'requested_prompt' => $prompt,
            'requested_language' => $validated['language'] ?? null,
            'requested_style' => $validated['style'] ?? null,
            'requested_website_type' => $validated['websiteType'] ?? null,
            'requested_input' => [
                'prompt' => $prompt,
                'language' => $validated['language'] ?? null,
                'style' => $validated['style'] ?? null,
                'websiteType' => $validated['websiteType'] ?? null,
                'ultra_cheap_mode' => $ultraCheapMode,
            ],
            'progress_message' => 'Preparing generation.',
        ]);

        RunProjectGeneration::dispatchAfterResponse((string) $generationRun->id);

        $url = route('chat', [
            'project' => $project,
        ]);
        $request->session()->put(self::PENDING_REDIRECT_URL_SESSION_KEY, $url);
        $request->session()->put(self::PENDING_REDIRECT_AT_SESSION_KEY, now()->toIso8601String());

        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->to($url)->with(
            'success',
            __('Website generation started. The workspace will unlock visual editing when the project is ready.')
        );
    }
}
