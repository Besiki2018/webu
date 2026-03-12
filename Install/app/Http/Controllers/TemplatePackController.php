<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Template;
use App\Services\TemplatePackExportService;
use App\Services\TemplatePackImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplatePackController extends Controller
{
    public function __construct(
        protected TemplatePackExportService $exportService,
        protected TemplatePackImportService $importService
    ) {}

    /**
     * Export project as Template Pack ZIP (layout + theme + CSS scaffold).
     */
    public function exportProject(Request $request, Project $project): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $project);

        try {
            $path = $this->exportService->exportProject($project);
        } catch (\Throwable $e) {
            Log::warning('Template pack export failed', ['project_id' => $project->id, 'error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 422);
        }

        $filename = 'webu-template-pack-'.str($project->name)->slug().'-'.now()->format('Y-m-d').'.zip';

        return response()->streamDownload(
            function () use ($path) {
                echo file_get_contents($path);
                @unlink($path);
            },
            $filename,
            ['Content-Type' => 'application/zip']
        );
    }

    /**
     * Export template as Template Pack ZIP (admin).
     */
    public function exportTemplate(Request $request, Template $template): StreamedResponse|JsonResponse
    {
        $request->user()?->isAdmin() || abort(403);

        try {
            $path = $this->exportService->exportTemplate($template);
        } catch (\Throwable $e) {
            Log::warning('Template pack export failed', ['template_id' => $template->id, 'error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 422);
        }

        $filename = 'webu-template-pack-'.str($template->slug)->slug().'-'.now()->format('Y-m-d').'.zip';

        return response()->streamDownload(
            function () use ($path) {
                echo file_get_contents($path);
                @unlink($path);
            },
            $filename,
            ['Content-Type' => 'application/zip']
        );
    }

    /**
     * Preview/validate Template Pack ZIP (admin). Returns summary and validation result without importing.
     */
    public function importPreview(Request $request): JsonResponse
    {
        $request->user()?->isAdmin() || abort(403);

        $request->validate([
            'file' => 'required|file|mimes:zip|max:51200',
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        try {
            $result = app(\App\Services\TemplatePackImportService::class)->preview($file);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'valid' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'valid' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'name' => $result['name'] ?? '',
            'slug' => $result['slug'] ?? '',
            'pages_count' => $result['pages_count'] ?? 0,
            'bindings_count' => $result['bindings_count'] ?? 0,
            'warnings' => $result['warnings'] ?? [],
        ]);
    }

    /**
     * Import Template Pack ZIP (admin). Creates or updates template.
     */
    public function import(Request $request): JsonResponse
    {
        $request->user()?->isAdmin() || abort(403);

        $request->validate([
            'file' => 'required|file|mimes:zip|max:51200',
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        try {
            $result = $this->importService->import(
                $file,
                $request->input('name'),
                $request->input('slug')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::warning('Template pack import failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 422);
        }

        /** @var Template $template */
        $template = $result['template'];

        return response()->json([
            'success' => true,
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
            ],
            'warnings' => $result['warnings'],
        ]);
    }
}
