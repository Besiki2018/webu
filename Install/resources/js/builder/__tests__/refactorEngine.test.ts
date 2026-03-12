/**
 * AI Component Refactor Engine — tests.
 * Verifies analyzeComponentRefactors and getRefactorPatchesForTree for project-type-driven refactors.
 */

import { describe, it, expect } from 'vitest';
import {
  analyzeComponentRefactors,
  analyzeNodeRefactor,
  getRefactorPatchesForTree,
  getRefactorPropPatch,
} from '../refactorEngine';

const headerNode = {
  id: 'header-1',
  componentKey: 'webu_header_01',
  props: { title: 'Site' },
} as const;

const heroNode = {
  id: 'hero-1',
  componentKey: 'webu_general_hero_01',
  props: {},
} as const;

describe('refactorEngine', () => {
  it('analyzeNodeRefactor returns suggestion for header + ecommerce', () => {
    const s = analyzeNodeRefactor('ecommerce', headerNode as any);
    expect(s).not.toBeNull();
    expect(s!.componentId).toBe('header-1');
    expect(s!.componentKey).toBe('webu_header_01');
    expect(s!.projectType).toBe('ecommerce');
    expect(s!.description).toContain('product search');
    expect(s!.propPatch).toEqual({
      showSearch: true,
      searchMode: 'product',
      showCartIcon: true,
    });
  });

  it('analyzeNodeRefactor returns suggestion for header + business (remove search)', () => {
    const s = analyzeNodeRefactor('business', headerNode as any);
    expect(s).not.toBeNull();
    expect(s!.propPatch).toEqual({
      showSearch: false,
      searchMode: 'none',
      showCartIcon: false,
    });
  });

  it('analyzeNodeRefactor returns null for hero (no rule)', () => {
    const s = analyzeNodeRefactor('ecommerce', heroNode as any);
    expect(s).toBeNull();
  });

  it('analyzeComponentRefactors returns suggestions for tree with header', () => {
    const tree = [headerNode, heroNode] as any[];
    const suggestions = analyzeComponentRefactors('ecommerce', tree);
    expect(suggestions.length).toBe(1);
    expect(suggestions[0].componentId).toBe('header-1');
    expect(suggestions[0].propPatch?.showCartIcon).toBe(true);
  });

  it('getRefactorPropPatch returns patch for header + ecommerce', () => {
    const patch = getRefactorPropPatch('ecommerce', headerNode as any);
    expect(patch).not.toBeNull();
    expect(patch!.showSearch).toBe(true);
    expect(patch!.searchMode).toBe('product');
    expect(patch!.showCartIcon).toBe(true);
  });

  it('getRefactorPatchesForTree returns one patch for single header', () => {
    const tree = [headerNode] as any[];
    const patches = getRefactorPatchesForTree('ecommerce', tree);
    expect(patches.length).toBe(1);
    expect(patches[0].componentId).toBe('header-1');
    expect(patches[0].patch).toEqual({
      showSearch: true,
      searchMode: 'product',
      showCartIcon: true,
    });
  });

  it('suggestion includes actionType modify_element_props and payload for header ecommerce', () => {
    const s = analyzeNodeRefactor('ecommerce', headerNode as any);
    expect(s).not.toBeNull();
    expect(s!.actionType).toBe('modify_element_props');
    expect(s!.payload).toBeDefined();
    expect(s!.payload!.action).toBe('modify_element_props');
    expect((s!.payload as any).targetId).toBe('header-1');
    expect((s!.payload as any).patch).toEqual({
      showSearch: true,
      searchMode: 'product',
      showCartIcon: true,
    });
  });
});
