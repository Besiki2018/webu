#!/usr/bin/env node
/**
 * Baseline Gate — minimum green gate for builder/chat changes (Task 8).
 * Must pass on every builder/chat change. Order:
 * 1. npm run typecheck
 * 2. Targeted Vitest (hook/component tests)
 * 3. Targeted PHP feature tests (builder/codegen/save)
 * 4. At least one Playwright builder smoke test (skipped if browsers not installed or no app server)
 *
 * Usage: node scripts/baseline-gate.mjs  OR  npm run baseline:gate
 * Exit: 0 if all steps pass, 1 on first failure.
 */
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

function run(cmdOrFull, argsOrOpts = [], opts = {}) {
  const useFull = typeof cmdOrFull === 'string' && cmdOrFull.includes(' ');
  const result = spawnSync(
    useFull ? cmdOrFull : cmdOrFull,
    useFull ? [] : argsOrOpts,
    {
      cwd: root,
      shell: true,
      encoding: 'utf-8',
      stdio: 'inherit',
      ...(useFull ? {} : opts),
    }
  );
  return result;
}

const steps = [
  { name: 'typecheck', run: () => run('npm', ['run', 'typecheck']) },
  {
    name: 'vitest (builder hook + schema + transport + state + tree)',
    run: () =>
      run('npx', [
        'vitest',
        'run',
        'resources/js/hooks/__tests__/useBuilderChat.test.ts',
        'resources/js/hooks/__tests__/useAiSiteEditor.test.ts',
        'resources/js/hooks/__tests__/useSessionReconnection.test.ts',
        'resources/js/lib/__tests__/aiSiteEditorPageContext.test.ts',
        'resources/js/ai/__tests__/changeSet.schema.test.ts',
        'resources/js/builder/state/__tests__/useBuilderCanvasState.test.ts',
        'resources/js/builder/visual/__tests__/treeUtils.test.ts',
        'resources/js/builder/cms/__tests__/chatBuilderMutationFlow.test.ts',
        'resources/js/builder/cms/__tests__/embeddedBuilderBridgeContract.test.ts',
        'resources/js/builder/cms/__tests__/workspaceBuilderSync.test.ts',
        'resources/js/builder/cms/__tests__/pageHydration.test.ts',
        'resources/js/builder/cms/__tests__/scheduleDraftPersist.test.ts',
        'resources/js/builder/cms/__tests__/useDraftPersistSchedule.test.ts',
        'resources/js/builder/cms/__tests__/schedulePreviewRefresh.test.ts',
      ]),
  },
  {
    name: 'php (builder feature)',
    run: () =>
      run('php', [
        'artisan',
        'test',
        'tests/Feature/Builder/BuilderStatusQuickHistoryTest.php',
      ]),
  },
  {
    name: 'playwright (builder smoke)',
    run: () => {
      const playwrightScript = process.env.WEBU_E2E_CHAT_URL
        ? 'test:e2e:builder-sync'
        : 'test:smoke';
      const result = spawnSync('npm', ['run', playwrightScript], {
        cwd: root,
        shell: true,
        encoding: 'utf-8',
        maxBuffer: 4 * 1024 * 1024,
      });
      if (result.status !== 0) {
        const out = [result.stdout, result.stderr].filter(Boolean).join('\n');
        const skip =
          out.includes("Executable doesn't exist") ||
          out.includes('playwright install') ||
          out.includes('ERR_CONNECTION_REFUSED');
        if (skip) {
          if (out.includes('ERR_CONNECTION_REFUSED')) {
            console.log(
              '[baseline-gate] Playwright skipped (no app server). Start with: npm run start'
            );
          } else {
            console.log(
              '[baseline-gate] Playwright skipped (browsers not installed). Run: npx playwright install chromium'
            );
          }
          return { status: 0 };
        }
        if (result.stdout) process.stdout.write(result.stdout);
        if (result.stderr) process.stderr.write(result.stderr);
      }
      return result;
    },
  },
];

console.log('[baseline-gate] Minimum gate for builder/chat (4 steps)\n');
let failedStep = null;
for (const step of steps) {
  console.log(`[baseline-gate] ${step.name}...`);
  const result = step.run();
  if (result.status !== 0) {
    failedStep = step.name;
    console.error(`[baseline-gate] FAILED: ${step.name}`);
    process.exit(1);
  }
}
console.log('\n[baseline-gate] All steps passed.');
process.exit(0);
