/**
 * AI Project Processor — processProjectComponents(project) tests.
 */

import { describe, it, expect } from 'vitest';
import { processProjectComponents, applyPropUpdates } from '../projectProcessor';

const headerNode = {
  id: 'header-1',
  componentKey: 'webu_header_01',
  props: { title: 'Site' },
  children: [],
} as const;

const heroNode = {
  id: 'hero-1',
  componentKey: 'webu_general_hero_01',
  props: {},
  children: [],
} as const;

const tree = [headerNode, heroNode] as any[];

describe('processProjectComponents', () => {
  it('step 1–2: analyzes project type and scans all components', () => {
    const result = processProjectComponents({
      projectType: 'ecommerce',
      componentTree: tree,
    });
    expect(result.projectType).toBe('ecommerce');
    expect(result.scannedNodeIds).toEqual(['header-1', 'hero-1']);
    expect(result.summary.some((s) => s.startsWith('Project type:'))).toBe(true);
    expect(result.summary.some((s) => s.includes('Scanned 2'))).toBe(true);
  });

  it('step 3: checks compatibility', () => {
    const result = processProjectComponents({
      projectType: 'ecommerce',
      componentTree: tree,
    });
    expect(result.compatibleNodeIds).toContain('header-1');
    expect(result.compatibleNodeIds).toContain('hero-1');
    expect(result.compatibleNodeIds.length).toBe(2);
  });

  it('step 4: unnecessary elements (incompatible nodes)', () => {
    const result = processProjectComponents({
      projectType: 'ecommerce',
      componentTree: tree,
    });
    expect(Array.isArray(result.unnecessaryElementIds)).toBe(true);
    expect(Array.isArray(result.incompatibleNodeIds)).toBe(true);
  });

  it('step 5–6: applies refactor rules and returns prop updates', () => {
    const result = processProjectComponents({
      projectType: 'ecommerce',
      componentTree: tree,
    });
    expect(result.refactorSuggestions.length).toBeGreaterThanOrEqual(1);
    const headerSuggestion = result.refactorSuggestions.find((s) => s.componentKey === 'webu_header_01');
    expect(headerSuggestion).toBeDefined();
    expect(headerSuggestion!.description).toContain('product search');
    expect(result.propUpdatesToApply.length).toBeGreaterThanOrEqual(1);
    const headerUpdate = result.propUpdatesToApply.find((u) => u.componentId === 'header-1');
    expect(headerUpdate).toBeDefined();
    expect(headerUpdate!.patch).toEqual({
      showSearch: true,
      searchMode: 'product',
      showCartIcon: true,
    });
  });

  it('business project: refactor removes search', () => {
    const result = processProjectComponents({
      projectType: 'business',
      componentTree: tree,
    });
    const headerUpdate = result.propUpdatesToApply.find((u) => u.componentId === 'header-1');
    expect(headerUpdate).toBeDefined();
    expect(headerUpdate!.patch.showSearch).toBe(false);
    expect(headerUpdate!.patch.searchMode).toBe('none');
    expect(headerUpdate!.patch.showCartIcon).toBe(false);
  });

  it('applyPropUpdates calls updater for each patch and returns counts', () => {
    const result = processProjectComponents({
      projectType: 'ecommerce',
      componentTree: tree,
    });
    let calls = 0;
    const updater = (id: string, payload: { patch: Record<string, unknown> }) => {
      calls += 1;
      expect(id).toBeDefined();
      expect(payload.patch).toBeDefined();
      return { ok: true };
    };
    const out = applyPropUpdates(result, updater);
    expect(calls).toBe(result.propUpdatesToApply.length);
    expect(out.applied).toBe(calls);
    expect(out.failed).toBe(0);
    expect(out.errors).toEqual([]);
  });
});
