<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\WebsitePage;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\WebuCodex\CodebaseScanner;
use App\Services\UniversalCmsSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AiContentPatchService
{
    public function __construct(
        protected AiRevisionService $aiRevisionService,
        protected AiOutputSchemaValidator $schemaValidator,
        protected UniversalCmsSyncService $universalCmsSync,
        protected ProjectWorkspaceService $workspace,
        protected CodebaseScanner $scanner,
        protected CmsSectionBindingService $sectionBindings,
    ) {}
    /**
     * @param  array<string, mixed>  $payload
     * @return array{replay: bool, revision: PageRevision, page: Page}
     */
    public function apply(Project $project, array $payload, ?int $actorId = null): array
    {
        $site = $project->site()->first();
        if (! $site) {
            throw new RuntimeException('Site is not provisioned for the selected project.');
        }

        $page = $this->resolvePage((string) $site->id, $payload);
        $idempotencyKey = $this->normalizeIdempotencyKey($payload['idempotency_key'] ?? null);

        if ($idempotencyKey !== null) {
            $replayed = $this->resolveReplay($project, $idempotencyKey);
            if ($replayed) {
                return [
                    'replay' => true,
                    'revision' => $replayed,
                    'page' => $page,
                ];
            }
        }

        $attempts = 0;

        beginning:
        try {
            $result = DB::transaction(function () use ($site, $page, $payload, $actorId): array {
                $lockedPage = Page::query()
                    ->whereKey($page->getKey())
                    ->lockForUpdate()
                    ->first() ?? $page;

                $latestRevision = PageRevision::query()
                    ->where('site_id', $site->id)
                    ->where('page_id', $lockedPage->id)
                    ->latest('version')
                    ->lockForUpdate()
                    ->first();

                $baseContent = is_array($latestRevision?->content_json)
                    ? $latestRevision->content_json
                    : ['sections' => []];

                $patch = is_array($payload['patch'] ?? null) ? $payload['patch'] : [];
                $mode = (string) ($payload['mode'] ?? 'merge');
                $patchFormat = (string) ($payload['patch_format'] ?? 'content_merge');

                if ($patchFormat === 'rfc6902' && is_array($patch) && $this->isSequentialArray($patch)) {
                    $nextContent = $this->applyJsonPatchRfc6902($baseContent, $patch);
                } elseif ($mode === 'replace') {
                    $nextContent = $patch;
                } else {
                    $nextContent = array_replace_recursive($baseContent, $patch);
                }

                if (! is_array($nextContent)) {
                    $nextContent = ['sections' => []];
                }

                if (! array_key_exists('sections', $nextContent) && ! array_key_exists('locales', $nextContent)) {
                    $nextContent['sections'] = [];
                }

                $nextContent = $this->canonicalizeSectionKeys($nextContent);

                $schemaErrors = $this->schemaValidator->validate($nextContent);
                if ($schemaErrors !== []) {
                    throw ValidationException::withMessages([
                        'patch' => $schemaErrors,
                    ]);
                }

                $nextVersion = ((int) ($latestRevision?->version ?? 0)) + 1;
                $revision = PageRevision::query()->create([
                    'site_id' => $site->id,
                    'page_id' => $lockedPage->id,
                    'version' => $nextVersion,
                    'content_json' => $nextContent,
                    'created_by' => $actorId,
                    'published_at' => null,
                ]);

                $publish = (bool) ($payload['publish'] ?? false);
                if ($publish) {
                    PageRevision::query()
                        ->where('site_id', $site->id)
                        ->where('page_id', $lockedPage->id)
                        ->whereNotNull('published_at')
                        ->update(['published_at' => null]);

                    $revision->update(['published_at' => now()]);
                    $lockedPage->update(['status' => 'published']);
                    if ($site->status === 'draft') {
                        $site->update(['status' => 'published']);
                    }
                }

                $this->aiRevisionService->saveRevision(
                    $site,
                    $lockedPage,
                    $baseContent,
                    $patch,
                    $nextContent,
                    (int) $revision->id,
                    $actorId,
                    isset($payload['instruction']) ? (string) $payload['instruction'] : null,
                    isset($payload['patch']) ? (is_array($payload['patch']) ? $payload['patch'] : null) : null
                );

                $websitePage = WebsitePage::query()
                    ->where('page_id', $lockedPage->id)
                    ->whereHas('website', fn ($q) => $q->where('site_id', $site->id))
                    ->first();
                if ($websitePage) {
                    $this->universalCmsSync->syncSectionsFromPageRevision($lockedPage, $websitePage);
                }

                return [
                    'replay' => false,
                    'revision' => $revision->fresh(),
                    'page' => $lockedPage->fresh(),
                ];
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateRevisionVersionException($e) && $attempts < 2) {
                $attempts++;
                goto beginning;
            }

            throw $e;
        }

        if ($idempotencyKey !== null) {
            Cache::put(
                $this->idempotencyCacheKey($project, $idempotencyKey),
                (int) $result['revision']->id,
                now()->addDay()
            );
        }

        if (! ($result['replay'] ?? false)) {
            $this->workspace->invalidateWorkspaceProjection($project);
            $this->scanner->invalidateIndex($project);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePage(string $siteId, array $payload): Page
    {
        if (! empty($payload['page_id'])) {
            $page = Page::query()
                ->where('site_id', $siteId)
                ->where('id', (int) $payload['page_id'])
                ->first();

            if ($page) {
                return $page;
            }
        }

        $slug = trim((string) ($payload['page_slug'] ?? ''));
        if ($slug !== '') {
            $page = Page::query()
                ->where('site_id', $siteId)
                ->where('slug', $slug)
                ->first();
            if ($page) {
                return $page;
            }
        }

        $home = Page::query()
            ->where('site_id', $siteId)
            ->where('slug', 'home')
            ->first();

        if ($home) {
            return $home;
        }

        $first = Page::query()
            ->where('site_id', $siteId)
            ->orderBy('id')
            ->first();

        if ($first) {
            return $first;
        }

        throw new RuntimeException('No editable page found in project site.');
    }

    /**
     * Normalize legacy/alias section keys inside content_json before schema validation.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function canonicalizeSectionKeys(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($key === 'sections' && is_array($value)) {
                $payload[$key] = array_map(function ($section) {
                    if (! is_array($section)) {
                        return $section;
                    }

                    return $this->canonicalizeSectionNode($section);
                }, array_values($value));
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->canonicalizeSectionKeys($value);
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function canonicalizeSectionNode(array $section): array
    {
        $currentKey = trim((string) (
            data_get($section, 'binding.section_key')
            ?? ($section['key'] ?? $section['type'] ?? '')
        ));

        if ($currentKey === '') {
            return $section;
        }

        $resolvedBinding = $this->sectionBindings->resolveBinding($currentKey);
        $canonicalKey = trim((string) ($resolvedBinding['section_key'] ?? $currentKey));
        if ($canonicalKey === '') {
            return $section;
        }

        $existingBinding = is_array($section['binding'] ?? null) ? $section['binding'] : [];

        $section['key'] = $canonicalKey;
        $section['type'] = $canonicalKey;
        $section['binding'] = array_replace_recursive($resolvedBinding, $existingBinding);
        $section['binding']['section_key'] = $canonicalKey;

        return $section;
    }

    private function normalizeIdempotencyKey(mixed $value): ?string
    {
        $key = trim((string) $value);
        if ($key === '') {
            return null;
        }

        return substr($key, 0, 120);
    }

    private function resolveReplay(Project $project, string $idempotencyKey): ?PageRevision
    {
        $revisionId = Cache::get($this->idempotencyCacheKey($project, $idempotencyKey));
        if (! is_int($revisionId) && ! ctype_digit((string) $revisionId)) {
            return null;
        }

        return PageRevision::query()->find((int) $revisionId);
    }

    private function idempotencyCacheKey(Project $project, string $idempotencyKey): string
    {
        return sprintf('ai-content-patch:%s:%s', (string) $project->id, $idempotencyKey);
    }

    private function isDuplicateRevisionVersionException(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'page_revisions.page_revisions_page_id_version_unique')
            || str_contains($message, 'Duplicate entry');
    }

    /**
     * Apply RFC 6902 JSON Patch operations (add, replace, remove) to a document.
     *
     * @param  array<string, mixed>  $doc
     * @param  array<int, array{op: string, path: string, value?: mixed}>  $ops
     * @return array<string, mixed>
     */
    private function applyJsonPatchRfc6902(array $doc, array $ops): array
    {
        $result = $doc;
        foreach ($ops as $op) {
            $opName = isset($op['op']) ? strtolower((string) $op['op']) : '';
            $path = isset($op['path']) ? (string) $op['path'] : '';
            if ($path === '' || $path[0] !== '/') {
                continue;
            }
            $segments = $this->jsonPointerToSegments($path);
            if ($opName === 'add') {
                $result = $this->patchAdd($result, $segments, $op['value'] ?? null);
            } elseif ($opName === 'replace') {
                $result = $this->patchReplace($result, $segments, $op['value'] ?? null);
            } elseif ($opName === 'remove') {
                $result = $this->patchRemove($result, $segments);
            }
        }

        return $result;
    }

    /**
     * @return array<int, int|string>
     */
    private function jsonPointerToSegments(string $path): array
    {
        $parts = explode('/', substr($path, 1));
        $segments = [];
        foreach ($parts as $p) {
            $segments[] = str_replace(['~1', '~0'], ['/', '~'], $p);
        }

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @param  array<int, int|string>  $segments
     * @return array<string, mixed>
     */
    private function patchAdd(array $doc, array $segments, mixed $value): array
    {
        if ($segments === []) {
            return is_array($value) ? $value : $doc;
        }
        $key = array_shift($segments);
        if ($segments === []) {
            if ($key === '-') {
                $doc[] = $value;

                return $doc;
            }
            $doc[$key] = $value;

            return $doc;
        }
        $child = $doc[$key] ?? [];
        if (! is_array($child)) {
            $child = [];
        }
        $doc[$key] = $this->patchAdd($child, $segments, $value);

        return $doc;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @param  array<int, int|string>  $segments
     * @return array<string, mixed>
     */
    private function patchReplace(array $doc, array $segments, mixed $value): array
    {
        if ($segments === []) {
            return is_array($value) ? $value : $doc;
        }
        $key = array_shift($segments);
        if (! array_key_exists($key, $doc)) {
            return $doc;
        }
        if ($segments === []) {
            $doc[$key] = $value;

            return $doc;
        }
        $child = $doc[$key];
        if (! is_array($child)) {
            return $doc;
        }
        $doc[$key] = $this->patchReplace($child, $segments, $value);

        return $doc;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @param  array<int, int|string>  $segments
     * @return array<string, mixed>
     */
    private function patchRemove(array $doc, array $segments): array
    {
        if ($segments === []) {
            return $doc;
        }
        $key = array_shift($segments);
        if (! array_key_exists($key, $doc)) {
            return $doc;
        }
        if ($segments === []) {
            unset($doc[$key]);

            return array_is_list($doc) ? array_values($doc) : $doc;
        }
        $child = $doc[$key];
        if (! is_array($child)) {
            return $doc;
        }
        $doc[$key] = $this->patchRemove($child, $segments);

        return $doc;
    }

    private function isSequentialArray(array $a): bool
    {
        if ($a === []) {
            return true;
        }

        return array_keys($a) === range(0, count($a) - 1);
    }
}
