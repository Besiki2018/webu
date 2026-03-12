<?php

namespace App\Services;

use App\Models\ChatPatchRollback;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\WebsitePage;
use App\Cms\Contracts\CmsRepositoryContract;
use Illuminate\Support\Facades\DB;

/**
 * Restores project/site state from the latest chat patch rollback entry.
 *
 * @see new tasks.txt — AI Design Director PART 6 (Director for Chat Editing, rollback)
 */
class ChatPatchRollbackService
{
    public function __construct(
        protected CmsRepositoryContract $cmsRepository,
        protected UniversalCmsSyncService $universalCmsSync
    ) {}

    /**
     * Restore state from the most recent rollback entry (if any). Marks that entry as rolled back.
     *
     * @return array{rolled_back: bool, patch_type: string|null, message: string}
     */
    public function rollbackLastPatch(Project $project): array
    {
        $entry = ChatPatchRollback::query()
            ->where('project_id', $project->id)
            ->whereNull('rolled_back_at')
            ->latest('id')
            ->first();

        if ($entry === null) {
            return [
                'rolled_back' => false,
                'patch_type' => null,
                'message' => 'No patch to roll back.',
            ];
        }

        $snapshot = is_array($entry->snapshot_json) ? $entry->snapshot_json : [];
        $patchType = (string) $entry->patch_type;

        DB::transaction(function () use ($entry, $project, $snapshot, $patchType): void {
            if ($patchType === 'theme_preset') {
                $this->restoreThemeSnapshot($project, $snapshot);
            }
            if ($patchType === 'add_section' || $patchType === 'page_patch') {
                $this->restoreAddSectionSnapshot($project, $snapshot);
            }
            $entry->update(['rolled_back_at' => now()]);
        });

        return [
            'rolled_back' => true,
            'patch_type' => $patchType,
            'message' => match ($patchType) {
                'theme_preset' => 'Theme reverted.',
                'page_patch' => 'Page changes reverted.',
                default => 'Section change reverted.',
            },
        ];
    }

    /**
     * @param  array{theme_preset?: string, theme_settings?: array}  $snapshot
     */
    private function restoreThemeSnapshot(Project $project, array $snapshot): void
    {
        $preset = $snapshot['theme_preset'] ?? null;
        if ($preset !== null) {
            $project->update(['theme_preset' => $preset]);
        }
        $site = $this->cmsRepository->findSiteByProject($project);
        if ($site !== null && isset($snapshot['theme_settings']) && is_array($snapshot['theme_settings'])) {
            $site->update(['theme_settings' => $snapshot['theme_settings']]);
        }
    }

    /**
     * @param  array{page_id?: int, content_json?: array}  $snapshot
     */
    private function restoreAddSectionSnapshot(Project $project, array $snapshot): void
    {
        $pageId = isset($snapshot['page_id']) ? (int) $snapshot['page_id'] : null;
        $content = $snapshot['content_json'] ?? null;
        if ($pageId === null || ! is_array($content)) {
            return;
        }

        $site = $this->cmsRepository->findSiteByProject($project);
        if ($site === null) {
            return;
        }

        $page = Page::query()
            ->where('site_id', $site->id)
            ->where('id', $pageId)
            ->first();
        if ($page === null) {
            return;
        }

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();
        $nextVersion = ((int) ($latestRevision?->version ?? 0)) + 1;

        PageRevision::query()->create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'version' => $nextVersion,
            'content_json' => $content,
            'created_by' => auth()->id(),
            'published_at' => null,
        ]);

        $websitePage = WebsitePage::query()
            ->where('page_id', $page->id)
            ->whereHas('website', fn ($q) => $q->where('site_id', $site->id))
            ->first();
        if ($websitePage !== null) {
            $this->universalCmsSync->syncSectionsFromPageRevision($page, $websitePage);
        }
    }
}
