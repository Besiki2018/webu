/**
 * Part 9 — Chat Compatibility: tests for mapping chat commands to schema paths.
 */
import {
  resolveChatPhraseToPath,
  allowedUpdatesFromEditableFields,
  type ChatPathResolution,
} from '../chatSchemaPaths';

describe('chatSchemaPaths', () => {
  const pricingEditableFields = [
    { key: 'title', type: 'text' },
    { key: 'plans', type: 'list' },
    { key: 'ctaButton', type: 'text' },
  ];

  describe('resolveChatPhraseToPath', () => {
    it('resolves "change pricing title" to path title', () => {
      const r = resolveChatPhraseToPath('change pricing title', { editableFields: pricingEditableFields });
      expect(r).not.toBeNull();
      expect((r as ChatPathResolution).path).toBe('title');
      expect((r as ChatPathResolution).type).toBe('text');
    });

    it('resolves "Change pricing title" (case insensitive)', () => {
      const r = resolveChatPhraseToPath('Change pricing title', { editableFields: pricingEditableFields });
      expect(r?.path).toBe('title');
    });

    it('resolves "update price" to plans or price when allowed', () => {
      const r = resolveChatPhraseToPath('update price', { editableFields: pricingEditableFields });
      expect(r).not.toBeNull();
      expect(['plans', 'price']).toContain((r as ChatPathResolution).path);
    });

    it('resolves "add fourth pricing plan" to plans with list hint', () => {
      const r = resolveChatPhraseToPath('add fourth pricing plan', { editableFields: pricingEditableFields });
      expect(r).not.toBeNull();
      expect((r as ChatPathResolution).path).toBe('plans');
      expect((r as ChatPathResolution).type).toBe('list');
      expect((r as ChatPathResolution).appendToList).toBe(true);
      expect((r as ChatPathResolution).listIndex).toBe(3);
    });

    it('resolves "add feature" to features when in editableFields', () => {
      const fields = [...pricingEditableFields, { key: 'features', type: 'list' }];
      const r = resolveChatPhraseToPath('add feature', { editableFields: fields });
      expect(r).not.toBeNull();
      expect((r as ChatPathResolution).path).toBe('features');
      expect((r as ChatPathResolution).appendToList).toBe(true);
    });

    it('returns null when phrase does not match', () => {
      const r = resolveChatPhraseToPath('delete everything', { editableFields: pricingEditableFields });
      expect(r).toBeNull();
    });

    it('respects editableFields: only returns path that exists in schema', () => {
      const onlyPlans = [{ key: 'plans', type: 'list' }];
      const r = resolveChatPhraseToPath('change pricing title', { editableFields: onlyPlans });
      expect(r).toBeNull();
      const r2 = resolveChatPhraseToPath('add plan', { editableFields: onlyPlans });
      expect(r2?.path).toBe('plans');
    });
  });

  describe('allowedUpdatesFromEditableFields', () => {
    it('maps editableFields to allowed path/type list', () => {
      const allowed = allowedUpdatesFromEditableFields(pricingEditableFields);
      expect(allowed).toHaveLength(3);
      expect(allowed.map((a) => a.path)).toEqual(['title', 'plans', 'ctaButton']);
      expect(allowed.map((a) => a.type)).toEqual(['text', 'list', 'text']);
    });
  });
});
