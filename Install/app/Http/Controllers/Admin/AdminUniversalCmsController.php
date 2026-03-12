<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Http\Controllers\Controller;
use App\Models\PageSection;
use App\Models\Site;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Repositories\TenantScoped\PageSectionRepository;
use App\Repositories\TenantScoped\WebsitePageRepository;
use App\Support\TenancyContext;
use App\Services\AiWebsiteGeneration\WebsiteUndoService;
use App\Services\UniversalCmsCleanupService;
use App\Services\UniversalCmsMediaService;
use App\Services\UniversalCmsSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin: Universal CMS — Websites → Pages → Sections.
 * Ensures every AI-generated website is editable without technical knowledge.
 */
class AdminUniversalCmsController extends Controller
{
    public function __construct(
        protected UniversalCmsSyncService $sync,
        protected UniversalCmsMediaService $media,
        protected UniversalCmsCleanupService $cleanup,
        protected WebsiteUndoService $undo,
        protected CmsRepositoryContract $repository,
        protected TenancyContext $tenancy,
        protected WebsitePageRepository $websitePageRepo,
        protected PageSectionRepository $sectionRepo
    ) {}

    /**
     * List all websites (with optional sync from sites).
     */
    public function index(Request $request): Response
    {
        $websites = Website::query()
            ->withCount('websitePages')
            ->with('site:id,name,status')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Admin/UniversalCms/WebsitesIndex', [
            'websites' => $websites,
            'title' => __('My Websites'),
        ]);
    }

    /**
     * Sync websites from existing sites (one-time or refresh).
     */
    public function syncFromSites(Request $request): \Illuminate\Http\RedirectResponse
    {
        $sites = Site::query()->with('pages.revisions')->get();

        foreach ($sites as $site) {
            $this->sync->syncPagesAndSectionsFromSite($site);
        }

        return back()->with('success', __('Websites synced from existing sites.'));
    }

    /**
     * List pages of a website.
     */
    public function pages(Website $website): Response
    {
        $this->authorize('view', $website);
        $website->load(['websitePages' => fn ($q) => $q->withCount('sections')->orderBy('order')]);

        return Inertia::render('Admin/UniversalCms/PagesIndex', [
            'website' => $website,
            'pages' => $website->websitePages,
            'title' => __('Pages') . ' — ' . $website->name,
            'undoPreviousVersion' => $this->undo->previousVersion($website),
        ]);
    }

    /**
     * Edit a page: list sections and allow editing each section.
     */
    public function editPage(Website $website, WebsitePage $websitePage): Response
    {
        $this->authorize('view', $website);
        $websitePage->load(['sections' => fn ($q) => $q->orderBy('order')]);

        return Inertia::render('Admin/UniversalCms/PageEdit', [
            'website' => $website->only(['id', 'name', 'domain']),
            'page' => $websitePage,
            'title' => __('Edit page') . ' — ' . $websitePage->title,
            'sectionTypeLabels' => config('universal_cms.section_type_labels', []),
            'undoPreviousVersion' => $this->undo->previousVersion($website),
        ]);
    }

    /**
     * Edit a single section (form with fields by section_type).
     */
    public function editSection(Website $website, WebsitePage $websitePage, PageSection $section): Response
    {
        $this->authorize('view', $website);
        return Inertia::render('Admin/UniversalCms/SectionEdit', [
            'website' => $website->only(['id', 'name', 'domain']),
            'page' => $websitePage->only(['id', 'title', 'slug']),
            'section' => $section->load('websitePage'),
            'title' => __('Edit section') . ' — ' . $section->section_type,
            'mediaBaseUrl' => asset('storage'),
        ]);
    }

    /**
     * Update section settings (from section editor form).
     */
    public function updateSection(Request $request, Website $website, WebsitePage $websitePage, PageSection $section): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $website);
        if ((int) $section->websitePage?->website_id !== (int) $website->id) {
            abort(403, 'Section does not belong to this website.');
        }
        $tenantId = $this->tenancy->tenantId() ?? $website->tenant_id;
        $websiteId = (string) $website->id;

        $settings = $request->validate([
            'title' => 'nullable|string|max:500',
            'subtitle' => 'nullable|string|max:1000',
            'button_text' => 'nullable|string|max:120',
            'button_link' => 'nullable|string|max:500',
            'image' => 'nullable|string|max:500',
            'background_color' => 'nullable|string|max:50',
            'alignment' => 'nullable|string|in:left,center,right',
            'heading' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'spacing' => 'nullable|string|max:50',
            'text_size' => 'nullable|string|max:50',
        ]);

        $this->sync->createSnapshot($website, 'cms_edit', $request->user()?->id);

        $merged = array_merge($section->getSettings(), array_filter($settings, fn ($v) => $v !== null && $v !== ''));
        if ($tenantId !== null) {
            $this->sectionRepo->update($tenantId, $websiteId, (int) $section->id, ['settings_json' => $merged]);
        } else {
            $section->setSettings($merged);
        }

        $this->sync->pushWebsitePageToRevision($websitePage);

        return redirect()
            ->route('admin.universal-cms.page-edit', [$website->id, $websitePage->id])
            ->with('success', __('Section updated.'));
    }

    /**
     * Undo: restore website to previous revision snapshot.
     */
    public function undoRevisions(Website $website): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $website);
        $toVersion = $this->undo->previousVersion($website);
        if ($toVersion === null) {
            return back()->with('error', __('No previous version to restore.'));
        }
        if (! $this->undo->undoToVersion($website, $toVersion)) {
            return back()->with('error', __('Could not restore previous version.'));
        }

        return back()->with('success', __('Restored to previous version.'));
    }

    // ---------- Media ----------

    public function mediaIndex(Website $website): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $website);
        return response()->json(['files' => $this->media->list($website)]);
    }

    /**
     * Full-page media library for the website (upload, delete, list).
     */
    public function mediaLibrary(Website $website): Response
    {
        $this->authorize('view', $website);
        return Inertia::render('Admin/UniversalCms/MediaLibrary', [
            'website' => $website->only(['id', 'name', 'domain']),
            'files' => $this->media->list($website),
            'title' => __('Media library') . ' — ' . $website->name,
            'mediaBaseUrl' => asset('storage'),
        ]);
    }

    public function mediaUpload(Request $request, Website $website): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $website);
        $request->validate(['file' => 'required|file|image|max:5120']);

        $path = $this->media->upload($website, $request->file('file'));

        return response()->json([
            'path' => $path,
            'url' => $this->media->url($path),
        ], 201);
    }

    public function mediaDestroy(Request $request, Website $website): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $website);
        $path = (string) ($request->query('path') ?: $request->input('path'));
        if ($path === '') {
            return response()->json(['error' => __('Path required.')], 422);
        }
        $this->media->delete($website, $path);

        return response()->json(['message' => __('Deleted.')]);
    }

    // ---------- Page management ----------

    public function storePage(Request $request, Website $website): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $website);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|regex:/^[a-z0-9\-]+$/',
        ]);
        $tenantId = $website->tenant_id;
        $maxOrder = $website->websitePages()->max('order') ?? -1;
        $websitePage = $tenantId !== null
            ? $this->websitePageRepo->store($tenantId, (string) $website->id, [
                'title' => $data['title'],
                'slug' => $data['slug'],
                'order' => $maxOrder + 1,
            ])
            : WebsitePage::create([
                'website_id' => $website->id,
                'title' => $data['title'],
                'slug' => $data['slug'],
                'order' => $maxOrder + 1,
            ]);

        if ($website->site_id) {
            $site = Site::query()->find($website->site_id);
            if ($site) {
                $page = $this->repository->firstOrCreatePage($site, $data['slug'], [
                    'title' => $data['title'],
                    'status' => 'draft',
                ]);
                $nextVersion = $this->repository->maxRevisionVersion($site, $page) + 1;
                $this->repository->createRevision($site, $page, [
                    'version' => $nextVersion,
                    'content_json' => ['sections' => []],
                    'created_by' => $request->user()?->id,
                ]);
                $websitePage->update(['page_id' => $page->id]);
            }
        }

        return redirect()
            ->route('admin.universal-cms.pages', $website->id)
            ->with('success', __('Page created.'));
    }

    public function updatePage(Request $request, Website $website, WebsitePage $websitePage): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $website);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|regex:/^[a-z0-9\-]+$/',
        ]);
        $websitePage->update($data);

        if ($websitePage->page_id) {
            $websitePage->page?->update([
                'title' => $data['title'],
                'slug' => $data['slug'],
            ]);
        }

        return redirect()
            ->route('admin.universal-cms.pages', $website->id)
            ->with('success', __('Page updated.'));
    }

    public function destroyPage(Website $website, WebsitePage $websitePage): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $website);
        $websitePage->sections()->delete();
        $websitePage->delete();

        return redirect()
            ->route('admin.universal-cms.pages', $website->id)
            ->with('success', __('Page deleted.'));
    }

    public function reorderPages(Request $request, Website $website): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $website);
        $order = $request->validate(['order' => 'required|array', 'order.*' => 'integer'])['order'];
        foreach ($order as $position => $id) {
            WebsitePage::query()->where('website_id', $website->id)->where('id', $id)->update(['order' => $position]);
        }

        return response()->json(['message' => __('Order saved.')]);
    }

    /**
     * Run cleanup: remove dummy content and test images from generated sites.
     */
    public function cleanup(Request $request): \Illuminate\Http\RedirectResponse
    {
        $websiteId = $request->query('website');
        if ($websiteId) {
            $website = Website::query()->find($websiteId);
            if ($website) {
                $result = $this->cleanup->cleanupWebsite($website);

                return back()->with('success', __('Cleanup done. Removed :sections sections and :media images.', [
                    'sections' => $result['removed_sections'],
                    'media' => $result['removed_media'],
                ]));
            }
        }
        $result = $this->cleanup->cleanupAll();

        return back()->with('success', __('Cleanup done. Removed :sections sections and :media images.', [
            'sections' => $result['removed_sections'],
            'media' => $result['removed_media'],
        ]));
    }
}
