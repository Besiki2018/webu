<?php

namespace Tests\Unit;

use App\Services\WebuCodex\PathRules;
use Tests\TestCase;

class PathRulesTest extends TestCase
{
    public function test_workspace_source_entrypoints_and_helpers_are_allowed(): void
    {
        $this->assertTrue(PathRules::isAllowed('src/App.tsx'));
        $this->assertTrue(PathRules::isAllowed('src/main.tsx'));
        $this->assertTrue(PathRules::isAllowed('src/hooks/useStore.ts'));
        $this->assertTrue(PathRules::isAllowed('src/lib/formatPrice.ts'));
        $this->assertTrue(PathRules::isAllowed('src/theme/tokens.css'));
        $this->assertTrue(PathRules::isAllowed('public/robots.txt'));
    }

    public function test_synthetic_or_forbidden_workspace_paths_are_rejected(): void
    {
        $this->assertFalse(PathRules::isAllowed('src/__generated_pages__/home/Page.tsx'));
        $this->assertFalse(PathRules::isAllowed('derived-preview/home/Page.tsx'));
        $this->assertFalse(PathRules::isAllowed('.webu/index.json'));
        $this->assertFalse(PathRules::isAllowed('node_modules/react/index.js'));
    }
}
