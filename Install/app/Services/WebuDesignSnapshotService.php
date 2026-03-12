<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Bakes a project's design CSS snapshot so the site is no longer linked to the global webu folder.
 * When a project is created from AI, we copy the current design-system (tokens + webu components)
 * into a site-specific file. The project's preview and storefront then load this file;
 * later changes to the default webu components do not affect this project. Design stays editable
 * via theme_settings (tokens) and section content in the builder.
 *
 * Requires public storage link so the baked file is reachable: run `php artisan storage:link` if missing.
 */
class WebuDesignSnapshotService
{
    private string $designSystemPath;

    public function __construct()
    {
        $this->designSystemPath = resource_path('css');
    }

    /**
     * Bake current design-system CSS (tokens + webu components) into a file for this site.
     * Saves to storage (public) and returns the public URL. Stores nothing in DB here —
     * caller should save theme_settings['design_snapshot'] with baked_css_url and detached = true.
     *
     * @return string|null Public URL to the baked CSS file, or null on failure
     */
    public function bakeSiteDesignCss(Site $site): ?string
    {
        $css = $this->buildDesignSystemCss();
        if ($css === '' || $css === null) {
            return null;
        }

        $disk = Storage::disk('public');
        $dir = 'site-styles';
        $filename = (string) $site->id . '.css';

        if (! $disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }

        $path = $dir . '/' . $filename;
        if (! $disk->put($path, $css)) {
            return null;
        }

        return Storage::url($path);
    }

    /**
     * Build a single CSS string: design-system tokens + webu global (with @import resolved).
     */
    private function buildDesignSystemCss(): string
    {
        $tokensPath = $this->designSystemPath . '/design-system/tokens.css';
        $webuGlobalPath = $this->designSystemPath . '/webu/global.css';

        $out = [];
        if (File::isFile($tokensPath)) {
            $out[] = '/* Design system tokens (snapshot) */';
            $out[] = File::get($tokensPath);
        }
        if (File::isFile($webuGlobalPath)) {
            $out[] = '/* Webu components (snapshot) */';
            $out[] = $this->resolveImports($webuGlobalPath, dirname($webuGlobalPath));
        }

        return implode("\n", $out);
    }

    /**
     * Read a CSS file and inline any @import "./path" with the content of that file (relative to baseDir).
     */
    private function resolveImports(string $filePath, string $baseDir): string
    {
        if (! File::isFile($filePath)) {
            return '';
        }
        $content = File::get($filePath);
        $lines = explode("\n", $content);
        $out = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*@import\s+["\']\.\/([^"\']+)["\']\s*;?\s*$/', $line, $m)) {
                $relative = str_replace(['../', './'], '', $m[1]);
                $resolved = $baseDir . '/' . $relative;
                $resolved = realpath($resolved) ?: $resolved;
                if (File::isFile($resolved)) {
                    $out[] = '/* from ' . $relative . ' */';
                    $out[] = File::get($resolved);
                }
            } else {
                $out[] = $line;
            }
        }

        return implode("\n", $out);
    }

    /**
     * Whether this site has a baked design (detached from default webu).
     */
    public function hasBakedDesign(Site $site): bool
    {
        $snapshot = is_array($site->theme_settings) ? ($site->theme_settings['design_snapshot'] ?? null) : null;

        return is_array($snapshot) && ! empty($snapshot['baked_css_url']);
    }

    /**
     * Get the baked CSS URL for the site, or null if not detached.
     *
     * @return string|null
     */
    public function getBakedCssUrl(Site $site): ?string
    {
        $snapshot = is_array($site->theme_settings) ? ($site->theme_settings['design_snapshot'] ?? null) : null;
        if (! is_array($snapshot)) {
            return null;
        }
        $url = $snapshot['baked_css_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }
}
