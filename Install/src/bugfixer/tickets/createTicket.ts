import * as fs from 'fs';
import * as path from 'path';
import type { BugEvent } from '../types.js';

const AUDIT_BASE = path.join(process.cwd(), 'audit', 'bugfixer');
const TICKETS_DIR = path.join(AUDIT_BASE, 'tickets');

export type CreateTicketOptions = {
  lastVerificationFailure?: string;
  reproDir?: string;
  verificationLogsDir?: string;
};

function loadBugEvent(bugId: string): BugEvent | null {
  const p = path.join(AUDIT_BASE, 'events', `${bugId}.json`);
  if (!fs.existsSync(p)) return null;
  try {
    return JSON.parse(fs.readFileSync(p, 'utf-8')) as BugEvent;
  } catch {
    return null;
  }
}

/**
 * Write audit/bugfixer/tickets/{bugId}.md with summary, severity, steps to reproduce,
 * expected vs actual, stack excerpt, suspected files, verification outputs, next steps.
 */
export function createTicket(
  bugId: string,
  options: CreateTicketOptions = {}
): string {
  if (!fs.existsSync(TICKETS_DIR)) fs.mkdirSync(TICKETS_DIR, { recursive: true });

  const event = loadBugEvent(bugId);
  const {
    lastVerificationFailure,
    reproDir,
    verificationLogsDir,
  } = options;

  const lines: string[] = [
    `# Ticket: ${bugId}`,
    '',
    `**Severity:** ${event?.severity ?? 'unknown'} | **Frequency:** ${event?.frequency ?? 1}`,
    '',
    '## Summary',
    event?.event ?? '(no event message)',
    '',
    '## Steps to reproduce',
    `Repro pack: ${reproDir ?? `audit/bugfixer/repro/${bugId}/`}`,
    '1. See instructions.md in repro pack.',
    event?.route ? `2. Route: ${event.route}` : '',
    '',
    '## Expected vs actual',
    '- **Expected:** No error.',
    `- **Actual:** ${event?.event ?? 'See stack trace.'}`,
    '',
    '## Stack trace (excerpt)',
    '```',
    event?.stack ?? '(none)',
    '```',
    '',
    '## Suspected files',
    event?.stack ? extractFileRefs(event.stack) : '(none)',
    '',
    '## Verification outputs',
    verificationLogsDir ? `Logs: ${verificationLogsDir}` : 'N/A',
    lastVerificationFailure ? `Last failure:\n${lastVerificationFailure}` : '',
    '',
    '## Recommended next steps',
    '1. Reproduce locally using repro pack.',
    '2. Identify root cause from stack and suspected files.',
    '3. Propose minimal patch and run lint/typecheck/unit/build/e2e.',
    '4. Do not disable validations or bypass tenant isolation.',
  ].filter(Boolean);

  const ticketPath = path.join(TICKETS_DIR, `${bugId}.md`);
  fs.writeFileSync(ticketPath, lines.join('\n'), 'utf-8');
  return ticketPath;
}

function extractFileRefs(stack: string): string {
  const re = /(?:\(|\s)([^\s)]+\.(?:ts|tsx|js|jsx|php))(?::\d+)/g;
  const seen = new Set<string>();
  let m: RegExpExecArray | null;
  while ((m = re.exec(stack)) !== null) {
    seen.add(m[1]);
  }
  return [...seen].slice(0, 10).join('\n') || '(none)';
}
