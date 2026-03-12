import * as fs from 'fs';
import * as path from 'path';
import type { BugEvent } from '../types.js';
import { redactObject } from '../safety/redact.js';

const AUDIT_BASE = path.join(process.cwd(), 'audit', 'bugfixer');
const REPRO_BASE = path.join(AUDIT_BASE, 'repro');

function ensureDir(dir: string): void {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

export type ReproStrategy = 'existing_e2e' | 'minimal_spec' | 'fixture_validation';

export type ReproPack = {
  bugId: string;
  strategy: ReproStrategy;
  reproDir: string;
  reproSpecPath: string;
  fixturesPath: string;
  instructionsPath: string;
};

/**
 * Decide reproduction strategy from BugEvent.
 */
export function decideStrategy(event: BugEvent): ReproStrategy {
  if (event.source === 'ai') return 'fixture_validation';
  if (event.source === 'e2e' && event.route) return 'minimal_spec';
  if (event.route && (event.source === 'frontend' || event.source === 'backend')) return 'existing_e2e';
  return 'minimal_spec';
}

/**
 * Build repro pack: repro.spec.ts, fixtures.json, instructions.md.
 * Targets websiteId/project fixture when present; sanitizes all data.
 */
export function buildReproPack(bugEvent: BugEvent): ReproPack {
  ensureDir(REPRO_BASE);
  const reproDir = path.join(REPRO_BASE, bugEvent.bugId);
  ensureDir(reproDir);

  const strategy = decideStrategy(bugEvent);
  const reproSpecPath = path.join(reproDir, 'repro.spec.ts');
  const fixturesPath = path.join(reproDir, 'fixtures.json');
  const instructionsPath = path.join(reproDir, 'instructions.md');

  // Fixtures: minimal sanitized bundle (no PII/secrets)
  const fixtures: Record<string, unknown> = {
    bugId: bugEvent.bugId,
    websiteId: bugEvent.websiteId ?? null,
    tenantId: bugEvent.tenantId ?? null,
    route: bugEvent.route ?? null,
    source: bugEvent.source,
    sanitizedContext: bugEvent.context ? redactObject(bugEvent.context as Record<string, unknown>) : {},
  };
  fs.writeFileSync(fixturesPath, JSON.stringify(fixtures, null, 2), 'utf-8');

  // instructions.md
  const instructions = [
    `# Repro: ${bugEvent.bugId}`,
    `Severity: ${bugEvent.severity} | Source: ${bugEvent.source}`,
    '',
    '## Steps to reproduce',
    `1. Ensure app is running (e.g. npm run start).`,
    bugEvent.route ? `2. Navigate to route: ${bugEvent.route}` : '2. Trigger the flow that produced the error.',
    '3. Perform the action that triggered the error (see event message below).',
    '',
    '## Event',
    '```',
    bugEvent.event,
    '```',
    bugEvent.stack ? ['## Stack (excerpt)', '```', bugEvent.stack, '```'].join('\n') : '',
  ].filter(Boolean).join('\n');
  fs.writeFileSync(instructionsPath, instructions, 'utf-8');

  // repro.spec.ts
  let specContent: string;
  if (strategy === 'fixture_validation') {
    specContent = [
      `import { test, expect } from '@playwright/test';`,
      `import * as fs from 'fs';`,
      `import * as path from 'path';`,
      `/** Regression: AI/fixture validation for ${bugEvent.bugId} */`,
      `test.describe('Bug ${bugEvent.bugId} - fixture validation', () => {`,
      `  test('load fixtures and validate shape', async () => {`,
      `    const fixturesPath = path.join(__dirname, 'fixtures.json');`,
      `    const raw = fs.readFileSync(fixturesPath, 'utf-8');`,
      `    const fixtures = JSON.parse(raw);`,
      `    expect(fixtures.bugId).toBe('${bugEvent.bugId}');`,
      `    expect(fixtures.source).toBeDefined();`,
      `  });`,
      `});`,
    ].join('\n');
  } else if (strategy === 'existing_e2e') {
    specContent = [
      `import { test, expect } from '@playwright/test';`,
      `/** Repro: ${bugEvent.bugId} - reuses app flow, targets route */`,
      `test.describe('Bug ${bugEvent.bugId}', () => {`,
      `  test('reproduce error on route', async ({ page }) => {`,
      `    const route = ${JSON.stringify(bugEvent.route || '/')};`,
      `    await page.goto(route);`,
      `    await expect(page).toHaveURL(new RegExp(route.replace(/\\//g, '\\\\/')));`,
      `    // Add steps that trigger the error (see instructions.md)`,
      `  });`,
      `});`,
    ].join('\n');
  } else {
    specContent = [
      `import { test, expect } from '@playwright/test';`,
      `/** Minimal repro spec for ${bugEvent.bugId} */`,
      `test.describe('Bug ${bugEvent.bugId}', () => {`,
      `  test('minimal repro', async ({ page }) => {`,
      `    await page.goto(${JSON.stringify(bugEvent.route || '/')});`,
      `    await expect(page).toBeDefined();`,
      `  });`,
      `});`,
    ].join('\n');
  }
  fs.writeFileSync(reproSpecPath, specContent, 'utf-8');

  return {
    bugId: bugEvent.bugId,
    strategy,
    reproDir,
    reproSpecPath,
    fixturesPath,
    instructionsPath,
  };
}
