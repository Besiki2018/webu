<?php

namespace App\Services;

use App\Models\AiRevision;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Site;
use Illuminate\Support\Collection;
use RuntimeException;

class AiRevisionService
{
    /**
     * Store an AI-applied patch as a revision record (for history and rollback).
     *
     * @param  array<string, mixed>  $snapshotBefore
     * @param  array<string, mixed>  $appliedPatch
     * @param  array<string, mixed>  $snapshotAfter
     */
    public function saveRevision(
        Site $site,
        Page $page,
        array $snapshotBefore,
        array $appliedPatch,
        array $snapshotAfter,
        ?int $pageRevisionId = null,
        ?int $userId = null,
        ?string $promptText = null,
        ?array $aiRawOutput = null
    ): AiRevision {
        $hash = hash('sha256', json_encode($snapshotAfter));

        return AiRevision::query()->create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'user_id' => $userId,
            'prompt_text' => $promptText,
            'ai_raw_output' => $aiRawOutput,
            'applied_patch' => $appliedPatch,
            'snapshot_before' => $snapshotBefore,
            'snapshot_after' => $snapshotAfter,
            'snapshot_hash' => $hash,
            'page_revision_id' => $pageRevisionId,
            'created_at' => now(),
        ]);
    }

    /**
     * List AI revisions for a tenant (site) and optionally a page.
     *
     * @return Collection<int, AiRevision>
     */
    public function listRevisions(Site $site, ?Page $page = null): Collection
    {
        $query = AiRevision::query()
            ->where('site_id', $site->id)
            ->orderByDesc('created_at');

        if ($page !== null) {
            $query->where('page_id', $page->id);
        }

        return $query->with(['user:id,name', 'page:id,slug,title'])->get();
    }

    /**
     * Rollback to an AI revision by restoring its snapshot as a new page revision.
     * Tenant-safe and creates a new revision (does not delete history).
     *
     * @return array{revision: PageRevision, page: Page, diverged: bool}
     */
    public function rollbackToRevision(int $aiRevisionId, Site $site): array
    {
        $aiRev = AiRevision::query()
            ->where('id', $aiRevisionId)
            ->where('site_id', $site->id)
            ->firstOrFail();

        $page = $aiRev->page;
        if (! $page || $page->site_id !== $site->id) {
            throw new RuntimeException('Page does not belong to site.');
        }

        $latest = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        $currentContent = is_array($latest?->content_json) ? $latest->content_json : [];
        $targetContent = $aiRev->snapshot_after;
        $diverged = json_encode($currentContent) !== json_encode($targetContent);

        $nextVersion = ((int) ($latest?->version ?? 0)) + 1;
        $revision = PageRevision::query()->create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'version' => $nextVersion,
            'content_json' => $targetContent,
            'created_by' => null,
            'published_at' => null,
        ]);

        return [
            'revision' => $revision->fresh(),
            'page' => $page->fresh(),
            'diverged' => $diverged,
        ];
    }

    /**
     * Get a single AI revision for preview (tenant-scoped).
     */
    public function getRevision(int $aiRevisionId, Site $site): ?AiRevision
    {
        return AiRevision::query()
            ->where('id', $aiRevisionId)
            ->where('site_id', $site->id)
            ->with(['page:id,slug,title'])
            ->first();
    }
}
