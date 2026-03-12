/**
 * Part 13 — Safety rules: registry-only components, fallback to default hero/features/footer.
 */

import { planSite } from '../sitePlanner';
import { sectionPlanToComponentTree, sectionPlanToBuilderState } from '../siteBuilder';
import { selectVariant, applyVariantSelection } from '../componentSelector';
import {
  hasEntry,
  DEFAULT_HERO_REGISTRY_ID,
  DEFAULT_FEATURES_REGISTRY_ID,
  DEFAULT_FOOTER_REGISTRY_ID,
} from '../../registry/componentRegistry';

describe('Part 13 — Safety rules', () => {
  describe('only registry components', () => {
    it('sectionPlanToComponentTree replaces invalid componentKey with fallbacks', () => {
      const planWithInvalidKeys = {
        sections: [
          { componentKey: 'nonexistent_hero_99', variant: 'hero-1' },
          { componentKey: 'fake_features_99', variant: 'features-1' },
          { componentKey: 'fake_footer_99', variant: 'footer-1' },
        ],
      };
      const tree = sectionPlanToComponentTree(planWithInvalidKeys);
      expect(tree).toHaveLength(3);
      expect(hasEntry(tree[0]!.componentKey)).toBe(true);
      expect(tree[0]!.componentKey).toBe(DEFAULT_HERO_REGISTRY_ID);
      expect(tree[1]!.componentKey).toBe(DEFAULT_FEATURES_REGISTRY_ID);
      expect(tree[2]!.componentKey).toBe(DEFAULT_FOOTER_REGISTRY_ID);
    });

    it('sectionPlanToBuilderState uses only registry-backed component short names', () => {
      const planWithInvalid = {
        sections: [
          { componentKey: 'invalid_01' },
          { componentKey: 'webu_general_hero_01' },
          { componentKey: 'also_invalid_01' },
        ],
      };
      const state = sectionPlanToBuilderState(planWithInvalid);
      expect(state.page.sections).toHaveLength(3);
      expect(state.page.sections[0]!.component).toBe('hero');
      expect(state.page.sections[1]!.component).toBe('hero');
      expect(state.page.sections[2]!.component).toBe('footer');
    });

    it('selectVariant returns empty string for non-registry componentKey', () => {
      const result = selectVariant('nonexistent_01', {
        projectType: 'ecommerce',
        tone: null,
        industry: null,
      });
      expect(result).toBe('');
    });

    it('applyVariantSelection does not break when section has invalid key (variant empty)', () => {
      const sections = [
        { componentKey: 'webu_general_hero_01' },
        { componentKey: 'invalid_key_01' },
      ];
      const result = applyVariantSelection(sections, {
        projectType: 'landing',
        tone: null,
        industry: null,
      });
      expect(result).toHaveLength(2);
      expect(result[0]!.variant).toBeTruthy();
      expect(result[1]!.variant).toBe('');
    });
  });

  describe('fallback defaults', () => {
    it('planSite empty analysis uses default hero, features, footer constants', () => {
      const result = planSite({
        projectType: 'landing',
        industry: null,
        tone: null,
        requiredSections: [],
        functionalNeeds: [],
      });
      const heroSection = result.sections.find((s) => s.componentKey === DEFAULT_HERO_REGISTRY_ID);
      const featuresSection = result.sections.find((s) => s.componentKey === DEFAULT_FEATURES_REGISTRY_ID);
      const footerSection = result.sections.find((s) => s.componentKey === DEFAULT_FOOTER_REGISTRY_ID);
      expect(heroSection).toBeDefined();
      expect(featuresSection).toBeDefined();
      expect(footerSection).toBeDefined();
    });

    it('sectionPlanToComponentTree uses default hero for first invalid, footer for last invalid', () => {
      const plan = {
        sections: [
          { componentKey: 'bad_first' },
          { componentKey: 'bad_mid' },
          { componentKey: 'bad_last' },
        ],
      };
      const tree = sectionPlanToComponentTree(plan);
      expect(tree[0]!.componentKey).toBe(DEFAULT_HERO_REGISTRY_ID);
      expect(tree[1]!.componentKey).toBe(DEFAULT_FEATURES_REGISTRY_ID);
      expect(tree[2]!.componentKey).toBe(DEFAULT_FOOTER_REGISTRY_ID);
    });
  });

  describe('valid plan unchanged', () => {
    it('plan with all valid keys produces same componentKeys in tree', () => {
      const plan = planSite({
        projectType: 'ecommerce',
        industry: 'tech',
        tone: 'modern',
        requiredSections: ['header', 'hero', 'features', 'footer'],
        functionalNeeds: [],
      });
      const tree = sectionPlanToComponentTree(plan);
      expect(tree.length).toBe(plan.sections.length);
      tree.forEach((node, i) => {
        expect(node.componentKey).toBe(plan.sections[i]!.componentKey);
        expect(hasEntry(node.componentKey)).toBe(true);
      });
    });
  });
});
