/**
 * Phase 11 — Design token system.
 */

import { describe, it, expect } from 'vitest';
import {
  getTokenValue,
  resolveToken,
  getDesignTokensAsCssVars,
  DESIGN_TOKEN_COLORS,
  DESIGN_TOKEN_SPACING,
  DESIGN_TOKEN_CATEGORIES,
} from '../designTokens';

describe('designTokens', () => {
  describe('getTokenValue', () => {
    it('resolves color primary', () => {
      expect(getTokenValue('colors', 'primary')).toBe(DESIGN_TOKEN_COLORS.primary);
    });
    it('resolves spacing lg', () => {
      expect(getTokenValue('spacing', 'lg')).toBe(DESIGN_TOKEN_SPACING.lg);
    });
    it('returns undefined for unknown category or name', () => {
      expect(getTokenValue('colors', 'unknown')).toBeUndefined();
    });
  });

  describe('resolveToken', () => {
    it('resolves color.primary (alias for colors)', () => {
      expect(resolveToken('color.primary')).toBe(DESIGN_TOKEN_COLORS.primary);
    });
    it('resolves spacing.lg', () => {
      expect(resolveToken('spacing.lg')).toBe(DESIGN_TOKEN_SPACING.lg);
    });
    it('returns undefined for invalid path', () => {
      expect(resolveToken('invalid')).toBeUndefined();
    });
  });

  describe('getDesignTokensAsCssVars', () => {
    it('returns CSS custom properties for all categories', () => {
      const vars = getDesignTokensAsCssVars();
      expect(vars['--color-primary']).toBe(DESIGN_TOKEN_COLORS.primary);
      expect(vars['--spacing-lg']).toBe(DESIGN_TOKEN_SPACING.lg);
      expect(Object.keys(vars).length).toBeGreaterThan(10);
    });
  });
});
