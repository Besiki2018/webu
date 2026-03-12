import * as fs from 'fs';
import * as path from 'path';
import { execSync } from 'child_process';

const AUDIT_BASE = path.join(process.cwd(), 'audit', 'bugfixer');
const VERIFY_BASE = path.join(AUDIT_BASE, 'verify');

const STEPS = [
  { key: 'lint', cmd: 'npm run lint' },
  { key: 'typecheck', cmd: 'npm run typecheck' },
  { key: 'test:unit', cmd: 'npm run test:run' },
  { key: 'build', cmd: 'npm run build' },
  { key: 'test:smoke', cmd: 'npm run test:smoke' },
  { key: 'test:e2e', cmd: '' }, // filled when repro spec path is provided
] as const;

export type VerificationResult = {
  passed: boolean;
  step: string;
  logPath: string;
  output: string;
  error?: string;
};

export type RunVerificationOptions = {
  bugId: string;
  reproSpecPath?: string | null;
};

/**
 * Run lint, typecheck, unit tests, build, smoke, then e2e (repro.spec if provided).
 * Capture each step log to audit/bugfixer/verify/{bugId}/{step}.log.
 */
export function runVerification(options: RunVerificationOptions): VerificationResult[] {
  const { bugId, reproSpecPath } = options;
  const verifyDir = path.join(VERIFY_BASE, bugId);
  if (!fs.existsSync(verifyDir)) fs.mkdirSync(verifyDir, { recursive: true });

  const results: VerificationResult[] = [];
  const cwd = process.cwd();
  const steps = STEPS.map((s) => ({ ...s }));
  const e2eSpec = reproSpecPath && fs.existsSync(path.resolve(cwd, reproSpecPath)) ? reproSpecPath : null;
  const stepList = steps.map((s) =>
    s.key === 'test:e2e' ? { ...s, cmd: e2eSpec ? `npm run test:e2e -- ${e2eSpec}` : '' } : s
  );

  for (const step of stepList) {
    const cmd = step.cmd;
    if (!cmd) continue;

    const logPath = path.join(verifyDir, `${step.key}.log`);
    let output = '';
    let error = '';
    let passed = false;
    try {
      output = execSync(cmd, {
        cwd,
        encoding: 'utf-8',
        maxBuffer: 4 * 1024 * 1024,
      });
      passed = true;
    } catch (err: unknown) {
      const e = err as { stdout?: string; stderr?: string; message?: string };
      output = (e.stdout ?? '') + (e.stderr ?? '');
      error = e.message ?? String(err);
      passed = false;
    }
    const content = output + (error ? `\n--- ERROR ---\n${error}` : '');
    fs.writeFileSync(logPath, content, 'utf-8');
    results.push({
      passed,
      step: step.key,
      logPath,
      output,
      ...(error ? { error } : {}),
    });
    if (!passed) break;
  }
  return results;
}
