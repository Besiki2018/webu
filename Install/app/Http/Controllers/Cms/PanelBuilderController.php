<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Site;
use App\Models\Template;
use App\Support\OwnedTemplateCatalog;
use App\Models\WebsitePage;
use App\Services\CmsBindingExpressionValidator;
use App\Services\CmsSectionBindingService;
use App\Services\SiteProvisioningService;
use App\Services\UniversalCmsSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PanelBuilderController extends Controller
{
    public function __construct(
        protected SiteProvisioningService $siteProvisioning,
        protected CmsSectionBindingService $sectionBindings,
        protected CmsBindingExpressionValidator $bindingValidator,
        protected UniversalCmsSyncService $universalCmsSync
    ) {}

    public function templates(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $owner = $site->project?->user;
        $templatesQuery = Template::query();
        if (! $owner?->hasAdminBypass()) {
            $templatesQuery->forPlan($owner?->getCurrentPlan());
        }

        $currentTemplateId = (int) ($site->project?->template_id ?? 0);
        $templatesQuery->where(function ($query) use ($currentTemplateId): void {
            $query->whereIn('slug', OwnedTemplateCatalog::slugs());
            if ($currentTemplateId > 0) {
                $query->orWhere('id', $currentTemplateId);
            }
        });

        $templates = $templatesQuery
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'description', 'category', 'is_system', 'metadata']);

        return response()->json([
            'site_id' => $site->id,
            'project_id' => $site->project_id,
            'current_template_id' => $site->project?->template_id,
            'templates' => $templates->map(fn (Template $template): array => [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'is_system' => (bool) $template->is_system,
                'module_flags' => data_get($template->metadata, 'module_flags', []),
                'default_pages' => data_get($template->metadata, 'default_pages', []),
            ])->values(),
        ]);
    }

    public function applyTemplate(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:templates,id'],
            'theme_preset' => ['nullable', 'string', 'in:default,arctic,summer,fragrant,slate,feminine,forest,midnight,coral,mocha,ocean,ruby,luxury_minimal,corporate_clean,bold_startup,soft_pastel,dark_modern,creative_portfolio'],
            'reset_existing_content' => ['sometimes', 'boolean'],
        ]);

        $template = Template::query()->findOrFail((int) $validated['template_id']);
        $owner = $site->project?->user;
        $currentTemplateId = (int) ($site->project?->template_id ?? 0);

        if ($template->id !== $currentTemplateId && ! OwnedTemplateCatalog::contains((string) $template->slug)) {
            throw ValidationException::withMessages([
                'template_id' => 'The selected template is not available in the active owned catalog.',
            ]);
        }

        if ($owner && ! $owner->hasAdminBypass() && ! $template->isAvailableForPlan($owner->getCurrentPlan())) {
            throw ValidationException::withMessages([
                'template_id' => 'The selected template is not available for this project owner plan.',
            ]);
        }

        $resetExisting = (bool) ($validated['reset_existing_content'] ?? true);

        DB::transaction(function () use ($site, $template, $validated, $resetExisting): void {
            $project = $site->project()->firstOrFail();

            $payload = [
                'template_id' => $template->id,
            ];
            if (array_key_exists('theme_preset', $validated)) {
                $payload['theme_preset'] = $validated['theme_preset'];
            }
            $project->update($payload);

            if ($resetExisting) {
                PageRevision::query()->where('site_id', $site->id)->delete();
                Page::query()->where('site_id', $site->id)->delete();
                $site->menus()->delete();
            }

            $this->siteProvisioning->provisionForProject($project->fresh());
        });

        $freshSite = Site::query()
            ->with(['pages:id,site_id,slug,title,status'])
            ->findOrFail($site->id);

        return response()->json([
            'message' => 'Template applied successfully.',
            'site_id' => $freshSite->id,
            'project_id' => $freshSite->project_id,
            'template_id' => $template->id,
            'pages' => $freshSite->pages
                ->map(fn (Page $page): array => [
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'title' => $page->title,
                    'status' => $page->status,
                ])
                ->values(),
        ]);
    }

    public function mutateSections(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:add,remove,duplicate,reorder'],
            'page_id' => ['nullable', 'integer'],
            'page_slug' => ['nullable', 'string', 'max:191'],
            'section' => ['nullable', 'array'],
            'index' => ['nullable', 'integer', 'min:0'],
            'from_index' => ['nullable', 'integer', 'min:0'],
            'to_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $page = $this->resolvePage($site, $validated);

        if (! $page) {
            return response()->json([
                'error' => 'Page not found for this site.',
            ], 404);
        }

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        $content = is_array($latestRevision?->content_json)
            ? $latestRevision->content_json
            : ['sections' => []];

        $sections = is_array($content['sections'] ?? null)
            ? array_values($content['sections'])
            : [];

        $action = (string) $validated['action'];
        switch ($action) {
            case 'add':
                $section = is_array($validated['section'] ?? null) ? $validated['section'] : [];
                $type = trim((string) ($section['type'] ?? ''));
                if ($type === '') {
                    throw ValidationException::withMessages([
                        'section.type' => 'Section type is required for add action.',
                    ]);
                }

                $sectionPayload = $this->sectionBindings->buildSectionPayload(
                    $type,
                    is_array($section['props'] ?? null) ? $section['props'] : []
                );

                $index = isset($validated['index']) ? (int) $validated['index'] : count($sections);
                $index = max(0, min($index, count($sections)));
                array_splice($sections, $index, 0, [$sectionPayload]);
                break;

            case 'remove':
                $index = isset($validated['index']) ? (int) $validated['index'] : -1;
                if (! array_key_exists($index, $sections)) {
                    throw ValidationException::withMessages([
                        'index' => 'Section index is invalid for remove action.',
                    ]);
                }

                array_splice($sections, $index, 1);
                break;

            case 'duplicate':
                $index = isset($validated['index']) ? (int) $validated['index'] : -1;
                if (! array_key_exists($index, $sections)) {
                    throw ValidationException::withMessages([
                        'index' => 'Section index is invalid for duplicate action.',
                    ]);
                }

                $duplicate = $sections[$index];
                $targetIndex = isset($validated['to_index']) ? (int) $validated['to_index'] : ($index + 1);
                $targetIndex = max(0, min($targetIndex, count($sections)));
                array_splice($sections, $targetIndex, 0, [$duplicate]);
                break;

            case 'reorder':
                $fromIndex = isset($validated['from_index']) ? (int) $validated['from_index'] : -1;
                $toIndex = isset($validated['to_index']) ? (int) $validated['to_index'] : -1;

                if (! array_key_exists($fromIndex, $sections)) {
                    throw ValidationException::withMessages([
                        'from_index' => 'from_index is invalid for reorder action.',
                    ]);
                }

                $toIndex = max(0, min($toIndex, count($sections) - 1));
                $moving = $sections[$fromIndex];
                array_splice($sections, $fromIndex, 1);
                array_splice($sections, $toIndex, 0, [$moving]);
                break;
        }

        $content['sections'] = array_values(array_map(function ($section): array {
            if (! is_array($section)) {
                return [];
            }

            $type = trim((string) ($section['type'] ?? ''));
            $props = is_array($section['props'] ?? null) ? $section['props'] : [];
            if ($type === '') {
                return [];
            }

            $payload = $this->sectionBindings->buildSectionPayload($type, $props);
            if (is_array($section['binding'] ?? null)) {
                $payload['binding'] = array_replace($payload['binding'], $section['binding']);
            }

            return $payload;
        }, $sections));
        $content['sections'] = array_values(array_filter($content['sections'], static fn (array $section): bool => $section !== []));
        $nextVersion = ((int) ($latestRevision?->version ?? 0)) + 1;

        $revision = PageRevision::query()->create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'version' => $nextVersion,
            'content_json' => $content,
            'created_by' => $request->user()?->id,
            'published_at' => null,
        ]);

        $websitePage = WebsitePage::query()
            ->where('page_id', $page->id)
            ->whereHas('website', fn ($q) => $q->where('site_id', $site->id))
            ->first();
        if ($websitePage) {
            $this->universalCmsSync->syncSectionsFromPageRevision($page, $websitePage);
        }

        return response()->json([
            'message' => 'Section mutation applied.',
            'page_id' => $page->id,
            'revision' => [
                'id' => $revision->id,
                'version' => $revision->version,
                'content_json' => $revision->content_json,
            ],
            'binding_validation' => $this->bindingValidator->validateContentJson(
                is_array($revision->content_json) ? $revision->content_json : []
            ),
        ]);
    }

    public function updateStyles(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'theme_preset' => ['nullable', 'string', 'in:default,arctic,summer,fragrant,slate,feminine,forest,midnight,coral,mocha,ocean,ruby,luxury_minimal,corporate_clean,bold_startup,soft_pastel,dark_modern,creative_portfolio'],
            'theme_settings' => ['nullable', 'array'],
        ]);

        $currentSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $incoming = is_array($validated['theme_settings'] ?? null) ? $validated['theme_settings'] : [];
        $nextSettings = array_replace_recursive($currentSettings, $incoming);

        if (array_key_exists('theme_preset', $validated)) {
            $nextSettings['preset'] = $validated['theme_preset'] ?? 'default';
            $site->project?->update([
                'theme_preset' => $validated['theme_preset'],
            ]);
        }

        $site->update([
            'theme_settings' => $nextSettings,
        ]);

        return response()->json([
            'message' => 'Styles updated.',
            'site_id' => $site->id,
            'theme_settings' => $site->fresh()->theme_settings ?? [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolvePage(Site $site, array $validated): ?Page
    {
        if (isset($validated['page_id'])) {
            return Page::query()
                ->where('site_id', $site->id)
                ->where('id', (int) $validated['page_id'])
                ->first();
        }

        if (! empty($validated['page_slug'])) {
            return Page::query()
                ->where('site_id', $site->id)
                ->where('slug', (string) $validated['page_slug'])
                ->first();
        }

        return null;
    }
}
