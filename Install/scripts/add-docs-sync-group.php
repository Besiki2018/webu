#!/usr/bin/env php
<?php
/**
 * One-off: add @group docs-sync to test classes that reference docs/ or PROJECT_.
 * Skip CmsAiPageGenerationServiceTest (has method-level group only).
 * Run from Install: php scripts/add-docs-sync-group.php
 */

$testsDir = __DIR__ . '/../tests';
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsDir, RecursiveDirectoryIterator::SKIP_DOTS)
);
$count = 0;
foreach ($iter as $path) {
    $file = $path->getPathname();
    if (!str_ends_with($file, '.php')) {
        continue;
    }
    $content = file_get_contents($file);
    if (strpos($content, '@group docs-sync') !== false) {
        continue;
    }
    if (strpos($content, "base_path('docs/") === false && strpos($content, "base_path('../PROJECT_") === false) {
        continue;
    }
    if (strpos($file, 'CmsAiPageGenerationServiceTest.php') !== false) {
        continue;
    }
    $pattern = '/\n(use [^;]+;\s*\n)+\n(class\s+\w+\s+extends\s+TestCase)\s*\{/s';
    if (!preg_match($pattern, $content, $m)) {
        continue;
    }
    $replacement = "\n\$1\n\n/** @group docs-sync */\n\$2\n{";
    $newContent = preg_replace($pattern, $replacement, $content, 1);
    if ($newContent === $content || $newContent === null) {
        continue;
    }
    file_put_contents($file, $newContent);
    $count++;
    echo "Added @group docs-sync: " . str_replace($testsDir . '/', '', $file) . "\n";
}
echo "Done. Updated {$count} files.\n";
