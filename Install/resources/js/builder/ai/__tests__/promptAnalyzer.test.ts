import { analyzePrompt } from '../promptAnalyzer';

describe('promptAnalyzer', () => {
  describe('analyzePrompt', () => {
    it('detects ecommerce from "store"', () => {
      const r = analyzePrompt('Create a modern ecommerce website for a furniture store');
      expect(r.projectType).toBe('ecommerce');
      expect(r.industry).toBe('furniture');
      expect(r.tone).toBe('modern');
      expect(r.requiredSections).toContain('header');
      expect(r.requiredSections).toContain('hero');
      expect(r.requiredSections).toContain('productGrid');
      expect(r.requiredSections).toContain('footer');
    });

    it('detects restaurant from "restaurant"', () => {
      const r = analyzePrompt('Website for a restaurant with menu and booking');
      expect(r.projectType).toBe('restaurant');
      expect(r.requiredSections).toContain('menu');
      expect(r.functionalNeeds).toContain('booking');
    });

    it('detects portfolio from "portfolio"', () => {
      const r = analyzePrompt('Portfolio showcase for my work');
      expect(r.projectType).toBe('portfolio');
      expect(r.requiredSections).toContain('gallery');
    });

    it('detects saas from "startup"', () => {
      const r = analyzePrompt('Landing page for a startup AI marketing tool');
      expect(r.projectType).toBe('saas');
      expect(r.requiredSections).toContain('pricing');
    });

    it('"Create a modern SaaS landing page" yields header, hero, features, pricing, testimonials, cta, footer', () => {
      const r = analyzePrompt('Create a modern SaaS landing page');
      expect(r.projectType).toBe('saas');
      expect(r.tone).toBe('modern');
      expect(r.requiredSections).toContain('header');
      expect(r.requiredSections).toContain('hero');
      expect(r.requiredSections).toContain('features');
      expect(r.requiredSections).toContain('pricing');
      expect(r.requiredSections).toContain('testimonials');
      expect(r.requiredSections).toContain('cta');
      expect(r.requiredSections).toContain('footer');
    });

    it('returns default projectType and sections for empty prompt', () => {
      const r = analyzePrompt('');
      expect(r.projectType).toBe('landing');
      expect(r.requiredSections.length).toBeGreaterThan(0);
      expect(r.industry).toBeNull();
      expect(r.tone).toBeNull();
    });

    it('detects tone and industry', () => {
      const r = analyzePrompt('Minimal fashion store with contact form');
      expect(r.tone).toBe('minimal');
      expect(r.industry).toBe('fashion');
      expect(r.functionalNeeds).toContain('contact_form');
    });
  });
});
