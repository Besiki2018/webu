import { describe, it, expect } from 'vitest';
import {
  createPageStateSnapshot,
  createUndoStack,
  pushUndo,
  popUndo,
  canUndo,
} from '../undo/undoSupport';

describe('undoSupport', () => {
  it('createPageStateSnapshot deep-clones sections', () => {
    const sections = [{ id: '1', type: 'hero', props: { headline: 'Hi' } }];
    const snap = createPageStateSnapshot(sections, { pageSlug: 'home' });
    expect(snap.sections).toHaveLength(1);
    expect((snap.sections as { id: string }[])[0].id).toBe('1');
    expect(snap.sections).not.toBe(sections);
    expect(snap.at).toBeGreaterThan(0);
    expect(snap.pageSlug).toBe('home');
  });

  it('pushUndo and popUndo roundtrip', () => {
    const stack = createUndoStack(5);
    expect(canUndo(stack)).toBe(false);
    const snap = createPageStateSnapshot([], {});
    pushUndo(stack, {
      changeSet: { operations: [], summary: [] },
      previousState: snap,
    });
    expect(canUndo(stack)).toBe(true);
    const entry = popUndo(stack);
    expect(entry).toBeDefined();
    expect(entry!.previousState).toBe(snap);
    expect(canUndo(stack)).toBe(false);
  });

  it('respects maxSize', () => {
    const stack = createUndoStack(2);
    for (let i = 0; i < 3; i++) {
      pushUndo(stack, {
        changeSet: { operations: [], summary: [] },
        previousState: createPageStateSnapshot([], { at: i }),
      });
    }
    expect(stack.entries).toHaveLength(2);
    popUndo(stack);
    popUndo(stack);
    expect(popUndo(stack)).toBeUndefined();
  });
});
