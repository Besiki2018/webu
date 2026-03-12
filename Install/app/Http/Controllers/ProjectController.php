<?php

namespace App\Http\Controllers;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Services\AssetFirstDraftComposerService;
use App\Services\ReadyTemplatesService;
use App\Services\SiteProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProjectController extends Controller
{
    private const PENDING_REDIRECT_URL_SESSION_KEY = 'create_pending_redirect_url';

    private const PENDING_REDIRECT_AT_SESSION_KEY = 'create_pending_redirect_at';

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tab = $request->get('tab', 'all');
        $search = $request->get('search');
        $sort = $request->get('sort', 'last-edited');
        $visibility = $request->get('visibility');

        // Build base query based on tab
        $query = match ($tab) {
            'favorites' => $user->projects()->with('user')->where('is_starred', true),
            default => $user->projects()->with('user'),
        };

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply visibility filter
        if ($visibility && in_array($visibility, ['public', 'private'])) {
            $query->where('is_public', $visibility === 'public');
        }

        // Apply sorting
        $query = match ($sort) {
            'name' => $query->orderBy('name', 'asc'),
            'created' => $query->orderBy('created_at', 'desc'),
            default => $query->orderBy('updated_at', 'desc'),
        };

        // Paginate
        $projects = $query->paginate(12)->withQueryString();

        $counts = [
            'all' => $user->projects()->count(),
            'favorites' => $user->projects()->where('is_starred', true)->count(),
            'trash' => $user->projects()->onlyTrashed()->count(),
        ];

        $filters = [
            'search' => $search,
            'sort' => $sort,
            'visibility' => $visibility,
        ];

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'counts' => $counts,
            'activeTab' => $tab,
            'filters' => $filters,
            'baseDomain' => SystemSetting::get('domain_base_domain', config('app.base_domain', 'example.com')),
        ]);
    }

    public function store(Request $request): RedirectResponse|SymfonyResponse
    {
        $validated = $request->validate([
            'mode' => 'nullable|string|in:ai,manual',
            'prompt' => 'nullable|string|max:2000',
            'project_name' => 'nullable|string|max:255',
            'template_id' => 'nullable|integer|exists:templates,id',
            'theme_preset' => 'nullable|string|in:default,arctic,summer,fragrant,slate,feminine,forest,midnight,coral,mocha,ocean,ruby,luxury_minimal,corporate_clean,bold_startup,soft_pastel,dark_modern,creative_portfolio',
        ]);

        $mode = ($validated['mode'] ?? 'ai') === 'manual' ? 'manual' : 'ai';
        $user = $request->user();
        $templateId = isset($validated['template_id']) ? (int) $validated['template_id'] : null;

        if ($templateId !== null) {
            $template = Template::query()->find($templateId);
            if ($template && ! $user->hasAdminBypass() && ! $template->isAvailableForPlan($user->getCurrentPlan())) {
                return back()->withErrors([
                    'template_id' => 'The selected template is not available for your plan.',
                ]);
            }
        }

        // Check project limit
        if (! $user->canCreateMoreProjects()) {
            $plan = $user->getCurrentPlan();
            $maxProjects = $plan ? $plan->getMaxProjects() : 0;

            return back()->withErrors([
                'prompt' => $maxProjects === 0
                    ? 'Your plan does not include project creation. Please upgrade your plan to create projects.'
                    : "You have reached the maximum number of projects ({$maxProjects}) allowed by your plan. Please upgrade to create more projects.",
            ]);
        }

        if ($mode === 'manual') {
            $projectName = trim((string) ($validated['project_name'] ?? ''));
            if ($projectName === '') {
                return back()->withErrors([
                    'project_name' => 'Project name is required for manual builder mode.',
                ]);
            }

            $project = Project::query()->create([
                'user_id' => $user->id,
                'name' => $projectName,
                'template_id' => $templateId,
                'theme_preset' => $validated['theme_preset'] ?? null,
                'last_viewed_at' => now(),
            ]);

            app(SiteProvisioningService::class)->provisionForProject($project);

            return $this->inertiaAwareRedirect($request, route('project.cms', $project));
        }

        $prompt = trim((string) ($validated['prompt'] ?? ''));
        if ($prompt === '') {
            return back()->withErrors([
                'prompt' => 'Prompt is required for AI builder mode.',
            ]);
        }

        // Broadcast is optional: without it, build progress is received via HTTP polling on the chat page.

        // Check if user can perform builds
        $buildCreditService = app(\App\Services\BuildCreditService::class);
        $canBuild = $buildCreditService->canPerformBuild($user);

        if (! $canBuild['allowed']) {
            return back()->withErrors([
                'prompt' => $canBuild['reason'],
            ]);
        }

        // Block concurrent builds for the same user
        $activeBuild = Project::where('user_id', $user->id)
            ->where('build_status', 'building')
            ->exists();

        if ($activeBuild) {
            return back()->withErrors([
                'prompt' => 'You have an active session. Wait for it to complete, or stop it.',
            ]);
        }

        // Generate a name from the prompt (first 50 chars)
        $name = str($prompt)->limit(50, '...')->toString();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => $name,
            'initial_prompt' => $prompt,
            'template_id' => $templateId,
            'theme_preset' => $validated['theme_preset'] ?? null,
            'last_viewed_at' => now(),
        ]);

        // Redirect to chat so user continues the conversation with AI only (ChatGPT-like).
        return $this->inertiaAwareRedirect($request, route('chat', $project));
    }

    /**
     * Create a new project from a ready template (resources/templates/*.json).
     */
    public function storeFromReadyTemplate(Request $request): RedirectResponse|SymfonyResponse
    {
        $validated = $request->validate([
            'template_slug' => 'required|string|max:128',
            'project_name' => 'nullable|string|max:255',
            'provision_demo_store' => 'nullable|boolean',
        ]);

        $user = $request->user();
        if (! $user->canCreateMoreProjects()) {
            return back()->withErrors([
                'template_slug' => __('You have reached the maximum number of projects. Please upgrade to create more.'),
            ]);
        }

        $readyTemplates = app(ReadyTemplatesService::class);
        $templateData = $readyTemplates->loadBySlug($validated['template_slug']);
        if ($templateData === []) {
            return back()->withErrors([
                'template_slug' => __('Template not found.'),
            ]);
        }

        $name = trim((string) ($validated['project_name'] ?? ''));
        if ($name === '') {
            $name = (string) (Arr::get($templateData, 'name', $validated['template_slug']));
        }

        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'template_id' => null,
            'theme_preset' => Arr::get($templateData, 'theme_preset'),
            'last_viewed_at' => now(),
        ]);

        $options = [];
        if (array_key_exists('provision_demo_store', $validated)) {
            $options['provision_demo_store'] = (bool) $validated['provision_demo_store'];
        }
        app(SiteProvisioningService::class)->provisionFromReadyTemplate($project, $templateData, $options);

        return $this->inertiaAwareRedirect($request, route('project.cms', $project));
    }

    /**
     * Create a new project from uploaded/pasted template JSON (same shape as ready templates).
     */
    public function storeFromTemplateJson(Request $request): RedirectResponse|SymfonyResponse
    {
        $request->validate([
            'project_name' => 'nullable|string|max:255',
            'template_json' => 'required|string',
            'provision_demo_store' => 'nullable|boolean',
        ]);

        $user = $request->user();
        if (! $user->canCreateMoreProjects()) {
            return back()->withErrors([
                'template_json' => __('You have reached the maximum number of projects. Please upgrade to create more.'),
            ]);
        }

        $templateData = json_decode($request->input('template_json'), true);
        if (! is_array($templateData) || ! isset($templateData['default_pages']) || ! is_array($templateData['default_pages'])) {
            return back()->withErrors([
                'template_json' => __('Invalid template JSON. Must contain "default_pages" array.'),
            ]);
        }

        $name = trim((string) ($request->input('project_name', '')));
        if ($name === '') {
            $name = (string) (Arr::get($templateData, 'name', 'Imported template'));
        }

        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'template_id' => null,
            'theme_preset' => Arr::get($templateData, 'theme_preset'),
            'last_viewed_at' => now(),
        ]);

        $options = [];
        if ($request->has('provision_demo_store')) {
            $options['provision_demo_store'] = (bool) $request->boolean('provision_demo_store');
        }
        app(SiteProvisioningService::class)->provisionFromReadyTemplate($project, $templateData, $options);

        return $this->inertiaAwareRedirect($request, route('project.cms', $project));
    }

    /**
     * Create a minimal project and redirect to requirement collection (Q&A then generate site).
     */
    public function storeForRequirementCollection(Request $request): RedirectResponse|SymfonyResponse
    {
        $user = $request->user();
        if (! $user->canCreateMoreProjects()) {
            return back()->withErrors([
                'prompt' => __('You have reached the maximum number of projects. Please upgrade to create more.'),
            ]);
        }

        $name = trim((string) $request->input('project_name', ''));
        if ($name === '') {
            $name = __('My Store');
        }

        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'template_id' => null,
            'theme_preset' => null,
            'requirement_collection_state' => \App\Services\RequirementCollectionService::STATE_COLLECTING,
            'last_viewed_at' => now(),
        ]);

        return $this->inertiaAwareRedirect($request, route('chat', $project));
    }

    private function inertiaAwareRedirect(Request $request, string $url): RedirectResponse|SymfonyResponse
    {
        $request->session()->put(self::PENDING_REDIRECT_URL_SESSION_KEY, $url);
        $request->session()->put(self::PENDING_REDIRECT_AT_SESSION_KEY, now()->toIso8601String());

        return redirect()->to($url);
    }

    /**
     * Export project as template JSON (theme_preset + default_pages) for backup or reuse.
     */
    public function exportTemplate(Request $request, Project $project): StreamedResponse|HttpResponse
    {
        $this->authorize('view', $project);

        $repository = app(CmsRepositoryContract::class);
        $site = $repository->findSiteByProject($project);
        if (! $site) {
            return response()->json(['error' => 'Site not found'], 404);
        }

        $pages = $repository->listPages($site);
        $defaultPages = [];
        foreach ($pages as $page) {
            $revision = $page->revisions()->orderByDesc('version')->first();
            $content = $revision?->content_json ?? [];
            $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
            $sectionRows = [];
            foreach ($sections as $sec) {
                if (! is_array($sec)) {
                    continue;
                }
                $type = trim((string) ($sec['type'] ?? ''));
                if ($type === '') {
                    continue;
                }
                $sectionRows[] = [
                    'key' => $type,
                    'enabled' => true,
                    'props' => is_array($sec['props'] ?? null) ? $sec['props'] : [],
                ];
            }
            $defaultPages[] = [
                'slug' => $page->slug,
                'title' => $page->title,
                'sections' => $sectionRows,
            ];
        }

        $export = [
            'theme_preset' => $project->theme_preset ?? 'default',
            'name' => $project->name,
            'slug' => str_replace(' ', '-', strtolower($project->name)),
            'default_pages' => $defaultPages,
        ];

        $filename = 'template-' . preg_replace('/[^a-z0-9_-]/', '-', strtolower($project->name)) . '-' . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(
            function () use ($export) {
                echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            },
            $filename,
            ['Content-Type' => 'application/json']
        );
    }

    public function trash(Request $request): Response
    {
        $user = $request->user();
        $search = $request->get('search');
        $sort = $request->get('sort', 'last-edited');

        $query = $user->projects()
            ->onlyTrashed()
            ->with('user');

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $query = match ($sort) {
            'name' => $query->orderBy('name', 'asc'),
            'created' => $query->orderBy('created_at', 'desc'),
            default => $query->orderBy('deleted_at', 'desc'),
        };

        // Paginate
        $projects = $query->paginate(12)->withQueryString();

        $counts = [
            'all' => $user->projects()->count(),
            'favorites' => $user->projects()->where('is_starred', true)->count(),
            'trash' => $user->projects()->onlyTrashed()->count(),
        ];

        $filters = [
            'search' => $search,
            'sort' => $sort,
            'visibility' => null, // Not applicable for trash
        ];

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'counts' => $counts,
            'activeTab' => 'trash',
            'filters' => $filters,
            'baseDomain' => SystemSetting::get('domain_base_domain', config('app.base_domain', 'example.com')),
        ]);
    }

    public function toggleStar(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update(['is_starred' => ! $project->is_starred]);

        return back();
    }

    public function duplicate(Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        // Check project limit
        if (! request()->user()->canCreateMoreProjects()) {
            $plan = request()->user()->getCurrentPlan();
            $maxProjects = $plan ? $plan->getMaxProjects() : 0;

            return back()->withErrors([
                'project' => $maxProjects === 0
                    ? 'Your plan does not include project creation. Please upgrade your plan to create projects.'
                    : "You have reached the maximum number of projects ({$maxProjects}) allowed by your plan. Please upgrade to create more projects.",
            ]);
        }

        $newProject = $project->duplicate(request()->user());

        return redirect()->route('projects.index')
            ->with('message', "Project duplicated as '{$newProject->name}'");
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return back()->with('message', 'Project moved to trash');
    }

    public function restore(Project $project): RedirectResponse
    {
        $this->authorize('restore', $project);

        $project->restore();

        return redirect()->route('projects.index')
            ->with('message', 'Project restored successfully');
    }

    public function forceDelete(Project $project): RedirectResponse
    {
        $this->authorize('forceDelete', $project);

        $project->forceDelete();

        return back()->with('message', 'Project permanently deleted');
    }
}
