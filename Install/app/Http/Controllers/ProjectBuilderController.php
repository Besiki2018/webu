<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\BuilderV2\BuilderDocumentService;
use App\Services\InternalAiService;
use App\Services\SiteProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectBuilderController extends Controller
{
    private const AI_ALLOWED_MUTATION_TYPES = [
        'PATCH_NODE_PROPS',
        'INSERT_NODE',
        'DELETE_NODE',
        'MOVE_NODE',
    ];

    public function __construct(
        protected BuilderDocumentService $builderDocumentService,
        protected SiteProvisioningService $siteProvisioningService,
        protected InternalAiService $internalAiService
    ) {}

    public function show(Project $project): Response
    {
        $this->authorize('update', $project);

        $project->update(['last_viewed_at' => now()]);
        $site = $this->siteProvisioningService->provisionForProject($project);
        $draft = $this->builderDocumentService->loadDraft($project);
        $published = $this->builderDocumentService->loadPublished($project);

        return Inertia::render('Builder', [
            'project' => [
                'id' => (string) $project->id,
                'name' => (string) $project->name,
                'subdomain' => $project->subdomain,
                'published_at' => $project->published_at?->toIso8601String(),
            ],
            'site' => [
                'id' => (string) $site->id,
                'name' => (string) $site->name,
                'locale' => (string) $site->locale,
                'status' => (string) $site->status,
            ],
            'builderDocument' => $draft,
            'publishedBuilderDocument' => $published,
            'builderApi' => [
                'document' => sprintf('/api/projects/%s/builder-document', $project->id),
                'mutations' => sprintf('/api/projects/%s/builder-mutations', $project->id),
                'publish' => sprintf('/api/projects/%s/publish', $project->id),
                'aiSuggestions' => sprintf('/api/projects/%s/builder-ai/suggest', $project->id),
                'assets' => sprintf('/project/%s/files', $project->id),
                'assetsUpload' => sprintf('/project/%s/files', $project->id),
            ],
        ]);
    }

    public function document(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        return response()->json([
            'document' => $this->builderDocumentService->loadDraft($project),
            'published_document' => $this->builderDocumentService->loadPublished($project),
        ]);
    }

    public function updateDocument(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'document' => ['required', 'array'],
        ]);

        return response()->json([
            'document' => $this->builderDocumentService->saveDraft($project, $validated['document']),
        ]);
    }

    public function applyMutations(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'mutations' => ['required', 'array'],
            'mutations.*.type' => ['required', 'string'],
            'mutations.*.payload' => ['nullable', 'array'],
        ]);

        return response()->json([
            'document' => $this->builderDocumentService->applyMutations($project, $validated['mutations']),
        ]);
    }

    public function publish(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        return response()->json([
            'document' => $this->builderDocumentService->publish($project),
        ]);
    }

    public function suggestMutations(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:5000'],
            'document' => ['required', 'array'],
            'selected_node_id' => ['nullable', 'string'],
        ]);

        $document = $validated['document'];
        $selectedNodeId = is_string($validated['selected_node_id'] ?? null) ? $validated['selected_node_id'] : null;
        $prompt = trim((string) $validated['prompt']);
        $response = $this->internalAiService->complete($this->buildAiPrompt($document, $prompt, $selectedNodeId), 1800);
        $suggestions = $this->parseAiSuggestions($response);

        if ($suggestions === []) {
            $suggestions = $this->buildFallbackSuggestions($document, $prompt, $selectedNodeId);
        }

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function buildAiPrompt(array $document, string $prompt, ?string $selectedNodeId): string
    {
        $node = $selectedNodeId && isset($document['nodes'][$selectedNodeId]) && is_array($document['nodes'][$selectedNodeId])
            ? $document['nodes'][$selectedNodeId]
            : null;

        $context = [
            'rootPageId' => $document['rootPageId'] ?? null,
            'pages' => $document['pages'] ?? [],
            'selectedNodeId' => $selectedNodeId,
            'selectedNode' => $node,
            'nodes' => $document['nodes'] ?? [],
        ];

        return <<<PROMPT
You are generating safe, structured mutation suggestions for a visual website builder.

Rules:
- Return ONLY JSON.
- Return an array of suggestion objects.
- Each suggestion must have: id, title, summary, mutations.
- Each mutation must have: type and payload.
- Allowed mutation types: PATCH_NODE_PROPS, INSERT_NODE, DELETE_NODE, MOVE_NODE.
- Never output code, prose, markdown, or explanations outside JSON.
- Use existing node ids when patching, deleting, or moving.
- For inserted nodes, generate stable string ids.

Current builder context:
{$this->toJson($context)}

User request:
{$prompt}

Return JSON in this shape:
[
  {
    "id": "suggestion-1",
    "title": "Short title",
    "summary": "One sentence summary",
    "mutations": [
      { "type": "PATCH_NODE_PROPS", "payload": { "nodeId": "node-1", "patch": { "title": "Updated" } } }
    ]
  }
]
PROMPT;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseAiSuggestions(?string $response): array
    {
        if (! is_string($response) || trim($response) === '') {
            return [];
        }

        $content = trim($response);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/u', $content, $matches)) {
            $content = trim((string) ($matches[1] ?? ''));
        }

        $decoded = json_decode($content, true);

        return is_array($decoded)
            ? $this->filterAiSuggestions(array_values(array_filter($decoded, static fn ($item): bool => is_array($item))))
            : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $suggestions
     * @return array<int, array<string, mixed>>
     */
    private function filterAiSuggestions(array $suggestions): array
    {
        return array_values(array_filter(array_map(function (array $suggestion, int $index): array {
            $mutations = array_values(array_filter(
                is_array($suggestion['mutations'] ?? null) ? $suggestion['mutations'] : [],
                function ($mutation): bool {
                    if (! is_array($mutation)) {
                        return false;
                    }

                    $type = trim((string) ($mutation['type'] ?? ''));
                    $payload = $mutation['payload'] ?? null;

                    return in_array($type, self::AI_ALLOWED_MUTATION_TYPES, true) && is_array($payload);
                }
            ));

            return [
                'id' => is_string($suggestion['id'] ?? null) ? $suggestion['id'] : 'suggestion-'.($index + 1),
                'title' => is_string($suggestion['title'] ?? null) ? $suggestion['title'] : 'Structured suggestion',
                'summary' => is_string($suggestion['summary'] ?? null) ? $suggestion['summary'] : 'Structured mutation suggestion',
                'mutations' => $mutations,
            ];
        }, $suggestions, array_keys($suggestions))), static fn (array $suggestion): bool => $suggestion['mutations'] !== []);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<int, array<string, mixed>>
     */
    private function buildFallbackSuggestions(array $document, string $prompt, ?string $selectedNodeId): array
    {
        $normalizedPrompt = Str::lower($prompt);
        $selectedNode = $selectedNodeId && isset($document['nodes'][$selectedNodeId]) && is_array($document['nodes'][$selectedNodeId])
            ? $document['nodes'][$selectedNodeId]
            : null;

        if ($selectedNodeId && (str_contains($normalizedPrompt, 'delete') || str_contains($normalizedPrompt, 'remove'))) {
            return [[
                'id' => 'fallback-delete-node',
                'title' => 'Remove selected block',
                'summary' => 'Deletes the currently selected node from the draft tree.',
                'mutations' => [[
                    'type' => 'DELETE_NODE',
                    'payload' => ['nodeId' => $selectedNodeId],
                ]],
            ]];
        }

        if (str_contains($normalizedPrompt, 'hero')) {
            $rootPageId = (string) ($document['rootPageId'] ?? '');
            $rootNodeId = is_string(data_get($document, "pages.{$rootPageId}.rootNodeId")) ? data_get($document, "pages.{$rootPageId}.rootNodeId") : null;
            if ($rootNodeId) {
                $nodeId = (string) Str::uuid();

                return [[
                    'id' => 'fallback-insert-hero',
                    'title' => 'Add hero block',
                    'summary' => 'Inserts a new hero section at the end of the active page.',
                    'mutations' => [[
                        'type' => 'INSERT_NODE',
                        'payload' => [
                            'parentId' => $rootNodeId,
                            'node' => [
                                'id' => $nodeId,
                                'type' => 'component',
                                'componentKey' => 'hero',
                                'parentId' => $rootNodeId,
                                'children' => [],
                                'props' => [
                                    'title' => 'New hero section',
                                    'subtitle' => 'Add your main message here',
                                    'description' => 'Generated by the V2 builder assistant fallback.',
                                    'buttonText' => 'Learn more',
                                    'buttonLink' => '#',
                                ],
                                'styles' => [],
                                'bindings' => [],
                                'meta' => ['label' => 'Hero'],
                            ],
                        ],
                    ]],
                ]];
            }
        }

        if ($selectedNodeId && is_array($selectedNode)) {
            $title = $this->extractFirstQuotedOrHeadline($prompt);
            $patch = $title !== null
                ? ['title' => $title]
                : ['description' => $prompt];

            return [[
                'id' => 'fallback-patch-selected',
                'title' => 'Update selected block copy',
                'summary' => 'Applies a safe content-only patch to the selected node.',
                'mutations' => [[
                    'type' => 'PATCH_NODE_PROPS',
                    'payload' => [
                        'nodeId' => $selectedNodeId,
                        'patch' => $patch,
                    ],
                ]],
            ]];
        }

        return [];
    }

    private function extractFirstQuotedOrHeadline(string $prompt): ?string
    {
        if (preg_match('/"([^"]+)"/u', $prompt, $matches)) {
            return trim((string) ($matches[1] ?? '')) ?: null;
        }

        $clean = trim(Arr::first(preg_split('/[.!?]/u', $prompt)) ?? '');

        return $clean !== '' ? Str::headline($clean) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function toJson(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
