<?php

namespace Tests\Unit;

use App\Services\CmsDynamicControlHookService;
use Tests\TestCase;

class CmsDynamicControlHookServiceTest extends TestCase
{
    public function test_it_builds_dynamic_hooks_for_text_image_and_link_fields(): void
    {
        $service = new CmsDynamicControlHookService;

        $hooks = $service->buildHooks(
            ['headline', 'image_url', 'primary_cta', 'search_placeholder', 'headline_typography', 'icon_class'],
            [
                'properties' => [
                    'headline' => ['type' => 'string'],
                    'image_url' => ['type' => 'string', 'format' => 'uri'],
                    'primary_cta' => ['type' => 'object'],
                    'search_placeholder' => ['type' => 'string'],
                    'headline_typography' => ['type' => 'string'],
                    'icon_class' => ['type' => 'string'],
                ],
            ]
        );

        $this->assertSame(1, $hooks['version']);
        $this->assertArrayHasKey('fields', $hooks);

        $fields = $hooks['fields'];
        $this->assertSame('text', $fields['headline']['kind']);
        $this->assertSame('image', $fields['image_url']['kind']);
        $this->assertSame('link', $fields['primary_cta']['kind']);
        $this->assertSame('text', $fields['search_placeholder']['kind']);

        $this->assertArrayNotHasKey('headline_typography', $fields);
        $this->assertArrayNotHasKey('icon_class', $fields);

        $this->assertContains('site', $fields['headline']['binding_namespaces']);
        $this->assertContains('global', $fields['image_url']['binding_namespaces']);
        $this->assertContains('route', $fields['primary_cta']['binding_namespaces']);
    }
}
