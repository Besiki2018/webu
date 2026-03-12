<?php

namespace Tests\Unit\Services;

use App\Services\WebuCmsResolver;
use Tests\TestCase;

/**
 * Unit tests for WebuCmsResolver return structures when site is null.
 * Uses Laravel TestCase so app()->getLocale() works in getSiteSettings.
 */
class WebuCmsResolverTest extends TestCase
{
    public function test_get_site_settings_returns_expected_keys_when_no_site(): void
    {
        $resolver = app(WebuCmsResolver::class);
        $settings = $resolver->getSiteSettings(null);
        $this->assertArrayHasKey('logo_text', $settings);
        $this->assertArrayHasKey('brand', $settings);
        $this->assertArrayHasKey('locale', $settings);
        $this->assertSame('Store', $settings['logo_text']);
    }

    public function test_get_footer_data_returns_menus_and_layout_when_no_site(): void
    {
        $resolver = app(WebuCmsResolver::class);
        $footer = $resolver->getFooterData(null, null);
        $this->assertArrayHasKey('menus', $footer);
        $this->assertArrayHasKey('layout', $footer);
        $this->assertIsArray($footer['menus']);
        $this->assertSame([], $footer['menus']);
        $this->assertArrayHasKey('contact_address', $footer['layout']);
    }

    public function test_get_testimonials_returns_array_when_no_site(): void
    {
        $resolver = app(WebuCmsResolver::class);
        $items = $resolver->getTestimonials(null);
        $this->assertIsArray($items);
        $this->assertGreaterThanOrEqual(1, count($items));
        $first = $items[0];
        $this->assertArrayHasKey('user_name', $first);
        $this->assertArrayHasKey('text', $first);
    }

    public function test_get_features_returns_array_when_no_site(): void
    {
        $resolver = app(WebuCmsResolver::class);
        $items = $resolver->getFeatures(null);
        $this->assertIsArray($items);
        $this->assertGreaterThanOrEqual(1, count($items));
        $this->assertArrayHasKey('title', $items[0]);
    }

    public function test_get_faq_returns_array_when_no_site(): void
    {
        $resolver = app(WebuCmsResolver::class);
        $items = $resolver->getFaq(null);
        $this->assertIsArray($items);
        $this->assertGreaterThanOrEqual(1, count($items));
        $this->assertArrayHasKey('question', $items[0]);
        $this->assertArrayHasKey('answer', $items[0]);
    }

    public function test_get_blog_posts_returns_array_when_no_site(): void
    {
        $resolver = app(WebuCmsResolver::class);
        $posts = $resolver->getBlogPosts(null, 5);
        $this->assertIsArray($posts);
        if (count($posts) > 0) {
            $this->assertArrayHasKey('id', $posts[0]);
            $this->assertArrayHasKey('title', $posts[0]);
        }
    }

    public function test_get_announcement_returns_array_or_null_when_no_site(): void
    {
        $resolver = app(WebuCmsResolver::class);
        $ann = $resolver->getAnnouncement(null);
        $this->assertTrue($ann === null || (is_array($ann) && isset($ann['text'])));
        if (is_array($ann)) {
            $this->assertArrayHasKey('text', $ann);
        }
    }

    public function test_get_stats_returns_array_when_no_site(): void
    {
        $resolver = app(WebuCmsResolver::class);
        $items = $resolver->getStats(null);
        $this->assertIsArray($items);
        $this->assertGreaterThanOrEqual(1, count($items));
        $this->assertArrayHasKey('label', $items[0]);
        $this->assertArrayHasKey('value', $items[0]);
    }

    public function test_get_team_returns_array_when_no_site(): void
    {
        $resolver = app(WebuCmsResolver::class);
        $members = $resolver->getTeam(null);
        $this->assertIsArray($members);
        $this->assertGreaterThanOrEqual(1, count($members));
        $this->assertArrayHasKey('name', $members[0]);
    }
}
