#!/usr/bin/env node
/**
 * AI regression gate — run validation tests with golden fixtures (no live API).
 * Output: audit/ci/{runId}/ai-regression.json when RUN_ID set, else audit/ci/ai-regression.json
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const runId = process.env.RUN_ID || 'standalone';
const outDir = path.join(root, 'audit', 'ci', runId);
const outPath = path.join(outDir, 'ai-regression.json');

if (!fs.existsSync(path.join(root, 'audit', 'ci'))) {
  fs.mkdirSync(path.join(root, 'audit', 'ci'), { recursive: true });
}
if (!fs.existsSync(outDir)) {
  fs.mkdirSync(outDir, { recursive: true });
}

const result = spawnSync(
  'npx',
  ['tsx', path.join(root, 'scripts', 'ai-regression-runner.mts')],
  { cwd: root, encoding: 'utf-8', env: { ...process.env } }
);

const report = {
  passed: result.status === 0,
  timestamp: new Date().toISOString(),
  status: result.status,
  stdout: result.stdout || '',
  stderr: result.stderr || '',
};
fs.writeFileSync(outPath, JSON.stringify(report, null, 2), 'utf-8');

if (result.status !== 0) {
  console.error('[audit:ai-regression] FAILED');
  if (result.stderr) process.stderr.write(result.stderr);
  process.exit(1);
}
console.log('[audit:ai-regression] OK');
process.exit(0);
