<?php

namespace Tests\Unit;

use App\Services\AiTools\SectionNameNormalizer;
use Tests\TestCase;

class SectionNameNormalizerTest extends TestCase
{
    public function test_it_preserves_existing_pascal_case_section_names(): void
    {
        $normalizer = app(SectionNameNormalizer::class);

        $this->assertSame('TestimonialsSection', $normalizer->normalize('TestimonialsSection'));
        $this->assertSame('PricingSection', $normalizer->normalize('pricing'));
        $this->assertSame('CTASection', $normalizer->normalize('CTA section'));
        $this->assertSame('TeamSection', $normalizer->normalize('team_section'));
    }
}
