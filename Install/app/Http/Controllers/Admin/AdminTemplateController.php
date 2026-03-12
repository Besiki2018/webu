<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ChecksDemoMode;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\Template;
use App\Services\SiteProvisioningService;
use App\Services\TemplateDemoService;
use App\Support\OwnedTemplateCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use ZipArchive;

class AdminTemplateController extends Controller
{
    use ChecksDemoMode;

    /**
     * Display a listing of templates.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(5, min($perPage, 100));
        $templates = Template::with('plans')
            ->whereIn('slug', OwnedTemplateCatalog::slugs())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Templates/Index', [
            'templates' => $templates,
            'plans' => Plan::orderBy('sort_order')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created template.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail' => 'nullable|image|max:2048',
            'zip_file' => 'required|file|mimes:zip|max:10240', // 10MB max
            'plan_ids' => 'nullable|array',
            'plan_ids.*' => 'exists:plans,id',
        ]);

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('thumbnails', 'public');
            $validated['thumbnail'] = $path;
        }

        // Store zip file
        $zipPath = $request->file('zip_file')->store('templates', 'local');

        // Extract and validate template.json
        $metadata = null;
        $zip = new ZipArchive;

        $fullZipPath = storage_path('app/'.$zipPath);
        if ($zip->open($fullZipPath) === true) {
            $jsonContent = $zip->getFromName('template.json');
            if ($jsonContent) {
                $decoded = json_decode($jsonContent, true);
                if ($decoded && is_array($decoded)) {
                    $metadata = $decoded;
                }
            }
            $zip->close();
        }

        // Create template record
        $template = Template::create([
            'slug' => Str::slug($request->name),
            'name' => $request->name,
            'description' => $request->description,
            'thumbnail' => $validated['thumbnail'] ?? null,
            'zip_path' => $zipPath,
            'metadata' => $metadata,
        ]);

        // Sync plan assignments
        $template->plans()->sync($request->input('plan_ids', []));

        return redirect()->route('admin.templates')
            ->with('success', 'Template uploaded successfully');
    }

    /**
     * Update the specified template.
     */
    public function update(Request $request, Template $template): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail' => 'nullable|image|max:2048',
            'zip_file' => 'nullable|file|mimes:zip|max:10240',
            'plan_ids' => 'nullable|array',
            'plan_ids.*' => 'exists:plans,id',
        ]);

        // Prevent editing system templates
        if ($template->is_system) {
            return redirect()
                ->route('admin.templates')
                ->with('error', 'System templates cannot be modified.');
        }

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail
            if ($template->thumbnail) {
                Storage::disk('public')->delete($template->thumbnail);
            }
            $validated['thumbnail'] = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        // Handle new zip file
        $metadata = $template->metadata;
        if ($request->hasFile('zip_file')) {
            // Delete old zip file (use raw value since accessor adds full path)
            if ($template->getRawOriginal('zip_path')) {
                Storage::disk('local')->delete($template->getRawOriginal('zip_path'));
            }

            $zipPath = $request->file('zip_file')->store('templates', 'local');

            // Extract metadata from new zip
            $zip = new ZipArchive;
            $fullZipPath = storage_path('app/'.$zipPath);
            if ($zip->open($fullZipPath) === true) {
                $jsonContent = $zip->getFromName('template.json');
                if ($jsonContent) {
                    $decoded = json_decode($jsonContent, true);
                    if ($decoded && is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }
                $zip->close();
            }

            $validated['zip_path'] = $zipPath;
        }

        $template->update([
            'name' => $request->name,
            'description' => $request->description,
            'thumbnail' => $validated['thumbnail'] ?? $template->thumbnail,
            'zip_path' => $validated['zip_path'] ?? $template->zip_path,
            'metadata' => $metadata,
        ]);

        // Sync plan assignments
        $template->plans()->sync($request->input('plan_ids', []));

        return redirect()->route('admin.templates')
            ->with('success', 'Template updated successfully');
    }

    /**
     * Remove the specified template.
     */
    public function destroy(Request $request, Template $template): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        try {
            // Delete zip file (use raw value since accessor adds full path)
            if ($template->getRawOriginal('zip_path')) {
                Storage::disk('local')->delete($template->getRawOriginal('zip_path'));
            }

            // Delete thumbnail
            if ($template->thumbnail) {
                Storage::disk('public')->delete($template->thumbnail);
            }

            $template->delete();

            return redirect()->route('admin.templates')
                ->with('success', 'Template deleted successfully');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.templates')
                ->with('error', 'System templates cannot be deleted.');
        }
    }

    /**
     * Get metadata for a template (JSON response).
     */
    public function metadata(Request $request, Template $template): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return response()->json($template->metadata ?? [
            'error' => 'No metadata available',
        ]);
    }

    /**
     * Render full demo page for a template.
     */
    public function demo(Request $request, Template $template, TemplateDemoService $templateDemoService): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'page' => 'nullable|string|max:120',
        ]);
        $requestedPage = isset($validated['page']) ? (string) $validated['page'] : null;
        // Always use template default (no site) so admin sees read-only demo; only project-scoped demo is editable.
        $site = null;

        $demo = $templateDemoService->buildPayload(
            $template,
            $requestedPage,
            $site,
            null,
            false
        );

        return Inertia::render('Admin/Templates/Demo', [
            'template' => $demo['template'],
            'demo' => $demo,
        ]);
    }

    /**
     * Get backend demo payload (used by live page switching in demo UI).
     */
    public function demoData(Request $request, Template $template, TemplateDemoService $templateDemoService): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'page' => 'nullable|string|max:120',
        ]);
        $requestedPage = isset($validated['page']) ? (string) $validated['page'] : null;
        // Always use template default (no site) so admin cannot edit demo content.
        $site = null;

        $demo = $templateDemoService->buildPayload(
            $template,
            $requestedPage,
            $site,
            null,
            false
        );

        return response()->json($demo);
    }

    /**
     * Redirect to template live demo page when available.
     */
    public function liveDemo(Request $request, Template $template): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $metadata = is_array($template->metadata) ? $template->metadata : [];
        $liveDemoPath = $this->resolveLiveDemoPath($metadata);

        if ($liveDemoPath !== null) {
            // No site= so demo is read-only; only project builder can edit.
            return redirect($this->appendDemoQueryParams($liveDemoPath, null));
        }

        // Redirect to template default demo (no site=) so demo is read-only; only project builder can edit.
        $params = ['templateSlug' => $template->slug, 'slug' => 'home'];

        return redirect()->route('template-demos.show', $params);
    }

    /**
     * Redirect admin to the unified project CMS panel for this template demo site.
     */
    public function liveAdmin(Request $request, Template $template): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $site = $this->resolveBestDemoSite($template);
        if (! $site) {
            $site = $this->createDemoSiteForTemplate($template, $request);
        }

        if (! $site || ! $site->project_id) {
            return redirect()
                ->route('admin.templates')
                ->with('error', 'Unable to open site admin for this template. Please try again.');
        }

        return redirect()->route('project.cms', ['project' => $site->project_id]);
    }

    /**
     * Redirect admin to the unified project CMS panel for this template demo site and open visual builder.
     */
    public function liveBuilder(Request $request, Template $template): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $site = $this->resolveBestDemoSite($template);
        if (! $site) {
            $site = $this->createDemoSiteForTemplate($template, $request);
        }

        if (! $site || ! $site->project_id) {
            return redirect()
                ->route('admin.templates')
                ->with('error', 'Unable to open site builder for this template. Please try again.');
        }

        return redirect()->route('project.cms', [
            'project' => $site->project_id,
            'tab' => 'editor',
        ]);
    }

    /**
     * Resolve live demo path only when the configured target really exists.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function resolveLiveDemoPath(array $metadata): ?string
    {
        $configured = trim((string) data_get($metadata, 'live_demo.path', ''));
        if ($configured === '') {
            return null;
        }

        $normalized = ltrim($configured, '/');
        $absolute = public_path($normalized);

        if (is_file($absolute)) {
            return '/'.$normalized;
        }

        if (is_dir($absolute) && is_file(rtrim($absolute, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'index.html')) {
            return '/'.trim($normalized, '/').'/index.html';
        }

        return null;
    }

    private function resolvePublicDemoSiteId(Template $template): ?string
    {
        $site = $this->resolveBestDemoSite($template, true);

        return $site?->id;
    }

    private function resolveBestDemoSite(Template $template, bool $onlyPublicPublished = false): ?Site
    {
        $baseQuery = Site::query()
            ->whereHas('project', function ($query) use ($template): void {
                $query->where('template_id', $template->id);
            });

        if ($onlyPublicPublished) {
            return (clone $baseQuery)
                ->whereHas('project', function ($query): void {
                    $query
                        ->whereNotNull('published_at')
                        ->where('published_visibility', 'public');
                })
                ->latest('updated_at')
                ->first();
        }

        $publicSite = (clone $baseQuery)
            ->whereHas('project', function ($query): void {
                $query
                    ->whereNotNull('published_at')
                    ->where('published_visibility', 'public');
            })
            ->latest('updated_at')
            ->first();

        if ($publicSite) {
            return $publicSite;
        }

        return (clone $baseQuery)->latest('updated_at')->first();
    }

    private function createDemoSiteForTemplate(Template $template, Request $request): ?Site
    {
        $admin = $request->user();
        if (! $admin) {
            return null;
        }

        $timestamp = Carbon::now()->format('Y-m-d H:i');
        $project = Project::query()->create([
            'user_id' => $admin->id,
            'template_id' => $template->id,
            'name' => trim($template->name.' Demo '.$timestamp),
            'description' => 'Auto-provisioned demo project for template live admin access.',
            'is_public' => true,
            'published_visibility' => 'public',
            'published_at' => Carbon::now(),
        ]);

        return app(SiteProvisioningService::class)->provisionForProject($project->fresh());
    }

    private function resolveOrCreateDemoSite(Template $template, Request $request): ?Site
    {
        $site = $this->resolveBestDemoSite($template);
        if ($site) {
            return $site;
        }

        return $this->createDemoSiteForTemplate($template, $request);
    }

    private function appendDemoQueryParams(string $path, ?string $siteId): string
    {
        if ($siteId === null || trim($siteId) === '') {
            return $path;
        }

        $separator = str_contains($path, '?') ? '&' : '?';

        return $path.$separator.http_build_query([
            'site' => $siteId,
            'slug' => 'home',
        ]);
    }
}
