<?php

namespace App\Services\WebuCodex;

/**
 * Allowed and forbidden paths for Webu AI project editing.
 * AI may only read/write inside allowed project workspace directories.
 */
class PathRules
{
    /** Allowed path prefixes (relative to workspace root). Only these dirs may be read/written by AI. */
    public const ALLOWED_PREFIXES = [
        'src',
        'public',
    ];

    /** Forbidden path segments (path must not contain these). */
    public const FORBIDDEN_SEGMENTS = [
        '__generated_pages__',
        'builder-core',
        'derived-preview',
        'system',
        'node_modules',
        'server',
        '.git',
    ];

    /**
     * Normalize relative path (no leading slash, no "..").
     */
    public static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = trim($path, '/');
        $path = preg_replace('#(^|/)\.\.(/|$)#', '', $path);

        return $path;
    }

    /**
     * Check if path is allowed for read/write. Returns true only if inside allowed dirs and not in forbidden.
     */
    public static function isAllowed(string $relativePath): bool
    {
        $path = self::normalizePath($relativePath);
        if ($path === '') {
            return false;
        }

        foreach (self::FORBIDDEN_SEGMENTS as $segment) {
            if (str_contains($path, $segment)) {
                return false;
            }
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
