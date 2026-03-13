/**
 * AI Component Refactor Engine — tests for project-context refactor suggestions.
 */

import { describe, it, expect } from 'vitest';
import {
  analyzeNodeForRefactor,
  analyzeTreeForRefactor,
  suggestionToPatch,
  getRefactorPatchPayload,
  componentSupportsHeaderSearchRefactor,
} from '../aiRefactorEngine';
import { REFACTOR_ACTION_KINDS } from '../refactorActions';

describe('AI Component Refactor Engine', () => {
  describe('analyzeNodeForRefactor', () => {
    it('suggests remove search for business when header has search', () => {
      const node = {
        id: 'header-1',
        componentKey: 'webu_header_01',
        props: { showSearch: true, searchMode: 'generic', showCartIcon: false },
      };
      const suggestions = analyzeNodeForRefactor('business', node as any);
      expect(suggestions).toHaveLength(1);
      expect(suggestions[0]!.action).toBe('remove_search');
      expect(suggestions[0]!.actionKind).toBe('remove_element');
      expect(suggestions[0]!.propPatch).toEqual({ showSearch: false, searchMode: 'none' });
    });

    it('suggests replace with product search + cart for ecommerce when header has search', () => {
      const node = {
        id: 'header-1',
        componentKey: 'webu_header_01',
        props: { showSearch: true, searchMode: 'generic', showCartIcon: false },
      };
      const suggestions = analyzeNodeForRefactor('ecommerce', node as any);
      expect(suggestions).toHaveLength(1);
      expect(suggestions[0]!.action).toBe('replace_with_product_search_and_cart');
      expect(suggestions[0]!.actionKind).toBe('replace_element');
      expect(suggestions[0]!.propPatch).toEqual({
        showSearch: true,
        searchMode: 'product',
        showCartIcon: true,
        showWishlistIcon: true,
      });
    });

    it('suggests add product search + cart for ecommerce when header has no search', () => {
      const node = {
        id: 'header-1',
        componentKey: 'webu_header_01',
        props: { showSearch: false, searchMode: 'none', showCartIcon: false },
      };
      const suggestions = analyzeNodeForRefactor('ecommerce', node as any);
      expect(suggestions).toHaveLength(1);
      expect(suggestions[0]!.action).toBe('add_product_search_and_cart');
      expect(suggestions[0]!.actionKind).toBe('add_element');
      expect(suggestions[0]!.propPatch.showCartIcon).toBe(true);
      expect(suggestions[0]!.propPatch.showWishlistIcon).toBe(true);
      expect(suggestions[0]!.propPatch.searchMode).toBe('product');
    });

    it('returns no suggestion for business when header has no search', () => {
      const node = {
        id: 'header-1',
        componentKey: 'webu_header_01',
        props: { showSearch: false, searchMode: 'none' },
      };
      const suggestions = analyzeNodeForRefactor('business', node as any);
      expect(suggestions).toHaveLength(0);
    });

    it('returns no suggestion for non-header components', () => {
      const node = {
        id: 'hero-1',
        componentKey: 'webu_general_hero_01',
        props: { title: 'Hello' },
      };
      const suggestions = analyzeNodeForRefactor('ecommerce', node as any);
      expect(suggestions).toHaveLength(0);
    });
  });

  describe('analyzeTreeForRefactor', () => {
    it('collects suggestions from all matching nodes in tree', () => {
      const tree = [
        {
          id: 'header-1',
          componentKey: 'webu_header_01',
          props: { showSearch: true, searchMode: 'generic' },
        },
        {
          id: 'hero-1',
          componentKey: 'webu_general_hero_01',
          props: {},
        },
      ];
      const suggestions = analyzeTreeForRefactor('business', tree as any);
      expect(suggestions).toHaveLength(1);
      expect(suggestions[0]!.nodeId).toBe('header-1');
      expect(suggestions[0]!.action).toBe('remove_search');
    });
  });

  describe('suggestionToPatch and getRefactorPatchPayload', () => {
    it('suggestionToPatch returns copy of propPatch', () => {
      const suggestion = {
        nodeId: 'header-1',
        componentKey: 'webu_header_01',
        action: 'remove_search' as const,
        actionKind: 'remove_element' as const,
        reason: 'Test',
        label: 'Remove search',
        propPatch: { showSearch: false, searchMode: 'none' },
      };
      const patch = suggestionToPatch(suggestion);
      expect(patch).toEqual({ showSearch: false, searchMode: 'none' });
      expect(patch).not.toBe(suggestion.propPatch);
    });

    it('getRefactorPatchPayload returns componentId and patch', () => {
      const suggestion = {
        nodeId: 'header-1',
        componentKey: 'webu_header_01',
        action: 'remove_search' as const,
        actionKind: 'remove_element' as const,
        reason: 'Test',
        label: 'Remove search',
        propPatch: { showSearch: false },
      };
      const payload = getRefactorPatchPayload(suggestion);
      expect(payload.componentId).toBe('header-1');
      expect(payload.patch).toEqual({ showSearch: false });
    });
  });

  describe('componentSupportsHeaderSearchRefactor', () => {
    it('returns false for webu_header_01 until header search props are exposed through the current schema shape', () => {
      expect(componentSupportsHeaderSearchRefactor('webu_header_01')).toBe(false);
    });
    it('returns false for hero', () => {
      expect(componentSupportsHeaderSearchRefactor('webu_general_hero_01')).toBe(false);
    });
  });

  describe('refactor action kinds', () => {
    it('suggestions use canonical actionKind from refactorActions', () => {
      const node = {
        id: 'header-1',
        componentKey: 'webu_header_01',
        props: { showSearch: true, searchMode: 'generic' },
      };
      const forBusiness = analyzeNodeForRefactor('business', node as any);
      const forEcommerce = analyzeNodeForRefactor('ecommerce', node as any);
      expect(forBusiness[0]!.actionKind).toBe('remove_element');
      expect(forEcommerce[0]!.actionKind).toBe('replace_element');
      expect(REFACTOR_ACTION_KINDS).toContain(forBusiness[0]!.actionKind);
      expect(REFACTOR_ACTION_KINDS).toContain(forEcommerce[0]!.actionKind);
    });
  });
});
