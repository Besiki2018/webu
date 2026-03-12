/**
 * CLI: create a ticket for a bug (e.g. medium severity = ticket only).
 * Usage: npx tsx src/bugfixer/cli/createTicket.ts <bugId>
 */
import * as fs from 'fs';
import * as path from 'path';
import { buildReproPack } from '../repro/buildReproPack.js';
import { createTicket } from '../tickets/createTicket.js';

const AUDIT_BASE = path.join(process.cwd(), 'audit', 'bugfixer');

function loadEvent(bugId: string): Record<string, unknown> | null {
  const p = path.join(AUDIT_BASE, 'events', `${bugId}.json`);
  if (!fs.existsSync(p)) return null;
  try {
    return JSON.parse(fs.readFileSync(p, 'utf-8')) as Record<string, unknown>;
  } catch {
    return null;
  }
}

const bugId = process.argv[2];
if (!bugId) {
  console.error('Usage: npx tsx src/bugfixer/cli/createTicket.ts <bugId>');
  process.exit(1);
}
const event = loadEvent(bugId);
if (!event) {
  console.error('Bug event not found:', bugId);
  process.exit(1);
}
const pack = buildReproPack(event as import('../types.js').BugEvent);
const ticketPath = createTicket(bugId, {
  reproDir: pack.reproDir,
  verificationLogsDir: path.join(AUDIT_BASE, 'verify', bugId),
});
console.log(ticketPath);
