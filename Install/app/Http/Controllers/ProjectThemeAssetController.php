<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectThemeAssetController extends Controller
{
    public function serve(Request $request, Project $project, string $path): BinaryFileResponse
    {
        $this->authorize('view', $project);

        $relativePath = ltrim(str_replace('\\', '/', (string) $path), '/');
        abort_if($relativePath === '' || str_contains($relativePath, '..'), 404);

        $previewAssetPath = Storage::disk('local')->path("previews/{$project->id}/assets/{$relativePath}");
        if (is_file($previewAssetPath)) {
            return response()->file($previewAssetPath, [
                'Content-Type' => $this->detectMimeType($relativePath),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        $templateSlug = trim((string) optional($project->template)->slug);
        if ($templateSlug === '') {
            $fallbackTemplateId = Template::query()
                ->orderByDesc('is_system')
                ->orderBy('id')
                ->value('id');

            if ($fallbackTemplateId) {
                $templateSlug = trim((string) Template::query()->whereKey($fallbackTemplateId)->value('slug'));
            }
        }

        if ($templateSlug !== '') {
            $candidates = [
                public_path("themes/{$templateSlug}/assets/{$relativePath}"),
                public_path("template-demos/{$templateSlug}/assets/{$relativePath}"),
                // Imported templates may live only in the internal runtime directory.
                base_path("templates/{$templateSlug}/runtime/assets/{$relativePath}"),
                // Some imported HTML/CSS references can drop the `assets/` prefix.
                base_path("templates/{$templateSlug}/runtime/{$relativePath}"),
            ];

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return response()->file($candidate, [
                        'Content-Type' => $this->detectMimeType($relativePath),
                        'Cache-Control' => 'public, max-age=86400',
                    ]);
                }
            }
        }

        abort(404);
    }

    private function detectMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => 'text/css',
            'js', 'mjs' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            default => 'application/octet-stream',
        };
    }
}
