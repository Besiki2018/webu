<?php

namespace App\Services;

use App\Models\Project;
use App\Services\AiTools\ComponentGeneratorService;
use App\Services\AiTools\DesignRulesService;
use App\Services\AiTools\SectionNameNormalizer;
use App\Services\AiTools\SitePlannerService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\WebuCodex\CodebaseScanner;
use App\Services\WebuCodex\ExecutionLogger;
use App\Services\WebuCodex\FileEditor;
use App\Services\WebuCodex\PathRules;
use Illuminate\Support\Facades\Log;

/**
 * Extends Webu AI with real project file editing.
 * Uses existing InternalAiService, workspace, and preview flow.
 * For each request: analyze project → plan file ops → execute → log → return real summary.
 */
class AiProjectFileEditService
{
    public function __construct(
        protected CodebaseScanner $scanner,
        protected InternalAiService $ai,
        protected ProjectWorkspaceService $workspace,
        protected FileEditor $fileEditor,
        protected ExecutionLogger $executionLogger,
        protected DesignRulesService $designRules,
        protected ComponentGeneratorService $componentGenerator,
        protected SitePlannerService $sitePlanner,
        protected SectionNameNormalizer $sectionNameNormalizer
    ) {}

    /**
     * Run AI project edit: scan → plan → execute → log. No fake confirmations.
     * Real project code is written to storage/workspaces/{project_id}/ (pages, sections, layouts).
     *
     * @param  array{section_id: string, parameter_path: string, element_id: string, page_id?: mixed, page_slug?: string|null, component_path?: string|null, component_type?: string|null, component_name?: string|null, editable_fields?: array<int, string>, variants?: array<string, mixed>|null, allowed_updates?: array<string, mixed>|null, current_breakpoint?: string|null, current_interaction_state?: string|null, responsive_context?: array<string, mixed>|null}|null  $selectedElement  Optional selected element for targeted edits (e.g. HeroSection.title)
     * @param  array<int, string>|null  $designPatternHints  Optional design memory hints for site planner (e.g. when generating full website)
     * @return array{success: bool, summary: string, changes: array<int, array{path: string, op: string}>, diagnostic_log: array<int, string>, error?: string, no_change_reason?: string}
     */
    public function run(Project $project, string $userMessage, ?array $selectedElement = null, ?array $designPatternHints = null): array
    {
        $userMessage = trim($userMessage);
        $diagnosticLog = [];
        if ($userMessage === '') {
            return [
                'success' => false,
                'summary' => '',
                'changes' => [],
                'diagnostic_log' => ['Request rejected: empty message.'],
                'error' => 'Empty request.',
            ];
        }

        $workspaceStatus = $this->workspace->ensureProjectCodebaseReady($project);
        $diagnosticLog[] = sprintf(
            'Workspace ready: %s (pages=%s, sections=%s, site=%s)',
            ! empty($workspaceStatus['ready']) ? 'yes' : 'no',
            ! empty($workspaceStatus['has_page_files']) ? 'yes' : 'no',
            ! empty($workspaceStatus['has_section_files']) ? 'yes' : 'no',
            ! empty($workspaceStatus['has_site']) ? 'yes' : 'no'
        );
        if (! empty($workspaceStatus['scaffold_seeded'])) {
            $diagnosticLog[] = 'Workspace scaffold created automatically.';
        }
        if (! empty($workspaceStatus['generated_from_cms'])) {
            $diagnosticLog[] = 'Workspace pages were generated from current CMS content.';
        }

        if (! $this->ai->isConfigured()) {
            return [
                'success' => false,
                'summary' => '',
                'changes' => [],
                'diagnostic_log' => ['Project edit is unavailable because AI is not configured.'],
                'error' => 'AI is not configured. Configure in Admin → Integrations.',
            ];
        }

        $diagnosticLog[] = $selectedElement !== null
            ? sprintf(
                'Selected element: section=%s path=%s element=%s',
                (string) ($selectedElement['section_id'] ?? 'n/a'),
                (string) ($selectedElement['parameter_path'] ?? 'n/a'),
                (string) ($selectedElement['element_id'] ?? 'n/a')
            )
            : 'Selected element: none';

        try {
            if (! empty($workspaceStatus['scaffold_seeded']) || ! empty($workspaceStatus['generated_from_cms'])) {
                $this->scanner->invalidateIndex($project);
            }
            $scan = $this->scanner->getScanFromIndex($project);
            if ($scan === null) {
                $scan = $this->scanner->scan($project);
                $this->scanner->writeIndex($project, $scan);
                $diagnosticLog[] = 'Workspace scan source: fresh scan';
            } else {
                $diagnosticLog[] = 'Workspace scan source: cached index';
            }
        } catch (\Throwable $e) {
            Log::warning('ai_project_edit.scan_failed', ['project_id' => $project->id, 'error' => $e->getMessage()]);

            return [
                'success' => false,
                'summary' => '',
                'changes' => [],
                'diagnostic_log' => [...$diagnosticLog, 'Workspace scan failed: '.$e->getMessage()],
                'error' => 'Could not read project structure. Ensure workspace is initialized.',
            ];
        }

        if ($this->isFullWebsiteRequest($userMessage)) {
            $diagnosticLog[] = 'Planner mode: full site generation';
            $sitePlanResult = $this->runFullSiteGeneration($project, $userMessage, $scan, $designPatternHints);
            if ($sitePlanResult !== null) {
                if (! empty($sitePlanResult['changes'])) {
                    $this->scanner->invalidateIndex($project);
                }

                $sitePlanResult['diagnostic_log'] = array_values(array_filter([
                    ...$diagnosticLog,
                    ...((is_array($sitePlanResult['diagnostic_log'] ?? null) ? $sitePlanResult['diagnostic_log'] : [])),
                    ! empty($sitePlanResult['changes']) ? 'Files changed: yes' : 'Files changed: no',
                ], static fn ($line): bool => is_string($line) && trim($line) !== ''));

                return $sitePlanResult;
            }
        }

        $diagnosticLog[] = 'Planner mode: targeted file edit';
        $plan = $this->plan($project, $userMessage, $scan, $selectedElement);
        if ($plan === null) {
            return [
                'success' => false,
                'summary' => '',
                'changes' => [],
                'diagnostic_log' => [...$diagnosticLog, 'Planner result: no valid edit plan.'],
                'error' => 'Could not produce a valid edit plan. Try rephrasing.',
            ];
        }
        $plan = $this->preparePlanOperations($project, $userMessage, $scan, $plan);

        $operations = $plan['operations'] ?? [];
        $summary = $plan['summary'] ?? 'No changes applied.';
        $diagnosticLog[] = 'Planned file operations: '.$this->summarizeFileOperations(is_array($operations) ? $operations : []);
        if ((isset($plan['no_change']) && $plan['no_change']) || $operations === []) {
            $this->executionLogger->log($project, $userMessage, [], $summary);

            return [
                'success' => true,
                'summary' => $summary,
                'changes' => [],
                'diagnostic_log' => [...$diagnosticLog, 'Files changed: no'],
                'no_change_reason' => $plan['reason'] ?? null,
            ];
        }

        $operations = $this->sortOperationsForDependencies($operations);
        $changes = [];
        $undoStack = [];

        try {
            foreach ($operations as $op) {
                $path = isset($op['path']) ? PathRules::normalizePath((string) $op['path']) : '';
                $operation = isset($op['op']) ? (string) $op['op'] : '';

                if ($path === '' || ! PathRules::isAllowed($path)) {
                    throw new \RuntimeException("Path not allowed or empty: {$path}");
                }

                $oldContent = in_array($operation, ['updateFile', 'deleteFile'], true)
                    ? $this->fileEditor->readFile($project, $path) : null;

                if ($operation === 'deleteFile') {
                    if (! $this->fileEditor->deleteFile($project, $path)) {
                        throw new \RuntimeException("Failed to delete file: {$path}");
                    }
                    $changes[] = ['path' => $path, 'op' => 'deleteFile', 'old_content' => $oldContent, 'new_content' => null];
                    $undoStack[] = ['op' => 'createFile', 'path' => $path, 'content' => $oldContent ?? ''];
                } elseif ($operation === 'createFile' || $operation === 'updateFile') {
                    $content = isset($op['content']) ? (string) $op['content'] : '';
                    if (! $this->fileEditor->writeFile($project, $path, $content)) {
                        throw new \RuntimeException("Failed to write file: {$path}");
                    }
                    $changes[] = ['path' => $path, 'op' => $operation, 'old_content' => $operation === 'updateFile' ? $oldContent : null, 'new_content' => $content];
                    $undoStack[] = $operation === 'createFile'
                        ? ['op' => 'deleteFile', 'path' => $path]
                        : ['op' => 'updateFile', 'path' => $path, 'content' => $oldContent ?? ''];
                }
            }

            $this->verifyChanges($project, $changes);

        } catch (\Throwable $e) {
            Log::warning('ai_project_edit.execution_failed_rollback', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
            $this->rollback($project, $undoStack);

            return [
                'success' => false,
                'summary' => '',
                'changes' => [],
                'diagnostic_log' => [...$diagnosticLog, 'Execution failed and rollback completed: '.$e->getMessage()],
                'error' => 'Multi-file edit failed: '.$e->getMessage().'. All changes were rolled back.',
            ];
        }

        $summary = $plan['summary'] ?? $this->summarizeChanges($changes);
        $this->executionLogger->log($project, $userMessage, $changes, $summary);
        $this->scanner->invalidateIndex($project);

        return [
            'success' => true,
            'summary' => $summary,
            'changes' => array_map(static fn (array $c): array => ['path' => $c['path'], 'op' => $c['op']], $changes),
            'diagnostic_log' => [...$diagnosticLog, 'Files changed: yes'],
            'created' => array_values(array_map(static fn (array $c): string => $c['path'], array_filter($changes, static fn (array $c): bool => ($c['op'] ?? '') === 'createFile'))),
            'updated' => array_values(array_map(static fn (array $c): string => $c['path'], array_filter($changes, static fn (array $c): bool => ($c['op'] ?? '') === 'updateFile'))),
            'deleted' => array_values(array_map(static fn (array $c): string => $c['path'], array_filter($changes, static fn (array $c): bool => ($c['op'] ?? '') === 'deleteFile'))),
        ];
    }

    /**
     * @param  array<int, array{op?: string, path?: string, content?: string}>  $operations
     */
    private function summarizeFileOperations(array $operations): string
    {
        if ($operations === []) {
            return 'none';
        }

        $counts = [];
        foreach ($operations as $operation) {
            $name = isset($operation['op']) && is_string($operation['op']) && trim($operation['op']) !== ''
                ? trim($operation['op'])
                : 'unknown';
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        ksort($counts);

        return implode(', ', array_map(
            static fn (string $name, int $count): string => sprintf('%s ×%d', $name, $count),
            array_keys($counts),
            array_values($counts)
        ));
    }

    /**
     * Sort operations so creates run first, then updates, then deletes (safe dependency order).
     *
     * @param  array<int, array{op: string, path?: string, content?: string}>  $operations
     * @return array<int, array{op: string, path?: string, content?: string}>
     */
    private function sortOperationsForDependencies(array $operations): array
    {
        $order = ['createFile' => 0, 'updateFile' => 1, 'deleteFile' => 2];
        usort($operations, static function (array $a, array $b) use ($order): int {
            $opA = $order[$a['op'] ?? ''] ?? 3;
            $opB = $order[$b['op'] ?? ''] ?? 3;

            return $opA <=> $opB;
        });

        return $operations;
    }

    /**
     * Rollback applied operations in reverse order (atomic undo).
     *
     * @param  array<int, array{op: string, path: string, content?: string}>  $undoStack
     */
    private function rollback(Project $project, array $undoStack): void
    {
        foreach (array_reverse($undoStack) as $undo) {
            $path = $undo['path'] ?? '';
            if ($path === '' || ! PathRules::isAllowed($path)) {
                continue;
            }
            if (($undo['op'] ?? '') === 'deleteFile') {
                $this->fileEditor->deleteFile($project, $path);
            } elseif (($undo['op'] ?? '') === 'createFile') {
                $this->fileEditor->writeFile($project, $path, (string) ($undo['content'] ?? ''));
            } elseif (($undo['op'] ?? '') === 'updateFile') {
                $this->fileEditor->writeFile($project, $path, (string) ($undo['content'] ?? ''));
            }
        }
    }

    /**
     * Verify that created/updated files contain the expected content (content diff).
     *
     * @param  array<int, array{path: string, op: string, new_content?: string|null}>  $changes
     */
    private function verifyChanges(Project $project, array $changes): void
    {
        foreach ($changes as $c) {
            $path = $c['path'] ?? '';
            $op = $c['op'] ?? '';
            if ($path === '' || ! PathRules::isAllowed($path)) {
                continue;
            }
            if ($op === 'deleteFile') {
                $current = $this->fileEditor->readFile($project, $path);
                if ($current !== null) {
                    throw new \RuntimeException("Verification failed: file still exists after delete: {$path}");
                }
                continue;
            }
            if ($op === 'createFile' || $op === 'updateFile') {
                $expected = $c['new_content'] ?? '';
                $current = $this->fileEditor->readFile($project, $path);
                if ($current === null) {
                    throw new \RuntimeException("Verification failed: file not found after write: {$path}");
                }
                if (trim((string) $current) !== trim((string) $expected)) {
                    throw new \RuntimeException("Verification failed: file content mismatch for: {$path}");
                }
            }
        }
    }

    /**
     * Heuristic: does the user request sound like a full multi-page website (e.g. "create a website for X")?
     */
    private function isFullWebsiteRequest(string $message): bool
    {
        $lower = strtolower($message);
        $triggers = [
            'website for',
            'full website',
            'multi-page',
            'multi page',
            'create a site for',
            'build a site for',
            'create a website',
            'build a website',
            'create website',
            'build website',
            'agency website',
            'digital agency',
            'marketing agency',
            'complete website',
            'whole website',
            'landing page',
            'saas landing',
            'saas startup',
            'restaurant website',
            'restaurant site',
            'portfolio website',
            'photography portfolio',
            'e-commerce',
            'ecommerce',
            'online store',
        ];

        foreach ($triggers as $t) {
            if (str_contains($lower, $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stage 1: AI returns a structured site plan (pages + sections per page). Stage 2: execute it.
     *
     * @param  array{pages: array, sections: array, ...}  $scan
     * @param  array<int, string>|null  $designPatternHints  Optional design memory hints for planner (layout quality enforced via design rules in prompt)
     * @return array{success: bool, summary: string, changes: array, created?: array, updated?: array, error?: string}|null
     */
    private function runFullSiteGeneration(Project $project, string $userMessage, array $scan, ?array $designPatternHints = null): ?array
    {
        $plannerResult = $this->sitePlanner->generate($project, $userMessage, $scan, $designPatternHints);
        $sitePlan = $plannerResult['plan'] ?? null;
        if (! is_array($sitePlan) || empty($sitePlan['pages']) || ! is_array($sitePlan['pages'])) {
            return null;
        }

        $availableSections = $this->getAvailableSectionNames($scan);

        $ensureResult = $this->ensurePlannedSectionsExist($project, $sitePlan, $availableSections, $userMessage);
        $availableSections = $ensureResult['available'];
        $scan = $ensureResult['scan'];
        $generatedChanges = $ensureResult['generated_changes'];

        $result = $this->executeSitePlan($project, $sitePlan, $availableSections, $scan);
        $allChanges = array_merge(
            array_map(static fn (array $c): array => ['path' => $c['path'], 'op' => $c['op']], $result['changes']),
            $generatedChanges
        );
        if ($allChanges === []) {
            return null;
        }

        $pageNames = array_map(static fn (array $p): string => $p['name'] ?? '', $sitePlan['pages']);
        $pageList = implode(', ', array_unique(array_filter($pageNames)));
        $sectionList = implode(', ', array_unique($result['sections_used'] ?? []));
        $summary = "Website created. Pages: {$pageList}. Sections used: {$sectionList}. Missing sections were generated where needed.";

        $this->executionLogger->log($project, $userMessage, $allChanges, $summary);

        $created = array_merge(
            $result['created'] ?? [],
            array_map(static fn (array $c): string => $c['path'], $generatedChanges)
        );

        return [
            'success' => true,
            'summary' => $summary,
            'changes' => $allChanges,
            'created' => $created,
            'updated' => $result['updated'] ?? [],
        ];
    }

    /**
     * When the site plan references sections that do not exist, generate them via Component Generator.
     * Returns the list of available section names (existing + newly generated) and any generated file changes.
     *
     * @param  array{pages: array<int, array{name: string, title?: string, sections: array<int, string>}>}  $sitePlan
     * @param  array<int, string>  $availableSections
     * @return array{available: array<int, string>, generated_changes: array<int, array{path: string, op: string}>, scan: array}
     */
    private function ensurePlannedSectionsExist(Project $project, array $sitePlan, array $availableSections, string $userMessage): array
    {
        $plannedNames = [];
        foreach ($sitePlan['pages'] ?? [] as $page) {
            foreach ($page['sections'] ?? [] as $s) {
                $name = is_string($s) ? trim($s) : '';
                if ($name !== '') {
                    $plannedNames[$name] = true;
                }
            }
        }
        $missing = array_values(array_filter(array_keys($plannedNames), static fn (string $name) => ! in_array($name, $availableSections, true)));
        if ($missing === []) {
            return ['available' => $availableSections, 'generated_changes' => [], 'scan' => $this->refreshScan($project)];
        }

        $list = array_values($availableSections);
        $generatedChanges = [];
        foreach ($missing as $sectionName) {
            $gen = $this->componentGenerator->generate($project, $sectionName, $userMessage);
            if (isset($gen['content'], $gen['path']) && $gen['content'] !== '' && ! ($gen['already_exists'] ?? false)) {
                if ($this->fileEditor->writeFile($project, $gen['path'], $gen['content'])) {
                    $list[] = basename($gen['path'], '.tsx');
                    $generatedChanges[] = ['path' => $gen['path'], 'op' => 'createFile'];
                }
            }
        }

        $scan = $this->refreshScan($project);

        return [
            'available' => array_values(array_unique($list)),
            'generated_changes' => $generatedChanges,
            'scan' => $scan,
        ];
    }

    /**
     * Stage 2 — Execute site plan: create or update each page file with the planned sections.
     *
     * @param  array{pages: array<int, array{name: string, title?: string, sections: array<int, string>}>}  $sitePlan
     * @param  array<int, string>  $availableSections
     * @return array{changes: array<int, array{path: string, op: string}>, created: array<int, string>, updated: array<int, string>, sections_used: array<int, string>}
     */
    private function executeSitePlan(Project $project, array $sitePlan, array $availableSections, array $scan): array
    {
        $changes = [];
        $created = [];
        $updated = [];
        $sectionsUsed = [];

        foreach ($sitePlan['pages'] ?? [] as $page) {
            $slug = $page['name'] ?? 'home';
            $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($slug)) ?: 'home';
            $sectionNames = [];
            foreach ($page['sections'] ?? [] as $s) {
                $sectionName = is_string($s) ? trim($s) : '';
                if ($sectionName !== '' && in_array($sectionName, $availableSections, true) && ! in_array($sectionName, $sectionNames, true)) {
                    $sectionNames[] = $sectionName;
                    $sectionsUsed[] = $sectionName;
                }
            }
            if ($sectionNames === []) {
                $fallbackSection = $availableSections[0] ?? 'HeroSection';
                $sectionNames[] = $fallbackSection;
                $sectionsUsed[] = $fallbackSection;
            }

            $path = "src/pages/{$slug}/Page.tsx";
            if (! PathRules::isAllowed($path)) {
                continue;
            }

            $existingContent = $this->fileEditor->readFile($project, $path);
            $pageTitle = $page['title'] ?? ucfirst($slug);
            $content = $this->buildPageTsxContent($project, $slug, $sectionNames, $pageTitle, $scan);

            if ($existingContent === null || trim($existingContent) === '') {
                if ($this->fileEditor->writeFile($project, $path, $content)) {
                    $changes[] = ['path' => $path, 'op' => 'createFile', 'old_content' => null, 'new_content' => $content];
                    $created[] = $path;
                }
            } else {
                if ($this->fileEditor->writeFile($project, $path, $content)) {
                    $changes[] = ['path' => $path, 'op' => 'updateFile', 'old_content' => $existingContent, 'new_content' => $content];
                    $updated[] = $path;
                }
            }
        }

        return [
            'changes' => $changes,
            'created' => $created,
            'updated' => $updated,
            'sections_used' => array_values(array_unique($sectionsUsed)),
        ];
    }

    /**
     * Default props for section components when generating pages (component parameter generation).
     * Sections that accept these props will display the copy; others ignore.
     */
    private function getDefaultSectionProps(string $sectionName, string $pageTitle, string $slug): array
    {
        $title = $pageTitle;
        $cta = 'Get Started';
        if ($slug === 'contact') {
            $title = 'Get In Touch';
            $cta = 'Send Message';
        } elseif ($slug === 'about') {
            $title = 'About Us';
        } elseif ($slug === 'services') {
            $title = 'Our Services';
            $cta = 'Learn More';
        }

        return match ($sectionName) {
            'HeroSection' => ['title' => $title, 'subtitle' => '', 'buttonText' => $cta],
            'CTASection' => ['title' => 'Ready to start?', 'subtitle' => '', 'buttonText' => $cta],
            'NewsletterSection' => ['title' => 'Subscribe', 'subtitle' => '', 'buttonText' => 'Subscribe'],
            'FeaturesSection' => ['title' => 'Features', 'subtitle' => ''],
            'TestimonialsSection' => ['title' => 'Testimonials', 'subtitle' => ''],
            default => [],
        };
    }

    /**
     * Build Page.tsx content for a given slug and section component names (existing components only).
     * Passes props only when the section file clearly accepts them.
     *
     * @param  array<int, string>  $sectionNames
     */
    private function buildPageTsxContent(Project $project, string $slug, array $sectionNames, string $pageTitle = '', array $scan = []): string
    {
        $imports = [];
        foreach (array_unique($sectionNames) as $name) {
            $imports[] = "import {$name} from '../../sections/{$name}';";
        }
        $imports[] = "import SiteLayout from '../../layouts/SiteLayout';";
        $importBlock = implode("\n", $imports);

        $children = [];
        foreach ($sectionNames as $name) {
            $props = $this->filterSupportedSectionProps(
                $project,
                $name,
                $this->getDefaultSectionProps($name, $pageTitle ?: ucfirst($slug), $slug),
                $scan
            );
            if ($props !== []) {
                $attr = implode(' ', array_map(static function (string $k, mixed $v): string {
                    return $k.'="'.htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8').'"';
                }, array_keys($props), array_values($props)));
                $children[] = "      <{$name} {$attr} />";
            } else {
                $children[] = "      <{$name} />";
            }
        }
        $childrenBlock = implode("\n", $children);

        $pageName = 'Page'.ucfirst(preg_replace('/[^a-z0-9]/i', '', $slug) ?: 'Home');

        return <<<TSX
{$importBlock}

export default function {$pageName}() {
  return (
    <SiteLayout>
{$childrenBlock}
    </SiteLayout>
  );
}

TSX;
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array{file_contents?: array<string, string>}  $scan
     * @return array<string, mixed>
     */
    private function filterSupportedSectionProps(Project $project, string $sectionName, array $props, array $scan): array
    {
        if ($props === []) {
            return [];
        }

        $acceptedProps = $this->getSupportedSectionProps($project, $sectionName, $scan);
        if ($acceptedProps === []) {
            return [];
        }

        return array_intersect_key($props, array_flip($acceptedProps));
    }

    /**
     * @param  array{file_contents?: array<string, string>}  $scan
     * @return array<int, string>
     */
    private function getSupportedSectionProps(Project $project, string $sectionName, array $scan): array
    {
        $content = $this->readSectionFileContent($project, $sectionName, $scan);
        if ($content === null || trim($content) === '') {
            return [];
        }

        $knownProps = ['title', 'subtitle', 'buttonText', 'buttonLink', 'headline', 'description'];
        $accepted = [];

        $patterns = [
            '/export\s+default\s+function\s+\w+\s*\(\s*\{\s*([^}]*)\}\s*[:)]/s',
            '/function\s+\w+\s*\(\s*\{\s*([^}]*)\}\s*[:)]/s',
            '/=\s*\(\s*\{\s*([^}]*)\}\s*[:)]/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches) === 1) {
                foreach (preg_split('/,/', $matches[1]) ?: [] as $prop) {
                    $candidate = trim((string) preg_replace('/[:=].*$/', '', $prop));
                    if ($candidate !== '' && in_array($candidate, $knownProps, true)) {
                        $accepted[$candidate] = $candidate;
                    }
                }
            }
        }

        foreach ($knownProps as $prop) {
            if (preg_match('/\bprops\.'.preg_quote($prop, '/').'\b/', $content) === 1) {
                $accepted[$prop] = $prop;
            }
        }

        return array_values($accepted);
    }

    /**
     * @param  array{file_contents?: array<string, string>}  $scan
     */
    private function readSectionFileContent(Project $project, string $sectionName, array $scan): ?string
    {
        $path = "src/sections/{$sectionName}.tsx";
        $scanContent = $scan['file_contents'][$path] ?? null;
        if (is_string($scanContent) && trim($scanContent) !== '') {
            return $scanContent;
        }

        return $this->fileEditor->readFile($project, $path);
    }

    /**
     * Section component names that exist in this project (from scan). Used for full-page generation.
     *
     * @param  array{sections: array<int, string>}  $scan
     * @return array<int, string>
     */
    private function getAvailableSectionNames(array $scan): array
    {
        $names = [];
        foreach ($scan['sections'] ?? [] as $path) {
            $base = basename($path, '.tsx');
            if ($base !== '' && ! in_array($base, $names, true)) {
                $names[] = $base;
            }
        }
        sort($names);

        return array_values($names);
    }

    /**
     * @param  array{operations?: array, summary: string, no_change?: bool, reason?: string}  $plan
     * @param  array{sections?: array<int, string>}  $scan
     * @return array{operations?: array, summary: string, no_change?: bool, reason?: string}
     */
    private function preparePlanOperations(Project $project, string $userMessage, array $scan, array $plan): array
    {
        if (! isset($plan['operations']) || ! is_array($plan['operations'])) {
            return $plan;
        }

        $availableSections = $this->getAvailableSectionNames($scan);
        $existingSectionPaths = array_fill_keys($scan['sections'] ?? [], true);
        $generatedOperations = [];
        $normalizedOperations = [];
        $generatedPaths = [];

        foreach ($plan['operations'] as $operation) {
            $path = isset($operation['path']) ? PathRules::normalizePath((string) $operation['path']) : '';
            $opName = isset($operation['op']) ? (string) $operation['op'] : '';

            if ($path !== '' && str_starts_with($path, 'src/sections/') && str_ends_with($path, '.tsx') && ! isset($existingSectionPaths[$path])) {
                $sectionName = $this->sectionNameNormalizer->normalize(basename($path, '.tsx'));
                $generated = $this->buildGeneratedSectionOperation($project, $sectionName, $userMessage);
                if ($generated !== null) {
                    $generatedOperations[] = $generated;
                    $generatedPaths[$generated['path']] = true;
                    $availableSections[] = basename($generated['path'], '.tsx');
                    continue;
                }
            }

            if ($path !== '') {
                $operation['path'] = $path;
            }
            if ($opName !== '') {
                $operation['op'] = $opName;
            }
            $normalizedOperations[] = $operation;
        }

        foreach ($this->extractReferencedSectionNamesFromOperations($normalizedOperations) as $sectionName) {
            if (in_array($sectionName, $availableSections, true)) {
                continue;
            }

            $generated = $this->buildGeneratedSectionOperation($project, $sectionName, $userMessage);
            if ($generated === null || isset($generatedPaths[$generated['path']])) {
                continue;
            }

            $generatedOperations[] = $generated;
            $generatedPaths[$generated['path']] = true;
            $availableSections[] = basename($generated['path'], '.tsx');
        }

        $plan['operations'] = array_merge($generatedOperations, $normalizedOperations);

        return $plan;
    }

    /**
     * @return array{op: string, path: string, content: string}|null
     */
    private function buildGeneratedSectionOperation(Project $project, string $sectionName, string $userMessage): ?array
    {
        $generated = $this->componentGenerator->generate($project, $sectionName, $userMessage);
        if (! ($generated['success'] ?? false) || empty($generated['path'])) {
            return null;
        }

        if (! empty($generated['already_exists'])) {
            return null;
        }

        $path = PathRules::normalizePath((string) $generated['path']);
        $content = isset($generated['content']) ? (string) $generated['content'] : '';
        if ($path === '' || $content === '') {
            return null;
        }

        return [
            'op' => 'createFile',
            'path' => $path,
            'content' => $content,
        ];
    }

    /**
     * @param  array<int, array{op?: string, path?: string, content?: string}>  $operations
     * @return array<int, string>
     */
    private function extractReferencedSectionNamesFromOperations(array $operations): array
    {
        $names = [];

        foreach ($operations as $operation) {
            $path = isset($operation['path']) ? PathRules::normalizePath((string) $operation['path']) : '';
            $content = isset($operation['content']) ? (string) $operation['content'] : '';

            if ($path !== '' && str_starts_with($path, 'src/sections/') && str_ends_with($path, '.tsx')) {
                $sectionName = $this->sectionNameNormalizer->normalize(basename($path, '.tsx'));
                if ($sectionName !== '') {
                    $names[$sectionName] = $sectionName;
                }
            }

            if ($content === '') {
                continue;
            }

            if (preg_match_all('/from\s+[\'"][^\'"]*sections\/([A-Za-z0-9_]+)[\'"]/', $content, $matches) > 0) {
                foreach ($matches[1] ?? [] as $match) {
                    $sectionName = $this->sectionNameNormalizer->normalize((string) $match);
                    if ($sectionName !== '') {
                        $names[$sectionName] = $sectionName;
                    }
                }
            }

            if (preg_match_all('/<([A-Z][A-Za-z0-9]*)\b/', $content, $matches) > 0) {
                foreach ($matches[1] ?? [] as $match) {
                    if (! str_ends_with((string) $match, 'Section')) {
                        continue;
                    }

                    $sectionName = $this->sectionNameNormalizer->normalize((string) $match);
                    if ($sectionName !== '') {
                        $names[$sectionName] = $sectionName;
                    }
                }
            }
        }

        return array_values($names);
    }

    /**
     * @return array{pages: array, sections: array, components: array, layouts: array, styles: array, public: array, imports_sample: array, file_contents: array, page_structure: array, component_parameters: array}
     */
    private function refreshScan(Project $project): array
    {
        $this->scanner->invalidateIndex($project);
        $scan = $this->scanner->scan($project);
        $this->scanner->writeIndex($project, $scan);

        return $scan;
    }

    /**
     * Registry hints for page generation: known section components and their suggested use / optional props.
     * AI uses this to pick sections and optionally pass props. Only sections present in the project may be used.
     */
    private function getPageGenerationRegistryHints(): string
    {
        $hints = [
            'HeroSection' => 'Hero / banner. Optional props: title, subtitle, buttonText, buttonLink.',
            'FeaturesSection' => 'Features grid. Optional: title, subtitle, feature items.',
            'CTASection' => 'Call to action. Optional: title, subtitle, buttonText, buttonLink. Use for pricing/promo if no PricingSection.',
            'NewsletterSection' => 'Newsletter signup. Optional: title, subtitle, buttonText.',
            'TestimonialsSection' => 'Testimonials. Optional: title, subtitle.',
            'ProductGridSection' => 'Product grid (ecommerce). Optional: title.',
            'TextSection' => 'Rich text block.',
            'HeadingSection' => 'Heading block.',
            'CardSection' => 'Card content.',
            'FormWrapperSection' => 'Contact/form.',
            'SpacerSection' => 'Vertical spacer.',
            'ImageSection' => 'Image block.',
        ];

        $lines = [];
        foreach ($hints as $name => $desc) {
            $lines[] = "  - {$name}: {$desc}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{pages: array, sections: array, components: array, layouts: array, styles: array, public: array, imports_sample: array, file_contents?: array<string, string>, component_parameters?: array<string, mixed>}  $scan
     * @param  array{section_id: string, parameter_path: string, element_id: string, page_id?: mixed, page_slug?: string|null, component_path?: string|null, component_type?: string|null, component_name?: string|null, editable_fields?: array<int, string>, variants?: array<string, mixed>|null, allowed_updates?: array<string, mixed>|null, current_breakpoint?: string|null, current_interaction_state?: string|null, responsive_context?: array<string, mixed>|null}|null  $selectedElement
     * @return array{operations?: array, summary: string, no_change?: bool, reason?: string}|null
     */
    private function plan(Project $project, string $userMessage, array $scan, ?array $selectedElement = null): ?array
    {
        $pagesList = implode("\n", array_map(static fn (string $p): string => '  - '.$p, $scan['pages']));
        $sectionsList = implode("\n", array_map(static fn (string $s): string => '  - '.$s, $scan['sections']));
        $componentsList = implode("\n", array_map(static fn (string $c): string => '  - '.$c, $scan['components']));
        $layoutsList = implode("\n", array_map(static fn (string $l): string => '  - '.$l, $scan['layouts']));
        $stylesList = implode("\n", array_map(static fn (string $s): string => '  - '.$s, $scan['styles']));
        $publicList = implode("\n", array_map(static fn (string $p): string => '  - '.$p, $scan['public']));
        $importsSample = $scan['imports_sample'] ?? [];
        $importsText = '';
        foreach (array_slice($importsSample, 0, 15) as $path => $block) {
            $importsText .= "File: {$path}\n".$block."\n\n";
        }

        $fileContents = $this->selectRelevantFileContents($project, $scan, $userMessage, $selectedElement);
        $fileContentsText = '';
        foreach ($fileContents as $path => $content) {
            $fileContentsText .= "--- BEGIN {$path} ---\n".$content."\n--- END {$path} ---\n\n";
        }
        if ($fileContentsText !== '') {
            $fileContentsText = "Current content of files you may need to update (use for updateFile; output full file content with your changes):\n\n".$fileContentsText;
        }
        $componentParametersText = $this->formatComponentParametersForPrompt($scan['component_parameters'] ?? []);
        $pageStructureText = $this->formatPageStructureForPrompt($scan['page_structure'] ?? []);
        $projectionMetadataText = $this->formatProjectionMetadataForPrompt($scan['projection_metadata'] ?? []);
        $workspaceFilesText = $this->formatWorkspaceFilesForPrompt($scan['workspace_files'] ?? []);
        $workingLanguageInstruction = $this->buildWorkingLanguageInstruction($userMessage);

        $availableSectionNames = $this->getAvailableSectionNames($scan);
        $availableSectionsLine = $availableSectionNames !== [] ? implode(', ', $availableSectionNames) : '(none)';
        $registryHints = $this->getPageGenerationRegistryHints();
        $designRulesFragment = $this->designRules->getPromptFragment();

        $prompt = $designRulesFragment.<<<PROMPT

---

You are a senior developer editing code at Codex/Cursor level: precise, minimal, surgical changes. Apply only what the user asked; preserve formatting, indentation, and all code you do not need to change. For updateFile: output the full file content with your edit applied exactly where needed (like applying a precise diff). Do not rewrite entire files unnecessarily; do not change style or structure unless the user requested it. Think step-by-step: identify the exact line(s) or block(s) to change, then output the complete file with only those changes.

You are performing a multi-file refactor in a Webu React/TypeScript project. One user request may require several coordinated file changes. Plan all required operations before applying. All generated layout and section markup MUST follow the Webu Design System rules above (container, spacing, typography, grid).
The only editable code targets are the real workspace files listed below. Never plan edits against synthetic or derived preview paths such as derived-preview/* or legacy src/__generated_pages__ snapshots.
Files marked as cms-projection are real workspace files generated from CMS authority. Editing them is allowed, but it should be treated as creating a custom workspace override that future regenerate-from-site runs must preserve.

Project structure:

Pages:
{$pagesList}

Sections (src/sections):
{$sectionsList}

Components (src/components):
{$componentsList}

Layouts (src/layouts):
{$layoutsList}

Styles and theme files:
{$stylesList}

Public assets:
{$publicList}

Workspace file states (same real files shown in the Code tab):
{$workspaceFilesText}

Current page structure:
{$pageStructureText}

Workspace projection metadata (real editable workspace generated from CMS authority):
{$projectionMetadataText}

EXISTING SECTIONS IN THIS PROJECT (use ONLY these for full-page generation — do not invent new section files):
{$availableSectionsLine}

Builder component registry hints (use only sections that exist above; optional props when the section supports them):
{$registryHints}

Sample imports (for reference):
{$importsText}
{$fileContentsText}

Editable parameters already detected in workspace files:
{$componentParametersText}

{$workingLanguageInstruction}

PROMPT;
        $selectedElementHint = '';
        if ($selectedElement !== null && isset($selectedElement['element_id'], $selectedElement['parameter_path'], $selectedElement['section_id'])) {
            $elId = $selectedElement['element_id'];
            $param = $selectedElement['parameter_path'];
            $secId = $selectedElement['section_id'];
            $componentType = isset($selectedElement['component_type']) && is_string($selectedElement['component_type']) ? trim($selectedElement['component_type']) : '';
            $componentName = isset($selectedElement['component_name']) && is_string($selectedElement['component_name']) ? trim($selectedElement['component_name']) : '';
            $pageSlug = isset($selectedElement['page_slug']) && is_string($selectedElement['page_slug']) ? trim($selectedElement['page_slug']) : '';
            $componentPath = isset($selectedElement['component_path']) && is_string($selectedElement['component_path']) ? trim($selectedElement['component_path']) : '';
            $currentBreakpoint = isset($selectedElement['current_breakpoint']) && is_string($selectedElement['current_breakpoint']) ? trim($selectedElement['current_breakpoint']) : '';
            $currentInteractionState = isset($selectedElement['current_interaction_state']) && is_string($selectedElement['current_interaction_state']) ? trim($selectedElement['current_interaction_state']) : '';
            $editableFields = isset($selectedElement['editable_fields']) && is_array($selectedElement['editable_fields'])
                ? array_values(array_filter(array_map(static fn ($field) => is_string($field) ? trim($field) : '', $selectedElement['editable_fields'])))
                : [];
            $allowedUpdates = is_array($selectedElement['allowed_updates'] ?? null) ? $selectedElement['allowed_updates'] : [];
            $allowedFieldPaths = is_array($allowedUpdates['fieldPaths'] ?? null)
                ? array_values(array_filter(array_map(static fn ($field) => is_string($field) ? trim($field) : '', $allowedUpdates['fieldPaths'])))
                : [];
            $selectedElementHint = "\n\nSelected element (apply the user's change to this specific element): element_id={$elId}, section_id={$secId}, parameter_path={$param}."
                .($pageSlug !== '' ? " page_slug={$pageSlug}." : '')
                .($componentName !== '' ? " component_name={$componentName}." : '')
                .($componentType !== '' ? " component_type={$componentType}." : '')
                .($componentPath !== '' ? " component_path={$componentPath}." : '')
                .($editableFields !== [] ? ' editable_fields='.implode(', ', $editableFields).'.' : '')
                .($allowedFieldPaths !== [] ? ' allowed_field_paths='.implode(', ', $allowedFieldPaths).'.' : '')
                .($currentBreakpoint !== '' ? " current_breakpoint={$currentBreakpoint}." : '')
                .($currentInteractionState !== '' ? " current_interaction_state={$currentInteractionState}." : '')
                ." Update the component or page that renders this section so that the prop/parameter '{$param}' is set to the value the user requested. Use updateFile on the page or section file that passes props to this component.\n\n";
        }
        $prompt .= $selectedElementHint."User request: {$userMessage}\n\n";
        $prompt .= <<<REST
Full-page generation (Lovable-style, use existing builder components only):
- When the user asks to "create landing page", "ecommerce homepage", "portfolio page", "restaurant page", "SaaS homepage", or similar, you MUST assemble the page using ONLY the existing sections listed above. Do NOT create new section files (no createFile for src/sections/*). Update ONLY the page file (e.g. src/pages/home/Page.tsx): add imports for the sections you need (only from the list), then render them inside SiteLayout in a logical order (e.g. Hero → Features → Testimonials → CTA → Newsletter). Only pass props that the section component actually accepts (check its function signature in the file content); if it has no parameters, use <SectionName />. All generated pages must remain fully editable in the existing Webu builder.

Multi-file refactor rules (Codex-style precision):
1. Edit like Codex: minimal, targeted changes. For updateFile, output the full file but change only the exact lines/blocks that need to change (e.g. one prop, one string, one className). Preserve indentation, line breaks, and all surrounding code.
2. Plan the full change set: new components/sections first, then files that import or use them. Order operations as: createFile → updateFile → deleteFile.
3. Allowed paths ONLY: src/pages, src/components, src/sections, src/layouts, src/styles, src/theme, public. Forbidden: builder-core, system, node_modules, server, .git.
4. When adding a new reusable section under src/sections, DO NOT hand-write the new section file content. The backend will generate missing section files automatically. Instead, reference the final PascalCase component name in the page update and import it from ../../sections/ComponentName. Only output createFile/updateFile for src/sections/* when editing an existing section file.
5. For updateFile: always output the complete file content (so the backend can replace the file); inside that content, only modify what the user asked — leave everything else character-for-character unchanged where possible.
6. Design tokens: if the user asks to change colors, spacing, typography, or theme, edit src/theme/designTokens.css (or the project’s theme file). Use the existing :root variables; change only the values that need to change.
7. If the request cannot be done (target does not exist or path not allowed), output no_change with reason.
8. Output valid JSON only. No markdown, no code fence.

Output format (choose one):

A) Plan with file operations (list createFile before updateFile before deleteFile). For full-page generation use only updateFile on the page, no createFile for sections:
{"operations":[{"op":"updateFile","path":"src/pages/home/Page.tsx","content":"...full file content with imports and <HeroSection title=\\"...\\" /> <FeaturesSection /> <CTASection /> ..."}],"summary":"Page created. Sections added: HeroSection, FeaturesSection, CTASection. All use existing Webu components."}

B) Or when requesting a missing reusable section that Webu will generate automatically:
{"operations":[{"op":"updateFile","path":"src/pages/home/Page.tsx","content":"...full file content importing ../../sections/PricingSection and rendering <PricingSection />..."}],"summary":"Added PricingSection to the home page. Webu should generate the missing reusable section file."}

C) No change possible:
{"no_change":true,"summary":"No changes applied.","reason":"..."}

Return only the JSON object.
REST;

        $response = $this->ai->completeForProjectEdit($prompt, 8000);
        if ($response === null || trim($response) === '') {
            return null;
        }

        $json = $this->extractJson($response);
        if ($json === null || ! is_array($json)) {
            return null;
        }

        if (! empty($json['no_change']) && isset($json['summary'])) {
            return [
                'no_change' => true,
                'summary' => (string) $json['summary'],
                'reason' => isset($json['reason']) ? (string) $json['reason'] : null,
            ];
        }

        $operations = $json['operations'] ?? [];
        if (! is_array($operations)) {
            return null;
        }

        return [
            'operations' => $operations,
            'summary' => isset($json['summary']) ? (string) $json['summary'] : 'Changes applied.',
        ];
    }

    /**
     * @param  array{pages?: array<int, string>, sections?: array<int, string>, components?: array<int, string>, layouts?: array<int, string>, styles?: array<int, string>, public?: array<int, string>, file_contents?: array<string, string>, page_structure?: array<string, array<int, string>>, component_parameters?: array<string, mixed>}  $scan
     * @param  array{section_id?: string, parameter_path?: string, element_id?: string, page_id?: mixed, page_slug?: string|null, component_path?: string|null, component_type?: string|null, component_name?: string|null}|null  $selectedElement
     * @return array<string, string>
     */
    private function selectRelevantFileContents(Project $project, array $scan, string $userMessage, ?array $selectedElement = null): array
    {
        return $this->scanner->selectRelevantFileContents($project, $scan, $userMessage, $selectedElement);
    }

    /**
     * @param  array<string, mixed>  $componentParameters
     */
    private function formatComponentParametersForPrompt(array $componentParameters): string
    {
        $lines = [];

        foreach (['sections', 'components', 'layouts'] as $bucket) {
            $entries = is_array($componentParameters[$bucket] ?? null) ? $componentParameters[$bucket] : [];
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $component = trim((string) ($entry['component'] ?? ''));
                $fields = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];
                $fieldNames = [];
                foreach ($fields as $field) {
                    if (! is_array($field)) {
                        continue;
                    }

                    $parameterName = trim((string) ($field['parameterName'] ?? ''));
                    if ($parameterName !== '') {
                        $fieldNames[] = $parameterName;
                    }
                }

                if ($component === '' || $fieldNames === []) {
                    continue;
                }

                $lines[] = "  - {$component}: ".implode(', ', array_values(array_unique($fieldNames)));
            }
        }

        if ($lines === []) {
            return '  - none detected';
        }

        return implode("\n", array_slice($lines, 0, 24));
    }

    /**
     * @param  array<string, array<int, string>>  $pageStructure
     */
    private function formatPageStructureForPrompt(array $pageStructure): string
    {
        if ($pageStructure === []) {
            return '  - none detected';
        }

        $lines = [];
        foreach ($pageStructure as $slug => $imports) {
            $pageSlug = is_string($slug) && trim($slug) !== '' ? trim($slug) : 'unknown';
            $usedComponents = is_array($imports)
                ? implode(', ', array_slice(array_values(array_filter(array_map(
                    static fn ($value): string => is_string($value) ? trim($value) : '',
                    $imports
                ))), 0, 12))
                : '';
            $lines[] = $usedComponents !== ''
                ? "  - {$pageSlug}: {$usedComponents}"
                : "  - {$pageSlug}: no imports detected";
        }

        return implode("\n", array_slice($lines, 0, 24));
    }

    /**
     * @param  array<string, mixed>  $projectionMetadata
     */
    private function formatProjectionMetadataForPrompt(array $projectionMetadata): string
    {
        $lines = [];

        foreach ((is_array($projectionMetadata['pages'] ?? null) ? $projectionMetadata['pages'] : []) as $pageEntry) {
            if (! is_array($pageEntry)) {
                continue;
            }

            $slug = trim((string) ($pageEntry['slug'] ?? ''));
            $path = trim((string) ($pageEntry['path'] ?? ''));
            if ($slug === '' || $path === '') {
                continue;
            }

            $sectionSummaries = [];
            foreach ((is_array($pageEntry['sections'] ?? null) ? $pageEntry['sections'] : []) as $sectionEntry) {
                if (! is_array($sectionEntry)) {
                    continue;
                }

                $componentName = trim((string) ($sectionEntry['component_name'] ?? ''));
                $componentPath = trim((string) ($sectionEntry['component_path'] ?? ''));
                $propPaths = array_values(array_filter(array_map(
                    static fn ($value): string => is_string($value) ? trim($value) : '',
                    is_array($sectionEntry['prop_paths'] ?? null) ? $sectionEntry['prop_paths'] : []
                )));
                $variantSummary = $this->formatProjectionVariantSummary($sectionEntry['variants'] ?? null);
                if ($componentName === '') {
                    continue;
                }

                $sectionSummaries[] = $componentPath !== ''
                    ? sprintf(
                        '%s (%s)%s%s',
                        $componentName,
                        $componentPath,
                        $propPaths !== [] ? ' props='.implode(', ', array_slice($propPaths, 0, 6)) : '',
                        $variantSummary !== '' ? ' variants='.$variantSummary : ''
                    )
                    : $componentName;
            }

            $layoutFiles = array_values(array_filter(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                is_array($pageEntry['layout_files'] ?? null) ? $pageEntry['layout_files'] : []
            )));

            $lines[] = sprintf(
                '  - page %s -> %s%s%s',
                $slug,
                $path,
                $layoutFiles !== [] ? ' | layout='.implode(', ', $layoutFiles) : '',
                $sectionSummaries !== [] ? ' | sections='.implode('; ', array_slice($sectionSummaries, 0, 6)) : ''
            );
        }

        foreach ((is_array($projectionMetadata['layouts'] ?? null) ? $projectionMetadata['layouts'] : []) as $layoutEntry) {
            if (! is_array($layoutEntry)) {
                continue;
            }

            $componentName = trim((string) ($layoutEntry['component_name'] ?? ''));
            $path = trim((string) ($layoutEntry['path'] ?? ''));
            $propPaths = array_values(array_filter(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                is_array($layoutEntry['prop_paths'] ?? null) ? $layoutEntry['prop_paths'] : []
            )));
            $variantSummary = $this->formatProjectionVariantSummary($layoutEntry['variants'] ?? null);

            if ($componentName === '' || $path === '') {
                continue;
            }

            $lines[] = sprintf(
                '  - layout %s -> %s%s%s',
                $componentName,
                $path,
                $propPaths !== [] ? ' | props='.implode(', ', array_slice($propPaths, 0, 8)) : '',
                $variantSummary !== '' ? ' | variants='.$variantSummary : ''
            );
        }

        foreach ((is_array($projectionMetadata['components'] ?? null) ? $projectionMetadata['components'] : []) as $componentEntry) {
            if (! is_array($componentEntry)) {
                continue;
            }

            $componentName = trim((string) ($componentEntry['component_name'] ?? ''));
            $path = trim((string) ($componentEntry['path'] ?? ''));
            if ($componentName === '' || $path === '') {
                continue;
            }

            $pages = array_values(array_filter(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                is_array($componentEntry['pages'] ?? null) ? $componentEntry['pages'] : []
            )));
            $propPaths = array_values(array_filter(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                is_array($componentEntry['prop_paths'] ?? null) ? $componentEntry['prop_paths'] : []
            )));
            $variantSummary = $this->formatProjectionVariantSummary($componentEntry['variants'] ?? null);

            $lines[] = sprintf(
                '  - component %s -> %s%s%s%s',
                $componentName,
                $path,
                $pages !== [] ? ' | pages='.implode(', ', $pages) : '',
                $propPaths !== [] ? ' | props='.implode(', ', array_slice($propPaths, 0, 8)) : '',
                $variantSummary !== '' ? ' | variants='.$variantSummary : ''
            );
        }

        if ($lines === []) {
            return '  - none detected';
        }

        return implode("\n", array_slice(array_values(array_unique($lines)), 0, 32));
    }

    private function formatProjectionVariantSummary(mixed $variants): string
    {
        if (! is_array($variants)) {
            return '';
        }

        $parts = [];
        foreach (['layout', 'style'] as $variantKey) {
            $values = array_values(array_filter(array_map(
                static fn ($value): string => is_string($value) ? trim($value) : '',
                is_array($variants[$variantKey] ?? null) ? $variants[$variantKey] : []
            )));
            if ($values !== []) {
                $parts[] = $variantKey.'='.implode('/', array_slice($values, 0, 4));
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $workspaceFiles
     */
    private function formatWorkspaceFilesForPrompt(array $workspaceFiles): string
    {
        if ($workspaceFiles === []) {
            return '  - none detected';
        }

        $lines = [];
        foreach ($workspaceFiles as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $path = trim((string) ($entry['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            if (str_starts_with($path, 'derived-preview/') || str_contains($path, '__generated_pages__')) {
                continue;
            }

            $projectionSource = trim((string) ($entry['projection_source'] ?? 'custom'));
            $projectionRole = trim((string) ($entry['projection_role'] ?? ''));
            $editable = ($entry['is_editable'] ?? true) ? 'editable' : 'read-only';

            $detail = $projectionSource !== '' ? $projectionSource : 'custom';
            if ($projectionRole !== '') {
                $detail .= ", role={$projectionRole}";
            }

            $lines[] = sprintf('  - %s [%s, %s]', $path, $detail, $editable);
        }

        if ($lines === []) {
            return '  - none detected';
        }

        return implode("\n", array_slice(array_values(array_unique($lines)), 0, 36));
    }

    private function buildWorkingLanguageInstruction(string $userMessage): string
    {
        if ($this->looksLikeEnglishCommand($userMessage)) {
            return 'The user may mix English with project terminology. Preserve requested copy exactly and keep any provided English text verbatim unless asked to translate it.';
        }

        if ($this->looksLikeGeorgianCommand($userMessage)) {
            return "The user's primary working language is Georgian. Treat spelling mistakes, colloquial phrasing, and romanized Georgian as valid Georgian instructions. Preserve requested Georgian copy exactly.";
        }

        return 'The user may write in Georgian or English. Preserve requested visible copy exactly, do not translate unless explicitly asked.';
    }

    private function looksLikeGeorgianCommand(string $message): bool
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/[\x{10A0}-\x{10FF}]/u', $trimmed) === 1) {
            return true;
        }

        $normalized = mb_strtolower($trimmed, 'UTF-8');
        $hints = ['qartulad', 'kartulad', 'qartuli', 'kartuli', 'gaakete', 'shecvale', 'gadaxede', 'magazia'];
        foreach ($hints as $hint) {
            if (str_contains($normalized, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeEnglishCommand(string $message): bool
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/[\x{10A0}-\x{10FF}]/u', $trimmed) === 1) {
            return false;
        }

        return preg_match('/[A-Za-z]/', $trimmed) === 1;
    }

    private function extractJson(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            $text = trim($m[1]);
        }
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $text = $m[0];
        }
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int, array{path: string, op: string}>  $changes
     */
    private function summarizeChanges(array $changes): string
    {
        $parts = [];
        $created = array_filter($changes, static fn (array $c): bool => ($c['op'] ?? '') === 'createFile');
        $updated = array_filter($changes, static fn (array $c): bool => ($c['op'] ?? '') === 'updateFile');
        $deleted = array_filter($changes, static fn (array $c): bool => ($c['op'] ?? '') === 'deleteFile');
        if ($created !== []) {
            $parts[] = 'Created: '.implode(', ', array_column($created, 'path'));
        }
        if ($updated !== []) {
            $parts[] = 'Updated: '.implode(', ', array_column($updated, 'path'));
        }
        if ($deleted !== []) {
            $parts[] = 'Deleted: '.implode(', ', array_column($deleted, 'path'));
        }

        return $parts !== [] ? implode('. ', $parts) : 'No changes applied.';
    }
}
