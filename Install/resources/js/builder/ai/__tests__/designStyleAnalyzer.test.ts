import {
  detectStyleFromDesign,
  getVariantForStyle,
  applyStyleToSectionPlan,
  type StyleVisionProvider,
} from '../designStyleAnalyzer';

describe('designStyleAnalyzer', () => {
  describe('getVariantForStyle', () => {
    it('minimal design → hero-2', () => {
      const variant = getVariantForStyle('webu_general_hero_01', 'minimal');
      expect(variant).toBe('hero-2');
    });

    it('corporate design → hero-1', () => {
      const variant = getVariantForStyle('webu_general_hero_01', 'corporate');
      expect(variant).toBe('hero-1');
    });

    it('modern design → hero-2 for hero component', () => {
      const variant = getVariantForStyle('webu_general_hero_01', 'modern');
      expect(variant).toBe('hero-2');
    });

    it('startup → hero-2, ecommerce → hero-1', () => {
      expect(getVariantForStyle('webu_general_hero_01', 'startup')).toBe('hero-2');
      expect(getVariantForStyle('webu_general_hero_01', 'ecommerce')).toBe('hero-1');
    });

    it('dark → hero-4', () => {
      const variant = getVariantForStyle('webu_general_hero_01', 'dark');
      expect(variant).toBe('hero-4');
    });

    it('returns features variant by style', () => {
      expect(getVariantForStyle('webu_general_features_01', 'minimal')).toBe('features-1');
      expect(getVariantForStyle('webu_general_features_01', 'modern')).toBe('features-2');
    });

    it('avoids already used variant when possible', () => {
      const used = new Set<string>(['hero-2']);
      const variant = getVariantForStyle('webu_general_hero_01', 'minimal', used);
      expect(variant).not.toBe('hero-2');
      expect(variant.startsWith('hero-')).toBe(true);
    });

    it('returns empty for unknown component key', () => {
      expect(getVariantForStyle('unknown_01', 'modern')).toBe('');
    });

    it('header/footer have single variant', () => {
      expect(getVariantForStyle('webu_header_01', 'minimal')).toBe('header-1');
      expect(getVariantForStyle('webu_footer_01', 'corporate')).toBe('footer-1');
    });
  });

  describe('detectStyleFromDesign', () => {
    it('returns heuristic style from projectType when no vision provider', async () => {
      const result = await detectStyleFromDesign({ projectType: 'saas' });
      expect(result.style).toBe('startup');
    });

    it('ecommerce projectType → ecommerce style', async () => {
      const result = await detectStyleFromDesign({ projectType: 'ecommerce' });
      expect(result.style).toBe('ecommerce');
    });

    it('business projectType → corporate style', async () => {
      const result = await detectStyleFromDesign({ projectType: 'business' });
      expect(result.style).toBe('corporate');
    });

    it('defaults to modern when projectType unknown', async () => {
      const result = await detectStyleFromDesign({ projectType: 'other' });
      expect(result.style).toBe('modern');
    });

    it('uses vision provider when provided and returns result', async () => {
      const provider: StyleVisionProvider = async () => ({ style: 'minimal', confidence: 0.9 });
      const result = await detectStyleFromDesign({
        designImageSource: 'data:image/png;base64,x',
        styleVisionProvider: provider,
      });
      expect(result.style).toBe('minimal');
      expect(result.confidence).toBe(0.9);
    });

    it('accepts string return from vision provider', async () => {
      const provider: StyleVisionProvider = async () => 'corporate';
      const result = await detectStyleFromDesign({
        designImageSource: 'data:image/png;base64,y',
        styleVisionProvider: provider,
      });
      expect(result.style).toBe('corporate');
    });

    it('falls back to heuristic when vision provider throws', async () => {
      const provider: StyleVisionProvider = async () => {
        throw new Error('API error');
      };
      const result = await detectStyleFromDesign({
        designImageSource: 'data:image/png;base64,z',
        styleVisionProvider: provider,
        projectType: 'landing',
      });
      expect(result.style).toBe('modern');
    });
  });

  describe('applyStyleToSectionPlan', () => {
    it('applies style variants to sections and avoids duplicates', () => {
      const sections = [
        { componentKey: 'webu_general_hero_01' },
        { componentKey: 'webu_general_features_01' },
        { componentKey: 'webu_general_cta_01' },
      ];
      const result = applyStyleToSectionPlan(sections, 'minimal');
      expect(result).toHaveLength(3);
      expect(result[0]!.variant).toBe('hero-2');
      expect(result[1]!.variant).toBe('features-1');
      expect(result[2]!.variant).toBe('cta-1');
    });

    it('corporate style yields hero-1 for hero', () => {
      const sections = [{ componentKey: 'webu_general_hero_01' }];
      const result = applyStyleToSectionPlan(sections, 'corporate');
      expect(result[0]!.variant).toBe('hero-1');
    });
  });
});
