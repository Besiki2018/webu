/**
 * CLI: read JSON lines from file (or stdin), run intakeError for each, output bug IDs + severity/frequency.
 * Usage: npx tsx src/bugfixer/cli/intakeFromFile.ts [path]
 */
import * as fs from 'fs';
import { intakeError } from '../intake/normalizeError.js';
import type { RawErrorSource } from '../types.js';

function main(): void {
  const pathArg = process.argv[2];
  const input = pathArg
    ? fs.readFileSync(pathArg, 'utf-8')
    : fs.readFileSync(0, 'utf-8');
  const lines = input.split('\n').filter((l) => l.trim());
  const results: { bugId: string; severity: string; frequency: number }[] = [];
  for (const line of lines) {
    try {
      const raw = JSON.parse(line) as RawErrorSource;
      const event = intakeError(raw);
      results.push({
        bugId: event.bugId,
        severity: event.severity,
        frequency: event.frequency ?? 1,
      });
    } catch {
      // skip invalid lines
    }
  }
  console.log(JSON.stringify(results));
}

main();
