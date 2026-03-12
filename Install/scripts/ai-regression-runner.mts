/**
 * AI regression runner: load fixtures and run validation (parseSitePlan / parseSitePlanSafe).
 * Called by scripts/ai-regression.mjs via tsx; runs in project root.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
// @ts-expect-error - path from project root
import { parseSitePlan, parseSitePlanSafe } from '../resources/js/ecommerce/schema/sitePlan';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const fixturesDir = path.join(root, 'tests', 'fixtures', 'ai');

function loadJson(name: string): unknown {
  const p = path.join(fixturesDir, name);
  if (!fs.existsSync(p)) throw new Error(`Fixture missing: ${name}`);
  return JSON.parse(fs.readFileSync(p, 'utf-8'));
}

const errors: string[] = [];

// Valid SitePlan must parse
try {
  const valid = loadJson('valid-siteplan.json') as Parameters<typeof parseSitePlan>[0];
  parseSitePlan(valid);
} catch (e) {
  errors.push(`valid-siteplan.json: expected parse to pass: ${(e as Error).message}`);
}

// Invalid SitePlan must fail (parseSitePlanSafe returns null or parse throws)
const invalidPlan = loadJson('invalid-siteplan.json');
const safeResult = parseSitePlanSafe(invalidPlan);
if (safeResult !== null) {
  errors.push('invalid-siteplan.json: expected parseSitePlanSafe to return null');
}
try {
  parseSitePlan(invalidPlan);
  errors.push('invalid-siteplan.json: expected parseSitePlan to throw');
} catch {
  // expected
}

// Valid theme shape inside SitePlan (theme is just object in our parser)
try {
  const validTheme = loadJson('valid-theme.json') as Record<string, unknown>;
  parseSitePlan({ theme: validTheme, pages: [] });
} catch (e) {
  errors.push(`valid-theme.json (wrapped in SitePlan): ${(e as Error).message}`);
}

// Valid section shape inside a page
try {
  const validSection = loadJson('valid-section.json') as Record<string, unknown>;
  parseSitePlan({
    theme: {},
    pages: [{ id: '1', route: '/', title: 'T', sections: [validSection as { id: string; type: string; props?: Record<string, unknown> }] }],
  });
} catch (e) {
  errors.push(`valid-section.json (wrapped in SitePlan): ${(e as Error).message}`);
}

if (errors.length > 0) {
  console.error('[ai-regression] FAILED:', errors.join('; '));
  process.exit(1);
}
console.log('[ai-regression] OK');
process.exit(0);
