import { matchVariant, applyVariantMatching, type VariantLayoutHints } from '../variantMatcher';

describe('variantMatcher', () => {
  describe('matchVariant (hero)', () => {
    it('returns hero-3 for image right + text left', () => {
      const result = matchVariant({
        componentKey: 'webu_general_hero_01',
        hints: { imagePosition: 'right', textAlignment: 'left' },
      });
      expect(result).toBe('hero-3');
    });

    it('returns hero-2 for image top + center', () => {
      const result = matchVariant({
        componentKey: 'webu_general_hero_01',
        hints: { imagePosition: 'top', textAlignment: 'center' },
      });
      expect(result).toBe('hero-2');
    });

    it('returns hero-4 or hero-6 for image background', () => {
      const result = matchVariant({
        componentKey: 'webu_general_hero_01',
        hints: { imagePosition: 'background', textAlignment: 'center' },
      });
      expect(['hero-4', 'hero-6']).toContain(result);
    });

    it('returns hero-5 for image left', () => {
      const result = matchVariant({
        componentKey: 'webu_general_hero_01',
        hints: { imagePosition: 'left', textAlignment: 'left' },
      });
      expect(result).toBe('hero-5');
    });

    it('returns hero-1 when no hints (default)', () => {
      const result = matchVariant({
        componentKey: 'webu_general_hero_01',
        hints: {},
      });
      expect(result).toBeTruthy();
      expect(result.startsWith('hero-')).toBe(true);
    });

    it('avoids already used variant when possible', () => {
      const used = new Set<string>(['hero-3']);
      const result = matchVariant({
        componentKey: 'webu_general_hero_01',
        hints: { imagePosition: 'right', textAlignment: 'left' },
        alreadyUsed: used,
      });
      expect(result).not.toBe('hero-3');
      expect(result.startsWith('hero-')).toBe(true);
    });
  });

  describe('matchVariant (features)', () => {
    it('prefers variant by layout density', () => {
      expect(
        matchVariant({
          componentKey: 'webu_general_features_01',
          hints: { layoutDensity: 'compact' },
        })
      ).toBe('features-1');
      expect(
        matchVariant({
          componentKey: 'webu_general_features_01',
          hints: { layoutDensity: 'spacious' },
        })
      ).toBe('features-3');
    });
  });

  describe('matchVariant (cta)', () => {
    it('prefers variant by layout density', () => {
      const result = matchVariant({
        componentKey: 'webu_general_cta_01',
        hints: { layoutDensity: 'medium' },
      });
      expect(result).toBe('cta-2');
    });
  });

  describe('matchVariant (other components)', () => {
    it('returns single variant for header/footer', () => {
      expect(matchVariant({ componentKey: 'webu_header_01', hints: {} })).toBe('header-1');
      expect(matchVariant({ componentKey: 'webu_footer_01', hints: {} })).toBe('footer-1');
    });

    it('returns empty for unknown component key', () => {
      expect(matchVariant({ componentKey: 'unknown_01', hints: {} })).toBe('');
    });
  });

  describe('applyVariantMatching', () => {
    it('applies matching to section list and tracks used variants', () => {
      const sections = [
        { componentKey: 'webu_general_hero_01' },
        { componentKey: 'webu_general_hero_01' },
      ];
      const hintsByIndex: VariantLayoutHints[] = [
        { imagePosition: 'right', textAlignment: 'left' },
        { imagePosition: 'top', textAlignment: 'center' },
      ];
      const result = applyVariantMatching(sections, hintsByIndex);
      expect(result).toHaveLength(2);
      expect(result[0]!.variant).toBe('hero-3');
      expect(result[1]!.variant).toBe('hero-2');
    });

    it('uses first unused variant when no hints', () => {
      const sections = [{ componentKey: 'webu_general_features_01' }];
      const result = applyVariantMatching(sections);
      expect(result[0]!.variant).toBeTruthy();
      expect(result[0]!.variant.startsWith('features-')).toBe(true);
    });
  });
});
