<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\BuildCreditService;
use App\Services\LandingPageService;
use App\Services\InternalAiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        if (! SystemSetting::get('landing_page_enabled', true)) {
            if (auth()->check()) {
                return redirect()->route('create');
            }
            return redirect()->route('login');
        }

        $landingPageService = app(LandingPageService::class);
        $locale = app()->getLocale();
        $pageConfig = $landingPageService->getPageConfig($locale);

        // Use locale-backed static dictionaries so landing content is always translated
        // consistently per selected language and never falls back to mixed-language AI output.
        $allHeadlines = InternalAiService::getStaticHeroHeadlines($locale);
        $allSubtitles = InternalAiService::getStaticHeroSubtitles($locale);
        $fallbackSuggestions = InternalAiService::getStaticSuggestions($locale);
        $fallbackTypingPrompts = InternalAiService::getStaticTypingPrompts($locale);

        $primaryHeadline = $allHeadlines[0] ?? 'What will you build?';
        $primarySubtitle = $allSubtitles[0] ?? 'Create stunning websites by chatting with AI.';

        if (isset($pageConfig['sections'])) {
            foreach ($pageConfig['sections'] as &$section) {
                if (($section['type'] ?? '') === 'hero') {
                    $section['content']['headlines'] = [$primaryHeadline];
                    $section['content']['subtitles'] = [$primarySubtitle];
                    $section['content']['suggestions'] = $fallbackSuggestions;
                    $section['content']['typing_prompts'] = $fallbackTypingPrompts;
                    break;
                }
            }
            unset($section);
        }

        $plans = Plan::active()
            ->orderBy('sort_order')
            ->get(Plan::landingPageSelectColumns());

        $canCreateProject = true;
        $cannotCreateReason = null;
        $isPusherConfigured = true;

        if (auth()->check()) {
            $broadcastService = app(BroadcastService::class);
            $isPusherConfigured = $broadcastService->isConfigured();
            $buildCreditService = app(BuildCreditService::class);
            $canBuildResult = $buildCreditService->canPerformBuild(auth()->user());
            $canCreateProject = $canBuildResult['allowed'];
            $cannotCreateReason = $canBuildResult['reason'];
        }

        return Inertia::render('Landing', array_merge($pageConfig, [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register') && SystemSetting::get('enable_registration', true),
            'plans' => $plans,
            'isPusherConfigured' => $isPusherConfigured,
            'canCreateProject' => $canCreateProject,
            'cannotCreateReason' => $cannotCreateReason,
            'statistics' => Cache::remember('landing_stats', 3600, fn () => [
                'usersCount' => User::count(),
                'projectsCount' => Project::count(),
            ]),
            'headline' => $primaryHeadline,
            'subtitle' => $primarySubtitle,
            'suggestions' => $fallbackSuggestions,
            'typingPrompts' => $fallbackTypingPrompts,
        ]));
    }
}
