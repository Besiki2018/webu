import { sectionPlanToBuilderState, sectionPlanToComponentTree } from '../siteBuilder';
import { planSite } from '../sitePlanner';
import type { PromptAnalysisResult } from '../promptAnalyzer';

describe('siteBuilder', () => {
  const analysis: PromptAnalysisResult = {
    projectType: 'ecommerce',
    industry: 'furniture',
    tone: 'modern',
    requiredSections: ['header', 'hero', 'features', 'cta', 'footer'],
    functionalNeeds: [],
  };

  const plan = planSite(analysis);

  describe('sectionPlanToBuilderState', () => {
    it('returns page with sections array', () => {
      const state = sectionPlanToBuilderState(plan);
      expect(state.page).toBeDefined();
      expect(Array.isArray(state.page.sections)).toBe(true);
      expect(state.page.sections.length).toBe(plan.sections.length);
    });

    it('each section has id, component (short name), variant, props', () => {
      const state = sectionPlanToBuilderState(plan);
      for (const sec of state.page.sections) {
        expect(sec.id).toBeDefined();
        expect(typeof sec.component).toBe('string');
        expect(typeof sec.variant).toBe('string');
        expect(sec.props !== null && typeof sec.props === 'object').toBe(true);
      }
    });

    it('first section is header with short name', () => {
      const state = sectionPlanToBuilderState(plan);
      const header = state.page.sections.find((s) => s.component.includes('header') || s.id.startsWith('header'));
      expect(header).toBeDefined();
      expect(header!.component).toBe('header');
    });

    it('merges propsByIndex into section props', () => {
      const state = sectionPlanToBuilderState(plan, {
        propsByIndex: {
          1: { title: 'Hero Title', subtitle: 'Sub', buttonText: 'Shop Now' },
        },
      });
      const heroSection = state.page.sections[1];
      expect(heroSection).toBeDefined();
      expect(heroSection!.props).toMatchObject({
        title: 'Hero Title',
        subtitle: 'Sub',
        buttonText: 'Shop Now',
      });
    });
  });

  describe('sectionPlanToComponentTree', () => {
    it('returns BuilderComponentInstance[]', () => {
      const tree = sectionPlanToComponentTree(plan);
      expect(Array.isArray(tree)).toBe(true);
      expect(tree.length).toBe(plan.sections.length);
      for (const node of tree) {
        expect(node.id).toBeDefined();
        expect(node.componentKey).toBeDefined();
        expect(node.props !== null && typeof node.props === 'object').toBe(true);
      }
    });

    it('uses registry componentKey (e.g. webu_general_hero_01)', () => {
      const tree = sectionPlanToComponentTree(plan);
      const hero = tree.find((n) => n.componentKey === 'webu_general_hero_01');
      expect(hero).toBeDefined();
    });

    it('merges propsByIndex with defaults', () => {
      const tree = sectionPlanToComponentTree(plan, {
        propsByIndex: {
          1: { title: 'Welcome', buttonText: 'Get started' },
        },
      });
      const hero = tree.find((n) => n.componentKey === 'webu_general_hero_01');
      expect(hero?.props?.title).toBe('Welcome');
      expect(hero?.props?.buttonText).toBe('Get started');
    });
  });
});
