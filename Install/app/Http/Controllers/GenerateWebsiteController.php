<?php

namespace App\Http\Controllers;

use App\Services\AiWebsiteGeneration\GenerateWebsiteProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Ultimate AI website generation: CMS-first, single flow.
 * Creates Website + Project + Site + pages/sections and opens the visual builder.
 */
class GenerateWebsiteController extends Controller
{
    private const PENDING_REDIRECT_URL_SESSION_KEY = 'create_pending_redirect_url';

    private const PENDING_REDIRECT_AT_SESSION_KEY = 'create_pending_redirect_at';

    public function __invoke(Request $request): RedirectResponse|SymfonyResponse
    {
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

        $ultraCheapMode = $user->aiSettings?->isUltraCheapMode() ?? true;

        try {
            $result = app(GenerateWebsiteProjectService::class)->generate([
                'userPrompt' => $validated['prompt'],
                'language' => $validated['language'] ?? null,
                'style' => $validated['style'] ?? null,
                'websiteType' => $validated['websiteType'] ?? null,
                'user_id' => $user->id,
                'ultra_cheap_mode' => $ultraCheapMode,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'prompt' => __('Generation failed. Try again or create a project from the main Create page.'),
            ]);
        }

        $project = $result['project'];

        $url = route('chat', [
            'project' => $project,
            'tab' => 'inspect',
        ]);
        $request->session()->put(self::PENDING_REDIRECT_URL_SESSION_KEY, $url);
        $request->session()->put(self::PENDING_REDIRECT_AT_SESSION_KEY, now()->toIso8601String());

        return redirect()->to($url)->with(
            'success',
            __('Website created. The visual builder is ready.')
        );
    }
}
