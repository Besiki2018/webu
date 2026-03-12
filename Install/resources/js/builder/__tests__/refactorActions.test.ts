/**
 * Refactor Actions — tests for canonical action kinds and examples.
 */

import { describe, it, expect } from 'vitest';
import {
  REFACTOR_ACTION_KINDS,
  REFACTOR_ACTION_LABELS,
  REFACTOR_ACTION_DESCRIPTIONS,
  REFACTOR_ACTION_EXAMPLES,
  isRefactorActionKind,
} from '../refactorActions';

describe('refactorActions', () => {
  it('defines the five supported action kinds', () => {
    expect(REFACTOR_ACTION_KINDS).toEqual([
      'remove_element',
      'replace_element',
      'add_element',
      'modify_element_props',
      'restructure_layout',
    ]);
  });

  it('has a label for every action kind', () => {
    for (const kind of REFACTOR_ACTION_KINDS) {
      expect(REFACTOR_ACTION_LABELS[kind]).toBeDefined();
      expect(typeof REFACTOR_ACTION_LABELS[kind]).toBe('string');
    }
  });

  it('has a description for every action kind', () => {
    for (const kind of REFACTOR_ACTION_KINDS) {
      expect(REFACTOR_ACTION_DESCRIPTIONS[kind]).toBeDefined();
      expect(typeof REFACTOR_ACTION_DESCRIPTIONS[kind]).toBe('string');
    }
  });

  it('has example strings for every action kind', () => {
    expect(REFACTOR_ACTION_EXAMPLES.remove_element).toContain('remove search field');
    expect(REFACTOR_ACTION_EXAMPLES.replace_element).toContain('replace hero layout');
    expect(REFACTOR_ACTION_EXAMPLES.add_element).toContain('add product filters');
    expect(REFACTOR_ACTION_EXAMPLES.add_element).toContain('add booking widget');
    expect(REFACTOR_ACTION_EXAMPLES.modify_element_props).toBeDefined();
    expect(REFACTOR_ACTION_EXAMPLES.restructure_layout).toBeDefined();
  });

  it('isRefactorActionKind returns true for valid kinds', () => {
    expect(isRefactorActionKind('remove_element')).toBe(true);
    expect(isRefactorActionKind('add_element')).toBe(true);
  });

  it('isRefactorActionKind returns false for invalid values', () => {
    expect(isRefactorActionKind('remove_search')).toBe(false);
    expect(isRefactorActionKind(null)).toBe(false);
    expect(isRefactorActionKind(1)).toBe(false);
  });
});
