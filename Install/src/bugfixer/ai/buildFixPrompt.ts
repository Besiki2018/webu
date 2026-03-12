import * as fs from 'fs';
import * as path from 'path';
import type { BugEvent } from '../types.js';

const MAX_SNIPPET_LINES = 80;
const MAX_FILES = 4;

export type FixPromptInput = {
  bugEvent: BugEvent;
  reproSpecContent?: string | null;
  lastVerificationFailure?: string | null;
  attemptNumber: 1 | 2;
};

/**
 * Resolve project path (Install/ is cwd when running bugfixer).
 */
function projectPath(relative: string): string {
  const base = process.cwd();
  return path.join(base, relative);
}

/**
 * Read file and return trimmed snippet (first N lines) for context.
 */
function readSnippet(filePath: string, maxLines = MAX_SNIPPET_LINES): string | null {
  const full = projectPath(filePath);
  if (!fs.existsSync(full)) return null;
  try {
    const content = fs.readFileSync(full, 'utf-8');
    const lines = content.split('\n');
    const slice = lines.length > maxLines ? lines.slice(0, maxLines).join('\n') + '\n...' : content;
    return slice;
  } catch {
    return null;
  }
}

/**
 * Extract file paths from stack trace (e.g. "at fn (file:///path/to/file.ts:10:2)" or "at /path/file.ts:10").
 */
function filesFromStack(stack: string | null | undefined): string[] {
  if (!stack) return [];
  const out: string[] = [];
  const re = /(?:\s+at\s+.*?\s+\()?(?:file:\/\/)?([^\s:]+\.(?:ts|tsx|js|jsx|php))(?::\d+)/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(stack)) !== null) {
    let p = m[1];
    if (p.startsWith('/')) {
      const cwd = process.cwd();
      if (p.startsWith(cwd)) p = p.slice(cwd.length).replace(/^\//, '');
    }
    if (p && !out.includes(p)) out.push(p);
  }
  return out.slice(0, MAX_FILES);
}

/**
 * Build compact prompt for AI: BugEvent, stack, 2–4 file snippets, repro spec, last failure.
 * No secrets, no huge history.
 */
export function buildFixPrompt(input: FixPromptInput): string {
  const { bugEvent, reproSpecContent, lastVerificationFailure, attemptNumber } = input;
  const sections: string[] = [];

  sections.push('## BugEvent (JSON)');
  sections.push(JSON.stringify(bugEvent, null, 2));

  sections.push('\n## Stack (top frames)');
  sections.push(bugEvent.stack || '(no stack)');

  const files = filesFromStack(bugEvent.stack);
  for (const f of files) {
    const snippet = readSnippet(f);
    if (snippet) {
      sections.push(`\n## File: ${f}`);
      sections.push('```');
      sections.push(snippet);
      sections.push('```');
    }
  }

  if (reproSpecContent) {
    sections.push('\n## Repro spec (repro.spec.ts)');
    sections.push('```');
    sections.push(reproSpecContent.slice(0, 2000));
    sections.push('```');
  }

  if (attemptNumber === 2 && lastVerificationFailure) {
    sections.push('\n## Last verification failure (attempt 1)');
    sections.push(lastVerificationFailure.slice(0, 1500));
  }

  sections.push('\n## Instructions');
  sections.push('Return a single JSON object only (no markdown, no explanation). Schema: PatchPlan.');
  sections.push('PatchPlan: { summary, rootCause, changes: [{ file, type: "edit"|"add"|"delete", diff (unified) }], testsToRun: string[], risk: "low"|"medium"|"high", rollbackPlan }');
  sections.push('Max 5 files, max 200 diff lines total. Do not touch auth/tenancy/secrets.');

  return sections.join('\n');
}
