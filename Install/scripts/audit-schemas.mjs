#!/usr/bin/env node
/**
 * Schema audit gate — verify validation exists for AI-facing data (SitePlan, theme, sections, etc.).
 * Output: audit/ci/schema-audit.json (and optionally audit/ci/{runId}/ if RUN_ID env set).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const runId = process.env.RUN_ID || 'standalone';
const outDir = path.join(root, 'audit', 'ci', runId);
const outPath = path.join(outDir, 'schema-audit.json');

const report = {
  passed: true,
  timestamp: new Date().toISOString(),
  schemas: {},
  errors: [],
};

// Check that key files exist and export parse/safeParse or parse*Safe
const checks = [
  {
    name: 'SitePlan',
    path: 'resources/js/ecommerce/schema/sitePlan.ts',
    expect: ['parseSitePlan', 'parseSitePlanSafe'],
    note: 'Custom parser (no Zod); used before render/save',
  },
  {
    name: 'ChangeSet (AI ops)',
    path: 'resources/js/ai/changes/changeSet.schema.ts',
    expect: ['z.', 'safeParse', 'parse'],
    note: 'Zod schemas for AI edit operations',
  },
];

for (const c of checks) {
  const fullPath = path.join(root, c.path);
  if (!fs.existsSync(fullPath)) {
    report.schemas[c.name] = { present: false, error: 'File missing' };
    report.errors.push(`${c.name}: file missing ${c.path}`);
    report.passed = false;
    continue;
  }
  const content = fs.readFileSync(fullPath, 'utf-8');
  const hasExpect = c.expect.every((e) => content.includes(e));
  report.schemas[c.name] = {
    present: true,
    hasValidation: hasExpect,
    note: c.note,
  };
  if (!hasExpect) {
    report.errors.push(`${c.name}: expected one of ${c.expect.join(', ')} in ${c.path}`);
    report.passed = false;
  }
}

if (!fs.existsSync(path.join(root, 'audit', 'ci'))) {
  fs.mkdirSync(path.join(root, 'audit', 'ci'), { recursive: true });
}
if (!fs.existsSync(outDir)) {
  fs.mkdirSync(outDir, { recursive: true });
}
fs.writeFileSync(outPath, JSON.stringify(report, null, 2), 'utf-8');

if (!report.passed) {
  console.error('[audit-schemas] FAILED:', report.errors.join('; '));
  process.exit(1);
}
console.log('[audit-schemas] OK');
process.exit(0);
