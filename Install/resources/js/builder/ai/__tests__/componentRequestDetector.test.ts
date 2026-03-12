import {
  detectComponentRequest,
  shouldTriggerComponentGenerator,
} from '../componentRequestDetector';

describe('componentRequestDetector', () => {
  describe('detectComponentRequest', () => {
    it('"Create pricing table" → isComponentRequest, not in registry, shouldTriggerGenerator', () => {
      const r = detectComponentRequest('Create pricing table');
      expect(r.isComponentRequest).toBe(true);
      expect(r.requestedPhrase).toBeTruthy();
      expect(r.normalizedSlug).toBe('pricing_table');
      expect(r.existsInRegistry).toBe(false);
      expect(r.shouldTriggerGenerator).toBe(true);
    });

    it('"Create testimonials slider" → shouldTriggerGenerator', () => {
      const r = detectComponentRequest('Create testimonials slider');
      expect(r.isComponentRequest).toBe(true);
      expect(r.normalizedSlug).toBe('testimonials_slider');
      expect(r.existsInRegistry).toBe(false);
      expect(r.shouldTriggerGenerator).toBe(true);
    });

    it('"Create team section" → shouldTriggerGenerator', () => {
      const r = detectComponentRequest('Create team section');
      expect(r.isComponentRequest).toBe(true);
      expect(r.normalizedSlug).toBe('team_section');
      expect(r.existsInRegistry).toBe(false);
      expect(r.shouldTriggerGenerator).toBe(true);
    });

    it('"Create FAQ accordion" → shouldTriggerGenerator', () => {
      const r = detectComponentRequest('Create FAQ accordion');
      expect(r.isComponentRequest).toBe(true);
      expect(r.normalizedSlug).toBe('faq_accordion');
      expect(r.existsInRegistry).toBe(false);
      expect(r.shouldTriggerGenerator).toBe(true);
    });

    it('"Create feature comparison table" → shouldTriggerGenerator', () => {
      const r = detectComponentRequest('Create feature comparison table');
      expect(r.isComponentRequest).toBe(true);
      expect(r.normalizedSlug).toBe('feature_comparison_table');
      expect(r.existsInRegistry).toBe(false);
      expect(r.shouldTriggerGenerator).toBe(true);
    });

    it('"Create pricing section" → pricing_table, shouldTriggerGenerator (FINAL RESULT)', () => {
      const r = detectComponentRequest('Create pricing section');
      expect(r.isComponentRequest).toBe(true);
      expect(r.normalizedSlug).toBe('pricing_table');
      expect(r.existsInRegistry).toBe(false);
      expect(r.shouldTriggerGenerator).toBe(true);
    });

    it('"Add pricing section" → pricing_table, shouldTriggerGenerator', () => {
      const r = detectComponentRequest('Add pricing section');
      expect(r.isComponentRequest).toBe(true);
      expect(r.normalizedSlug).toBe('pricing_table');
      expect(r.existsInRegistry).toBe(false);
      expect(r.shouldTriggerGenerator).toBe(true);
    });

    it('"Add testimonials" → in registry (cards), no trigger', () => {
      const r = detectComponentRequest('Add testimonials');
      expect(r.isComponentRequest).toBe(true);
      expect(r.existsInRegistry).toBe(true);
      expect(r.registryId).toBe('webu_general_cards_01');
      expect(r.shouldTriggerGenerator).toBe(false);
    });

    it('"Add hero" → in registry, no trigger', () => {
      const r = detectComponentRequest('Add hero');
      expect(r.isComponentRequest).toBe(true);
      expect(r.existsInRegistry).toBe(true);
      expect(r.registryId).toBe('webu_general_hero_01');
      expect(r.shouldTriggerGenerator).toBe(false);
    });

    it('non-request prompt → not component request', () => {
      const r = detectComponentRequest('What is the weather today?');
      expect(r.isComponentRequest).toBe(false);
      expect(r.shouldTriggerGenerator).toBe(false);
    });

    it('empty string → not component request', () => {
      const r = detectComponentRequest('');
      expect(r.isComponentRequest).toBe(false);
      expect(r.shouldTriggerGenerator).toBe(false);
    });

    it('"I need a team section" → shouldTriggerGenerator', () => {
      const r = detectComponentRequest('I need a team section');
      expect(r.isComponentRequest).toBe(true);
      expect(r.normalizedSlug).toBe('team_section');
      expect(r.shouldTriggerGenerator).toBe(true);
    });
  });

  describe('shouldTriggerComponentGenerator', () => {
    it('returns true for missing components', () => {
      expect(shouldTriggerComponentGenerator('Create pricing table')).toBe(true);
      expect(shouldTriggerComponentGenerator('Add FAQ accordion')).toBe(true);
    });

    it('returns false when component exists in registry', () => {
      expect(shouldTriggerComponentGenerator('Add hero')).toBe(false);
      expect(shouldTriggerComponentGenerator('Create features')).toBe(false);
    });

    it('returns false for non-request text', () => {
      expect(shouldTriggerComponentGenerator('Hello world')).toBe(false);
    });
  });
});
