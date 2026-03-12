/**
 * Phase 8 — AI site generation: buildTreeFromStructure.
 */

import { describe, it, expect } from 'vitest';
import { buildTreeFromStructure, DEFAULT_SAAS_LANDING_STRUCTURE } from '../aiSiteGeneration';

describe('aiSiteGeneration', () => {
  describe('buildTreeFromStructure', () => {
    it('builds tree from structure with default ids', () => {
      const structure = [
        { componentKey: 'webu_header_01' },
        { componentKey: 'webu_general_hero_01' },
      ];
      const tree = buildTreeFromStructure({ projectType: 'saas', structure });
      expect(tree).toHaveLength(2);
      expect(tree[0]!.id).toBe('header-1');
      expect(tree[0]!.componentKey).toBe('webu_header_01');
      expect(tree[0]!.props).toBeDefined();
      expect(tree[1]!.id).toBe('general-hero-2');
      expect(tree[1]!.componentKey).toBe('webu_general_hero_01');
    });

    it('merges optional variant and props', () => {
      const structure = [
        { componentKey: 'webu_general_hero_01', variant: 'hero-2', props: { title: 'AI-generated title' } },
      ];
      const tree = buildTreeFromStructure({ projectType: 'landing', structure });
      expect(tree).toHaveLength(1);
      expect(tree[0]!.variant).toBe('hero-2');
      expect(tree[0]!.props.title).toBe('AI-generated title');
    });

    it('uses default SaaS landing structure when passed', () => {
      const tree = buildTreeFromStructure({
        projectType: 'saas',
        structure: DEFAULT_SAAS_LANDING_STRUCTURE,
      });
      expect(tree.length).toBeGreaterThanOrEqual(4);
      const keys = tree.map((n) => n.componentKey);
      expect(keys).toContain('webu_header_01');
      expect(keys).toContain('webu_general_hero_01');
      expect(keys).toContain('webu_general_features_01');
      expect(keys).toContain('webu_general_cta_01');
      expect(keys).toContain('webu_footer_01');
    });
  });
});
