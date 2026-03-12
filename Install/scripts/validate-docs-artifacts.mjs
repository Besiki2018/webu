#!/usr/bin/env node
/**
 * Validate docs/ artifact tree (Task 3). Ensures files required by docs-sync
 * contract tests exist so they do not fail for missing files.
 *
 * Usage: node scripts/validate-docs-artifacts.mjs [--php]
 *   --php  Also run: php artisan cms:component-library-alias-map-validate
 * Exit: 0 if all checks pass, 1 on first failure.
 */
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const required = [
  'docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json',
  'docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP_V1.md',
  'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map.v1.schema.json',
  'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map-export.v1.schema.json',
];

let failed = false;
for (const rel of required) {
  const full = path.join(root, rel);
  if (!existsSync(full)) {
    console.error(`Missing: ${rel}`);
    failed = true;
  }
}
if (failed) {
  process.exit(1);
}

const runPhp = process.argv.includes('--php');
if (runPhp) {
  const r = spawnSync('php', ['artisan', 'cms:component-library-alias-map-validate'], {
    cwd: root,
    encoding: 'utf-8',
    stdio: 'inherit',
  });
  if (r.status !== 0) {
    process.exit(1);
  }
}

console.log('Docs artifacts OK' + (runPhp ? ' (alias map validate passed)' : ''));
process.exit(0);
