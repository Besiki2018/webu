<?php

namespace Tests\Unit;

use App\Services\CmsCanonicalBindingResolver;
use Tests\TestCase;

class CmsCanonicalBindingResolverTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function samplePayload(): array
    {
        return [
            'project_id' => 'proj_1',
            'site_id' => 'site_1',
            'resolved_domain' => 'demo.example.test',
            'slug' => 'home',
            'requested_slug' => 'home',
            'locale' => 'ka',
            'route' => [
                'slug' => 'home',
                'requested_slug' => 'home',
                'locale' => 'ka',
                'domain' => 'demo.example.test',
                'params' => [
                    'slug' => 'premium-dog-snack',
                    'product_slug' => 'premium-dog-snack',
                    'product_id' => '42',
                ],
            ],
            'site' => [
                'id' => 'site_1',
                'name' => 'Webu Shop',
                'locale' => 'ka',
                'theme_settings' => [
                    'layout' => [
                        'header_menu_key' => 'header',
                    ],
                ],
            ],
            'typography' => [
                'font_stack' => '"Noto Sans Georgian", sans-serif',
                'heading_font_stack' => '"BPG Nino", serif',
            ],
            'global_settings' => [
                'logo_asset_url' => '/storage/logo.png',
                'contact_json' => [
                    'email' => 'demo@example.test',
                    'phone' => '+995 555 00 11 22',
                    'address' => 'Tbilisi',
                ],
                'social_links_json' => [
                    ['label' => 'Facebook', 'url' => 'https://facebook.com/example'],
                ],
                'analytics_ids_json' => [
                    'ga4' => 'G-TEST123',
                ],
            ],
            'menus' => [
                'header' => [
                    'key' => 'header',
                    'items_json' => [
                        ['label' => 'მთავარი', 'url' => '/'],
                        ['label' => 'მაღაზია', 'url' => '/shop'],
                    ],
                ],
                'footer' => [
                    'key' => 'footer',
                    'items_json' => [
                        ['label' => 'კონტაქტი', 'url' => '/contact'],
                    ],
                ],
            ],
            'page' => [
                'id' => 10,
                'slug' => 'home',
                'title' => 'მთავარი',
                'seo_title' => 'Home SEO',
                'seo_description' => 'Home description',
            ],
            'revision' => [
                'content_json' => [
                    'sections' => [
                        [
                            'type' => 'webu_home_hero_01',
                            'props' => [
                                'headline' => 'Welcome',
                                'primary_cta' => ['label' => 'Shop', 'url' => '/shop'],
                            ],
                        ],
                    ],
                ],
            ],
            'meta' => [
                'endpoints' => [
                    'ecommerce_products' => '/public/sites/site_1/ecommerce/products',
                    'ecommerce_checkout' => '/public/sites/site_1/ecommerce/carts/{cart_id}/checkout',
                    'booking_services' => '/public/sites/site_1/booking/services',
                ],
            ],
        ];
    }

    public function test_it_resolves_canonical_paths_against_runtime_payload(): void
    {
        $resolver = new CmsCanonicalBindingResolver;
        $payload = $this->samplePayload();

        $this->assertSame('Webu Shop', $resolver->resolve($payload, '{{site.name}}'));
        $this->assertSame('/storage/logo.png', $resolver->resolve($payload, '{{global.logo.url}}'));
        $this->assertSame('+995 555 00 11 22', $resolver->resolve($payload, '{{global.contact.phone}}'));
        $this->assertSame('მთავარი', $resolver->resolve($payload, '{{menu.header.items[0].label}}'));
        $this->assertSame('Home SEO', $resolver->resolve($payload, '{{page.seo.title}}'));
        $this->assertSame('home', $resolver->resolve($payload, '{{route.slug}}'));
        $this->assertSame('premium-dog-snack', $resolver->resolve($payload, '{{route.params.slug}}'));
        $this->assertSame('Welcome', $resolver->resolve($payload, '{{page.sections[0].props.headline}}'));
        $this->assertSame(
            '/public/sites/site_1/ecommerce/products',
            $resolver->resolve($payload, '{{ecommerce.endpoints.products}}')
        );
    }

    public function test_it_normalizes_legacy_paths_to_canonical_expressions(): void
    {
        $resolver = new CmsCanonicalBindingResolver;

        $this->assertSame('{{project.id}}', $resolver->normalizeExpression('project_id'));
        $this->assertSame('{{route.slug}}', $resolver->normalizeExpression('slug'));
        $this->assertSame('{{route.params.slug}}', $resolver->normalizeExpression('route.params.slug'));
        $this->assertSame('{{global.contact.phone}}', $resolver->normalizeExpression('global_settings.contact_json.phone'));
        $this->assertSame('{{menu.header.items}}', $resolver->normalizeExpression('menus.header.items_json'));
        $this->assertSame('{{page.sections[0].props.headline}}', $resolver->normalizeExpression('revision.content_json.sections[0].props.headline'));
        $this->assertSame('{{ecommerce.endpoints.products}}', $resolver->normalizeExpression('meta.endpoints.ecommerce_products'));
    }

    public function test_it_prefers_top_level_typography_alias_for_site_typography_namespace(): void
    {
        $resolver = new CmsCanonicalBindingResolver;

        $this->assertSame(
            '"Noto Sans Georgian", sans-serif',
            $resolver->resolve($this->samplePayload(), '{{site.typography.font_stack}}')
        );
        $this->assertSame(
            '"BPG Nino", serif',
            $resolver->resolve($this->samplePayload(), '{{typography.heading_font_stack}}')
        );
    }

    public function test_it_returns_safe_fallback_for_invalid_or_unresolved_paths(): void
    {
        $resolver = new CmsCanonicalBindingResolver;
        $payload = $this->samplePayload();

        $invalid = $resolver->inspect($payload, '{{ site.name + 1 }}', 'fallback');
        $this->assertFalse($invalid['ok']);
        $this->assertSame('invalid_syntax', $invalid['error']);
        $this->assertSame('fallback', $invalid['value']);

        $missing = $resolver->inspect($payload, '{{global.contact.fax}}', 'N/A');
        $this->assertTrue($missing['ok']);
        $this->assertFalse($missing['resolved']);
        $this->assertSame('unresolved_path', $missing['error']);
        $this->assertSame('N/A', $missing['value']);

        $unsupported = $resolver->inspect($payload, '{{unknown.value}}', 'x');
        $this->assertFalse($unsupported['ok']);
        $this->assertSame('unsupported_namespace', $unsupported['error']);
        $this->assertSame('x', $unsupported['value']);
    }

    public function test_it_marks_deferred_semantic_bindings_without_throwing(): void
    {
        $resolver = new CmsCanonicalBindingResolver;
        $cases = [
            ['{{ecommerce.products}}', []],
            ['{{booking.services}}', []],
            ['{{booking.slots}}', []],
            ['{{content.properties}}', []],
            ['{{content.rooms}}', []],
            ['{{content.projects}}', []],
        ];

        foreach ($cases as [$expression, $fallback]) {
            $result = $resolver->inspect($this->samplePayload(), $expression, $fallback);

            $this->assertTrue($result['ok'], $expression);
            $this->assertTrue($result['deferred'], $expression);
            $this->assertFalse($result['resolved'], $expression);
            $this->assertNull($result['error'], $expression);
            $this->assertSame($fallback, $result['value'], $expression);
        }
    }
}
