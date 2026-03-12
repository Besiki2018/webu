<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use Illuminate\Support\Facades\Log;

class AiContentPatchProposalService
{
    public function __construct(
        protected InternalAiService $internalAi
    ) {}

    /**
     * Propose a JSON patch from a natural-language instruction. Does not apply the patch.
     *
     * @return array{success: bool, proposed_patch?: array<int, array{op: string, path: string, value?: mixed}>, patch_format: string, error?: string}
     */
    public function propose(Project $project, ?int $pageId, string $instruction): array
    {
        $instruction = trim($instruction);
        if ($instruction === '') {
            return [
                'success' => false,
                'patch_format' => 'rfc6902',
                'error' => 'Instruction is required.',
            ];
        }

        if (! $this->internalAi->isConfigured()) {
            return [
                'success' => false,
                'patch_format' => 'rfc6902',
                'error' => 'AI provider is not configured. Configure in Admin Settings → Integrations.',
            ];
        }

        $site = $project->site()->first();
        if (! $site) {
            return [
                'success' => false,
                'patch_format' => 'rfc6902',
                'error' => 'Site is not provisioned for this project.',
            ];
        }

        $page = $this->resolvePage((string) $site->id, $pageId);
        $revision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        $contentJson = is_array($revision?->content_json) ? $revision->content_json : ['sections' => []];
        $contentJson = $this->sanitizeForPrompt($contentJson);

        $prompt = $this->buildPrompt($instruction, $contentJson);
        $response = $this->internalAi->complete($prompt, 4000);

        if ($response === null || trim($response) === '') {
            return [
                'success' => false,
                'patch_format' => 'rfc6902',
                'error' => 'AI did not return a response. Try again or check AI provider configuration.',
            ];
        }

        $patch = $this->parsePatchFromResponse($response);
        if ($patch === null) {
            Log::warning('AI content patch proposal: failed to parse patch from response', [
                'project_id' => $project->id,
                'response_length' => strlen($response),
            ]);

            return [
                'success' => false,
                'patch_format' => 'rfc6902',
                'error' => 'AI response could not be parsed as a valid JSON Patch array. You can paste a patch manually in Apply patch.',
            ];
        }

        return [
            'success' => true,
            'proposed_patch' => $patch,
            'patch_format' => 'rfc6902',
        ];
    }

    private function resolvePage(string $siteId, ?int $pageId): Page
    {
        if ($pageId !== null && $pageId > 0) {
            $page = Page::query()
                ->where('site_id', $siteId)
                ->where('id', $pageId)
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

        if (! $first) {
            throw new \RuntimeException('No page found in site.');
        }

        return $first;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function sanitizeForPrompt(array $content): array
    {
        $encoded = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > 12000) {
            return ['sections' => [], '_truncated' => true];
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $contentJson
     */
    private function buildPrompt(string $instruction, array $contentJson): string
    {
        $json = json_encode($contentJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 10000) {
            $json = json_encode(['sections' => array_slice($contentJson['sections'] ?? [], 0, 5), '_note' => 'Truncated for prompt']);
        }

        return <<<PROMPT
You are a CMS content patch generator. The user edits a page that has a JSON structure with a "sections" array. Each section has "type" and "props".

User instruction: {$instruction}

Current page content_json:
{$json}

Respond with ONLY a valid RFC 6902 JSON Patch array. Each operation must have "op" (add, replace, remove), "path" (JSON Pointer, e.g. /sections/0/props/headline), and "value" for add/replace. No markdown, no code fence, no explanation. Example:
[{"op":"replace","path":"/sections/0/props/headline","value":"New headline"}]
PROMPT;
    }

    /**
     * @return array<int, array{op: string, path: string, value?: mixed}>|null
     */
    private function parsePatchFromResponse(string $response): ?array
    {
        $text = trim($response);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            $text = trim($m[1]);
        }
        if (preg_match('/\[[\s\S]*\]/', $text, $m)) {
            $text = $m[0];
        }
        $decoded = json_decode($text, true);
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return null;
        }
        foreach ($decoded as $op) {
            if (! is_array($op) || empty($op['op']) || empty($op['path'])) {
                return null;
            }
            if (! in_array($op['op'], ['add', 'replace', 'remove'], true)) {
                return null;
            }
            if ($op['op'] !== 'remove' && ! array_key_exists('value', $op)) {
                return null;
            }
        }

        return $decoded;
    }
}
