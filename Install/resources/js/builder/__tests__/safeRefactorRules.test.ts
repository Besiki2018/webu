/**
 * Safe Refactor Rules — never delete components; only modify props, children, variants; suggest replacement when unsafe.
 */

import { describe, it, expect } from 'vitest';
import {
  SAFE_MODIFICATION_TYPES,
  SAFE_REFACTOR_POLICY,
  SAFE_MODIFICATION_DESCRIPTIONS,
  isAllowedModification,
  isSafeRefactorSuggestion,
} from '../safeRefactorRules';

describe('safeRefactorRules', () => {
  describe('policy constants', () => {
    it('never delete components is true', () => {
      expect(SAFE_REFACTOR_POLICY.neverDeleteComponents).toBe(true);
    });
    it('allowed modifications are props, child_elements, layout_variants', () => {
      expect(SAFE_MODIFICATION_TYPES).toContain('props');
      expect(SAFE_MODIFICATION_TYPES).toContain('child_elements');
      expect(SAFE_MODIFICATION_TYPES).toContain('layout_variants');
      expect(SAFE_REFACTOR_POLICY.allowedModifications).toEqual([
        'props',
        'child_elements',
        'layout_variants',
      ]);
    });
    it('suggest replacement when unsafe is true', () => {
      expect(SAFE_REFACTOR_POLICY.suggestReplacementWhenUnsafe).toBe(true);
    });
  });

  describe('SAFE_MODIFICATION_DESCRIPTIONS', () => {
    it('has a description for each allowed type', () => {
      for (const t of SAFE_MODIFICATION_TYPES) {
        expect(SAFE_MODIFICATION_DESCRIPTIONS[t]).toBeDefined();
        expect(typeof SAFE_MODIFICATION_DESCRIPTIONS[t]).toBe('string');
      }
    });
  });

  describe('isAllowedModification', () => {
    it('returns true for props, child_elements, layout_variants', () => {
      expect(isAllowedModification('props')).toBe(true);
      expect(isAllowedModification('child_elements')).toBe(true);
      expect(isAllowedModification('layout_variants')).toBe(true);
    });
    it('returns false for delete_component and other types', () => {
      expect(isAllowedModification('delete_component')).toBe(false);
      expect(isAllowedModification('replace_entire_tree')).toBe(false);
    });
  });

  describe('isSafeRefactorSuggestion', () => {
    it('returns false when deleteComponent is true', () => {
      expect(isSafeRefactorSuggestion({ deleteComponent: true })).toBe(false);
    });
    it('returns true when only propPatch is present with keys', () => {
      expect(isSafeRefactorSuggestion({ propPatch: { showSearch: false } })).toBe(true);
    });
    it('returns false when no propPatch and no other safe modification', () => {
      expect(isSafeRefactorSuggestion({})).toBe(false);
      expect(isSafeRefactorSuggestion({ propPatch: {} })).toBe(false);
    });
  });
});
