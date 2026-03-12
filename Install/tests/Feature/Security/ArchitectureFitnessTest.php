<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class ArchitectureFitnessTest extends TestCase
{
    public function test_cms_context_has_no_forbidden_cross_module_imports(): void
    {
        $this->assertNoForbiddenImports(
            app_path('Cms'),
            ['App\\Ecommerce\\', 'App\\Booking\\']
        );
    }

    public function test_ecommerce_context_has_no_forbidden_cross_module_imports(): void
    {
        $this->assertNoForbiddenImports(
            app_path('Ecommerce'),
            ['App\\Booking\\', 'App\\Cms\\Repositories\\EloquentCmsRepository']
        );
    }

    public function test_booking_context_has_no_forbidden_cross_module_imports(): void
    {
        $this->assertNoForbiddenImports(
            app_path('Booking'),
            ['App\\Ecommerce\\', 'App\\Cms\\Repositories\\EloquentCmsRepository']
        );
    }

    public function test_module_contexts_do_not_bypass_global_scopes_directly(): void
    {
        foreach ([app_path('Cms'), app_path('Ecommerce'), app_path('Booking')] as $directory) {
            foreach ($this->phpFiles($directory) as $filePath) {
                $contents = file_get_contents($filePath);
                $this->assertIsString($contents);
                $this->assertStringNotContainsString('withoutGlobalScope(', $contents, $filePath);
                $this->assertStringNotContainsString('withoutGlobalScopes(', $contents, $filePath);
            }
        }
    }

    /**
     * @param  array<int, string>  $forbiddenNamespaces
     */
    private function assertNoForbiddenImports(string $directory, array $forbiddenNamespaces): void
    {
        foreach ($this->phpFiles($directory) as $filePath) {
            $contents = file_get_contents($filePath);
            $this->assertIsString($contents);

            foreach ($forbiddenNamespaces as $forbidden) {
                $this->assertStringNotContainsString("use {$forbidden}", $contents, $filePath);
                $this->assertStringNotContainsString($forbidden, $contents, $filePath);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $item) {
            if (! $item instanceof \SplFileInfo) {
                continue;
            }

            if ($item->isDir()) {
                continue;
            }

            if (strtolower($item->getExtension()) !== 'php') {
                continue;
            }

            $files[] = $item->getPathname();
        }

        return $files;
    }
}
