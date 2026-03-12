<?php

namespace Tests\Unit;

use App\Services\ChatIntentToPatchService;
use Tests\TestCase;

class ChatIntentToPatchServiceTest extends TestCase
{
    public function test_it_parses_georgian_theme_intents(): void
    {
        $service = app(ChatIntentToPatchService::class);

        $result = $service->parse('დიზაინი გაამუქე და უფრო თანამედროვე გახადე');

        $this->assertSame('theme_preset', $result['type']);
        $this->assertSame('dark_modern', $result['patch']['theme_preset'] ?? null);
    }

    public function test_it_parses_georgian_best_sellers_section_intent(): void
    {
        $service = app(ChatIntentToPatchService::class);

        $result = $service->parse('დაამატე ბესტსელერების სექცია მთავარ გვერდზე');

        $this->assertSame('add_section', $result['type']);
        $this->assertSame('home', $result['patch']['page_slug'] ?? null);
        $this->assertSame('webu_ecom_product_grid_01', $result['patch']['section']['key'] ?? null);
    }
}
