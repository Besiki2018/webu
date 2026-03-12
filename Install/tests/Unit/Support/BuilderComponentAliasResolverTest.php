<?php

namespace Tests\Unit\Support;

use App\Support\BuilderComponentAliasResolver;
use Tests\TestCase;

class BuilderComponentAliasResolverTest extends TestCase
{
    public function test_it_normalizes_common_ai_section_aliases_to_canonical_builder_components(): void
    {
        $this->assertSame('webu_general_features_01', BuilderComponentAliasResolver::normalize('features'));
        $this->assertSame('webu_general_features_01', BuilderComponentAliasResolver::normalize('pricing'));
        $this->assertSame('webu_general_form_wrapper_01', BuilderComponentAliasResolver::normalize('contact_form'));
        $this->assertSame('webu_general_grid_01', BuilderComponentAliasResolver::normalize('gallery'));
        $this->assertSame('webu_general_testimonials_01', BuilderComponentAliasResolver::normalize('testimonials'));
    }

    public function test_registered_component_ids_include_runtime_builder_components_used_by_ai(): void
    {
        $registered = BuilderComponentAliasResolver::registeredComponentIds();

        $this->assertContains('webu_general_features_01', $registered);
        $this->assertContains('webu_general_cards_01', $registered);
        $this->assertContains('webu_general_grid_01', $registered);
        $this->assertContains('webu_general_testimonials_01', $registered);
    }
}
