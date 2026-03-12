#!/usr/bin/env node
/**
 * Task 4: Run PHPUnit in two separate lanes and report independently.
 * - Runtime lane: fast product health (excludes docs-sync tests).
 * - Docs-sync lane: architecture/qa/OpenAPI contract parity.
 *
 * Usage: node scripts/ci-phpunit-lanes.mjs [--runtime-only|--docs-only]
 *   (no flags) Run both lanes in order; exit 1 on first failure.
 *   --runtime-only  Run only runtime lane.
 *   --docs-only     Run only docs-sync lane.
 *
 * Exit: 0 if selected lane(s) pass, 1 otherwise.
 */
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const args = process.argv.slice(2);
const runtimeOnly = args.includes('--runtime-only');
const docsOnly = args.includes('--docs-only');

function runLane(name, phpunitArgs) {
  const result = spawnSync(
    'php',
    ['artisan', 'test', ...phpunitArgs],
    { cwd: root, encoding: 'utf-8', stdio: 'inherit' }
  );
  return result.status === 0;
}

let runtimeOk = true;
let docsOk = true;

if (!docsOnly) {
  console.log('\n[ci-phpunit-lanes] Runtime lane (--exclude-group=docs-sync)...\n');
  runtimeOk = runLane('runtime', ['--exclude-group=docs-sync']);
  if (!runtimeOk) {
    console.error('\n[ci-phpunit-lanes] Runtime lane FAILED.');
    if (!runtimeOnly) process.exit(1);
  } else {
    console.log('\n[ci-phpunit-lanes] Runtime lane passed.');
  }
}

if (!runtimeOnly) {
  console.log('\n[ci-phpunit-lanes] Docs-sync lane (--group=docs-sync)...\n');
  docsOk = runLane('docs-sync', ['--group=docs-sync']);
  if (!docsOk) {
    console.error('\n[ci-phpunit-lanes] Docs-sync lane FAILED.');
    process.exit(1);
  }
  console.log('\n[ci-phpunit-lanes] Docs-sync lane passed.');
}

console.log('\n[ci-phpunit-lanes] All selected lane(s) passed.');
process.exit(0);
