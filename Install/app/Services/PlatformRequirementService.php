<?php

namespace App\Services;

class PlatformRequirementService
{
    public const MIN_PHP_VERSION = '8.4.0';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $composerJson = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $composerLock = null;

    /**
     * @return array{minimum: string, current: string, ok: bool}
     */
    public function phpVersionStatus(): array
    {
        return [
            'minimum' => self::MIN_PHP_VERSION,
            'current' => PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>='),
        ];
    }

    /**
     * @return array{extension: string, loaded: bool, required: bool, required_by: array<int, string>}
     */
    public function extensionStatus(string $extension): array
    {
        $normalized = $this->normalizeExtensionName($extension);
        $requiredBy = $this->extensionRequiredBy($normalized);

        return [
            'extension' => $normalized,
            'loaded' => $this->extensionLoaded($normalized),
            'required' => $requiredBy !== [],
            'required_by' => $requiredBy,
        ];
    }

    public function extensionLoaded(string $extension): bool
    {
        $normalized = $this->normalizeExtensionName($extension);
        $runtimeName = str_starts_with($normalized, 'ext-')
            ? substr($normalized, 4)
            : $normalized;

        return extension_loaded($runtimeName);
    }

    /**
     * @return array<int, string>
     */
    public function extensionRequiredBy(string $extension): array
    {
        $normalized = $this->normalizeExtensionName($extension);
        $requiredBy = [];
        $composerJson = $this->readComposerJson();

        foreach (['require', 'require-dev'] as $section) {
            $requirements = $composerJson[$section] ?? [];
            if (is_array($requirements) && isset($requirements[$normalized])) {
                $requiredBy[] = sprintf(
                    'root composer.json (%s: %s)',
                    $section,
                    (string) $requirements[$normalized]
                );
            }
        }

        foreach ($this->lockPackages() as $package) {
            if (! is_array($package)) {
                continue;
            }

            $name = (string) ($package['name'] ?? 'unknown/package');
            $requirements = $package['require'] ?? [];

            if (is_array($requirements) && isset($requirements[$normalized])) {
                $requiredBy[] = sprintf(
                    '%s (%s)',
                    $name,
                    (string) $requirements[$normalized]
                );
            }
        }

        $requiredBy = array_values(array_unique($requiredBy));
        sort($requiredBy);

        return $requiredBy;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lockPackages(): array
    {
        $composerLock = $this->readComposerLock();
        $packages = [];

        foreach (['packages', 'packages-dev'] as $section) {
            $sectionPackages = $composerLock[$section] ?? [];
            if (is_array($sectionPackages)) {
                $packages = [...$packages, ...$sectionPackages];
            }
        }

        return $packages;
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerJson(): array
    {
        if (is_array($this->composerJson)) {
            return $this->composerJson;
        }

        $path = base_path('composer.json');
        $this->composerJson = $this->readJsonFile($path);

        return $this->composerJson;
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerLock(): array
    {
        if (is_array($this->composerLock)) {
            return $this->composerLock;
        }

        $path = base_path('composer.lock');
        $this->composerLock = $this->readJsonFile($path);

        return $this->composerLock;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeExtensionName(string $extension): string
    {
        $normalized = strtolower(trim($extension));
        if ($normalized === '') {
            return 'ext-unknown';
        }

        if (! str_starts_with($normalized, 'ext-')) {
            $normalized = 'ext-'.$normalized;
        }

        return $normalized;
    }
}
