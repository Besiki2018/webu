<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DesignDecisionService;
use App\Services\EcommerceQuestionnaireService;
use App\Services\RequirementCollectionService;
use App\Services\SiteProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequirementCollectionController extends Controller
{
    public function __construct(
        protected RequirementCollectionService $requirementCollection,
        protected DesignDecisionService $designDecision,
        protected SiteProvisioningService $provisioning,
        protected EcommerceQuestionnaireService $questionnaire
    ) {}

    /**
     * Process one step of requirement collection: user message → next question or completed config.
     */
    public function step(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $history = is_array($project->conversation_history) ? $project->conversation_history : [];
        $result = $this->requirementCollection->processMessage($project, $history, $validated['message']);

        $newHistory = $history;
        $newHistory[] = ['role' => 'user', 'content' => $validated['message']];

        if ($result['type'] === 'config') {
            $newHistory[] = ['role' => 'assistant', 'content' => 'I have everything I need. Click "Generate my site" to create your store.'];
            $project->update([
                'conversation_history' => $newHistory,
                'requirement_config' => $result['config'],
                'requirement_collection_state' => RequirementCollectionService::STATE_COMPLETE,
            ]);

            return response()->json([
                'type' => 'config',
                'config' => $result['config'],
            ]);
        }

        $newHistory[] = ['role' => 'assistant', 'content' => $result['text']];
        $project->update([
            'conversation_history' => $newHistory,
            'requirement_collection_state' => RequirementCollectionService::STATE_COLLECTING,
        ]);

        return response()->json([
            'type' => 'question',
            'text' => $result['text'],
        ]);
    }

    /**
     * GET questionnaire state: next question or completed + design_brief.
     */
    public function questionnaireState(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $config = is_array($project->requirement_config) ? $project->requirement_config : [];
        $answers = is_array($config['questionnaire_answers'] ?? null) ? $config['questionnaire_answers'] : [];

        $state = $this->questionnaire->getState($answers);

        return response()->json([
            'completed' => $state['completed'],
            'next_question' => $state['next_question'],
            'answers' => $state['answers'],
            'design_brief' => $state['design_brief'],
        ]);
    }

    /**
     * POST questionnaire answer: store answer and return next question or completed state.
     */
    public function questionnaireAnswer(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'question_key' => 'required|string|max:64',
            'value' => 'nullable',
        ]);

        $config = is_array($project->requirement_config) ? $project->requirement_config : [];
        $answers = is_array($config['questionnaire_answers'] ?? null) ? $config['questionnaire_answers'] : [];
        $key = $validated['question_key'];
        $value = $validated['value'] ?? null;

        $question = $this->questionnaire->getQuestion($key);
        if (! $question) {
            return response()->json(['error' => 'Unknown question key.'], 422);
        }

        $answers[$key] = $value;
        $config['questionnaire_answers'] = $answers;

        $nextKey = $this->questionnaire->getNextQuestionKey($answers);
        $completed = $nextKey === null;

        if ($completed) {
            $config = $this->questionnaire->buildRequirementConfigFromAnswers($answers);
            $project->update([
                'requirement_config' => $config,
                'requirement_collection_state' => RequirementCollectionService::STATE_COMPLETE,
            ]);
            $designBrief = $this->questionnaire->buildDesignBriefFromAnswers($answers);
            return response()->json([
                'completed' => true,
                'next_question' => null,
                'answers' => $answers,
                'design_brief' => $designBrief,
                'message' => 'All set. Click "Generate my site" to create your store.',
            ]);
        }

        $project->update(['requirement_config' => $config]);
        $nextQuestion = $this->questionnaire->getQuestion($nextKey);

        return response()->json([
            'completed' => false,
            'next_question' => $nextQuestion,
            'answers' => $answers,
        ]);
    }

    /**
     * Generate site from requirement config: blueprint → provision project.
     * Uses project.requirement_config if body config not provided.
     * If requirement_config contains questionnaire_answers, builds config from questionnaire first.
     */
    public function generateFromConfig(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $config = $request->input('config');
        if (! is_array($config)) {
            $config = is_array($project->requirement_config) ? $project->requirement_config : null;
        }

        if (! is_array($config) || empty($config)) {
            return response()->json([
                'error' => 'No requirement config. Complete the requirement collection first.',
            ], 422);
        }

        if (isset($config['questionnaire_answers']) && is_array($config['questionnaire_answers'])) {
            $config = $this->questionnaire->buildRequirementConfigFromAnswers($config['questionnaire_answers']);
        }

        $blueprint = $this->designDecision->configToBlueprint($config);

        $templateData = [
            'name' => $blueprint['name'],
            'theme_preset' => $blueprint['theme_preset'],
            'default_pages' => $blueprint['default_pages'],
        ];

        $site = $this->provisioning->provisionFromReadyTemplate(
            $project,
            $templateData,
            ['provision_demo_store' => ($config['siteType'] ?? '') === 'ecommerce']
        );

        $project->update([
            'theme_preset' => $blueprint['theme_preset'],
        ]);

        $previewUrl = $project->cms_preview_url ?? $project->preview_url ?? null;

        return response()->json([
            'success' => true,
            'site_id' => $site->id,
            'preview_url' => $previewUrl,
        ]);
    }

    /**
     * Show the requirement collection page (Q&A then Generate site).
     */
    public function show(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Project/Requirements', [
            'project' => $project->only('id', 'name', 'requirement_config', 'requirement_collection_state', 'conversation_history'),
        ]);
    }
}
