<?php

namespace Tests\Unit\Services;

use App\Services\WebuVariantResolver;
use Tests\TestCase;

class WebuVariantResolverTest extends TestCase
{
    public function test_resolve_returns_default_when_variant_empty(): void
    {
        $resolver = new WebuVariantResolver();
        $this->assertSame('header-1', $resolver->resolve('header', null));
        $this->assertSame('header-1', $resolver->resolve('header', ''));
    }

    public function test_resolve_returns_allowed_variant_when_given(): void
    {
        $resolver = new WebuVariantResolver();
        $this->assertSame('header-2', $resolver->resolve('header', 'header-2'));
        $this->assertSame('header-3', $resolver->resolve('header', 'header-3'));
    }

    public function test_resolve_fallback_to_default_when_unknown_variant(): void
    {
        $resolver = new WebuVariantResolver();
        $this->assertSame('header-1', $resolver->resolve('header', 'invalid'));
        $this->assertSame('hero-1', $resolver->resolve('hero', 'unknown'));
    }

    public function test_allowed_variants_returns_list(): void
    {
        $resolver = new WebuVariantResolver();
        $header = $resolver->allowedVariants('header');
        $this->assertContains('header-1', $header);
        $this->assertContains('header-2', $header);
        $this->assertContains('header-3', $header);
    }

    public function test_default_variant_returns_first_allowed(): void
    {
        $resolver = new WebuVariantResolver();
        $this->assertSame('header-1', $resolver->defaultVariant('header'));
        $this->assertSame('hero-1', $resolver->defaultVariant('hero'));
    }
}
