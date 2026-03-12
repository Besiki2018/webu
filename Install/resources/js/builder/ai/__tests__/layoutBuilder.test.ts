import {
  buildLayoutFromPlan,
  layoutToTreeSummary,
  type LayoutBuilderSectionPlan,
} from '../layoutBuilder';

describe('layoutBuilder', () => {
  const plan: LayoutBuilderSectionPlan = {
    sections: [
      { componentKey: 'webu_header_01', variant: 'header-1' },
      { componentKey: 'webu_general_hero_01', variant: 'hero-3' },
      { componentKey: 'webu_general_features_01', variant: 'features-1' },
      { componentKey: 'webu_general_cards_01', variant: 'cards-1' },
      { componentKey: 'webu_general_cta_01', variant: 'cta-2' },
      { componentKey: 'webu_footer_01', variant: 'footer-1' },
    ],
  };

  describe('buildLayoutFromPlan', () => {
    it('converts section plan into builder structure with component short names', () => {
      const result = buildLayoutFromPlan(plan);
      expect(result.sections).toHaveLength(6);
      expect(result.sections[0]).toMatchObject({
        id: expect.any(String),
        component: 'header',
        variant: 'header-1',
        props: {},
      });
      expect(result.sections[1]).toMatchObject({ component: 'hero', variant: 'hero-3' });
      expect(result.sections[2]).toMatchObject({ component: 'features', variant: 'features-1' });
      expect(result.sections[3]).toMatchObject({ component: 'cards', variant: 'cards-1' });
      expect(result.sections[4]).toMatchObject({ component: 'cta', variant: 'cta-2' });
      expect(result.sections[5]).toMatchObject({ component: 'footer', variant: 'footer-1' });
    });

    it('every section has id, component, variant, props', () => {
      const result = buildLayoutFromPlan(plan);
      result.sections.forEach((s) => {
        expect(s.id).toBeDefined();
        expect(typeof s.component).toBe('string');
        expect(typeof s.variant).toBe('string');
        expect(s.props !== null && typeof s.props === 'object').toBe(true);
      });
    });

    it('replaces invalid componentKey with registry fallback (hero/features/footer)', () => {
      const planWithInvalid = {
        sections: [
          { componentKey: 'webu_header_01', variant: 'header-1' },
          { componentKey: 'invalid_01', variant: 'x' },
          { componentKey: 'webu_footer_01', variant: 'footer-1' },
        ],
      };
      const result = buildLayoutFromPlan(planWithInvalid);
      expect(result.sections).toHaveLength(3);
      expect(result.sections[0]!.component).toBe('header');
      expect(result.sections[2]!.component).toBe('footer');
      expect(['hero', 'features']).toContain(result.sections[1]!.component);
    });

    it('merges propsByIndex into section props', () => {
      const result = buildLayoutFromPlan(plan, {
        propsByIndex: {
          1: { title: 'Hero Title', buttonText: 'Start' },
        },
      });
      expect(result.sections[1]!.props).toMatchObject({
        title: 'Hero Title',
        buttonText: 'Start',
      });
    });

    it('generates stable ids by default', () => {
      const result = buildLayoutFromPlan(plan);
      expect(result.sections[0]!.id).toBe('header-1');
      expect(result.sections[1]!.id).toBe('hero-2');
      expect(result.sections[5]!.id).toBe('footer-6');
    });

    it('accepts custom generateId', () => {
      const result = buildLayoutFromPlan(plan, {
        generateId: (_, i) => `sec-${i}`,
      });
      expect(result.sections[0]!.id).toBe('sec-0');
      expect(result.sections[5]!.id).toBe('sec-5');
    });
  });

  describe('layoutToTreeSummary', () => {
    it('returns tree string with page root and section names', () => {
      const result = buildLayoutFromPlan(plan);
      const tree = layoutToTreeSummary(result);
      expect(tree).toContain('page');
      expect(tree).toContain('header');
      expect(tree).toContain('hero');
      expect(tree).toContain('features');
      expect(tree).toContain('cards');
      expect(tree).toContain('cta');
      expect(tree).toContain('footer');
      expect(tree).toContain('hero-3');
      expect(tree.split('\n').length).toBe(7);
    });

    it('last section uses └ branch', () => {
      const single = buildLayoutFromPlan({ sections: [{ componentKey: 'webu_header_01', variant: 'header-1' }] });
      const tree = layoutToTreeSummary(single);
      expect(tree).toContain(' └ header');
    });
  });
});
