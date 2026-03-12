#!/usr/bin/env node
/**
 * Bundle validation gate — validate demo/generated bundles (SitePlan + theme).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const runId = process.env.RUN_ID || 'standalone';
const outDir = path.join(root, 'audit', 'ci', runId);
const bundlesDir = path.join(root, 'tests', 'fixtures', 'bundles');
const latestPath = path.join(root, 'audit', 'latest-bundle.json');

function validateBundle(obj) {
  if (typeof obj !== 'object' || obj === null) return { valid: false, error: 'Not an object' };
  if (!obj.theme || typeof obj.theme !== 'object') return { valid: false, error: 'Missing or invalid theme' };
  if (!Array.isArray(obj.pages)) return { valid: false, error: 'pages must be array' };
  return { valid: true };
}

const errors = [];
const checked = [];

if (fs.existsSync(bundlesDir)) {
  for (const name of fs.readdirSync(bundlesDir)) {
    if (!name.endsWith('.json')) continue;
    const p = path.join(bundlesDir, name);
    try {
      const data = JSON.parse(fs.readFileSync(p, 'utf-8'));
      const r = validateBundle(data);
      checked.push({ file: name, valid: r.valid, error: r.error });
      if (!r.valid) errors.push(name + ': ' + r.error);
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err);
      checked.push({ file: name, valid: false, error: msg });
      errors.push(name + ': ' + msg);
    }
  }
}

if (fs.existsSync(latestPath)) {
  try {
    const data = JSON.parse(fs.readFileSync(latestPath, 'utf-8'));
    const r = validateBundle(data);
    checked.push({ file: 'audit/latest-bundle.json', valid: r.valid, error: r.error });
    if (!r.valid) errors.push('latest-bundle.json: ' + r.error);
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    checked.push({ file: 'latest-bundle.json', valid: false, error: msg });
    errors.push('latest-bundle.json: ' + msg);
  }
}

if (checked.length === 0) {
  checked.push({ file: '(none)', valid: true, note: 'No bundle fixtures found; gate passes.' });
}

const passed = errors.length === 0;
const report = { passed, timestamp: new Date().toISOString(), checked, errors };

if (!fs.existsSync(path.join(root, 'audit', 'ci'))) fs.mkdirSync(path.join(root, 'audit', 'ci'), { recursive: true });
if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });
fs.writeFileSync(path.join(outDir, 'bundle-validate.json'), JSON.stringify(report, null, 2), 'utf-8');

if (!passed) {
  console.error('[audit:bundle-validate] FAILED:', errors.join('; '));
  process.exit(1);
}
console.log('[audit:bundle-validate] OK');
process.exit(0);
