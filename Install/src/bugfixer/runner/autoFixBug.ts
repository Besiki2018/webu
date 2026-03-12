import * as fs from 'fs';
import * as path from 'path';
import type { BugEvent } from '../types.js';
import { buildReproPack } from '../repro/buildReproPack.js';
import { buildFixPrompt } from '../ai/buildFixPrompt.js';
import { parsePatchPlanSafe, type PatchPlan } from '../patch/patchPlan.schema.js';
import { applyPatchPlan, rollback } from '../patch/applyPatchPlan.js';
import { runVerification } from '../verify/runVerification.js';
import { createTicket } from '../tickets/createTicket.js';

const AUDIT_BASE = path.join(process.cwd(), 'audit', 'bugfixer');
const EVENTS_DIR = path.join(AUDIT_BASE, 'events');

export type AutoFixOptions = {
  /** If provided, called to get PatchPlan from the built prompt. Return null to skip fix (e.g. no AI). */
  getPatchPlan?: (prompt: string, attempt: 1 | 2) => Promise<PatchPlan | null>;
};

export type AutoFixResult =
  | { status: 'success'; bugId: string; attempt: 1 | 2; reportPath?: string }
  | { status: 'ticket'; bugId: string; ticketPath: string }
  | { status: 'error'; bugId: string; error: string };

function loadBugEvent(bugId: string): BugEvent | null {
  const p = path.join(EVENTS_DIR, `${bugId}.json`);
  if (!fs.existsSync(p)) return null;
  try {
    return JSON.parse(fs.readFileSync(p, 'utf-8')) as BugEvent;
  } catch {
    return null;
  }
}

/**
 * Two-attempt fix loop. Never applies unverified fixes; rollback on failure; create ticket after 2 failures.
 */
export async function autoFixBug(bugId: string, options: AutoFixOptions = {}): Promise<AutoFixResult> {
  const bugEvent = loadBugEvent(bugId);
  if (!bugEvent) return { status: 'error', bugId, error: 'BugEvent not found' };

  const pack = buildReproPack(bugEvent);
  let reproSpecContent: string | null = null;
  if (fs.existsSync(pack.reproSpecPath)) {
    reproSpecContent = fs.readFileSync(pack.reproSpecPath, 'utf-8');
  }
  const reproSpecPathForVerify = path.relative(process.cwd(), pack.reproSpecPath);

  let lastVerificationFailure: string | null = null;

  for (const attempt of [1, 2] as const) {
    const prompt = buildFixPrompt({
      bugEvent,
      reproSpecContent,
      lastVerificationFailure,
      attemptNumber: attempt,
    });

    const getPlan = options.getPatchPlan;
    const plan = getPlan ? await getPlan(prompt, attempt) : null;
    if (!plan) {
      lastVerificationFailure = 'No automatic patch plan was generated for this bug.';
      break;
    }

    const parsed = parsePatchPlanSafe(plan as unknown);
    if (!parsed.success) continue;
    const patchPlan = parsed.data;

    const applyResult = applyPatchPlan(bugId, patchPlan);
    if (!applyResult.ok) {
      lastVerificationFailure = applyResult.reason;
      continue;
    }

    const verification = runVerification({
      bugId,
      reproSpecPath: reproSpecPathForVerify,
    });
    const allPassed = verification.length > 0 && verification.every((r) => r.passed);
    if (allPassed) {
      const reportDir = path.join(AUDIT_BASE, 'reports');
      if (!fs.existsSync(reportDir)) fs.mkdirSync(reportDir, { recursive: true });
      const reportPath = path.join(reportDir, `${bugId}.json`);
      fs.writeFileSync(
        reportPath,
        JSON.stringify(
          {
            bugId,
            status: 'fixed',
            attempt,
            timestamp: new Date().toISOString(),
            patchPlan: { summary: patchPlan.summary, risk: patchPlan.risk },
          },
          null,
          2
        ),
        'utf-8'
      );
      return { status: 'success', bugId, attempt, reportPath };
    }

    const failed = verification.find((r) => !r.passed);
    lastVerificationFailure = failed
      ? `${failed.step}: ${(failed.error ?? (failed.output || '')).slice(0, 1000)}`
      : 'Unknown';
    rollback(applyResult.backup);
  }

  const ticketPath = createTicket(bugId, {
    lastVerificationFailure: lastVerificationFailure ?? undefined,
    reproDir: pack.reproDir,
    verificationLogsDir: path.join(AUDIT_BASE, 'verify', bugId),
  });
  return { status: 'ticket', bugId, ticketPath };
}

const bugId = process.argv[2];
if (bugId) {
  autoFixBug(bugId)
    .then((r) => {
      console.log(JSON.stringify(r, null, 2));
      process.exit(r.status === 'error' ? 1 : 0);
    })
    .catch((err) => {
      console.error(err);
      process.exit(1);
    });
}
