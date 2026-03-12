<?php

namespace App\Services\AiWebsiteGeneration;

use App\Models\Website;
use App\Models\WebsitePage;
use App\Models\WebsiteRevision;
use App\Models\PageSection;
use App\Services\UniversalCmsSyncService;
use Illuminate\Support\Facades\DB;

/**
 * Undo: restore website from previous website_revisions snapshot.
 */
class WebsiteUndoService
{
    public function __construct(
        protected UniversalCmsSyncService $cmsSync
    ) {}

    /**
     * Restore website to the state in the given revision (by version number).
     * Returns true if restored, false if no previous revision.
     */
    public function undoToVersion(Website $website, int $toVersion): bool
    {
        $revision = WebsiteRevision::query()
            ->where('website_id', $website->id)
            ->where('version', $toVersion)
            ->first();

        if (! $revision || ! is_array($revision->snapshot_json)) {
            return false;
        }

        $pages = $revision->snapshot_json['pages'] ?? [];
        if ($pages === []) {
            return false;
        }

        DB::transaction(function () use ($website, $pages) {
            foreach ($website->websitePages as $wp) {
                $wp->sections()->delete();
            }
            foreach ($pages as $pageData) {
                $wp = WebsitePage::query()
                    ->where('website_id', $website->id)
                    ->where('id', $pageData['id'] ?? 0)
                    ->first();
                if (! $wp) {
                    continue;
                }
                foreach ($pageData['sections'] ?? [] as $order => $sec) {
                    PageSection::create([
                        'page_id' => $wp->id,
                        'section_type' => $sec['section_type'] ?? 'content',
                        'order' => $order,
                        'settings_json' => $sec['settings_json'] ?? [],
                    ]);
                }
                $this->cmsSync->pushWebsitePageToRevision($wp);
            }
        });

        return true;
    }

    /**
     * Get the revision version to restore to for Undo (snapshot created before last edit).
     * When CMS creates a snapshot before each section update, that snapshot is the latest revision;
     * Undo restores to it.
     */
    public function previousVersion(Website $website): ?int
    {
        $max = (int) $website->revisions()->max('version');
        return $max >= 1 ? $max : null;
    }
}
