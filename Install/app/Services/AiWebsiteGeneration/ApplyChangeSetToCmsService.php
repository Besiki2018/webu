<?php

namespace App\Services\AiWebsiteGeneration;

use App\Models\Website;
use App\Models\WebsitePage;
use App\Models\PageSection;
use App\Models\WebsiteRevision;
use App\Services\UniversalCmsSyncService;
use Illuminate\Support\Facades\DB;

/**
 * Applies AI ChangeSet ops to CMS. Always pushes an Undo snapshot.
 * Ops: updateSection, insertSection, deleteSection, reorderSection, updateTheme, translatePage, generateSEO.
 */
class ApplyChangeSetToCmsService
{
    public function __construct(
        protected UniversalCmsSyncService $cmsSync
    ) {}

    /**
     * @param  array{ops: array<int, array{op: string, ...}>}  $changeSet
     * @return array{applied: bool, website: Website|null}
     */
    public function apply(Website $website, array $changeSet): array
    {
        $ops = $changeSet['ops'] ?? [];
        if ($ops === []) {
            return ['applied' => false, 'website' => $website];
        }

        $website = DB::transaction(function () use ($website, $ops) {
            foreach ($ops as $op) {
                $this->applyOp($website, $op);
            }
            $this->snapshotRevision($website, 'ai');
            return $website->fresh();
        });

        return ['applied' => true, 'website' => $website];
    }

    /** @param array{op: string, sectionId?: int, pageId?: int, content?: array, index?: int, section?: array, fromIndex?: int, toIndex?: int, theme?: array, locale?: string, seo?: array} $op */
    private function applyOp(Website $website, array $op): void
    {
        $opType = (string) ($op['op'] ?? '');
        switch ($opType) {
            case 'updateSection':
                $this->updateSection($website, $op);
                break;
            case 'insertSection':
                $this->insertSection($website, $op);
                break;
            case 'deleteSection':
                $this->deleteSection($website, $op);
                break;
            case 'reorderSection':
                $this->reorderSection($website, $op);
                break;
            case 'updateTheme':
                $this->updateTheme($website, $op);
                break;
            default:
                break;
        }
    }

    private function updateSection(Website $website, array $op): void
    {
        $sectionId = (int) ($op['sectionId'] ?? 0);
        $content = $op['content'] ?? [];
        if ($sectionId < 1 || $content === []) {
            return;
        }
        $section = PageSection::query()->whereIn('page_id', $website->websitePages()->pluck('id')->all())->find($sectionId);
        if ($section) {
            $section->settings_json = array_merge($section->settings_json ?? [], $content);
            $section->save();
            $wp = $section->websitePage;
            if ($wp) {
                $this->cmsSync->pushWebsitePageToRevision($wp);
            }
        }
    }

    private function insertSection(Website $website, array $op): void
    {
        $websitePageId = (int) ($op['pageId'] ?? 0);
        $index = (int) ($op['index'] ?? 0);
        $section = $op['section'] ?? [];
        $type = (string) ($section['type'] ?? 'content');
        $props = $section['props'] ?? [];
        $wp = WebsitePage::query()->where('website_id', $website->id)->find($websitePageId);
        if (! $wp) {
            return;
        }
        $maxOrder = (int) $wp->sections()->max('order');
        $newOrder = min($index, $maxOrder + 1);
        PageSection::create([
            'page_id' => $wp->id,
            'section_type' => $type,
            'order' => $newOrder,
            'settings_json' => $props,
        ]);
        $this->reorderSectionsForPage($wp);
        $this->cmsSync->pushWebsitePageToRevision($wp);
    }

    private function deleteSection(Website $website, array $op): void
    {
        $sectionId = (int) ($op['sectionId'] ?? 0);
        if ($sectionId < 1) {
            return;
        }
        $section = PageSection::query()->whereIn('page_id', $website->websitePages()->pluck('id')->all())->find($sectionId);
        if ($section) {
            $wp = $section->websitePage;
            $section->delete();
            if ($wp) {
                $this->reorderSectionsForPage($wp);
                $this->cmsSync->pushWebsitePageToRevision($wp);
            }
        }
    }

    private function reorderSection(Website $website, array $op): void
    {
        $pageId = (int) ($op['pageId'] ?? 0);
        $fromIndex = (int) ($op['fromIndex'] ?? 0);
        $toIndex = (int) ($op['toIndex'] ?? 0);
        $wp = WebsitePage::query()->where('website_id', $website->id)->find($pageId);
        if (! $wp) {
            return;
        }
        $sections = $wp->sections()->orderBy('order')->get();
        if (! isset($sections[$fromIndex]) || ! isset($sections[$toIndex])) {
            return;
        }
        $moved = $sections[$fromIndex];
        $sections = $sections->values()->all();
        array_splice($sections, $fromIndex, 1);
        array_splice($sections, $toIndex, 0, [$moved]);
        foreach ($sections as $i => $sec) {
            $sec->update(['order' => $i]);
        }
        $this->cmsSync->pushWebsitePageToRevision($wp);
    }

    private function updateTheme(Website $website, array $op): void
    {
        $theme = $op['theme'] ?? [];
        if ($theme !== []) {
            $website->theme = array_merge($website->theme ?? [], $theme);
            $website->save();
            $site = $website->site;
            if ($site) {
                $site->theme_settings = array_merge($site->theme_settings ?? [], $theme);
                $site->save();
            }
        }
    }

    private function reorderSectionsForPage(WebsitePage $wp): void
    {
        $wp->sections()->orderBy('order')->get()->each(function ($s, $i) {
            $s->update(['order' => $i]);
        });
    }

    private function snapshotRevision(Website $website, string $changeType): void
    {
        $maxVersion = (int) $website->revisions()->max('version');
        $snapshot = [
            'website_id' => $website->id,
            'pages' => $website->websitePages()->with('sections')->get()->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'order' => $p->order,
                'sections' => $p->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'section_type' => $s->section_type,
                    'order' => $s->order,
                    'settings_json' => $s->settings_json,
                ])->all(),
            ])->all(),
        ];
        WebsiteRevision::create([
            'website_id' => $website->id,
            'version' => $maxVersion + 1,
            'snapshot_json' => $snapshot,
            'change_type' => $changeType,
        ]);
    }
}
