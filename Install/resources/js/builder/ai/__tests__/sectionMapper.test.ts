import {
  mapBlocksToSectionPlan,
  sectionPlanFromLayoutResult,
  type MappableBlock,
} from '../sectionMapper';
import type { DetectedLayoutBlock } from '../layoutDetector';
import { hasEntry, DEFAULT_GENERIC_SECTION_REGISTRY_ID } from '../../componentRegistry';

describe('sectionMapper', () => {
  describe('mapBlocksToSectionPlan', () => {
    it('maps header, hero, features, cta, footer to registry component keys', () => {
      const blocks: DetectedLayoutBlock[] = [
        { type: 'header', position: 'top' },
        { type: 'hero', position: 'top-section' },
        { type: 'features', position: 'middle' },
        { type: 'cta', position: 'bottom' },
        { type: 'footer', position: 'end' },
      ];
      const result = mapBlocksToSectionPlan({ blocks });
      expect(result.sections).toHaveLength(5);
      expect(result.sections[0]).toMatchObject({ componentKey: 'webu_header_01', variant: 'header-1' });
      expect(result.sections[1]).toMatchObject({ componentKey: 'webu_general_hero_01' });
      expect(result.sections[2]).toMatchObject({ componentKey: 'webu_general_features_01' });
      expect(result.sections[3]).toMatchObject({ componentKey: 'webu_general_cta_01' });
      expect(result.sections[4]).toMatchObject({ componentKey: 'webu_footer_01', variant: 'footer-1' });
    });

    it('every section has a componentKey that exists in registry', () => {
      const blocks: DetectedLayoutBlock[] = [
        { type: 'header', position: 'top' },
        { type: 'hero', position: 'top-section' },
        { type: 'testimonials', position: 'middle' },
        { type: 'cta', position: 'bottom' },
        { type: 'footer', position: 'end' },
      ];
      const result = mapBlocksToSectionPlan({ blocks });
      result.sections.forEach((s) => {
        expect(hasEntry(s.componentKey)).toBe(true);
        expect(s.componentKey).toBeDefined();
      });
    });

    it('3-column block in middle maps to features when type is generic', () => {
      const blocks: MappableBlock[] = [
        { type: 'header', position: 'top' },
        { type: 'features', position: 'middle', columnCount: 3 },
        { type: 'footer', position: 'end' },
      ];
      const result = mapBlocksToSectionPlan({ blocks });
      expect(result.sections[1]!.componentKey).toBe('webu_general_features_01');
    });

    it('logo grid hint maps to cards component', () => {
      const blocks: MappableBlock[] = [
        { type: 'header', position: 'top' },
        { type: 'cards', position: 'middle', isLogoGrid: true },
        { type: 'footer', position: 'end' },
      ];
      const result = mapBlocksToSectionPlan({ blocks });
      expect(result.sections[1]!.componentKey).toBe('webu_general_cards_01');
    });

    it('hasButton + position bottom maps to CTA', () => {
      const blocks: MappableBlock[] = [
        { type: 'features', position: 'bottom', hasButton: true },
        { type: 'footer', position: 'end' },
      ];
      const result = mapBlocksToSectionPlan({ blocks });
      expect(result.sections[0]!.componentKey).toBe('webu_general_cta_01');
    });

    it('hasImageAndText + top-section maps to hero', () => {
      const blocks: MappableBlock[] = [
        { type: 'header', position: 'top' },
        { type: 'features', position: 'top-section', hasImageAndText: true },
        { type: 'footer', position: 'end' },
      ];
      const result = mapBlocksToSectionPlan({ blocks });
      expect(result.sections[1]!.componentKey).toBe('webu_general_hero_01');
    });

    it('preferredStyle modern applies variant overrides', () => {
      const blocks: DetectedLayoutBlock[] = [
        { type: 'hero', position: 'top-section' },
        { type: 'features', position: 'middle' },
      ];
      const result = mapBlocksToSectionPlan({ blocks, preferredStyle: 'modern' });
      expect(result.sections[0]!.variant).toBe('hero-2');
      expect(result.sections[1]!.variant).toBe('features-2');
    });

    it('testimonials and grid types map to cards and grid', () => {
      const blocks: DetectedLayoutBlock[] = [
        { type: 'testimonials', position: 'middle' },
        { type: 'grid', position: 'middle' },
      ];
      const result = mapBlocksToSectionPlan({ blocks });
      expect(result.sections[0]!.componentKey).toBe('webu_general_cards_01');
      expect(result.sections[1]!.componentKey).toBe('webu_general_grid_01');
    });
  });

  describe('Part 11 — unmapped block falls back to generic section', () => {
    it('block type not in slug map resolves to generic section (GenericSection)', () => {
      const blocks: MappableBlock[] = [
        { type: 'header', position: 'top' },
        { type: 'unknownBlock' as DetectedLayoutBlock['type'], position: 'middle' },
        { type: 'footer', position: 'end' },
      ];
      const result = mapBlocksToSectionPlan({ blocks });
      expect(result.sections).toHaveLength(3);
      expect(result.sections[0]!.componentKey).toBe('webu_header_01');
      expect(result.sections[1]!.componentKey).toBe(DEFAULT_GENERIC_SECTION_REGISTRY_ID);
      expect(result.sections[2]!.componentKey).toBe('webu_footer_01');
    });
  });

  describe('sectionPlanFromLayoutResult', () => {
    it('builds section plan from layout detector result', () => {
      const layoutResult = {
        blocks: [
          { type: 'header' as const, position: 'top' as const },
          { type: 'hero' as const, position: 'top-section' as const },
          { type: 'features' as const, position: 'middle' as const },
          { type: 'cta' as const, position: 'bottom' as const },
          { type: 'footer' as const, position: 'end' as const },
        ],
      };
      const result = sectionPlanFromLayoutResult(layoutResult, { preferredStyle: 'minimal' });
      expect(result.sections).toHaveLength(5);
      expect(result.sections[0]!.componentKey).toBe('webu_header_01');
      expect(result.sections[4]!.componentKey).toBe('webu_footer_01');
    });
  });
});
