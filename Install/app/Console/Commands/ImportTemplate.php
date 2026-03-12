<?php

namespace App\Console\Commands;

use App\Services\TemplateImportService;
use Illuminate\Console\Command;

class ImportTemplate extends Command
{
    protected $signature = 'templates:import
        {--path= : Path to template source folder (HTML + assets)}
        {--theme=custom-template : Template slug}
        {--name=Custom Template : Template display name}
        {--plan=* : Plan IDs to assign template to}
        {--force : Overwrite an existing template with the same slug}';

    protected $description = 'Import a custom template package from an HTML source directory.';

    public function __construct(
        protected TemplateImportService $templateImportService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourcePath = $this->resolveSourcePath();
        $themeSlug = (string) $this->option('theme');
        $themeName = (string) $this->option('name');

        if ($themeSlug === '') {
            $this->error('Theme slug cannot be empty.');

            return self::FAILURE;
        }

        if ($themeName === '') {
            $this->error('Theme name cannot be empty.');

            return self::FAILURE;
        }

        if (! is_dir($sourcePath)) {
            $this->error('Template source path not found: '.$sourcePath);

            return self::FAILURE;
        }

        $existing = \App\Models\Template::query()->where('slug', $themeSlug)->first();
        if ($existing !== null && ! (bool) $this->option('force')) {
            $this->error('Template slug already exists. Re-run with --force to overwrite.');

            return self::FAILURE;
        }

        $planIds = collect((array) $this->option('plan'))
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        try {
            $summary = $this->templateImportService->import(
                sourcePath: $sourcePath,
                themeSlug: $themeSlug,
                themeName: $themeName,
                planIds: $planIds,
            );

            $this->info('Template import completed successfully.');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Slug', (string) ($summary['slug'] ?? '')],
                    ['Name', (string) ($summary['name'] ?? '')],
                    ['Source', (string) ($summary['source_path'] ?? '')],
                    ['Template Root', (string) ($summary['template_root'] ?? '')],
                    ['Public Theme Path', (string) ($summary['public_theme_path'] ?? '')],
                    ['Zip Path', (string) ($summary['zip_path'] ?? '')],
                    ['Pages', (string) ($summary['page_count'] ?? 0)],
                    ['Sections', (string) ($summary['section_count'] ?? 0)],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Template import failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveSourcePath(): string
    {
        $provided = trim((string) $this->option('path'));
        if ($provided !== '') {
            return $provided;
        }

        $candidates = [
            base_path('../themeplate/custom-template'),
            base_path('resources/imports/custom-template'),
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
}
