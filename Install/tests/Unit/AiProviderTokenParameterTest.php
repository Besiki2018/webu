<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use Tests\TestCase;

class AiProviderTokenParameterTest extends TestCase
{
    public function test_it_uses_max_completion_tokens_for_openai_gpt5_models(): void
    {
        $this->assertSame(
            'max_completion_tokens',
            AiProvider::resolveTokenLimitParameter(AiProvider::TYPE_OPENAI, 'gpt-5.2')
        );
    }

    public function test_it_uses_max_tokens_for_non_gpt5_or_non_openai_models(): void
    {
        $this->assertSame(
            'max_tokens',
            AiProvider::resolveTokenLimitParameter(AiProvider::TYPE_OPENAI, 'gpt-4o-mini')
        );

        $this->assertSame(
            'max_tokens',
            AiProvider::resolveTokenLimitParameter(AiProvider::TYPE_GROK, 'grok-4-1-fast-reasoning')
        );
    }
}

