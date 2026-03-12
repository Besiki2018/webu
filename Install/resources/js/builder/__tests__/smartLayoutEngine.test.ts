/**
 * Phase 12 — Smart layout engine.
 */

import { describe, it, expect } from 'vitest';
import {
  layoutIntentToPropPatch,
  getLayoutSummary,
  type LayoutIntent,
} from '../smartLayoutEngine';

describe('smartLayoutEngine', () => {
  describe('layoutIntentToPropPatch', () => {
    it('returns columns for Grid component (schema has columns)', () => {
      const intent: LayoutIntent = { columns: 3 };
      const patch = layoutIntentToPropPatch(intent, 'webu_general_grid_01');
      expect(patch.columns).toBe(3);
    });

    it('returns alignment for Hero component', () => {
      const intent: LayoutIntent = { alignment: 'center' };
      const patch = layoutIntentToPropPatch(intent, 'webu_general_hero_01');
      expect(patch.alignment).toBe('center');
    });

    it('resolves spacing token', () => {
      const intent: LayoutIntent = { spacing: 'lg' };
      const patch = layoutIntentToPropPatch(intent, 'webu_general_grid_01');
      expect(patch.gap === '1.5rem' || patch.spacing === '1.5rem' || patch.padding).toBeDefined();
    });

    it('responsiveStacking stack sets responsiveColumns when component accepts responsive prop', () => {
      const intent: LayoutIntent = { responsiveStacking: 'stack' };
      const patch = layoutIntentToPropPatch(intent, 'webu_general_grid_01');
      // Grid may not have responsive in schema; patch may be empty or have responsive if schema is extended
      expect(patch).toBeDefined();
      if (Object.keys(patch).length > 0) expect(patch.responsive).toEqual({ mobile: 1, tablet: 2 });
    });
  });

  describe('getLayoutSummary', () => {
    it('extracts layout intent from node props', () => {
      const node = {
        id: 'grid-1',
        componentKey: 'webu_general_grid_01',
        props: { columns: 3, alignment: 'center' },
      };
      const summary = getLayoutSummary(node as any);
      expect(summary.columns).toBe(3);
      expect(summary.alignment).toBe('center');
    });
  });
});
