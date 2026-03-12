import {
  parseRefineCommand,
  applyRefineToTree,
  refineLayoutFromCommand,
  refineSectionsFromCommand,
  type RefineParseResult,
} from '../layoutRefine';
import type { BuilderComponentInstance } from '../../core/types';
import type { BuilderSection } from '../../visual/treeUtils';

function node(id: string, componentKey: string, variant?: string, props?: Record<string, unknown>): BuilderComponentInstance {
  return {
    id,
    componentKey,
    ...(variant && { variant }),
    props: { ...(props ?? {}), ...(variant && { variant }) },
  };
}

function section(localId: string, type: string, props: Record<string, unknown> = {}): BuilderSection {
  return {
    localId,
    type,
    props,
    propsText: JSON.stringify(props),
    propsError: null,
    bindingMeta: null,
  };
}

describe('layoutRefine', () => {
  describe('parseRefineCommand', () => {
    it('"Improve this layout" → improve_layout, style modern', () => {
      const r = parseRefineCommand('Improve this layout');
      expect(r.intent).toBe('improve_layout');
      expect(r.style).toBe('modern');
    });

    it('"Make hero more modern" → make_section_modern, target hero, style modern', () => {
      const r = parseRefineCommand('Make hero more modern');
      expect(r.intent).toBe('make_section_modern');
      expect(r.targetSectionType).toBe('hero');
      expect(r.style).toBe('modern');
    });

    it('"Add CTA section" → add_section, sectionKeyToAdd webu_general_cta_01', () => {
      const r = parseRefineCommand('Add CTA section');
      expect(r.intent).toBe('add_section');
      expect(r.sectionKeyToAdd).toBe('webu_general_cta_01');
    });

    it('"Make layout minimal" → make_layout_style, style minimal', () => {
      const r = parseRefineCommand('Make layout minimal');
      expect(r.intent).toBe('make_layout_style');
      expect(r.style).toBe('minimal');
    });

    it('empty string → improve_layout, style modern', () => {
      const r = parseRefineCommand('');
      expect(r.intent).toBe('improve_layout');
      expect(r.style).toBe('modern');
    });

    it('"add call to action" → add_section', () => {
      const r = parseRefineCommand('add call to action');
      expect(r.intent).toBe('add_section');
    });

    it('"clean layout" → make_layout_style minimal', () => {
      const r = parseRefineCommand('clean layout');
      expect(r.intent).toBe('make_layout_style');
      expect(r.style).toBe('minimal');
    });
  });

  describe('applyRefineToTree', () => {
    it('improve_layout applies modern style variants', () => {
      const tree: BuilderComponentInstance[] = [
        node('h1', 'webu_general_hero_01', 'hero-1'),
        node('f1', 'webu_general_features_01', 'features-1'),
      ];
      const result = applyRefineToTree(tree, { intent: 'improve_layout', style: 'modern' });
      expect(result).toHaveLength(2);
      expect(result[0]!.props?.variant).toBeDefined();
      expect(result[1]!.props?.variant).toBeDefined();
    });

    it('add_section inserts CTA before footer', () => {
      const tree: BuilderComponentInstance[] = [
        node('h1', 'webu_general_hero_01'),
        node('foot', 'webu_footer_01'),
      ];
      const result = applyRefineToTree(tree, {
        intent: 'add_section',
        sectionKeyToAdd: 'webu_general_cta_01',
      });
      expect(result).toHaveLength(3);
      expect(result[1]!.componentKey).toBe('webu_general_cta_01');
      expect(result[2]!.componentKey).toBe('webu_footer_01');
    });

    it('make_section_modern updates only hero variant', () => {
      const tree: BuilderComponentInstance[] = [
        node('h1', 'webu_general_hero_01', 'hero-1'),
        node('f1', 'webu_general_features_01', 'features-1'),
      ];
      const result = applyRefineToTree(tree, {
        intent: 'make_section_modern',
        targetSectionType: 'hero',
        style: 'modern',
      });
      expect(result).toHaveLength(2);
      expect(result[0]!.props?.variant).toBe('hero-3');
      expect(result[1]!.props?.variant).toBe('features-1');
    });
  });

  describe('refineLayoutFromCommand', () => {
    it('returns tree and sectionsDraft', () => {
      const tree: BuilderComponentInstance[] = [
        node('h1', 'webu_general_hero_01', 'hero-1'),
        node('foot', 'webu_footer_01'),
      ];
      const result = refineLayoutFromCommand(tree, 'Make hero more modern');
      expect(result.tree).toBeDefined();
      expect(result.sectionsDraft).toBeDefined();
      expect(result.sectionsDraft).toHaveLength(result.tree.length);
      expect(result.sectionsDraft[0]?.localId).toBe('h1');
      expect(result.sectionsDraft[0]?.type).toBe('webu_general_hero_01');
    });

    it('add CTA increases section count', () => {
      const tree: BuilderComponentInstance[] = [
        node('h1', 'webu_general_hero_01'),
        node('foot', 'webu_footer_01'),
      ];
      const result = refineLayoutFromCommand(tree, 'Add CTA section');
      expect(result.tree).toHaveLength(3);
      expect(result.sectionsDraft).toHaveLength(3);
    });
  });

  describe('refineSectionsFromCommand', () => {
    it('accepts sectionsDraft and returns new sectionsDraft', () => {
      const sections: BuilderSection[] = [
        section('h1', 'webu_general_hero_01', { variant: 'hero-1' }),
        section('foot', 'webu_footer_01', {}),
      ];
      const result = refineSectionsFromCommand(sections, 'Improve this layout');
      expect(result.sectionsDraft).toBeDefined();
      expect(result.sectionsDraft.length).toBeGreaterThanOrEqual(2);
    });

    it('add CTA from sectionsDraft', () => {
      const sections: BuilderSection[] = [
        section('h1', 'webu_general_hero_01', {}),
        section('foot', 'webu_footer_01', {}),
      ];
      const result = refineSectionsFromCommand(sections, 'Add CTA section');
      expect(result.sectionsDraft).toHaveLength(3);
      const cta = result.sectionsDraft.find((s) => s.type === 'webu_general_cta_01');
      expect(cta).toBeDefined();
    });
  });
});
