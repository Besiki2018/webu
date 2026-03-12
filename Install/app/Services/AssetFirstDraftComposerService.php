<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\Site;
use App\Models\Template;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssetFirstDraftComposerService
{
    public function __construct(
        protected InternalAssetRetrievalService $retrieval,
        protected SiteProvisioningService $siteProvisioning
    ) {}

    /**
     * Compose site draft from existing template/section assets only.
     *
     * @return array<string, mixed>
     */
    public function composeForProject(
        Project $project,
        string $prompt,
        ?string $templateId = null,
        bool $resetExistingContent = true,
        ?int $actorId = null
    ): array {
        $project->loadMissing(['user', 'template', 'site']);
        $context = $this->retrieval->buildContext($project, $prompt, $templateId);

        $selectedTemplate = $this->resolveTemplate($project, $context, $templateId);
        if ($selectedTemplate) {
            $project->forceFill([
                'template_id' => $selectedTemplate->id,
            ])->save();
            $project->setRelation('template', $selectedTemplate);
        }

        DB::transaction(function () use ($project, $resetExistingContent): void {
            if ($resetExistingContent && $project->site) {
                $siteId = $project->site->id;
                PageRevision::query()->where('site_id', $siteId)->delete();
                Page::query()->where('site_id', $siteId)->delete();
                $project->site->menus()->delete();
            }

            $this->siteProvisioning->provisionForProject($project->fresh(['template', 'user', 'site']));
        });

        $site = $project->fresh(['site', 'template'])->site;
        if ($site) {
            $this->syncModuleSignals($site, $context, $selectedTemplate);
        }

        return [
            'project_id' => $project->id,
            'site_id' => $site?->id,
            'template' => $selectedTemplate ? [
                'id' => $selectedTemplate->id,
                'slug' => $selectedTemplate->slug,
                'name' => $selectedTemplate->name,
                'category' => $selectedTemplate->category,
            ] : null,
            'classification' => Arr::get($context, 'classification', []),
            'retrieval' => [
                'source' => Arr::get($context, 'source'),
                'fallback_to_generic' => (bool) Arr::get($context, 'fallback_to_generic', false),
                'template_candidates' => count(Arr::get($context, 'catalog.templates', [])),
                'section_candidates' => count(Arr::get($context, 'catalog.sections', [])),
            ],
            'reset_existing_content' => $resetExistingContent,
            'composed_at' => now()->toIso8601String(),
            'actor_id' => $actorId,
        ];
    }

    private function resolveTemplate(Project $project, array $context, ?string $templateId): ?Template
    {
        if (is_string($templateId) && trim($templateId) !== '') {
            $explicit = Template::query()->find($templateId);
            if ($explicit) {
                return $explicit;
            }
        }

        $candidateId = Arr::get($context, 'catalog.templates.0.id');
        if (is_string($candidateId) || is_int($candidateId)) {
            $candidate = Template::query()->find((string) $candidateId);
            if ($candidate) {
                return $candidate;
            }
        }

        if ($project->template) {
            return $project->template;
        }

        if ($project->template_id) {
            return Template::query()->find($project->template_id);
        }

        return null;
    }

    private function syncModuleSignals(Site $site, array $context, ?Template $template): void
    {
        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $existingModules = is_array(Arr::get($themeSettings, 'modules', [])) ? Arr::get($themeSettings, 'modules', []) : [];

        $templateFlags = is_array($template?->metadata)
            ? (is_array(Arr::get($template->metadata, 'module_flags', [])) ? Arr::get($template->metadata, 'module_flags', []) : [])
            : [];

        $inferred = $this->inferModuleSignalsFromContext($context);

        $normalized = [];
        foreach (array_merge($templateFlags, $inferred) as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalized[$key] = (bool) $value;
        }

        $themeSettings['modules'] = array_merge($existingModules, $normalized);
        $themeSettings['composer'] = array_merge(
            is_array(Arr::get($themeSettings, 'composer', [])) ? Arr::get($themeSettings, 'composer', []) : [],
            [
                'mode' => 'asset_first',
                'updated_at' => now()->toIso8601String(),
                'classification' => Arr::get($context, 'classification', []),
                'template_slug' => $template?->slug,
                'template_category' => $template?->category,
            ]
        );

        $site->update([
            'theme_settings' => $themeSettings,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function inferModuleSignalsFromContext(array $context): array
    {
        $signals = [
            'ecommerce' => false,
            'payments' => false,
            'shipping' => false,
            'booking' => false,
        ];

        $classificationCategory = Str::lower((string) Arr::get($context, 'classification.category', ''));
        if ($classificationCategory === 'ecommerce') {
            $signals['ecommerce'] = true;
            $signals['payments'] = true;
            $signals['shipping'] = true;
        }

        foreach (Arr::get($context, 'catalog.sections', []) as $section) {
            $key = Str::lower((string) Arr::get($section, 'key', ''));
            if ($key === '') {
                continue;
            }

            if (
                str_contains($key, 'product')
                || str_contains($key, 'shop')
                || str_contains($key, 'checkout')
                || str_contains($key, 'cart')
            ) {
                $signals['ecommerce'] = true;
                $signals['payments'] = true;
                $signals['shipping'] = true;
            }

            if (
                str_contains($key, 'booking')
                || str_contains($key, 'slot')
                || str_contains($key, 'appointment')
            ) {
                $signals['booking'] = true;
            }
        }

        return $signals;
    }
}
