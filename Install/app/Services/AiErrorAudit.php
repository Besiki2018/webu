<?php

namespace App\Services;

use Throwable;

/**
 * Tab 9 A3: Store AI errors to audit/ai-errors/{timestamp}.json for diagnostics
 * and self-healing pipeline. Never log secrets (API keys redacted).
 */
class AiErrorAudit
{
    protected static function auditDir(): string
    {
        $dir = base_path(implode(DIRECTORY_SEPARATOR, ['audit', 'ai-errors']));
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    protected static function redact(string $s): string
    {
        $s = preg_replace('/\bsk-[a-zA-Z0-9_-]{20,}\b/', '[REDACTED]', $s);
        $s = preg_replace('/\bBearer\s+[a-zA-Z0-9_.-]+\b/i', '[REDACTED]', $s);
        return $s;
    }

    /**
     * Report an AI error to audit/ai-errors/{timestamp}_{id}.json.
     *
     * @param  string  $message  Short description (e.g. "OpenAI call failed")
     * @param  array  $context  Optional: prompt_hash, provider, status, validation_errors, response_truncated
     * @param  Throwable|null  $exception  Optional exception to include (message + class, no stack in file if sensitive)
     */
    public static function report(string $message, array $context = [], ?Throwable $exception = null): void
    {
        $payload = [
            'timestamp' => now()->toIso8601String(),
            'message' => self::redact($message),
            'context' => self::redactArray($context),
        ];
        if ($exception !== null) {
            $payload['exception'] = [
                'class' => get_class($exception),
                'message' => self::redact($exception->getMessage()),
            ];
        }
        $id = substr(uniqid('', true), -8);
        $filename = date('Y-m-d_His').'_'.$id.'.json';
        $path = self::auditDir().DIRECTORY_SEPARATOR.$filename;
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    protected static function redactArray(array $arr): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $keyLower = is_string($k) ? strtolower($k) : '';
            if (is_string($v)) {
                $out[$k] = str_contains($keyLower, 'key') || str_contains($keyLower, 'secret') || str_contains($keyLower, 'token')
                    ? '[REDACTED]'
                    : self::redact($v);
            } elseif (is_array($v)) {
                $out[$k] = self::redactArray($v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
