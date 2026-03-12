<?php

namespace App\Services;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Models\ChatPatchRollback;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\Site;
use App\Models\WebsitePage;
use Illuminate\Support\Facades\Log;

/**
 * Applies chat intent patches: theme_preset (project + site) or add_section (page revision).
 * Director for Chat Editing (PART 6): creates a rollback entry before every patch; patches use templates/variants/tokens only.
 *
 * @see new tasks.txt — PART 7 AI Chat Editing, PART 6 Director for Chat Editing
 */
class ChatApplyPatchService
{
    private const ALLOWED_THEME_PRESETS = [
        'default', 'arctic', 'summer', 'fragrant', 'slate', 'feminine', 'forest',
        'midnight', 'coral', 'mocha', 'ocean', 'ruby', 'luxury_minimal', 'corporate_clean',
        'bold_startup', 'soft_pastel', 'dark_modern', 'creative_portfolio',
    ];

    /** Director: only these section key prefixes allowed (no raw HTML/CSS). */
    private const ALLOWED_SECTION_PREFIXES = ['webu_ecom_', 'webu_general_'];

    public function __construct(
        protected ChatIntentToPatchService $intentParser,
        protected CmsRepositoryContract $cmsRepository,
        protected CmsSectionBindingService $sectionBindings,
        protected UniversalCmsSyncService $universalCmsSync,
        protected AiContentPatchProposalService $proposalService,
        protected AiContentPatchService $aiContentPatch
    ) {}

    /**
     * Parse message and apply patch if intent is theme_preset or add_section.
     *
     * @return array{applied: bool, type?: string, patch?: array, message?: string, error?: string}
     */
    public function apply(Project $project, string $message): array
    {
        $message = trim((string) $message);
        if ($message === '') {
            return ['applied' => false, 'message' => 'No message provided.'];
        }

        $result = $this->intentParser->parse($message);
        $type = $result['type'] ?? 'none';
        $patch = $result['patch'] ?? [];

        if ($type === 'none' || $patch === []) {
            return $this->applyAiGeneratedPatch($project, $message);
        }

        if ($type === 'theme_preset') {
            return $this->applyThemePreset($project, $patch);
        }

        if ($type === 'add_section') {
            return $this->applyAddSection($project, $patch);
        }

        return ['applied' => false];
    }

    /**
     * Propose a patch from a natural-language message (Instruction → patch flow).
     * Does not apply; returns type, patch, and human-readable summary for diff preview.
     *
     * @return array{proposed: bool, type?: string, patch?: array, summary?: string}
     */
    public function propose(Project $project, string $message): array
    {
        $message = trim((string) $message);
        if ($message === '') {
            return ['proposed' => false];
        }

        $result = $this->intentParser->parse($message);
        $type = $result['type'] ?? 'none';
        $patch = $result['patch'] ?? [];

        if ($type === 'none' || $patch === []) {
            return $this->proposeAiGeneratedPatch($project, $message);
        }

        if ($type === 'theme_preset') {
            $preset = trim((string) ($patch['theme_preset'] ?? ''));
            if ($preset === '' || ! in_array($preset, self::ALLOWED_THEME_PRESETS, true)) {
                return ['proposed' => false];
            }
            $summary = $this->themePresetSummary($preset);
            return [
                'proposed' => true,
                'type' => 'theme_preset',
                'patch' => $patch,
                'summary' => $summary,
            ];
        }

        if ($type === 'add_section') {
            $sectionKey = trim((string) (($patch['section'] ?? [])['key'] ?? ''));
            $pageSlug = (string) ($patch['page_slug'] ?? 'home');
            if ($sectionKey === '' || ! $this->isAllowedSectionKey($sectionKey)) {
                return ['proposed' => false];
            }
            $summary = $this->addSectionSummary($sectionKey, $pageSlug);
            return [
                'proposed' => true,
                'type' => 'add_section',
                'patch' => $patch,
                'summary' => $summary,
            ];
        }

        return ['proposed' => false];
    }

    private function themePresetSummary(string $preset): string
    {
        $labels = [
            'dark_modern' => 'Change theme to Dark Modern',
            'luxury_minimal' => 'Change theme to Luxury Minimal',
            'bold_startup' => 'Change theme to Bold Startup',
            'soft_pastel' => 'Change theme to Soft Pastel',
            'corporate_clean' => 'Change theme to Corporate Clean',
            'creative_portfolio' => 'Change theme to Creative Portfolio',
        ];
        return $labels[$preset] ?? "Change theme to {$preset}";
    }

    private function addSectionSummary(string $sectionKey, string $pageSlug): string
    {
        $pageLabel = $pageSlug === 'home' ? 'Home' : ucfirst($pageSlug);
        if (str_contains($sectionKey, 'product_grid') || str_contains($sectionKey, 'product_grid_01')) {
            return "Add Best Sellers section to {$pageLabel}";
        }
        if (str_contains($sectionKey, 'testimonials')) {
            return "Add Testimonials section to {$pageLabel}";
        }
        if (str_contains($sectionKey, 'newsletter')) {
            return "Add Newsletter section to {$pageLabel}";
        }
        if (str_contains($sectionKey, 'heading') || str_contains($sectionKey, 'categor')) {
            return "Add Categories section to {$pageLabel}";
        }
        if (str_contains($sectionKey, 'cta_banner')) {
            return "Add Promo banner to {$pageLabel}";
        }
        return "Add section to {$pageLabel}";
    }

    /**
     * @return array{applied: bool, type?: string, patch?: array, message?: string, rollback_id?: int}
     */
    private function applyAiGeneratedPatch(Project $project, string $message): array
    {
        $page = $this->resolveEditablePage($project);
        if ($page === null) {
            return ['applied' => false];
        }

        $latestRevision = PageRevision::query()
            ->where('site_id', $page->site_id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        $contentBefore = is_array($latestRevision?->content_json)
            ? $latestRevision->content_json
            : ['sections' => []];

        $proposal = $this->proposalService->propose($project, $page->id, $message);
        $proposedPatch = is_array($proposal['proposed_patch'] ?? null)
            ? $proposal['proposed_patch']
            : [];

        if (! ($proposal['success'] ?? false) || $proposedPatch === []) {
            return ['applied' => false];
        }

        $rollback = $this->createRollbackEntry($project, $page->site, 'page_patch', [
            'page_id' => $page->id,
            'content_json' => $contentBefore,
        ]);

        try {
            $result = $this->aiContentPatch->apply($project, [
                'page_id' => $page->id,
                'page_slug' => $page->slug,
                'patch_format' => 'rfc6902',
                'patch' => $proposedPatch,
                'instruction' => $message,
            ], auth()->id());
        } catch (\Throwable $e) {
            Log::warning('Chat AI patch apply failed', [
                'project_id' => $project->id,
                'page_id' => $page->id,
                'error' => $e->getMessage(),
            ]);

            return ['applied' => false];
        }

        $response = [
            'applied' => true,
            'type' => 'page_patch',
            'patch' => [
                'page_id' => $page->id,
                'page_slug' => $page->slug,
                'patch_format' => 'rfc6902',
                'operations' => $proposedPatch,
            ],
            'message' => $this->pagePatchConfirmationMessage($page->slug, $proposedPatch),
        ];

        if ($rollback !== null) {
            $response['rollback_id'] = $rollback->id;
        }

        if (($result['revision']->id ?? null) !== null) {
            $response['patch']['revision_id'] = $result['revision']->id;
        }

        return $response;
    }

    /**
     * @return array{proposed: bool, type?: string, patch?: array, summary?: string}
     */
    private function proposeAiGeneratedPatch(Project $project, string $message): array
    {
        $page = $this->resolveEditablePage($project);
        if ($page === null) {
            return ['proposed' => false];
        }

        $proposal = $this->proposalService->propose($project, $page->id, $message);
        $proposedPatch = is_array($proposal['proposed_patch'] ?? null)
            ? $proposal['proposed_patch']
            : [];

        if (! ($proposal['success'] ?? false) || $proposedPatch === []) {
            return ['proposed' => false];
        }

        return [
            'proposed' => true,
            'type' => 'page_patch',
            'patch' => [
                'page_id' => $page->id,
                'page_slug' => $page->slug,
                'patch_format' => 'rfc6902',
                'operations' => $proposedPatch,
            ],
            'summary' => $this->pagePatchSummary($page->slug, $proposedPatch),
        ];
    }

    private function resolveEditablePage(Project $project): ?Page
    {
        $site = $this->cmsRepository->findSiteByProject($project);
        if ($site === null) {
            return null;
        }

        return Page::query()
            ->where('site_id', $site->id)
            ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    /**
     * Human-friendly summary for chat (GPT/Codex style).
     *
     * @param  array<int, array{op?: string, path?: string, value?: mixed}>  $patch
     */
    private function pagePatchSummary(string $pageSlug, array $patch): string
    {
        $pageLabel = $pageSlug === 'home' ? 'Home' : ucfirst($pageSlug);
        $count = count($patch);

        if ($count === 1) {
            return "I've applied your change to the {$pageLabel} page.";
        }

        return "I've applied your {$count} changes to the {$pageLabel} page.";
    }

    /**
     * @param  array<int, array{op?: string, path?: string, value?: mixed}>  $patch
     */
    private function pagePatchConfirmationMessage(string $pageSlug, array $patch): string
    {
        return $this->pagePatchSummary($pageSlug, $patch);
    }

    /**
     * @param  array{theme_preset: string}  $patch
     * @return array{applied: bool, type: string, patch: array, message: string, rollback_id?: int, error?: string}
     */
    private function applyThemePreset(Project $project, array $patch): array
    {
        $preset = trim((string) ($patch['theme_preset'] ?? ''));
        if ($preset === '' || ! in_array($preset, self::ALLOWED_THEME_PRESETS, true)) {
            return [
                'applied' => false,
                'type' => 'theme_preset',
                'patch' => $patch,
                'message' => 'Invalid or unsupported theme preset.',
                'error' => 'invalid_preset',
            ];
        }

        $site = $this->cmsRepository->findSiteByProject($project);
        $rollbackId = null;
        $rollback = $this->createRollbackEntry($project, $site, 'theme_preset', [
            'theme_preset' => $project->theme_preset,
            'theme_settings' => $site !== null && is_array($site->theme_settings) ? $site->theme_settings : [],
        ]);
        if ($rollback !== null) {
            $rollbackId = $rollback->id;
        }

        $project->update(['theme_preset' => $preset]);
        if ($site !== null) {
            $current = is_array($site->theme_settings) ? $site->theme_settings : [];
            $site->update([
                'theme_settings' => array_merge($current, ['preset' => $preset]),
            ]);
        }

        $result = [
            'applied' => true,
            'type' => 'theme_preset',
            'patch' => $patch,
            'message' => $this->themePresetConfirmationMessage($preset),
        ];
        if (isset($rollbackId)) {
            $result['rollback_id'] = $rollbackId;
        }
        return $result;
    }

    /**
     * @param  array{page_slug: string, section: array{key: string, props: array}, index: int}  $patch
     * @return array{applied: bool, type: string, patch: array, message: string, error?: string}
     */
    private function applyAddSection(Project $project, array $patch): array
    {
        $site = $this->cmsRepository->findSiteByProject($project);
        if ($site === null) {
            return [
                'applied' => false,
                'type' => 'add_section',
                'patch' => $patch,
                'message' => 'Site not found. Generate your site first.',
                'error' => 'no_site',
            ];
        }

        $pageSlug = (string) ($patch['page_slug'] ?? 'home');
        $page = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', $pageSlug)
            ->first();

        if ($page === null) {
            return [
                'applied' => false,
                'type' => 'add_section',
                'patch' => $patch,
                'message' => "Page \"{$pageSlug}\" not found.",
                'error' => 'page_not_found',
            ];
        }

        $sectionDef = $patch['section'] ?? [];
        $sectionKey = trim((string) ($sectionDef['key'] ?? ''));
        $sectionProps = is_array($sectionDef['props'] ?? null) ? $sectionDef['props'] : [];
        if ($sectionKey === '') {
            return [
                'applied' => false,
                'type' => 'add_section',
                'patch' => $patch,
                'message' => 'Section type is required.',
                'error' => 'missing_section_key',
            ];
        }
        if (! $this->isAllowedSectionKey($sectionKey)) {
            return [
                'applied' => false,
                'type' => 'add_section',
                'patch' => $patch,
                'message' => 'Only predefined section types (templates/variants) are allowed.',
                'error' => 'forbidden_section',
            ];
        }

        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        $content = is_array($latestRevision?->content_json) ? $latestRevision->content_json : ['sections' => []];
        $sections = is_array($content['sections'] ?? null) ? array_values($content['sections']) : [];

        $sectionPayload = $this->sectionBindings->buildSectionPayload($sectionKey, $sectionProps);
        $index = isset($patch['index']) && (int) $patch['index'] >= 0
            ? min((int) $patch['index'], count($sections))
            : count($sections);
        $contentBefore = $content;
        array_splice($sections, $index, 0, [$sectionPayload]);

        $rollback = $this->createRollbackEntry($project, $site, 'add_section', [
            'page_id' => $page->id,
            'content_json' => $contentBefore,
        ]);
        $rollbackId = $rollback?->id;

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
                $payload['binding'] = array_replace($payload['binding'] ?? [], $section['binding']);
            }
            return $payload;
        }, $sections));
        $content['sections'] = array_values(array_filter($content['sections'], static fn (array $s): bool => $s !== []));

        $nextVersion = ((int) ($latestRevision?->version ?? 0)) + 1;
        $revision = PageRevision::query()->create([
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

        $result = [
            'applied' => true,
            'type' => 'add_section',
            'patch' => $patch,
            'message' => $this->addSectionConfirmationMessage($sectionKey),
        ];
        if ($rollbackId !== null) {
            $result['rollback_id'] = $rollbackId;
        }
        return $result;
    }

    /**
     * Director: only templates/variants (webu_ecom_*, webu_general_*); no raw HTML/CSS.
     */
    private function isAllowedSectionKey(string $key): bool
    {
        foreach (self::ALLOWED_SECTION_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function createRollbackEntry(Project $project, ?Site $site, string $patchType, array $snapshot): ?ChatPatchRollback
    {
        return ChatPatchRollback::query()->create([
            'project_id' => $project->id,
            'site_id' => $site?->id,
            'patch_type' => $patchType,
            'snapshot_json' => $snapshot,
        ]);
    }

    private function themePresetConfirmationMessage(string $preset): string
    {
        $labels = [
            'dark_modern' => 'Theme updated to dark modern.',
            'luxury_minimal' => 'Theme updated to luxury minimal.',
            'bold_startup' => 'Theme updated to bold startup.',
            'soft_pastel' => 'Theme updated to soft pastel.',
            'corporate_clean' => 'Theme updated to corporate clean.',
        ];

        return $labels[$preset] ?? "Theme updated to {$preset}.";
    }

    private function addSectionConfirmationMessage(string $sectionKey): string
    {
        if (str_contains($sectionKey, 'product_grid') || str_contains($sectionKey, 'product_grid_01')) {
            return 'Best sellers section added to the homepage.';
        }
        if (str_contains($sectionKey, 'testimonials')) {
            return 'Testimonials section added.';
        }
        if (str_contains($sectionKey, 'newsletter')) {
            return 'Newsletter section added.';
        }
        if (str_contains($sectionKey, 'heading') || str_contains($sectionKey, 'categor')) {
            return 'Categories section added to the homepage.';
        }

        return 'Section added to the page.';
    }
}
