import * as fs from 'fs';
import * as path from 'path';
import * as crypto from 'crypto';
import type { BugEvent, RawErrorSource } from '../types.js';
import { redactObject, redactString } from '../safety/redact.js';

const AUDIT_BASE = path.join(process.cwd(), 'audit', 'bugfixer');
const EVENTS_DIR = path.join(AUDIT_BASE, 'events');

function ensureDir(dir: string): void {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

function topStackFrames(stack: string, n = 5): string {
  const lines = (stack || '').split('\n').slice(0, n);
  return lines.join('\n');
}

function hashDedup(event: string, stackTop: string, route: string): string {
  const payload = `${event}\n${stackTop}\n${route}`;
  return crypto.createHash('sha256').update(payload).digest('hex').slice(0, 16);
}

function severityFromSource(raw: RawErrorSource): BugEvent['severity'] {
  if (raw.type === 'system_log' && (raw as { level?: string }).level === 'ERROR') return 'high';
  if (raw.type === 'ai') return 'medium';
  if (raw.type === 'performance') return 'medium';
  return 'medium';
}

function sourceFromRaw(raw: RawErrorSource): BugEvent['source'] {
  switch (raw.type) {
    case 'system_log': return 'backend';
    case 'frontend': return 'frontend';
    case 'ai': return 'ai';
    case 'e2e': return 'e2e';
    case 'performance': return 'backend';
    default: return 'backend';
  }
}

/**
 * Normalize a raw error into a BugEvent, redact secrets, compute dedup key, and optionally merge with existing.
 */
export function normalizeError(raw: RawErrorSource, overrides?: Partial<BugEvent>): BugEvent {
  const event = typeof (raw as { message?: string }).message === 'string'
    ? (raw as { message: string }).message
    : JSON.stringify(raw);
  const stack = (raw as { stack?: string }).stack ?? '';
  const stackTop = topStackFrames(stack);
  const route =
    (raw as { route?: string }).route ??
    (raw as { url?: string }).url ??
    (raw as { context?: { route?: string } }).context?.route ??
    '';

  const dedupKey = hashDedup(redactString(event), stackTop, route);
  const bugId = `bug_${new Date().toISOString().slice(0, 10).replace(/-/g, '')}_${dedupKey}`;

  const severity = overrides?.severity ?? severityFromSource(raw);
  const source = overrides?.source ?? sourceFromRaw(raw);

  const context = (raw as { context?: Record<string, unknown> }).context
    ? redactObject((raw as { context: Record<string, unknown> }).context)
    : undefined;

  return {
    bugId,
    timestamp: new Date().toISOString(),
    severity,
    source,
    tenantId: (raw as { tenantId?: string }).tenantId ?? null,
    websiteId: (raw as { websiteId?: string }).websiteId ?? null,
    userId: (raw as { userId?: string }).userId ? '[REDACTED]' : null,
    route: route || null,
    event: redactString(event),
    stack: stack ? redactString(stack) : null,
    context,
    dedupKey,
    frequency: 1,
    ...overrides,
  };
}

/**
 * Store BugEvent to audit/bugfixer/events/{bugId}.json.
 * If a file with same dedupKey exists, merge (increment frequency, update timestamp).
 */
export function storeBugEvent(bugEvent: BugEvent): BugEvent {
  ensureDir(EVENTS_DIR);

  const existingPath = path.join(EVENTS_DIR, `${bugEvent.bugId}.json`);
  let merged = bugEvent;

  if (fs.existsSync(existingPath)) {
    try {
      const existing = JSON.parse(fs.readFileSync(existingPath, 'utf-8')) as BugEvent;
      merged = {
        ...existing,
        timestamp: bugEvent.timestamp,
        frequency: (existing.frequency ?? 1) + 1,
      };
    } catch {
      // overwrite on parse error
    }
  }

  fs.writeFileSync(
    path.join(EVENTS_DIR, `${merged.bugId}.json`),
    JSON.stringify(merged, null, 2),
    'utf-8'
  );
  return merged;
}

/**
 * Find existing event by dedupKey to merge.
 */
export function findExistingByDedupKey(dedupKey: string): BugEvent | null {
  if (!fs.existsSync(EVENTS_DIR)) return null;
  const files = fs.readdirSync(EVENTS_DIR);
  for (const f of files) {
    if (!f.endsWith('.json')) continue;
    try {
      const content = JSON.parse(fs.readFileSync(path.join(EVENTS_DIR, f), 'utf-8')) as BugEvent;
      if (content.dedupKey === dedupKey) return content;
    } catch {
      // skip
    }
  }
  return null;
}

/**
 * Intake: normalize, dedup, store. Returns the stored BugEvent.
 */
export function intakeError(raw: RawErrorSource, overrides?: Partial<BugEvent>): BugEvent {
  const normalized = normalizeError(raw, overrides);
  const existing = normalized.dedupKey ? findExistingByDedupKey(normalized.dedupKey) : null;
  if (existing) {
    const merged: BugEvent = {
      ...existing,
      timestamp: normalized.timestamp,
      frequency: (existing.frequency ?? 1) + 1,
    };
    fs.writeFileSync(
      path.join(EVENTS_DIR, `${existing.bugId}.json`),
      JSON.stringify(merged, null, 2),
      'utf-8'
    );
    return merged;
  }
  return storeBugEvent(normalized);
}
