<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Contracts\CmsPanelPageServiceContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Site;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Services\CmsBindingExpressionValidator;
use App\Services\UniversalCmsSyncService;
use App\Cms\Services\CmsSiteVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelPageController extends Controller
{
    public function __construct(
        protected CmsPanelPageServiceContract $pages,
        protected CmsBindingExpressionValidator $bindingValidator,
        protected UniversalCmsSyncService $cmsSync,
        protected CmsSiteVisibilityService $siteVisibility
    ) {}

    /**
     * List pages for a site.
     */
    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->pages->listPages($site));
    }

    /**
     * Hydrate page from Universal CMS (sections + theme). For builder "Load from CMS" option.
     */
    public function hydrateFromCms(Site $site, Page $page): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $website = Website::query()->where('site_id', $site->id)->first();
        if (! $website) {
            return response()->json(['error' => 'No CMS website linked to this site.'], 404);
        }

        $websitePage = WebsitePage::query()
            ->where('website_id', $website->id)
            ->where('page_id', $page->id)
            ->first();
        if (! $websitePage) {
            return response()->json(['error' => 'Page not linked in CMS.'], 404);
        }

        $sections = $this->cmsSync->buildContentSectionsFromPageSections($websitePage);
        $theme = $website->theme ?? [];

        return response()->json([
            'pageId' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'sections' => $sections,
            'theme' => $theme,
        ]);
    }

    /**
     * Get a single page with latest and published revision payload.
     */
    public function show(Request $request, Site $site, Page $page): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        return response()->json($this->pages->getPageDetails($site, $page, $validated['locale'] ?? null));
    }

    /**
     * Create a page with initial revision.
     */
    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $actorId = $request->user()?->id ?? auth()->id();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages')->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:2000'],
            'content_json' => ['nullable', 'array'],
        ]);

        $page = $this->pages->createPage($site, $validated, $actorId);

        return response()->json([
            'message' => 'Page created successfully.',
            'page' => $page,
        ], 201);
    }

    /**
     * Update page metadata.
     */
    public function update(Request $request, Site $site, Page $page): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages')
                    ->ignore($page->id)
                    ->where(fn ($query) => $query->where('site_id', $site->id)),
            ],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:2000'],
        ]);

        $updatedPage = $this->pages->updatePage($site, $page, $validated);

        return response()->json([
            'message' => 'Page updated successfully.',
            'page' => $updatedPage,
        ]);
    }

    /**
     * Delete a page and its revisions.
     * Required ecommerce pages (home, shop, product, cart, checkout, contact) cannot be deleted for ecommerce projects.
     */
    public function destroy(Site $site, Page $page): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $requiredSlugs = config('ecommerce-required-pages.slugs', []);
        $slugNormalized = strtolower(trim((string) $page->slug));
        if (is_array($requiredSlugs) && $slugNormalized !== '' && in_array($slugNormalized, array_map('strtolower', $requiredSlugs), true)) {
            if ($this->siteVisibility->hasCapability($site, 'ecommerce')) {
                return response()->json([
                    'message' => __('This page is required for ecommerce and cannot be deleted.'),
                    'code' => 'required_page_deletion_forbidden',
                ], 422);
            }
        }

        $this->pages->deletePage($site, $page);

        return response()->json([
            'message' => 'Page deleted successfully.',
        ]);
    }

    /**
     * Create new page revision (draft).
     */
    public function storeRevision(Request $request, Site $site, Page $page): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $actorId = $request->user()?->id ?? auth()->id();

        $validated = $request->validate([
            'content_json' => ['required', 'array'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $revision = $this->pages->createRevision(
            $site,
            $page,
            $validated,
            $actorId,
            $validated['locale'] ?? null
        );

        return response()->json([
            'message' => 'Page revision created successfully.',
            'revision' => [
                'id' => $revision->id,
                'version' => $revision->version,
                'content_json' => $revision->content_json,
                'created_at' => $revision->created_at?->toISOString(),
            ],
            'binding_validation' => $this->bindingValidator->validateContentJson(
                is_array($revision->content_json) ? $revision->content_json : []
            ),
        ], 201);
    }

    /**
     * Publish a page revision.
     */
    public function publish(Request $request, Site $site, Page $page): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'revision_id' => ['nullable', 'integer'],
        ]);

        try {
            $revision = $this->pages->publish($site, $page, $validated['revision_id'] ?? null);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Page published successfully.',
            'page_id' => $page->id,
            'revision_id' => $revision->id,
            'published_at' => $revision->published_at?->toISOString(),
        ]);
    }
}
