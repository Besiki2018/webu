<?php

namespace App\Services\UnifiedAgent;

use App\Models\Project;
use App\Services\AiAgentExecutorService;
use App\Services\AiInterpretCommandService;
use App\Services\AiSiteEditorAnalyzeService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\SiteProvisioningService;
use App\Services\WebuCodex\CodebaseScanner;
use Illuminate\Support\Facades\Log;

/**
 * Unified Webu Site Agent v1.
 *
 * Single orchestrator for all AI site work. Owns full lifecycle:
 * context collection → intent resolution → plan → tool execution → verification → response.
 *
 * Replaces the split between generate-website, ai-site-editor, and ai-project-edit from the user's perspective.
 * Georgian-first, Codex-like website/design agent.
 */
class UnifiedWebuSiteAgentOrchestrator
{
    public function __construct(
        protected AiSiteEditorAnalyzeService $analyzeService,
        protected AiInterpretCommandService $interpretService,
        protected AiAgentExecutorService $executor,
        protected SiteProvisioningService $siteProvisioning,
        protected ProjectWorkspaceService $workspace,
        protected CodebaseScanner $codebaseScanner,
        protected GeorgianCommandNormalizer $georgianNormalizer,
        protected AgentVerificationService $verification,
        protected ContextCollector $contextCollector
    ) {}

    /**
     * Run the unified agent: collect context, interpret, execute, verify.
     * For edit requests only. Generation uses runGeneration().
     *
     * @param  array{
     *   instruction: string,
     *   page_slug?: string|null,
     *   page_id?: int|null,
     *   locale?: string|null,
     *   selected_target?: array|null,
     *   recent_edits?: string|null,
     *   project_mode?: 'builder'|'cms'|'code',
     *   publish?: bool,
     *   actor_id?: int|null
     * }  $input
     * @return array{
     *   success: bool,
     *   error?: string,
     *   error_code?: string,
     *   diagnostic_log?: array<int, string>,
     *   summary?: array<int, string>,
     *   page?: array,
     *   revision?: array,
     *   action_log?: array,
     *   applied_changes?: array,
     *   highlight_section_ids?: array
     * }
     */
    public function runEdit(Project $project, array $input): array
    {
        $instruction = trim((string) ($input['instruction'] ?? ''));
        $diagnosticLog = [];

        if ($instruction === '') {
            return [
                'success' => false,
                'error' => 'Instruction is required.',
                'error_code' => 'empty_instruction',
                'diagnostic_log' => ['Request rejected: empty instruction.'],
            ];
        }

        $normalized = $this->georgianNormalizer->normalize($instruction);
        $diagnosticLog[] = sprintf(
            'Locale: %s, Georgian primary: %s',
            $normalized['locale'],
            $normalized['is_georgian_primary'] ? 'yes' : 'no'
        );

        $context = $this->contextCollector->collect($project, [
            'page_slug' => $input['page_slug'] ?? null,
            'page_id' => $input['page_id'] ?? null,
            'locale' => $input['locale'] ?? $normalized['locale'],
            'selected_target' => $input['selected_target'] ?? null,
            'recent_edits' => $input['recent_edits'] ?? null,
            'project_mode' => $input['project_mode'] ?? 'builder',
        ]);
        $diagnosticLog[] = 'Context collected: pages='.count($context->cmsPages).', global_components='.count($context->globalComponents);

        $pageContext = $context->toPageContextForInterpret();
        $pageContext['locale'] = $normalized['locale'];

        $interpretResult = $this->interpretService->interpret(
            $normalized['normalized_prompt'] ?: $instruction,
            $pageContext
        );

        if (! ($interpretResult['success'] ?? false)) {
            $diagnosticLog[] = 'Interpret failed: '.($interpretResult['error'] ?? 'unknown');
            return [
                'success' => false,
                'error' => $interpretResult['error'] ?? 'Failed to interpret command.',
                'error_code' => 'interpret_failed',
                'diagnostic_log' => $diagnosticLog,
            ];
        }

        $changeSet = $interpretResult['change_set'] ?? [];
        $operations = $changeSet['operations'] ?? [];
        if ($operations === []) {
            $diagnosticLog[] = 'Interpret returned empty operations';
            return [
                'success' => false,
                'error' => 'No changes to apply. The command could not be mapped to any operation.',
                'error_code' => 'no_effect',
                'diagnostic_log' => $diagnosticLog,
            ];
        }

        $execResult = $this->executor->execute($project, $changeSet, [
            'page_id' => $input['page_id'] ?? null,
            'page_slug' => $input['page_slug'] ?? null,
            'locale' => $context->locale,
            'instruction' => $instruction,
            'publish' => (bool) ($input['publish'] ?? false),
            'actor_id' => $input['actor_id'] ?? null,
            'selected_target' => $input['selected_target'] ?? null,
        ]);

        $diagnosticLog = array_merge(
            $diagnosticLog,
            is_array($execResult['diagnostic_log'] ?? null) ? $execResult['diagnostic_log'] : []
        );

        if (! ($execResult['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $execResult['error'] ?? 'Execution failed.',
                'error_code' => $execResult['error_code'] ?? 'execution_failed',
                'diagnostic_log' => $diagnosticLog,
            ];
        }

        return [
            'success' => true,
            'change_set' => $changeSet,
            'summary' => $changeSet['summary'] ?? [],
            'page' => isset($execResult['page']) ? [
                'id' => $execResult['page']->id,
                'slug' => $execResult['page']->slug,
                'status' => $execResult['page']->status,
            ] : null,
            'revision' => isset($execResult['revision']) ? [
                'id' => $execResult['revision']->id,
                'version' => $execResult['revision']->version,
                'published_at' => $execResult['revision']->published_at?->toISOString(),
                'content_json' => $execResult['revision']->content_json,
            ] : null,
            'action_log' => $execResult['action_log'] ?? [],
            'applied_changes' => $execResult['applied_changes'] ?? [],
            'highlight_section_ids' => $execResult['highlight_section_ids'] ?? [],
            'diagnostic_log' => $diagnosticLog,
        ];
    }

    /**
     * Determine if the request should go to generation path.
     */
    public function isGenerationRequest(string $instruction): bool
    {
        return $this->georgianNormalizer->looksLikeSiteCreation($instruction);
    }
}
