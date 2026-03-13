import { planSite, planToDisplayFormat } from '../sitePlanner';
import type { PromptAnalysisResult } from '../promptAnalyzer';
import { buildTreeFromStructure } from '../../aiSiteGeneration';

describe('sitePlanner', () => {
  const analysisEcommerce: PromptAnalysisResult = {
    projectType: 'ecommerce',
    industry: 'furniture',
    tone: 'modern',
    requiredSections: ['header', 'hero', 'productGrid', 'features', 'testimonials', 'cta', 'footer'],
    functionalNeeds: [],
  };

  describe('planSite', () => {
    it('returns builder-compatible sections from prompt analysis', () => {
      const result = planSite(analysisEcommerce);
      expect(result.sections.length).toBeGreaterThan(0);
      expect(result.project.type).toBe('ecommerce');
      expect(result.available_components).toContain('webu_ecom_product_grid_01');
      expect(result.sections[0]).toMatchObject({ componentKey: 'webu_header_01', variant: 'header-1' });
      expect(result.sections.some((s) => s.componentKey === 'webu_general_hero_01')).toBe(true);
      expect(result.sections.some((s) => s.componentKey === 'webu_ecom_product_grid_01')).toBe(true);
      expect(result.sections.some((s) => s.componentKey === 'webu_general_features_01')).toBe(true);
      expect(result.sections.some((s) => s.componentKey === 'webu_general_testimonials_01')).toBe(true);
      expect(result.sections.some((s) => s.componentKey === 'webu_general_cta_01')).toBe(true);
      expect(result.sections.some((s) => s.componentKey === 'webu_footer_01')).toBe(true);
    });

    it('output can be passed to buildTreeFromStructure', () => {
      const result = planSite(analysisEcommerce);
      const tree = buildTreeFromStructure({ projectType: 'ecommerce', structure: result.sections });
      expect(tree.length).toBe(result.sections.length);
      expect(tree[0].componentKey).toBe('webu_header_01');
      expect(tree[0].props?.variant).toBeDefined();
    });

    it('returns default structure when analysis has no required sections', () => {
      const empty: PromptAnalysisResult = {
        projectType: 'landing',
        industry: null,
        tone: null,
        requiredSections: [],
        functionalNeeds: [],
      };
      const result = planSite(empty);
      expect(result.sections.length).toBeGreaterThanOrEqual(5);
      expect(result.sections[0].componentKey).toBe('webu_header_01');
      expect(result.project.type).toBe('landing');
    });
  });

  describe('planToDisplayFormat', () => {
    it('returns display format with component short name and variant', () => {
      const result = planSite(analysisEcommerce);
      const display = planToDisplayFormat(result);
      expect(display.length).toBe(result.sections.length);
      expect(display[0]).toMatchObject({ component: expect.any(String), variant: expect.any(String) });
      const headerDisplay = display.find((d) => d.component.includes('header') || d.variant === 'header-1');
      expect(headerDisplay).toBeDefined();
    });
  });

  it('normalizes hospitality projects to booking governance and keeps booking-safe components', () => {
    const result = planSite({
      projectType: 'restaurant',
      industry: 'food',
      tone: 'modern',
      requiredSections: ['header', 'hero', 'menu', 'booking', 'footer'],
      functionalNeeds: ['booking'],
    });

    expect(result.project.type).toBe('booking');
    expect(result.sections.some((section) => section.componentKey === 'webu_ecom_product_grid_01')).toBe(false);
    expect(result.sections.some((section) => section.componentKey === 'webu_general_form_wrapper_01')).toBe(true);
  });
});
