<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Contracts\CmsPanelSiteServiceContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteCustomFont;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PanelSiteController extends Controller
{
    public function __construct(
        protected CmsPanelSiteServiceContract $settings
    ) {}

    /**
     * Get site-level global settings.
     */
    public function settings(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        return response()->json(
            $this->settings->settings($site, $validated['locale'] ?? null)
        );
    }

    /**
     * Update site/global settings.
     */
    public function updateSettings(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'available_locales' => ['sometimes', 'array'],
            'available_locales.*' => ['string', 'max:10'],
            'translation_locale' => ['sometimes', 'string', 'max:10'],
            'ui_translations' => ['sometimes', 'array'],
            'theme_settings' => ['sometimes', 'array'],
            'contact_json' => ['sometimes', 'array'],
            'social_links_json' => ['sometimes', 'array'],
            'analytics_ids_json' => ['sometimes', 'array'],
            'logo_media_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->settings->updateSettings($site, $validated);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Site settings updated successfully.',
        ]);
    }

    /**
     * Get normalized typography contract and available font options for panel editor.
     */
    public function typography(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->settings->typography($site));
    }

    /**
     * Update site typography contract (allowlist-enforced font keys).
     */
    public function updateTypography(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $allowedFontKeys = $this->settings->allowedFontKeys($site);
        $validated = $request->validate([
            'font_key' => ['required', 'string', Rule::in($allowedFontKeys)],
            'heading_font_key' => ['nullable', 'string', Rule::in($allowedFontKeys)],
            'body_font_key' => ['nullable', 'string', Rule::in($allowedFontKeys)],
            'button_font_key' => ['nullable', 'string', Rule::in($allowedFontKeys)],
        ]);

        try {
            $payload = $this->settings->updateTypography($site, $validated);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload);
    }

    /**
     * Upload a tenant-scoped custom font and append it to typography options.
     */
    public function uploadCustomFont(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => 'Unauthorized.',
            ], 401);
        }

        $validated = $request->validate([
            'file' => ['required', 'file'],
            'label' => ['nullable', 'string', 'max:120'],
            'font_family' => ['nullable', 'string', 'max:120'],
            'font_key' => ['nullable', 'string', 'max:64'],
            'font_weight' => ['nullable', 'integer', 'between:100,900'],
            'font_style' => ['nullable', Rule::in(['normal', 'italic', 'oblique'])],
            'font_display' => ['nullable', Rule::in(['auto', 'block', 'swap', 'fallback', 'optional'])],
        ]);

        try {
            $payload = $this->settings->uploadCustomFont($site, $request->file('file'), $user, $validated);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload, 201);
    }

    /**
     * Delete a tenant-scoped custom font by id.
     */
    public function deleteCustomFont(Site $site, SiteCustomFont $font): JsonResponse
    {
        Gate::authorize('update', $site->project);

        try {
            $payload = $this->settings->deleteCustomFont($site, $font);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload);
    }
}
