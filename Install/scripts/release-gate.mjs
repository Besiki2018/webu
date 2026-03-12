#!/usr/bin/env node
/**
 * Release Gate — runs quality gates in order, stops on first failure.
 * Output: audit/ci/{runId}/{step}.log, summary.json, summary.md
 * Used by: CI (.github/workflows/release-gate.yml), optional pre-push (.husky/pre-push).
 * Pre-push bypass: SKIP_RELEASE_GATE=1 git push
 */
import fs from 'node:fs';
import path from 'node:path';
import { execSync, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const runId = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
const auditCi = path.join(root, 'audit', 'ci', runId);

const GATES = [
  { key: 'lint', cmd: 'npm run lint:ci' },
  { key: 'typecheck', cmd: 'npm run typecheck' },
  { key: 'test:unit', cmd: 'npm run test:unit' },
  { key: 'build', cmd: 'npm run build' },
  { key: 'audit:schemas', cmd: 'npm run audit:schemas' },
  { key: 'audit:ai-regression', cmd: 'npm run audit:ai-regression' },
  { key: 'test:smoke', cmd: 'npm run test:smoke' },
  { key: 'audit:bundle-validate', cmd: 'npm run audit:bundle-validate' },
  { key: 'audit:security', cmd: 'npm run audit:security' },
];

if (!fs.existsSync(path.join(root, 'audit', 'ci'))) {
  fs.mkdirSync(path.join(root, 'audit', 'ci'), { recursive: true });
}
fs.mkdirSync(auditCi, { recursive: true });

const results = [];
let failedStep = null;

for (const gate of GATES) {
  const logPath = path.join(auditCi, `${gate.key}.log`);
  console.log(`[release-gate] ${gate.key}...`);
  const result = spawnSync(gate.cmd, [], {
    cwd: root,
    shell: true,
    encoding: 'utf-8',
    maxBuffer: 8 * 1024 * 1024,
    env: { ...process.env, RUN_ID: runId },
  });
  const out = [result.stdout || '', result.stderr || ''].filter(Boolean).join('\n');
  fs.writeFileSync(logPath, out, 'utf-8');

  const isSmokeSkip = gate.key === 'test:smoke' && result.status !== 0 &&
    (out.includes('Executable doesn\'t exist') || out.includes('playwright install'));
  const passed = result.status === 0 || isSmokeSkip;

  results.push({
    step: gate.key,
    passed,
    status: result.status,
    skipped: isSmokeSkip || undefined,
  });
  if (result.status !== 0) {
    if (isSmokeSkip) {
      console.log(`[release-gate] test:smoke skipped (Playwright browsers not installed; run: npx playwright install chromium)`);
    } else {
      failedStep = gate.key;
      console.error(`[release-gate] FAILED: ${gate.key}`);
      break;
    }
  }
}

const allPassed = !failedStep;
const summary = {
  runId,
  passed: allPassed,
  failedStep: failedStep ?? null,
  results,
  timestamp: new Date().toISOString(),
};
fs.writeFileSync(path.join(auditCi, 'summary.json'), JSON.stringify(summary, null, 2), 'utf-8');

const md = [
  `# Release Gate — ${runId}`,
  '',
  `**Result:** ${allPassed ? 'PASSED' : 'FAILED'}`,
  failedStep ? `**Failed step:** ${failedStep}` : '',
  '',
  '## Steps',
  ...results.map((r) => `- ${r.passed ? '✅' : '❌'} ${r.step}${r.skipped ? ' (skipped: no browser)' : ''}`),
].filter(Boolean).join('\n');
fs.writeFileSync(path.join(auditCi, 'summary.md'), md, 'utf-8');

console.log(allPassed ? '[release-gate] All gates passed.' : `[release-gate] Blocked at: ${failedStep}`);
process.exit(allPassed ? 0 : 1);
