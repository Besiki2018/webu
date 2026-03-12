/**
 * AI Project Processor — processProjectComponents(project) tests.
 */

import { describe, it, expect } from 'vitest';
import {
  processProjectComponents,
  applyProjectComponentUpdates,
  processAndApplyProjectComponents,
} from '../aiProjectProcessor';
import type { BuilderComponentInstance } from '../core/types';

function node(
  id: string,
  componentKey: string,
  props: Record<string, unknown> = {},
  children?: BuilderComponentInstance[]
): BuilderComponentInstance {
  return { id, componentKey, props, ...(children?.length && { children }) };
}

describe('AI Project Processor', () => {
  describe('processProjectComponents', () => {
    it('1. analyzes project type (uses default when missing)', () => {
      const project = { componentTree: [] };
      const result = processProjectComponents(project);
      expect(result.projectType).toBe('landing');
    });

    it('2. uses provided project type', () => {
      const project = { projectType: 'ecommerce', componentTree: [] };
      const result = processProjectComponents(project);
      expect(result.projectType).toBe('ecommerce');
    });

    it('3. scans all components in tree', () => {
      const tree: BuilderComponentInstance[] = [
        node('header-1', 'webu_header_01'),
        node('hero-1', 'webu_general_hero_01'),
      ];
      const result = processProjectComponents({ projectType: 'business', componentTree: tree });
      expect(result.scannedNodes).toHaveLength(2);
      expect(result.scannedNodes.map((n) => n.id)).toEqual(['header-1', 'hero-1']);
      expect(result.scannedNodes.map((n) => n.componentKey)).toContain('webu_header_01');
      expect(result.scannedNodes.map((n) => n.componentKey)).toContain('webu_general_hero_01');
    });

    it('4. checks compatibility per node', () => {
      const tree: BuilderComponentInstance[] = [
        node('header-1', 'webu_header_01'),
        node('hero-1', 'webu_general_hero_01'),
      ];
      const result = processProjectComponents({ projectType: 'business', componentTree: tree });
      expect(result.compatibility).toHaveLength(2);
      result.compatibility.forEach((c) => {
        expect(c).toHaveProperty('nodeId');
        expect(c).toHaveProperty('componentKey');
        expect(typeof c.compatible).toBe('boolean');
      });
    });

    it('5. detects unnecessary elements and applies refactor rules (e.g. remove search for business)', () => {
      const tree: BuilderComponentInstance[] = [
        node('header-1', 'webu_header_01', { showSearch: true, searchMode: 'generic' }),
      ];
      const result = processProjectComponents({ projectType: 'business', componentTree: tree });
      expect(result.suggestions.length).toBeGreaterThanOrEqual(1);
      const removeSearch = result.suggestions.find((s) => s.actionKind === 'remove_element');
      expect(removeSearch).toBeDefined();
      expect(result.updates).toHaveLength(1);
      expect(result.updates[0]!.nodeId).toBe('header-1');
      expect(result.updates[0]!.patch).toMatchObject({ showSearch: false, searchMode: 'none' });
      expect(result.summary).toContain(removeSearch!.label);
    });

    it('6. produces updates (e.g. add ecommerce cart when project is ecommerce)', () => {
      const tree: BuilderComponentInstance[] = [
        node('header-1', 'webu_header_01', { showSearch: false, searchMode: 'none' }),
      ];
      const result = processProjectComponents({ projectType: 'ecommerce', componentTree: tree });
      expect(result.suggestions.length).toBeGreaterThanOrEqual(1);
      expect(result.updates).toHaveLength(1);
      expect(result.updates[0]!.patch).toMatchObject({
        showSearch: true,
        searchMode: 'product',
        showCartIcon: true,
      });
    });

    it('returns empty updates when no refactor rules match', () => {
      const tree: BuilderComponentInstance[] = [
        node('hero-1', 'webu_general_hero_01', { title: 'Hi' }),
      ];
      const result = processProjectComponents({ projectType: 'landing', componentTree: tree });
      expect(result.suggestions).toHaveLength(0);
      expect(result.updates).toHaveLength(0);
      expect(result.summary).toEqual([]);
    });
  });

  describe('applyProjectComponentUpdates', () => {
    it('applies patches to tree and returns new tree', () => {
      const tree: BuilderComponentInstance[] = [
        node('header-1', 'webu_header_01', { showSearch: true, searchMode: 'generic' }),
      ];
      const updates = [{ nodeId: 'header-1', patch: { showSearch: false, searchMode: 'none' } }];
      const next = applyProjectComponentUpdates(tree, updates);
      expect(next).toHaveLength(1);
      expect(next[0]!.props.showSearch).toBe(false);
      expect(next[0]!.props.searchMode).toBe('none');
      expect(tree[0]!.props.showSearch).toBe(true);
    });
  });

  describe('processAndApplyProjectComponents', () => {
    it('returns result and updated tree with refactors applied', () => {
      const tree: BuilderComponentInstance[] = [
        node('header-1', 'webu_header_01', { showSearch: true, searchMode: 'generic' }),
      ];
      const project = { projectType: 'business', componentTree: tree };
      const { result, updatedTree } = processAndApplyProjectComponents(project);
      expect(result.updates).toHaveLength(1);
      expect(updatedTree[0]!.props.showSearch).toBe(false);
      expect(updatedTree[0]!.props.searchMode).toBe('none');
    });
  });
});
