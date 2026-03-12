<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CleanupDuplicateImportedTemplates extends Command
{
    protected $signature = 'templates:cleanup-duplicates {--dry-run : Show planned cleanup actions without deleting data}';

    protected $description = 'Clean up duplicate imported templates with numeric slug suffixes (e.g. slug-2) when source matches base template.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $templates = Template::query()
            ->with('plans:id')
            ->orderBy('slug')
            ->get();

        $index = $templates
            ->keyBy(fn (Template $template): string => (string) $template->slug);

        $rows = [];
        $deleted = 0;
        $skipped = 0;

        foreach ($templates as $template) {
            $slug = (string) $template->slug;
            if (! preg_match('/^(.*)-(\d+)$/', $slug, $matches)) {
                continue;
            }

            $baseSlug = (string) ($matches[1] ?? '');
            if ($baseSlug === '') {
                continue;
            }

            /** @var Template|null $base */
            $base = $index->get($baseSlug);
            if (! $base) {
                $skipped++;
                $rows[] = [$slug, $baseSlug, 'skipped', 'base slug not found'];
                continue;
            }

            $duplicateSource = $this->extractSourceRoot($template);
            $baseSource = $this->extractSourceRoot($base);
            if (! $this->isSameImportSource($baseSource, $duplicateSource, $baseSlug)) {
                $skipped++;
                $rows[] = [$slug, $baseSlug, 'skipped', 'source mismatch'];
                continue;
            }

            if ($dryRun) {
                $rows[] = [$slug, $baseSlug, 'candidate', 'would delete duplicate and merge plan links'];
                continue;
            }

            $this->mergeTemplatePlans($base, $template);
            $this->promoteMissingFields($base, $template);
            $this->cleanupTemplateFiles($base, $template);

            $template->delete();
            $deleted++;

            $rows[] = [$slug, $baseSlug, 'deleted', 'duplicate removed'];
        }

        if ($rows !== []) {
            $this->table(['Duplicate', 'Base', 'Status', 'Note'], $rows);
        } else {
            $this->info('No duplicate suffix templates found.');
        }

        $this->info(sprintf(
            'Cleanup complete. Deleted: %d, Skipped: %d, Dry-run: %s',
            $deleted,
            $skipped,
            $dryRun ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }

    private function mergeTemplatePlans(Template $base, Template $duplicate): void
    {
        $basePlanIds = $base->plans()->pluck('plans.id')->map(fn ($id): int => (int) $id)->all();
        $duplicatePlanIds = $duplicate->plans()->pluck('plans.id')->map(fn ($id): int => (int) $id)->all();

        $merged = array_values(array_unique(array_merge($basePlanIds, $duplicatePlanIds)));
        $base->plans()->sync($merged);
    }

    private function promoteMissingFields(Template $base, Template $duplicate): void
    {
        $payload = [];

        if (! $base->thumbnail && $duplicate->thumbnail) {
            $payload['thumbnail'] = $duplicate->thumbnail;
        }

        if (! $base->getRawOriginal('zip_path') && $duplicate->getRawOriginal('zip_path')) {
            $payload['zip_path'] = $duplicate->getRawOriginal('zip_path');
        }

        if ($payload !== []) {
            $base->update($payload);
        }
    }

    private function cleanupTemplateFiles(Template $base, Template $duplicate): void
    {
        $baseZip = (string) ($base->getRawOriginal('zip_path') ?? '');
        $duplicateZip = (string) ($duplicate->getRawOriginal('zip_path') ?? '');

        if ($duplicateZip !== '' && $duplicateZip !== $baseZip) {
            Storage::disk('local')->delete($duplicateZip);
        }

        $baseThumbnail = (string) ($base->thumbnail ?? '');
        $duplicateThumbnail = (string) ($duplicate->thumbnail ?? '');
        if ($duplicateThumbnail !== '' && $duplicateThumbnail !== $baseThumbnail) {
            Storage::disk('public')->delete($duplicateThumbnail);
        }
    }

    private function extractSourceRoot(Template $template): string
    {
        return trim((string) (
            data_get($template->metadata, 'source_root', '')
            ?: data_get($template->metadata, 'import.source_root', '')
        ));
    }

    private function isSameImportSource(string $baseSourceRoot, string $duplicateSourceRoot, string $baseSlug = ''): bool
    {
        if ($baseSourceRoot === '' || $duplicateSourceRoot === '') {
            return false;
        }

        if ($baseSourceRoot === $duplicateSourceRoot) {
            return true;
        }

        // Legacy compatibility: older imports used plain "directory".
        if (
            ($baseSourceRoot === 'directory' && str_starts_with($duplicateSourceRoot, 'directory:'))
            || ($duplicateSourceRoot === 'directory' && str_starts_with($baseSourceRoot, 'directory:'))
        ) {
            return true;
        }

        $extractDirectoryRelative = static function (string $value): string {
            $trimmed = trim($value);
            if (! str_starts_with($trimmed, 'directory:')) {
                return '';
            }

            return trim(str_replace('\\', '/', substr($trimmed, strlen('directory:'))), '/');
        };

        $baseDirectory = $extractDirectoryRelative($baseSourceRoot);
        $duplicateDirectory = $extractDirectoryRelative($duplicateSourceRoot);
        if ($baseDirectory !== '' && $duplicateDirectory !== '') {
            if (
                $baseDirectory === $duplicateDirectory
                || str_ends_with($baseDirectory, '/'.$duplicateDirectory)
                || str_ends_with($duplicateDirectory, '/'.$baseDirectory)
            ) {
                return true;
            }
        }

        if ($baseSlug !== '') {
            $aliases = array_values(array_filter(array_unique([
                $baseSlug,
                'directory:'.$baseSlug,
                'directory',
            ])));

            if (in_array($baseSourceRoot, $aliases, true) && in_array($duplicateSourceRoot, $aliases, true)) {
                return true;
            }

            $normalizeToSlug = static function (string $value): string {
                $trimmed = trim($value);
                if ($trimmed === '' || $trimmed === 'directory') {
                    return $trimmed;
                }

                if (str_starts_with($trimmed, 'directory:')) {
                    $trimmed = substr($trimmed, strlen('directory:'));
                } elseif (str_starts_with($trimmed, 'zip:')) {
                    $trimmed = substr($trimmed, strlen('zip:'));
                }

                return Str::slug($trimmed);
            };

            $normalizedBase = $normalizeToSlug($baseSourceRoot);
            $normalizedDuplicate = $normalizeToSlug($duplicateSourceRoot);
            if ($normalizedBase !== '' && $normalizedDuplicate !== '' && $normalizedBase === $normalizedDuplicate && $normalizedBase === $baseSlug) {
                return true;
            }

            if (
                ($duplicateSourceRoot === 'directory' && $normalizedBase === $baseSlug)
                || ($baseSourceRoot === 'directory' && $normalizedDuplicate === $baseSlug)
            ) {
                return true;
            }
        }

        $baseLegacy = ! str_contains($baseSourceRoot, '/')
            && ! str_contains($baseSourceRoot, '\\')
            && ! str_contains($baseSourceRoot, ':');

        $duplicateLegacy = ! str_contains($duplicateSourceRoot, '/')
            && ! str_contains($duplicateSourceRoot, '\\')
            && ! str_contains($duplicateSourceRoot, ':');

        $baseTail = basename(str_replace('\\', '/', $baseSourceRoot));
        $duplicateTail = basename(str_replace('\\', '/', $duplicateSourceRoot));
        if ($baseTail === '' || $duplicateTail === '') {
            return false;
        }

        if ($baseLegacy || $duplicateLegacy) {
            return $baseTail === $duplicateTail;
        }

        return false;
    }
}
