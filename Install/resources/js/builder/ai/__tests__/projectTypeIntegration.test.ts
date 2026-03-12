import {
  getSectionsForProjectType,
  getCapabilitiesForProjectType,
  PROJECT_TYPE_SECTIONS,
  PROJECT_TYPE_CAPABILITIES,
} from '../projectTypeIntegration';

describe('projectTypeIntegration', () => {
  describe('PROJECT_TYPE_SECTIONS', () => {
    it('ecommerce includes productGrid', () => {
      const sections = PROJECT_TYPE_SECTIONS.ecommerce;
      expect(sections).toContain('productGrid');
      expect(sections).toContain('header');
      expect(sections).toContain('hero');
    });

    it('restaurant includes menu, gallery, booking', () => {
      const sections = PROJECT_TYPE_SECTIONS.restaurant;
      expect(sections).toContain('menu');
      expect(sections).toContain('gallery');
      expect(sections).toContain('booking');
    });

    it('saas includes features, pricing, and testimonials', () => {
      const sections = PROJECT_TYPE_SECTIONS.saas;
      expect(sections).toContain('features');
      expect(sections).toContain('pricing');
      expect(sections).toContain('testimonials');
    });
  });

  describe('PROJECT_TYPE_CAPABILITIES', () => {
    it('ecommerce has product grid, cart, filters', () => {
      const cap = PROJECT_TYPE_CAPABILITIES.ecommerce;
      expect(cap).toContain('product grid');
      expect(cap).toContain('cart');
      expect(cap).toContain('filters');
    });
    it('restaurant has menu, reservation, gallery', () => {
      const cap = PROJECT_TYPE_CAPABILITIES.restaurant;
      expect(cap).toContain('menu');
      expect(cap).toContain('reservation');
      expect(cap).toContain('gallery');
    });
    it('saas has features, pricing, integrations', () => {
      const cap = PROJECT_TYPE_CAPABILITIES.saas;
      expect(cap).toContain('features');
      expect(cap).toContain('pricing');
      expect(cap).toContain('integrations');
    });
  });

  describe('getSectionsForProjectType', () => {
    it('returns copy of sections for type', () => {
      const a = getSectionsForProjectType('ecommerce');
      const b = getSectionsForProjectType('ecommerce');
      expect(a).toEqual(b);
      expect(a).not.toBe(b);
    });
  });

  describe('getCapabilitiesForProjectType', () => {
    it('returns capabilities for type', () => {
      expect(getCapabilitiesForProjectType('saas')).toContain('integrations');
    });
  });
});
