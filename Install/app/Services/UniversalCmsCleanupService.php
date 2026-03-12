<?php

namespace App\Services;

use App\Models\PageSection;
use App\Models\Site;
use App\Models\Website;
use Illuminate\Support\Facades\Storage;

/**
 * Cleans AI-generated sites: remove dummy content, unused blocks, test images.
 * Keeps only necessary sections and editable content.
 */
class UniversalCmsCleanupService
{
    private const DUMMY_TITLE_PATTERNS = [
        '/^lorem\s+/i',
        '/^ipsum\s+/i',
        '/^dummy\s+/i',
        '/^test\s+/i',
        '/^sample\s+/i',
        '/^placeholder\s+/i',
        '/^example\s+/i',
        '/^click\s+here\s*$/i',
        '/^add\s+content\s*$/i',
        '/^your\s+text\s*$/i',
    ];

    private const DUMMY_IMAGE_NAMES = [
        'placeholder',
        'dummy',
        'test',
        'sample',
        'lorem',
        'ipsum',
        'example',
        'default',
        'img-placeholder',
    ];

    /**
     * Run cleanup for a website: sections with dummy content and orphan media.
     */
    public function cleanupWebsite(Website $website): array
    {
        $removedSections = 0;
        $removedMedia = 0;

        foreach ($website->websitePages as $page) {
            $removed = $this->cleanupPageSections($page->id);
            $removedSections += $removed;
        }

        $siteId = $website->site_id ?? $website->id;
        $mediaPath = 'websites/' . $siteId . '/media';
        if (Storage::disk('public')->exists($mediaPath)) {
            $files = Storage::disk('public')->files($mediaPath);
            foreach ($files as $path) {
                $name = strtolower(pathinfo($path, PATHINFO_FILENAME));
                foreach (self::DUMMY_IMAGE_NAMES as $dummy) {
                    if (str_contains($name, $dummy)) {
                        Storage::disk('public')->delete($path);
                        $removedMedia++;
                        break;
                    }
                }
            }
        }

        return ['removed_sections' => $removedSections, 'removed_media' => $removedMedia];
    }

    /**
     * Remove sections that are clearly dummy (title/subtitle match placeholder patterns).
     */
    private function cleanupPageSections(int $websitePageId): int
    {
        $sections = PageSection::query()
            ->where('page_id', $websitePageId)
            ->orderBy('order')
            ->get();

        $toRemove = [];
        foreach ($sections as $section) {
            $settings = $section->settings_json ?? [];
            $title = (string) ($settings['title'] ?? $settings['heading'] ?? '');
            $subtitle = (string) ($settings['subtitle'] ?? '');
            if ($this->looksDummy($title) && $this->looksDummy($subtitle) && $this->isEmptySection($settings)) {
                $toRemove[] = $section->id;
            }
        }

        if ($toRemove !== []) {
            PageSection::query()->whereIn('id', $toRemove)->delete();

            return count($toRemove);
        }

        return 0;
    }

    private function looksDummy(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return true;
        }
        foreach (self::DUMMY_TITLE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function isEmptySection(array $settings): bool
    {
        $filled = 0;
        foreach (['title', 'heading', 'subtitle', 'description', 'button_text', 'image'] as $key) {
            $v = $settings[$key] ?? '';
            if (is_string($v) && trim($v) !== '') {
                $filled++;
            }
        }

        return $filled <= 1;
    }

    /**
     * Run cleanup for all websites that have a linked site.
     */
    public function cleanupAll(): array
    {
        $total = ['removed_sections' => 0, 'removed_media' => 0];
        $websites = Website::query()->whereNotNull('site_id')->with('websitePages')->get();

        foreach ($websites as $website) {
            $result = $this->cleanupWebsite($website);
            $total['removed_sections'] += $result['removed_sections'];
            $total['removed_media'] += $result['removed_media'];
        }

        return $total;
    }
}
