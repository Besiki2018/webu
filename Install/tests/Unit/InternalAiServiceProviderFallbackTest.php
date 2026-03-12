<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use App\Services\InternalAiService;
use Tests\TestCase;

class InternalAiServiceProviderFallbackTest extends TestCase
{
    public function test_it_falls_back_to_default_ai_provider_when_internal_provider_is_not_set(): void
    {
        $defaultProvider = new AiProvider([
            'name' => 'OpenAI',
            'type' => AiProvider::TYPE_OPENAI,
            'status' => 'active',
        ]);
        $defaultProvider->id = 42;

        $service = new class($defaultProvider) extends InternalAiService
        {
            public function __construct(private readonly AiProvider $defaultProvider) {}

            protected function preferredProviderIds(): array
            {
                return [42];
            }

            protected function findActiveProviderById(mixed $providerId): ?AiProvider
            {
                return (int) $providerId === 42 ? $this->defaultProvider : null;
            }

            protected function findFirstActiveProvider(): ?AiProvider
            {
                return null;
            }
        };

        $resolved = $service->getProvider();

        $this->assertNotNull($resolved);
        $this->assertSame(42, $resolved->id);
    }

    public function test_it_falls_back_to_first_active_provider_when_preferred_ids_do_not_resolve(): void
    {
        $activeProvider = new AiProvider([
            'name' => 'Anthropic',
            'type' => AiProvider::TYPE_ANTHROPIC,
            'status' => 'active',
        ]);
        $activeProvider->id = 7;

        $service = new class($activeProvider) extends InternalAiService
        {
            public function __construct(private readonly AiProvider $activeProvider) {}

            protected function preferredProviderIds(): array
            {
                return [999, 1000];
            }

            protected function findActiveProviderById(mixed $providerId): ?AiProvider
            {
                return null;
            }

            protected function findFirstActiveProvider(): ?AiProvider
            {
                return $this->activeProvider;
            }
        };

        $resolved = $service->getProvider();

        $this->assertNotNull($resolved);
        $this->assertSame(7, $resolved->id);
    }
}
