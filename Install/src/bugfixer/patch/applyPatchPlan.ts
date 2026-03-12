import * as fs from 'fs';
import * as path from 'path';
import type { PatchPlan } from './patchPlan.schema.js';
import { isForbidden } from '../safety/forbiddenPaths.js';

const AUDIT_BASE = path.join(process.cwd(), 'audit', 'bugfixer');
const PATCHES_BASE = path.join(AUDIT_BASE, 'patches');
const MAX_FILES = 5;
const MAX_DIFF_LINES = 200;

export type ApplyResult =
  | { ok: true; appliedPath: string; backup: Map<string, string> }
  | { ok: false; reason: string };

/**
 * Parse unified diff and apply to files. Reject if >5 files, >200 lines, or forbidden path.
 */
export function applyPatchPlan(bugId: string, plan: PatchPlan): ApplyResult {
  if (plan.changes.length > MAX_FILES) {
    return { ok: false, reason: `Too many files: ${plan.changes.length} (max ${MAX_FILES})` };
  }

  let totalDiffLines = 0;
  for (const c of plan.changes) {
    const lines = (c.diff || '').split('\n').length;
    totalDiffLines += lines;
    if (totalDiffLines > MAX_DIFF_LINES) {
      return { ok: false, reason: `Diff too large: ${totalDiffLines} lines (max ${MAX_DIFF_LINES})` };
    }
    const normalizedPath = path.normalize(c.file).replace(/\\/g, '/');
    if (isForbidden(normalizedPath)) {
      return { ok: false, reason: `Forbidden path: ${normalizedPath}` };
    }
  }

  const backup: Map<string, string> = new Map();
  const patchDir = path.join(PATCHES_BASE, bugId);
  if (!fs.existsSync(patchDir)) fs.mkdirSync(patchDir, { recursive: true });
  const appliedDiffPath = path.join(patchDir, 'applied.diff');

  try {
    const fullDiff: string[] = [];
    for (const change of plan.changes) {
      const filePath = path.resolve(process.cwd(), change.file);
      if (!backup.has(filePath) && fs.existsSync(filePath)) {
        backup.set(filePath, fs.readFileSync(filePath, 'utf-8'));
      }
      fullDiff.push(`--- ${change.file}\n+++ ${change.file}\n${change.diff}`);
      applyUnifiedDiff(filePath, change);
    }
    fs.writeFileSync(appliedDiffPath, fullDiff.join('\n---\n'), 'utf-8');
    return { ok: true, appliedPath: appliedDiffPath, backup };
  } catch (err) {
    rollback(backup);
    return {
      ok: false,
      reason: err instanceof Error ? err.message : String(err),
    };
  }
}

function applyUnifiedDiff(filePath: string, change: { type: string; diff: string }): void {
  if (change.type === 'delete') {
    if (fs.existsSync(filePath)) fs.unlinkSync(filePath);
    return;
  }
  const diff = change.diff;
  const lines = diff.split('\n');
  let currentLine = 0;
  let result: string[] = [];
  if (change.type === 'add' || !fs.existsSync(filePath)) {
    for (const line of lines) {
      if (line.startsWith('+') && !line.startsWith('+++')) result.push(line.slice(1));
    }
    fs.writeFileSync(filePath, result.join('\n'), 'utf-8');
    return;
  }
  const original = fs.readFileSync(filePath, 'utf-8').split('\n');
  let i = 0;
  while (i < lines.length) {
    const line = lines[i];
    if (line.startsWith('---') || line.startsWith('+++')) {
      i++;
      continue;
    }
    if (line.startsWith('+') && !line.startsWith('+++')) {
      result.push(line.slice(1));
      i++;
      continue;
    }
    if (line.startsWith('-')) {
      currentLine++;
      i++;
      continue;
    }
    if (line.startsWith(' ') || line === '') {
      result.push(currentLine < original.length ? original[currentLine] : line.slice(1));
      currentLine++;
      i++;
      continue;
    }
    i++;
  }
  while (currentLine < original.length) {
    result.push(original[currentLine]);
    currentLine++;
  }
  fs.writeFileSync(filePath, result.join('\n'), 'utf-8');
}

export function rollback(backup: Map<string, string>): void {
  for (const [filePath, content] of backup) {
    fs.writeFileSync(filePath, content, 'utf-8');
  }
}

/**
 * Load last applied diff from audit and revert those changes (simple restore from backup if we stored it).
 * Caller should pass the backup map they got before apply, or we read applied.diff and reverse.
 */
export function rollbackFromBackup(backup: Map<string, string>): void {
  rollback(backup);
}
