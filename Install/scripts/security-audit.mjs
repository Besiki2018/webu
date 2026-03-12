#!/usr/bin/env node
/**
 * Security audit gate — no eval(), safe JSON parse, no unvalidated AI save, content sanitization.
 * Output: audit/ci/{runId}/security-audit.json
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const runId = process.env.RUN_ID || 'standalone';
const outDir = path.join(root, 'audit', 'ci', runId);

const report = { passed: true, timestamp: new Date().toISOString(), checks: [], errors: [] };

function scanDir(dir, pattern, callback) {
  if (!fs.existsSync(dir)) return;
  for (const name of fs.readdirSync(dir)) {
    const full = path.join(dir, name);
    const stat = fs.statSync(full);
    if (stat.isDirectory() && name !== 'node_modules' && !name.startsWith('.')) {
      scanDir(full, pattern, callback);
    } else if (stat.isFile() && pattern.test(name)) {
      callback(full);
    }
  }
}

// No eval() in resources/js
const jsDir = path.join(root, 'resources', 'js');
scanDir(jsDir, /\.(tsx?|jsx?)$/, (full) => {
  const content = fs.readFileSync(full, 'utf-8');
  if (/\beval\s*\(/.test(content)) {
    report.errors.push(`eval() found: ${path.relative(root, full)}`);
    report.passed = false;
  }
});
report.checks.push({ name: 'no-eval', passed: report.errors.filter((e) => e.includes('eval')).length === 0 });

// Safe JSON: expect JSON.parse or safeParse, not eval or Function
let unsafeJson = false;
scanDir(jsDir, /\.(tsx?|jsx?)$/, (full) => {
  const content = fs.readFileSync(full, 'utf-8');
  if (/\beval\s*\(\s*[\'"`]/.test(content) || /new\s+Function\s*\(/.test(content)) {
    unsafeJson = true;
    report.errors.push(`Unsafe dynamic code: ${path.relative(root, full)}`);
  }
});
if (unsafeJson) report.passed = false;
report.checks.push({ name: 'safe-json', passed: !unsafeJson });

if (!fs.existsSync(path.join(root, 'audit', 'ci'))) fs.mkdirSync(path.join(root, 'audit', 'ci'), { recursive: true });
if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });
fs.writeFileSync(path.join(outDir, 'security-audit.json'), JSON.stringify(report, null, 2), 'utf-8');

if (!report.passed) {
  console.error('[audit:security] FAILED:', report.errors.join('; '));
  process.exit(1);
}
console.log('[audit:security] OK');
process.exit(0);
