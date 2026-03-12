<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalBindingNamespaceCompatibilityP5F5Test extends TestCase
{
    public function test_p5_f5_03_universal_binding_namespace_compatibility_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_BINDING_NAMESPACE_COMPATIBILITY_P5_F5_03.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $resolver = File::get(base_path('app/Services/CmsCanonicalBindingResolver.php'));
        $validator = File::get(base_path('app/Services/CmsBindingExpressionValidator.php'));
        $cms = File::get(base_path('resources/js/Pages/Project/Cms.tsx'));
        $resolverTest = File::get(base_path('tests/Unit/CmsCanonicalBindingResolverTest.php'));
        $validatorTest = File::get(base_path('tests/Unit/CmsBindingExpressionValidatorTest.php'));
        $frontendContract = File::get(base_path('resources/js/Pages/Project/__tests__/CmsUniversalBindingNamespaceCompatibility.contract.test.ts'));
        $roadmap = File::get(base_path('../PROJECT_ROADMAP_TASKS_KA.md'));

        $this->assertStringContainsString('P5-F5-03', $doc);
        $this->assertStringContainsString('CmsCanonicalBindingResolver', $doc);
        $this->assertStringContainsString('CmsBindingExpressionValidator', $doc);
        $this->assertStringContainsString('CanonicalControlGroup', $doc);
        $this->assertStringContainsString('content.properties', $doc);
        $this->assertStringContainsString('content.rooms', $doc);

        $this->assertStringContainsString("'booking'", $resolver);
        $this->assertStringContainsString("'content'", $resolver);
        $this->assertStringContainsString("Str::startsWith(\$canonicalPath, 'booking.services')", $resolver);
        $this->assertStringContainsString("Str::startsWith(\$canonicalPath, 'content.')", $resolver);

        $this->assertStringContainsString('private function routeBindingRules(): array', $validator);
        $this->assertStringContainsString('webu_book_slots_01', $validator);
        $this->assertStringContainsString('webu_portfolio_project_hero_01', $validator);
        $this->assertStringContainsString('webu_realestate_property_hero_01', $validator);
        $this->assertStringContainsString('webu_hotel_room_detail_01', $validator);
        $this->assertStringContainsString('missing_route_service_id_binding', $validator);
        $this->assertStringContainsString('invalid_route_room_slug_binding', $validator);

        $this->assertStringContainsString('CanonicalControlGroup', $cms);
        $this->assertStringContainsString("service_id: { type: 'string', title: 'Service ID (Binding Hint)', default: '{{route.params.service_id}}' }", $cms);
        $this->assertStringContainsString("property_slug: { type: 'string', title: 'Property Slug (Binding Hint)', default: '{{route.params.slug}}' }", $cms);
        $this->assertStringContainsString("room_slug: { type: 'string', title: 'Room Slug (Binding Hint)', default: '{{route.params.slug}}' }", $cms);

        $this->assertStringContainsString('test_it_marks_deferred_semantic_bindings_without_throwing', $resolverTest);
        $this->assertStringContainsString('test_it_accepts_canonical_route_bindings_for_universal_vertical_detail_components', $validatorTest);
        $this->assertStringContainsString('test_it_warns_when_universal_vertical_components_use_missing_or_invalid_route_bindings', $validatorTest);
        $this->assertStringContainsString('CMS universal binding namespace compatibility contracts (P5-F5-03)', $frontendContract);

        $this->assertStringContainsString('- ✅ Maintain same Content/Style/Advanced + bindings model', $roadmap);
        $this->assertStringContainsString("`P5-F5-03` (✅ `DONE`) Universal binding namespace compatibility checks (booking/content/properties/rooms/etc.).", $roadmap);
    }
}

