import {
  selectVariant,
  applyVariantSelection,
  AVAILABLE_VARIANTS_BY_COMPONENT,
  type ComponentSelectionContext,
} from '../componentSelector';

describe('componentSelector', () => {
  const baseContext: ComponentSelectionContext = {
    projectType: 'ecommerce',
    tone: null,
    industry: null,
  };

  describe('AVAILABLE_VARIANTS_BY_COMPONENT', () => {
    it('includes hero variants hero-1 through hero-7', () => {
      const hero = AVAILABLE_VARIANTS_BY_COMPONENT['webu_general_hero_01'];
      expect(hero).toContain('hero-1');
      expect(hero).toContain('hero-3');
      expect(hero).toContain('hero-7');
    });

    it('includes features and cta variants', () => {
      expect(AVAILABLE_VARIANTS_BY_COMPONENT['webu_general_features_01']).toContain('features-2');
      expect(AVAILABLE_VARIANTS_BY_COMPONENT['webu_general_cta_01']).toContain('cta-1');
    });
  });

  describe('selectVariant', () => {
    it('selects hero-3 for modern tone (prefer modern variants)', () => {
      const ctx: ComponentSelectionContext = { ...baseContext, tone: 'modern' };
      const v = selectVariant('webu_general_hero_01', ctx);
      expect(['hero-2', 'hero-3', 'hero-4']).toContain(v);
    });

    it('selects minimal variant (hero-1 or hero-2) for minimal tone', () => {
      const ctx: ComponentSelectionContext = { ...baseContext, tone: 'minimal' };
      const v = selectVariant('webu_general_hero_01', ctx);
      expect(['hero-1', 'hero-2']).toContain(v);
    });

    it('changes hero variant based on industry when tone alone is not enough', () => {
      const medical = selectVariant('webu_general_hero_01', {
        ...baseContext,
        tone: 'modern',
        industry: 'vet clinic',
      });
      const creative = selectVariant('webu_general_hero_01', {
        ...baseContext,
        tone: 'modern',
        industry: 'creative studio',
      });

      expect(medical).not.toBe(creative);
    });

    it('avoids duplicate when alreadyUsedVariantsByComponent is set', () => {
      const used = new Set<string>(['hero-3']);
      const ctx: ComponentSelectionContext = {
        ...baseContext,
        tone: 'modern',
        alreadyUsedVariantsByComponent: { webu_general_hero_01: used },
      };
      const v = selectVariant('webu_general_hero_01', ctx);
      expect(v).not.toBe('hero-3');
      expect(['hero-2', 'hero-4']).toContain(v);
    });

    it('returns only option for single-variant component', () => {
      const v = selectVariant('webu_header_01', baseContext);
      expect(v).toBe('header-1');
    });
  });

  describe('applyVariantSelection', () => {
    it('applies variants to sections and avoids duplicate variants per component', () => {
      const sections = [
        { componentKey: 'webu_general_hero_01' },
        { componentKey: 'webu_general_features_01' },
        { componentKey: 'webu_general_hero_01' },
      ];
      const result = applyVariantSelection(sections, { ...baseContext, tone: 'modern' });
      expect(result).toHaveLength(3);
      expect(result[0].variant).toBeDefined();
      expect(result[1].variant).toBeDefined();
      expect(result[2].variant).toBeDefined();
      expect(result[0].variant).not.toBe(result[2].variant);
    });
  });
});
