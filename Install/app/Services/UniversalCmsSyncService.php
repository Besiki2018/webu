<?php

namespace App\Services;

use App\Cms\Support\LocalizedCmsPayload;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Models\WebsiteRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Syncs existing Site/Page/content_json into Universal CMS tables (Website, WebsitePage, PageSection).
 * Ensures every AI-generated website is editable from CMS.
 */
class UniversalCmsSyncService
{
    public function __construct(
        protected LocalizedCmsPayload $localizedPayload
    ) {}

    /**
     * Resolve tenant_id for a site: from project.tenant_id or get/create tenant for project owner.
     */
    protected function resolveTenantIdForSite(Site $site): ?string
    {
        $site->loadMissing('project');
        $project = $site->project;
        if (! $project) {
            return null;
        }
        if ($project->tenant_id) {
            return $project->tenant_id;
        }
        $userId = $project->user_id;
        if (! $userId) {
            return null;
        }
        $tenant = Tenant::query()
            ->where('owner_user_id', $userId)
            ->orWhere('created_by_user_id', $userId)
            ->first();
        if ($tenant) {
            $project->update(['tenant_id' => $tenant->id]);

            return $tenant->id;
        }
        $name = $project->user?->name ?: "User {$userId}";
        $baseSlug = Str::slug($name) . '-' . substr((string) Str::uuid(), 0, 8);
        $slug = $baseSlug;
        $n = 0;
        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . (++$n ?: time());
        }
        $tenant = Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'owner_user_id' => $userId,
            'created_by_user_id' => $userId,
        ]);
        $project->update(['tenant_id' => $tenant->id]);

        return $tenant->id;
    }

    /**
     * Ensure a Website record exists for the site; create or update.
     */
    public function syncWebsiteFromSite(Site $site): Website
    {
        $tenantId = $this->resolveTenantIdForSite($site);
        if (! $tenantId) {
            throw new \InvalidArgumentException(
                'Cannot sync site without a project or tenant. Site ID: ' . $site->id
            );
        }

        $website = Website::query()->where('site_id', $site->id)->first();

        if ($website) {
            $website->update([
                'tenant_id' => $tenantId,
                'name' => $site->name,
                'domain' => $site->primary_domain ?? $site->subdomain,
                'theme' => $site->theme_settings,
                'user_id' => $site->project?->user_id,
            ]);

            return $website;
        }

        $website = Website::create([
            'tenant_id' => $tenantId,
            'user_id' => $site->project?->user_id,
            'name' => $site->name,
            'domain' => $site->primary_domain ?? $site->subdomain,
            'theme' => $site->theme_settings,
            'site_id' => $site->id,
        ]);

        return $website;
    }

    /**
     * Sync all pages and sections from site's latest revisions into website_pages and page_sections.
     */
    public function syncPagesAndSectionsFromSite(Site $site): void
    {
        $website = $this->syncWebsiteFromSite($site);

        foreach ($site->pages()->orderBy('id')->get() as $index => $page) {
            $wp = $this->syncWebsitePageFromPage($website, $page, $index);
            $this->syncSectionsFromPageRevision($page, $wp);
        }
    }

    public function syncWebsitePageFromPage(Website $website, Page $page, int $order = 0): WebsitePage
    {
        $wp = WebsitePage::query()
            ->where('website_id', $website->id)
            ->where('page_id', $page->id)
            ->first();

        if ($wp) {
            $wp->update([
                'slug' => $page->slug,
                'title' => $page->title,
                'order' => $order,
            ]);

            return $wp;
        }

        return WebsitePage::create([
            'website_id' => $website->id,
            'tenant_id' => $website->tenant_id,
            'slug' => $page->slug,
            'title' => $page->title,
            'order' => $order,
            'page_id' => $page->id,
        ]);
    }

    /**
     * Copy sections from page's latest revision content_json into page_sections.
     */
    public function syncSectionsFromPageRevision(Page $page, WebsitePage $websitePage): void
    {
        $revision = $page->revisions()->latest('version')->first();
        if (! $revision || ! is_array($revision->content_json)) {
            return;
        }

        $siteLocale = $this->resolveSiteLocaleForPage($page);
        $content = $this->resolveRevisionContentForLocale($revision, $siteLocale);
        $sections = is_array($content['sections'] ?? null)
            ? array_values($content['sections'])
            : [];

        $website = $websitePage->website;
        $tenantId = $website->tenant_id;
        $websiteId = $website->id;

        DB::transaction(function () use ($websitePage, $sections, $tenantId, $websiteId) {
            $websitePage->sections()->delete();

            foreach ($sections as $order => $section) {
                $type = $section['type'] ?? 'unknown';
                $props = $section['props'] ?? [];
                PageSection::create([
                    'page_id' => $websitePage->id,
                    'tenant_id' => $tenantId,
                    'website_id' => $websiteId,
                    'section_type' => $type,
                    'order' => $order,
                    'settings_json' => $props,
                ]);
            }
        });
    }

    /**
     * Build content_json sections array from page_sections (for builder/revision).
     */
    public function buildContentSectionsFromPageSections(WebsitePage $websitePage): array
    {
        return $websitePage->sections()
            ->orderBy('order')
            ->get()
            ->map(fn (PageSection $s) => [
                'type' => $s->section_type,
                'props' => $s->settings_json ?? [],
            ])
            ->all();
    }

    /**
     * Push CMS section data back to the linked Page's latest revision so the builder stays in sync.
     */
    public function pushWebsitePageToRevision(WebsitePage $websitePage): void
    {
        $page = $websitePage->page;
        if (! $page) {
            return;
        }

        $revision = $page->revisions()->latest('version')->first();
        if (! $revision) {
            return;
        }

        $content = is_array($revision->content_json) ? $revision->content_json : [];
        $sections = $this->buildContentSectionsFromPageSections($websitePage);
        $siteLocale = $this->resolveSiteLocaleForPage($page);
        $localeMap = $this->localizedPayload->extractLocaleMap($content);

        if ($localeMap !== null) {
            $resolvedContent = $this->resolveRevisionContentForLocale($revision, $siteLocale);
            $resolvedContent['sections'] = $sections;
            $content = $this->localizedPayload->mergeForLocale(
                $content,
                $siteLocale,
                $resolvedContent,
                $siteLocale,
                array_keys($localeMap)
            );
        } else {
            $content['sections'] = $sections;
        }

        $revision->update(['content_json' => $content]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveRevisionContentForLocale(PageRevision $revision, string $siteLocale): array
    {
        $resolved = $this->localizedPayload->resolve(
            $revision->content_json ?? ['sections' => []],
            $siteLocale,
            $siteLocale
        );

        return is_array($resolved['content'] ?? null)
            ? $resolved['content']
            : ['sections' => []];
    }

    protected function resolveSiteLocaleForPage(Page $page): string
    {
        $page->loadMissing('site');

        return $this->localizedPayload->normalizeLocale(
            $page->site?->locale,
            CmsLocaleResolver::PRIMARY_LOCALE
        );
    }

    /**
     * Create a website_revisions snapshot (for Undo). Call before editing sections in CMS.
     */
    public function createSnapshot(Website $website, string $changeType = 'cms_edit', ?int $createdBy = null): void
    {
        $maxVersion = (int) $website->revisions()->max('version');
        $snapshot = [
            'website_id' => $website->id,
            'pages' => $website->websitePages()->with('sections')->get()->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'order' => $p->order,
                'sections' => $p->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'section_type' => $s->section_type,
                    'order' => $s->order,
                    'settings_json' => $s->settings_json,
                ])->all(),
            ])->all(),
        ];
        WebsiteRevision::create([
            'website_id' => $website->id,
            'tenant_id' => $website->tenant_id,
            'version' => $maxVersion + 1,
            'snapshot_json' => $snapshot,
            'change_type' => $changeType,
            'created_by' => $createdBy,
        ]);
    }
}
