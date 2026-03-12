import { describe, it, expect } from 'vitest';
import { validateChangeSet, parseChangeSet, changeSetSchema } from '../changes/changeSet.schema';

describe('changeSetSchema', () => {
  it('accepts valid ChangeSet with updateSection', () => {
    const data = {
      operations: [{ op: 'updateSection', sectionId: 'hero-1', patch: { headline: 'New' } }],
      summary: ['Updated hero'],
    };
    const result = validateChangeSet(data);
    expect(result.success).toBe(true);
    if (result.success) {
      expect(result.data.operations).toHaveLength(1);
      expect(result.data.operations[0].op).toBe('updateSection');
      expect(result.data.summary).toEqual(['Updated hero']);
    }
  });

  it('accepts valid ChangeSet with insertSection', () => {
    const data = {
      operations: [
        { op: 'insertSection', sectionType: 'pricing', afterSectionId: 'hero-1' },
      ],
      summary: ['Added pricing section'],
    };
    const result = validateChangeSet(data);
    expect(result.success).toBe(true);
  });

  it('accepts valid ChangeSet with deleteSection and reorderSection', () => {
    const data = {
      operations: [
        { op: 'deleteSection', sectionId: 'test-1' },
        { op: 'reorderSection', sectionId: 'hero-1', toIndex: 0 },
      ],
      summary: ['Removed section', 'Reordered'],
    };
    const result = validateChangeSet(data);
    expect(result.success).toBe(true);
  });

  it('accepts updateTheme and translatePage', () => {
    const data = {
      operations: [
        { op: 'updateTheme', patch: { primary: '#1e3a8a' } },
        { op: 'translatePage', targetLocale: 'ka' },
      ],
      summary: ['Theme and language updated'],
    };
    const result = validateChangeSet(data);
    expect(result.success).toBe(true);
  });

  it('rejects invalid op type', () => {
    const data = {
      operations: [{ op: 'invalidOp', sectionId: 'x' }],
      summary: [],
    };
    const result = validateChangeSet(data);
    expect(result.success).toBe(false);
  });

  it('rejects missing operations', () => {
    const result = validateChangeSet({ summary: [] });
    expect(result.success).toBe(false);
  });

  it('rejects missing summary', () => {
    const result = validateChangeSet({
      operations: [{ op: 'updateSection', sectionId: 'x', patch: {} }],
    });
    expect(result.success).toBe(false);
  });

  it('accepts empty operations array', () => {
    const result = validateChangeSet({ operations: [], summary: [] });
    expect(result.success).toBe(true);
    if (result.success) {
      expect(result.data.operations).toHaveLength(0);
      expect(result.data.summary).toEqual([]);
    }
  });

  it('parseChangeSet throws on invalid', () => {
    expect(() => parseChangeSet(null)).toThrow();
    expect(() => parseChangeSet({})).toThrow();
  });
});
