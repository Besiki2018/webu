/**
 * Part 2 — UX Heuristic Rules tests.
 */
import {
  LANDING_PAGE_RULES,
  ECOMMERCE_PAGE_RULES,
  getUxRulesForPageType,
  evaluateUxRules,
  getUxSuggestions,
  normalizeToSectionKinds,
  type PageType,
} from '../uxRules';

describe('uxRules', () => {
  describe('rule sets', () => {
    it('landing page requires hero, features, social proof, cta, footer', () => {
      expect(LANDING_PAGE_RULES.required).toContain('hero');
      expect(LANDING_PAGE_RULES.required).toContain('features');
      expect(LANDING_PAGE_RULES.required).toContain('social_proof');
      expect(LANDING_PAGE_RULES.required).toContain('cta');
      expect(LANDING_PAGE_RULES.required).toContain('footer');
      expect(LANDING_PAGE_RULES.required).toContain('header');
    });

    it('ecommerce page requires hero, product grid, filters, reviews, cta, footer', () => {
      expect(ECOMMERCE_PAGE_RULES.required).toContain('hero');
      expect(ECOMMERCE_PAGE_RULES.required).toContain('product_grid');
      expect(ECOMMERCE_PAGE_RULES.required).toContain('filters');
      expect(ECOMMERCE_PAGE_RULES.required).toContain('reviews');
      expect(ECOMMERCE_PAGE_RULES.required).toContain('cta');
      expect(ECOMMERCE_PAGE_RULES.required).toContain('footer');
    });
  });

  describe('getUxRulesForPageType', () => {
    it('returns landing rules for "landing"', () => {
      const r = getUxRulesForPageType('landing');
      expect(r.pageType).toBe('landing');
      expect(r.required).toEqual(LANDING_PAGE_RULES.required);
    });

    it('returns ecommerce rules for "ecommerce"', () => {
      const r = getUxRulesForPageType('ecommerce');
      expect(r.pageType).toBe('ecommerce');
      expect(r.required).toContain('product_grid');
    });

    it('returns generic rules for unknown type', () => {
      const r = getUxRulesForPageType('unknown' as PageType);
      expect(r.pageType).toBe('generic');
    });
  });

  describe('normalizeToSectionKinds', () => {
    it('maps cards and testimonials to social_proof', () => {
      const kinds = normalizeToSectionKinds(['header', 'hero', 'cards', 'footer']);
      expect(kinds).toContain('social_proof');
      expect(kinds).toContain('header');
      expect(kinds).toContain('hero');
      expect(kinds).toContain('footer');
    });

    it('maps grid to product_grid', () => {
      const kinds = normalizeToSectionKinds(['grid', 'hero']);
      expect(kinds).toContain('product_grid');
    });
  });

  describe('evaluateUxRules', () => {
    it('landing with header, hero, features, footer only → suggests social proof and CTA', () => {
      const result = evaluateUxRules(
        ['header', 'hero', 'features', 'footer'],
        'landing'
      );
      expect(result.pageType).toBe('landing');
      expect(result.present).toContain('header');
      expect(result.present).toContain('hero');
      expect(result.present).toContain('features');
      expect(result.present).toContain('footer');
      expect(result.missing.some((m) => m.sectionKind === 'social_proof')).toBe(true);
      expect(result.missing.some((m) => m.sectionKind === 'cta')).toBe(true);
      expect(result.missing.every((m) => m.message.startsWith('Add a '))).toBe(true);
      expect(result.missing.every((m) => m.suggestedType)).toBe(true);
    });

    it('landing with all required sections → no missing', () => {
      const result = evaluateUxRules(
        ['header', 'hero', 'features', 'social_proof', 'cta', 'footer'],
        'landing'
      );
      expect(result.missing).toHaveLength(0);
      expect(result.present.length).toBe(6);
    });

    it('ecommerce with only hero and footer → suggests product grid, filters, reviews, CTA, header', () => {
      const result = evaluateUxRules(
        ['hero', 'footer'],
        'ecommerce'
      );
      expect(result.missing.some((m) => m.sectionKind === 'product_grid')).toBe(true);
      expect(result.missing.some((m) => m.sectionKind === 'filters')).toBe(true);
      expect(result.missing.some((m) => m.sectionKind === 'reviews')).toBe(true);
      expect(result.missing.some((m) => m.sectionKind === 'cta')).toBe(true);
    });

    it('accepts analyzer-style string kinds and normalizes', () => {
      const result = evaluateUxRules(
        ['header', 'hero', 'features', 'cards', 'cta', 'footer'],
        'landing'
      );
      expect(result.missing).toHaveLength(0);
    });
  });

  describe('getUxSuggestions', () => {
    it('returns missing sections as suggestions for AI to suggest adding', () => {
      const suggestions = getUxSuggestions(
        ['header', 'hero', 'footer'],
        'landing'
      );
      expect(suggestions.length).toBeGreaterThan(0);
      expect(suggestions.some((s) => s.sectionKind === 'features')).toBe(true);
      expect(suggestions.some((s) => s.sectionKind === 'cta')).toBe(true);
      expect(suggestions.every((s) => s.message && s.sectionKind)).toBe(true);
    });
  });
});
