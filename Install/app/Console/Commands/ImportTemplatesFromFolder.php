<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Template;
use App\Services\TemplateImportContractService;
use App\Services\TemplateMetadataNormalizerService;
use App\Services\TemplateSectionInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use ZipArchive;

class ImportTemplatesFromFolder extends Command
{
    private string $resolvedImportPath = '';
    private bool $skipDemoBuild = false;

    public function __construct(
        protected TemplateMetadataNormalizerService $metadataNormalizer,
        protected TemplateSectionInventoryService $sectionInventory,
        protected TemplateImportContractService $importContract
    ) {
        parent::__construct();
    }

    protected $signature = 'templates:import-folder
        {path? : Folder containing template directories or .zip files}
        {--plan=* : Plan IDs to assign imported templates to (default: all plans)}
        {--system : Mark imported templates as system templates}
        {--strict : Fail import when template root is ambiguous/invalid instead of skipping}
        {--skip-demo-build : Skip source build/export attempt for static demo generation}';

    protected $description = 'Import template folders/zips into template registry for site creation.';

    public function handle(): int
    {
        $importPath = $this->resolveImportPath((string) ($this->argument('path') ?? ''));
        $this->resolvedImportPath = str_replace('\\', '/', (string) (realpath($importPath) ?: $importPath));
        if (! is_dir($importPath)) {
            $this->error("Import folder not found: {$importPath}");

            return self::FAILURE;
        }

        if ($this->directoryLooksLikeTemplateRoot($importPath)) {
            $entries = collect([$importPath]);
        } else {
            $skipNames = $this->ignoredEntryNames();
            $entries = collect(File::files($importPath))
                ->merge(File::directories($importPath))
                ->filter(static fn (string $path): bool => ! Str::startsWith(basename($path), '.'))
                ->filter(static fn (string $path): bool => ! in_array(mb_strtolower(basename($path)), $skipNames, true))
                ->sortBy(static fn (string $path): string => mb_strtolower(basename($path)))
                ->values();
        }

        if ($entries->isEmpty()) {
            $this->warn("No template directories or zip files found in: {$importPath}");

            return self::SUCCESS;
        }

        $isSystem = (bool) $this->option('system');
        $this->skipDemoBuild = (bool) $this->option('skip-demo-build');
        $planIds = $this->resolvePlanIds($isSystem);

        $this->info('Template import started');
        $this->line("Source folder: {$importPath}");
        $this->line('Entries found: '.$entries->count());
        if (! $isSystem) {
            $this->line('Plan assignment: '.($planIds === [] ? 'none' : implode(', ', $planIds)));
        }

        $imported = 0;
        $failed = 0;
        $skipped = 0;
        $rows = [];
        $strict = (bool) $this->option('strict');

        foreach ($entries as $entry) {
            try {
                if (is_dir($entry)) {
                    $results = $this->importFromDirectory($entry, $planIds, $isSystem, $strict);

                    if ($results === []) {
                        $skipped++;
                        $rows[] = [
                            basename($entry),
                            '-',
                            'directory',
                            'skipped',
                            '-',
                            '-',
                        ];

                        continue;
                    }

                    foreach ($results as $result) {
                        $imported++;
                        $rows[] = [
                            $result['slug'],
                            $result['name'],
                            $result['source'],
                            'imported',
                            (string) ($result['section_count'] ?? '-'),
                            (string) ($result['note'] ?? '-'),
                        ];
                    }

                    continue;
                }

                $result = $this->importFromZip($entry, $planIds, $isSystem);

                if ($result === null) {
                    $skipped++;
                    $rows[] = [
                        basename($entry),
                        '-',
                        'file',
                        'skipped',
                        '-',
                        '-',
                    ];

                    continue;
                }

                $imported++;
                $rows[] = [
                    $result['slug'],
                    $result['name'],
                    $result['source'],
                    'imported',
                    (string) ($result['section_count'] ?? '-'),
                    (string) ($result['note'] ?? '-'),
                ];
            } catch (\Throwable $e) {
                $failed++;
                $rows[] = [
                    basename($entry),
                    '-',
                    is_dir($entry) ? 'directory' : 'zip',
                    'failed: '.$e->getMessage(),
                    '-',
                    '-',
                ];
            }
        }

        $this->newLine();
        $this->table(['Slug', 'Name', 'Source', 'Status', 'Sections', 'Note'], $rows);
        $this->info("Completed. Imported: {$imported}, Skipped: {$skipped}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{slug:string,name:string,source:string,section_count:int,note:string}>
     */
    private function importFromDirectory(string $directory, array $planIds, bool $isSystem, bool $strict): array
    {
        $sourceDirs = $this->resolveTemplateSourceDirectories($directory, $strict);
        if ($sourceDirs === []) {
            return [];
        }

        $results = [];
        foreach ($sourceDirs as $sourceDir) {
            $result = $this->importFromResolvedDirectory($directory, $sourceDir, $planIds, $isSystem, $strict);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param  array<int,int>  $planIds
     * @return array{slug:string,name:string,source:string,section_count:int,note:string}|null
     */
    private function importFromResolvedDirectory(string $directory, string $sourceDir, array $planIds, bool $isSystem, bool $strict): ?array
    {
        $rootValidation = $this->importContract->validateSourceRoot($sourceDir);
        if (! $rootValidation['valid']) {
            if ($strict) {
                throw new \RuntimeException(implode(' ', $rootValidation['errors']));
            }

            $this->warn('Skipped "'.basename($sourceDir).'": '.implode(' ', $rootValidation['errors']));

            return null;
        }

        foreach ($rootValidation['warnings'] as $warning) {
            $this->line('['.basename($sourceDir)."] {$warning}");
        }

        $templateJsonPath = $this->findTemplateJsonPath($sourceDir);
        $manifest = $templateJsonPath ? $this->decodeJsonFile($templateJsonPath) : null;

        $baseName = basename($sourceDir);
        $sourceDescriptor = $this->formatDirectorySource($directory, $sourceDir);
        $slug = $this->resolveImportSlug(
            $this->resolveSlug($manifest, $baseName),
            $manifest,
            $sourceDescriptor
        );
        $name = $this->resolveName($manifest, $slug);
        $description = $this->resolveDescription($manifest, $name);
        $category = $this->resolveCategory($manifest);
        $version = $this->resolveVersion($manifest);
        $keywords = $this->resolveKeywords($manifest);
        $publishedDemoPath = $this->publishStaticDemoFromSourceIfAvailable($sourceDir, $slug, ! $this->skipDemoBuild);
        $baseMetadata = $this->metadataNormalizer->normalize(
            $manifest,
            $slug,
            $name,
            $category,
            ['source_root' => $sourceDescriptor]
        );
        $inventory = $this->sectionInventory->extract($baseMetadata);
        $metadata = $this->metadataNormalizer->normalize(
            $baseMetadata,
            $slug,
            $name,
            $category,
            [
                'source_root' => $sourceDescriptor,
                'section_inventory' => $inventory,
            ]
        );
        $metadata = $this->injectLiveDemoPathIfAvailable($metadata, $slug, $publishedDemoPath);

        $metadataValidation = $this->importContract->validateMetadata($metadata);
        if (! $metadataValidation['valid']) {
            if ($strict) {
                throw new \RuntimeException(implode(' ', $metadataValidation['errors']));
            }

            $this->warn('Skipped "'.basename($sourceDir).'": '.implode(' ', $metadataValidation['errors']));

            return null;
        }

        foreach ($metadataValidation['warnings'] as $warning) {
            $this->line('['.basename($sourceDir)."] {$warning}");
        }

        $zipRelativePath = "templates/{$slug}-template.zip";
        $zipAbsolutePath = storage_path('app/'.$zipRelativePath);
        $this->createTemplateZipFromDirectory($sourceDir, $zipAbsolutePath, $metadata, $templateJsonPath !== null);

        $thumbnailPath = $this->importThumbnailFromDirectory($sourceDir, $slug, $manifest);

        $this->upsertTemplate(
            slug: $slug,
            name: $name,
            description: $description,
            category: $category,
            version: $version,
            keywords: $keywords,
            metadata: $metadata,
            zipPath: $zipRelativePath,
            thumbnail: $thumbnailPath,
            planIds: $planIds,
            isSystem: $isSystem
        );

        return [
            'slug' => $slug,
            'name' => $name,
            'source' => $this->formatDirectorySource($directory, $sourceDir),
            'section_count' => (int) Arr::get($inventory, 'summary.total', 0),
            'note' => 'mapped='.((int) Arr::get($inventory, 'summary.mapped', 0)).', unmapped='.((int) Arr::get($inventory, 'summary.unmapped', 0)),
        ];
    }

    /**
     * @return array{slug:string,name:string,source:string,section_count:int,note:string}|null
     */
    private function importFromZip(string $zipFile, array $planIds, bool $isSystem): ?array
    {
        if (strtolower(pathinfo($zipFile, PATHINFO_EXTENSION)) !== 'zip') {
            return null;
        }

        $manifest = $this->readManifestFromZip($zipFile);
        $baseName = pathinfo($zipFile, PATHINFO_FILENAME);
        $slug = $this->resolveSlug($manifest, $baseName);
        $sourceDescriptor = 'zip:'.basename($zipFile);
        $slug = $this->resolveImportSlug($slug, $manifest, $sourceDescriptor);
        $name = $this->resolveName($manifest, $slug);
        $description = $this->resolveDescription($manifest, $name);
        $category = $this->resolveCategory($manifest);
        $version = $this->resolveVersion($manifest);
        $keywords = $this->resolveKeywords($manifest);
        $baseMetadata = $this->metadataNormalizer->normalize(
            $manifest,
            $slug,
            $name,
            $category,
            ['source_root' => $sourceDescriptor]
        );
        $inventory = $this->sectionInventory->extract($baseMetadata);
        $metadata = $this->metadataNormalizer->normalize(
            $baseMetadata,
            $slug,
            $name,
            $category,
            [
                'source_root' => $sourceDescriptor,
                'section_inventory' => $inventory,
            ]
        );
        $metadata = $this->injectLiveDemoPathIfAvailable($metadata, $slug);

        $metadataValidation = $this->importContract->validateMetadata($metadata);
        if (! $metadataValidation['valid']) {
            throw new \RuntimeException(implode(' ', $metadataValidation['errors']));
        }

        $zipRelativePath = "templates/{$slug}-template.zip";
        $zipAbsolutePath = storage_path('app/'.$zipRelativePath);
        File::ensureDirectoryExists(dirname($zipAbsolutePath));
        File::copy($zipFile, $zipAbsolutePath);

        $thumbnailPath = $this->importThumbnailFromZip($zipFile, $slug, $manifest);

        $this->upsertTemplate(
            slug: $slug,
            name: $name,
            description: $description,
            category: $category,
            version: $version,
            keywords: $keywords,
            metadata: $metadata,
            zipPath: $zipRelativePath,
            thumbnail: $thumbnailPath,
            planIds: $planIds,
            isSystem: $isSystem
        );

        return [
            'slug' => $slug,
            'name' => $name,
            'source' => 'zip',
            'section_count' => (int) Arr::get($inventory, 'summary.total', 0),
            'note' => 'mapped='.((int) Arr::get($inventory, 'summary.mapped', 0)).', unmapped='.((int) Arr::get($inventory, 'summary.unmapped', 0)),
        ];
    }

    private function resolveImportPath(string $pathArgument): string
    {
        if ($pathArgument !== '') {
            if (Str::startsWith($pathArgument, ['/','\\'])) {
                return $pathArgument;
            }

            return base_path($pathArgument);
        }

        // Default workspace path: ../themeplate/import
        return dirname(base_path()).DIRECTORY_SEPARATOR.'themeplate'.DIRECTORY_SEPARATOR.'import';
    }

    /**
     * @return array<int,int>
     */
    private function resolvePlanIds(bool $isSystem): array
    {
        if ($isSystem) {
            return [];
        }

        $planOption = collect((array) $this->option('plan'))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($planOption !== []) {
            $planIds = Plan::query()->whereIn('id', $planOption)->pluck('id')->map(static fn ($id) => (int) $id)->all();
            if ($planIds === []) {
                $this->warn('No valid plans found in --plan option. Imported templates will not be assigned to any plan.');
            }

            return $planIds;
        }

        return Plan::query()->pluck('id')->map(static fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<int,string>
     */
    private function resolveTemplateSourceDirectories(string $directory, bool $strict): array
    {
        if ($this->directoryLooksLikeTemplateRoot($directory)) {
            return [$directory];
        }

        $candidates = $this->findTemplateRootCandidates($directory, 2);
        if ($candidates === []) {
            if ($strict) {
                throw new \RuntimeException('No valid template root found');
            }

            $this->warn('Skipped "'.basename($directory).'": no valid template root found.');

            return [];
        }

        if (count($candidates) > 1) {
            if ($strict) {
                throw new \RuntimeException('Ambiguous template roots: '.implode(', ', array_map('basename', $candidates)));
            }

            $this->line('['.basename($directory).'] multiple template roots detected ('.count($candidates).'); importing each detected root.');
        }

        return $candidates;
    }

    private function directoryLooksLikeTemplateRoot(string $directory): bool
    {
        $hasTemplateManifest = File::exists($directory.'/template.json');
        $hasHtmlEntry = File::exists($directory.'/index.html');
        $hasPackage = File::exists($directory.'/package.json');
        $hasCodeDir = File::isDirectory($directory.'/src')
            || File::isDirectory($directory.'/pages')
            || File::isDirectory($directory.'/app');
        $hasPublicDir = File::isDirectory($directory.'/public');
        $hasGatsbyConfig = File::exists($directory.'/gatsby-config.js')
            || File::exists($directory.'/gatsby-node.js');

        return $hasTemplateManifest
            || $hasHtmlEntry
            || ($hasPackage && ($hasCodeDir || $hasPublicDir))
            || ($hasPackage && $hasGatsbyConfig)
            || $hasCodeDir;
    }

    /**
     * @return array<int,string>
     */
    private function findTemplateRootCandidates(string $directory, int $maxDepth = 2): array
    {
        $candidates = [];
        $queue = [[$directory, 0]];
        $skipNames = $this->ignoredEntryNames();

        while ($queue !== []) {
            [$current, $depth] = array_shift($queue);

            if ($current !== $directory && $this->directoryLooksLikeTemplateRoot($current)) {
                $candidates[] = $current;
                continue;
            }

            if ($depth >= $maxDepth) {
                continue;
            }

            foreach (File::directories($current) as $childDir) {
                $name = basename($childDir);
                if (Str::startsWith($name, '.') || in_array(mb_strtolower($name), $skipNames, true)) {
                    continue;
                }

                $queue[] = [$childDir, $depth + 1];
            }
        }

        return collect($candidates)
            ->unique()
            ->sortBy(static fn (string $path): string => mb_strtolower($path))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function ignoredEntryNames(): array
    {
        return [
            '.git',
            '.github',
            '.next',
            '.nuxt',
            '.turbo',
            'node_modules',
            'vendor',
            'dist',
            'build',
            'out',
            'coverage',
        ];
    }

    private function formatDirectorySource(string $entryDirectory, string $sourceDirectory): string
    {
        $sourceReal = realpath($sourceDirectory);
        if (! is_string($sourceReal)) {
            return 'directory';
        }

        $sourceNormalized = str_replace('\\', '/', $sourceReal);
        $relative = '';

        $importRoot = trim($this->resolvedImportPath);
        if ($importRoot !== '') {
            $importRoot = rtrim(str_replace('\\', '/', $importRoot), '/');
            if (str_starts_with($sourceNormalized, $importRoot.'/')) {
                $relative = ltrim(substr($sourceNormalized, strlen($importRoot)), '/');
            }
        }

        if ($relative === '') {
            $entryReal = realpath($entryDirectory);
            if (is_string($entryReal)) {
                $entryNormalized = rtrim(str_replace('\\', '/', $entryReal), '/');
                if (str_starts_with($sourceNormalized, $entryNormalized.'/')) {
                    $relative = ltrim(substr($sourceNormalized, strlen($entryNormalized)), '/');
                }
            }
        }

        if ($relative === '') {
            $relative = basename($sourceNormalized);
        }

        return 'directory:'.$relative;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function injectLiveDemoPathIfAvailable(array $metadata, string $slug, ?string $publishedPath = null): array
    {
        $existing = trim((string) Arr::get($metadata, 'live_demo.path', ''));
        if ($existing !== '') {
            return $metadata;
        }

        $relativePath = $publishedPath ?: "template-demos/{$slug}";
        $indexFile = public_path($relativePath.'/index.html');

        if (! is_file($indexFile)) {
            return $metadata;
        }

        $liveDemo = is_array(Arr::get($metadata, 'live_demo', []))
            ? Arr::get($metadata, 'live_demo', [])
            : [];
        $liveDemo['path'] = $relativePath;
        $metadata['live_demo'] = $liveDemo;

        return $metadata;
    }

    private function publishStaticDemoFromSourceIfAvailable(string $sourceDir, string $slug, bool $allowBuild = true): ?string
    {
        $selected = $this->findStaticDemoCandidateDirectory($sourceDir);
        if (! is_string($selected) && $allowBuild) {
            $selected = $this->buildStaticDemoFromSourceIfAvailable($sourceDir, $slug);
        }

        if (! is_string($selected)) {
            return null;
        }

        $demoPublicSlug = $this->resolveDemoPublicSlug($selected, $slug);
        $relativePath = "template-demos/{$demoPublicSlug}";
        $target = public_path($relativePath);
        File::deleteDirectory($target);
        File::ensureDirectoryExists(dirname($target));
        File::copyDirectory($selected, $target);
        $this->normalizePublishedDemoHtmlReferences($target, $demoPublicSlug);
        $this->normalizePublishedDemoAssetPrefixes($target, $demoPublicSlug);

        return $relativePath;
    }

    private function findStaticDemoCandidateDirectory(string $sourceDir): ?string
    {
        $candidates = [
            $sourceDir.'/out',
            $sourceDir.'/dist',
            $sourceDir.'/build',
            $sourceDir.'/demo',
            $sourceDir.'/public/demo',
            $sourceDir.'/.output/public',
        ];

        foreach ($candidates as $candidate) {
            if (! is_dir($candidate) || ! is_file($candidate.'/index.html')) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function buildStaticDemoFromSourceIfAvailable(string $sourceDir, string $slug): ?string
    {
        if (app()->environment('testing') || ! is_file($sourceDir.'/package.json')) {
            return null;
        }

        $package = $this->decodeJsonFile($sourceDir.'/package.json');
        if (! is_array($package)) {
            return null;
        }

        $scripts = Arr::get($package, 'scripts', []);
        if (! is_array($scripts)) {
            $scripts = [];
        }

        $framework = $this->detectTemplateFramework($sourceDir, $package);
        $buildPlans = $this->resolveBuildPlans($framework, $scripts);
        if ($buildPlans === []) {
            return null;
        }

        $installCommand = $this->resolvePackageInstallCommand($sourceDir);
        if ($installCommand === []) {
            return null;
        }

        $nextConfigPatchState = $framework === 'next'
            ? $this->prepareNextStaticExportConfig($sourceDir, $slug)
            : null;
        $sourceAliasPaths = $this->prepareBuildSourceAliases($sourceDir);

        try {
            $this->line("[{$slug}] preparing demo build dependencies...");
            $installResult = $this->runProcessCommand($installCommand, $sourceDir, 1200);
            if (! $installResult['success']) {
                $this->warn("[{$slug}] demo build skipped: dependency install failed.");

                return null;
            }

            foreach ($buildPlans as $plan) {
                $this->line("[{$slug}] demo build attempt: {$plan['label']}");
                $success = true;

                foreach ($plan['commands'] as $command) {
                    $result = $this->runProcessCommand($command, $sourceDir, 1200);
                    if (! $result['success']) {
                        $success = false;
                        break;
                    }
                }

                if (! $success) {
                    continue;
                }

                $candidate = $this->findStaticDemoCandidateDirectory($sourceDir);
                if (is_string($candidate)) {
                    $this->line("[{$slug}] static demo build completed.");

                    return $candidate;
                }
            }

            return null;
        } finally {
            $this->restoreNextStaticExportConfig($nextConfigPatchState);
            $this->cleanupBuildSourceAliases($sourceAliasPaths);
            $this->cleanupTemplateBuildDependencies($sourceDir);
        }
    }

    /**
     * @param  array<string,mixed>  $package
     */
    private function detectTemplateFramework(string $sourceDir, array $package): string
    {
        $dependencies = array_merge(
            is_array(Arr::get($package, 'dependencies', [])) ? Arr::get($package, 'dependencies', []) : [],
            is_array(Arr::get($package, 'devDependencies', [])) ? Arr::get($package, 'devDependencies', []) : []
        );

        if (is_array($dependencies) && array_key_exists('next', $dependencies)) {
            return 'next';
        }

        if (is_array($dependencies) && array_key_exists('gatsby', $dependencies)) {
            return 'gatsby';
        }

        if (is_array($dependencies) && (array_key_exists('vite', $dependencies) || array_key_exists('@vitejs/plugin-react', $dependencies) || array_key_exists('@vitejs/plugin-react-swc', $dependencies))) {
            return 'vite';
        }

        if (is_array($dependencies) && array_key_exists('react-scripts', $dependencies)) {
            return 'cra';
        }

        if (File::exists($sourceDir.'/next.config.js') || File::exists($sourceDir.'/next.config.mjs') || File::exists($sourceDir.'/next.config.ts')) {
            return 'next';
        }

        if (File::exists($sourceDir.'/vite.config.js') || File::exists($sourceDir.'/vite.config.ts') || File::exists($sourceDir.'/vite.config.mjs')) {
            return 'vite';
        }

        return 'generic';
    }

    /**
     * @param  array<string,mixed>  $scripts
     * @return array<int,array{label:string,commands:array<int,array<int,string>>}>
     */
    private function resolveBuildPlans(string $framework, array $scripts): array
    {
        $plans = [];
        $packageManager = $this->resolvePackageManagerByLockfiles();

        $buildScriptExists = is_string(Arr::get($scripts, 'build')) && trim((string) Arr::get($scripts, 'build')) !== '';
        $exportScriptExists = is_string(Arr::get($scripts, 'export')) && trim((string) Arr::get($scripts, 'export')) !== '';

        if ($buildScriptExists && $exportScriptExists) {
            $plans[] = [
                'label' => 'build+export',
                'commands' => [
                    $this->resolveRunScriptCommand($packageManager, 'build'),
                    $this->resolveRunScriptCommand($packageManager, 'export'),
                ],
            ];
        }

        if ($buildScriptExists) {
            $plans[] = [
                'label' => 'build',
                'commands' => [
                    $this->resolveRunScriptCommand($packageManager, 'build'),
                ],
            ];
        }

        if ($framework === 'next' && $buildScriptExists && ! $exportScriptExists) {
            $plans[] = [
                'label' => 'build+next-export',
                'commands' => [
                    $this->resolveRunScriptCommand($packageManager, 'build'),
                    ['npx', 'next', 'export'],
                ],
            ];
        }

        return collect($plans)
            ->filter(static fn (array $plan): bool => collect($plan['commands'] ?? [])
                ->every(static fn (array $command): bool => $command !== []))
            ->unique(static fn (array $plan): string => implode('|', collect($plan['commands'])->map(static fn (array $command): string => implode(' ', $command))->all()))
            ->values()
            ->all();
    }

    /**
     * @return array<int,string>
     */
    private function resolvePackageInstallCommand(string $sourceDir): array
    {
        $manager = $this->resolvePackageManagerByLockfiles($sourceDir);

        return match ($manager) {
            'yarn' => ['yarn', 'install', '--ignore-engines'],
            'pnpm' => ['pnpm', 'install', '--no-frozen-lockfile'],
            default => ['npm', 'install', '--legacy-peer-deps', '--no-audit', '--no-fund'],
        };
    }

    /**
     * @return array<int,string>
     */
    private function resolveRunScriptCommand(string $packageManager, string $script): array
    {
        return match ($packageManager) {
            'yarn' => ['yarn', 'run', $script],
            'pnpm' => ['pnpm', 'run', $script],
            default => ['npm', 'run', $script],
        };
    }

    private function resolvePackageManagerByLockfiles(?string $sourceDir = null): string
    {
        $root = is_string($sourceDir) ? rtrim($sourceDir, '/') : null;
        if (is_string($root) && is_file($root.'/yarn.lock')) {
            return 'yarn';
        }

        if (is_string($root) && (is_file($root.'/pnpm-lock.yaml') || is_file($root.'/pnpm-lock.yml'))) {
            return 'pnpm';
        }

        if (is_string($root) && is_file($root.'/package-lock.json')) {
            return 'npm';
        }

        return 'npm';
    }

    /**
     * @param  array<int,string>  $command
     * @return array{success:bool,output:string}
     */
    private function runProcessCommand(array $command, string $workingDirectory, int $timeoutSeconds = 600): array
    {
        $cacheRoot = $workingDirectory.'/.webby-build-cache';
        File::ensureDirectoryExists($cacheRoot);

        $process = new Process($command, $workingDirectory, [
            'NEXT_TELEMETRY_DISABLED' => '1',
            'NPM_CONFIG_CACHE' => $cacheRoot.'/npm',
            'YARN_CACHE_FOLDER' => $cacheRoot.'/yarn',
            'PNPM_HOME' => $cacheRoot.'/pnpm',
        ]);
        $process->setTimeout($timeoutSeconds);
        $process->run();

        $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());

        if (! $process->isSuccessful() && $output !== '') {
            $lines = preg_split('/\R/', $output) ?: [];
            $tail = collect($lines)->filter()->take(-6)->values()->all();
            if ($tail !== []) {
                $this->line('  - '.implode(' | ', $tail));
            }
        }

        return [
            'success' => $process->isSuccessful(),
            'output' => $output,
        ];
    }

    private function cleanupTemplateBuildDependencies(string $sourceDir): void
    {
        $cleanupPaths = [
            $sourceDir.'/node_modules',
            $sourceDir.'/.next/cache',
            $sourceDir.'/.webby-build-cache',
        ];

        foreach ($cleanupPaths as $cleanupPath) {
            if (! is_dir($cleanupPath)) {
                continue;
            }

            File::deleteDirectory($cleanupPath);
        }
    }

    /**
     * @return array<int,string>
     */
    private function prepareBuildSourceAliases(string $sourceDir): array
    {
        $sourceAssets = $sourceDir.'/src/assets';
        if (! is_dir($sourceAssets)) {
            return [];
        }

        $aliases = [];
        $candidateAliases = [
            $sourceDir.'/src/pages/assets',
            $sourceDir.'/src/components/assets',
            $sourceDir.'/src/layouts/assets',
        ];

        foreach ($candidateAliases as $aliasPath) {
            if (is_dir($aliasPath) || is_file($aliasPath) || is_link($aliasPath)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($aliasPath));
            $targetRelative = $this->relativePath(dirname($aliasPath), $sourceAssets);

            if (@symlink($targetRelative, $aliasPath)) {
                $aliases[] = $aliasPath;
                continue;
            }

            // Fallback when symlink is unavailable.
            if (File::copyDirectory($sourceAssets, $aliasPath)) {
                $aliases[] = $aliasPath;
            }
        }

        return $aliases;
    }

    /**
     * @param  array<int,string>  $aliases
     */
    private function cleanupBuildSourceAliases(array $aliases): void
    {
        foreach ($aliases as $aliasPath) {
            if (is_link($aliasPath) || is_file($aliasPath)) {
                @unlink($aliasPath);
                continue;
            }

            if (is_dir($aliasPath)) {
                File::deleteDirectory($aliasPath);
            }
        }
    }

    private function relativePath(string $from, string $to): string
    {
        $fromParts = explode('/', trim(str_replace('\\', '/', realpath($from) ?: $from), '/'));
        $toParts = explode('/', trim(str_replace('\\', '/', realpath($to) ?: $to), '/'));

        while ($fromParts !== [] && $toParts !== [] && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        $up = array_fill(0, count($fromParts), '..');
        $path = array_merge($up, $toParts);

        return $path === [] ? '.' : implode('/', $path);
    }

    /**
     * @return array{config_file:string,backup_file:string|null,created_new:bool}|null
     */
    private function prepareNextStaticExportConfig(string $sourceDir, string $slug): ?array
    {
        $candidates = [
            $sourceDir.'/next.config.js',
            $sourceDir.'/next.config.mjs',
            $sourceDir.'/next.config.ts',
        ];

        $existingConfig = collect($candidates)->first(static fn (string $candidate): bool => is_file($candidate));
        $assetPrefix = '/template-demos/'.$slug;

        if (! is_string($existingConfig)) {
            $newConfig = $sourceDir.'/next.config.js';
            File::put($newConfig, $this->buildNextConfigWrapperContents(
                extension: 'js',
                backupBasename: null,
                assetPrefix: $assetPrefix
            ));

            return [
                'config_file' => $newConfig,
                'backup_file' => null,
                'created_new' => true,
            ];
        }

        $extension = strtolower((string) pathinfo($existingConfig, PATHINFO_EXTENSION));
        $backupFile = $existingConfig.'.webby-original';

        if (is_file($backupFile)) {
            File::delete($backupFile);
        }

        File::move($existingConfig, $backupFile);
        File::put(
            $existingConfig,
            $this->buildNextConfigWrapperContents(
                extension: $extension,
                backupBasename: basename($backupFile),
                assetPrefix: $assetPrefix
            )
        );

        return [
            'config_file' => $existingConfig,
            'backup_file' => $backupFile,
            'created_new' => false,
        ];
    }

    /**
     * @param  array{config_file:string,backup_file:string|null,created_new:bool}|null  $state
     */
    private function restoreNextStaticExportConfig(?array $state): void
    {
        if (! is_array($state)) {
            return;
        }

        $configFile = (string) ($state['config_file'] ?? '');
        $backupFile = isset($state['backup_file']) ? (string) $state['backup_file'] : null;
        $createdNew = (bool) ($state['created_new'] ?? false);

        if ($configFile === '') {
            return;
        }

        if ($createdNew) {
            if (is_file($configFile)) {
                File::delete($configFile);
            }

            return;
        }

        if (! is_string($backupFile) || $backupFile === '' || ! is_file($backupFile)) {
            return;
        }

        if (is_file($configFile)) {
            File::delete($configFile);
        }

        File::move($backupFile, $configFile);
    }

    private function buildNextConfigWrapperContents(string $extension, ?string $backupBasename, string $assetPrefix): string
    {
        $escapedAssetPrefix = str_replace("'", "\\'", $assetPrefix);

        return match ($extension) {
            'mjs' => <<<MJS
// Auto-generated by Webby template importer to force static export during demo build.
import baseConfig from '{$backupBasename}';

const resolveConfig = typeof baseConfig === 'function'
    ? baseConfig
    : () => (baseConfig || {});

const webbyConfig = (...args) => {
    const config = resolveConfig(...args) || {};

    return {
        ...config,
        output: 'export',
        trailingSlash: true,
        images: {
            ...(config.images || {}),
            unoptimized: true,
        },
        assetPrefix: '{$escapedAssetPrefix}',
    };
};

export default webbyConfig;
MJS,
            'ts' => <<<TS
// Auto-generated by Webby template importer to force static export during demo build.
import baseConfig from '{$backupBasename}';

const resolveConfig = typeof baseConfig === 'function'
    ? baseConfig
    : () => (baseConfig || {});

const webbyConfig = (...args: any[]) => {
    const config = resolveConfig(...args) || {};

    return {
        ...config,
        output: 'export',
        trailingSlash: true,
        images: {
            ...(config.images || {}),
            unoptimized: true,
        },
        assetPrefix: '{$escapedAssetPrefix}',
    };
};

export default webbyConfig;
TS,
            default => $backupBasename === null
                ? <<<JS
// Auto-generated by Webby template importer to force static export during demo build.
module.exports = {
    output: 'export',
    trailingSlash: true,
    images: {
        unoptimized: true,
    },
    assetPrefix: '{$escapedAssetPrefix}',
};
JS
                : <<<JS
// Auto-generated by Webby template importer to force static export during demo build.
const baseConfig = require('./{$backupBasename}');

const resolveConfig = typeof baseConfig === 'function'
    ? baseConfig
    : () => (baseConfig || {});

module.exports = (...args) => {
    const config = resolveConfig(...args) || {};

    return {
        ...config,
        output: 'export',
        trailingSlash: true,
        images: {
            ...(config.images || {}),
            unoptimized: true,
        },
        assetPrefix: '{$escapedAssetPrefix}',
    };
};
JS,
        };
    }

    private function normalizePublishedDemoHtmlReferences(string $targetDir, string $demoPublicSlug): void
    {
        $prefix = '/template-demos/'.$demoPublicSlug;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $absolutePath = (string) $item->getPathname();
            if (strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION)) !== 'html') {
                continue;
            }

            $content = (string) File::get($absolutePath);
            if ($content === '') {
                continue;
            }

            $rewritten = preg_replace_callback(
                '/\b(href|src|action|content)=([\'"])(\/[^\'"]*)\2/i',
                static function (array $matches) use ($prefix): string {
                    $attr = $matches[1];
                    $quote = $matches[2];
                    $path = $matches[3];

                    if (
                        str_starts_with($path, '//')
                        || str_starts_with($path, '/template-demos/')
                        || str_starts_with($path, '/mailto:')
                        || str_starts_with($path, '/tel:')
                        || str_starts_with($path, '/javascript:')
                    ) {
                        return $matches[0];
                    }

                    return "{$attr}={$quote}{$prefix}{$path}{$quote}";
                },
                $content
            );

            if (! is_string($rewritten)) {
                continue;
            }

            if ($rewritten !== $content) {
                File::put($absolutePath, $rewritten);
            }
        }
    }

    private function normalizePublishedDemoAssetPrefixes(string $targetDir, string $demoPublicSlug): void
    {
        $prefix = '/template-demos/'.$demoPublicSlug;
        $textExtensions = ['html', 'js', 'mjs', 'css', 'json', 'txt', 'map'];

        $replacements = [
            '"/_next/' => '"'.$prefix.'/_next/',
            "'/_next/" => "'".$prefix."/_next/",
            '"/assets/' => '"'.$prefix.'/assets/',
            "'/assets/" => "'".$prefix."/assets/",
            '"/static/' => '"'.$prefix.'/static/',
            "'/static/" => "'".$prefix."/static/",
            'url(/_next/' => 'url('.$prefix.'/_next/',
            'url(/assets/' => 'url('.$prefix.'/assets/',
            'url(/static/' => 'url('.$prefix.'/static/',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $absolutePath = (string) $item->getPathname();
            $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
            if (! in_array($extension, $textExtensions, true)) {
                continue;
            }

            $content = (string) File::get($absolutePath);
            if ($content === '') {
                continue;
            }

            $rewritten = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $content
            );

            if ($rewritten !== $content) {
                File::put($absolutePath, $rewritten);
            }
        }
    }

    private function resolveDemoPublicSlug(string $demoSourceDir, string $fallbackSlug): string
    {
        $indexFile = $demoSourceDir.'/index.html';
        if (! is_file($indexFile)) {
            return $fallbackSlug;
        }

        $contents = (string) File::get($indexFile);
        if ($contents === '') {
            return $fallbackSlug;
        }

        $patterns = [
            '/"assetPrefix":"\\\\\/template-demos\\\\\/([^"\\\\\/]+)"/i',
            '/"assetPrefix"\s*:\s*"\/template-demos\/([^"\/]+)"/i',
            '/\/template-demos\/([a-z0-9\-]+)\/_next\/static/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents, $matches) === 1) {
                $candidate = trim((string) ($matches[1] ?? ''));
                $normalized = Str::slug($candidate);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return $fallbackSlug;
    }

    private function findTemplateJsonPath(string $directory): ?string
    {
        $rootPath = $directory.'/template.json';
        if (File::exists($rootPath)) {
            return $rootPath;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = (string) $file->getPathname();
            if (strtolower(basename($path)) === 'template.json') {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeJsonFile(string $file): ?array
    {
        $decoded = json_decode((string) File::get($file), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function readManifestFromZip(string $zipFile): ?array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipFile) !== true) {
            return null;
        }

        $manifestPath = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (strtolower(basename($name)) !== 'template.json') {
                continue;
            }

            if ($manifestPath === null || strlen($name) < strlen($manifestPath)) {
                $manifestPath = $name;
            }
        }

        if (! is_string($manifestPath)) {
            $zip->close();

            return null;
        }

        $contents = $zip->getFromName($manifestPath);
        $zip->close();

        if (! is_string($contents)) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function resolveSlug(?array $manifest, string $fallback): string
    {
        $rawSlug = (string) Arr::get($manifest, 'slug', '');
        $rawSlug = trim($rawSlug) !== '' ? $rawSlug : $fallback;
        $slug = Str::slug($rawSlug);

        return $slug !== '' ? $slug : 'template-'.Str::random(8);
    }

    private function resolveImportSlug(string $baseSlug, ?array $manifest, string $sourceDescriptor): string
    {
        $rawManifestSlug = trim((string) Arr::get($manifest, 'slug', ''));
        if ($rawManifestSlug !== '') {
            return $baseSlug;
        }

        $candidate = $baseSlug;
        $suffix = 2;

        while (true) {
            $existing = Template::query()->where('slug', $candidate)->first();
            if (! $existing) {
                return $candidate;
            }

            $existingSourceRoot = trim((string) (
                data_get($existing->metadata, 'source_root', '')
                ?: data_get($existing->metadata, 'import.source_root', '')
            ));
            if ($this->isSameImportSource($existingSourceRoot, $sourceDescriptor, $candidate)) {
                return $candidate;
            }

            $candidate = "{$baseSlug}-{$suffix}";
            $suffix++;
        }
    }

    private function isSameImportSource(string $existingSourceRoot, string $sourceDescriptor, string $candidateSlug = ''): bool
    {
        if ($existingSourceRoot === '' || $sourceDescriptor === '') {
            return false;
        }

        if ($existingSourceRoot === $sourceDescriptor) {
            return true;
        }

        // Legacy compatibility: older imports used plain "directory" as source root.
        if (
            ($existingSourceRoot === 'directory' && str_starts_with($sourceDescriptor, 'directory:'))
            || ($sourceDescriptor === 'directory' && str_starts_with($existingSourceRoot, 'directory:'))
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

        $existingDirectory = $extractDirectoryRelative($existingSourceRoot);
        $candidateDirectory = $extractDirectoryRelative($sourceDescriptor);
        if ($existingDirectory !== '' && $candidateDirectory !== '') {
            if (
                $existingDirectory === $candidateDirectory
                || str_ends_with($existingDirectory, '/'.$candidateDirectory)
                || str_ends_with($candidateDirectory, '/'.$existingDirectory)
            ) {
                return true;
            }
        }

        if ($candidateSlug !== '') {
            $aliases = array_values(array_filter(array_unique([
                $candidateSlug,
                'directory:'.$candidateSlug,
                'directory',
            ])));

            if (in_array($existingSourceRoot, $aliases, true) && in_array($sourceDescriptor, $aliases, true)) {
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

            $normalizedExisting = $normalizeToSlug($existingSourceRoot);
            $normalizedDescriptor = $normalizeToSlug($sourceDescriptor);
            if ($normalizedExisting !== '' && $normalizedDescriptor !== '' && $normalizedExisting === $normalizedDescriptor && $normalizedExisting === $candidateSlug) {
                return true;
            }

            if (
                ($sourceDescriptor === 'directory' && $normalizedExisting === $candidateSlug)
                || ($existingSourceRoot === 'directory' && $normalizedDescriptor === $candidateSlug)
            ) {
                return true;
            }
        }

        $isLegacyRoot = ! str_contains($existingSourceRoot, '/')
            && ! str_contains($existingSourceRoot, '\\')
            && ! str_contains($existingSourceRoot, ':');

        if (! $isLegacyRoot) {
            return false;
        }

        $existingTail = basename(str_replace('\\', '/', $existingSourceRoot));
        $sourceTail = basename(str_replace('\\', '/', $sourceDescriptor));

        return $existingTail !== '' && $sourceTail !== '' && $existingTail === $sourceTail;
    }

    private function resolveName(?array $manifest, string $slug): string
    {
        $name = trim((string) Arr::get($manifest, 'name', ''));

        return $name !== '' ? $name : Str::of($slug)->replace('-', ' ')->title()->value();
    }

    private function resolveDescription(?array $manifest, string $name): string
    {
        $description = trim((string) Arr::get($manifest, 'description', ''));

        return $description !== '' ? $description : "{$name} imported template";
    }

    private function resolveCategory(?array $manifest): string
    {
        $category = trim((string) Arr::get($manifest, 'category', Arr::get($manifest, 'vertical', 'general')));

        return $category !== '' ? Str::slug($category) : 'general';
    }

    private function resolveVersion(?array $manifest): string
    {
        $version = trim((string) Arr::get($manifest, 'version', '1.0.0'));

        return $version !== '' ? $version : '1.0.0';
    }

    /**
     * @return array<int,string>
     */
    private function resolveKeywords(?array $manifest): array
    {
        $keywords = Arr::get($manifest, 'keywords', []);
        if (! is_array($keywords)) {
            return [];
        }

        return collect($keywords)
            ->filter(static fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value): string => trim($value))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private function createTemplateZipFromDirectory(string $sourceDir, string $zipPath, array $metadata, bool $hasManifestFile): void
    {
        File::ensureDirectoryExists(dirname($zipPath));

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create zip archive');
        }

        $sourceDirReal = realpath($sourceDir);
        if (! is_string($sourceDirReal)) {
            $zip->close();
            throw new \RuntimeException('Invalid source directory');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDirReal, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $absolutePath = (string) $item->getPathname();
            $localName = ltrim(str_replace('\\', '/', Str::replaceFirst($sourceDirReal, '', $absolutePath)), '/');

            if (
                Str::startsWith($localName, 'node_modules/')
                || Str::startsWith($localName, '.next/')
                || Str::startsWith($localName, 'out/')
                || Str::startsWith($localName, 'dist/')
                || Str::startsWith($localName, 'build/')
                || Str::startsWith($localName, '.git/')
                || Str::startsWith($localName, '.turbo/')
                || Str::startsWith($localName, 'coverage/')
                || str_ends_with($localName, '/.DS_Store')
                || $localName === '.DS_Store'
            ) {
                continue;
            }

            $zip->addFile($absolutePath, $localName);
        }

        if (! $hasManifestFile) {
            $zip->addFromString('template.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $zip->close();
    }

    /**
     * @param  array<string,mixed>|null  $manifest
     */
    private function importThumbnailFromDirectory(string $sourceDir, string $slug, ?array $manifest): ?string
    {
        $candidates = [];

        $manifestThumbnail = trim((string) Arr::get($manifest, 'thumbnail', ''));
        if ($manifestThumbnail !== '') {
            $candidates[] = $sourceDir.'/'.ltrim($manifestThumbnail, '/');
        }

        foreach ([
            'thumbnail.png', 'thumbnail.jpg', 'thumbnail.jpeg', 'thumbnail.webp',
            'preview.png', 'preview.jpg', 'preview.jpeg', 'cover.png',
            'public/thumbnail.png', 'public/preview.png', 'public/cover.png',
            'public/assets/images/logo.png',
        ] as $candidate) {
            $candidates[] = $sourceDir.'/'.$candidate;
        }

        $thumbnailFile = collect($candidates)
            ->first(static fn (string $candidate): bool => File::exists($candidate) && is_file($candidate));

        if (! is_string($thumbnailFile)) {
            return null;
        }

        $extension = strtolower(pathinfo($thumbnailFile, PATHINFO_EXTENSION)) ?: 'png';
        $targetRelativePath = "thumbnails/{$slug}.{$extension}";
        $targetAbsolutePath = storage_path('app/public/'.$targetRelativePath);
        File::ensureDirectoryExists(dirname($targetAbsolutePath));
        File::copy($thumbnailFile, $targetAbsolutePath);

        return $targetRelativePath;
    }

    /**
     * @param  array<string,mixed>|null  $manifest
     */
    private function importThumbnailFromZip(string $zipPath, string $slug, ?array $manifest): ?string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $manifestThumbnail = trim((string) Arr::get($manifest, 'thumbnail', ''));
        $entryName = null;

        if ($manifestThumbnail !== '') {
            $entryName = $this->findZipEntryBySuffix($zip, ltrim($manifestThumbnail, '/'));
        }

        if (! is_string($entryName)) {
            $entryName = $this->findFirstThumbnailEntryInZip($zip);
        }

        if (! is_string($entryName)) {
            $zip->close();

            return null;
        }

        $contents = $zip->getFromName($entryName);
        $zip->close();

        if (! is_string($contents)) {
            return null;
        }

        $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) ?: 'png';
        $targetRelativePath = "thumbnails/{$slug}.{$extension}";
        Storage::disk('public')->put($targetRelativePath, $contents);

        return $targetRelativePath;
    }

    private function findFirstThumbnailEntryInZip(ZipArchive $zip): ?string
    {
        $keywords = ['thumbnail', 'preview', 'cover', 'screenshot', 'logo'];
        $extensions = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $base = mb_strtolower((string) pathinfo($name, PATHINFO_FILENAME));
            $ext = mb_strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($ext, $extensions, true)) {
                continue;
            }

            foreach ($keywords as $keyword) {
                if (str_contains($base, $keyword)) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function findZipEntryBySuffix(ZipArchive $zip, string $suffix): ?string
    {
        $suffix = str_replace('\\', '/', ltrim($suffix, '/'));

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if (Str::endsWith($name, $suffix)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  array<int,int>  $planIds
     * @param  array<int,string>  $keywords
     * @param  array<string,mixed>  $metadata
     */
    private function upsertTemplate(
        string $slug,
        string $name,
        string $description,
        string $category,
        string $version,
        array $keywords,
        array $metadata,
        string $zipPath,
        ?string $thumbnail,
        array $planIds,
        bool $isSystem
    ): void {
        $existing = Template::query()->where('slug', $slug)->first();

        if ($existing && $existing->getRawOriginal('zip_path') && $existing->getRawOriginal('zip_path') !== $zipPath) {
            Storage::disk('local')->delete($existing->getRawOriginal('zip_path'));
        }

        $template = Template::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description,
                'category' => $category,
                'version' => $version,
                'keywords' => $keywords,
                'is_system' => $isSystem,
                'zip_path' => $zipPath,
                'thumbnail' => $thumbnail ?? $existing?->thumbnail,
                'metadata' => $metadata,
            ]
        );

        if (! $template->is_system) {
            $template->plans()->sync($planIds);
        }
    }
}
