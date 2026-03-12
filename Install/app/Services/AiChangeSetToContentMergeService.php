<?php

namespace App\Services;

use App\Cms\Support\LocalizedCmsPayload;
use App\Support\CmsSectionLocalId;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Converts AI interpreter ChangeSet (operations) into content_merge patch
 * for AiContentPatchService (content_json shape: sections with type, props, localId).
 */
class AiChangeSetToContentMergeService
{
    public function __construct(
        protected CmsSectionBindingService $sectionBindings,
        protected LocalizedCmsPayload $localizedPayload,
        protected AiButtonOperationPatchResolver $buttonPatchResolver
    ) {}

    /**
     * Build full content_json from change_set and current content (for replace mode).
     *
     * @param  array{operations: array<int, array<string, mixed>>, summary?: array}  $changeSet
     * @param  array{sections?: array<int, array<string, mixed>>}  $currentContent
     * @return array{sections: array<int, array<string, mixed>>, ...}
     */
    public function toPatch(array $changeSet, array $currentContent, ?string $requestedLocale = null, ?string $siteLocale = null): array
    {
        $resolvedPayload = $this->localizedPayload->resolve($currentContent, $requestedLocale, $siteLocale);
        $workingContent = is_array($resolvedPayload['content'] ?? null)
            ? $resolvedPayload['content']
            : ['sections' => []];

        $sections = is_array($workingContent['sections'] ?? null)
            ? CmsSectionLocalId::materialize($workingContent['sections'])
            : [];

        foreach ($changeSet['operations'] ?? [] as $op) {
            if (! is_array($op) || empty($op['op'])) {
                continue;
            }
            switch ($op['op']) {
                case 'updateSection':
                    $sections = $this->applyUpdateSection($sections, $op);
                    break;
                case 'replaceImage':
                    $sections = $this->applyReplaceImage($sections, $op);
                    break;
                case 'updateButton':
                    $sections = $this->applyUpdateButton($sections, $op);
                    break;
                case 'insertSection':
                    $sections = $this->applyInsertSection($sections, $op);
                    break;
                case 'deleteSection':
                    $sections = $this->applyDeleteSection($sections, $op);
                    break;
                case 'reorderSection':
                    $sections = $this->applyReorderSection($sections, $op);
                    break;
                case 'updateText':
                    $sections = $this->applyUpdateText($sections, $op);
                    break;
                default:
                    break;
            }
        }

        $workingContent['sections'] = array_values($sections);

        if (($resolvedPayload['localized'] ?? false) || $this->localizedPayload->extractLocaleMap($currentContent) !== null) {
            return $this->localizedPayload->mergeForLocale(
                $currentContent,
                (string) ($resolvedPayload['resolved_locale'] ?? $this->localizedPayload->normalizeLocale($requestedLocale, $siteLocale)),
                $workingContent,
                $siteLocale,
                is_array($resolvedPayload['available_locales'] ?? null) ? $resolvedPayload['available_locales'] : []
            );
        }

        return $workingContent;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array{sectionId: string, patch: array<string, mixed>}  $op
     * @return array<int, array<string, mixed>>
     */
    private function applyUpdateSection(array $sections, array $op): array
    {
        $sectionId = trim((string) ($op['sectionId'] ?? ''));
        $patch = is_array($op['patch'] ?? null) ? $op['patch'] : [];
        if ($sectionId === '' || $patch === []) {
            return $sections;
        }

        foreach ($sections as $i => $section) {
            if (! is_array($section)) {
                continue;
            }
            $localId = isset($section['localId']) ? trim((string) $section['localId']) : '';
            if ($localId === $sectionId) {
                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                $sections[$i]['props'] = array_replace_recursive($props, $patch);
                break;
            }
        }

        return $sections;
    }

    /**
     * replaceImage: set image URL on a section (patch or image_url).
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array{sectionId: string, patch?: array<string, mixed>, image_url?: string}  $op
     * @return array<int, array<string, mixed>>
     */
    private function applyReplaceImage(array $sections, array $op): array
    {
        $sectionId = trim((string) ($op['sectionId'] ?? ''));
        if ($sectionId === '') {
            return $sections;
        }
        $patch = is_array($op['patch'] ?? null) ? $op['patch'] : [];
        if ($patch === [] && ! empty($op['image_url']) && is_string($op['image_url'])) {
            $patch = ['image_url' => trim($op['image_url'])];
        }
        if ($patch === []) {
            return $sections;
        }

        return $this->applyUpdateSection($sections, ['sectionId' => $sectionId, 'patch' => $patch]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<string, mixed>  $op
     * @return array<int, array<string, mixed>>
     */
    private function applyUpdateButton(array $sections, array $op): array
    {
        $sectionId = trim((string) ($op['sectionId'] ?? ''));
        if ($sectionId === '') {
            return $sections;
        }

        $currentProps = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            if (trim((string) ($section['localId'] ?? '')) !== $sectionId) {
                continue;
            }

            $currentProps = is_array($section['props'] ?? null) ? $section['props'] : [];
            break;
        }

        $patch = $this->buttonPatchResolver->resolvePatch($op, $currentProps);
        if ($patch === []) {
            return $sections;
        }

        return $this->applyUpdateSection($sections, [
            'sectionId' => $sectionId,
            'patch' => $patch,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array{sectionType: string, afterSectionId?: string}  $op
     * @return array<int, array<string, mixed>>
     */
    private function applyInsertSection(array $sections, array $op): array
    {
        $sectionType = trim((string) ($op['sectionType'] ?? ''));
        if ($sectionType === '') {
            return $sections;
        }
        $afterId = trim((string) ($op['afterSectionId'] ?? ''));
        $insertIndex = count($sections);
        if ($afterId !== '') {
            foreach ($sections as $i => $section) {
                if (is_array($section) && trim((string) ($section['localId'] ?? '')) === $afterId) {
                    $insertIndex = $i + 1;
                    break;
                }
            }
        }

        $newSection = $this->sectionBindings->buildSectionPayload(
            $this->normalizeSectionKey($sectionType),
            is_array($op['props'] ?? null) ? $op['props'] : []
        );
        $newSection['localId'] = 'ai-'.Str::random(8);

        array_splice($sections, $insertIndex, 0, [$newSection]);

        return $sections;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array{sectionId: string}  $op
     * @return array<int, array<string, mixed>>
     */
    private function applyDeleteSection(array $sections, array $op): array
    {
        $sectionId = trim((string) ($op['sectionId'] ?? ''));
        if ($sectionId === '') {
            return $sections;
        }
        return array_values(array_filter($sections, static function ($section) use ($sectionId) {
            if (! is_array($section)) {
                return true;
            }
            $localId = isset($section['localId']) ? trim((string) $section['localId']) : '';
            return $localId !== $sectionId;
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array{sectionId: string, toIndex: int}  $op
     * @return array<int, array<string, mixed>>
     */
    private function applyReorderSection(array $sections, array $op): array
    {
        $sectionId = trim((string) ($op['sectionId'] ?? ''));
        $toIndex = isset($op['toIndex']) ? (int) $op['toIndex'] : 0;
        if ($sectionId === '') {
            return $sections;
        }

        $fromIndex = null;
        $moving = null;
        foreach ($sections as $i => $section) {
            if (is_array($section) && trim((string) ($section['localId'] ?? '')) === $sectionId) {
                $fromIndex = $i;
                $moving = $section;
                break;
            }
        }
        if ($fromIndex === null || $moving === null) {
            return $sections;
        }

        $sections = array_values($sections);
        array_splice($sections, $fromIndex, 1);
        $toIndex = max(0, min($toIndex, count($sections)));
        array_splice($sections, $toIndex, 0, [$moving]);

        return $sections;
    }

    /**
     * updateText: set a single prop by path (e.g. title, headline, subtitle) for a section.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array{sectionId: string, path?: string, value: string}  $op
     * @return array<int, array<string, mixed>>
     */
    private function applyUpdateText(array $sections, array $op): array
    {
        $sectionId = trim((string) ($op['sectionId'] ?? $op['section_id'] ?? ''));
        $value = isset($op['value']) ? (string) $op['value'] : '';
        $pathValue = $op['path'] ?? $op['parameter_path'] ?? 'headline';
        if (is_array($pathValue)) {
            $path = implode('.', array_values(array_filter(array_map(static function ($segment): string {
                return trim((string) $segment);
            }, $pathValue), static fn (string $segment): bool => $segment !== '')));
        } else {
            $path = trim((string) $pathValue);
        }
        if ($path === '') {
            $path = 'headline';
        }
        if ($sectionId === '') {
            return $sections;
        }

        foreach ($sections as $i => $section) {
            if (! is_array($section)) {
                continue;
            }
            $localId = isset($section['localId']) ? trim((string) $section['localId']) : '';
            if ($localId !== $sectionId) {
                continue;
            }
            $props = is_array($section['props'] ?? null) ? $section['props'] : [];
            Arr::set($props, $path, $value);
            $sections[$i]['props'] = $props;
            break;
        }

        return $sections;
    }

    private function normalizeSectionKey(string $key): string
    {
        $key = Str::lower(trim($key));
        if ($key === '') {
            return 'hero';
        }
        $map = [
            'hero' => 'webu_general_heading_01',
            'heading' => 'webu_general_heading_01',
            'banner' => 'banner',
            'footer' => 'webu_footer_01',
            'header' => 'webu_header_01',
            'pricing' => 'webu_general_placeholder_01',
            'testimonials' => 'webu_general_placeholder_01',
            'faq' => 'webu_general_placeholder_01',
            'contact' => 'webu_general_placeholder_01',
            'gallery' => 'webu_general_placeholder_01',
            'team' => 'webu_general_placeholder_01',
            'productgrid' => 'webu_ecom_product_grid_01',
            'productgrid01' => 'webu_ecom_product_grid_01',
            'newsletter' => 'webu_general_newsletter_01',
        ];
        return $map[$key] ?? $key;
    }
}
