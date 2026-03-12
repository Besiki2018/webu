<?php

namespace Tests\Feature\Cms;

use App\Services\TemplateClassifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateClassifierServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_classifier_returns_detailed_payload_for_georgian_prompt(): void
    {
        $service = app(TemplateClassifierService::class);

        $result = $service->classifyDetailed(
            'მინდა ვეტერინარული კლინიკის საიტი ონლაინ ჯავშნებით და კონტაქტის გვერდით',
            'ka'
        );

        $this->assertSame('vet', $result['category']);
        $this->assertSame('ka', $result['locale']);
        $this->assertSame('keyword', $result['strategy']);
        $this->assertSame(TemplateClassifierService::AI_FALLBACK_PROVIDER_NOT_CONFIGURED, $result['fallback_reason']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function test_classifier_handles_english_vertical_keyword_matching(): void
    {
        $service = app(TemplateClassifierService::class);

        $result = $service->classifyDetailed(
            'Need a legal services website for a law firm with consultation booking',
            'en'
        );

        $this->assertSame('legal', $result['category']);
        $this->assertSame('en', $result['locale']);
        $this->assertSame('keyword', $result['strategy']);
        $this->assertSame(TemplateClassifierService::AI_FALLBACK_PROVIDER_NOT_CONFIGURED, $result['fallback_reason']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function test_classifier_handles_business_template_matching_with_specific_keywords(): void
    {
        $service = app(TemplateClassifierService::class);

        $result = $service->classifyDetailed(
            'Need a corporate website for a consulting company with professional services pages',
            'en'
        );

        $this->assertSame('business', $result['category']);
        $this->assertSame('en', $result['locale']);
        $this->assertSame('keyword', $result['strategy']);
        $this->assertSame(TemplateClassifierService::AI_FALLBACK_PROVIDER_NOT_CONFIGURED, $result['fallback_reason']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function test_classifier_returns_default_with_fallback_reason_when_nothing_matches(): void
    {
        $service = app(TemplateClassifierService::class);

        $result = $service->classifyDetailed(
            'Build something futuristic and abstract with no business context',
            'en'
        );

        $this->assertSame('default', $result['category']);
        $this->assertSame('default', $result['strategy']);
        $this->assertSame('en', $result['locale']);
        $this->assertSame(TemplateClassifierService::AI_FALLBACK_PROVIDER_NOT_CONFIGURED, $result['fallback_reason']);
        $this->assertLessThanOrEqual(0.3, $result['confidence']);
    }

    public function test_classifier_matches_yoga_studio_service_site_to_business_template(): void
    {
        $service = app(TemplateClassifierService::class);

        $result = $service->classifyDetailed(
            'Create a yoga studio brand website with instructor bios, membership pricing, and a contact form',
            'en'
        );

        $this->assertSame('business', $result['category']);
        $this->assertSame('en', $result['locale']);
        $this->assertSame('keyword', $result['strategy']);
        $this->assertSame(TemplateClassifierService::AI_FALLBACK_PROVIDER_NOT_CONFIGURED, $result['fallback_reason']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function test_legacy_classify_method_returns_category_only(): void
    {
        $service = app(TemplateClassifierService::class);

        $category = $service->classify('იურიდიული მომსახურების საიტი ადვოკატებისთვის');

        $this->assertSame('legal', $category);
    }
}
