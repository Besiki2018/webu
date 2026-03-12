<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives runtime error reports from the frontend (ErrorBoundary).
 * Stores them in audit/bugfixer/events/ for the bugfixer pipeline.
 */
class BugfixerReportController extends Controller
{
    private function auditPath(string ...$parts): string
    {
        return base_path(implode(DIRECTORY_SEPARATOR, array_merge(['audit', 'bugfixer'], $parts)));
    }

    private function redact(string $s): string
    {
        $s = preg_replace('/\bsk-[a-zA-Z0-9_-]{20,}\b/', '[REDACTED]', $s);
        $s = preg_replace('/\bBearer\s+[a-zA-Z0-9_.-]+\b/i', '[REDACTED]', $s);
        return $s;
    }

    private function findExistingByDedupKey(string $dedupKey): ?array
    {
        $dir = $this->auditPath('events');
        if (! is_dir($dir)) {
            return null;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            if (! str_ends_with($f, '.json')) {
                continue;
            }
            $content = @file_get_contents($dir.DIRECTORY_SEPARATOR.$f);
            if ($content === false) {
                continue;
            }
            $data = json_decode($content, true);
            if (is_array($data) && ($data['dedupKey'] ?? '') === $dedupKey) {
                return $data;
            }
        }
        return null;
    }

    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'stack' => 'nullable|string|max:10000',
            'route' => 'nullable|string|max:500',
            'projectId' => 'nullable|string|max:100',
            'sectionId' => 'nullable|string|max:100',
            'componentStack' => 'nullable|string|max:2000',
        ]);
        $message = $this->redact($validated['message']);
        $stack = isset($validated['stack']) ? $this->redact($validated['stack']) : null;
        $route = $validated['route'] ?? '';
        $stackTop = $stack ? implode("\n", array_slice(explode("\n", $stack), 0, 5)) : '';
        $dedupKey = substr(hash('sha256', $message.$stackTop.$route), 0, 16);
        $bugId = 'bug_'.date('Ymd').'_'.$dedupKey;

        $existing = $this->findExistingByDedupKey($dedupKey);
        $frequency = 1;
        if ($existing !== null) {
            $bugId = $existing['bugId'];
            $frequency = (int) ($existing['frequency'] ?? 1) + 1;
        }

        $event = [
            'bugId' => $bugId,
            'timestamp' => now()->toIso8601String(),
            'severity' => 'high',
            'source' => 'frontend',
            'tenantId' => null,
            'websiteId' => $validated['projectId'] ?? null,
            'userId' => $request->user()?->id ? '[REDACTED]' : null,
            'route' => $route ?: null,
            'event' => $message,
            'stack' => $stack,
            'context' => [
                'sectionId' => $validated['sectionId'] ?? null,
                'componentStack' => isset($validated['componentStack']) ? $this->redact($validated['componentStack']) : null,
            ],
            'dedupKey' => $dedupKey,
            'frequency' => $frequency,
        ];

        $dir = $this->auditPath('events');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $dir.DIRECTORY_SEPARATOR.$bugId.'.json',
            json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        return response()->json(['ok' => true, 'bugId' => $bugId]);
    }
}
