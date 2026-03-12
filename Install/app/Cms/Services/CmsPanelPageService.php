<?php

namespace App\Cms\Services;

use App\Cms\Contracts\CmsPanelPageServiceContract;
use App\Cms\Contracts\CmsRepositoryContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Cms\Support\LocalizedCmsPayload;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Site;
use App\Models\WebsitePage;
use App\Services\CmsBuilderDeltaCaptureService;
use App\Services\CmsLocaleResolver;
use App\Services\CmsSectionBindingService;
use App\Services\UniversalCmsSyncService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CmsPanelPageService implements CmsPanelPageServiceContract
{
    public function __construct(
        protected CmsRepositoryContract $repository,
        protected LocalizedCmsPayload $localizedPayload,
        protected CmsSectionBindingService $sectionBindings,
        protected CmsBuilderDeltaCaptureService $builderDeltaCapture,
        protected UniversalCmsSyncService $universalCmsSync,
        protected CmsSiteVisibilityService $siteVisibility
    ) {}

    public function listPages(Site $site): array
    {
        $visiblePageIds = collect($this->siteVisibility->visiblePages($site))
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $pages = $this->repository->listPages($site)
            ->filter(static fn (Page $page): bool => in_array($page->id, $visiblePageIds, true))
            ->map(function (Page $page) use ($site): array {
                $latestRevision = $this->repository->latestRevision($site, $page);
                $publishedRevision = $this->repository->latestPublishedRevision($site, $page);

                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'status' => $page->status,
                    'seo_title' => $page->seo_title,
                    'seo_description' => $page->seo_description,
                    'latest_revision' => $latestRevision ? [
                        'id' => $latestRevision->id,
                        'version' => $latestRevision->version,
                        'created_at' => $latestRevision->created_at?->toISOString(),
                    ] : null,
                    'published_revision' => $publishedRevision ? [
                        'id' => $publishedRevision->id,
                        'version' => $publishedRevision->version,
                        'published_at' => $publishedRevision->published_at?->toISOString(),
                    ] : null,
                    'created_at' => $page->created_at?->toISOString(),
                    'updated_at' => $page->updated_at?->toISOString(),
                ];
            })
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'pages' => $pages,
        ];
    }

    public function getPageDetails(Site $site, Page $page, ?string $requestedLocale = null): array
    {
        $this->ensurePageBelongsToSite($site, $page);

        $siteLocale = $this->localizedPayload->normalizeLocale($site->locale, CmsLocaleResolver::PRIMARY_LOCALE);
        $resolvedLocale = $this->localizedPayload->normalizeLocale($requestedLocale, $siteLocale);
        $latestRevision = $this->repository->latestRevision($site, $page);
        $publishedRevision = $this->repository->latestPublishedRevision($site, $page);
        $latestSerialized = $this->serializeRevision($latestRevision, $resolvedLocale, $siteLocale);
        $publishedSerialized = $this->serializeRevision($publishedRevision, $resolvedLocale, $siteLocale);
        $availableLocales = $this->localizedPayload->mergeLocaleList(
            [
                'locales' => array_values(array_unique(array_filter([
                    ...($latestSerialized['meta']['available_locales'] ?? []),
                    ...($publishedSerialized['meta']['available_locales'] ?? []),
                ]))),
            ],
            [],
            $siteLocale
        );

        return [
            'site_id' => $site->id,
            'locale' => $resolvedLocale,
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'created_at' => $page->created_at?->toISOString(),
                'updated_at' => $page->updated_at?->toISOString(),
            ],
            'latest_revision' => $latestSerialized,
            'published_revision' => $publishedSerialized,
            'meta' => [
                'requested_locale' => $requestedLocale,
                'resolved_locale' => $resolvedLocale,
                'fallback_locale' => CmsLocaleResolver::PRIMARY_LOCALE,
                'secondary_fallback_locale' => CmsLocaleResolver::SECONDARY_FALLBACK_LOCALE,
                'site_locale' => $siteLocale,
                'available_locales' => $availableLocales,
            ],
        ];
    }

    public function createPage(Site $site, array $payload, ?int $actorId): Page
    {
        $created = DB::transaction(function () use ($site, $payload, $actorId): Page {
            $page = $this->repository->createPage($site, [
                'title' => $payload['title'],
                'slug' => $payload['slug'],
                'status' => 'draft',
                'seo_title' => $payload['seo_title'] ?? null,
                'seo_description' => $payload['seo_description'] ?? null,
            ]);

            $this->repository->createRevision($site, $page, [
                'version' => 1,
                'content_json' => $this->hydrateSectionBindingsInContentJson(
                    is_array($payload['content_json'] ?? null) ? $payload['content_json'] : ['sections' => []]
                ),
                'created_by' => $actorId,
                'published_at' => null,
            ]);

            return $page;
        });

        return $created->fresh();
    }

    public function updatePage(Site $site, Page $page, array $payload): Page
    {
        $this->ensurePageBelongsToSite($site, $page);

        return $this->repository->updatePage($page, $payload);
    }

    public function deletePage(Site $site, Page $page): void
    {
        $this->ensurePageBelongsToSite($site, $page);
        $this->repository->deletePage($page);
    }

    public function createRevision(Site $site, Page $page, array $payload, ?int $actorId, ?string $locale = null): PageRevision
    {
        $this->ensurePageBelongsToSite($site, $page);

        $nextVersion = $this->repository->maxRevisionVersion($site, $page) + 1;
        $siteLocale = $this->localizedPayload->normalizeLocale($site->locale, CmsLocaleResolver::PRIMARY_LOCALE);
        $targetLocale = $locale !== null
            ? $this->localizedPayload->normalizeLocale($locale, $siteLocale)
            : null;
        $latestRevision = $this->repository->latestRevision($site, $page);
        $contentJson = $payload['content_json'];

        if (is_array($contentJson) && $locale !== null) {
            $contentJson = $this->localizedPayload->mergeForLocale(
                $latestRevision?->content_json ?? [],
                $targetLocale,
                $contentJson,
                $siteLocale
            );
        }

        if (is_array($contentJson)) {
            $contentJson = $this->hydrateSectionBindingsInContentJson($contentJson);
        }

        $revision = $this->repository->createRevision($site, $page, [
            'version' => $nextVersion,
            'content_json' => $contentJson,
            'created_by' => $actorId,
            'published_at' => null,
        ]);

        try {
            $this->builderDeltaCapture->captureAfterManualRevisionSave(
                $site,
                $page,
                $latestRevision,
                $revision,
                $actorId,
                $targetLocale,
                $siteLocale
            );
        } catch (\Throwable $exception) {
            Log::warning('cms.builder_delta_capture_failed', [
                'site_id' => (string) $site->id,
                'project_id' => (string) $site->project_id,
                'page_id' => $page->id,
                'revision_id' => $revision->id,
                'locale' => $targetLocale,
                'error' => $exception->getMessage(),
            ]);
        }

        $websitePage = WebsitePage::query()
            ->where('page_id', $page->id)
            ->whereHas('website', fn ($q) => $q->where('site_id', $site->id))
            ->first();
        if ($websitePage) {
            $this->universalCmsSync->syncSectionsFromPageRevision($page, $websitePage);
        }

        return $revision;
    }

    public function publish(Site $site, Page $page, ?int $revisionId = null): PageRevision
    {
        $this->ensurePageBelongsToSite($site, $page);

        if ($revisionId !== null) {
            $revision = $this->repository->findRevisionById($site, $page, $revisionId);
        } else {
            $revision = $this->repository->latestRevision($site, $page);
        }

        if (! $revision) {
            throw new CmsDomainException('Revision not found for publish.', 422);
        }

        DB::transaction(function () use ($site, $page, $revision): void {
            $this->repository->clearPublishedRevisions($site, $page);
            $this->repository->updateRevision($revision, ['published_at' => now()]);
            $this->repository->updatePage($page, ['status' => 'published']);

            if ($site->status === 'draft') {
                $this->repository->updateSite($site, ['status' => 'published']);
            }
        });

        return $revision->fresh();
    }

    private function ensurePageBelongsToSite(Site $site, Page $page): void
    {
        if (! $this->repository->findPageBySiteAndId($site, $page->id)) {
            throw (new ModelNotFoundException)->setModel(Page::class, [(string) $page->id]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeRevision(?PageRevision $revision, string $requestedLocale, string $siteLocale): ?array
    {
        if (! $revision) {
            return null;
        }

        $localized = $this->localizedPayload->resolve($revision->content_json ?? [], $requestedLocale, $siteLocale);

        return [
            'id' => $revision->id,
            'version' => $revision->version,
            'content_json' => $localized['content'],
            'published_at' => $revision->published_at?->toISOString(),
            'created_at' => $revision->created_at?->toISOString(),
            'meta' => [
                'requested_locale' => $localized['requested_locale'],
                'resolved_locale' => $localized['resolved_locale'],
                'available_locales' => $localized['available_locales'],
                'localized' => $localized['localized'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $contentJson
     * @return array<string, mixed>
     */
    private function hydrateSectionBindingsInContentJson(array $contentJson): array
    {
        $sections = is_array($contentJson['sections'] ?? null) ? array_values($contentJson['sections']) : null;
        if ($sections === null) {
            return $contentJson;
        }

        $contentJson['sections'] = array_values(array_map(function ($section): array {
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

            foreach ($section as $key => $value) {
                if (in_array((string) $key, ['type', 'props', 'binding'], true)) {
                    continue;
                }
                $payload[$key] = $value;
            }

            return $payload;
        }, $sections));
        $contentJson['sections'] = array_values(array_filter(
            $contentJson['sections'],
            static fn (array $section): bool => $section !== []
        ));

        return $contentJson;
    }
}
