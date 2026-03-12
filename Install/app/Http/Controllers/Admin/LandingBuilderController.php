<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ChecksDemoMode;
use App\Models\LandingSection;
use App\Models\Language;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Services\BroadcastService;
use App\Services\LandingPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LandingBuilderController extends Controller
{
    use ChecksDemoMode;

    public function __construct(
        protected LandingPageService $landingPageService
    ) {}

    /**
     * Display the landing page builder.
     */
    public function index(): Response
    {
        $sections = $this->landingPageService->getAllSectionsForAdmin();
        $sectionTypes = $this->landingPageService->getSectionTypes();
        $languages = Language::active()->orderBy('sort_order')->get();
        $defaultLanguage = Language::getDefault()?->code ?? config('app.locale', 'ka');

        return Inertia::render('Admin/LandingBuilder/Index', [
            'sections' => $sections,
            'sectionTypes' => $sectionTypes,
            'languages' => $languages,
            'defaultLanguage' => $defaultLanguage,
        ]);
    }

    /**
     * Display the landing page preview.
     */
    public function preview(Request $request): Response
    {
        $locale = $request->input('locale', app()->getLocale());
        $config = $this->landingPageService->getPreviewConfig($locale);

        // Get additional props needed for landing page
        return Inertia::render('Landing', array_merge($config, [
            'isPreview' => true,
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register') && SystemSetting::get('enable_registration', true),
            'plans' => Plan::active()->orderBy('sort_order')->get(Plan::landingPageSelectColumns()),
            'isPusherConfigured' => app(BroadcastService::class)->isConfigured(),
            'statistics' => [
                'users' => 0,
                'projects' => 0,
            ],
        ]));
    }

    /**
     * Reorder sections.
     */
    public function reorder(Request $request): RedirectResponse
    {
        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:landing_sections,id',
        ]);

        $this->landingPageService->reorderSections($validated['ids']);

        return back()->with('success', __('Sections reordered successfully.'));
    }

    /**
     * Update a section's settings and enabled status.
     */
    public function updateSection(Request $request, LandingSection $section): RedirectResponse
    {
        if ($redirect = $this->denyIfDemo()) {
            return $redirect;
        }

        $validated = $request->validate([
            'is_enabled' => 'boolean',
            'settings' => 'nullable|array',
        ]);

        $this->landingPageService->updateSection($section->id, $validated);

        return back()->with('success', __('Section updated successfully.'));
    }

    /**
     * Update section content for a locale.
     */
    public function updateContent(Request $request, LandingSection $section): RedirectResponse|JsonResponse
    {
        if ($redirect = $this->denyIfDemo()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => __('This action is disabled in demo mode.')], 403);
            }

            return $redirect;
        }

        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:10', Rule::exists('languages', 'code')],
            'fields' => 'required|array',
        ]);

        $this->landingPageService->updateContent($section->id, $validated['locale'], $validated['fields']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Content updated successfully.')]);
        }

        return back()->with('success', __('Content updated successfully.'));
    }

    /**
     * Update section items for a locale.
     */
    public function updateItems(Request $request, LandingSection $section): RedirectResponse|JsonResponse
    {
        if ($redirect = $this->denyIfDemo()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => __('This action is disabled in demo mode.')], 403);
            }

            return $redirect;
        }

        $request->validate([
            'locale' => ['required', 'string', 'max:10', Rule::exists('languages', 'code')],
            'items' => 'required|array',
            'items.*.key' => 'required|uuid',
            'items.*.sort_order' => 'required|integer|min:0',
            'items.*.is_enabled' => 'boolean',
            'items.*.data' => 'required|array',
            'items.*.data.rating' => 'nullable|integer|min:1|max:5',
            'items.*.data.answer' => 'nullable|string|max:10000',
            'items.*.data.image_url' => 'nullable|string|max:500',
            'items.*.data.avatar' => 'nullable|string|max:500',
            'items.*.data.company_url' => 'nullable|string|max:500',
        ]);

        // Use request directly to preserve the full data array
        $this->landingPageService->updateItems($section->id, $request->input('locale'), $request->input('items'));

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Items updated successfully.')]);
        }

        return back()->with('success', __('Items updated successfully.'));
    }

    /**
     * Upload a media file.
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        if ($this->isDemoMode()) {
            return response()->json(['error' => __('This action is disabled in demo mode.')], 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,svg,webp|max:2048',
            'type' => 'required|in:logo,avatar,image',
        ]);

        $file = $request->file('file');
        $filename = time().'_'.$file->getClientOriginalName();
        $path = $file->storeAs('landing', $filename, 'public');

        return response()->json([
            'path' => basename($path),
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Delete a media file.
     */
    public function deleteMedia(Request $request): JsonResponse
    {
        if ($this->isDemoMode()) {
            return response()->json(['error' => __('This action is disabled in demo mode.')], 403);
        }

        $validated = $request->validate([
            'path' => 'required|string',
        ]);

        $fullPath = 'landing/'.$validated['path'];

        if (Storage::disk('public')->exists($fullPath)) {
            Storage::disk('public')->delete($fullPath);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Check if demo mode is enabled.
     */
    protected function isDemoMode(): bool
    {
        return config('app.demo_mode', false);
    }
}
