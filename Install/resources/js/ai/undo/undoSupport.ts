/**
 * Undo support for ChangeSets.
 * Every ChangeSet must be reversible: we store the previous state before applying.
 */
import type { ChangeSet } from '../changes/changeSet.schema';

/**
 * Snapshot of page state before applying a ChangeSet.
 * Store this when applying; use it to reverse the change.
 */
export interface PageStateSnapshot {
  /** Page identifier */
  pageId?: string | null;
  pageSlug?: string;
  /** Full content_json or sections + theme at time of change */
  sections: unknown[];
  theme?: Record<string, unknown> | null;
  /** Timestamp for ordering */
  at: number;
}

/**
 * One undo entry: the ChangeSet that was applied and the state before it.
 */
export interface UndoEntry {
  changeSet: ChangeSet;
  previousState: PageStateSnapshot;
}

/**
 * Creates a snapshot from current page state (e.g. sections + theme).
 */
export function createPageStateSnapshot(
  sections: unknown[],
  options: { theme?: Record<string, unknown> | null; pageId?: string | null; pageSlug?: string } = {}
): PageStateSnapshot {
  return {
    pageId: options.pageId ?? null,
    pageSlug: options.pageSlug,
    sections: JSON.parse(JSON.stringify(sections)),
    theme: options.theme ? { ...options.theme } : null,
    at: Date.now(),
  };
}

/**
 * Undo stack: push before apply, pop to get previous state to restore.
 */
export interface UndoStack {
  entries: UndoEntry[];
  maxSize: number;
}

export function createUndoStack(maxSize = 50): UndoStack {
  return { entries: [], maxSize };
}

export function pushUndo(stack: UndoStack, entry: UndoEntry): void {
  stack.entries.push(entry);
  if (stack.entries.length > stack.maxSize) {
    stack.entries.shift();
  }
}

export function popUndo(stack: UndoStack): UndoEntry | undefined {
  return stack.entries.pop();
}

export function canUndo(stack: UndoStack): boolean {
  return stack.entries.length > 0;
}
