<?php

namespace App\Services\WebuCodex;

use App\Models\Project;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use Illuminate\Support\Facades\File;

/**
 * Logs every AI change for audit: user request, files modified, old/new snapshot, timestamp.
 */
class ExecutionLogger
{
    private const LOG_FILE = 'cms/ai_project_edit_log.jsonl';

    public function __construct(
        protected ProjectWorkspaceService $workspace
    ) {}

    /**
     * Append one execution log entry.
     *
     * @param  array<int, array{path: string, op: string, old_content?: string|null, new_content?: string|null}>  $changes
     */
    public function log(Project $project, string $userRequest, array $changes, string $summary): void
    {
        $root = $this->workspace->ensureWorkspaceRoot($project);
        $logPath = $root.'/'.self::LOG_FILE;
        $dir = dirname($logPath);
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0775, true);
        }

        $truncate = static fn (?string $s, int $max = 5000): ?string => $s === null ? null : (strlen($s) > $max ? substr($s, 0, $max)."\n...(truncated)" : $s);

        $entry = [
            'timestamp' => now()->toIso8601String(),
            'user_request' => $userRequest,
            'summary' => $summary,
            'changes' => array_map(static function ($c) use ($truncate) {
                $out = ['path' => $c['path'], 'op' => $c['op']];
                if (array_key_exists('old_content', $c)) {
                    $out['old_content'] = $truncate($c['old_content'] ?? null);
                }
                if (array_key_exists('new_content', $c)) {
                    $out['new_content'] = $truncate($c['new_content'] ?? null);
                }
                return $out;
            }, $changes),
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE)."\n";
        File::append($logPath, $line);
    }
}
